{{--
    Breadcrumb dạng minimal — chỉ navigation chain, không có title block.
    Dùng ở checkout, account, product detail.
--}}
@if(isset($breadcrumbs) && count($breadcrumbs))
    <div class="page-header breadcrumb-wrap">
        <div class="container mx-auto max-w-7xl px-4">
            <div class="breadcrumb">
                @foreach ($breadcrumbs as $i => $breadcrumb)
                    @if(filled($breadcrumb['href']) && $i < (count($breadcrumbs) - 1))
                        <a href="{{ $breadcrumb['href'] }}" title="{{ $breadcrumb['text'] }}">
                            {{ $breadcrumb['text'] }}
                        </a>
                    @else
                        {{ $breadcrumb['text'] }}
                    @endif
                    @if($i < (count($breadcrumbs) - 1))
                        <span></span>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif
