@extends('web.layouts.main_account')
@section('style')
    <style>
        .form-address .new a {
            background-color: rgb(255, 255, 255);
            font-size: 15px;
            height: 60px;
            border: 1px dashed rgb(216, 216, 216);
            margin: 0 0 10px;
            display: flex;
            -webkit-box-align: center;
            align-items: center;
            -webkit-box-pack: center;
            justify-content: center;
        }
    </style>
@stop
@section('script_header')
    <script type="text/javascript">
        @php
            $defaultZoneId = $entity?->zoneId ?? (int) request()->cookie(getCoreConfig('cookie.shipping_zone'), 0);
        @endphp
        var oldZoneId = '<?php echo old('zone_id', $defaultZoneId) ?>';
        var oldDistrictId = '<?php echo old('district_id', $entity?->districtId ?? 0) ?>';
        var oldWardId = '<?php echo old('ward_id', $entity?->wardId ?? 0) ?>';
    </script>
@stop
@section('script')
    <script type="text/javascript">
        callResourceDistrict(oldZoneId);
        callResourceWard(oldDistrictId);

        function changeZone(obj) { callResourceDistrict(obj.value); }
        function changeDistrict(obj) { callResourceWard(obj.value); }
    </script>
@stop
@section('content')
    <div class="col-xl-9 account form-address">
        <div class="card">
            <div class="card-header">
                <h3>
                    @if(filled(request()->get('address_id')))
                        Sửa địa chỉ
                    @else
                        Tạo địa chỉ
                    @endif
                </h3>
            </div>
            <div class="card-body">
                <form action="{{ route('account.address.edit') }}" method="post" class="mt-30">
                    @csrf
                    <input type="hidden" name="id" value="{{ old('id', request()->get('address_id')) }}"/>
                    <input type="hidden" name="redirect_url"
                           value="{{ old('redirect_url', request()->get('redirect_url')) }}"/>
                    <div class="row mb-30">
                        <label for="inputFullName" class="col-sm-2 col-form-label text-right">Họ tên</label>
                        <div class="col-sm-10">
                            <input type="text" name="full_name" id="inputFullName"
                                   placeholder="Họ tên" value="{{ old('full_name', $entity?->fullName) }}"
                                   class="form-control @if ($errors->has('full_name')) is-invalid @endif">
                            @if ($errors->has('full_name'))
                                <div class="invalid-feedback">{{ $errors->first('full_name') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputPhone" class="col-sm-2 col-form-label text-right">Điện thoại</label>
                        <div class="col-sm-10">
                            <input type="text" name="telephone" id="inputPhone" placeholder="Điện thoại"
                                   value="{{ old('telephone', $entity?->telephone) }}"
                                   class="form-control @if ($errors->has('telephone')) is-invalid @endif">
                            @if ($errors->has('telephone'))
                                <div class="invalid-feedback">{{ $errors->first('telephone') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputZone" class="col-sm-2 col-form-label text-right">Thành phố/ Tỉnh</label>
                        <div class="col-sm-10">
                            <select id="inputZone" name="zone_id" onchange="changeZone(this)"
                                    class="form-control @if ($errors->has('zone_id')) is-invalid @endif">
                                <option value="">--Chọn--</option>
                                @foreach($zones as $item)
                                    @if(old('zone_id', $defaultZoneId) == $item['id'])
                                        <option value="{{ $item['id'] }}" selected>{!! $item['name'] !!}</option>
                                    @else
                                        <option value="{{ $item['id'] }}">{!! $item['name'] !!}</option>
                                    @endif
                                @endforeach
                            </select>
                            @if ($errors->has('zone_id'))
                                <div class="invalid-feedback">{{ $errors->first('zone_id') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputDistrict" class="col-sm-2 col-form-label text-right">Quận/ Huyện</label>
                        <div class="col-sm-10">
                            <select id="inputDistrict" name="district_id"
                                    onchange="changeDistrict(this)"
                                    class="form-control @if ($errors->has('district_id')) is-invalid @endif">
                                <option value="">--Chọn--</option>
                            </select>
                            @if ($errors->has('district_id'))
                                <div class="invalid-feedback">{{ $errors->first('district_id') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputWard" class="col-sm-2 col-form-label text-right">Phường/ Xã</label>
                        <div class="col-sm-10">
                            <select id="inputWard" name="ward_id"
                                    class="form-control @if ($errors->has('ward_id')) is-invalid @endif">
                                <option value="">--Chọn--</option>
                            </select>
                            @if ($errors->has('ward_id'))
                                <div class="invalid-feedback">{{ $errors->first('ward_id') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputAddress" class="col-sm-2 col-form-label text-right">Địa chỉ</label>
                        <div class="col-sm-10">
                            <input type="text" name="address" id="inputAddress" placeholder="Địa chỉ"
                                   value="{{ old('address', $entity?->address) }}"
                                   class="form-control @if ($errors->has('address')) is-invalid @endif">
                            @if ($errors->has('address'))
                                <div class="invalid-feedback">{{ $errors->first('address') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <div class="col-sm-2"></div>
                        <div class="col-sm-10">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="isDefaultCheck"
                                       name="is_default" value="1" @if ($entity?->isDefault) checked @endif/>
                                <label class="form-check-label" for="isDefaultCheck">Địa chỉ mặc định</label>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-30 mt-4">
                        <div class="col-sm-2"></div>
                        <div class="col-sm-10">
                            <button type="submit" class="btn btn-md">Cập nhật</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
