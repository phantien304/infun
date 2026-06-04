@extends('web.layouts.main_account')
@section('content')
    <div class="col-xl-9 account">
        <div class="card">
            <div class="card-header">
                <h3>Đăng ký nhận tin khuyến mãi</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('account.newsletter') }}" method="post" class="mt-3">
                    @csrf
                    <div class="row mb-40">
                        <legend class="col-form-label col-sm-2">Đăng ký</legend>
                        <div class="col-sm-10 pt-2">
                            <div class="form-check form-check-inline mr-30">
                                <input type="radio" id="newsletterYes" name="newsletter"
                                       @if($entity?->newsletter) checked @endif
                                       class="form-check-input" value="1">
                                <label class="form-check-label" for="newsletterYes">Có</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="radio" id="newsletterNo" name="newsletter"
                                       @if(!$entity?->newsletter) checked @endif
                                       class="form-check-input" value="0">
                                <label class="form-check-label" for="newsletterNo">Không</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <button type="submit" class="btn btn-md">Cập nhật</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
