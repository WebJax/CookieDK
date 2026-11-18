<?php
/**
 * Integration med WP Consent API.
 *
 * Gør CookieDK til samtykke-banner for plugins, der læser wp_has_consent().
 * Kører kun, når pluginet WP Consent API er aktivt.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Consent_API.
 *
 * Registrerer samtykketype, udløb og kendte cookies hos WP Consent API.
 */
class CookieDK_Consent_API {

	/**
	 * CookieDK-kategori til WP Consent API-kategori.
	 *
	 * Nødvendige cookies er functional. CookieDK har foldet preferences ind i functional.
	 * Analytics dækker både statistics og statistics-anonymous i JavaScript.
	 *
	 * @var array<string, string>
	 */
	const CATEGORY_MAP = array(
		'necessary'  => 'functional',
		'functional' => 'preferences',
		'analytics'  => 'statistics',
		'marketing'  => 'marketing',
	);

	/**
	 * Registrerer hooks, når WP Consent API er tilgængelig.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! self::is_available() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_api_notice' ) );
			return;
		}

		$basename = defined( 'COOKIEDK_PLUGIN_BASENAME' ) ? COOKIEDK_PLUGIN_BASENAME : plugin_basename( COOKIEDK_PLUGIN_FILE );

		add_filter( 'wp_consent_api_registered_' . $basename, '__return_true' );
		add_filter( 'wp_get_consent_type', array( $this, 'get_consent_type' ) );
		add_filter( 'wp_consent_api_cookie_expiration', array( $this, 'get_cookie_expiration' ) );

		// Efter cookiedk_init (prioritet 20), så cookie-tabellen er indlæst.
		add_action( 'init', array( $this, 'register_cookies' ), 21 );
	}

	/**
	 * Om WP Consent API er indlæst.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'wp_has_consent' ) && function_exists( 'wp_add_cookie_info' );
	}

	/**
	 * Danmark er opt-in. CookieDK overskriver derfor samtykketypen.
	 *
	 * @param string $consent_type Type sat af et andet plugin.
	 * @return string
	 */
	public function get_consent_type( $consent_type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Filteret sender typen, men CookieDK er altid opt-in.
		return 'optin';
	}

	/**
	 * Bruger CookieDKs samtykkefrist, så API-cookies ikke udløber efter 30 dage.
	 *
	 * @param int $days API'ets standardudløb i dage.
	 * @return int
	 */
	public function get_cookie_expiration( $days ) {
		$settings = get_option( 'cookiedk_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$expiry = isset( $settings['consent_expiry_days'] ) ? absint( $settings['consent_expiry_days'] ) : 0;
		if ( $expiry < 1 ) {
			$expiry = absint( $days );
		}

		if ( $expiry < 1 ) {
			return 365;
		}

		return $expiry;
	}

	/**
	 * Mapper en CookieDK-kategori til en WP Consent API-kategori.
	 *
	 * @param string $category CookieDK-kategori.
	 * @return string
	 */
	public static function map_category( $category ) {
		$category = sanitize_key( (string) $category );

		if ( isset( self::CATEGORY_MAP[ $category ] ) ) {
			return self::CATEGORY_MAP[ $category ];
		}

		return 'functional';
	}

	/**
	 * Registrerer CookieDKs eget samtykke og de cookies, sitet har gemt.
	 *
	 * @return void
	 */
	public function register_cookies() {
		if ( ! function_exists( 'wp_add_cookie_info' ) ) {
			return;
		}

		$expiry_days = $this->get_cookie_expiration( 365 );

		wp_add_cookie_info(
			'cookiedk_consent',
			'CookieDK',
			'functional',
			sprintf(
				/* translators: %d: number of days the consent is stored. */
				__( '%d dage', 'cookiedk' ),
				$expiry_days
			),
			__( 'Gemmer brugerens cookie-samtykke i browseren.', 'cookiedk' ),
			'',
			false,
			false,
			'LOCALSTORAGE'
		);

		if ( ! class_exists( 'CookieDK_Cookie_Storage' ) ) {
			return;
		}

		$storage = new CookieDK_Cookie_Storage();

		foreach ( $storage->get_all_cookies() as $cookie ) {
			if ( ! is_object( $cookie ) || empty( $cookie->name ) ) {
				continue;
			}

			$name = sanitize_text_field( (string) $cookie->name );
			if ( '' === $name ) {
				continue;
			}

			$service  = ! empty( $cookie->provider ) ? sanitize_text_field( (string) $cookie->provider ) : __( 'Ukendt', 'cookiedk' );
			$purpose  = ! empty( $cookie->description_da ) ? sanitize_text_field( (string) $cookie->description_da ) : $name;
			$expires  = ! empty( $cookie->duration ) ? sanitize_text_field( (string) $cookie->duration ) : __( 'Ukendt', 'cookiedk' );
			$category = ! empty( $cookie->necessary ) ? 'functional' : self::map_category( isset( $cookie->category ) ? (string) $cookie->category : '' );

			wp_add_cookie_info( $name, $service, $category, $expires, $purpose );
		}
	}

	/**
	 * Fortæller administratoren, at samtykke-API'et mangler.
	 *
	 * @return void
	 */
	public function render_missing_api_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the current admin page.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'cookiedk' !== $page ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		echo wp_kses(
			sprintf(
				/* translators: %s: URL to the WP Consent API plugin on WordPress.org. */
				__( 'CookieDK kan dele samtykke med andre plugins via WP Consent API. <a href="%s">Installér og aktivér WP Consent API</a>, så plugins som WooCommerce og statistik-plugins kan læse besøgendes valg.', 'cookiedk' ),
				esc_url( 'https://wordpress.org/plugins/wp-consent-api/' )
			),
			array(
				'a' => array(
					'href' => array(),
				),
			)
		);
		echo '</p></div>';
	}
}
