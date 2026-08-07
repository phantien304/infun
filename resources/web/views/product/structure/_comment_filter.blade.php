<div class="review-filter" id="reviewFilterBar">
    <div class="rf-row">
        <button type="button" class="rf-chip is-active" data-review-clear="1">
            Tất cả <span class="rf-count">{{ number_format($reviewCount) }}</span>
        </button>

        @for ($star = 5; $star >= 1; $star--)
            @php $cnt = (int) ($distribution[$star] ?? $distribution[(string) $star] ?? 0); @endphp
            <button type="button" class="rf-chip"
                    data-review-filter='@json(['rating' => $star])'>
                {{ $star }} sao <span class="rf-count">{{ number_format($cnt) }}</span>
            </button>
        @endfor

        <button type="button" class="rf-chip"
                data-review-filter='@json(['has_media' => 1])'>
            <i class="fa fa-image"></i> Có ảnh/video
        </button>

        <button type="button" class="rf-chip"
                data-review-filter='@json(['has_text' => 1])'>
            <i class="fa fa-comment"></i> Có bình luận
        </button>
    </div>

    @if ($reviewTags->isNotEmpty())
        <div class="rf-row rf-row--tags">
            @foreach ($reviewTags as $tag)
                <button type="button" class="rf-chip rf-chip--tag"
                        data-review-filter='@json(['tag' => $tag->code])'>
                    {{ $tag->name ?? $tag->code }}
                    @if (! empty($tag->usageCount))
                        <span class="rf-count">{{ number_format($tag->usageCount) }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    <div class="rf-row rf-row--sort">
        <label class="rf-sort-label">Sắp xếp:</label>
        <select id="reviewSortSelect" class="rf-sort-select">
            <option value="-review.helpful_count" selected>Liên quan</option>
            <option value="-review.created_at">Mới nhất</option>
            <option value="review.created_at">Cũ nhất</option>
            <option value="-review.rating">Sao cao → thấp</option>
            <option value="review.rating">Sao thấp → cao</option>
        </select>
    </div>
</div>
