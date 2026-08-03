<?php

use PHPUnit\Framework\TestCase;

final class CookieDKAdminTest extends TestCase
{
    public function test_admin_menu_init_registers_hooks(): void
    {
        $menu = new CookieDK_Admin_Menu();
        $menu->init();

        $this->assertArrayHasKey('admin_menu', $GLOBALS['__cookiedk_hooks']['actions']);
        $this->assertArrayHasKey('admin_enqueue_scripts', $GLOBALS['__cookiedk_hooks']['actions']);
    }

    public function test_sanitize_settings_validates_values(): void
    {
        $admin = new CookieDK_Admin_Page();
        $method = new ReflectionMethod(CookieDK_Admin_Page::class, 'sanitize_settings');
        $method->setAccessible(true);

        $settings = $method->invoke(
            $admin,
            array(
                'banner_position' => 'invalid-position',
                'color_theme' => 'invalid-theme',
                'consent_expiry_days' => '10',
                'primary_color' => '#ffffff',
                'secondary_color' => '#000000',
            )
        );

        $this->assertSame('bottom', $settings['banner_position']);
        $this->assertSame('light', $settings['color_theme']);
        $this->assertSame(10, $settings['consent_expiry_days']);

        $corner = $method->invoke(
            $admin,
            array(
                'banner_position' => 'bottom-left',
                'color_theme' => 'dark',
                'policy_owner_name' => 'Test ApS',
                'policy_owner_address' => 'Testvej 1',
                'policy_owner_postal' => '2100',
                'policy_owner_city' => 'København',
                'policy_owner_cvr' => '12345678',
            )
        );

        $this->assertSame('bottom-left', $corner['banner_position']);
        $this->assertSame('Test ApS', $corner['policy_owner_name']);
        $this->assertSame('12345678', $corner['policy_owner_cvr']);
    }
}
