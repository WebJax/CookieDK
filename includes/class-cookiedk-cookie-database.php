<?php
/**
 * Cookie-database-loader for CookieDK.
 *
 * Indlæser cookies.json og providers.json og mapper GDPR-kategorier
 * til pluginnets interne kategorier.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Cookie_Database.
 *
 * Læser den statiske cookie- og udbyderdatabase.
 */
class CookieDK_Cookie_Database {

	/**
	 * Mapping fra database-kategorier til plugin-kategorier.
	 *
	 * @var array<string, string>
	 */
	const CATEGORY_MAP = array(
		'necessary'    => 'necessary',
		'preferences'  => 'functional',
		'statistics'   => 'analytics',
		'marketing'    => 'marketing',
		'unclassified' => 'functional',
	);

	/**
	 * Rå cookie-poster fra JSON (efter kategori-mapping).
	 *
	 * @var array<string, array>|null
	 */
	private static $known_cookies = null;

	/**
	 * Rå udbyder-poster fra JSON.
	 *
	 * @var array<string, array>|null
	 */
	private static $providers = null;

	/**
	 * Nulstiller intern cache (bruges i tests).
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$known_cookies = null;
		self::$providers     = null;
	}

	/**
	 * Mapper en database-kategori til pluginnets kategori.
	 *
	 * @param string $category Kategori fra cookies.json.
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
	 * Returnerer udbydere fra providers.json.
	 *
	 * @return array<string, array>
	 */
	public function get_providers() {
		if ( null !== self::$providers ) {
			return self::$providers;
		}

		$decoded         = $this->load_json_file( 'providers.json' );
		self::$providers = is_array( $decoded ) ? $decoded : array();

		return self::$providers;
	}

	/**
	 * Returnerer en enkelt udbyder.
	 *
	 * @param string $provider_id Udbyder-nøgle.
	 * @return array|null
	 */
	public function get_provider( $provider_id ) {
		$providers   = $this->get_providers();
		$provider_id = sanitize_key( (string) $provider_id );

		return isset( $providers[ $provider_id ] ) ? $providers[ $provider_id ] : null;
	}

	/**
	 * Returnerer kendte cookies i detektorens format.
	 *
	 * Kategorier er mappet: preferences → functional, statistics → analytics,
	 * unclassified → functional.
	 *
	 * @return array<string, array>
	 */
	public function get_known_cookies() {
		if ( null !== self::$known_cookies ) {
			return self::$known_cookies;
		}

		$decoded   = $this->load_json_file( 'cookies.json' );
		$providers = $this->get_providers();
		$mapped    = array();

		if ( ! is_array( $decoded ) ) {
			self::$known_cookies = array();
			return self::$known_cookies;
		}

		foreach ( $decoded as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$key         = (string) $key;
			$provider_id = isset( $entry['provider_id'] ) ? sanitize_key( (string) $entry['provider_id'] ) : '';
			$provider    = isset( $providers[ $provider_id ]['name'] ) ? (string) $providers[ $provider_id ]['name'] : $provider_id;
			$essential   = ! empty( $entry['essential'] );
			$match_type  = isset( $entry['match_type'] ) && 'wildcard' === $entry['match_type'] ? 'wildcard' : 'exact';

			$mapped[ $key ] = array(
				'name'           => isset( $entry['name'] ) ? (string) $entry['name'] : $key,
				'pattern'        => isset( $entry['pattern'] ) ? (string) $entry['pattern'] : $key,
				'match_type'     => $match_type,
				'category'       => self::map_category( isset( $entry['category'] ) ? (string) $entry['category'] : '' ),
				'provider'       => $provider,
				'provider_id'    => $provider_id,
				'purpose'        => isset( $entry['purpose'] ) ? (string) $entry['purpose'] : '',
				'description_da' => isset( $entry['description_da'] ) ? (string) $entry['description_da'] : '',
				'description_en' => isset( $entry['description_en'] ) ? (string) $entry['description_en'] : '',
				'duration'       => isset( $entry['duration'] ) ? (string) $entry['duration'] : '',
				'first_party'    => ! empty( $entry['first_party'] ),
				'essential'      => $essential,
				'necessary'      => $essential,
				'tags'           => isset( $entry['tags'] ) && is_array( $entry['tags'] ) ? $entry['tags'] : array(),
			);
		}

		self::$known_cookies = $mapped;

		return self::$known_cookies;
	}

	/**
	 * Indlæser og dekoder en JSON-fil fra database/-mappen.
	 *
	 * @param string $filename Filnavn relativt til database/.
	 * @return array|null
	 */
	private function load_json_file( $filename ) {
		$allowed = array( 'cookies.json', 'providers.json' );
		if ( ! in_array( $filename, $allowed, true ) ) {
			return null;
		}

		$path = COOKIEDK_PLUGIN_DIR . 'database/' . $filename;

		if ( ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin JSON file.
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return null;
		}

		return $decoded;
	}
}
