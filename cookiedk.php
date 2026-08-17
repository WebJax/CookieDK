<?php
/**
 * Plugin Name:       CookieDK
 * Plugin URI:        https://github.com/WebJax/CookieDK
 * Description:       GDPR-compliant cookiebanner med auto-detektion (dansk)
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            WebJax
 * Author URI:        https://webjax.dk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cookiedk
 * Domain Path:       /languages
 *
 * @package CookieDK
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin-konstanter.
define( 'COOKIEDK_VERSION', '1.0.0' );
define( 'COOKIEDK_PLUGIN_FILE', __FILE__ );
define( 'COOKIEDK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COOKIEDK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'COOKIEDK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'COOKIEDK_DB_VERSION', '1.0.0' );

/**
 * Returnerer temats primærfarve fra globale styles.
 *
 * @return string
 */
function cookiedk_get_theme_primary_color() {
	$css = wp_get_global_stylesheet();
	if ( ! empty( $css ) ) {
		preg_match( '/--wp--preset--color--primary:\s*([^;]+);/i', $css, $matches );
		if ( ! empty( $matches[1] ) ) {
			$color = trim( $matches[1] );
			if ( $color ) {
				$sanitized = sanitize_hex_color( $color );
				if ( $sanitized ) {
					return $sanitized;
				}
			}
		}
	}

	$settings = wp_get_global_settings();
	if ( ! empty( $settings['color']['palette'] ) ) {
		foreach ( $settings['color']['palette'] as $palette_color ) {
			if ( ! empty( $palette_color['slug'] ) && 'primary' === $palette_color['slug'] && ! empty( $palette_color['color'] ) ) {
				$sanitized = sanitize_hex_color( $palette_color['color'] );
				if ( $sanitized ) {
					return $sanitized;
				}
			}
		}
	}

	return '#2271b1';
}

/**
 * Returnerer en lidt mørkere variant af en hex-farve.
 *
 * @param string $hex   Hex-farve.
 * @param float  $amount Mørkningsgrad mellem 0 og 1.
 * @return string
 */
function cookiedk_make_darker_color( $hex, $amount = 0.14 ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( 6 !== strlen( $hex ) ) {
		return '#135e96';
	}

	$r = max( 0, min( 255, (int) hexdec( substr( $hex, 0, 2 ) ) * ( 1 - $amount ) ) );
	$g = max( 0, min( 255, (int) hexdec( substr( $hex, 2, 2 ) ) * ( 1 - $amount ) ) );
	$b = max( 0, min( 255, (int) hexdec( substr( $hex, 4, 2 ) ) * ( 1 - $amount ) ) );

	return sprintf( '#%02x%02x%02x', (int) $r, (int) $g, (int) $b );
}

/**
 * Returnerer temats sekundærfarve som en mørkere variant af primary.
 *
 * @return string
 */
function cookiedk_get_theme_secondary_color() {
	$primary = cookiedk_get_theme_primary_color();
	return cookiedk_make_darker_color( $primary );
}

/**
 * Inkluder nødvendige klasser.
 */
function cookiedk_load_dependencies() {
	include_once COOKIEDK_PLUGIN_DIR . 'includes/class-cookiedk-cookie-database.php';
	include_once COOKIEDK_PLUGIN_DIR . 'includes/class-cookiedk-cookie-detector.php';
	include_once COOKIEDK_PLUGIN_DIR . 'includes/class-cookiedk-cookie-storage.php';
	include_once COOKIEDK_PLUGIN_DIR . 'includes/class-cookiedk-security.php';
	include_once COOKIEDK_PLUGIN_DIR . 'includes/class-cookiedk-consent-export.php';
	include_once COOKIEDK_PLUGIN_DIR . 'includes/class-cookiedk-gdpr-compliance.php';
	include_once COOKIEDK_PLUGIN_DIR . 'includes/class-cookiedk-privacy-policy.php';
	include_once COOKIEDK_PLUGIN_DIR . 'includes/class-cookiedk-translations.php';
	include_once COOKIEDK_PLUGIN_DIR . 'public/class-cookiedk-frontend.php';
	include_once COOKIEDK_PLUGIN_DIR . 'admin/class-cookiedk-admin-menu.php';
	include_once COOKIEDK_PLUGIN_DIR . 'admin/class-cookiedk-admin-page.php';
}

/**
 * Aktiveringshook – opretter database-tabeller.
 */
function cookiedk_activate() {
	cookiedk_load_dependencies();

	$storage = new CookieDK_Cookie_Storage();
	$storage->create_tables();

	// Gem aktiveringstidspunkt.
	add_option( 'cookiedk_activated_at', current_time( 'mysql' ) );
	add_option( 'cookiedk_db_version', COOKIEDK_DB_VERSION );

	// Standard-indstillinger.
	$default_settings = array(
		'banner_position'       => 'bottom',
		'color_theme'           => 'light',
		'cookie_policy_url'     => '',
		'cookie_policy_page_id' => 0,
		'policy_owner_name'     => '',
		'policy_owner_address'  => '',
		'policy_owner_postal'   => '',
		'policy_owner_city'     => '',
		'policy_owner_cvr'      => '',
		'consent_expiry_days'   => 365,
		'enable_analytics'      => true,
		'enable_marketing'      => true,
		'enable_functional'     => true,
		'anonymize_ip'          => true,
		'log_retention_days'    => 365,
		'primary_color'         => cookiedk_get_theme_primary_color(),
		'secondary_color'       => cookiedk_get_theme_secondary_color(),
	);
	add_option( 'cookiedk_settings', $default_settings );

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cookiedk_activate' );

/**
 * Deaktiveringshook.
 */
function cookiedk_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cookiedk_deactivate' );

/**
 * Initialisér pluginen.
 */
function cookiedk_init() {
	cookiedk_load_dependencies();

	// Indlæs oversættelsesfiler.
	load_plugin_textdomain(
		'cookiedk',
		false,
		dirname( COOKIEDK_PLUGIN_BASENAME ) . '/languages'
	);

	// Kør database-migrationer ved behov.
	cookiedk_maybe_upgrade();

	// Initialisér klasser.
	$detector = new CookieDK_Cookie_Detector();
	$storage  = new CookieDK_Cookie_Storage();
	$security = new CookieDK_Security();
	$gdpr     = new CookieDK_GDPR_Compliance();
	$privacy  = new CookieDK_Privacy_Policy();

	$detector->init();
	$security->init();
	$gdpr->init();
	$privacy->init();

	// Fase 4: Frontend-banner (kun på frontend).
	if ( ! is_admin() ) {
		$frontend = new CookieDK_Frontend();
		$frontend->init();
	}

	// Fase 5: Admin-interface.
	if ( is_admin() ) {
		$admin_menu = new CookieDK_Admin_Menu();
		$admin_menu->init();

		$admin_page = new CookieDK_Admin_Page();
		$admin_page->register_ajax_handlers();
	}
}
add_action( 'init', 'cookiedk_init', 20 );

/**
 * Kør database-migrationer hvis DB-version er forældet.
 */
function cookiedk_maybe_upgrade() {
	$current_db_version = get_option( 'cookiedk_db_version', '0' );

	if ( version_compare( $current_db_version, COOKIEDK_DB_VERSION, '<' ) ) {
		$storage = new CookieDK_Cookie_Storage();
		$storage->create_tables();
		update_option( 'cookiedk_db_version', COOKIEDK_DB_VERSION );
	}
}
