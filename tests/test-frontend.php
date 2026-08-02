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
}
