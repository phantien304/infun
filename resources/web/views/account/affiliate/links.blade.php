@extends('web::layouts.main_account')
@section('content')
    @php
        $fmt = fn ($v) => number_format((int) $v, 0, ',', '.');
        $newLinkId = (int) session('new_link_id', 0);
    @endphp
    <div class="col-xl-9 account">
        <div class="card mb-30">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                <h3>Link giới thiệu</h3>
                <a href="{{ route('account.affiliate') }}" class="btn btn-sm">
                    <i class="fas fa-chart-line mr-5"></i>Xem dashboard
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('account.affiliate.createLink') }}" method="post" class="mb-30">
                    @csrf
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label for="inputUrl" class="col-form-label">URL cần rút gọn</label>
                            <input type="text" name="url" id="inputUrl"
                                placeholder="Dán URL sản phẩm / trang bất kỳ của website"
                                value="{{ old('url') }}"
                                class="form-control @if ($errors->has('url')) is-invalid @endif">
                            @if ($errors->has('url'))
                                <div class="invalid-feedback">{{ $errors->first('url') }}</div>
                            @endif
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="inputSubId" class="col-form-label">
                                Sub ID <span class="text-gray-400">(tùy chọn — tách kênh)</span>
                            </label>
                            <input type="text" name="sub_id" id="inputSubId" placeholder="VD: tiktok, ig-bio"
                                value="{{ old('sub_id') }}"
                                class="form-control @if ($errors->has('sub_id')) is-invalid @endif">
                            @if ($errors->has('sub_id'))
                                <div class="invalid-feedback">{{ $errors->first('sub_id') }}</div>
                            @endif
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-md w-100">Tạo link</button>
                        </div>
                    </div>
                </form>

                <div class="d-flex align-items-center flex-wrap mb-30 p-3 bg-light rounded">
                    <span class="text-gray-500 me-2 text-sm">Link giới thiệu chung (trang chủ):</span>
                    <code class="me-2">{{ $refUrl }}</code>
                    <button type="button" class="btn btn-sm js-copy" data-copy="{{ $refUrl }}">
                        <i class="far fa-copy mr-5"></i>Copy
                    </button>
                </div>

                @if ($entities->count())
                    <div class="table-responsive">
                        <table class="table custom align-middle">
                            <thead>
                                <tr>
                                    <th>Link rút gọn</th>
                                    <th>Trang đích</th>
                                    <th>Sub ID</th>
                                    <th class="text-end">Click</th>
                                    <th>Ngày tạo</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entities as $link)
                                    @php $shortUrl = $shortUrls[$link->id] ?? ''; @endphp
                                    <tr @if ($newLinkId === (int) $link->id) class="table-success" @endif>
                                        <td><code>{{ $shortUrl }}</code></td>
                                        <td class="text-gray-500 text-sm" style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                            title="{{ $link->destination_url }}">{{ $link->destination_url }}</td>
                                        <td>{{ $link->sub_id ?: '—' }}</td>
                                        <td class="text-end fw-bold">{{ $fmt($link->clicks_count) }}</td>
                                        <td class="text-nowrap text-gray-500">{{ $link->created_at?->format('d/m/Y') }}</td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm js-copy" data-copy="{{ $shortUrl }}">
                                                <i class="far fa-copy"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm js-qr" data-url="{{ $shortUrl }}"
                                                title="Mã QR">
                                                <i class="fas fa-qrcode"></i>
                                            </button>
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
                @else
                    <div class="text-center text-gray-400 py-4">
                        <p>Bạn chưa tạo link nào — dán URL phía trên để bắt đầu.</p>
                    </div>
                @endif

                @if (! empty($subIdBreakdown))
                    <h5 class="mt-30 mb-15">Click theo kênh (Sub ID)</h5>
                    <div class="table-responsive" style="max-width: 420px;">
                        <table class="table custom">
                            <thead>
                                <tr>
                                    <th>Kênh</th>
                                    <th class="text-end">Click</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subIdBreakdown as $subId => $count)
                                    <tr>
                                        <td>{{ $subId !== '' ? $subId : '(không gắn sub id)' }}</td>
                                        <td class="text-end">{{ $fmt($count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Mã QR</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qrContainer" class="d-inline-block"></div>
                    <div class="text-xs text-gray-500 mt-2" id="qrUrl" style="word-break: break-all;"></div>
                </div>
            </div>
        </div>
    </div>
@stop
@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        (function () {
            document.addEventListener('click', function (e) {
                var copyEl = e.target.closest('.js-copy');
                if (copyEl) {
                    var text = copyEl.getAttribute('data-copy') || '';
                    var done = function () {
                        var old = copyEl.innerHTML;
                        copyEl.innerHTML = '<i class="fas fa-check"></i>';
                        setTimeout(function () { copyEl.innerHTML = old; }, 1500);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(done);
                    } else {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        done();
                    }
                    return;
                }

                var qrEl = e.target.closest('.js-qr');
                if (qrEl && window.QRCode) {
                    var url = qrEl.getAttribute('data-url') || '';
                    var container = document.getElementById('qrContainer');
                    container.innerHTML = '';
                    new QRCode(container, { text: url, width: 180, height: 180 });
                    document.getElementById('qrUrl').textContent = url;
                    var modalEl = document.getElementById('qrModal');
                    if (window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    } else if (window.jQuery && jQuery.fn.modal) {
                        jQuery(modalEl).modal('show');
                    }
                }
            });
        })();
    </script>
@stop
