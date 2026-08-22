<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Contact;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ContactRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ContactRepository extends QueryableRepository implements ContactRepositoryInterface
{
    public function model(): string
    {
        return Contact::class;
    }

    public function saveContact(array $data): ?Contact
    {
        try {
            return $this->resetModel()->create([
                'name'    => $data['name'] ?? null,
                'email'   => $data['email'] ?? null,
                'phone'   => $data['phone'] ?? null,
                'service' => $data['service'] ?? null,
                'content' => $data['content'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            logError($exception->getMessage());

            return null;
        }
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['name', 'email', 'company', 'phone', 'address', 'service', 'created_at'], true)
            ? $request->input('sort')
            : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->query();

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%')
                    ->orWhere('phone', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    /**
     * Mở chi tiết 1 contact = đã đọc — mirror _beforeRender() ở mt219
     * (Cms\ContactController), tự set is_read=1 ngay khi admin xem.
     */
    public function getForCms(int $id): ?Contact
    {
        $contact = $this->resetModel()->withTrashed()->find($id);
        if ($contact && ! $contact->is_read) {
            $contact->is_read = 1;
            $contact->save();
        }

        return $contact;
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Contact
    {
        $contact = $this->resetModel()->withTrashed()->find($id);
        $contact?->restore();

        return $contact;
    }
}
