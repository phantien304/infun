@extends('web::layouts.main_account')
@section('content')
    <div class="col-xl-9 account">
        <div class="card">
            <div class="card-header">
                <h3>Lịch sử mua hàng</h3>
            </div>
            <div class="card-body shopping-summery">
                <div class="table-responsive">
                    <table class="table table-hover mt-4">
                        <thead>
                            <tr class="mb-30">
                                <th class="model start pl-30" style="width: 400px;">Sản phẩm</th>
                                <th class="name">Ngày mua</th>
                                <th class="stock">Tổng tiền</th>
                                <th class="price">Trạng thái</th>
                                <th class="action end" style="min-width: 75px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entities as $item)
                                <tr class="border-bottom">
                                    <td class="model">
                                        {{ $item->firstProductName }}
                                        @if ($item->productCount > 1)
                                            ...và {{ $item->productCount - 1 }} sản phẩm
                                        @endif
                                    </td>
                                    <td class="date underline" data-title="Ngày mua">
                                        {{ $item->createdAt }}
                                    </td>
                                    <td class="price" data-title="Tổng tiền">
                                        {{ $item->totalLabel }}
                                    </td>
                                    <td class="status" data-title="Trạng thái">
                                        {{ $item->orderStatusName }}
                                    </td>
                                    <td class="action" data-title="Thao tác">
                                        <a href="{{ route('account.detailOrder', ['id' => $item->id]) }}"
                                            style="font-size: 20px" title="Xem chi tiết đơn hàng">
                                            <i class="fas fa-eye"></i>
                                        </a>&nbsp;&nbsp;
                                        <a href="{{ route('order.search', ['order_code' => $item->invoiceNo]) }}"
                                            target="_blank" style="font-size: 20px">
                                            <i class="fas fa-search" title="Theo dõi đơn hàng"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pagination-area mt-15 mb-sm-5 mb-lg-0">
                    <nav aria-label="Phân trang">
                        {!! $entities->links('web::share.structure._paging', ['removeKey' => ['per_page']]) !!}
                    </nav>
                </div>
            </div>
        </div>
    </div>
@stop