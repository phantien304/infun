# Kế hoạch hệ thống Affiliate — từ database đến vận hành

> Phác thảo 2026-07-10. Hiện trạng: `orders` còn 4 cột vestigial OpenCart
> (`affiliate_id`, `marketing_id`, `tracking`, `commission`) — không bảng
> tracking, không code reference. Xây mới theo mô hình TMĐT hiện đại
> (Shopee Affiliate / AccessTrade / Impact), tái dùng các pattern sẵn có
> của codebase (observer lifecycle như reward, ledger + enum, settings DB).

## 1. Mô hình vận hành (modern affiliate flow)

```
Đăng ký → Duyệt → Nhận mã ref + link/coupon riêng
   ↓
Khách click link (?ref=CODE) → ghi click log → set cookie first-party (30d)
   hoặc: khách nhập coupon riêng của KOL (attribution không cần click)
   ↓
Khách đặt hàng → ghi conversion PENDING (gắn order + click/coupon nguồn)
   ↓
Đơn giao thành công → conversion APPROVED (qua observer, giống reward)
Đơn hủy / refund   → conversion REJECTED
   ↓
Cuối kỳ (tháng) → gom conversion approved → payout (min threshold) → PAID
```

Quyết định chuẩn hiện đại được chọn (đổi được bằng config):
- **Attribution: last-click wins**, cookie window 30 ngày (chuẩn ngành).
- **Coupon-code attribution song song** — bắt buộc ở VN: KOL đăng story/TikTok,
  khách không click link mà gõ mã. Mỗi affiliate map được N coupon.
- **Commission trên đơn giao thành công** (không phải đơn đặt) + hold period
  X ngày chờ hết hạn đổi trả rồi mới cho vào kỳ payout.
- **Self-referral block**: affiliate tự mua bằng link mình → không tính.

## 2. Thiết kế database (Phase 1)

### 2.1. Bảng mới

```sql
affiliate (
    id INT PK AUTO,
    user_id INT UNIQUE FK->user,        -- affiliate là 1 user của hệ thống
    code VARCHAR(32) UNIQUE,            -- mã ref trên URL (?ref=CODE)
    status TINYINT,                     -- enum AffiliateStatus: 0 pending / 1 active / 2 suspended
    commission_rate DECIMAL(5,2),       -- % mặc định; NULL = dùng config global
    payment_info JSON,                  -- bank / momo / zalopay để chi trả
    clicks_count INT DEFAULT 0,         -- aggregate cache (observer đếm, như rating_avg)
    approved_at TIMESTAMP NULL,
    created_at, updated_at
)

affiliate_link (                        -- link rút gọn kiểu s.shopee.vn (xem mục 2.4)
    id INT PK AUTO,
    affiliate_id INT FK CASCADE,
    slug VARCHAR(10) UNIQUE,            -- /l/{slug}, random base62 8 ký tự
    destination_url VARCHAR(512),       -- URL đích trong site (validate cùng domain!)
    product_id INT NULL,                -- deep link SP (để report top SP theo KOL)
    sub_id VARCHAR(64) NULL,            -- KOL tự đặt để tách kênh (bio IG / TikTok...)
    clicks_count INT DEFAULT 0,         -- aggregate cache
    created_at, updated_at
)

affiliate_click (
    id BIGINT PK AUTO,
    affiliate_id INT FK CASCADE,
    affiliate_link_id INT NULL FK,      -- NULL nếu click từ ?ref= trực tiếp
    click_token VARCHAR(16) UNIQUE,     -- random token gắn lên URL đích (uls_trackid
                                        -- của Shopee) — khóa join click ↔ conversion
    sub_id VARCHAR(64) NULL,            -- copy từ link lúc click (link sửa sau không lệch data)
    session_id VARCHAR(64),             -- match session checkout
    ip VARCHAR(45), user_agent VARCHAR(255),
    landing_url VARCHAR(512), referrer VARCHAR(512),
    utm_source VARCHAR(64), utm_medium VARCHAR(64), utm_campaign VARCHAR(64),
    product_id INT NULL,                -- deep link tới SP cụ thể
    created_at,
    INDEX (affiliate_id, created_at), INDEX (session_id)
)   -- volume lớn: job prune > 90 ngày (giữ conversion là đủ cho đối soát)

affiliate_conversion (
    id INT PK AUTO,
    affiliate_id INT FK,
    order_id INT UNIQUE FK,             -- 1 đơn chỉ attribution 1 affiliate (last-click)
    click_id BIGINT NULL FK,            -- nguồn: click...
    coupon_code VARCHAR(20) NULL,       -- ...hoặc coupon (một trong hai)
    order_total INT,                    -- snapshot lúc tạo (sau discount, trước ship)
    commission INT,                     -- tiền hoa hồng đã tính, snapshot
    commission_rate DECIMAL(5,2),       -- rate áp lúc đó (audit)
    status TINYINT,                     -- enum ConversionStatus: 0 pending / 1 approved / 2 rejected / 3 paid
    payout_id INT NULL FK,
    approved_at TIMESTAMP NULL,
    created_at, updated_at,
    INDEX (affiliate_id, status)
)

affiliate_payout (
    id INT PK AUTO,
    affiliate_id INT FK,
    period VARCHAR(7),                  -- '2026-07'
    amount INT,                         -- SUM(commission) các conversion trong kỳ
    status TINYINT,                     -- 0 pending / 1 paid / 2 cancelled
    paid_at TIMESTAMP NULL, note TEXT,
    created_at, updated_at,
    UNIQUE (affiliate_id, period)
)

affiliate_coupon (                      -- map coupon riêng của KOL
    affiliate_id INT FK,
    coupon_id INT FK->coupon,
    PRIMARY KEY (affiliate_id, coupon_id)
)
```

Phase sau (không làm ngay): `commission_rule` (override % theo
category/product — như product_reward override earn rate).

### 2.4. Link rút gọn + auto-UTM (cơ chế kiểu Shopee)

Shopee sinh link dạng `s.shopee.vn/xxx` → 302 redirect sang URL đích kèm:
`uls_trackid=...&utm_source=an_<affiliate>&utm_medium=affiliates&utm_campaign=id_<link>&utm_term=<click_token>&utm_content=<sub_id>`.
Hai giá trị của cơ chế này, ta làm tương tự:

1. **Click log server-side tại redirect** — route `GET /l/{slug}`:
   lookup `affiliate_link` (cache theo slug) → insert `affiliate_click`
   (sinh `click_token` random) → set cookie `aff_ref` → 302 sang
   `destination_url` với params tự gắn:
   ```
   ?aff=<affiliate.code>&aff_click=<click_token>
   &utm_source=aff_<affiliate.code>&utm_medium=affiliates
   &utm_campaign=link_<slug>&utm_content=<sub_id>
   ```
   Click được ghi TRƯỚC khi landing load → không phụ thuộc JS/cookie
   phía trang đích; UTM chuẩn để GA/analytics tự bắt.
2. **Token trên URL thay vì chỉ cookie** — middleware `TrackAffiliateRef`
   ở landing đọc `aff_click` token → chỉ set/refresh cookie (KHÔNG log
   click lần 2 — đã log ở redirect). Attribution lúc checkout ưu tiên:
   coupon KOL > cookie {click_token}. Token là khóa join chính xác
   click → conversion (kể cả khi cookie bị xóa giữa chừng, còn token
   trong session).
3. **Sub-id cho KOL** — `utm_content` để KOL tự tách kênh của họ
   (bio IG / TikTok / group Zalo); copy vào `affiliate_click.sub_id`
   lúc click để report breakdown cho KOL trong dashboard.

Lưu ý bảo mật: `destination_url` phải validate **cùng domain** khi tạo link
(chặn open-redirect); slug random base62 (không đoán được); route redirect
throttle theo IP.

### 2.2. Dọn cột `orders`

- `affiliate_id` — GIỮ, thành FK thật -> `affiliate.id` (đúng mục đích gốc).
- `tracking` — DROP (OpenCart dùng làm mã ref; giờ nằm ở affiliate.code
  + affiliate_conversion).
- `commission` — DROP (snapshot nằm ở affiliate_conversion.commission).
- `marketing_id` — DROP (OpenCart marketing campaign; UTM đã log ở
  affiliate_click; nếu sau cần campaign tracking nội bộ thì làm bảng riêng).

### 2.3. Settings seed (bảng `setting`, admin đổi trong CMS)

| Key | Default | Ý nghĩa |
|---|---|---|
| `config_affiliate_enabled` | 0 | bật/tắt toàn hệ |
| `config_affiliate_commission_rate` | 5.00 | % hoa hồng mặc định |
| `config_affiliate_cookie_days` | 30 | attribution window |
| `config_affiliate_hold_days` | 7 | chờ sau giao mới đủ điều kiện payout |
| `config_affiliate_min_payout` | 200000 | ngưỡng chi trả tối thiểu (đ) |
| `config_affiliate_auto_approve` | 0 | duyệt đăng ký tự động hay tay |

## 3. Các phase triển khai

### Phase 1 — Database + Models + Enums (~1 ngày)
Migration 6 bảng + sửa orders + seed settings (+ flush schema cache như
migration reward). Models: Affiliate, AffiliateLink, AffiliateClick,
AffiliateConversion, AffiliatePayout. Enums: `AffiliateStatus`,
`ConversionStatus` (theo convention `App\Enums`). Repository + interface
theo pattern QueryableRepository.

### Phase 2 — Short link + Click tracking + Attribution (~2 ngày)
- **Route redirect** `GET /l/{slug}` (withoutMiddleware cache_page, throttle):
  log click + sinh click_token + set cookie + 302 kèm auto-UTM (mục 2.4).
- **Middleware** `TrackAffiliateRef` (nhóm web): đọc `aff_click=TOKEN`
  (từ redirect) hoặc `?ref=CODE` trực tiếp (link tay, không qua shortener —
  trường hợp này mới insert click) → set cookie first-party `aff_ref`
  TTL theo config. Last-click: ghi đè cookie cũ. Throttle: cùng session +
  affiliate trong 30 phút không ghi click mới (chống spam log).
- **AffiliateAttributionService**: `resolve(): ?Attribution` — ưu tiên
  (1) coupon của KOL trong `session.applied_coupons` qua `affiliate_coupon`,
  (2) cookie `aff_ref` {click_token} còn hạn. Trả affiliate_id + click_id/coupon_code.
- Self-referral: attribution.user_id === affiliate.user_id → bỏ qua.

### Phase 3 — Conversion + Lifecycle (~1 ngày)
- `CreateOrderService::create()`: sau khi ghi order → gọi
  `AffiliateConversionService::record(orderId, total)` — resolve attribution,
  tính commission (rate của affiliate ?? config), insert conversion PENDING,
  set `orders.affiliate_id`. Idempotent theo order_id (unique).
- **Mở rộng `OrderRewardObserver`** thành observer chung hoặc thêm
  `OrderAffiliateObserver`: status ∈ `order_complete_status_all` → conversion
  APPROVED + approved_at; status = `order_cancel_status_id` → REJECTED.
  (Đúng pattern reward đã chạy — mọi flow đều qua upsertOrder/Eloquent.)

### Phase 4 — Cổng affiliate cho user (~2 ngày)
Account section (tái dùng layout account như trang Điểm thưởng):
- Đăng ký affiliate (form + điều khoản) → pending → admin duyệt
  (hoặc auto theo config).
- Dashboard: tổng click / conversion / commission theo trạng thái, biểu đồ
  theo ngày, bảng conversion phân trang.
- Link generator (kiểu Shopee): dán URL bất kỳ của site (hoặc chọn SP)
  + sub_id tùy chọn → tạo `affiliate_link` → trả short link `/l/{slug}`.
  Copy button + QR. Bảng link đã tạo kèm clicks_count, breakdown theo sub_id.
  Hiện coupon được cấp.

### Phase 5 — Admin CMS (~2 ngày)
- Duyệt/suspend affiliate, chỉnh commission_rate riêng, gán coupon cho KOL.
- Báo cáo: top affiliate, conversion theo kỳ, đối soát click→order.
- Payout: nút "chốt kỳ" → gom conversion APPROVED đã qua hold_days, đạt
  min_payout → tạo affiliate_payout + set conversion PAID. Export CSV
  chuyển khoản. (CMS React infun_cms — làm sau cùng vì CMS order management
  cũng chưa có; tạm thời Phase 5 có thể chạy bằng artisan command chốt kỳ.)

### Phase 6 — Anti-fraud + hoàn thiện (~1 ngày, optional đợt đầu)
- Dedupe click theo IP+UA window; cap click/ngày/affiliate.
- Cảnh báo conversion rate bất thường.
- Job prune affiliate_click > 90 ngày.
- Unit tests: attribution precedence (coupon > cookie), self-referral,
  idempotency conversion, lifecycle approve/reject.

## 4. Business ĐÃ CHỐT (2026-07-10)

1. Commission tính trên: **sau discount, TRƯỚC ship**.
2. % admin chỉnh được (setting), **có phân theo ngành hàng** →
   `affiliate_commission_rule` làm ngay từ Phase 1. Precedence:
   affiliate.commission_rate > rule theo category của item > config global.
   Tính theo TỪNG item trong đơn (Phase 3).
3. Cookie window **30 ngày, last-click** — link KOL khác click sau override.
4. Payout: **chuyển khoản trước** (payment_info JSON chứa được cả bank lẫn
   ZaloPay — tích hợp chi hộ ZaloPay sau, checkout đã có sẵn liên kết).
5. **Có coupon riêng cho KOL** — affiliate_coupon từ Phase 1.
6. Short link: **domain chính `site.vn/l/{slug}`**.

**Tổng ước lượng: ~9-10 ngày dev** (Phase 1-4 là lõi ~6 ngày, Phase 5-6 hoàn thiện).

## 5. Tiến độ

### Phase 1 — DONE 2026-07-10

Migration `2026_07_10_000001_create_affiliate_tables.php`:
- 7 bảng: affiliate, affiliate_link, affiliate_click, affiliate_conversion,
  affiliate_payout, affiliate_coupon, affiliate_commission_rule. Kiểu FK khớp
  legacy: user.id BIGINT UNSIGNED, coupon/category/orders.id INT SIGNED →
  PK affiliate dùng INT SIGNED (orders.affiliate_id int(11) FK được).
- orders: DROP tracking/commission/marketing_id; affiliate_id → FK
  `fk_orders_affiliate` SET NULL (dọn 0/orphan về NULL trước).
- Seed 6 setting (mục 2.3) + flush cache setting + schema cache orders.
- Enums: `AffiliateStatus` (Pending/Active/Suspended + canTrack()),
  `AffiliateConversionStatus` (Pending/Approved/Rejected/Paid + isPayable()),
  `AffiliatePayoutStatus` (Pending/Paid/Cancelled).
- Models đủ 7 theo Base conventions (AffiliateClick timestamps=false
  append-only như StockMovement — created_at set tay khi insert;
  AffiliateCoupon composite PK như ProductReward; Affiliate casts
  payment_info=array).
- Repositories (binding auto theo convention AppServiceProvider):
  `AffiliateRepository` (findActiveByCode / findByUserId /
  findActiveByCouponCode / register — sinh code 8 ký tự unique, auto-approve
  theo config), `AffiliateLinkRepository` (findBySlug / createLink slug
  base62 8 ký tự / getListForAffiliate), `AffiliateConversionRepository`
  (recordConversion nhận DTO `App\Data\Affiliate\AffiliateConversionData`
  — idempotent theo order_id / approveForOrder / rejectForOrder — sẵn cho
  observer Phase 3).

### Phase 2 — DONE 2026-07-10

- **Core config** `core.config.affiliate`: cookie `aff_ref` (chứa click_token),
  param `ref` / `aff_click`, throttle 30 phút.
- **Route** `GET l/{slug}` (name affiliate.redirect, regex [A-Za-z0-9]{1,10},
  middleware maintenance + throttle:60,1, KHÔNG cache_page) →
  `AffiliateRedirectController@show`: lookup link → affiliate phải Active +
  hệ bật (không thì vẫn redirect, chỉ bỏ track) → throttle-reuse click cũ
  hoặc `recordClick` (token 12 ký tự, tăng clicks_count affiliate+link) →
  queue cookie aff_ref TTL config_affiliate_cookie_days → 302 destination
  kèm auto-UTM (aff, aff_click, utm_source=aff_<code>, utm_medium=affiliates,
  utm_campaign=link_<slug>, utm_content=sub_id). Chống open-redirect:
  `safeDestination()` chỉ nhận path tương đối hoặc cùng host app.url.
- **Middleware** `TrackAffiliateRef` (append web group, bootstrap/app.php):
  GET + hệ bật; `aff_click` → validate token (findValidByToken: còn hạn +
  affiliate Active) + refresh cookie, KHÔNG log lần 2; `?ref=CODE` →
  findActiveByCode + throttle-reuse hoặc log click mới + cookie. Last-click:
  cookie ghi đè. Mọi exception nuốt + logError — tracking không phá page.
- **AffiliateAttributionService** (app/Services/Affiliate): `resolve()` —
  coupon KOL trong session.applied_coupons (qua findActiveByCouponCode)
  ưu tiên trước cookie token; self-referral (user hiện tại = chủ affiliate)
  → bỏ. Trả `AffiliateAttribution` (affiliateId, affiliateUserId,
  commissionRate riêng nếu có, clickId | couponCode).
- **DTOs** `App\Data\Affiliate`: AffiliateClickData (fromRequest() dựng
  context ip/UA/UTM), AffiliateAttribution. Repo mới AffiliateClickRepository
  (recordClick + aggregate, findRecent throttle, findValidByToken).

### Phase 3-6 — chưa làm (xem mục 3)
