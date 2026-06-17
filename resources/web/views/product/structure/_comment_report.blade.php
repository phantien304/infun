{{--
    Modal báo cáo review — Alpine state, KHÔNG dùng Bootstrap modal.

    Mở/đóng qua CustomEvent global:
      window.dispatchEvent(new CustomEvent('open-report-modal', { detail: { reviewId } }))
      window.dispatchEvent(new CustomEvent('close-report-modal'))

    JS `_comment_script.blade.php` đã update:
      - Click `.ri-btn--report` → dispatch open
      - Submit thành công → dispatch close
--}}
@auth
    <div x-data="{ open: false, reviewId: '' }"
         @open-report-modal.window="
            reviewId = $event.detail?.reviewId || '';
            const input = document.getElementById('reportReviewId');
            if (input) input.value = reviewId;
            open = true;
         "
         @close-report-modal.window="open = false"
         x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         id="reviewReportModalWrapper"
         x-transition>
        <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
            <form method="post" action="{{ route('review.report') }}" id="reviewReportForm">
                @csrf
                <input type="hidden" name="review_id" id="reportReviewId" value="">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
                    <h5 class="text-base font-semibold m-0">Báo cáo đánh giá</h5>
                    <button type="button" class="text-2xl leading-none text-gray-400 hover:text-gray-700 bg-transparent border-0"
                            aria-label="Đóng" @click="open = false">&times;</button>
                </div>
                <div class="p-5">
                    <div class="mb-4">
                        <label class="block mb-2 font-medium">Lý do</label>
                        <div class="space-y-1.5">
                            @foreach ([
                                'spam'       => 'Spam / quảng cáo',
                                'offensive'  => 'Ngôn từ thô tục, xúc phạm',
                                'fake'       => 'Đánh giá giả mạo, không có thật',
                                'irrelevant' => 'Không liên quan đến sản phẩm',
                                'other'      => 'Lý do khác',
                            ] as $code => $label)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="reason_code" value="{{ $code }}" required>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label for="reportDescription" class="block mb-1 text-sm">Mô tả thêm (không bắt buộc)</label>
                        <textarea name="description" id="reportDescription" rows="3"
                                  class="form-control" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200">
                    <button type="button"
                            class="bg-transparent text-brand-500 underline border-0 px-3 py-2 cursor-pointer"
                            @click="open = false">Hủy</button>
                    <button type="submit" class="btn btn-danger btn-sm">Gửi báo cáo</button>
                </div>
            </form>
        </div>
    </div>
@endauth
