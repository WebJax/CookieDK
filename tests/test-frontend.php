<?php

use PHPUnit\Framework\TestCase;

final class CookieDKFrontendTest extends TestCase
{
    public function test_init_registers_hooks(): void
    {
        $frontend = new CookieDK_Frontend();
        $frontend->init();

        $this->assertArrayHasKey('wp_enqueue_scripts', $GLOBALS['__cookiedk_hooks']['actions']);
        $this->assertArrayHasKey('wp_footer', $GLOBALS['__cookiedk_hooks']['actions']);
        $this->assertArrayHasKey('wp_head', $GLOBALS['__cookiedk_hooks']['actions']);
        $this->assertContains(0, $GLOBALS['__cookiedk_hooks']['action_priorities']['wp_head']);
    }

    public function test_custom_css_contains_css_variables(): void
    {
        $GLOBALS['__cookiedk_options']['cookiedk_settings'] = array(
            'primary_color' => '#123456',
            'secondary_color' => '#abcdef',
        );

        $frontend = new CookieDK_Frontend();
        $method = new ReflectionMethod(CookieDK_Frontend::class, 'get_custom_css');
        $method->setAccessible(true);
        $css = $method->invoke($frontend);

        $this->assertStringContainsString('--cookiedk-primary: #123456;', $css);
        $this->assertStringContainsString('--cookiedk-secondary: #abcdef;', $css);
    }

    public function test_google_consent_mode_defaults_are_denied(): void
    {
        $frontend = new CookieDK_Frontend();
        $script = $this->consent_mode_script($frontend, true);

        $this->assertStringContainsString("gtag( 'consent', 'default'", $script);
        $this->assertStringContainsString("'ad_storage': 'denied'", $script);
        $this->assertStringContainsString("'ad_user_data': 'denied'", $script);
        $this->assertStringContainsString("'ad_personalization': 'denied'", $script);
        $this->assertStringContainsString("'analytics_storage': 'denied'", $script);
        $this->assertStringContainsString("localStorage.getItem( 'cookiedk_consent' )", $script);
        $this->assertStringContainsString("gtag( 'consent', 'update'", $script);
        $this->assertStringContainsString('stored.consent.analytics', $script);
        $this->assertStringContainsString('stored.consent.marketing', $script);
        $this->assertStringContainsString("'ad_user_data': marketing", $script);
        $this->assertStringContainsString("'ad_personalization': marketing", $script);
    }

    public function test_google_consent_mode_skips_default_when_site_kit_owns_it(): void
    {
        add_filter(
            'googlesitekit_consent_mode_status',
            static function ( $status ) {
                unset($status);
                return 'enabled';
            }
        );

        $frontend = new CookieDK_Frontend();
        $frontend->init();

        $this->assertContains(2, $GLOBALS['__cookiedk_hooks']['action_priorities']['wp_head']);

        ob_start();
        $frontend->render_google_consent_mode();
        $output = ob_get_clean();

        $this->assertStringNotContainsString("gtag( 'consent', 'default'", $output);
        $this->assertStringContainsString("gtag( 'consent', 'update'", $output);
        $this->assertStringContainsString('id="cookiedk-google-consent-mode"', $output);

        unset($GLOBALS['__cookiedk_hooks']['filters']['googlesitekit_consent_mode_status']);
    }

    public function test_google_consent_mode_can_be_disabled(): void
    {
        add_filter(
            'cookiedk_enable_google_consent_mode',
            static function ( $enabled ) {
                unset($enabled);
                return false;
            }
        );

        $frontend = new CookieDK_Frontend();

        ob_start();
        $frontend->render_google_consent_mode();
        $output = ob_get_clean();

        $this->assertSame('', $output);

        unset($GLOBALS['__cookiedk_hooks']['filters']['cookiedk_enable_google_consent_mode']);
    }

    public function test_banner_css_mobile_modal_uses_95_percent_width(): void
    {
        $css = file_get_contents(COOKIEDK_PLUGIN_DIR . 'public/assets/css/banner.css');

        $this->assertNotFalse($css);
        $this->assertStringContainsString('#cookiedk-banner.cookiedk-position-bottom', $css);

        $mobile_query_pos = strpos($css, '@media screen and (max-width: 782px)');
        $this->assertNotFalse($mobile_query_pos);

        $mobile_css = substr($css, $mobile_query_pos);
        $this->assertStringContainsString('#cookiedk-banner.cookiedk-position-bottom', $mobile_css);
        $this->assertStringContainsString('width: 95%;', $mobile_css);
        $this->assertStringContainsString('#cookiedk-settings-panel', $mobile_css);
    }

    /**
     * @param bool $set_default Om default-kaldet skal med.
     */
    private function consent_mode_script( CookieDK_Frontend $frontend, bool $set_default ): string
    {
        $method = new ReflectionMethod(CookieDK_Frontend::class, 'get_google_consent_mode_script');
        $method->setAccessible(true);

        return $method->invoke($frontend, $set_default);
    }
}
