<?php

use PHPUnit\Framework\TestCase;

if ( ! function_exists('wp_has_consent') ) {
    /**
     * Test-stub for WP Consent API.
     *
     * @return bool
     */
    function wp_has_consent()
    {
        return false;
    }
}

if ( ! function_exists('wp_add_cookie_info') ) {
    /**
     * Test-stub, der gemmer registrerede cookies.
     *
     * @return void
     */
    function wp_add_cookie_info()
    {
        $GLOBALS['__cookiedk_cookie_info'][] = func_get_args();
    }
}

final class CookieDKConsentApiTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__cookiedk_hooks'] = array( 'actions' => array(), 'filters' => array() );
        $GLOBALS['__cookiedk_options'] = array();
        $GLOBALS['__cookiedk_cookie_info'] = array();
        $GLOBALS['__cookiedk_scripts'] = array();
        $GLOBALS['__cookiedk_caps'] = array();
        $GLOBALS['wpdb'] = new FakeWpdb();
    }

    public function test_map_category_matches_consent_api(): void
    {
        $this->assertSame('functional', CookieDK_Consent_API::map_category('necessary'));
        $this->assertSame('preferences', CookieDK_Consent_API::map_category('functional'));
        $this->assertSame('statistics', CookieDK_Consent_API::map_category('analytics'));
        $this->assertSame('marketing', CookieDK_Consent_API::map_category('marketing'));
        $this->assertSame('functional', CookieDK_Consent_API::map_category('unknown'));
    }

    public function test_consent_type_is_optin(): void
    {
        $api = new CookieDK_Consent_API();

        $this->assertSame('optin', $api->get_consent_type('optout'));
    }

    public function test_cookie_expiration_follows_settings(): void
    {
        $GLOBALS['__cookiedk_options']['cookiedk_settings'] = array(
            'consent_expiry_days' => 90,
        );
        $api = new CookieDK_Consent_API();

        $this->assertSame(90, $api->get_cookie_expiration(30));
    }

    public function test_cookie_expiration_falls_back_to_365(): void
    {
        $api = new CookieDK_Consent_API();

        $this->assertSame(365, $api->get_cookie_expiration(0));
    }

    public function test_init_registers_consent_api_hooks(): void
    {
        $api = new CookieDK_Consent_API();
        $api->init();

        $this->assertArrayHasKey('wp_consent_api_registered_CookieDK/cookiedk.php', $GLOBALS['__cookiedk_hooks']['filters']);
        $this->assertArrayHasKey('wp_get_consent_type', $GLOBALS['__cookiedk_hooks']['filters']);
        $this->assertArrayHasKey('wp_consent_api_cookie_expiration', $GLOBALS['__cookiedk_hooks']['filters']);
        $this->assertArrayHasKey('init', $GLOBALS['__cookiedk_hooks']['actions']);
    }

    public function test_register_cookies_maps_categories_and_consent_store(): void
    {
        $storage = new CookieDK_Cookie_Storage();
        $storage->save_cookie(
            array(
                'name' => '_ga',
                'category' => 'analytics',
                'description_da' => 'Statistik',
                'duration' => '2 år',
                'provider' => 'Google',
                'necessary' => 0,
            )
        );
        $storage->save_cookie(
            array(
                'name' => 'woocommerce_cart_hash',
                'category' => 'marketing',
                'description_da' => 'Kurv',
                'duration' => 'Session',
                'provider' => 'WooCommerce',
                'necessary' => 1,
            )
        );

        $api = new CookieDK_Consent_API();
        $api->register_cookies();

        $by_name = array();
        foreach ( $GLOBALS['__cookiedk_cookie_info'] as $cookie ) {
            $by_name[ $cookie[0] ] = $cookie;
        }

        $this->assertSame('functional', $by_name['cookiedk_consent'][2]);
        $this->assertSame('LOCALSTORAGE', $by_name['cookiedk_consent'][8]);
        $this->assertSame('statistics', $by_name['_ga'][2]);
        $this->assertSame('Google', $by_name['_ga'][1]);
        $this->assertSame('functional', $by_name['woocommerce_cart_hash'][2]);
    }

    public function test_frontend_depends_on_consent_api_script(): void
    {
        $frontend = new CookieDK_Frontend();
        $frontend->enqueue_assets();

        $consent = null;
        foreach ( $GLOBALS['__cookiedk_scripts'] as $script ) {
            if ('cookiedk-consent' === $script['handle'] ) {
                $consent = $script;
            }
        }

        $this->assertNotNull($consent);
        $this->assertContains('wp-consent-api', $consent['deps']);
    }
}
