<?php

use PHPUnit\Framework\TestCase;

final class CookieDKCookieDescriberTest extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cookiedk-ai-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
        $GLOBALS['__cookiedk_hooks']['filters'] = array();
        $GLOBALS['__cookiedk_options']          = array();
        $GLOBALS['__cookiedk_transients']       = array();
        $GLOBALS['wpdb']                        = new FakeWpdb();
        CookieDK_Cookie_Database::reset_cache();

        add_filter(
            'cookiedk_catalog_dir',
            function () {
                return $this->dir;
            }
        );
        add_filter(
            'cookiedk_ai_available',
            static function () {
                return true;
            }
        );
    }

    protected function tearDown(): void
    {
        foreach ( glob($this->dir . '/*') ?: array() as $file ) {
            unlink($file);
        }
        if ( is_dir($this->dir) ) {
            rmdir($this->dir);
        }
    }

    public function test_sanitize_ai_payload_maps_and_rejects_empty_description(): void
    {
        $describer = new CookieDK_Cookie_Describer();
        $ok        = $describer->sanitize_ai_payload(
            wp_json_encode(
                array(
                    'category'       => 'statistics',
                    'description_da' => 'Måler trafik.',
                    'description_en' => 'Measures traffic.',
                    'duration'       => '2 years',
                    'provider'       => 'Google',
                    'provider_id'    => 'google',
                    'essential'      => false,
                )
            ),
            '_ga_test'
        );

        $this->assertIsArray($ok);
        $this->assertSame('statistics', $ok['category']);
        $this->assertSame('google', $ok['provider_id']);

        $bad = $describer->sanitize_ai_payload('{"category":"marketing"}', 'x');
        $this->assertInstanceOf(WP_Error::class, $bad);
    }

    public function test_describe_cookie_saves_overlay_and_pushes_github(): void
    {
        $putCalled = false;
        add_filter(
            'cookiedk_pre_ai_description',
            static function () {
                return array(
                    'category'       => 'statistics',
                    'description_da' => 'AI-beskrivelse af ukendt cookie.',
                    'description_en' => 'AI description of unknown cookie.',
                    'duration'       => '1 year',
                    'provider'       => 'Example',
                    'purpose'        => 'analytics',
                    'essential'      => false,
                );
            }
        );
        add_filter(
            'cookiedk_github_token',
            static function () {
                return 'test-token';
            }
        );
        add_filter(
            'cookiedk_pre_remote_request',
            function ( $pre, $url, $args ) use ( &$putCalled ) {
                $method = isset($args['method']) ? $args['method'] : 'GET';
                if ( 'PUT' === $method ) {
                    $putCalled = true;
                    $body      = json_decode($args['body'], true);
                    $decoded   = json_decode(base64_decode($body['content']), true);
                    $this->assertArrayHasKey('unknown_ai_cookie', $decoded);
                    $this->assertSame('AI-beskrivelse af ukendt cookie.', $decoded['unknown_ai_cookie']['description_da']);
                    return array(
                        'response' => array( 'code' => 201 ),
                        'body'     => '{}',
                        'headers'  => array(),
                    );
                }

                return array(
                    'response' => array( 'code' => 200 ),
                    'body'     => wp_json_encode(
                        array(
                            'sha'     => 'sha1',
                            'content' => base64_encode('{}'),
                        )
                    ),
                    'headers'  => array(),
                );
            }
        );

        $describer = new CookieDK_Cookie_Describer();
        $result    = $describer->describe_cookie('unknown_ai_cookie');
        $this->assertTrue($result);
        $this->assertTrue($putCalled);

        $overlay = CookieDK_Cookie_Database::get_overlay();
        $this->assertArrayHasKey('unknown_ai_cookie', $overlay);

        $storage = new CookieDK_Cookie_Storage();
        $row     = $storage->get_cookie_by_name('unknown_ai_cookie');
        $this->assertNotNull($row);
        $this->assertSame('AI-beskrivelse af ukendt cookie.', $row->description_da);
        $this->assertSame('analytics', $row->category);
        $this->assertSame('catalog', $row->origin);
    }

    public function test_is_ai_available_false_without_client(): void
    {
        $GLOBALS['__cookiedk_hooks']['filters'] = array();
        $describer = new CookieDK_Cookie_Describer();
        $this->assertFalse($describer->is_ai_available());
    }
}
