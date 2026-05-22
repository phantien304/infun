<script type="application/ld+json">
    {"@context":"http://schema.org","@type":"BreadcrumbList","itemListElement": {!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) !!}}
</script>
@foreach($entities as $product)
    @if(isset($product->productDescription))
        <script type="application/ld+json">
            {"@context":"https://schema.org/","@type":"Product","url":"{!! $product->productDescription->getUrlClient() !!}","image":"{!! $product->getImageClient(540, 540) !!}","name":"{!! $product->productDescription->name !!}","description":"{!! $product->productDescription->description !!}","sku":"{!! $product->sku !!}","aggregateRating":{"@type":"AggregateRating","ratingValue":"{!! $product->rating !!}","reviewCount":"{!! $product->total_rating ?? 0 !!}"},"offers":{"@type":"Offer","url":"{!! $product->productDescription->getUrlClient() !!}","itemCondition":"https://schema.org/NewCondition","availability":"https://schema.org/InStock","priceCurrency":"VND","price":{!! $product->price !!}}}
        </script>
    @endif
@endforeach
<script type="application/ld+json">
    {"@context":"http://schema.org","@type":"WebSite","name":"{!! getConfigDb('config_name') !!}","url":"{!! getConfigDb('config_domain') !!}"}
</script>
