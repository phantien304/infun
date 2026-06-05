{{--
    Modal báo cáo review xấu/spam — shared cho mọi review trên trang.
    Click button .ri-btn--report → JS pre-fill #reportReviewId.
--}}
@auth
    <div class="modal fade" id="reviewReportModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="post" action="{{ routeArea('review.report') }}" id="reviewReportForm">
                    @csrf
                    <input type="hidden" name="review_id" id="reportReviewId" value="">
                    <div class="modal-header">
                        <h5 class="modal-title">Báo cáo đánh giá</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="d-block">Lý do</label>
                            @foreach ([
                                'spam'       => 'Spam / quảng cáo',
                                'offensive'  => 'Ngôn từ thô tục, xúc phạm',
                                'fake'       => 'Đánh giá giả mạo, không có thật',
                                'irrelevant' => 'Không liên quan đến sản phẩm',
                                'other'      => 'Lý do khác',
                            ] as $code => $label)
                                <label class="d-block">
                                    <input type="radio" name="reason_code" value="{{ $code }}" required>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <div class="form-group">
                            <label for="reportDescription">Mô tả thêm (không bắt buộc)</label>
                            <textarea name="description" id="reportDescription" rows="3"
                                      class="form-control" maxlength="500"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-danger">Gửi báo cáo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endauth
