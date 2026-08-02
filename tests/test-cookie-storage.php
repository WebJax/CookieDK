<?php

use PHPUnit\Framework\TestCase;

final class CookieDKCookieStorageTest extends TestCase
{
    public function test_save_and_get_cookie(): void
    {
        $storage = new CookieDK_Cookie_Storage();

        $id = $storage->save_cookie(
            array(
                'name' => 'test_cookie',
                'category' => 'analytics',
                'description_da' => 'Test',
                'duration' => '30 dage',
                'provider' => 'CookieDK',
                'source' => 'manual',
            )
        );

        $this->assertIsInt($id);
        $cookie = $storage->get_cookie_by_name('test_cookie');
        $this->assertSame('test_cookie', $cookie->name);
    }

    public function test_update_cookie(): void
    {
        $storage = new CookieDK_Cookie_Storage();
        $id = $storage->save_cookie(array( 'name' => 'update_cookie', 'category' => 'functional' ));

        $result = $storage->update_cookie($id, array( 'name' => 'update_cookie', 'category' => 'marketing' ));

        $this->assertTrue($result);
        $cookie = $storage->get_cookie_by_id($id);
        $this->assertSame('marketing', $cookie->category);
    }

    public function test_delete_cookie(): void
    {
        $storage = new CookieDK_Cookie_Storage();
        $id = $storage->save_cookie(array( 'name' => 'delete_cookie', 'category' => 'functional' ));

        $this->assertTrue($storage->delete_cookie($id));
        $this->assertNull($storage->get_cookie_by_id($id));
    }
}
