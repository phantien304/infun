@php
    $address = json_decode(getCookie(setting('cookie.user.address'), '[]'), true);
    $addressVisitor = [];
    if (filled($address)) {
        $addressVisitor = array_filter($address, function ($k) {
            return $k['is_default'] == 1;
        });
        $addressVisitor = array_values($addressVisitor)[0];
    }
    $userAddressId = old('id', data_get($addressVisitor, 'id', 0));
    $zoneIdCookie = getCookie(setting('cookie.shipping_zone'), '');
    $zoneNameCookie = '';
    if (filled($zoneIdCookie)) {
        $zoneCookie = $zones->firstWhere('id', $zoneIdCookie);
        $zoneNameCookie = $zoneCookie ? $zoneCookie->name : '';
    }
    $zoneId = old('zone_id', data_get($addressVisitor, 'zone_id', $zoneIdCookie));
    $fullName = old('full_name', data_get($addressVisitor, 'full_name', ''));
    $telephone = old('telephone', data_get($addressVisitor, 'telephone', ''));
    $zoneName = old('zone_name', data_get($addressVisitor, 'zone_name', $zoneNameCookie));
    $districtId = old('district_id', data_get($addressVisitor, 'district_id', 0));
    $districtName = old('district_name', data_get($addressVisitor, 'district_name', ''));
    $wardId = old('ward_id', data_get($addressVisitor, 'ward_id', 0));
    $wardName = old('ward_name', data_get($addressVisitor, 'ward_name', ''));
    $inputAddress = old('address', data_get($addressVisitor, 'address', ''));
@endphp
<div class="modal fade" id="chooseAddress" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('account.addAddress') }}" method="post">
                <div class="modal-header justify-content-center">
                    <h5 class="modal-title" id="exampleModalLabel">Chọn địa chỉ nhận hàng</h5>
                </div>
                <div class="modal-body" style="font-size: 14px;">
                    <div class="">
                        <p>Để chúng tôi có thể phục vụ quý khách tốt hơn, xin quý khách vui lòng chọn địa chỉ
                            nhận hàng</p>
                        @if (auth()->check())
                            @if (empty($address))
                                Bạn chưa có địa chỉ, vui lòng
                                <a
                                    href="{{ route('account.address.create', ['redirect_url' => url()->current()]) }}">
                                    <b>Thêm địa chỉ</b>
                                </a>
                            @else
                                <p>Bạn muốn giao tới địa chỉ khác, vui lòng
                                    <a
                                        href="{{ route('account.address.create', ['redirect_url' => url()->current()]) }}">
                                        <b>Thêm địa chỉ</b>
                                    </a>
                                </p>
                                <div class="form-group row">
                                    <label for="inputZone" class="col-sm-4 col-form-label">Chọn địa chỉ&nbsp;<span
                                            class="required">*</span></label>
                                    <div class="col-sm-8">
                                        <select class="form-control" onchange="changeAddressCustomer(this)">
                                            @foreach ($address as $item)
                                                @if ($userAddressId == $item['id'])
                                                    <option value="{{ $item['id'] }}" selected>
                                                        {{ data_get($item, 'full_address') }}
                                                    </option>
                                                @else
                                                    <option value="{{ $item['id'] }}">
                                                        {{ data_get($item, 'full_address') }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="id" id="idUserAddress" value="{{ $userAddressId }}">
                                <input type="hidden" name="zone_id" id="popupZoneId" value="{{ $zoneId }}">
                                <input type="hidden" name="zone_name" id="popupZoneName" value="{{ $zoneName }}">
                                <input type="hidden" name="district_id" id="popupDistrictId"
                                    value="{{ $districtId }}">
                                <input type="hidden" name="district_name" id="popupDistrictName"
                                    value="{{ $districtName }}">
                                <input type="hidden" name="ward_id" id="popupWardId" value="{{ $wardId }}">
                                <input type="hidden" name="ward_name" id="popupWardName" value="{{ $wardName }}">
                                <input type="hidden" name="address" id="popupAddress" value="{{ $inputAddress }}">
                            @endif
                        @else
                            <div class="form-group row">
                                <label for="popupInputZone" class="col-sm-4 col-form-label">Thành phố/Tỉnh&nbsp;<span
                                        class="required">*</span></label>
                                <div class="col-sm-8">
                                    <select id="popupInputZone" name="zone_id" onchange="changeZone(this)"
                                        class="form-control @if ($errors->has('zone_id')) is-invalid @endif">
                                        <option value="">Chọn Thành phố/Tỉnh</option>
                                        @foreach ($zones as $item)
                                            <option value="{{ $item->id }}" name="{!! $item->name !!}"
                                                @selected($zoneId == $item->id)>
                                                {!! $item->name !!}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="zone_name" id="popupZoneName"
                                        value="{{ $zoneName }}">
                                    @if ($errors->has('zone_id'))
                                        <div class="invalid-feedback">{{ $errors->first('zone_id') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="popupInputDistrict" class="col-sm-4 col-form-label">Quận/Huyện&nbsp;<span
                                        class="required">*</span></label>
                                <div class="col-sm-8">
                                    <select id="popupInputDistrict" name="district_id" onchange="changeDistrict(this)"
                                        class="form-control @if ($errors->has('district_id')) is-invalid @endif">
                                        <option value="">Chọn Quận/Huyện</option>
                                    </select>
                                    <input type="hidden" name="district_name" id="popupDistrictName"
                                        value="{{ $districtName }}">
                                    @if ($errors->has('district_id'))
                                        <div class="invalid-feedback">{{ $errors->first('district_id') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="popupInputWard" class="col-sm-4 col-form-label">Phường/Xã&nbsp;<span
                                        class="required">*</span></label>
                                <div class="col-sm-8">
                                    <select id="popupInputWard" name="ward_id" onchange="changeWard(this)"
                                        class="form-control @if ($errors->has('ward_id')) is-invalid @endif">
                                        <option value="">Chọn Phường/Xã</option>
                                    </select>
                                    <input type="hidden" name="ward_name" id="popupWardName"
                                        value="{{ $wardName }}">
                                    @if ($errors->has('ward_id'))
                                        <div class="invalid-feedback">{{ $errors->first('ward_id') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="row">
                                <label for="popupInputWard" class="col-sm-4 col-form-label">Địa chỉ&nbsp;<span
                                        class="required">*</span></label>
                                <div class="col-sm-8">
                                    <input type="text" name="address" value="{{ $inputAddress }}"
                                        id="popupAddress" placeholder="Ví dụ: 52, đường Trần Hưng Đạo"
                                        class="form-control @if ($errors->has('address')) is-invalid @endif">
                                    @if ($errors->has('address'))
                                        <div class="invalid-feedback">{{ $errors->first('address') }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                @if (auth()->check())
                    @if (filled($address))
                        <div class="modal-footer justify-content-center">
                            <button type="submit" class="btn btn-md">Xác nhận</button>
                        </div>
                    @endif
                @else
                    <div class="modal-footer justify-content-center">
                        <button type="submit" class="btn btn-md">Xác nhận</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@section('script')
    <script type="text/javascript">
        @if (empty($address))
            $(window).on('load', function() {
                let modalChooseAddress = new bootstrap.Modal(document.getElementById('chooseAddress'), {
                    show: true,
                    backdrop: 'static',
                    keyboard: false
                });
                modalChooseAddress.toggle()
            });
        @endif

        var address = JSON.parse('<?php echo json_encode($address); ?>');
        var oldZoneId = '<?php echo $zoneId; ?>';
        var oldDistrictId = '<?php echo $districtId; ?>';
        var oldWardId = '<?php echo $wardId; ?>';
        callResourceDistrict(oldZoneId);
        callResourceWard(oldDistrictId);

        function changeZone(obj) {
            $('input#popupZoneName').val(obj.options[obj.selectedIndex].text);
            callResourceDistrict(obj.value);
        }

        function callResourceDistrict(zoneId) {
            let input = '<option value="">Chọn Quận/Huyện</option>';
            $('#popupInputDistrict').html(input);
            $('#popupInputWard').html('<option value="">Chọn Phường/Xã</option>');
            if (zoneId) {
                $.ajax({
                    url: '/resource/district?zone_id=' + zoneId,
                    type: 'get',
                    success: function(json) {
                        if (json['success'] === true) {
                            let data = json['data'];
                            for (let i = 0; i < data.length; i++) {
                                let selected = '';
                                if (data[i]['id'] == oldDistrictId) {
                                    selected = 'selected';
                                }
                                input += '<option value="' + data[i]['id'] + '"' + selected + ' name="' + data[
                                    i]['name'] + '">' + data[i]['name'] + '</option>';
                            }
                            $('#popupInputDistrict').html(input);
                        }
                    },
                });
            }
        }

        function changeDistrict(obj) {
            $('input#popupDistrictName').val(obj.options[obj.selectedIndex].text);
            callResourceWard(obj.value);
        }

        function callResourceWard(wardId) {
            let input = '<option value="">Chọn Phường/Xã</option>';
            $('#popupInputWard').html(input);
            if (wardId) {
                $.ajax({
                    url: '/resource/ward?district_id=' + wardId,
                    type: 'get',
                    success: function(json) {
                        if (json['success'] === true) {
                            let data = json['data'];
                            for (let i = 0; i < data.length; i++) {
                                let selected = '';
                                if (data[i]['id'] == oldWardId) {
                                    selected = 'selected';
                                }
                                input += '<option value="' + data[i]['id'] + '"' + selected + ' name="' + data[
                                    i]['name'] + '">' + data[i]['name'] + '</option>';
                            }
                            $('#popupInputWard').html(input);
                        }
                    },
                });
            }
        }

        function changeWard(obj) {
            $('input#popupWardName').val(obj.options[obj.selectedIndex].text);
        }

        function changeAddressCustomer(obj) {
            let add = address.filter(item => item.id === parseInt(obj.value))[0];
            $('input#idUserAddress').val(add.id);
            $('input#popupZoneId').val(add.zone_id);
            $('input#popupZoneName').val(add.ward_name);
            $('input#popupDistrictId').val(add.district_id);
            $('input#popupDistrictName').val(add.district_name);
            $('input#popupWardId').val(add.ward_id);
            $('input#popupWardName').val(add.ward_name);
            $('input#popupAddress').val(add.address);
        }
    </script>
@stop
