<div id="input-option{{ $option['id'] }}" class="input-option">
    <input type="hidden" name="option[{{ $option['id'] }}][required]" value="{{ $option['required'] }}">
    <input type="hidden" name="option[{{ $option['id'] }}][variation]" value="{{ $option['variation'] }}">
    <input type="hidden" name="option[{{ $option['id'] }}][name]"
           value="{!! array_get($option, 'name_display', '') !!}">
    <input type="hidden" name="option[{{ $option['id'] }}][type]" value="{!! array_get($option, 'option_type', '') !!}">
    <input type="hidden" name="option[{{ $option['id'] }}][value]" value="" id="option-value-{{ $option['id'] }}">
    <input type="hidden" name="option_price" class="option-choose" id="option-choose-{{ $option['id'] }}" data-price=""
           value="">
    @if($option['variation'] == 1)
        <div class="col-xl-12 mt-3 pt-3 @if($key > 0) border-top @endif">
            <div class="row align-items-center">
                <div class="col-xl-3 text-left">
                    <label class="tit">
                        {!! array_get($option, 'name_display', '') !!}
                        @if ($option['required'])
                            <span class="required text-danger">(*)</span>
                        @endif
                    </label>
                </div>
                <div class="col-xl-9">
                    @if($option['option_type'] == 'radio')
                        <div class="radio-choose no-image" id="option-{{ $option['id'] }}">
                            @foreach($option['product_option_values'] as $optionValue)
                                <div>
                                    <input type="radio" id="opt-{{ $optionValue['id'] }}"
                                           name="option[{!! $option['id'] !!}][product_option_value_id]"
                                           value="{{ $optionValue['id'] }}"
                                           data-option="{{ $option['id'] }}"
                                           data-type="{{ array_get($option, 'children.type') }}">
                                    <label for="opt-{{ $optionValue['id'] }}">
                                        {{ $optionValue['name'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if($option['option_type'] == 'select')
                        <div class="d-flex flex-column">
                            <select name="option[{!! $option['id'] !!}][product_option_value_id]"
                                    class="select-choose select-option-product" id="option-{{ $option['id'] }}">
                                <option value="" data-option="{{ $option['id'] }}">--Chọn--</option>
                                @foreach($option['product_option_values'] as $optionValue)
                                    <option value="{{ $optionValue['id'] }}"
                                            data-id="opt-{{ $optionValue['id'] }}"
                                            data-label="{{ $optionValue['name'] }}"
                                            data-type="{{ array_get($option, 'children.type') }}"
                                            data-option="{{ $option['id'] }}">
                                        {{ $optionValue['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if($option['option_type'] == 'image')
                        <div class="radio-choose" id="option-{{ $option['id'] }}">
                            @foreach($option['product_option_values'] as $optionValue)
                                <div>
                                    <input data-image="{{ resizeImage($optionValue['image'], 1000, 1000, 'client') }}"
                                           type="radio" id="opt-{{ $optionValue['id'] }}"
                                           name="option[{!! $option['id'] !!}][product_option_value_id]"
                                           value="{{ $optionValue['id'] }}"
                                           data-option="{{ $option['id'] }}"
                                           data-type="{{ array_get($option, 'children.type') }}">
                                    <label for="opt-{{ $optionValue['id'] }}">
                                        <img src="{{ resizeImage($optionValue['image'], 50, 50, 'client') }}"
                                             alt="{{ $optionValue['name'] }}" class="img-thumbnail">
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-12 mt-3 pt-3 border-top">
            <div class="row align-items-center">
                <div class="col-xl-3 text-left">
                    <label class="tit">
                        <label>{!! array_get($option, 'children.name_display') !!}</label>
                        @if ($option['required'])
                            <span class="required text-danger">(*)</span>
                        @endif
                    </label>
                </div>
                <div class="col-xl-9">
                    @if(array_get($option, 'children.type') == 'radio')
                        <div class="radio-child-choose" id="option-child-{{ $option['id'] }}">
                            @foreach(array_get($option, 'children.option', []) as $children)
                                <div>
                                    <input type="radio" id="opt-{{$option['id']}}-child-{{ $children['id'] }}"
                                           name="option[{!! $option['id'] !!}][children][]"
                                           value="{{ $children['id'] }}"
                                           data-option="{{ $option['id'] }}" data-value="{{ $children['value'] }}">
                                    <label
                                        for="opt-{{$option['id']}}-child-{{ $children['id'] }}">{{ $children['value'] }}</label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if(array_get($option, 'children.type') == 'image')
                        <div class="image-child-choose" id="option-child-{{ $option['id'] }}">
                            @foreach(array_get($option, 'children.option', []) as $children)
                                <div>
                                    <input type="radio" name="option[{!! $option['id'] !!}][children][]"
                                           id="opt-{{$option['id']}}-child-{{ $children['id'] }}"
                                           value="{{ $children['id'] }}" data-value="{{ $children['value'] }}"
                                           data-option="{{ $option['id'] }}" data-price="">
                                    <label for="opt-{{$option['id']}}-child-{{ $children['id'] }}">
                                        <img src="{{ $children['image'] }}"
                                             alt="{{ $children['value'] }}" class="img-thumbnail">
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if(array_get($option, 'children.type') == 'select')
                        <div class="d-flex flex-column" id="option-child-{{ $option['id'] }}">
                            <select name="option[{!! $option['id'] !!}][children][]"
                                    class="select-child-choose select-option-product">
                                <option value="" data-option="{{ $option['id'] }}">--Chọn--</option>
                                @foreach(array_get($option, 'children.option', []) as $children)
                                    <option value="{{ $children['value'] }}"
                                            data-label="{{ $children['value'] }}"
                                            data-option="{{ $option['id'] }}"
                                            data-value="{{ $children['value'] }}">
                                        {{ $children['value'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if(array_get($option, 'children.type') == 'checkbox')
                        <div class="checkbox-child-choose" id="option-child-{{ $option['id'] }}"
                             style="display: table;">
                            @foreach (array_get($option, 'children.option', []) as $children)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox"
                                           id="opt-{{ $option['id'] }}-child-{{ $children['id'] }}"
                                           name="option[{!! $option['id'] !!}][children][]"
                                           value="{{ $children['id'] }}"
                                           data-option="{{ $option['id'] }}" data-value="{{ $children['value'] }}">
                                    <label class="form-check-label"
                                           for="opt-{{ $option['id'] }}-child-{{ $children['id'] }}">{!! $children['value'] !!}</label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="col-xl-12 mt-3 pt-3 @if($key > 0) border-top @endif">
            <div class="row align-items-center">
                <div class="col-xl-3 text-left">
                    <label class="tit">
                        {!! array_get($option, 'name_display', '') !!}
                        @if ($option['required'])
                            <span class="required text-danger">(*)</span>
                        @endif
                    </label>
                </div>
                <div class="col-xl-9">
                    @if($option['option_type'] == 'radio')
                        <div class="radio-choose-v2 no-image" id="option-{{ $option['id'] }}">
                            @foreach($option['product_option_values'] as $optionValue)
                                <div>
                                    <input type="radio" id="opt-{{ $optionValue['id'] }}"
                                           name="option[{!! $option['id'] !!}][product_option_value_id]"
                                           value="{{ $optionValue['id'] }}"
                                           data-type=""
                                           data-option="{{ $option['id'] }}"
                                           data-price="{{ (int)$optionValue['price'] }}">
                                    <label for="opt-{{ $optionValue['id'] }}">
                                        {{ $optionValue['name'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <input type="hidden" name="option[{!! $option['id'] !!}][children][]"
                               id="option-child-{{ $option['id'] }}">
                    @endif
                    @if($option['option_type'] == 'image')
                        <div class="radio-choose-v2" id="option-{{ $option['id'] }}">
                            @foreach($option['product_option_values'] as $optionValue)
                                <div>
                                    <input data-image="{{ resizeImage($optionValue['image'], 1000, 1000, 'client') }}"
                                           type="radio" id="opt-{{ $optionValue['id'] }}"
                                           name="option[{!! $option['id'] !!}][product_option_value_id]"
                                           value="{{ $optionValue['id'] }}"
                                           data-type=""
                                           data-option="{{ $option['id'] }}"
                                           data-price="{{ (int)$optionValue['price'] }}">
                                    <label for="opt-{{ $optionValue['id'] }}">
                                        <img src="{{ resizeImage($optionValue['image'], 50, 50, 'client') }}"
                                             alt="{{ $optionValue['name'] }}" class="img-thumbnail">
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <input type="hidden" name="option[{!! $option['id'] !!}][children][]"
                               id="option-child-{{ $option['id'] }}">
                    @endif
                    @if($option['option_type'] == 'select')
                        <div class="d-flex flex-column">
                            <select name="option[{!! $option['id'] !!}][product_option_value_id]"
                                    class="select-choose-v2 select-option-product" id="option-{{ $option['id'] }}">
                                <option value="" data-option="{{ $option['id'] }}">--Chọn--</option>
                                @foreach($option['product_option_values'] as $optionValue)
                                    <option value="{{ $optionValue['id'] }}" data-type=""
                                            data-option="{{ $option['id'] }}"
                                            data-label="{{ $optionValue['name'] }}"
                                            data-price="{{ (int)$optionValue['price'] }}">{{ $optionValue['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" name="option[{!! $option['id'] !!}][children][]"
                               id="option-child-{{ $option['id'] }}">
                    @endif
                    @if($option['option_type'] == 'checkbox')
                        <div class="checkbox-choose-v2 no-image" id="option-{{ $option['id'] }}"
                             style="display: table;">
                            @foreach($option['product_option_values'] as $optionValue)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="opt-{{ $optionValue['id'] }}"
                                           name="option[{!! $option['id'] !!}][product_option_value_id][]"
                                           value="{{ $optionValue['id'] }}"
                                           data-type=""
                                           data-option="{{ $option['id'] }}"
                                           data-price="{{ (int)$optionValue['price'] }}">
                                    <label class="form-check-label" for="opt-{{ $optionValue['id'] }}">
                                        {{ $optionValue['name'] }}
                                    </label>
                                </div>
                                <input type="hidden"
                                       name="option[{!! $option['id'] !!}][children][{{ $optionValue['id'] }}][]"
                                       id="option-child-{{ $option['id'] }}">
                            @endforeach
                        </div>
                    @endif
                    @if($option['option_type'] == 'text')
                        <input type="text" class="input-choose form-control" id="option-{{ $option['id'] }}"
                               data-option="{{ $option['id'] }}" @if ($option['required']) required @endif>
                    @endif
                    @if($option['option_type'] == 'textarea')
                        <textarea class="textarea-choose form-control" id="option-{{ $option['id'] }}"
                                  data-option="{{ $option['id'] }}" rows="5"></textarea>
                    @endif
                    @if($option['option_type'] == 'email')
                        <input type="email" class="input-choose form-control" id="option-{{ $option['id'] }}"
                               data-option="{{ $option['id'] }}" @if ($option['required']) required @endif>
                    @endif
                    @if($option['option_type'] == 'phone')
                        <input type="tel" class="input-choose form-control" id="option-{{ $option['id'] }}"
                               data-option="{{ $option['id'] }}" @if ($option['required']) required @endif>
                    @endif
                    @if($option['option_type'] == 'file')
                        <div id="option-{{ $option['id'] }}">
                            <button type="button" id="button-upload{!! $option['id'] !!}" data-loading-text="Loading..."
                                    class="btn btn-sm btn-upload">
                                <span class="fas fa-upload"></span> Upload File
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
