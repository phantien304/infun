@php
    $byParent = collect();
    foreach ($categories ?? [] as $cat) {
        $byParent->put($cat->parent_id, ($byParent->get($cat->parent_id) ?? collect())->push($cat));
    }
    $rootCategories = $byParent->get(0) ?? collect();
@endphp

@if ($rootCategories->isNotEmpty())
    <div class="sidebar-widget mb-30">
        <h5 class="section-title style-1 mb-30 wow fadeIn animated">Danh mục</h5>
        <ul class="category-tree list-none m-0 p-0" data-cat-tree>
            @foreach ($rootCategories as $root)
                @include('web::category.structure._side_bar_node', [
                    'node' => $root,
                    'byParent' => $byParent,
                    'depth' => 0,
                ])
            @endforeach
        </ul>
    </div>
@endif
