@php
    $isVariant = (int) $option['role'] === getCoreConfig('option.role_variant');
    $valueParam = $isVariant ? 'option_value_id' : 'product_option_value_id';
@endphp
<div id="input-option{{ $option['id'] }}" class="input-option">
    <input type="hidden" name="option[{{ $option['id'] }}][required]" value="{{ (int) $option['required'] }}">
    <input type="hidden" name="option[{{ $option['id'] }}][role]" value="{{ $option['role'] }}">
    <input type="hidden" name="option[{{ $option['id'] }}][name]" value="{{ data_get($option, 'name_display', '') }}">
    <input type="hidden" name="option[{{ $option['id'] }}][type]" value="{{ data_get($option, 'option_type', '') }}">
    <input type="hidden" name="option[{{ $option['id'] }}][option_id]" value="{{ data_get($option, 'option_id') }}">
    <input type="hidden" name="option[{{ $option['id'] }}][value]" value="{{ data_get($option, 'value', '') }}"
        id="option-value-{{ $option['id'] }}">

    <div class="col-xl-12 mt-3 pt-3 @if ($key > 0) border-top @endif">
        <div class="row align-items-center">
            <div class="col-xl-3 text-left">
                <label class="tit">
                    {{ data_get($option, 'name_display', '') }}
                    @if ($option['required'])
                        <span class="required text-danger">(*)</span>
                    @endif
                </label>
            </div>
            <div class="col-xl-9">
                @if ($option['option_type'] == 'radio')
                    <div class="radio-choose-v2 no-image" id="option-{{ $option['id'] }}">
                        @foreach ($option['product_option_values'] as $optionValue)
                            <div>
                                <input type="radio" id="opt-{{ $optionValue['id'] }}"
                                    name="option[{{ $option['id'] }}][{{ $valueParam }}]"
                                    value="{{ $optionValue['id'] }}" data-option="{{ $option['id'] }}"
                                    data-option-id="{{ data_get($option, 'option_id') }}"
                                    data-option-value-id="{{ $optionValue['id'] }}">
                                <label for="opt-{{ $optionValue['id'] }}">
                                    {{ $optionValue['name'] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($option['option_type'] == 'image')
                    <div class="radio-choose-v2" id="option-{{ $option['id'] }}">
                        @foreach ($option['product_option_values'] as $optionValue)
                            @php
                                $thumb = $optionValue['variant_image'] ?: $optionValue['image'];
                            @endphp
                            <div>
                                <input data-image="{{ thumbnail($thumb, 1000, 1000) }}"
                                    data-variant-image="{{ thumbnail($optionValue['variant_image'] ?: '', 1000, 1000) }}"
                                    type="radio" id="opt-{{ $optionValue['id'] }}"
                                    name="option[{{ $option['id'] }}][{{ $valueParam }}]"
                                    value="{{ $optionValue['id'] }}" data-option="{{ $option['id'] }}"
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
                @if ($option['option_type'] == 'select')
                    <div class="d-flex flex-column">
                        <select name="option[{{ $option['id'] }}][{{ $valueParam }}]"
                            class="select-choose-v2 select-option-product" id="option-{{ $option['id'] }}"
                            data-option="{{ $option['id'] }}" data-option-id="{{ data_get($option, 'option_id') }}">
                            <option value="">--Chọn--</option>
                            @foreach ($option['product_option_values'] as $optionValue)
                                <option value="{{ $optionValue['id'] }}"
                                    data-option-value-id="{{ $optionValue['id'] }}"
                                    data-label="{{ $optionValue['name'] }}">{{ $optionValue['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($option['option_type'] == 'checkbox')
                    <div class="checkbox-choose-v2 no-image" id="option-{{ $option['id'] }}" style="display: table;">
                        @foreach ($option['product_option_values'] as $optionValue)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="opt-{{ $optionValue['id'] }}"
                                    name="option[{{ $option['id'] }}][{{ $valueParam }}][]"
                                    value="{{ $optionValue['id'] }}" data-option="{{ $option['id'] }}"
                                    data-option-id="{{ data_get($option, 'option_id') }}"
                                    data-option-value-id="{{ $optionValue['id'] }}">
                                <label class="form-check-label" for="opt-{{ $optionValue['id'] }}">
                                    {{ $optionValue['name'] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($option['option_type'] == 'text')
                    <input type="text" class="input-choose form-control" id="option-{{ $option['id'] }}"
                        data-option="{{ $option['id'] }}" @if ($option['required']) required @endif>
                @endif
                @if ($option['option_type'] == 'textarea')
                    <textarea class="textarea-choose form-control" id="option-{{ $option['id'] }}" data-option="{{ $option['id'] }}"
                        rows="5"></textarea>
                @endif
                @if ($option['option_type'] == 'email')
                    <input type="email" class="input-choose form-control" id="option-{{ $option['id'] }}"
                        data-option="{{ $option['id'] }}" @if ($option['required']) required @endif>
                @endif
                @if ($option['option_type'] == 'phone')
                    <input type="tel" class="input-choose form-control" id="option-{{ $option['id'] }}"
                        data-option="{{ $option['id'] }}" @if ($option['required']) required @endif>
                @endif
                @if ($option['option_type'] == 'file')
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
</div>
