<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\OrdersStatus;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface OrdersStatusRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Dropdown "Tình trạng đơn hàng" theo locale hiện tại, sắp theo id.
     * ĐẶT TÊN getAll() (không phải listAll()) — BaseRepositoryInterface đã
     * khai listAll(?Request $request = null): Eloquent\Collection; trùng tên
     * khác chữ ký (không tham số, trả về Support\Collection) gây PHP Fatal
     * error "Declaration ... must be compatible with ..." ngay khi bootstrap
     * (đã ăn lỗi thật lúc test /order-status trên trình duyệt — mọi request
     * đều "Failed to fetch" vì server 500 ngay từ lúc autoload interface).
     * Đúng theo convention getAll() mà LengthClass/Manufacturer/TaxClass/
     * WeightClassRepositoryInterface đã dùng cho đúng lý do này.
     */
    public function getAll(): Collection;

    // ----- CMS (admin) -----

    /**
     * 1 dòng/status cho lưới CMS — lọc theo ngôn ngữ admin mặc định (bảng
     * orders_status lưu i18n bằng composite key (id, language_code) ngay
     * trên chính bảng, KHÔNG có bảng *_description riêng như Filter/
     * Manufacturer). Status chưa có dòng ở ngôn ngữ mặc định sẽ không hiện
     * — chấp nhận được vì form CMS luôn bắt buộc nhập tên ở ngôn ngữ mặc định.
     */
    public function listForCms(Request $request): LengthAwarePaginator;

    /** Toàn bộ dòng (mọi ngôn ngữ, kể cả đã xoá mềm) của 1 status id. */
    public function getForCms(int $id): Collection;

    /**
     * Tạo mới (id=null, tự để DB auto-increment ở dòng ngôn ngữ đầu) hoặc
     * sửa (upsert theo từng ngôn ngữ, xoá mềm dòng ngôn ngữ nào bị để trống
     * tên — giống pattern saveDescription() của ReviewCriteria/ReviewTag).
     * Trả về id của status (mới tạo hoặc đang sửa).
     */
    public function saveFromCms(?int $id, array $data): int;

    /**
     * Xoá qua vòng lặp model (KHÔNG mass-delete builder) — OrdersStatus dùng
     * HasCascadeRelations ($destroyRelations = ['ordersStatusCarrierOrders']),
     * cascade chỉ chạy qua event `deleting` của TỪNG model instance, mass
     * delete() ở query builder sẽ bỏ qua cascade và để sót pivot mồ côi
     * (cùng loại bug đã gặp ở ReviewRepository::deleteByIds()).
     */
    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): Collection;
}
