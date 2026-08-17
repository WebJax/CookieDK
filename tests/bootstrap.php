<?php

define('ABSPATH', __DIR__ . '/');
define('COOKIEDK_VERSION', '1.0.0');
define('COOKIEDK_PLUGIN_DIR', dirname(__DIR__) . '/');
define('COOKIEDK_PLUGIN_URL', 'https://example.org/wp-content/plugins/cookiedk/');

$GLOBALS['__cookiedk_hooks'] = array( 'actions' => array(), 'filters' => array() );
$GLOBALS['__cookiedk_options'] = array();
$GLOBALS['__cookiedk_transients'] = array();

function __( $text, $domain = null )
{
    return $text; 
}
function esc_html__( $text, $domain = null )
{
    return $text; 
}
function esc_attr_e( $text, $domain = null )
{
    echo $text; 
}
function esc_html_e( $text, $domain = null )
{
    echo $text; 
}
function esc_html( $text )
{
    return (string) $text; 
}
function esc_js( $text )
{
    return (string) $text; 
}
function esc_url( $text )
{
    return (string) $text; 
}
function esc_url_raw( $text )
{
    return (string) $text; 
}
function sanitize_text_field( $text )
{
    return trim(strip_tags((string) $text)); 
}
function sanitize_textarea_field( $text )
{
    return trim(strip_tags((string) $text)); 
}
function sanitize_email( $text )
{
    return strtolower(trim((string) $text)); 
}
function sanitize_key( $text )
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $text)); 
}
function sanitize_hex_color( $color )
{
    $color = (string) $color;
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : null;
}
function wp_unslash( $value )
{
    return $value; 
}
function wp_json_encode( $value )
{
    return json_encode($value); 
}
function absint( $value )
{
    return abs((int) $value); 
}
function current_time( $type )
{
    return '2026-01-01 12:00:00'; 
}
function admin_url( $path = '' )
{
    return 'https://example.org/wp-admin/' . ltrim($path, '/'); 
}
function add_action( $hook, $callback )
{
    $GLOBALS['__cookiedk_hooks']['actions'][ $hook ][] = $callback; 
}
function add_filter( $hook, $callback )
{
    $GLOBALS['__cookiedk_hooks']['filters'][ $hook ][] = $callback; 
}
function apply_filters( $hook, $value )
{
    return $value; 
}
function is_admin()
{
    return false; 
}
function wp_create_nonce( $action )
{
    return 'nonce-' . $action; 
}
function check_ajax_referer( $action, $field = 'nonce' )
{
    return true; 
}
function wp_send_json_success( $data = array() )
{
    throw new RuntimeException('json_success:' . json_encode($data)); 
}
function wp_send_json_error( $data = array() )
{
    throw new RuntimeException('json_error:' . json_encode($data)); 
}
function status_header( $status )
{
    return $status; 
}
function is_user_logged_in()
{
    return false; 
}
function get_current_user_id()
{
    return 0; 
}
function get_user_by( $field, $value )
{
    return null; 
}
function update_user_meta( $user_id, $key, $value )
{
    return true; 
}
function wp_verify_nonce( $nonce, $action )
{
    return true; 
}
function add_settings_error( $setting, $code, $message, $type )
{
    return true; 
}
function wp_parse_args( $args, $defaults )
{
    return array_merge($defaults, $args); 
}
function cookiedk_get_theme_primary_color()
{
    return '#2271b1';
}
function cookiedk_get_theme_secondary_color()
{
    return '#135e96';
}
function wp_enqueue_style()
{
    return true; 
}
function wp_enqueue_script()
{
    return true; 
}
function wp_localize_script()
{
    return true; 
}
function wp_add_inline_style()
{
    return true; 
}
function wp_enqueue_media()
{
    return true; 
}
function wp_schedule_event()
{
    return true; 
}
function wp_next_scheduled()
{
    return false; 
}
function get_site_url()
{
    return 'https://example.org'; 
}
function get_bloginfo( $show )
{
    return 'Eksempel Site'; 
}
function wp_kses_post( $content )
{
    return $content; 
}
function wp_add_privacy_policy_content( $plugin_name, $content )
{
    return true; 
}
function set_transient( $key, $value, $expiration )
{
    $GLOBALS['__cookiedk_transients'][ $key ] = $value; return true; 
}
function get_transient( $key )
{
    return isset($GLOBALS['__cookiedk_transients'][ $key ]) ? $GLOBALS['__cookiedk_transients'][ $key ] : false; 
}
function get_option( $name, $default = false )
{
    return isset($GLOBALS['__cookiedk_options'][ $name ]) ? $GLOBALS['__cookiedk_options'][ $name ] : $default; 
}
function update_option( $name, $value )
{
    $GLOBALS['__cookiedk_options'][ $name ] = $value; return true; 
}
class FakeWpdb
{
    public $prefix = 'wp_';
    public $insert_id = 0;
    private $cookies = array();
    private $consents = array();

    public function get_charset_collate()
    {
        return 'CHARSET utf8mb4'; 
    }
    public function prepare( $query )
    {
        $args = func_get_args();
        array_shift($args);
        foreach ( $args as $arg ) {
            $replacement = is_numeric($arg) ? (string) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sd]/', $replacement, $query, 1);
        }
        return $query;
    }
    public function insert( $table, $data )
    {
        if (false !== strpos($table, 'consent') ) {
            $this->insert_id = count($this->consents) + 1;
            $data['id'] = $this->insert_id;
            $this->consents[] = (object) $data;
            return 1;
        }
        $this->insert_id = count($this->cookies) + 1;
        $data['id'] = $this->insert_id;
        $this->cookies[ $this->insert_id ] = (object) $data;
        return 1;
    }
    public function update( $table, $data, $where )
    {
        $id = (int) $where['id'];
        if (isset($this->cookies[ $id ]) ) {
            foreach ( $data as $k => $v ) {
                $this->cookies[ $id ]->$k = $v;
            }
            return 1;
        }
        return false;
    }
    public function delete( $table, $where )
    {
        if (isset($where['id']) ) {
            $id = (int) $where['id'];
            if (isset($this->cookies[ $id ]) ) {
                unset($this->cookies[ $id ]);
                return 1;
            }
            return 0;
        }
        if (isset($where['user_fingerprint']) ) {
            $before = count($this->consents);
            $this->consents = array_values(
                array_filter(
                    $this->consents, function ( $row ) use ( $where ) {
                        return $row->user_fingerprint !== $where['user_fingerprint'];
                    } 
                ) 
            );
            return $before - count($this->consents);
        }
        return 0;
    }
    public function query( $query )
    {
        return 1; 
    }
    public function get_row( $query )
    {
        if (preg_match("/WHERE name = '([^']+)'/", $query, $m) ) {
            foreach ( $this->cookies as $row ) {
                if ($row->name === stripslashes($m[1]) ) {
                    return $row;
                }
            }
        }
        if (preg_match('/WHERE id = (\d+)/', $query, $m) ) {
            $id = (int) $m[1];
            return isset($this->cookies[ $id ]) ? $this->cookies[ $id ] : null;
        }
        return null;
    }
    public function get_results( $query )
    {
        if (false !== strpos($query, 'consent_log') ) {
            return $this->consents;
        }
        return array_values($this->cookies);
    }
}

$GLOBALS['wpdb'] = new FakeWpdb();

require_once dirname(__DIR__) . '/includes/class-cookiedk-cookie-storage.php';
require_once dirname(__DIR__) . '/includes/class-cookiedk-cookie-database.php';
require_once dirname(__DIR__) . '/includes/class-cookiedk-cookie-detector.php';
require_once dirname(__DIR__) . '/includes/class-cookiedk-consent-export.php';
require_once dirname(__DIR__) . '/includes/class-cookiedk-gdpr-compliance.php';
require_once dirname(__DIR__) . '/includes/class-cookiedk-privacy-policy.php';
require_once dirname(__DIR__) . '/includes/class-cookiedk-translations.php';
require_once dirname(__DIR__) . '/includes/class-cookiedk-security.php';
require_once dirname(__DIR__) . '/public/class-cookiedk-frontend.php';
require_once dirname(__DIR__) . '/admin/class-cookiedk-admin-page.php';
require_once dirname(__DIR__) . '/admin/class-cookiedk-admin-menu.php';
