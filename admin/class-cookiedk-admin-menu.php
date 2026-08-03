<?php
/**
 * Admin-menu registrering for CookieDK.
 *
 * Registrerer admin-menu under Indstillinger med undermenuer.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Admin_Menu.
 *
 * Håndterer registrering af WordPress admin-menuer.
 */
class CookieDK_Admin_Menu {


	/**
	 * Admin-side slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'cookiedk';

	/**
	 * Registrerer WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Registrerer admin-menuerne.
	 *
	 * @return void
	 */
	public function register_menus() {
		// Toplevel-side under Indstillinger.
		add_options_page(
			__( 'CookieDK', 'cookiedk' ),
			__( 'CookieDK', 'cookiedk' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renderer admin-siden – delegerer til CookieDK_Admin_Page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Du har ikke rettigheder til at se denne side.', 'cookiedk' ) );
		}

		$admin_page = new CookieDK_Admin_Page();
		$admin_page->render();
	}

	/**
	 * Indlæser admin-assets kun på CookieDK-sider.
	 *
	 * @param  string $hook_suffix Nuværende admin-sides hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		// Kun på vores indstillingsside.
		if ( 'settings_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'cookiedk-admin',
			COOKIEDK_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			COOKIEDK_VERSION
		);

		wp_enqueue_script(
			'cookiedk-admin',
			COOKIEDK_PLUGIN_URL . 'admin/assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			COOKIEDK_VERSION,
			true
		);

		wp_localize_script(
			'cookiedk-admin',
			'cookieDKAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cookiedk_admin_nonce' ),
				'themeColors' => array(
					'primary'   => cookiedk_get_theme_primary_color(),
					'secondary' => cookiedk_get_theme_secondary_color(),
				),
				'i18n'    => array(
					'confirm_delete'       => __( 'Er du sikker på, at du vil slette denne cookie?', 'cookiedk' ),
					'saved'                => __( 'Indstillinger gemt.', 'cookiedk' ),
					'error'                => __( 'Der opstod en fejl. Prøv igen.', 'cookiedk' ),
					'loading'              => __( 'Indlæser…', 'cookiedk' ),
					'edit_policy'          => __( 'Rediger cookiepolitik-siden', 'cookiedk' ),
					'theme_colors_applied' => __( 'Tema-farver indlæst.', 'cookiedk' ),
				),
			)
		);

		// Tilføj wp.media til export/import-funktioner.
		wp_enqueue_media();
	}
}
