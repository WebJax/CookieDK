<?php
/**
 * Frontend-klasse for CookieDK.
 *
 * Håndterer registrering af scripts og styles, banner-rendering
 * og integration med wp_footer hook.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Frontend.
 *
 * Renderer cookie-banneret og håndterer frontend-logik.
 */
class CookieDK_Frontend {


	/**
	 * Plugin-indstillinger.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * CookieDK_Cookie_Storage-instans.
	 *
	 * @var CookieDK_Cookie_Storage
	 */
	private $storage;

	/**
	 * Konstruktør – henter indstillinger.
	 */
	public function __construct() {
		$defaults = array(
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
			'primary_color'         => '#2271b1',
			'secondary_color'       => '#135e96',
		);

		$saved          = get_option( 'cookiedk_settings', array() );
		$this->settings = wp_parse_args( $saved, $defaults );
		$this->storage  = new CookieDK_Cookie_Storage();
	}

	/**
	 * Registrerer WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_banner' ) );
	}

	/**
	 * Registrerer og indlæser frontend-assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Style/script-handles må ikke genbruge DOM-id'et "cookiedk-banner".
		// Optimizers (preload/defer CSS) sætter ofte id="{handle}" på <link>,
		// hvilket ellers stjæler getElementById( 'cookiedk-banner' ) fra banner-div'en.
		wp_enqueue_style(
			'cookiedk-banner-style',
			COOKIEDK_PLUGIN_URL . 'public/assets/css/banner.css',
			array(),
			COOKIEDK_VERSION
		);

		wp_enqueue_script(
			'cookiedk-consent',
			COOKIEDK_PLUGIN_URL . 'public/assets/js/cookie-consent.js',
			array(),
			COOKIEDK_VERSION,
			true
		);

		wp_enqueue_script(
			'cookiedk-banner-script',
			COOKIEDK_PLUGIN_URL . 'public/assets/js/banner.js',
			array( 'cookiedk-consent' ),
			COOKIEDK_VERSION,
			true
		);

		// Videregiv data til JavaScript.
		wp_localize_script(
			'cookiedk-banner-script',
			'cookieDKData',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'cookiedk_log_consent' ),
				'consentExpiry'    => absint( $this->settings['consent_expiry_days'] ),
				'bannerPosition'   => sanitize_key( $this->settings['banner_position'] ),
				'primaryColor'     => sanitize_hex_color( $this->settings['primary_color'] ),
				'secondaryColor'   => sanitize_hex_color( $this->settings['secondary_color'] ),
				'cookiePolicyUrl'  => esc_url( $this->settings['cookie_policy_url'] ),
				'enableAnalytics'  => ! empty( $this->settings['enable_analytics'] ),
				'enableMarketing'  => ! empty( $this->settings['enable_marketing'] ),
				'enableFunctional' => ! empty( $this->settings['enable_functional'] ),
				'i18n'             => array(
					'accept_all'       => __( 'Accepter alle', 'cookiedk' ),
					'accept_necessary' => __( 'Kun nødvendige', 'cookiedk' ),
					'settings'         => __( 'Indstillinger', 'cookiedk' ),
					'save_settings'    => __( 'Gem indstillinger', 'cookiedk' ),
					'close'            => __( 'Luk', 'cookiedk' ),
				),
			)
		);

		// Tilføj inline CSS med brugerdefinerede farver.
		$custom_css = $this->get_custom_css();
		if ( $custom_css ) {
			wp_add_inline_style( 'cookiedk-banner-style', $custom_css );
		}
	}

	/**
	 * Genererer inline CSS baseret på brugerens farveindstillinger.
	 *
	 * @return string CSS-streng.
	 */
	private function get_custom_css() {
		$primary   = sanitize_hex_color( $this->settings['primary_color'] );
		$secondary = sanitize_hex_color( $this->settings['secondary_color'] );

		if ( ! $primary && ! $secondary ) {
			return '';
		}

		$css = ':root {';
		if ( $primary ) {
			$css .= '--cookiedk-primary: ' . $primary . ';';
		}
		if ( $secondary ) {
			$css .= '--cookiedk-secondary: ' . $secondary . ';';
		}
		$css .= '}';

		return $css;
	}

	/**
	 * Renderer banner og indstillingspanel i footer.
	 *
	 * @return void
	 */
	public function render_banner() {
		// Hent cookies grupperet efter kategori.
		$cookies_by_category = $this->get_cookies_by_category();

		$banner_position = sanitize_key( $this->settings['banner_position'] );
		$cookie_policy   = esc_url( $this->settings['cookie_policy_url'] );

		// Indlæs banner-template.
		include COOKIEDK_PLUGIN_DIR . 'public/templates/banner.php';

		// Indlæs indstillingspanel-template.
		include COOKIEDK_PLUGIN_DIR . 'public/templates/settings-panel.php';
	}

	/**
	 * Henter cookies grupperet efter kategori.
	 *
	 * @return array Cookies opdelt i kategorier.
	 */
	private function get_cookies_by_category() {
		$categories  = CookieDK_Cookie_Detector::get_categories();
		$all_cookies = $this->storage->get_all_cookies();
		$grouped     = array();

		foreach ( $categories as $slug => $label ) {
			$grouped[ $slug ] = array(
				'label'   => $label,
				'cookies' => array(),
				'enabled' => 'necessary' === $slug || ! empty( $this->settings[ 'enable_' . $slug ] ),
			);
		}

		foreach ( $all_cookies as $cookie ) {
			$cat                          = isset( $grouped[ $cookie->category ] ) ? $cookie->category : 'functional';
			$grouped[ $cat ]['cookies'][] = $cookie;
		}

		return $grouped;
	}
}
