<?php
/**
 * Sikkerheds-hjælpere for CookieDK.
 *
 * @package CookieDK
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Security.
 */
class CookieDK_Security {


	/**
	 * Registrerer globale sikkerheds-hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'send_headers', array( $this, 'send_security_headers' ) );
	}

	/**
	 * Tilføjer anbefalede sikkerhedsheaders.
	 *
	 * @return void
	 */
	public function send_security_headers() {
		if ( headers_sent() ) {
			return;
		}

		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
	}

	/**
	 * Enkelt rate limiting pr. action og klient.
	 *
	 * @param  string $action       Action-navn.
	 * @param  int    $max_requests Maks antal requests i vinduet.
	 * @param  int    $window       Vindue i sekunder.
	 * @return bool True hvis request er tilladt, ellers false.
	 */
	public static function check_rate_limit( $action, $max_requests = 30, $window = 60 ) {
		$action       = sanitize_key( $action );
		$max_requests = max( 1, absint( $max_requests ) );
		$window       = max( 1, absint( $window ) );
		$ip           = self::get_client_ip();
		$ip           = $ip ? $ip : 'unknown';
		$key          = 'cookiedk_rl_' . md5( $action . '|' . $ip );
		$count        = (int) get_transient( $key );

		if ( $count >= $max_requests ) {
			return false;
		}

		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Returnerer klientens IP fra almindelige headers.
	 *
	 * @return string
	 */
	private static function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '';
	}
}
