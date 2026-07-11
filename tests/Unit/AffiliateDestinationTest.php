<?php

namespace Tests\Unit;

use App\Services\Affiliate\AffiliatePortalService;
use Tests\TestCase;

/**
 * Validate destination lúc TẠO link (Phase 4/6 — AFFILIATE-PLAN.md mục 2.4):
 * chỉ nhận cùng domain / path tương đối, chặn open-redirect, loop /l/,
 * khu vực private; detect product_id từ URL dạng buildUrl `/{slug}-p{id}`.
 * Test qua reflection vì 2 method là protected (không cần DB).
 */
class AffiliateDestinationTest extends TestCase
{
    protected function normalize(string $url): ?string
    {
        config(['app.url' => 'https://infun.vn']);
        $service = $this->app->make(AffiliatePortalService::class);
        $method = new \ReflectionMethod($service, 'normalizeDestination');
        $method->setAccessible(true);

        return $method->invoke($service, $url);
    }

    protected function detect(string $path): ?int
    {
        $service = $this->app->make(AffiliatePortalService::class);
        $method = new \ReflectionMethod($service, 'detectProductId');
        $method->setAccessible(true);

        return $method->invoke($service, $path);
    }

    public function test_chap_nhan_path_tuong_doi_va_url_cung_domain(): void
    {
        $this->assertSame('/san-pham?page=2', $this->normalize('/san-pham?page=2'));
        $this->assertSame('/gau-bong-p12', $this->normalize('https://infun.vn/gau-bong-p12'));
        $this->assertSame('/gau-bong-p12', $this->normalize('HTTPS://INFUN.VN/gau-bong-p12'));
        $this->assertSame('/', $this->normalize('https://infun.vn'));
    }

    public function test_chan_domain_la_va_scheme_nguy_hiem(): void
    {
        $this->assertNull($this->normalize('https://evil.com/phish'));
        $this->assertNull($this->normalize('//evil.com/phish'));
        $this->assertNull($this->normalize('javascript:alert(1)'));
        $this->assertNull($this->normalize('ftp://infun.vn/file'));
        $this->assertNull($this->normalize(''));
        $this->assertNull($this->normalize('/x?q=' . str_repeat('a', 600))); // quá 512 ký tự
    }

    public function test_chan_loop_shortener_va_khu_vuc_private(): void
    {
        $this->assertNull($this->normalize('/l/abc123'));
        $this->assertNull($this->normalize('https://infun.vn/l/abc123'));
        $this->assertNull($this->normalize('/account/affiliate'));
        $this->assertNull($this->normalize('/account'));
        $this->assertNull($this->normalize('/checkout/cart'));
        $this->assertNull($this->normalize('/api/v1/products'));

        // Không dính oan slug chỉ TƯƠNG TỰ khu vực bị chặn.
        $this->assertSame('/account-sale-p5', $this->normalize('/account-sale-p5'));
        $this->assertSame('/lang-que', $this->normalize('/lang-que'));
    }

    public function test_detect_product_id_theo_convention_buildurl(): void
    {
        $this->assertSame(123, $this->detect('/gau-bong-cute-p123'));
        $this->assertSame(123, $this->detect('/gau-bong-cute-p123?utm_source=x'));
        $this->assertNull($this->detect('/bai-viet-hay-n55'));    // blog, không phải product
        $this->assertNull($this->detect('/san-pham'));
        $this->assertNull($this->detect('/'));
    }
}
