@section('banner')
    <style>
        .infun-hero-slide {
            position: relative;
            overflow: hidden;
            min-height: 420px;
        }

        @media (min-width: 640px) {
            .infun-hero-slide {
                min-height: 520px;
            }
        }

        @media (min-width: 1024px) {
            .infun-hero-slide {
                min-height: 680px;
            }
        }

        .infun-hero-media {
            position: absolute;
            inset: 0;
        }

        .infun-hero-media img {
            display: block;
            height: 100%;
            width: 100%;
            object-fit: cover;
            object-position: 30% center;
        }

        .infun-hero-text-wrap {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            height: 100%;
            min-height: inherit;
            padding: 0 1.5rem;
        }

        .infun-hero-text-container {
            margin: 0 auto;
            width: 100%;
            max-width: 80rem;
        }

        .infun-hero-text-inner {
            max-width: 26rem;
            text-align: left;
        }

        .infun-hero-text-inner h1 {
            margin: 0 0 .875rem;
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1.08;
            color: #0a0a0a;
        }

        .infun-hero-text-inner p {
            margin: 0 0 1.5rem;
            font-size: .9375rem;
            line-height: 1.6;
            letter-spacing: .01em;
            color: #1f2430;
        }

        @media (min-width: 640px) {
            .infun-hero-text-wrap {
                padding: 0 2.5rem;
            }

            .infun-hero-text-inner h1 {
                font-size: 2.5rem;
            }

            .infun-hero-text-inner p {
                font-size: 1rem;
            }
        }

        @media (min-width: 1024px) {
            .infun-hero-text-wrap {
                padding: 0 4rem;
            }

            .infun-hero-text-inner h1 {
                font-size: 3.125rem;
            }
        }

        .infun-hero-slide .cta a {
            display: inline-flex;
            align-items: center;
            background-color: #000;
            color: #fff;
            padding: 11px 20px;
            border-radius: 3px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .28em;
            transition: background-color .15s ease;
        }

        .infun-hero-slide .cta a:hover {
            background-color: #e88a5e;
        }
    </style>
    @foreach ($banners as $banner)
        <section class="home-slider relative infunstudio">
            <div class="hero-slider-1 style-4 dot-style-1 dot-style-1-position-1">
                @foreach ($banner->bannerValues as $index => $item)
                    @php $opacity = $index == 0 ? 1 : 0; @endphp
                    <div class="single-hero-slider single-animation-wrap infun-hero-slide"
                        style="opacity:{{ $opacity }}">
                        @if ($item->image)
                            <div class="infun-hero-media">
                                <img src="{{ storageImage()->url($item->image) }}" alt=""
                                    onerror="this.style.display='none'">
                            </div>
                        @endif
                        <div class="infun-hero-text-wrap">
                            <div class="infun-hero-text-container">
                                <div class="infun-hero-text-inner">
                                    @if (isset($item->valueDescription))
                                        <h1>{!! $item->valueDescription->title !!}</h1>
                                        <p>{!! $item->valueDescription->content !!}</p>
                                    @else
                                        <h1>{{ getConfigDb('config_name') ?: 'In&Fun Studio' }}</h1>
                                        <p>Gia công dấu khắc, in ấn và thiết kế bao bì theo yêu cầu.</p>
                                    @endif
                                    @php
                                        $rawLink = trim((string) $item->link);
                                        $linkIsHtml = str_contains($rawLink, '<a');
                                    @endphp
                                    @if ($linkIsHtml) <div class="cta">{!! $rawLink !!}</div>
                                    @elseif ($rawLink !== '')
                                        <div class="cta">
                                            <a href="{{ $rawLink }}" target="_blank" rel="noopener">Xem ngay</a>
                                        </div> @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="slider-arrow hero-slider-1-arrow"></div>
        </section>
    @endforeach
@endsection
