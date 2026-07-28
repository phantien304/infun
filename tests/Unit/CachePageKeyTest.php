<?php

namespace Tests\Unit;

use App\Http\Middleware\CachePage;
use Illuminate\Http\Request;
use Tests\TestCase;

class CachePageKeyTest extends TestCase
{
    private function invoke(string $method, string $url)
    {
        $middleware = new CachePage();
        $ref = new \ReflectionMethod($middleware, $method);
        $ref->setAccessible(true);

        return $ref->invoke($middleware, Request::create($url));
    }

    /** Tái hiện đúng bước lọc query mà handle() làm trước khi gọi isCacheableQuery/cacheKey. */
    private function significantQuery(CachePage $middleware, Request $request): array
    {
        $ignoredProp = new \ReflectionProperty($middleware, 'ignoredQueryParams');
        $ignoredProp->setAccessible(true);

        return array_diff_key($request->query(), array_flip($ignoredProp->getValue($middleware)));
    }

    private function isCacheable(string $url): bool
    {
        $middleware = new CachePage();
        $request = Request::create($url);
        $query = $this->significantQuery($middleware, $request);

        $ref = new \ReflectionMethod($middleware, 'isCacheableQuery');
        $ref->setAccessible(true);

        return $ref->invoke($middleware, $query);
    }

    private function keyFor(string $url): string
    {
        $middleware = new CachePage();
        $request = Request::create($url);
        $query = $this->significantQuery($middleware, $request);

        $ref = new \ReflectionMethod($middleware, 'cacheKey');
        $ref->setAccessible(true);

        return $ref->invoke($middleware, $request, $query);
    }

    // ---- isCacheableQuery: query rỗng / chỉ whitelist → cache ----

    public function test_trang_khong_query_thi_cache(): void
    {
        $this->assertTrue($this->isCacheable('/san-pham'));
        $this->assertTrue($this->isCacheable('/gau-bong-p12'));
    }

    public function test_chi_co_tracking_thi_van_cache(): void
    {
        $this->assertTrue($this->isCacheable('/san-pham?utm_source=a&ref=b&gclid=c&fbclid=d'));
    }

    public function test_page_sort_filter_hop_le_thi_van_cache(): void
    {
        $this->assertTrue($this->isCacheable('/san-pham?page=2'));
        $this->assertTrue($this->isCacheable('/san-pham?sort=price'));
        $this->assertTrue($this->isCacheable('/san-pham?filter[category_id]=5'));
    }

    public function test_search_hoac_filter_khong_whitelist_thi_bypass(): void
    {
        $this->assertFalse($this->isCacheable('/san-pham?q=abc'));
        $this->assertFalse($this->isCacheable('/san-pham?filter[in_stock]=1'));
    }

    // ---- isExcepted: trang động / theo-user ----

    public function test_except_cart_checkout_account_api(): void
    {
        $this->assertTrue($this->invoke('isExcepted', '/cart/badge'));
        $this->assertTrue($this->invoke('isExcepted', '/checkout/cart'));
        $this->assertTrue($this->invoke('isExcepted', '/account/wishlist'));
        $this->assertTrue($this->invoke('isExcepted', '/api/v1/x'));
    }

    public function test_trang_cong_khai_khong_except(): void
    {
        $this->assertFalse($this->invoke('isExcepted', '/san-pham'));
        $this->assertFalse($this->invoke('isExcepted', '/gau-bong-p12'));
        $this->assertFalse($this->invoke('isExcepted', '/'));
    }

    // ---- cacheKey: tracking không đổi key; path khác → key khác ----

    public function test_affiliate_va_khach_thuong_chung_key(): void
    {
        $this->assertSame(
            $this->keyFor('/gau-bong-p12'),
            $this->keyFor('/gau-bong-p12?ref=kol9&utm_campaign=abc&gclid=zzz'),
        );
    }

    public function test_path_khac_nhau_key_khac_nhau(): void
    {
        $this->assertNotSame(
            $this->keyFor('/gau-bong-p12'),
            $this->keyFor('/gau-bong-p13'),
        );
    }
}
