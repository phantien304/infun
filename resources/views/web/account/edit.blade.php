@php
    $phone = $entity->userPhone ? $entity->userPhone->phone : '';
@endphp
@extends('client.infunstudio.layouts.main_account')
@section('content')
    <div class="col-xl-9 account">
        <div class="card">
            <div class="card-header">
                <h3>Thông tin tài khoản</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('account.edit') }}" method="post" class="mt-30">
                    <div class="row mb-30">
                        <label for="staticEmail" class="col-sm-2 col-form-label">Email</label>
                        <div class="col-sm-10">
                            <input type="text" readonly name="email" class="form-control form-control-plaintext" id="staticEmail"
                                   value="{{ $entity->email }}">
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputFullName" class="col-sm-2 col-form-label">Họ tên</label>
                        <div class="col-sm-10">
                            <input type="text" name="full_name" id="inputFullName"
                                   placeholder="Họ tên" value="{{ old('full_name', $entity->full_name) }}"
                                   class="form-control @if ($errors->has('full_name')) is-invalid @endif">
                            @if ($errors->has('full_name'))
                                <div class="invalid-feedback">{{ $errors->first('full_name') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputPassword" class="col-sm-2 col-form-label">Điện thoại</label>
                        <div class="col-sm-10">
                            <input type="text" name="phone" id="inputPassword" placeholder="Điện thoại"
                                   value="{{ old('phone', $phone) }}"
                                   class="form-control @if ($errors->has('phone')) is-invalid @endif">
                            @if ($errors->has('phone'))
                                <div class="invalid-feedback">{{ $errors->first('phone') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <legend class="col-form-label col-sm-2 pt-0">Giới tính</legend>
                        <div class="col-sm-10">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio"
                                       @if($entity->sex == 1) checked @endif
                                       name="sex" id="genderMale" value="1">
                                <label class="form-check-label" for="genderMale">Nam</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio"
                                       @if($entity->sex == 0) checked @endif
                                       name="sex" id="genderFemale" value="0">
                                <label class="form-check-label" for="genderFemale">Nữ</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
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
