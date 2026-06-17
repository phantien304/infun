{{--
    Write review form — đa tiêu chí + tags + title + text + media + anonymous.
    Naming convention: rating[criteria_code] để controller dispatch sang
    review_rating pivot.

    Policy gate (admin cấu hình qua setting('config_review_policy')):
      - public   : Bất cứ ai (bao gồm guest chưa login)
      - login    : Phải đăng nhập, không cần mua hàng
      - purchase : Phải đăng nhập + đã mua hàng (có order completed)
    Value đọc từ ProductController::index qua setting('config_review_policy', ...)
    fallback default review.default_policy trong config/core/config.php.
    UI phân nhánh:
      * canSubmit=false + chưa login           → prompt đăng nhập
      * canSubmit=false + login + chưa mua     → prompt mua hàng (policy verified)
      * canSubmit=true  + hasReviewed          → khóa form, hiển thị thông báo cảm ơn
      * canSubmit=true                          → render form đầy đủ
--}}
@php
    $policyPublic = getCoreConfig('review.policy.public');
    $policyLogin = getCoreConfig('review.policy.login');
    $policyVerified = getCoreConfig('review.policy.purchase');
    $effPolicy = $reviewPolicy ?? $policyPublic;
    $isLoggedIn = auth()->check();
    $canSubmit =
        $effPolicy === $policyPublic ||
        ($effPolicy === $policyLogin && $isLoggedIn) ||
        ($effPolicy === $policyVerified && $isLoggedIn && ($hasVerifiedPurchase ?? false));
@endphp

<div class="review-write" id="reviewWriteAnchor">
    @if (!$canSubmit && !$isLoggedIn)
        <div class="rw-login-prompt">
            <i class="fa fa-lock"></i>
            <p>
                @if ($effPolicy === $policyVerified)
                    Vui lòng <a href="{{ route('auth.login') }}">đăng nhập</a>
                    và mua sản phẩm để có thể đánh giá.
                @else
                    Vui lòng <a href="{{ route('auth.login') }}">đăng nhập</a> để viết đánh giá.
                @endif
            </p>
        </div>
    @elseif (!$canSubmit && $isLoggedIn && $effPolicy === $policyVerified)
        <div class="rw-login-prompt">
            <i class="fa fa-shopping-bag"></i>
            <p>Chỉ khách hàng đã mua sản phẩm này mới có thể đánh giá.</p>
        </div>
    @elseif ($hasReviewed)
        <div class="rw-locked">
            <i class="fa fa-check-circle"></i>
            Bạn đã đánh giá sản phẩm này. Cảm ơn bạn!
        </div>
    @else
        <details class="rw-collapse" open>
            <summary class="rw-summary">
                <span><i class="fa fa-pen"></i> Viết đánh giá của bạn</span>
            </summary>

            <form action="{!! route('review.saveReview') !!}" method="post" enctype="multipart/form-data" class="rw-form"
                id="reviewWriteForm">
                @csrf
                <input type="hidden" name="product_id" value="{{ $entity->id }}">

                {{-- Multi-criteria rating --}}
                <div class="rw-criteria">
                    @forelse ($reviewCriteria as $c)
                        <div class="rw-crit-row" data-criteria="{{ $c->code }}">
                            <label class="rw-crit-label">
                                {{ $c->name ?? $c->code }}
                                @if (!empty($c->isRequired))
                                    <span class="rw-required">*</span>
                                @endif
                            </label>
                            <div class="rw-stars" data-input="rating[{{ $c->code }}]">
                                @for ($i = 5; $i >= 1; $i--)
                                    <input type="radio" id="rw-{{ $c->code }}-{{ $i }}"
                                        name="rating[{{ $c->code }}]" value="{{ $i }}"
                                        {{ $c->isRequired ? 'required' : '' }}>
                                    <label for="rw-{{ $c->code }}-{{ $i }}"
                                        title="{{ $i }} sao">
                                        <i class="fa fa-star"></i>
                                    </label>
                                @endfor
                                <span class="rw-stars-hint"></span>
                            </div>
                        </div>
                    @empty
                        {{-- Fallback: 1 rating tổng thể nếu chưa seed criteria --}}
                        <div class="rw-crit-row">
                            <label class="rw-crit-label">Đánh giá tổng thể <span class="rw-required">*</span></label>
                            <div class="rw-stars">
                                @for ($i = 5; $i >= 1; $i--)
                                    <input type="radio" id="rw-overall-{{ $i }}" name="rating"
                                        value="{{ $i }}" required>
                                    <label for="rw-overall-{{ $i }}"><i class="fa fa-star"></i></label>
                                @endfor
                                <span class="rw-stars-hint"></span>
                            </div>
                        </div>
                    @endforelse
                </div>

                {{-- Tags --}}
                @if ($reviewTags->isNotEmpty())
                    <div class="rw-tags">
                        <label class="rw-section-label">Chọn các đặc điểm phù hợp:</label>
                        <div class="rw-tag-grid">
                            @foreach ($reviewTags as $tag)
                                <label class="rw-tag-chip">
                                    <input type="checkbox" name="tags[]" value="{{ $tag->code }}">
                                    <span>{{ $tag->name ?? $tag->code }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Title --}}
                <div class="rw-field">
                    <label for="rw-title">Tiêu đề (không bắt buộc)</label>
                    <input type="text" id="rw-title" name="title" maxlength="255" class="form-control"
                        placeholder="Tóm tắt cảm nhận của bạn">
                </div>

                {{-- Body --}}
                <div class="rw-field">
                    <label for="rw-text">Nội dung đánh giá <span class="rw-required">*</span></label>
                    <textarea id="rw-text" name="text" rows="5" required class="form-control"
                        placeholder="Chia sẻ chi tiết về sản phẩm: chất lượng, mức độ hài lòng… Đánh giá hữu ích sẽ giúp khách hàng khác mua sắm tốt hơn."
                        maxlength="2000"></textarea>
                    <div class="rw-counter"><span class="rw-counter-now">0</span>/2000</div>
                </div>

                {{-- Media upload --}}
                <div class="rw-field rw-media">
                    <label>Thêm ảnh / video (tối đa 9 ảnh + 1 video)</label>
                    <div class="rw-media-drop" id="rwMediaDrop">
                        <input type="file" id="rwMediaInput" name="media[]" accept="image/*,video/*" multiple hidden>
                        <label for="rwMediaInput" class="rw-media-trigger">
                            <i class="fa fa-camera"></i>
                            <span>Bấm hoặc kéo thả file vào đây</span>
                        </label>
                        <div class="rw-media-preview" id="rwMediaPreview"></div>
                    </div>
                </div>

                {{-- Anonymous (chỉ hiện khi login — guest mặc định đã ẩn danh qua author field) --}}
                @if ($isLoggedIn)
                    <div class="rw-field rw-anon">
                        <label class="rw-checkbox">
                            <input type="checkbox" name="is_anonymous" value="1">
                            <span>Hiển thị tên tôi dưới dạng <strong>Người dùng ẩn danh</strong></span>
                        </label>
                    </div>
                @else
                    {{-- Guest cần điền tên + email --}}
                    <div class="rw-field">
                        <label for="rw-author">Tên hiển thị <span class="rw-required">*</span></label>
                        <input type="text" id="rw-author" name="author" required maxlength="255"
                            class="form-control" placeholder="Tên hiển thị trên review">
                    </div>
                    <div class="rw-field">
                        <label for="rw-email">Email (không hiển thị công khai)</label>
                        <input type="email" id="rw-email" name="email" maxlength="255" class="form-control"
                            placeholder="email@example.com">
                    </div>
                @endif

                <div class="rw-actions">
                    {{-- .btn (component layer Tailwind) đã có brand color default
                         → bỏ .btn-primary Bootstrap. .btn-link reset: dùng
                         arbitrary để tránh ghi đè brand background. --}}
                    <button type="submit" class="btn rw-submit">
                        <i class="fa fa-paper-plane"></i> Gửi đánh giá
                    </button>
                    <button type="reset" class="btn bg-transparent text-gray-500 hover:text-gray-700">
                        Huỷ
                    </button>
                </div>
            </form>
        </details>
    @endif
</div>
