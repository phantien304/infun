@if(isset($breadcrumbs) && count($breadcrumbs))
    <div class="page-header mt-30 mb-50">
        <div class="container-xl">
            <div class="archive-header">
                <div class="row align-items-center">
                    <div class="col-xl-12">
                        @if(isset($titlePage))<h1 class="mb-15">{{ $titlePage }}</h1>@endif
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
            </div>
        </div>
    </div>
@endif
