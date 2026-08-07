<div class="review-list">
    @forelse ($reviews ?? [] as $r)
        <article class="review-item" data-review-id="{{ $r->id }}">
            <div class="ri-head">
                <div class="ri-avatar">
                    @if (! empty($r->userAvatar) && ! $r->isAnonymous)
                        <img src="{{ $r->userAvatar }}" alt="{{ $r->displayName }}">
                    @else
                        <span class="ri-avatar-letter">{{ strtoupper(mb_substr($r->displayName ?? '?', 0, 1)) }}</span>
                    @endif
                </div>
                <div class="ri-meta">
                    <div class="ri-name">
                        {{ $r->isAnonymous ? 'Người dùng ẩn danh' : ($r->displayName ?? $r->author ?? '---') }}
                        @if (! empty($r->orderId))
                            <span class="ri-badge ri-badge--verified" title="Đã mua hàng">
                                <i class="fa fa-check-circle"></i> Đã mua
                            </span>
                        @endif
                    </div>
                    <div class="ri-stars">
                        @for ($i = 1; $i <= 5; $i++)
                            <i class="fa fa-star {{ $i <= $r->rating ? 'is-on' : '' }}"></i>
                        @endfor
                    </div>
                    <div class="ri-sub">
                        <span class="ri-date">{{ $r->createdAtHuman ?? optional($r->createdAt)->format('d/m/Y H:i') }}</span>
                        @if (! empty($r->variantLabel))
                            <span class="ri-dot">|</span>
                            <span class="ri-variant">Phân loại: {{ $r->variantLabel }}</span>
                        @endif
                        @if (! empty($r->source) && $r->source !== 'web')
                            <span class="ri-dot">|</span>
                            <span class="ri-source">{{ ucfirst($r->source) }}</span>
                        @endif
                    </div>
                </div>
            </div>

            @if (! empty($r->title))
                <h5 class="ri-title">{{ $r->title }}</h5>
            @endif
            @if (! empty($r->text))
                <div class="ri-text">{{ $r->text }}</div>
            @endif

            @if (! empty($r->ratings) && count($r->ratings) > 0)
                <details class="ri-criteria">
                    <summary>Đánh giá chi tiết theo tiêu chí</summary>
                    <ul class="ri-criteria-list">
                        @foreach ($r->ratings as $rr)
                            <li>
                                <span class="ri-crit-name">{{ $rr->criteriaName ?? $rr->code }}:</span>
                                @for ($i = 1; $i <= 5; $i++)
                                    <i class="fa fa-star {{ $i <= $rr->rating ? 'is-on' : '' }}"></i>
                                @endfor
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if (! empty($r->media) && count($r->media) > 0)
                <div class="ri-media">
                    @foreach ($r->media as $m)
                        @if ($m->type === 'video')
                            <a class="ri-media-cell ri-media-cell--video"
                               href="{{ $m->url }}" data-fancybox="rv-{{ $r->id }}"
                               data-caption="Video đánh giá">
                                <img src="{{ $m->thumbnail ?: $m->url }}" alt="">
                                <span class="ri-media-play"><i class="fa fa-play"></i></span>
                                @if ($m->duration)
                                    <span class="ri-media-dur">{{ gmdate($m->duration >= 3600 ? 'H:i:s' : 'i:s', $m->duration) }}</span>
                                @endif
                            </a>
                        @else
                            <a class="ri-media-cell" href="{{ $m->url }}" data-fancybox="rv-{{ $r->id }}">
                                <img src="{{ $m->thumbnail ?: $m->url }}" alt="" loading="lazy">
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            @if (! empty($r->tags) && count($r->tags) > 0)
                <div class="ri-tags">
                    @foreach ($r->tags as $t)
                        <span class="ri-tag">{{ $t->name ?? $t->code }}</span>
                    @endforeach
                </div>
            @endif

            @if (! empty($r->replies) && count($r->replies) > 0)
                <div class="ri-replies">
                    @foreach ($r->replies as $rp)
                        <div class="ri-reply ri-reply--{{ $rp->authorType === 1 ? 'shop' : ($rp->authorType === 2 ? 'admin' : 'user') }}">
                            <div class="ri-reply-head">
                                <strong>
                                    @switch($rp->authorType)
                                        @case(1) <i class="fa fa-store"></i> Phản hồi của Shop @break
                                        @case(2) <i class="fa fa-shield-alt"></i> Quản trị viên @break
                                        @default {{ $rp->authorName ?? 'Người dùng' }}
                                    @endswitch
                                </strong>
                                <span class="ri-reply-date">{{ optional($rp->createdAt)->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="ri-reply-text">{{ $rp->text }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="ri-foot">
                <button type="button" class="ri-btn ri-btn--helpful"
                        data-review-id="{{ $r->id }}" data-vote="1"
                        aria-pressed="{{ ($r->myVote ?? 0) === 1 ? 'true' : 'false' }}">
                    <i class="fa fa-thumbs-up"></i> Hữu ích
                    <span class="ri-count">({{ number_format($r->helpfulCount ?? 0) }})</span>
                </button>
                <button type="button" class="ri-btn ri-btn--unhelpful"
                        data-review-id="{{ $r->id }}" data-vote="-1"
                        aria-pressed="{{ ($r->myVote ?? 0) === -1 ? 'true' : 'false' }}">
                    <i class="fa fa-thumbs-down"></i>
                    <span class="ri-count">({{ number_format($r->unhelpfulCount ?? 0) }})</span>
                </button>
                @auth
                    <button type="button" class="ri-btn ri-btn--report"
                            data-review-id="{{ $r->id }}"
                            data-toggle="modal" data-target="#reviewReportModal">
                        <i class="fa fa-flag"></i> Báo cáo
                    </button>
                @endauth
            </div>
        </article>
    @empty
        <div class="review-empty">
            <i class="fa fa-comment-slash"></i>
            <p>Chưa có đánh giá nào phù hợp bộ lọc.</p>
        </div>
    @endforelse
</div>
@if ($reviews && method_exists($reviews, 'hasPages') && $reviews->hasPages())
    <div class="review-paging">
        @includeIf('web::share.structure._paging', ['paginator' => $reviews])
    </div>
@endif
