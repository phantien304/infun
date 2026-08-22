<?php

namespace App\Http\Controllers\Api\Cms\Customer;

use App\Data\Cms\ContactData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Models\Entities\Contact;
use App\Repositories\Interfaces\ContactRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class ContactController extends BaseCmsController
{
    protected string $permission = 'contact';

    public function __construct(
        private readonly ContactRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return ContactData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function show($id)
    {
        $contact = $this->repo->getForCms((int) $id);
        abort_if($contact === null, 404);

        return respondSuccess(ContactData::fromModel($contact));
    }

    public function destroy(Contact $contact)
    {
        $this->repo->deleteByIds([$contact->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $contact = $this->repo->restoreById((int) $id);
        abort_if($contact === null, 404);

        return respondSuccess(ContactData::fromModel($contact), 'contact_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $affected = $data['action'] === 'delete'
            ? $this->repo->deleteByIds($data['ids'])
            : $this->repo->restoreByIds($data['ids']);

        return respondSuccess(['affected' => $affected]);
    }
}
