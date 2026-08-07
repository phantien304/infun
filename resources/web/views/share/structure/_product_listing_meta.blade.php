@php
    $resourceUrl   = $resourceUrl   ?? request()->fullUrl();
    $resourceImage = $resourceImage ?? thumbnail(getModuleConfig('img_default'), 800, 354);
@endphp
<!-- META FOR FACEBOOK -->
<meta property="og:site_name" content="{!! getConfigDb('config_name') !!}" />
<meta property="og:rich_attachment" content="true" />
<meta property="og:type" content="article" />
<meta property="article:publisher" content="{!! getConfigDb('config_facebook') !!}" />
<meta property="og:url" itemprop="url" content="{!! $resourceUrl !!}" />
<meta property="og:image" itemprop="thumbnailUrl" content="{!! $resourceImage !!}" />
<meta property="og:image:width" content="800" />
<meta property="og:image:height" content="354" />
<meta content="{!! $titleSeo !!}" itemprop="headline" property="og:title" />
<meta content="{!! $descriptionSeo !!}" itemprop="description" property="og:description" />
<!-- END META FOR FACEBOOK -->
@include('web::share.structure._meta_common')
<!-- Twitter Card -->
<meta name="twitter:card" value="summary" />
<meta name="twitter:url" content="{!! $resourceUrl !!}" />
<meta name="twitter:title" content="{!! $titleSeo !!}" />
<meta name="twitter:description" content="{!! $descriptionSeo !!}" />
<meta name="twitter:image" content="{!! $resourceImage !!}" />
<meta name="twitter:site" content="{!! getConfigDb('config_name') !!}" />
<meta name="twitter:creator" content="{!! getConfigDb('config_name') !!}" />
<!-- End Twitter Card -->
@include('web::share.structure._schema_product_list')
