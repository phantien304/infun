# Voucher reward — checklist go-live & kiểm chứng (Bước 6)

> Trạng thái code (2026-07-18): Bước 1–3 (service + observer + job + mail) đã xong,
> Bước 4/4b không cần code, **Bước 5 (CMS) tạm bỏ**. Tài liệu này gom nốt phần
> vận hành + kiểm thử để chạy thật. Sandbox không có PHP/DB nên các lệnh dưới đây
> phải chạy ở máy có Herd/XAMPP.

## 0. Bug đã sửa trong đợt này

Interface `VoucherRewardRuleRepositoryInterface` và `VoucherRewardService` gọi
`decrementRewardRuleCount()`, nhưng repo Eloquent lại đặt tên `decrementGrantedCount()`.
Lệch tên ⇒ lớp repo không thoả interface (fatal khi container bind) và luồng
**revoke** gãy. Đã đổi tên method trong
`app/Repositories/Eloquent/VoucherRewardRuleRepository.php` thành
`decrementRewardRuleCount()`. Cần chạy `php -l` + test để xác nhận.

## 1. Migrate 2 bảng mới

```
php artisan migrate
```

Tạo `voucher_reward_rule` và `voucher_reward_grant`
(`database/migrations/2026_07_17_000000_*`, `..._000001_*`).

## 2. Clear cache repository (BẮT BUỘC)

Binding Interface→Eloquent auto theo convention được cache ở
`bootstrap/cache/repositories.php`. Nếu file này đã cache trước khi thêm 2 repo mới
(và trước khi sửa tên method), binding cũ vẫn được dùng ⇒ đổi tên không có tác dụng.

```
php artisan repository:clear
```

Thêm bước này vào ritual deploy.

## 3. Lint cú pháp

Sandbox không có PHP, chưa chạy được `php -l`. Chạy ở máy có PHP cho các file:

```
php -l app/Services/Voucher/VoucherRewardService.php
php -l app/Repositories/Eloquent/VoucherRewardRuleRepository.php
php -l database/seeders/VoucherRewardRuleSeeder.php
php -l tests/Unit/Services/VoucherRewardServiceTest.php
```

## 4. Seed rule đầu tiên

Trước khi có CMS, tạo rule "đơn ≥ 1tr tặng 50k" bằng seeder (idempotent, chạy lại
không clobber `granted_count`):

```
php artisan db:seed --class=Database\\Seeders\\VoucherRewardRuleSeeder
```

Chỉnh `min_order_total` / `reward_amount` / `quota_total` / `max_per_user` trong
`database/seeders/VoucherRewardRuleSeeder.php` nếu chương trình khác.

## 5. Chạy test tự động

Bộ unit test mới: `tests/Unit/Services/VoucherRewardServiceTest.php` (PHPUnit + Mockery,
mock 3 repo, không đụng DB). Bao phủ:

- đủ 1tr → tặng + gửi mail; dưới 1tr → không tặng
- observer bắn 2 lần → không phát đôi (grant đã có + voucher Active)
- reactivate khi đơn complete lại; rollback khi trạng thái đổi giữa chừng
- hết quota; đua duplicate-key bị nuốt; `max_per_user`
- khách vãng lai (`user_id` NULL) đếm theo email
- revoke khi hủy; bỏ qua revoke khi voucher đã dùng một phần

```
php artisan test --filter=VoucherRewardServiceTest
```

## 6. Kiểm thử thủ công (đầu-cuối, COD)

1. Đặt đơn COD tổng ≥ 1.000.000đ.
2. Vào CMS chuyển đơn sang trạng thái **hoàn tất** (`order_complete_status_all`).
3. Kỳ vọng: 1 voucher 50k xuất hiện cho email khách, đúng HSD (30 ngày),
   mail tặng voucher gửi đi, `voucher_reward_rule.granted_count` +1,
   có 1 row trong `voucher_reward_grant`.
4. Đăng nhập tài khoản khách → voucher hiện ở "voucher của tôi".
5. **Hủy** đơn đó → voucher chuyển `Revoked`, `granted_count` −1.
6. Cho complete lại → voucher `Active` trở lại (giữ nguyên HSD gốc), `granted_count` +1.
7. Ca đã dùng: redeem một phần voucher rồi hủy đơn → voucher KHÔNG bị thu hồi,
   có log lỗi cho CS xử lý tay.

## 7. Lưu ý vận hành (COD)

Điều kiện để observer bắn đúng:

- Mọi chỗ đổi `order_status_id` phải qua Eloquent `save()`
  (`orderRepo->upsertOrder`), KHÔNG `DB::table('orders')->update()`.
- Với COD, "hoàn tất" phải nghĩa là **đã thu tiền**. Nếu sau này tích hợp webhook
  ĐVVC, trạng thái "delivered" nên map vào trạng thái trung gian, đối soát COD xong
  mới đẩy lên hoàn tất (tránh tặng voucher cho đơn bị bom hàng).

## Chưa làm (ngoài phạm vi đợt này)

- Bước 5 — CMS quản trị rule (CRUD + permission + xem grant/quota).
- Bước 4b tự động — tích hợp webhook/poll ĐVVC để tự chuyển trạng thái đơn.
