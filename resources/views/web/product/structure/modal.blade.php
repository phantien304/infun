<div class="modal fade" id="myModal-{{$key}}">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="p-3 align-self-center">
                <div class="safety border-white d-flex align-self-center"
                     style="background-image: url('{!! $background !!}'); line-height: 40px;">
                    {{ implode('-', array_map(function ($entry) {return $entry['name'];}, $nameSafety)) }}
                </div>
            </div>
            <div class="modal-header" style="justify-content: center">
                <h4 class="modal-title">{{ $ingredient->name }}</h4>
            </div>
            <div class="modal-body">
                @if ($ingredient->warning || count($effects))
                    <h4 class="title my-3">Thành phần chú ý</h4>
                @endif
                @if ($ingredient->warning)
                    <div class="body">
                        {{ $ingredient->warning_text }}
                    </div>
                @endif
                @if (count($effects))
                    <div class="body">
                        @foreach($effects as $item)
                            <span class="me-2">
                                @if(empty(array_get($item, 'icon')))
                                    <img class="icon-effect"
                                         alt="{!! array_get($item, 'name') !!}"
                                         src="{!! array_get($item, 'image_icon') !!}">
                                @else
                                    <i class="{{ array_get($item, 'icon') }} text-secondary"></i>
                                @endif
                                {{ array_get($item, 'name') }}
                            </span>
                        @endforeach
                    </div>
                @endif
                @if (count($skincareList))
                    <h4 class="title my-3">Khuyên dùng</h4>
                    <div class="body">
                        @foreach($skincareList as $item)
                            <span class="me-2">
                                @if(empty(array_get($item, 'icon')))
                                    <img class="icon-skincare"
                                         alt="{!! array_get($item, 'name') !!}"
                                         src="{!! array_get($item, 'image_icon') !!}">
                                @else
                                    <i class="{{ array_get($item, 'icon') }} text-secondary"></i>
                                @endif
                                {{ array_get($item, 'name') }}
                            </span>
                        @endforeach
                    </div>
                @endif
                @if (filled($ingredient->description))
                    <h4 class="title my-3">Chi tiết thành phần</h4>
                    <div class="body">
                        {{ $ingredient->description }}
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">
                    Đóng
                </button>
            </div>
        </div>
    </div>
</div>
