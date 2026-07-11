<?php

namespace Tests\Unit;

use App\Http\Middleware\CachePage;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Cache key của CachePage phải bỏ param tracking (fix review affiliate
 * 2026-07-11): giữ aff_click unique per-visitor trong key = mỗi click short
 * link 1 bản cache 24h + khách affiliate không bao giờ HIT.
 */
class CachePageKeyTest extends TestCase
{
    protected function normalize(string $url): string
    {
        $middleware = new CachePage();
        $method = new \ReflectionMethod($middleware, 'normalizedUrl');
        $method->setAccessible(true);

        return $method->invoke($middleware, Request::create($url));
    }

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

    public function test_sort_param_de_key_on_dinh(): void
    {
        $this->assertSame(
            $this->normalize('/x?b=2&a=1'),
            $this->normalize('/x?a=1&b=2'),
        );
    }

    public function test_khach_affiliate_va_khach_thuong_chung_key(): void
    {
        $this->assertSame(
            $this->normalize('/gau-bong-p12'),
            $this->normalize('/gau-bong-p12?ref=kol9&utm_campaign=link_abc&gclid=zzz'),
        );
    }
}
