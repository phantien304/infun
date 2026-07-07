<div id="input-option{{ $option['option_id'] }}" class="input-option">
    <input type="hidden" name="option[{{ $option['option_id'] }}][required]" value="{{ (int) $option['required'] }}">
    <input type="hidden" name="option[{{ $option['option_id'] }}][role]" value="{{ $option['role'] }}">
    <input type="hidden" name="option[{{ $option['option_id'] }}][name]" value="{{ data_get($option, 'name_display', '') }}">
    <input type="hidden" name="option[{{ $option['option_id'] }}][type]" value="{{ data_get($option, 'type', '') }}">
    <input type="hidden" name="option[{{ $option['option_id'] }}][option_id]" value="{{ data_get($option, 'option_id') }}">
    <input type="hidden" name="option[{{ $option['option_id'] }}][value]" value="{{ data_get($option, 'value', '') }}"
        id="option-value-{{ $option['option_id'] }}">

    <div class="w-full mt-3 pt-3 @if ($key > 0) border-t border-gray-200 @endif">
        <div class="flex flex-col xl:flex-row xl:items-center gap-3">
            <div class="xl:w-1/4 text-left">
                <label class="tit">
                    {{ data_get($option, 'name_display', '') }}
                    @if ($option['required'])
                        <span class="required text-danger">(*)</span>
                    @endif
                    @include('web::product.structure._option_price', ['amount' => data_get($option, 'price', 0)])
                </label>
            </div>
            <div class="xl:w-3/4 flex-1">
                @if ($option['type'] == 'radio')
                    <div class="radio-choose-v2 no-image" id="option-{{ $option['option_id'] }}">
                        @foreach ($option['selectableValues'] as $optionValue)
                            <div>
                                <input type="radio" id="opt-{{ $optionValue['id'] }}"
                                    name="option[{{ $option['option_id'] }}][option_value_id]"
                                    value="{{ $optionValue['id'] }}" data-option="{{ $option['option_id'] }}"
                                    data-option-id="{{ data_get($option, 'option_id') }}"
                                    data-option-value-id="{{ $optionValue['id'] }}"
                                    data-price="{{ (float) data_get($optionValue, 'price', 0) }}">
                                <label for="opt-{{ $optionValue['id'] }}">
                                    {{ $optionValue['name'] }}
                                    @include('web::product.structure._option_price', ['amount' => data_get($optionValue, 'price', 0)])
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($option['type'] == 'image')
                    <div class="radio-choose-v2" id="option-{{ $option['option_id'] }}">
                        @foreach ($option['selectableValues'] as $optionValue)
                            @php
                                $thumb = $optionValue['variant_image'] ?: $optionValue['image'];
                            @endphp
                            <div>
                                <input data-image="{{ thumbnail($thumb, 1000, 1000) }}"
                                    data-variant-image="{{ thumbnail($optionValue['variant_image'] ?: '', 1000, 1000) }}"
                                    type="radio" id="opt-{{ $optionValue['id'] }}"
                                    name="option[{{ $option['option_id'] }}][option_value_id]"
                                    value="{{ $optionValue['id'] }}" data-option="{{ $option['option_id'] }}"
                                    data-option-id="{{ data_get($option, 'option_id') }}"
                                    data-option-value-id="{{ $optionValue['id'] }}">
                                <label for="opt-{{ $optionValue['id'] }}">
                                    <img src="{{ thumbnail($thumb, 50, 50) }}" alt="{{ $optionValue['name'] }}"
                                        class="img-thumbnail">
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($option['type'] == 'select')
                    <div class="flex flex-col">
                        <select name="option[{{ $option['option_id'] }}][option_value_id]"
                            class="select-choose-v2 select-option-product" id="option-{{ $option['option_id'] }}"
                            data-option="{{ $option['option_id'] }}" data-option-id="{{ data_get($option, 'option_id') }}">
                            <option value="">--Chọn--</option>
                            @foreach ($option['selectableValues'] as $optionValue)
                                <option value="{{ $optionValue['id'] }}"
                                    data-option-value-id="{{ $optionValue['id'] }}"
                                    data-price="{{ (float) data_get($optionValue, 'price', 0) }}"
                                    data-label="{{ $optionValue['name'] }}">{{ $optionValue['name'] }}@php $povp = (float) data_get($optionValue, 'price', 0); @endphp @if ($povp != 0)({{ $povp > 0 ? '+' : '−' }}{{ money(abs($povp)) }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($option['type'] == 'checkbox')
                    <div class="checkbox-choose-v2 no-image" id="option-{{ $option['option_id'] }}" style="display: table;">
                        @foreach ($option['selectableValues'] as $optionValue)
                            <div class="form-check inline-flex mr-2">
                                <input class="form-check-input" type="checkbox" id="opt-{{ $optionValue['id'] }}"
                                    name="option[{{ $option['option_id'] }}][option_value_id][]"
                                    value="{{ $optionValue['id'] }}" data-option="{{ $option['option_id'] }}"
                                    data-option-id="{{ data_get($option, 'option_id') }}"
                                    data-option-value-id="{{ $optionValue['id'] }}"
                                    data-price="{{ (float) data_get($optionValue, 'price', 0) }}">
                                <label class="form-check-label" for="opt-{{ $optionValue['id'] }}">
                                    {{ $optionValue['name'] }}
                                    @include('web::product.structure._option_price', ['amount' => data_get($optionValue, 'price', 0)])
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($option['type'] == 'text')
                    <input type="text" class="input-choose form-control" id="option-{{ $option['option_id'] }}"
                        data-option="{{ $option['option_id'] }}" data-price="{{ (float) data_get($option, 'price', 0) }}" @if ($option['required']) required @endif>
                @endif
                @if ($option['type'] == 'textarea')
                    <textarea class="textarea-choose form-control" id="option-{{ $option['option_id'] }}" data-option="{{ $option['option_id'] }}"
                        data-price="{{ (float) data_get($option, 'price', 0) }}" rows="5"></textarea>
                @endif
                @if ($option['type'] == 'email')
                    <input type="email" class="input-choose form-control" id="option-{{ $option['option_id'] }}"
                        data-option="{{ $option['option_id'] }}" data-price="{{ (float) data_get($option, 'price', 0) }}" @if ($option['required']) required @endif>
                @endif
                @if ($option['type'] == 'phone')
                    <input type="tel" class="input-choose form-control" id="option-{{ $option['option_id'] }}"
                        data-option="{{ $option['option_id'] }}" data-price="{{ (float) data_get($option, 'price', 0) }}" @if ($option['required']) required @endif>
                @endif
                @if ($option['type'] == 'file')
                    <div id="option-{{ $option['option_id'] }}">
                        <button type="button" id="button-upload{!! $option['option_id'] !!}" data-loading-text="Loading..."
                            class="btn btn-sm btn-upload">
                            <span class="fas fa-upload"></span> Upload File
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
