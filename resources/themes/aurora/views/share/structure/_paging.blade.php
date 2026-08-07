@if ($paginator->hasPages())
    @php
        $query = request()->query();
        if (isset($removeKey)) {
            $query = \Illuminate\Support\Arr::except($query, (array) $removeKey);
        }
        $paginator->appends($query);
    @endphp
    <ul class="flex flex-wrap items-center gap-1.5 justify-center list-none p-0 m-0">
        @if ($paginator->currentPage() > 1)
            <li>
                <a href="{{ $paginator->url($paginator->currentPage() - 1) }}" aria-label="Trang trước"
                    class="h-10 w-10 rounded-xl border border-line bg-white hover:border-ink grid place-items-center transition">‹</a>
            </li>
        @endif

        @foreach (range(1, $paginator->lastPage()) as $i)
            @if ($i >= $paginator->currentPage() - 3 && $i <= $paginator->currentPage() + 3)
                @if ($i == $paginator->currentPage())
                    <li>
                        <span
                            class="h-10 min-w-10 px-3 rounded-xl bg-ink text-white font-semibold grid place-items-center">{{ $i }}</span>
                    </li>
                @else
                    <li>
                        <a href="{{ $paginator->url($i) }}" title="{{ $i }}"
                            class="h-10 min-w-10 px-3 rounded-xl border border-line bg-white hover:border-ink grid place-items-center transition">{{ $i }}</a>
                    </li>
                @endif
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <li>
                <a href="{{ $paginator->url($paginator->currentPage() + 1) }}" aria-label="Trang sau"
                    class="h-10 w-10 rounded-xl border border-line bg-white hover:border-ink grid place-items-center transition">›</a>
            </li>
        @endif
    </ul>
@endif
