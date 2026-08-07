@php
    $currentSort = collect($sortMenu)->firstWhere('active', true) ?? ($sortMenu[0] ?? null);
    $currentPerPage = request()->get('per_page', 20);
@endphp
<div class="flex items-center gap-2">
    <div class="relative hidden sm:block" x-data="{ open: false }" @click.outside="open = false">
        <button @click="open = !open"
            class="h-10 pl-4 pr-3 rounded-xl bg-white border border-line hover:border-ink font-medium text-[13.5px] flex items-center gap-2 transition">
            <span class="text-ink-3">Hiển thị:</span>
            <span>{{ $currentPerPage }}</span>
            <svg class="w-3.5 h-3.5 transition-transform" :class="open && 'rotate-180'" fill="none"
                stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="m6 9 6 6 6-6" />
            </svg>
        </button>
        <div x-show="open" x-cloak x-transition.opacity.duration.120ms
            class="absolute right-0 top-full mt-1.5 w-28 bg-white rounded-xl border border-line shadow-lg py-1.5 z-40">
            @foreach ($perPageMenu as $item)
                <a href="{{ $item['url'] }}"
                    class="block px-4 py-2 text-[14px] hover:bg-canvas transition {{ $item['active'] ? 'font-semibold text-brand' : '' }}">
                    {{ $item['value'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
        <button @click="open = !open"
            class="h-10 pl-4 pr-3 rounded-xl bg-white border border-line hover:border-ink font-medium text-[13.5px] flex items-center gap-2 transition">
            <span class="text-ink-3 hidden sm:inline">Sắp xếp:</span>
            <span>{{ $currentSort['label'] ?? trans('sort.-created_at') }}</span>
            <svg class="w-3.5 h-3.5 transition-transform" :class="open && 'rotate-180'" fill="none"
                stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="m6 9 6 6 6-6" />
            </svg>
        </button>
        <div x-show="open" x-cloak x-transition.opacity.duration.120ms
            class="absolute right-0 top-full mt-1.5 w-56 bg-white rounded-xl border border-line shadow-lg py-1.5 z-40">
            @foreach ($sortMenu as $item)
                <a href="{{ $item['url'] }}" title="{{ $item['label'] }}"
                    class="block px-4 py-2 text-left text-[14px] hover:bg-canvas transition {{ $item['active'] ? 'font-semibold text-brand' : '' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</div>
