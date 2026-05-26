<script type="application/ld+json">
    {"@@context":"http://schema.org","@@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
</script>
@foreach ($entities as $product)
    <script type="application/ld+json">
        {"@@context":"https://schema.org/","@@type":"Product","url":"{!! $product->url !!}","image":"{!! $product->thumbnail(540, 540) !!}","name":"{!! $product->name !!}","description":"{!! $product->description !!}","sku":"{!! $product->sku !!}","aggregateRating":{"@@type":"AggregateRating","ratingValue":"{!! $product->rating !!}","reviewCount":"{!! $product->totalRating ?? 0 !!}"},"offers":{"@@type":"Offer","url":"{!! $product->url !!}","itemCondition":"https://schema.org/NewCondition","availability":"https://schema.org/InStock","priceCurrency":"VND","price":{!! $product->price !!}}}
    </script>
@endforeach
<script type="application/ld+json">
    {"@@context":"http://schema.org","@@type":"WebSite","name":"{!! getConfigDb('config_name') !!}","url":"{!! getConfigDb('config_domain') !!}"}
</script>
