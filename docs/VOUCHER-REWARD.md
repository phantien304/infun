# Voucher reward — "đơn ≥ 1tr tặng voucher 50k" — kế hoạch tích hợp luồng order

> Đã có (2026-07-17): migration `voucher_reward_rule` + `voucher_reward_grant`,
> entity `VoucherRewardRule`/`VoucherRewardGrant`, enum `VoucherRewardRuleStatus`,
> repo `VoucherRewardRepository` (auto-bind theo convention Interfaces→Eloquent).
> Còn lại là nối vào luồng order — các bước dưới đây.

**Nguyên tắc:** KHÔNG đụng `CreateOrderService` — tặng không xảy ra lúc đặt hàng.
Tặng khi đơn chuyển sang trạng thái hoàn tất, thu hồi khi hủy — đúng pattern
`OrderRewardObserver` (điểm tích hợp có sẵn, đã chứng minh chạy được với reward points).

---

## Bước 1 — `App\Services\Voucher\VoucherRewardService`

- [ ] `grantForOrder(Orders $order): void`
  1. `listRunningRules()`; mỗi rule chạy độc lập:
  2. Check rẻ ngoài transaction: `matchesOrderTotal((float) $order->total)`
     (cơ sở tính = `orders.total` — tổng cuối; muốn đổi sang sub_total thì sửa 1 chỗ này),
     `grantExists(rule, order)` → có rồi thì skip (đơn lật status nhiều lần),
     `max_per_user`: `countGrantsForUser(rule, user_id, email)` ≥ limit → skip.
  3. Transaction:
     a. `incrementGrantedCount(rule)` — affected 0 = hết quota → skip (không exception, đơn vẫn bình thường).
     b. `createGrant([...])` — race 2 process cùng đơn: UNIQUE `uq_vrg_rule_order` ném
        duplicate-key → catch `QueryException` code 23000 → `decrementGrantedCount` + skip.
     c. Tạo voucher: `code` = gen 12-16 ký tự A-Z0-9 (retry nếu trúng `uq_voucher_code`),
        `amount = reward_amount`, `date_expire = today + reward_expire_days`,
        `to_email/to_name` = email/full_name của đơn, `from_name` = tên shop,
        `order_id = $order->id`, `status = VoucherStatus::Active`.
     d. Update `voucher_id` vào grant.
  4. `DB::afterCommit`: dispatch job gửi mail voucher (bước 3).
- [ ] `revokeForOrder(Orders $order): void` — đơn hủy sau khi đã tặng:
  `grantsForOrder(order)` → mỗi grant: voucher `redeemed_balance == 0` →
  `status = Revoked` + `decrementGrantedCount`; đã dùng một phần → KHÔNG revoke,
  log warning cho CS xử tay (khách đã tiêu tiền thưởng của đơn bị hủy).
- [ ] Complete lại sau khi hủy (lật qua lại): grant đã tồn tại → nếu voucher đang
  `Revoked` và chưa dùng → reactivate `Active` + `incrementGrantedCount` lại
  (guard quota như 3a).

## Bước 2 — Observer nối vào luồng order

- [ ] `App\Observers\OrderVoucherRewardObserver` — copy đúng skeleton `OrderRewardObserver`:
  `updated()` + `wasChanged('order_status_id')`;
  status ∈ `getConfigDb('order_complete_status_all')` → `grantForOrder`;
  status == `getConfigDb('order_cancel_status_id')` → `revokeForOrder`.
- [ ] Đăng ký trong `AppServiceProvider::registerObservers()` (cạnh dòng
  `Orders::observe(OrderRewardObserver::class)`).
- [ ] ⚠ Observer chỉ bắn khi update qua Eloquent `save()`. Rà các chỗ đổi
  `order_status_id` bằng `DB::table('orders')->update(...)` (nếu có) — những chỗ đó
  observer câm. `OrderRewardObserver` sống được với hiện trạng nên rủi ro thấp,
  nhưng vẫn grep xác nhận một lần.
- [ ] Thân observer nên chỉ `dispatch(new GrantVoucherRewardJob($order->id))`
  (queue redis có sẵn) thay vì chạy sync — CMS đổi status hàng loạt không bị chậm;
  job idempotent nhờ `uq_vrg_rule_order` nên retry vô hại.

## Bước 3 — Thông báo cho khách

- [ ] Job/Mailable gửi mã voucher vào `to_email`, set `voucher.sent_at` sau khi gửi
  (cột có sẵn). Template nêu rõ: mệnh giá, mã, HSD, "áp dụng cho đơn tiếp theo".
- [ ] Khách đăng nhập: voucher tự hiện ở "voucher của tôi" (`listForEmail` có sẵn) — không cần code thêm.

## Bước 4 — Chiều SỬ DỤNG: không sửa gì

Voucher tặng là row `voucher` bình thường → flow redeem hiện tại
(`VoucherService`, `incrementRedeemed` conditional UPDATE chống over-redeem,
`scopeRedeemable`, dùng dần nhiều đơn) chạy nguyên. Chỉ cần 1 quyết định nghiệp vụ:
**đơn dùng voucher 50k có được tính `min_order_total` cho lần tặng tiếp theo không?**
(hiện tại: có, vì so trên `orders.total` sau khi đã trừ voucher — nếu muốn chặn
"xài voucher vẫn được tặng tiếp" thì đổi cơ sở tính ở Bước 1.2).

## Bước 5 — CMS quản trị rule (phase sau, không chặn go-live)

- [ ] `cmsApiResource('voucher-reward-rule', ...)` + permission — CRUD rule,
  xem `granted_count`/quota, danh sách grant per rule.
- [ ] Trước khi có CMS: seed rule đầu tiên bằng migration/seeder hoặc tinker.

## Bước 6 — Vận hành & kiểm chứng

- [ ] `php artisan migrate` (2 bảng mới).
- [ ] `php artisan repository:clear` — nếu `bootstrap/cache/repositories.php` đã cache
  thì binding mới KHÔNG được nhận cho đến khi clear/re-cache. Deploy ritual thêm bước này.
- [ ] `php -l` bằng Herd (sandbox không có PHP): 2 migration, 2 entity, enum,
  interface + repo, service, observer, job.
- [ ] Test Pest: grant đúng khi đủ 1tr; không grant dưới 1tr; không phát đôi khi
  observer bắn 2 lần; hết quota giữa chừng; max_per_user; revoke khi hủy;
  reactivate khi complete lại; khách vãng lai (user_id NULL, đếm theo email).
- [ ] Case thủ công: đặt đơn COD 1tr → CMS chuyển hoàn tất → voucher xuất hiện
  đúng mệnh giá/HSD, mail đi, `granted_count` +1 → hủy đơn → voucher Revoked,
  `granted_count` −1.

---

## Thứ tự làm

1 (service) → 2 (observer + job) → 3 (mail) → 6 (verify) là lõi go-live.
4 = không làm gì. 5 làm sau khi lõi chạy.
