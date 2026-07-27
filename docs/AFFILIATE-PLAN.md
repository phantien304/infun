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

### Phase 3 — DONE 2026-07-10

- **AffiliateConversionService** (app/Services/Affiliate) — `record(orderId,
  items, totalData)`:
  - Base = đọc từ totalData (sub_total + các dòng âm coupon:/reward/voucher:)
    → luôn khớp orders_total; KHÔNG gồm ship/coupon_freeship/gifts. Đúng chốt
    "sau discount, TRƯỚC ship".
  - Rate per-item: affiliate.commission_rate (flat cả đơn) >
    affiliate_commission_rule theo category của SP (nhiều category có rule →
    lấy CAO NHẤT) > config_affiliate_commission_rate. Discount phân bổ tỷ lệ
    (factor = base/subtotal). commission_rate lưu = rate hiệu dụng
    (commission/base×100, 2dp) để audit.
  - Ghi conversion PENDING (DTO AffiliateConversionData, idempotent) + set
    `orders.affiliate_id` bằng query-builder update (không fire observer).
- **CreateOrderService::writeAffiliateConversion** — gọi trong transaction
  create(), wrap try/catch + logError: lỗi tracking không phá flow đặt hàng.
- **OrderAffiliateObserver** (đăng ký AppServiceProvider, chạy cạnh
  OrderRewardObserver): status ∈ order_complete_status_all → approveForOrder
  (Pending→Approved + approved_at, hold_days tính từ đây); status =
  order_cancel_status_id → rejectForOrder (Pending/Approved→Rejected,
  không đụng Paid).

### Review Phase 1-3 (2026-07-11) — 3 fix đã áp

- **CachePage**: strip param tracking (aff/aff_click/ref/utm_*/gclid/fbclid)
  khỏi cache key + sort query — hết rác cache per-click, khách affiliate HIT
  cache chung (`normalizedUrl()`).
- **IP click log**: `getIpVisitor()` (CF / X-Forwarded-For aware) thay
  REMOTE_ADDR — chuẩn bị cho dedupe anti-fraud Phase 6.
- **Throttle click per-link**: `findRecent(..., ?int $linkId)` — link khác
  của cùng KOL log riêng, clicks_count/sub_id per-link không lệch;
  `?ref=` trực tiếp match whereNull(affiliate_link_id).

### Phase 4 — DONE 2026-07-11

- **AffiliatePortalService** (app/Services/Affiliate): dashboard() gom stat
  theo status + chartSeries 30 ngày (labels đủ ngày, trống = 0); createLink()
  validate destination NGAY LÚC TẠO — `normalizeDestination()` chuẩn về path
  tương đối, chỉ nhận cùng host app.url, chặn /l/ (loop) + /account/ +
  /checkout/ + /api/, chặn scheme lạ; `detectProductId()` bắt `-p{id}`
  (buildUrl convention) để lưu deep-link SP.
- **Repos mở rộng**: AffiliateConversionRepository (getListForAffiliate,
  getStatusTotals group by status, countByDay), AffiliateClickRepository
  (countByDay, countBySubId — breakdown kênh KOL).
- **AffiliateAccountController** + routes `account/affiliate` (nhóm auth):
  index (4 trạng thái → 4 view: disabled/register/status/dashboard),
  register (POST, AffiliateRegisterRequest: điều khoản accepted + bank info
  → payment_info JSON, auto/pending theo config), links (generator + bảng),
  createLink (POST, throttle 20/1m).
- **Views** resources/web/views/account/affiliate/: register (stat intro +
  form bank + điều khoản tóm tắt), status (pending/suspended), dashboard
  (stat cards 4 ô, Chart.js line 30 ngày click+đơn, coupon KOL copy được,
  bảng conversion phân trang, nguồn = coupon/link), links (form tạo + ref
  URL chung + bảng link với copy/QR modal + breakdown sub_id). Chart.js
  4.4.3 + qrcodejs CDN (đã verify URL sống); copy dùng navigator.clipboard
  + fallback execCommand. Menu account thêm "Tiếp thị liên kết".
- **i18n**: messages.affiliate.*, breadcrumbs.account_affiliate,
  seo.account.affiliate (+ bổ sung seo.account.rewards còn thiếu từ trước).

### Phase 5 — SKIPPED (TODO, quyết định 2026-07-11)

Bỏ qua đợt này vì CMS React (infun_cms) chưa có order management. Việc cần
làm khi quay lại:

- [ ] CMS: duyệt/suspend affiliate, chỉnh commission_rate riêng, gán coupon
      cho KOL (bảng affiliate_coupon), CRUD affiliate_commission_rule.
- [ ] CMS: báo cáo top affiliate, conversion theo kỳ, đối soát click→order.
- [ ] Payout: nút "chốt kỳ" — gom conversion APPROVED đã qua
      `config_affiliate_hold_days` (tính từ approved_at), đạt
      `config_affiliate_min_payout` → tạo affiliate_payout + set conversion
      PAID (payout_id). Export CSV chuyển khoản.
- [ ] Tạm thời có thể chạy artisan command chốt kỳ (chưa viết — cân nhắc
      `affiliate:close-period {period?}` khi cần).
- [ ] Admin hiện vẫn thao tác tay được qua DB: duyệt = set
      affiliate.status=1 + approved_at.

### Phase 6 — DONE 2026-07-11 (anti-fraud + tests; làm trước Phase 5)

- **Anti-fraud đặt TRONG `recordClick`** (mọi caller tự được bảo vệ, trả
  `?AffiliateClick`):
  1. Dedupe cùng (affiliate, link, IP, UA) trong `affiliate.dedupe_minutes`
     (10') → tái dùng click cũ — chặn bot xóa cookie/session bơm click
     (dedupe không phụ thuộc session, scope per-link khớp fix review).
  2. Cap `affiliate.max_clicks_per_day` (2000, 0 = tắt) → trả null: caller
     bỏ track nhưng VẪN redirect/load page (controller vẫn gắn UTM cho
     analytics, chỉ bỏ cookie + aff_click token).
- **Commands** (schedule trong routes/console.php):
  - `affiliate:prune-clicks` (daily 02:10) — xóa click >
    `affiliate.click_retention_days` (90) theo chunk; từ chối chạy nếu
    retention < cookie window (phá attribution).
  - `affiliate:health-check` (daily 08:00) — cảnh báo (console + logError,
    KHÔNG tự khóa): CR > `health.max_cr_percent` (15%, mẫu ≥ 50 click) nghi
    coupon/self-referral abuse; ≥ 500 click 0 đơn nghi click spam; ≥ 5 đơn
    toàn coupon 0 click thì nhắc liếc nguồn mã. Ngưỡng ở core config
    `affiliate.health.*`.
- **Tests** (sqlite :memory:, tự dựng schema, fake ConfigDbService — pattern
  StockOversellTest; PHP không có trong sandbox dev nên CHƯA chạy, cần
  `php artisan test` trên máy thật):
  - `tests/Feature/Affiliate/AffiliateAttributionTest` — coupon > cookie,
    cookie fallback (+rate riêng), token quá hạn, suspended, self-referral
    (chặn cả coupon lẫn cookie, có control case; ép web-context bằng
    reflection vì getCurrentUserId trả null khi runningInConsole), hệ tắt.
  - `tests/Feature/Affiliate/AffiliateConversionLifecycleTest` — idempotent
    theo order_id, commission ≤ 0 không ghi, Pending→Approved (+approved_at,
    gọi lặp vô hại), Pending/Approved→Rejected, Paid bất khả xâm,
    Rejected không approve lại được.
  - `tests/Feature/Affiliate/AffiliateClickAntiFraudTest` — dedupe IP+UA
    xuyên session, IP khác vẫn log, dedupe scope per-link, cap/ngày,
    cap=0 không giới hạn, findRecent throttle theo (session, link).
  - `tests/Unit/AffiliateDestinationTest` — normalizeDestination (cùng
    domain, chặn open-redirect///scheme lạ/loop /l//private, không dính oan
    slug tương tự) + detectProductId theo convention buildUrl.
  - Fix kèm theo ở `Base::getNextInsertId()`: case sqlite bỏ trống → mọi
    insert không truyền id nhận 1 → UNIQUE violation từ row thứ 2 (lý do
    StockOversellTest trước đây phải dùng id tường minh). Bổ sung
    MAX(key)+1 cho sqlite — chỉ ảnh hưởng test, MySQL giữ nguyên.

### Hardening 2026-07-11 (rà lần 2 sau khi test suite xanh)

Bug tiềm ẩn đã fix:
1. **500 trên route redirect với query hostile** — `/l/{slug}?utm_source[]=x`
   → `request()->query()` trả array → TypeError vào DTO ?string; utm dài
   > 64 ký tự → QueryException (cột varchar 64). Controller KHÔNG wrap
   try/catch (khác middleware) nên nổ 500 thật. Fix:
   `AffiliateClickData::fromRequest` guard is_string + truncate 64;
   `TrackAffiliateRef::stringQuery()` tương tự.
2. **Fragment nuốt params** — destination legacy có `#section`: params gắn
   sau fragment bị browser coi là fragment → mất aff_click/UTM.
   `safeDestination` strip `#...` trước khi validate.
3. **Race đăng ký** — double-submit song song vượt qua findByUserId → UNIQUE
   user_id nổ 500. `register()` catch UniqueConstraintViolationException →
   trả row đã tạo.
4. **Cap số link/affiliate** — `affiliate.max_links` (200, 0 = tắt) +
   `countForAffiliate`; throttle route chỉ chặn theo phút, không chặn spam
   bảng dài hạn. Message `messages.affiliate.link_limit`.

Test bổ sung:
- `AffiliateConversionServiceTest` — TIỀN NONG end-to-end: base sau
  discount trước ship (reward/voucher trừ, coupon_freeship/shipping không),
  precedence rate KOL > rule category (max) > global, phân bổ discount theo
  factor, snapshot + orders.affiliate_id, coupon attribution ghi
  coupon_code, không attribution không ghi, gọi lặp không dup.
- `AffiliatePortalTest` — đăng ký pending/auto-approve/idempotent, tạo link
  + detect product_id, chặn domain lạ, cap max_links, chartSeries đủ 30
  ngày (ngày trống = 0) + stat cards.
- `CachePageKeyTest` — key bỏ param tracking, sort ổn định, khách affiliate
  và khách thường chung key.
(Schema test thêm orders / product_category / affiliate_commission_rule.)

### Fix 2026-07-11 (test suite bắt được — round 2)

5. **Double json-encode `payment_info` (bug PRODUCTION, test bắt được)** —
   `Base::save()` refill raw attributes (`setRawAttributes([])->fill($attrs)`)
   → cast `array` encode LẦN 2 → DB lưu `"\"{\\\"bank_name\\\"...}\""`, đọc
   ra string thay vì array (Phase 5 export payout sẽ hỏng). Fix: bỏ cast,
   dùng accessor/mutator idempotent `Affiliate::paymentInfo()` (set: array
   mới encode, string giữ nguyên; get: decode + tự sửa data cũ double-encoded).
   ⚠️ Audit codebase-wide (2026-07-11): CHỈ affiliate.payment_info dính —
   không model Entities nào khác có cast array/json/object/collection,
   không classic mutator set*Attribute, không encrypted/hashed trên Base
   (password hash ở service; App\Models\User skeleton không dùng, không
   extends Base). Enum/datetime/decimal cast idempotent với refill → an toàn.
   **Fix central chống tái phát**: `Base::setAttribute()` gán thẳng chuỗi
   JSON hợp lệ cho cột json-castable (không encode lại khi refill) +
   regression test `tests/Feature/BaseJsonCastTest` (probe model ẩn danh:
   create, update-lại, set array mới, null). Model tương lai dùng json cast
   sẽ không dính nữa.
   Data hiện có: bảng affiliate tạo 2026-07-10 (dump 25/06 chưa có) — nếu
   môi trường dev đã có row đăng ký, check & sửa:
   `SELECT id FROM affiliate WHERE payment_info LIKE '"%';`
   `UPDATE affiliate SET payment_info = JSON_UNQUOTE(payment_info) WHERE payment_info LIKE '"%';`
   (accessor cũng đã tự decode data cũ khi đọc).
6. Schema test `orders` thiếu `deleted_at`/`timestamps` — Orders dùng
   SoftDeletes (global scope) và update qua model query tự touch
   updated_at. Đã bổ sung + assertion round-trip DB cho payment_info.

### Còn lại: Phase 5 (TODO ở trên).
