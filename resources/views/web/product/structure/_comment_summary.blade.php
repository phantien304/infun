{{--
    Rating summary header (Shopee-style).
    Hiển thị: avg rating to + sao + count + distribution 5..1 sao + criteria breakdown.
    Distribution bar dùng data-attr để JS dispatch AJAX filter (không href).
--}}
<div class="review-summary">
    <div class="rs-overview">
        <div class="rs-overview__avg">
            <span class="rs-avg-number">{{ number_format($ratingAvg, 1) }}</span>
            <span class="rs-avg-slash">/ 5</span>
        </div>
        {{-- CSS overlay technique cho half-star (FA 5.0.6 không có fa-star-half-alt) --}}
        <div class="rs-overview__stars rating-fractional" style="--rating-pct: {{ max(0, min(100, $ratingAvg * 20)) }}%;">
            <span class="rf-bg">
                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
            </span>
            <span class="rf-fg">
                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
            </span>
        </div>
        <div class="rs-overview__count">
            {{ number_format($reviewCount) }} đánh giá
        </div>
    </div>

    {{-- Distribution 5..1 sao — click filter qua AJAX --}}
    <div class="rs-distribution">
        @for ($star = 5; $star >= 1; $star--)
            @php
                $cnt = (int) ($distribution[$star] ?? $distribution[(string) $star] ?? 0);
                $pct = $reviewCount > 0 ? round($cnt * 100 / $reviewCount, 1) : 0;
            @endphp
            <button type="button" class="rs-dist-row"
                    data-review-filter='@json(['rating' => $star])'>
                <span class="rs-dist-label">{{ $star }} <i class="fa fa-star"></i></span>
                <span class="rs-dist-bar"><span class="rs-dist-fill" style="width: {{ $pct }}%"></span></span>
                <span class="rs-dist-count">{{ number_format($cnt) }}</span>
            </button>
        @endfor
    </div>

    {{-- Breakdown đa tiêu chí --}}
    @if ($criteria->isNotEmpty())
        <div class="rs-criteria">
            <div class="rs-criteria__title">Đánh giá theo tiêu chí</div>
            <div class="rs-criteria__grid">
                @foreach ($criteria as $c)
                    @php
                        $code = $c->code ?? null;
                        $avg  = (float) ($criteriaAverages[$code] ?? 0);
                    @endphp
                    <div class="rs-crit-cell">
                        <div class="rs-crit-name">{{ $c->name ?? $code }}</div>
                        <div class="rs-crit-stars">
                            {{-- Half-star overlay — đồng bộ với header --}}
                            <span class="rs-crit-fractional rating-fractional" style="--rating-pct: {{ max(0, min(100, $avg * 20)) }}%;">
                                <span class="rf-bg">
                                    <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                                </span>
                                <span class="rf-fg">
                                    <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                                </span>
                            </span>
                            <span class="rs-crit-value">{{ number_format($avg, 1) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
