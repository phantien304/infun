@extends('web::layouts.main_account')
@section('style')
    <style type="text/css">
        .shopping-summery tbody tr.border-bottom:last-child {
            border-bottom: none !important;
        }
    </style>
@stop
@section('content')
    <div class="col-xl-9 account">
        <div class="card">
            <div class="card-header">
                <h3>Sản phẩm yêu thích</h3>
            </div>
            <div class="card-body shopping-summery">
                <table class="table table-bordered mt-4">
                    <thead>
                        <tr class="main-heading">
                            <th class="start name pl-30" colspan="2">Tên sản phẩm</th>
                            <th class="model">Model</th>
                            <th class="stock">Tình trạng</th>
                            <th class="price">Đơn giá</th>
                            <th class="end action">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entities as $item)
                            @if (empty($item->name))
                                @continue
                            @endif
                            <tr class="border-bottom">
                                <td class="image">
                                    <a href="{{ $item->url }}">
                                        <img src="{{ thumbnail($item->image, 80, 80) }}" alt="{{ $item->name }}"
                                            class="img-responsive rounded-circle">
                                    </a>
                                </td>
                                <td class="name underline">
                                    <a href="{{ $item->url }}">{{ $item->name }}</a>
                                </td>
                                <td class="model" data-title="Model">{{ $item->model }}</td>
                                <td class="stock" data-title="Tình trạng">{{ $item->stockLabel }}</td>
                                <td class="price" data-title="Đơn giá">
                                    <div class="price">{{ $item->priceLabel }}</div>
                                </td>
                                <td class="action" data-title="Thao tác">
                                    <a href="{{ route('account.wishlist', ['remove' => $item->productId]) }}"
                                        title="Xóa yêu thích" class="d-flex justify-content-center align-self-center">
                                        <i class="far fa-times-circle text-danger"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop
