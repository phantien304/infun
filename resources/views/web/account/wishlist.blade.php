@extends('client.infunstudio.layouts.main_account')
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
                    @foreach($entities as $item)
                        @if(!isset($item->product->productDescription))
                            @continue;
                        @endif
                        <tr class="border-bottom">
                            <td class="image">
                                <a href="{{ $item->product->productDescription->getUrlClient() }}">
                                    <img src="{{ resizeImage($item->product->image, 80, 80 ,'client') }}"
                                         alt="{{ $item->product->productDescription->name }}"
                                         class="img-responsive rounded-circle">
                                </a>
                            </td>
                            <td class="name underline">
                                <a href="{{ $item->product->productDescription->getUrlClient() }}">
                                    {{ $item->product->productDescription->name }}
                                </a>
                            </td>
                            <td class="model" data-title="Model">
                                {{ $item->product->model }}
                            </td>
                            <td class="stock" data-title="Tình trạng">
                                {{ $item->product->getStock() }}
                            </td>
                            <td class="price" data-title="Đơn giá">
                                <div class="price">
                                    @php $price = $item->product->price;
                                        if(count($item->product->productSpecials)){
                                            $productSpecial = $item->product->productSpecials->sortByDesc('priority')->first();
                                            $price = $productSpecial->price;
                                        }
                                    @endphp
                                    {{ number_format($price, 0, '', ',') . 'đ' }}
                                </div>
                            </td>
                            <td class="action" data-title="Thao tác">
                                <a href="{{ route('account.wishlist', ['remove' => $item->product_id]) }}"
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
