<?php

namespace App\Http\Controllers\Api\Cms;

use App\Data\Cms\BlogData;
use App\Http\Requests\Cms\BlogRequest;
use App\Models\Entities\Blog;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class BlogController extends BaseCmsController
{
    protected string $permission = 'blog';

    public function __construct(
        private readonly BlogRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return BlogData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(BlogRequest $request)
    {
        $blog = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(BlogData::from($blog), 'blog_created');
    }

    public function show(Blog $blog)
    {
        return respondSuccess(BlogData::from($blog->load(['descriptions', 'user'])));
    }

    public function update(BlogRequest $request, Blog $blog)
    {
        $blog = $this->repo->saveFromCms($blog, $request->validated());

        return respondSuccess(BlogData::from($blog), 'blog_updated');
    }

    public function destroy(Blog $blog)
    {
        $this->repo->deleteByIds([$blog->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $blog = $this->repo->restoreById((int) $id);
        abort_if($blog === null, 404);

        return respondSuccess(BlogData::from($blog), 'blog_restored');
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
