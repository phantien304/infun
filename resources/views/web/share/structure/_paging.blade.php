@if ($paginator->hasPages())
    @php
        $query = request()->query();
        if (isset($removeKey)) {
            $query = \Illuminate\Support\Arr::except($query, (array) $removeKey);
        }
        $paginator->appends($query);
    @endphp
    <ul class="pagination justify-content-start">
        @foreach (range(1, $paginator->lastPage()) as $i)
            @if ($i >= $paginator->currentPage() - 3 && $i <= $paginator->currentPage() + 3)
                @if ($i == $paginator->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $i }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $paginator->url($i) }}"
                            title="{{ $i }}">{{ $i }}</a></li>
                @endif
            @endif
        @endforeach
    </ul>
@endif
