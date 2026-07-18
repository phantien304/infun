# Voucher reward — "đơn ≥ 1tr tặng voucher 50k" — kế hoạch tích hợp luồng order

> Đã có (2026-07-17): migration `voucher_reward_rule` + `voucher_reward_grant`,
> entity `VoucherRewardRule`/`VoucherRewardGrant`, enum `VoucherRewardRuleStatus`,
> 2 repo tách theo bảng: `VoucherRewardRuleRepository` (rule + quota) và
> `VoucherRewardGrantRepository` (grant) — auto-bind theo convention Interfaces→Eloquent.
> Còn lại là nối vào luồng order — các bước dưới đây.

**Nguyên tắc:** KHÔNG đụng `CreateOrderService` — tặng không xảy ra lúc đặt hàng.
Tặng khi đơn chuyển sang trạng thái hoàn tất, thu hồi khi hủy — đúng pattern
`OrderRewardObserver` (điểm tích hợp có sẵn, đã chứng minh chạy được với reward points).

---

## Bước 1 — `App\Services\Voucher\VoucherRewardService` ✅ (2026-07-17)

- [x] `grantForOrder()`: check rẻ ngoài transaction (`matchesOrderTotal` trên
  `orders.total`, `rewardGrantExists`, `max_per_user` theo user_id/email) → transaction
  {chiếm quota conditional → tạo voucher → insert grant}. Race duplicate-key →
  **rollback cả transaction** (quota + voucher tự hoàn, KHÔNG decrement tay như
  plan gốc — increment nằm cùng transaction nên rollback là đủ, sạch hơn).
- [x] `revokeForOrder()`: `revokeUnused` conditional (status Active + redeemed = 0)
  → affected > 0 mới `decrementRewardRuleCount`; đã dùng một phần → log CS xử tay.
- [x] Reactivate khi đơn complete lại: chiếm quota lại → `reactivateRevoked`
  conditional; noop → sentinel exception rollback quota. Giữ date_expire gốc.
- [x] Code gen: 12 ký tự, alphabet bỏ 0/O/1/I, pre-check `findByCode` + retry 5 lần,
  `uq_voucher_code` là chốt cuối.
- [x] Repo hỗ trợ thêm vào `VoucherRepository`(+interface): `createVoucher`,
  `revokeUnused`, `reactivateRevoked` (pattern `markFullyUsed` có sẵn).
- ⚠ **Voucher tặng KHÔNG set `order_id`** (khác plan gốc): `resolveVoucher` coi
  voucher có `order_id` là "voucher MUA trong đơn" → đòi link `orders_voucher`
  (legacy) → sẽ không redeem được. Truy vết đơn gốc = `voucher_reward_grant.order_id`.
- Mail: hook `notifyGranted()` để sẵn — TODO Bước 3.

## Bước 2 — Observer nối vào luồng order ✅ (2026-07-17)

- [x] `OrderVoucherRewardObserver` (skeleton `OrderRewardObserver`) — đăng ký trong
  `AppServiceProvider::registerObservers()`. Thân observer CHỈ dispatch
  `SyncVoucherRewardJob` (`$afterCommit = true` — chờ transaction đổi status commit).
- [x] `SyncVoucherRewardJob`: đọc lại đơn từ DB lúc worker chạy rồi mới quyết định
  grant/revoke theo status HIỆN TẠI (không tin status lúc dispatch — đơn lật
  nhiều lần vẫn khớp trạng thái cuối). Idempotent, retry vô hại.
- [x] Đã grep xác nhận: KHÔNG có `DB::table('orders')->update(...)` nào trong `app/`;
  mọi chỗ đổi status (CMS API, hủy đơn `AccountService`, callback ZaloPay
  `CheckoutPaymentService`) đều qua `orderRepo->upsertOrder` = `fill()->save()`
  Eloquent → observer bắt đủ.

## Bước 3 — Thông báo cho khách ✅ (2026-07-17)

- [x] `VoucherRewardSendEmailJob`: chỉ gửi khi voucher còn Active + claim `sent_at`
  bằng conditional UPDATE (`whereNull('sent_at')`) → dispatch/retry trùng không gửi 2 lần.
- [x] `JobMailer::voucherReward` + view `web::mailer.voucher_reward` — tận dụng khối
  trans `mailer.voucher` có sẵn, thêm key `expire` + `reward_reason`; config
  `job_mailer.voucher.from/sender` trong `config/module/web/config.php`.
- [x] Khách đăng nhập: voucher tự hiện ở "voucher của tôi" (`listForEmail` có sẵn) — không cần code thêm.

## Bước 4 — Chiều SỬ DỤNG: không sửa gì

Voucher tặng là row `voucher` bình thường → flow redeem hiện tại
(`VoucherService`, `incrementRedeemed` conditional UPDATE chống over-redeem,
`scopeRedeemable`, dùng dần nhiều đơn) chạy nguyên. Chỉ cần 1 quyết định nghiệp vụ:
**đơn dùng voucher 50k có được tính `min_order_total` cho lần tặng tiếp theo không?**
(hiện tại: có, vì so trên `orders.total` sau khi đã trừ voucher — nếu muốn chặn
"xài voucher vẫn được tặng tiếp" thì đổi cơ sở tính ở Bước 1.2).

## Bước 4b — Nguồn chuyển trạng thái đơn (đặc biệt COD)

Observer không quan tâm AI đổi status — 2 con đường cùng đi qua 1 cửa:

- **Tay (go-live ngay)**: CMS React gọi API infun → Eloquent `save()` → observer bắn.
  Admin xác nhận "giao thành công + đã thu COD" → grant; khách bom hàng → set hủy → revoke.
- **Tự động (phase sau)**: tích hợp API/webhook ĐVVC (GHN/GHTK/VTP). Schema legacy ĐÃ CÓ
  khung map `carrier_order_status` ↔ `orders_status_carrier_order` (chưa có code dùng):
  webhook carrier → tra map → update `order_status_id` qua Eloquent → observer voucher
  tự bắn, KHÔNG sửa code voucher. Carrier không có webhook → scheduled command poll
  API mỗi X phút cho đơn đang giao.
- ⚠ Ràng buộc cho cả 2: (1) đổi status phải qua Eloquent `save()`, không
  `DB::table()->update()`; (2) với COD, "hoàn tất" (`order_complete_status_all`) phải
  nghĩa là ĐÃ THU TIỀN — trạng thái "delivered" từ carrier nên map vào trạng thái
  trung gian, đối soát COD xong mới đẩy lên hoàn tất.

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
