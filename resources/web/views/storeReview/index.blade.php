@php
    $urlStoreReviewCategory = $titleStoreReviewCategory = $urlStoreReview = $title = $descriptionStoreReview = $contentStoreReview =
        '';
    if (isset($entity->storeReviewCategory->storeReviewCategoryDescription)) {
        $storeReviewCategoryDescription = $entity->storeReviewCategory->storeReviewCategoryDescription;
        if ($storeReviewCategoryDescription) {
            $urlStoreReviewCategory = $storeReviewCategoryDescription->getUrlClient();
            $titleStoreReviewCategory = $storeReviewCategoryDescription->title;
        }
    }
    $title = $entity->title;
    $urlStoreReview = $entity->url;
    $fullName = $entity?->user->full_name ?? getConfigDb('config_name');
@endphp
@extends('web::layouts.main')
@section('meta')
    <!-- META FOR FACEBOOK -->
    <meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
    <meta property="og:rich_attachment" content="true" />
    <meta property="og:type" content="article" />
    <meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
    <meta property="og:url" itemprop="url" content="{!! $urlStoreReview !!}" />
    <meta property="og:image" itemprop="thumbnailUrl" content="{!! $entity->thumbnail(800, 354) !!}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="354" />
    <meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
    <meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
    <!-- END META FOR FACEBOOK -->
    <meta content="{!! $entity->publishedDate !!}" itemprop="datePublished" name="pubdate" />
    <meta content="{!! $entity->modifiedDate !!}" itemprop="dateModified" name="lastmod" />
    <meta content="{!! $entity->publishedDate !!}" itemprop="dateCreated" />
    @include('web::share.structure._meta_common')
    <!-- Twitter Card -->
    <meta name="twitter:card" value="summary" />
    <meta name="twitter:url" content="{!! $urlStoreReview !!}" />
    <meta name="twitter:title" content="{!! $titleSeo !!}" />
    <meta name="twitter:description" content="{!! $descriptionSeo !!}" />
    <meta name="twitter:image" content="{!! $entity->thumbnail(800, 354) !!}" />
    <meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
    <meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
    <!-- End Twitter Card -->
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
    </script>
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"NewsArticle","mainEntityOfPage":{"@@type":"WebPage","@@id":"{!! $urlStoreReview !!}"},"headline":"{!! $titleSeo !!}","description":"{!! $descriptionSeo !!}","image":{"@@type":"ImageObject","url":"{!! $entity->thumbnail(900, 540) !!}","width":900,"height":540},"datePublished":"{!! $entity->publishedDate !!}","dateModified":"{!! $entity->modifiedDate !!}","author":{"@@type":"Organization","name":"{!! getConfigDb('config_name') !!}"},"publisher":{"@@type":"Organization","name":"{!! getConfigDb('config_name') !!}","logo":{"@@type":"ImageObject","url":"{!! thumbnail(getConfigDb('config_logo')) !!}","width":180,"height":55 }},"about":"{!! $titleStoreReviewCategory !!}"}
    </script>
    <script type="application/ld+json">
        {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! getConfigDb('config_name') !!}","url":"{!! getConfigDb('config_domain') !!}"}
    </script>
@stop
@section('content')
    @include('web::storeReview.structure._breadcrumb', ['titlePage' => $title])
    <div class="page-content mb-50">
        <div class="container-xl">
            <div class="row">
                <div class="col-lg-9">
                    <div class="single-page pt-50 pr-30">
                        <div class="single-header style-2">
                            <div class="row">
                                <div class="col-xl-10 col-lg-12 m-auto">
                                    <h6 class="mb-10">
                                        <a href="{!! $urlStoreReviewCategory !!}" title="{!! $titleStoreReviewCategory !!}">
                                            {!! $titleStoreReviewCategory !!}
                                        </a>
                                    </h6>
                                    <h1 class="display-5 fw-600 mb-10">{!! $title !!}</h1>
                                    <div class="single-header-meta">
                                        <div class="entry-meta meta-1 font-xs mt-15 mb-15">
                                            <span class="post-by">Bởi
                                                <img alt="author-avatar" class="avatar" src="{!! asset('web/images/avatar.png') !!}"
                                                    height="32" width="32">
                                                {!! $fullName !!}
                                            </span>
                                            <span class="post-on has-dot">{!! $entity->diffForHumans !!}</span>
                                        </div>
                                        <div class="social-icons single-share">
                                            <ul class="text-grey-5 d-inline-block">
                                                <li class="mr-5">
                                                    <a href="#">
                                                        <img src="/web/images/theme/icons/icon-bookmark.svg" alt="bookmark">
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <figure class="single-thumbnail">
                            <img src="{!! $entity->thumbnail(1052, 490) !!}" alt="{!! $title !!}" />
                        </figure>
                        <div class="single-content">
                            <div class="row">
                                <div class="col-xl-10 col-lg-12 m-auto">
                                    {!! $entity->content() !!}
                                    <div class="entry-bottom mt-50 mb-30 wow fadeIn animated">
                                        <div class="social-icons single-share">
                                            <ul class="text-grey-5 d-inline-block">
                                                <li><strong class="mr-10">Share this:</strong></li>
                                                <li class="social-facebook">
                                                    <a href="#">
                                                        <img src="/web/images/theme/icons/icon-facebook.svg" alt="">
                                                    </a>
                                                </li>
                                                <li class="social-twitter">
                                                    <a href="#">
                                                        <img src="/web/images/theme/icons/icon-twitter.svg" alt="">
                                                    </a>
                                                </li>
                                                <li class="social-instagram">
                                                    <a href="#">
                                                        <img src="/web/images/theme/icons/icon-instagram.svg"
                                                            alt="">
                                                    </a>
                                                </li>
                                                <li class="social-linkedin">
                                                    <a href="#">
                                                        <img src="/web/images/theme/icons/icon-pinterest.svg"
                                                            alt="">
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <!--Comment form-->
                                    <div class="comment-form">
                                        <h3 class="mb-30">Bình luận</h3>
                                        <div class="row">
                                            <div class="fb-comments" data-href="{!! $urlStoreReview !!}"
                                                data-colorscheme="light" data-numposts="5" data-order-by="social"
                                                data-width="100%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @include('web::storeReview.structure._side_bar', ['class' => 'pt-50'])
            </div>
            @if (count($storeReviews))
                <section class="store-review section-padding">
                    <div class="wow fadeIn animated">
                        <h3 class="mb-30 text-center text-9">Khách hàng</h3>
                        <div class="carausel-5-columns-cover arrow-center position-relative">
                            <div class="slider-arrow slider-arrow-2 carausel-5-columns-arrow"
                                id="review-5-columns-arrows"></div>
                            <div class="carausel-5-columns" id="review-5-columns">
                                @foreach ($storeReviews as $item)
                                    <div class="card-1">
                                        <figure class="img-hover-scale overflow-hidden">
                                            <a href="{!! $item->url !!}" title="{!! $item->name !!}">
                                                <img src="{!! $item->thumbnail(312, 340) !!}" alt="{!! $item->name !!}">
                                                <div class="author-review">
                                                    <i class="fab fa-{{ $item->social_icon }}"></i>
                                                    <span>by {!! $item->name !!}</span>
                                                </div>
                                            </a>
                                        </figure>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
