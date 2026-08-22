<?php

namespace App\Http\Controllers\Api\Cms\Marketing;

use App\Data\Cms\MailCampaignData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\MailCampaignRequest;
use App\Repositories\Interfaces\MailCampaignRepositoryInterface;
use App\Services\Marketing\MailCampaignService;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * CMS gửi mail marketing — thay cho
 * `App\Http\Controllers\Cms\MailController` của mt219.
 *
 * Khác bản cũ:
 *  - mt219 chỉ có `save()` (gửi) và một `send()` rỗng trả "Success". Ở đây
 *    `store()` là hành động gửi, còn `index()`/`show()` là LỊCH SỬ chiến dịch
 *    — bản cũ không lưu gì để mà xem lại.
 *  - Không dùng `cmsApiResource`: chiến dịch đã gửi thì không sửa, không xoá
 *    được (nó là biên bản). Chỉ đăng ký đúng 3 route thật sự có nghĩa.
 */
class MailCampaignController extends BaseCmsController
{
    protected string $permission = 'mail-campaign';

    public function __construct(
        private readonly MailCampaignRepositoryInterface $repo,
        private readonly MailCampaignService $service,
    ) {
    }

    public function index(Request $request)
    {
        return MailCampaignData::collect(
            $this->repo->listForCms($request),
            PaginatedDataCollection::class,
        );
    }

    public function show($id)
    {
        $campaign = $this->repo->getForCms((int) $id);
        abort_if($campaign === null, 404);

        return respondSuccess(MailCampaignData::fromModel($campaign));
    }

    /**
     * Tạo chiến dịch và đẩy job gửi theo lô. Trả về ngay — không chờ gửi
     * xong, vì "gửi tất cả khách" có thể mất hàng chục phút.
     */
    public function store(MailCampaignRequest $request)
    {
        $campaign = $this->service->createAndDispatch(
            $request->validated(),
            $request->file('file'),
            $request->user()?->id,
        );

        return respondCreated(MailCampaignData::fromModel($campaign), 'mail_campaign_queued');
    }
}
