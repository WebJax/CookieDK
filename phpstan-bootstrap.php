<?php
define('COOKIEDK_PLUGIN_URL', 'https://example.com');
define('COOKIEDK_PLUGIN_DIR', __DIR__ . '/');
define('COOKIEDK_PLUGIN_FILE', __DIR__ . '/cookiedk.php');
define('COOKIEDK_PLUGIN_BASENAME', 'CookieDK/cookiedk.php');

if ( ! function_exists( 'wp_has_consent' ) ) {
	/**
	 * Stub. WP Consent API er ikke en del af WordPress-kernen.
	 *
	 * @param string      $category     Consent category.
	 * @param string|null $requested_by Plugin basename.
	 * @return bool
	 */
	function wp_has_consent( $category, $requested_by = null ) {
		unset( $category, $requested_by );
		return false;
	}
}

if ( ! function_exists( 'wp_add_cookie_info' ) ) {
	/**
	 * Stub. WP Consent API er ikke en del af WordPress-kernen.
	 *
	 * @param string $name                    Cookie name.
	 * @param string $plugin_or_service       Service name.
	 * @param string $category                Consent category.
	 * @param string $expires                 Expiration label.
	 * @param string $function                Purpose.
	 * @param string $collected_personal_data Personal data description.
	 * @param bool   $member_cookie           Members only.
	 * @param bool   $administrator_cookie    Administrators only.
	 * @param string $type                    HTTP, LOCALSTORAGE or API.
	 * @param string $domain                  Cookie domain.
	 * @return void
	 */
	function wp_add_cookie_info( $name, $plugin_or_service, $category, $expires, $function, $collected_personal_data = '', $member_cookie = false, $administrator_cookie = false, $type = 'HTTP', $domain = '' ) {
		unset( $name, $plugin_or_service, $category, $expires, $function, $collected_personal_data, $member_cookie, $administrator_cookie, $type, $domain );
	}
}