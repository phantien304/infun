<?php

namespace App\Http\Controllers\Api\Cms\Affiliate;

use App\Data\Cms\AffiliateConversionItemData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * CMS đối soát hoa hồng — chỉ ĐỌC.
 *
 * Không có sửa/xoá là chủ ý: vòng đời conversion do hệ thống lái
 * (OrderAffiliateObserver approve/reject theo trạng thái đơn; chốt kỳ đẩy
 * sang Paid). Cho admin sửa tay một dòng ở đây là tạo ra con số không khớp
 * với đơn hàng thật — và đó chính là thứ màn đối soát sinh ra để phát hiện.
 */
class AffiliateConversionController extends BaseCmsController
{
    protected string $permission = 'affiliate-conversion';

    public function __construct(
        private readonly AffiliateConversionRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return AffiliateConversionItemData::collect(
            $this->repo->listForCms($request),
            PaginatedDataCollection::class,
        );
    }
}
