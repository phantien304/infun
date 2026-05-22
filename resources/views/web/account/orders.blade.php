@extends('client.infunstudio.layouts.main_account')
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
                        @foreach($entities as $item)
                            <tr class="border-bottom">
                                <td class="model">
                                    @php
                                        $countProduct = count($item->ordersProducts);
                                        if($countProduct){
                                            $nameProduct = $item->ordersProducts[0]->name;
                                            $append = $countProduct > 1 ? '...và ' . ($countProduct - 1). ' sản phẩm' : '';
                                            echo $nameProduct . $append;
                                        }
                                    @endphp
                                </td>
                                <td class="date underline" data-title="Ngày mua">
                                    {!! \Carbon\Carbon::parse($item->created_at)->format('H:i m/d/Y') !!}
                                </td>
                                <td class="price" data-title="Tổng tiền">
                                    @if(isset($item->ordersTotal))
                                        {{ number_format($item->ordersTotal->value, 0, '', ',') . 'đ' }}
                                    @endif
                                </td>
                                <td class="status" data-title="Trạng thái">
                                    {!! array_get($item, 'ordersStatus.name') !!}
                                </td>
                                <td class="action" data-title="Thao tác">
                                    <a href="{{ route('account.detailOrder', ['id' => $item->id]) }}"
                                       style="font-size: 20px" title="Xem chi tiết đơn hàng">
                                        <i class="fas fa-eye"></i>
                                    </a>&nbsp;&nbsp;
                                    <a href="{{ route('order.search', ['order_code' => $item->invoice_no]) }}"
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
                        {!! $entities->links('client.infunstudio.share.structure._paging', ['removeKey' => ['category_id_eq', 'per_page']]) !!}
                    </nav>
                </div>
            </div>
        </div>
    </div>
@stop
