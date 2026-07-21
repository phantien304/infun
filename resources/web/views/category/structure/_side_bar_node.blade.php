@php
    $children = $byParent->get($node->id) ?? collect();
    $hasChildren = $children->isNotEmpty();
@endphp
<li class="cat-tree-item block mb-3 last:mb-0" data-cat-node data-cat-id="{{ $node->id }}"
    data-cat-url="{{ $node->url }}">
    <div
        class="cat-tree-row flex items-center justify-between gap-2 px-4 py-2 rounded-md border transition
                border-gray-200 hover:border-brand hover:shadow">
        <a href="{{ $node->url }}" title="{!! $node->title !!}"
            class="cat-tree-link flex-1 min-w-0 truncate text-sm leading-6 hover:text-brand text-gray-800">
            {!! $node->title !!}
        </a>
        @if ($hasChildren)
            <button type="button"
                class="cat-tree-toggle shrink-0 inline-flex items-center justify-center w-6 h-6 rounded
                       text-gray-500 hover:text-brand hover:bg-gray-100"
                aria-expanded="false" aria-label="Mở rộng {{ $node->title }}">
                <svg class="cat-tree-caret w-3 h-3" style="transition: transform 200ms ease" viewBox="0 0 20 20"
                    fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        @endif
    </div>

    @if ($hasChildren)
        <ul class="cat-tree-children list-none m-0 p-0 pl-3 mt-3 w-full" style="display:none">
            @foreach ($children as $child)
                @include('web::category.structure._side_bar_node', [
                    'node' => $child,
                    'byParent' => $byParent,
                    'depth' => $depth + 1,
                ])
            @endforeach
        </ul>
    @endif
</li>
