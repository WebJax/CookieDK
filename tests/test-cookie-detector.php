<?php

use PHPUnit\Framework\TestCase;

final class CookieDKCookieDetectorTest extends TestCase
{
    public function test_classify_cookie_with_known_cookie(): void
    {
        $detector = new CookieDK_Cookie_Detector();
        $meta = $detector->classify_cookie('_ga');

        $this->assertSame('analytics', $meta['category']);
        $this->assertFalse($meta['necessary']);
    }

    public function test_classify_cookie_with_wildcard_cookie(): void
    {
        $detector = new CookieDK_Cookie_Detector();
        $meta = $detector->classify_cookie('wordpress_logged_in_123');

        $this->assertSame('necessary', $meta['category']);
    }

    public function test_classify_cookie_maps_preferences_to_functional(): void
    {
        $detector = new CookieDK_Cookie_Detector();
        $meta = $detector->classify_cookie('wp-settings-1');

        $this->assertSame('functional', $meta['category']);
        $this->assertSame('WordPress', $meta['provider']);
    }

    public function test_classify_cookie_maps_ga4_wildcard_to_analytics(): void
    {
        $detector = new CookieDK_Cookie_Detector();
        $meta = $detector->classify_cookie('_ga_XXXXXXXXXX');

        $this->assertSame('analytics', $meta['category']);
        $this->assertFalse($meta['necessary']);
    }

    public function test_classify_cookie_with_unknown_cookie(): void
    {
        $detector = new CookieDK_Cookie_Detector();
        $meta = $detector->classify_cookie('custom_cookie');

        $this->assertSame('functional', $meta['category']);
        $this->assertArrayHasKey('description_da', $meta);
        $this->assertSame('unknown', $meta['origin']);
    }

    public function test_classify_known_cookie_sets_catalog_origin(): void
    {
        $detector = new CookieDK_Cookie_Detector();
        $meta     = $detector->classify_cookie('_ga');
        $this->assertSame('catalog', $meta['origin']);
    }
}
