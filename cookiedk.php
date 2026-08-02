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
 * Inkluder nødvendige klasser.
 */
function cookiedk_load_dependencies() {
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
		'banner_position'     => 'bottom',
		'color_theme'         => 'light',
		'cookie_policy_url'   => '',
		'consent_expiry_days' => 365,
		'enable_analytics'    => true,
		'enable_marketing'    => true,
		'enable_functional'   => true,
		'anonymize_ip'        => true,
		'log_retention_days'  => 365,
		'primary_color'       => '#2271b1',
		'secondary_color'     => '#135e96',
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
add_action( 'plugins_loaded', 'cookiedk_init' );

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
