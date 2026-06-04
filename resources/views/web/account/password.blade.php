@extends('web.layouts.main_account')
@section('content')
    @php $typeRegister = $entity?->typeRegister; @endphp
    <div class="col-xl-9 account">
        <div class="card">
            <div class="card-header">
                <h3>Thay đổi mật khẩu</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('account.password') }}" method="post" class="mt-30">
                    @csrf
                    @if (empty($typeRegister))
                        <div class="row mb-30">
                            <label for="inputOldPassword" class="col-sm-2 col-form-label">Mật khẩu cũ</label>
                            <div class="col-sm-10">
                                <input type="password" name="old_password" id="inputOldPassword"
                                       placeholder="Mật khẩu cũ" value="{{ old('old_password') }}"
                                       class="form-control @if ($errors->has('old_password')) is-invalid @endif">
                                @if ($errors->has('old_password'))
                                    <div class="invalid-feedback">{{ $errors->first('old_password') }}</div>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div class="row mb-30">
                        <label for="inputPassword" class="col-sm-2 col-form-label">Mật khẩu</label>
                        <div class="col-sm-10">
                            <input type="password" name="password" id="inputPassword" placeholder="Mật khẩu"
                                   value="{{ old('password') }}"
                                   class="form-control @if ($errors->has('password')) is-invalid @endif">
                            @if ($errors->has('password'))
                                <div class="invalid-feedback">{{ $errors->first('password') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
                        <label for="inputConfirmPassword" class="col-sm-2 col-form-label">Nhập lại mật khẩu</label>
                        <div class="col-sm-10">
                            <input type="password" name="confirm_password" id="inputConfirmPassword"
                                   placeholder="Nhập lại mật khẩu" value="{{ old('confirm_password') }}"
                                   class="form-control @if ($errors->has('confirm_password')) is-invalid @endif">
                            @if ($errors->has('confirm_password'))
                                <div class="invalid-feedback">{{ $errors->first('confirm_password') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-30">
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
