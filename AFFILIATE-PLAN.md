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

affiliate_click (
    id BIGINT PK AUTO,
    affiliate_id INT FK CASCADE,
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
Migration 5 bảng + sửa orders + seed settings (+ flush schema cache như
migration reward). Models: Affiliate, AffiliateClick, AffiliateConversion,
AffiliatePayout. Enums: `AffiliateStatus`, `ConversionStatus` (theo convention
`App\Enums`). Repository + interface theo pattern QueryableRepository.

### Phase 2 — Click tracking + Attribution (~1.5 ngày)
- **Middleware** `TrackAffiliateRef` (nhóm web): thấy `?ref=CODE` hợp lệ
  (affiliate active) → insert `affiliate_click` + set cookie first-party
  `aff_ref` = {code, click_id} TTL theo config. Last-click: ghi đè cookie cũ.
  Throttle: cùng session + affiliate trong 30 phút không ghi click mới
  (chống spam log).
- **AffiliateAttributionService**: `resolve(): ?Attribution` — ưu tiên
  (1) coupon của KOL trong `session.applied_coupons` qua `affiliate_coupon`,
  (2) cookie `aff_ref` còn hạn. Trả affiliate_id + click_id/coupon_code.
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
- Link generator: nhập URL sản phẩm → ra link `?ref=CODE`; hiện coupon
  được cấp. Copy button.

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

## 4. Điểm cần chốt trước khi code (business)

1. Commission tính trên giá trị nào: sau discount trước ship (đề xuất) hay subtotal?
2. % mặc định bao nhiêu, có phân theo ngành hàng ngay từ đầu không (→ cần commission_rule sớm)?
3. Cookie window 30 ngày OK? Hold period 7 ngày OK?
4. Payout thủ công chuyển khoản (đề xuất đợt đầu) hay tích hợp cổng chi hộ?
5. KOL có coupon riêng ngay đợt đầu không (Phase 2 coupon-attribution phụ thuộc)?

**Tổng ước lượng: ~8-9 ngày dev** (Phase 1-4 là lõi ~5.5 ngày, Phase 5-6 hoàn thiện).
