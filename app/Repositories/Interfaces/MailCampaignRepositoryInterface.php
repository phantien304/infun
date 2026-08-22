<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\MailCampaign;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface MailCampaignRepositoryInterface extends BaseRepositoryInterface
{
    public function create(array $data): MailCampaign;

    /**
     * Chèn một lô người nhận. Dùng insertOrIgnore để trùng email giữa "nhóm
     * khách" và "file upload" không làm vỡ UNIQUE (campaign_id, email).
     *
     * @param  array<int, array{email: string, user_id: int|null}>  $rows
     * @return int  số dòng thực sự được chèn (đã trừ trùng)
     */
    public function insertRecipients(int $campaignId, array $rows): int;

    /** ID người nhận đang chờ gửi, dùng để chia lô job. */
    public function pendingRecipientIds(int $campaignId): Collection;

    /**
     * Khoá và lấy các dòng người nhận còn pending trong lô — bên trong
     * transaction của job gửi.
     */
    public function claimRecipients(array $recipientIds): Collection;

    public function markRecipientSent(int $recipientId): void;

    public function markRecipientFailed(int $recipientId, string $error): void;

    public function bumpCounters(int $campaignId, int $sent, int $failed): void;

    public function countPendingRecipients(int $campaignId): int;

    public function finalize(int $campaignId): void;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?MailCampaign;
}
