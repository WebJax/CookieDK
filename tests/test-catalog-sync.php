<?php

use PHPUnit\Framework\TestCase;

final class CookieDKCatalogSyncTest extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cookiedk-sync-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
        $GLOBALS['__cookiedk_hooks']['filters'] = array();
        $GLOBALS['__cookiedk_options']          = array();
        CookieDK_Cookie_Database::reset_cache();

        add_filter(
            'cookiedk_catalog_dir',
            function () {
                return $this->dir;
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

    public function test_rejects_non_https_and_unknown_hosts(): void
    {
        $sync = new CookieDK_Catalog_Sync();
        $this->assertFalse($sync->is_allowed_url('http://raw.githubusercontent.com/WebJax/CookieDK/main/database/cookies.json'));
        $this->assertFalse($sync->is_allowed_url('https://evil.example/cookies.json'));
        $this->assertTrue($sync->is_allowed_url('https://raw.githubusercontent.com/WebJax/CookieDK/main/database/cookies.json'));
        $this->assertTrue($sync->is_allowed_url('https://api.github.com/repos/WebJax/CookieDK/contents/database/cookies.json'));
    }

    public function test_validate_catalog_json_rejects_lists_and_bad_keys(): void
    {
        $sync = new CookieDK_Catalog_Sync();
        $this->assertInstanceOf(WP_Error::class, $sync->validate_catalog_json('[]', 'cookies'));
        $this->assertInstanceOf(WP_Error::class, $sync->validate_catalog_json('{"../x":{"name":"x"}}', 'cookies'));
        $this->assertTrue($sync->validate_catalog_json('{"_ga":{"name":"_ga","category":"statistics"}}', 'cookies'));
    }

    public function test_sync_writes_snapshot_from_remote_json(): void
    {
        $cookies = wp_json_encode(
            array(
                'remote_cookie' => array(
                    'name'           => 'remote_cookie',
                    'category'       => 'statistics',
                    'description_da' => 'Remote',
                    'provider_id'    => 'google',
                    'match_type'     => 'exact',
                    'essential'      => false,
                ),
            )
        );
        $providers = wp_json_encode(
            array(
                'google' => array(
                    'name'     => 'Google',
                    'services' => array( 'Google Analytics' ),
                ),
            )
        );

        add_filter(
            'cookiedk_pre_remote_request',
            function ( $pre, $url ) use ( $cookies, $providers ) {
                if ( false !== strpos($url, 'cookies.json') ) {
                    return array(
                        'response' => array( 'code' => 200 ),
                        'headers'  => array( 'etag' => '"cookies-1"' ),
                        'body'     => $cookies,
                    );
                }
                return array(
                    'response' => array( 'code' => 200 ),
                    'headers'  => array( 'etag' => '"providers-1"' ),
                    'body'     => $providers,
                );
            }
        );

        $sync   = new CookieDK_Catalog_Sync();
        $result = $sync->sync();
        $this->assertTrue($result);
        $this->assertFileExists($this->dir . '/cookies.json');

        CookieDK_Cookie_Database::reset_cache();
        $database = new CookieDK_Cookie_Database();
        $known    = $database->get_known_cookies();
        $this->assertArrayHasKey('remote_cookie', $known);
        $this->assertSame('analytics', $known['remote_cookie']['category']);
    }

    public function test_push_cookie_entry_adds_missing_key_via_github_api(): void
    {
        $catalog = array(
            '_ga' => array(
                'name'     => '_ga',
                'category' => 'statistics',
            ),
        );
        $sha     = 'abc123';
        $putCalled = false;

        add_filter(
            'cookiedk_github_token',
            static function () {
                return 'test-token';
            }
        );
        add_filter(
            'cookiedk_pre_remote_request',
            function ( $pre, $url, $args ) use ( &$catalog, $sha, &$putCalled ) {
                $method = isset($args['method']) ? $args['method'] : 'GET';
                if ( 'PUT' === $method ) {
                    $putCalled = true;
                    $body      = json_decode($args['body'], true);
                    $decoded   = json_decode(base64_decode($body['content']), true);
                    $this->assertArrayHasKey('new_ai_cookie', $decoded);
                    return array(
                        'response' => array( 'code' => 200 ),
                        'body'     => '{}',
                        'headers'  => array(),
                    );
                }

                return array(
                    'response' => array( 'code' => 200 ),
                    'body'     => wp_json_encode(
                        array(
                            'sha'     => $sha,
                            'content' => base64_encode(wp_json_encode($catalog)),
                        )
                    ),
                    'headers'  => array(),
                );
            }
        );

        $sync   = new CookieDK_Catalog_Sync();
        $result = $sync->push_cookie_entry(
            array(
                'name'           => 'new_ai_cookie',
                'pattern'        => 'new_ai_cookie',
                'match_type'     => 'exact',
                'category'       => 'statistics',
                'description_da' => 'AI',
            )
        );

        $this->assertTrue($result);
        $this->assertTrue($putCalled);
        $this->assertFileExists($this->dir . '/cookies.json');
    }

    public function test_push_without_token_returns_error(): void
    {
        $sync   = new CookieDK_Catalog_Sync();
        $result = $sync->push_cookie_entry(array( 'name' => 'x' ));
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('cookiedk_push_no_token', $result->get_error_code());
    }
}
