<?php

namespace App\Repositories\Eloquent;

use App\Enums\MailCampaignRecipientStatus;
use App\Enums\MailCampaignStatus;
use App\Models\Entities\MailCampaign;
use App\Models\Entities\MailCampaignRecipient;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\MailCampaignRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MailCampaignRepository extends QueryableRepository implements MailCampaignRepositoryInterface
{
    public function model(): string
    {
        return MailCampaign::class;
    }

    public function create(array $data): MailCampaign
    {
        $campaign = new MailCampaign();
        $campaign->fill($data);
        $campaign->save();

        return $campaign;
    }

    public function insertRecipients(int $campaignId, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $payload = [];
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $payload[] = [
                'campaign_id' => $campaignId,
                'email'       => $email,
                'user_id'     => $row['user_id'] ?? null,
                'status'      => MailCampaignRecipientStatus::Pending->value,
            ];
        }

        if (empty($payload)) {
            return 0;
        }

        return DB::table('mail_campaign_recipient')->insertOrIgnore($payload);
    }

    public function pendingRecipientIds(int $campaignId): Collection
    {
        return MailCampaignRecipient::where('campaign_id', $campaignId)
            ->pending()
            ->orderBy('id')
            ->pluck('id');
    }

    public function claimRecipients(array $recipientIds): Collection
    {
        if (empty($recipientIds)) {
            return collect();
        }

        return MailCampaignRecipient::whereIn('id', $recipientIds)
            ->pending()
            ->orderBy('id')
            ->get();
    }

    public function markRecipientSent(int $recipientId): void
    {
        MailCampaignRecipient::where('id', $recipientId)
            ->pending()
            ->update([
                'status'  => MailCampaignRecipientStatus::Sent->value,
                'error'   => null,
                'sent_at' => now(),
            ]);
    }

    public function markRecipientFailed(int $recipientId, string $error): void
    {
        MailCampaignRecipient::where('id', $recipientId)
            ->pending()
            ->update([
                'status' => MailCampaignRecipientStatus::Failed->value,
                'error'  => mb_substr($error, 0, 255),
            ]);
    }

    /**
     * Cộng dồn bộ đếm bằng increment thô (không đọc-rồi-ghi): nhiều worker
     * chạy song song trên cùng chiến dịch, read-modify-write sẽ nuốt mất số
     * của nhau.
     */
    public function bumpCounters(int $campaignId, int $sent, int $failed): void
    {
        if ($sent === 0 && $failed === 0) {
            return;
        }

        DB::table('mail_campaign')
            ->where('id', $campaignId)
            ->update([
                'sent_count'   => DB::raw('sent_count + ' . (int) $sent),
                'failed_count' => DB::raw('failed_count + ' . (int) $failed),
                'status'       => MailCampaignStatus::Sending->value,
                'started_at'   => DB::raw('COALESCE(started_at, NOW())'),
                'updated_at'   => now(),
            ]);
    }

    public function countPendingRecipients(int $campaignId): int
    {
        return MailCampaignRecipient::where('campaign_id', $campaignId)->pending()->count();
    }

    /**
     * Chốt trạng thái khi không còn ai chờ gửi. Toàn bộ đều lỗi ⇒ Failed, để
     * admin nhìn danh sách là biết chiến dịch hỏng chứ không phải "đã xong".
     */
    public function finalize(int $campaignId): void
    {
        $campaign = $this->resetModel()->find($campaignId);
        if (! $campaign || $this->countPendingRecipients($campaignId) > 0) {
            return;
        }

        $campaign->status = ((int) $campaign->sent_count === 0 && (int) $campaign->failed_count > 0)
            ? MailCampaignStatus::Failed->value
            : MailCampaignStatus::Completed->value;
        $campaign->finished_at = now();
        $campaign->save();
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sortable = ['id', 'subject', 'created_at', 'recipient_count', 'sent_count'];
        $sort     = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order    = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted  = (int) $request->input('deleted_at', -1);
        $keyword  = trim((string) $request->input('keyword', ''));
        $perPage  = max(1, (int) $request->input('per_page', 50));

        // Liệt kê cột tường minh để BỎ `message` (longtext chứa cả bài
        // HTML) khỏi màn danh sách — thêm cột mới vào bảng thì nhớ thêm ở đây.
        $query = $this->resetModel()->newQuery()
            ->with('author')
            ->select([
                'id', 'subject', 'send_to', 'user_group_id',
                'recipient_count', 'sent_count', 'failed_count',
                'status', 'created_by', 'started_at', 'finished_at',
                'created_at', 'updated_at', 'deleted_at',
            ]);

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('subject', 'like', '%' . $keyword . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    /**
     * Chi tiết kèm 200 người nhận: ưu tiên dòng LỖI trước (status desc) — mở
     * chi tiết một chiến dịch thường là để xem cái gì không gửi được.
     */
    public function getForCms(int $id): ?MailCampaign
    {
        return $this->resetModel()
            ->withTrashed()
            ->with([
                'author',
                'recipients' => fn ($q) => $q->orderByDesc('status')->orderBy('id')->limit(200),
            ])
            ->find($id);
    }
}
