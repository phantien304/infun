{{--
    Render 1 node trong cây danh mục sidebar (đệ quy) — accordion dọc.
    Mỗi row có border + padding + margin giống style theme gốc
    (`.widget-category-2 ul li`), nhưng KHÔNG kế thừa class theme để
    submenu không float ngang.

    Required:
      $node      — CategoryDTO
      $byParent  — Collection<parent_id, Collection<CategoryDTO>>
      $openIds   — array<int,true> id phải mở mặc định (chuỗi tổ tiên active)
      $activeId  — int|null id node đang active
      $depth     — int cấp đệ quy (chưa dùng cho indent vì children có pl-3 riêng)
--}}
@php
    $children    = $byParent->get($node->id) ?? collect();
    $hasChildren = $children->isNotEmpty();
    $isActive    = $activeId !== null && (int) $activeId === (int) $node->id;
    $isOpen      = isset($openIds[$node->id]);
@endphp

<li class="cat-tree-item block mb-3 last:mb-0"
    @if ($hasChildren) x-data="{ open: {{ $isOpen ? 'true' : 'false' }} }" @endif>
    {{-- Hàng node: pill bo góc + border + padding như theme `widget-category-2`.
         Hover: brand border + shadow. Active: brand background + chữ trắng.
         Link chiếm flex-1; nút mũi tên (nếu có con) bám phải. --}}
    <div class="cat-tree-row flex items-center justify-between gap-2 px-4 py-2 rounded-md border transition
                {{ $isActive
                    ? 'bg-brand border-brand text-white'
                    : 'border-gray-200 hover:border-brand hover:shadow' }}">
        <a href="{{ $node->url }}" title="{!! $node->title !!}"
            class="flex-1 min-w-0 truncate text-sm leading-6 hover:text-brand
                   {{ $isActive ? 'text-white font-semibold hover:text-white' : 'text-gray-800' }}">
            {!! $node->title !!}
        </a>

        @if ($hasChildren)
            <button type="button"
                class="shrink-0 inline-flex items-center justify-center w-6 h-6 rounded
                       {{ $isActive ? 'text-white hover:bg-white/20' : 'text-gray-500 hover:text-brand hover:bg-gray-100' }}"
                @click.stop="open = !open"
                :aria-expanded="open ? 'true' : 'false'"
                aria-label="Mở rộng {{ $node->title }}">
                {{-- Inline style thay cho class `rotate-90`: Tailwind v4 chỉ
                     compile class scan được trong build hiện tại, class mới
                     thêm sẽ rỗng tới khi `npm run build` lại. :style ép
                     transform trực tiếp → không phụ thuộc CSS bundle. --}}
                <svg class="w-3 h-3"
                    style="transition: transform 200ms ease"
                    :style="open ? 'transform: rotate(90deg)' : 'transform: rotate(0deg)'"
                    viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/>
                </svg>
            </button>
        @endif
    </div>

    @if ($hasChildren)
        {{-- Submenu DƯỚI hàng node, thụt vào bằng pl-3. list-none + m-0 + p-0
             reset mọi rule <ul> lồng từ theme. mt-3 = spacing giữa cha và row con đầu. --}}
        <ul class="cat-tree-children list-none m-0 p-0 pl-3 mt-3 block w-full"
            x-show="open" x-cloak>
            @foreach ($children as $child)
                @include('web::category.structure._side_bar_node', [
                    'node'     => $child,
                    'byParent' => $byParent,
                    'openIds'  => $openIds,
                    'activeId' => $activeId,
                    'depth'    => $depth + 1,
                ])
            @endforeach
        </ul>
    @endif
</li>
