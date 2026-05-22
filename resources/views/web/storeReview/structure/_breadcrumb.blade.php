@if(isset($breadcrumbs) && count($breadcrumbs))
    <div class="page-header breadcrumb-wrap">
        <div class="container-xl">
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
