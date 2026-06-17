@extends('web::layouts.main_account')
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

        .form-address .item {
            background-color: rgb(255, 255, 255);
            padding: 17px;
            margin: 0 0 10px;
            display: flex;
            -webkit-box-pack: justify;
            justify-content: space-between;
            font-size: 13px;
            line-height: 19px;
            border-bottom: 1px solid #eaeaea;
        }

        .form-address .item .name {
            text-transform: uppercase;
            margin: 0 0 10px;
        }

        .form-address .item .name>span {
            font-size: 12px;
            margin: 0 0 0 15px;
            display: inline-block;
            -webkit-box-align: center;
            align-items: center;
            color: rgb(38, 188, 78);
            text-transform: none;
        }

        .form-address .item .name>span svg {
            display: inline-block;
            vertical-align: middle;
            position: relative;
            top: -1px;
        }

        .form-address .item .name>span span {
            display: inline-block;
            vertical-align: middle;
            margin: 0 0 0 5px;
        }

        .form-address .item .address {
            margin: 0 0 5px;
        }

        .form-address .item .address span,
        .form-address .item .phone span {
            color: rgb(120, 120, 120);
        }

        .form-address .item .edit {
            font-size: 14px;
            color: rgb(27, 168, 255);
            display: inline-block;
            padding: 6px 12px;
        }

        .form-address .item .delete {
            font-size: 14px;
            color: rgb(255, 66, 78);
            border: 0;
            display: inline-block;
            padding: 6px 12px;
            cursor: pointer;
            outline: 0;
            background: rgb(239 239 239);
        }

        .form-address .new svg {
            color: rgb(120, 120, 120);
            font-size: 28px;
            margin: 0 20px;
        }
    </style>
@stop
@section('content')
    <div class="col-xl-9 account form-address">
        <div class="card">
            <div class="card-header">
                <h3>Địa Chỉ Của Tôi</h3>
            </div>
            <div class="card-body inner">
                <div class="new">
                    <a href="{{ route('account.address.create') }}">
                        <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 24 24" height="1em"
                            width="1em" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path>
                        </svg>
                        <span>Thêm địa chỉ mới</span>
                    </a>
                </div>
                @foreach ($entities as $item)
                    <div class="item">
                        <div class="info">
                            <div class="name">
                                {{ $item->fullName }}
                                @if ($item->isDefault)
                                    <span>
                                        <svg stroke="currentColor" fill="currentColor" stroke-width="0"
                                            viewBox="0 0 512 512" height="1em" width="1em"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path
                                                d="M256 8C119.033 8 8 119.033 8 256s111.033 248 248 248 248-111.033 248-248S392.967 8 256 8zm0 48c110.532 0 200 89.451 200 200 0 110.532-89.451 200-200 200-110.532 0-200-89.451-200-200 0-110.532 89.451-200 200-200m140.204 130.267l-22.536-22.718c-4.667-4.705-12.265-4.736-16.97-.068L215.346 303.697l-59.792-60.277c-4.667-4.705-12.265-4.736-16.97-.069l-22.719 22.536c-4.705 4.667-4.736 12.265-.068 16.971l90.781 91.516c4.667 4.705 12.265 4.736 16.97.068l172.589-171.204c4.704-4.668 4.734-12.266.067-16.971z">
                                            </path>
                                        </svg>
                                        <span>Địa chỉ mặc định</span>
                                    </span>
                                @endif
                            </div>
                            <div class="address">
                                <span>Địa chỉ: </span>{{ $item->fullAddress }}
                            </div>
                            <div class="phone">
                                <span>Điện thoại: </span>{{ $item->telephone }}
                            </div>
                        </div>
                        <div class="action">
                            <a class="edit" href="{{ route('account.address.edit', ['address_id' => $item->id]) }}">
                                Chỉnh sửa
                            </a>
                            @if (!$item->isDefault)
                                <a href="{{ route('account.address', ['remove' => $item->id]) }}" class="delete">
                                    Xóa
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@stop
