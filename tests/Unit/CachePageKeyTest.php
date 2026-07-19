<?php

namespace Tests\Unit;

use App\Http\Middleware\CachePage;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Cache key của CachePage sau rà soát 2026-07:
 *   - Bỏ param tracking (aff/utm/ref/gclid/fbclid) nhưng vẫn cache.
 *   - Chỉ giữ param ALLOWLIST (page/sort/filter...) trong key.
 *   - Param lạ ngoài allowlist ⇒ BYPASS (hasDisallowedParams = true).
 *   - `page` phải là số và <= trần (pageWithinCap).
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

    private function normalize(string $url): string
    {
        return $this->invoke('normalizedUrl', $url);
    }

    // ---- Tracking params: bỏ khỏi key, vẫn cache ----

    public function test_bo_param_tracking_giu_param_noi_dung(): void
    {
        $this->assertSame(
            'http://localhost/san-pham?page=2',
            $this->normalize('/san-pham?page=2&aff=kol1&aff_click=tok123&utm_source=aff_kol1&utm_medium=affiliates&fbclid=x'),
        );
    }

    public function test_url_khong_query_giu_nguyen(): void
    {
        $this->assertSame('http://localhost/san-pham', $this->normalize('/san-pham'));
    }

    public function test_khach_affiliate_va_khach_thuong_chung_key(): void
    {
        $this->assertSame(
            $this->normalize('/gau-bong-p12'),
            $this->normalize('/gau-bong-p12?ref=kol9&utm_campaign=link_abc&gclid=zzz'),
        );
    }

    // ---- Allowlist: giữ param nội dung, key ổn định bất kể thứ tự ----

    public function test_giu_param_allowlist_on_dinh_thu_tu(): void
    {
        $this->assertSame(
            'http://localhost/san-pham?page=2&sort=price',
            $this->normalize('/san-pham?sort=price&page=2'),
        );
        $this->assertSame(
            $this->normalize('/san-pham?sort=price&page=2'),
            $this->normalize('/san-pham?page=2&sort=price'),
        );
    }

    public function test_param_ngoai_allowlist_bi_loai_khoi_key(): void
    {
        // 'a','b' không thuộc allowlist ⇒ rơi khỏi key (chỉ dùng để so key ổn định).
        $this->assertSame(
            $this->normalize('/x?b=2&a=1'),
            $this->normalize('/x?a=1&b=2'),
        );
        $this->assertSame('http://localhost/x', $this->normalize('/x?a=1&b=2'));
    }

    // ---- BYPASS: param lạ / search tự do ----

    public function test_param_la_bi_coi_la_disallowed(): void
    {
        $this->assertTrue($this->invoke('hasDisallowedParams', '/san-pham?q=abc'));
        $this->assertTrue($this->invoke('hasDisallowedParams', '/san-pham?page=2&search=xyz'));
    }

    public function test_param_allowlist_va_tracking_khong_disallowed(): void
    {
        $this->assertFalse($this->invoke('hasDisallowedParams', '/san-pham?page=2&sort=price&filter=mau-do'));
        $this->assertFalse($this->invoke('hasDisallowedParams', '/san-pham?utm_source=a&ref=b&gclid=c'));
        $this->assertFalse($this->invoke('hasDisallowedParams', '/san-pham'));
    }

    // ---- Trần số trang ----

    public function test_page_hop_le_va_qua_tran(): void
    {
        $this->assertTrue($this->invoke('pageWithinCap', '/san-pham'));
        $this->assertTrue($this->invoke('pageWithinCap', '/san-pham?page=2'));
        $this->assertFalse($this->invoke('pageWithinCap', '/san-pham?page=51'));
        $this->assertFalse($this->invoke('pageWithinCap', '/san-pham?page=abc'));
        $this->assertFalse($this->invoke('pageWithinCap', '/san-pham?page=0'));
    }
}
