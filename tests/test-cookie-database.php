<?php

use PHPUnit\Framework\TestCase;

final class CookieDKCookieDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        CookieDK_Cookie_Database::reset_cache();
    }

    public function test_map_category_converts_gdpr_slugs(): void
    {
        $this->assertSame('necessary', CookieDK_Cookie_Database::map_category('necessary'));
        $this->assertSame('functional', CookieDK_Cookie_Database::map_category('preferences'));
        $this->assertSame('analytics', CookieDK_Cookie_Database::map_category('statistics'));
        $this->assertSame('marketing', CookieDK_Cookie_Database::map_category('marketing'));
        $this->assertSame('functional', CookieDK_Cookie_Database::map_category('unclassified'));
        $this->assertSame('functional', CookieDK_Cookie_Database::map_category('unknown-slug'));
    }

    public function test_known_cookies_map_statistics_to_analytics(): void
    {
        $database = new CookieDK_Cookie_Database();
        $cookies  = $database->get_known_cookies();

        $this->assertArrayHasKey('_ga', $cookies);
        $this->assertSame('analytics', $cookies['_ga']['category']);
        $this->assertSame('Google', $cookies['_ga']['provider']);
        $this->assertFalse($cookies['_ga']['necessary']);
        $this->assertSame('exact', $cookies['_ga']['match_type']);
    }

    public function test_known_cookies_map_preferences_to_functional(): void
    {
        $database = new CookieDK_Cookie_Database();
        $cookies  = $database->get_known_cookies();

        $this->assertArrayHasKey('wp-settings-*', $cookies);
        $this->assertSame('functional', $cookies['wp-settings-*']['category']);
        $this->assertSame('wildcard', $cookies['wp-settings-*']['match_type']);
    }

    public function test_known_cookies_keep_necessary_and_marketing(): void
    {
        $database = new CookieDK_Cookie_Database();
        $cookies  = $database->get_known_cookies();

        $this->assertSame('necessary', $cookies['wordpress_test_cookie']['category']);
        $this->assertTrue($cookies['wordpress_test_cookie']['necessary']);
        $this->assertSame('marketing', $cookies['_fbp']['category']);
        $this->assertSame('Meta', $cookies['_fbp']['provider']);
    }

    public function test_get_provider_returns_vendor_metadata(): void
    {
        $database = new CookieDK_Cookie_Database();
        $google   = $database->get_provider('google');

        $this->assertIsArray($google);
        $this->assertSame('Google', $google['name']);
        $this->assertContains('Google Analytics', $google['services']);
    }
}
