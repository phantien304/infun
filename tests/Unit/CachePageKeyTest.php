<?php

namespace Tests\Unit;

use App\Http\Middleware\CachePage;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * CachePage bản 80/20:
 *   - Chỉ cache trang SẠCH (không query có nghĩa). Có filter/sort/page/search → BYPASS.
 *   - Param tracking (utm/aff/ref/gclid/fbclid) KHÔNG tính là query → vẫn cache, chung key.
 *   - Trang cart/checkout/account/api → không cache (except).
 *   - Key theo path (+locale+currency), KHÔNG kèm query/device.
 */
class CachePageKeyTest extends TestCase
{
    private function invoke(string $method, string $url)
    {
        $middleware = new CachePage();
        $ref = new \ReflectionMethod($middleware, $method);
        $ref->setAccessible(true);

        return $ref->invoke($middleware, Request::create($url));
    }

    // ---- hasMeaningfulQuery: chỉ trang sạch mới được cache ----

    public function test_trang_khong_query_thi_cache(): void
    {
        $this->assertFalse($this->invoke('hasMeaningfulQuery', '/san-pham'));
        $this->assertFalse($this->invoke('hasMeaningfulQuery', '/gau-bong-p12'));
    }

    public function test_chi_co_tracking_thi_van_cache(): void
    {
        $this->assertFalse($this->invoke('hasMeaningfulQuery', '/san-pham?utm_source=a&ref=b&gclid=c&fbclid=d'));
    }

    public function test_co_filter_sort_page_search_thi_bypass(): void
    {
        $this->assertTrue($this->invoke('hasMeaningfulQuery', '/san-pham?page=2'));
        $this->assertTrue($this->invoke('hasMeaningfulQuery', '/san-pham?sort=price'));
        $this->assertTrue($this->invoke('hasMeaningfulQuery', '/san-pham?q=abc'));
        $this->assertTrue($this->invoke('hasMeaningfulQuery', '/san-pham?filter[in_stock]=1'));
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
            $this->invoke('cacheKey', '/gau-bong-p12'),
            $this->invoke('cacheKey', '/gau-bong-p12?ref=kol9&utm_campaign=abc&gclid=zzz'),
        );
    }

    public function test_path_khac_nhau_key_khac_nhau(): void
    {
        $this->assertNotSame(
            $this->invoke('cacheKey', '/gau-bong-p12'),
            $this->invoke('cacheKey', '/gau-bong-p13'),
        );
    }
}
