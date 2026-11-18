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
			'reopen_position'       => 'bottom-left',
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
		// WP Consent API registrerer sit script på PHP_INT_MAX - 100.
		// CookieDK skal i kø bagefter, så dependency-handle findes.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), PHP_INT_MAX - 50 );
		add_action( 'wp_footer', array( $this, 'render_banner' ) );

		// Før Google-tags. Prioritet 0 når vi selv sætter default. Prioritet 2
		// når Site Kit allerede har sat default på prioritet 1 — et nyt
		// default bagefter kan nulstille et update for EØS.
		add_action( 'wp_head', array( $this, 'render_google_consent_mode' ), $this->google_consent_mode_priority() );
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

		$consent_dependencies = array();
		if ( function_exists( 'wp_has_consent' ) ) {
			$consent_dependencies[] = 'wp-consent-api';
		}

		wp_enqueue_script(
			'cookiedk-consent',
			COOKIEDK_PLUGIN_URL . 'public/assets/js/cookie-consent.js',
			$consent_dependencies,
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
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'cookiedk_log_consent' ),
				'consentExpiry'     => absint( $this->settings['consent_expiry_days'] ),
				'bannerPosition'    => sanitize_key( $this->settings['banner_position'] ),
				'reopenPosition'    => $this->get_reopen_position(),
				'primaryColor'      => sanitize_hex_color( $this->settings['primary_color'] ),
				'secondaryColor'    => sanitize_hex_color( $this->settings['secondary_color'] ),
				'cookiePolicyUrl'   => esc_url( $this->settings['cookie_policy_url'] ),
				'enableAnalytics'   => ! empty( $this->settings['enable_analytics'] ),
				'enableMarketing'   => ! empty( $this->settings['enable_marketing'] ),
				'enableFunctional'  => ! empty( $this->settings['enable_functional'] ),
				'googleConsentMode' => $this->is_google_consent_mode_enabled(),
				'i18n'              => array(
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

		// Tilføj inline CSS til cookie-tabellen når vi er på cookiepolitik-siden.
		if ( is_page( $this->settings['cookie_policy_page_id'] ) ) {
			$inline_css = '
			.cookiedk-cookie-table th {
				background-color: #f2f2f2;
				padding: 5px;
			}
			.cookiedk-cookie-table td {
				padding: 3px 10px;
				font-size: small;
				border: 1px solid #f2f2f2;
				vertical-align: top;
			}';
			wp_add_inline_style( 'cookiedk-banner-style', $inline_css );
		}
	}

	/**
	 * Om Google Consent Mode v2 skal udskrives.
	 *
	 * @return bool
	 */
	private function is_google_consent_mode_enabled() {
		return (bool) apply_filters( 'cookiedk_enable_google_consent_mode', true );
	}

	/**
	 * Om Site Kit allerede sætter consent default.
	 *
	 * @return bool
	 */
	private function site_kit_consent_mode_enabled() {
		return 'enabled' === apply_filters( 'googlesitekit_consent_mode_status', '' );
	}

	/**
	 * Prioritet på wp_head for consent-scriptet.
	 *
	 * @return int
	 */
	private function google_consent_mode_priority() {
		return $this->site_kit_consent_mode_enabled() ? 2 : 0;
	}

	/**
	 * Udskriver Consent Mode, før Google-tags læser dataLayer.
	 *
	 * Uden gemt samtykke forbliver de fire signaler denied. Med gyldigt
	 * samtykke i localStorage opdateres de i samme synkrone script.
	 *
	 * @return void
	 */
	public function render_google_consent_mode() {
		if ( ! $this->is_google_consent_mode_enabled() ) {
			return;
		}

		$script = $this->get_google_consent_mode_script( ! $this->site_kit_consent_mode_enabled() );

		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag(
				$script,
				array(
					'id' => 'cookiedk-google-consent-mode',
				)
			);
			return;
		}

		echo "<script id=\"cookiedk-google-consent-mode\">\n" . $script . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Statisk script uden brugerinput.
	}

	/**
	 * Bygger det tidlige Consent Mode-script.
	 *
	 * Analytics mapper til analytics_storage. Marketing mapper til
	 * ad_storage, ad_user_data og ad_personalization.
	 *
	 * @param bool $set_default Om scriptet selv skal sende consent default.
	 * @return string
	 */
	private function get_google_consent_mode_script( $set_default ) {
		$default = '';

		if ( $set_default ) {
			$default = <<<'JS'
	window.gtag( 'consent', 'default', {
		'ad_storage': 'denied',
		'ad_user_data': 'denied',
		'ad_personalization': 'denied',
		'analytics_storage': 'denied'
	} );
JS;
		}

		return <<<JS
( function () {
	'use strict';

	window.dataLayer = window.dataLayer || [];
	window.gtag = window.gtag || function () {
		window.dataLayer.push( arguments );
	};

{$default}
	window.gtag( 'set', 'ads_data_redaction', true );

	try {
		var raw = localStorage.getItem( 'cookiedk_consent' );
		if ( ! raw ) {
			return;
		}

		var stored = JSON.parse( raw );
		if ( ! stored || ! stored.consent || ! stored.expiry || new Date( stored.expiry ) < new Date() ) {
			return;
		}

		var marketing = stored.consent.marketing ? 'granted' : 'denied';
		window.gtag( 'consent', 'update', {
			'analytics_storage': stored.consent.analytics ? 'granted' : 'denied',
			'ad_storage': marketing,
			'ad_user_data': marketing,
			'ad_personalization': marketing
		} );
	} catch ( ignore ) {}
}() );
JS;
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
		$reopen_position = $this->get_reopen_position();
		$cookie_policy   = esc_url( $this->settings['cookie_policy_url'] );

		// Indlæs banner-template.
		include COOKIEDK_PLUGIN_DIR . 'public/templates/banner.php';

		// Indlæs indstillingspanel-template.
		include COOKIEDK_PLUGIN_DIR . 'public/templates/settings-panel.php';
	}

	/**
	 * Placering af genkalds-ikonet. Samme værdier som banner-position.
	 *
	 * @return string
	 */
	private function get_reopen_position() {
		$valid = array(
			'bottom',
			'top',
			'side',
			'top-left',
			'top-right',
			'center',
			'bottom-left',
			'bottom-right',
		);

		$position = isset( $this->settings['reopen_position'] ) ? sanitize_key( $this->settings['reopen_position'] ) : 'bottom-left';
		if ( ! in_array( $position, $valid, true ) ) {
			return 'bottom-left';
		}

		return $position;
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
