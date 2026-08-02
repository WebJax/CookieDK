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

    public function test_classify_cookie_with_unknown_cookie(): void
    {
        $detector = new CookieDK_Cookie_Detector();
        $meta = $detector->classify_cookie('custom_cookie');

        $this->assertSame('functional', $meta['category']);
        $this->assertArrayHasKey('description_da', $meta);
    }
}
