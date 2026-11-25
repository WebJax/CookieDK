<?php
/**
 * AI-beskrivelser af ukendte cookies via WordPress AI Client.
 *
 * @package CookieDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Cookie_Describer.
 */
class CookieDK_Cookie_Describer {

	/**
	 * Transient-nøgle for dagligt AI-kald-antal.
	 *
	 * @var string
	 */
	const RATE_TRANSIENT = 'cookiedk_ai_calls_today';

	/**
	 * Cron-hook for én cookie.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'cookiedk_describe_cookie';

	/**
	 * Registrerer hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( self::CRON_HOOK, array( $this, 'describe_cookie' ), 10, 1 );
		add_action( 'cookiedk_daily_cron', array( $this, 'process_pending_unknowns' ) );
	}

	/**
	 * Om WP AI Client kan generere tekst på dette site.
	 *
	 * @return bool
	 */
	public function is_ai_available() {
		$forced = apply_filters( 'cookiedk_ai_available', null );
		if ( is_bool( $forced ) ) {
			return $forced;
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		$settings = $this->get_plugin_settings();
		if ( empty( $settings['ai_descriptions'] ) ) {
			return false;
		}

		try {
			$builder = wp_ai_client_prompt( 'CookieDK availability check' );
			if ( ! method_exists( $builder, 'is_supported_for_text_generation' ) ) {
				return false;
			}
			return (bool) $builder->is_supported_for_text_generation();
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Planlægger AI-beskrivelse af en ukendt cookie.
	 *
	 * @param string $cookie_name Cookienavn.
	 * @return bool
	 */
	public function queue( $cookie_name ) {
		$cookie_name = sanitize_text_field( (string) $cookie_name );
		if ( '' === $cookie_name || ! $this->is_ai_available() ) {
			return false;
		}

		if ( wp_next_scheduled( self::CRON_HOOK, array( $cookie_name ) ) ) {
			return true;
		}

		return (bool) wp_schedule_single_event( time() + 15, self::CRON_HOOK, array( $cookie_name ) );
	}

	/**
	 * Genererer beskrivelse, gemmer overlay og pusher til GitHub.
	 *
	 * @param string $cookie_name Cookienavn.
	 * @return true|WP_Error
	 */
	public function describe_cookie( $cookie_name ) {
		$cookie_name = sanitize_text_field( (string) $cookie_name );
		if ( '' === $cookie_name ) {
			return new WP_Error( 'cookiedk_ai_name', __( 'Ugyldigt cookie-navn.', 'cookiedk' ) );
		}

		if ( ! class_exists( 'CookieDK_Catalog_Sync' ) ) {
			return new WP_Error( 'cookiedk_ai_sync', __( 'Katalog-sync er ikke indlæst.', 'cookiedk' ) );
		}

		$sync = new CookieDK_Catalog_Sync();
		if ( ! $sync->is_safe_cookie_key( $cookie_name ) ) {
			return new WP_Error( 'cookiedk_ai_name', __( 'Ugyldigt cookie-navn.', 'cookiedk' ) );
		}

		if ( ! $this->is_ai_available() ) {
			return new WP_Error( 'cookiedk_ai_unavailable', __( 'WordPress AI Client er ikke tilgængelig.', 'cookiedk' ) );
		}

		if ( class_exists( 'CookieDK_Cookie_Storage' ) ) {
			$storage  = new CookieDK_Cookie_Storage();
			$existing = $storage->get_cookie_by_name( $cookie_name );
			if ( $existing && ! empty( $existing->locked ) ) {
				return true;
			}
			if ( $existing && isset( $existing->origin ) && in_array( (string) $existing->origin, array( 'catalog', 'wildcard', 'manual' ), true ) ) {
				return true;
			}
		}

		if ( ! $this->consume_rate_limit() ) {
			return new WP_Error( 'cookiedk_ai_rate', __( 'Det daglige AI-loft er nået.', 'cookiedk' ) );
		}

		$parsed = $this->generate_description( $cookie_name );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$catalog_entry = $this->to_catalog_entry( $cookie_name, $parsed );
		$storage_data  = $this->to_storage_data( $cookie_name, $parsed );

		if ( class_exists( 'CookieDK_Cookie_Database' ) ) {
			CookieDK_Cookie_Database::save_overlay_entry( $cookie_name, $catalog_entry );
		}

		if ( class_exists( 'CookieDK_Cookie_Storage' ) ) {
			$storage = new CookieDK_Cookie_Storage();
			$storage->save_cookie( $storage_data );
		}

		$pushed = $sync->push_cookie_entry( $catalog_entry );
		if ( is_wp_error( $pushed ) ) {
			return $pushed;
		}

		return true;
	}

	/**
	 * Beskriver ventende ukendte cookies op til det daglige loft.
	 *
	 * @return void
	 */
	public function process_pending_unknowns() {
		if ( ! $this->is_ai_available() || ! class_exists( 'CookieDK_Cookie_Storage' ) ) {
			return;
		}

		$storage = new CookieDK_Cookie_Storage();
		foreach ( $storage->get_all_cookies() as $cookie ) {
			if ( empty( $cookie->name ) || ! empty( $cookie->locked ) ) {
				continue;
			}
			$origin = isset( $cookie->origin ) ? (string) $cookie->origin : 'unknown';
			if ( 'unknown' !== $origin ) {
				continue;
			}
			$this->queue( (string) $cookie->name );
		}
	}

	/**
	 * Kalder WP AI Client og parser JSON-svaret.
	 *
	 * @param string $cookie_name Cookienavn.
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_description( $cookie_name ) {
		$pre = apply_filters( 'cookiedk_pre_ai_description', null, $cookie_name );
		if ( is_wp_error( $pre ) ) {
			return $pre;
		}
		if ( is_array( $pre ) ) {
			return $this->sanitize_ai_payload( wp_json_encode( $pre ), $cookie_name );
		}

		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'category'       => array(
					'type' => 'string',
					'enum' => array( 'necessary', 'preferences', 'statistics', 'marketing', 'unclassified' ),
				),
				'description_da' => array( 'type' => 'string' ),
				'description_en' => array( 'type' => 'string' ),
				'duration'       => array( 'type' => 'string' ),
				'provider'       => array( 'type' => 'string' ),
				'provider_id'    => array( 'type' => 'string' ),
				'purpose'        => array( 'type' => 'string' ),
				'essential'      => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'category', 'description_da', 'duration', 'provider' ),
		);

		$prompt = sprintf(
			'Classify the HTTP cookie named "%1$s" used on the website %2$s. Return JSON only. description_da must be Danish, description_en English. If unsure, use category unclassified and say so. Do not invent legal or vendor claims you are not reasonably confident about.',
			$cookie_name,
			wp_parse_url( get_site_url(), PHP_URL_HOST )
		);

		try {
			$builder = wp_ai_client_prompt( $prompt )
				->using_system_instruction( 'You classify HTTP cookies for a Danish GDPR cookie banner. Return only JSON that matches the schema. Prefer conservative categories. Never mark a cookie essential unless it is strictly required for the site to function.' )
				->using_temperature( 0.2 )
				->as_json_response( $schema );

			if ( ! method_exists( $builder, 'generate_text' ) ) {
				return new WP_Error( 'cookiedk_ai_builder', __( 'WordPress AI Client kunne ikke bygge prompten.', 'cookiedk' ) );
			}

			$json = $builder->generate_text();
		} catch ( Exception $e ) {
			return new WP_Error( 'cookiedk_ai_exception', $e->getMessage() );
		}

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		return $this->sanitize_ai_payload( $json, $cookie_name );
	}

	/**
	 * Validerer og saniterer AI-JSON.
	 *
	 * @param string $json        Rå JSON.
	 * @param string $cookie_name Cookienavn.
	 * @return array<string, mixed>|WP_Error
	 */
	public function sanitize_ai_payload( $json, $cookie_name ) {
		$decoded = json_decode( (string) $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error( 'cookiedk_ai_json', __( 'AI-svaret var ikke gyldig JSON.', 'cookiedk' ) );
		}

		$categories = array( 'necessary', 'preferences', 'statistics', 'marketing', 'unclassified' );
		$category   = isset( $decoded['category'] ) ? sanitize_key( (string) $decoded['category'] ) : 'unclassified';
		if ( ! in_array( $category, $categories, true ) ) {
			$category = 'unclassified';
		}

		$description_da = isset( $decoded['description_da'] ) ? sanitize_textarea_field( (string) $decoded['description_da'] ) : '';
		if ( '' === $description_da ) {
			return new WP_Error( 'cookiedk_ai_description', __( 'AI-svaret manglede en dansk beskrivelse.', 'cookiedk' ) );
		}

		$provider_id = isset( $decoded['provider_id'] ) ? sanitize_key( (string) $decoded['provider_id'] ) : '';
		if ( '' !== $provider_id && class_exists( 'CookieDK_Cookie_Database' ) ) {
			$database = new CookieDK_Cookie_Database();
			if ( null === $database->get_provider( $provider_id ) ) {
				$provider_id = '';
			}
		}

		if ( '' === $provider_id && ! empty( $decoded['provider'] ) ) {
			$provider_id = sanitize_key( (string) $decoded['provider'] );
		}

		return array(
			'category'       => $category,
			'description_da' => $description_da,
			'description_en' => isset( $decoded['description_en'] ) ? sanitize_textarea_field( (string) $decoded['description_en'] ) : '',
			'duration'       => isset( $decoded['duration'] ) ? sanitize_text_field( (string) $decoded['duration'] ) : __( 'Ukendt', 'cookiedk' ),
			'provider'       => isset( $decoded['provider'] ) ? sanitize_text_field( (string) $decoded['provider'] ) : __( 'Ukendt udbyder', 'cookiedk' ),
			'provider_id'    => $provider_id,
			'purpose'        => isset( $decoded['purpose'] ) ? sanitize_text_field( (string) $decoded['purpose'] ) : '',
			'essential'      => ! empty( $decoded['essential'] ) && 'necessary' === $category,
			'name'           => $cookie_name,
		);
	}

	/**
	 * Mapper AI-data til cookies.json-skema.
	 *
	 * @param string               $cookie_name Cookienavn.
	 * @param array<string, mixed> $parsed      Saniteret AI-data.
	 * @return array<string, mixed>
	 */
	public function to_catalog_entry( $cookie_name, array $parsed ) {
		$provider_id = isset( $parsed['provider_id'] ) ? (string) $parsed['provider_id'] : '';

		return array(
			'name'           => (string) $cookie_name,
			'pattern'        => (string) $cookie_name,
			'match_type'     => 'exact',
			'category'       => isset( $parsed['category'] ) ? (string) $parsed['category'] : 'unclassified',
			'provider_id'    => $provider_id,
			'purpose'        => isset( $parsed['purpose'] ) ? (string) $parsed['purpose'] : '',
			'description_da' => isset( $parsed['description_da'] ) ? (string) $parsed['description_da'] : '',
			'description_en' => isset( $parsed['description_en'] ) ? (string) $parsed['description_en'] : '',
			'duration'       => isset( $parsed['duration'] ) ? (string) $parsed['duration'] : '',
			'first_party'    => false,
			'essential'      => ! empty( $parsed['essential'] ),
			'tags'           => array_values( array_filter( array( $provider_id, 'ai' ) ) ),
			'last_verified'  => gmdate( 'Y-m-d' ),
		);
	}

	/**
	 * Mapper AI-data til database-rækken.
	 *
	 * @param string               $cookie_name Cookienavn.
	 * @param array<string, mixed> $parsed      Saniteret AI-data.
	 * @return array<string, mixed>
	 */
	public function to_storage_data( $cookie_name, array $parsed ) {
		$category = 'functional';
		if ( class_exists( 'CookieDK_Cookie_Database' ) ) {
			$category = CookieDK_Cookie_Database::map_category( isset( $parsed['category'] ) ? (string) $parsed['category'] : 'unclassified' );
		}

		return array(
			'name'           => (string) $cookie_name,
			'category'       => $category,
			'description_da' => isset( $parsed['description_da'] ) ? (string) $parsed['description_da'] : '',
			'duration'       => isset( $parsed['duration'] ) ? (string) $parsed['duration'] : '',
			'provider'       => isset( $parsed['provider'] ) ? (string) $parsed['provider'] : '',
			'source'         => 'server',
			'necessary'      => ! empty( $parsed['essential'] ),
			'origin'         => 'ai',
			'locked'         => 0,
		);
	}

	/**
	 * Forbruger ét kald under det daglige AI-loft.
	 *
	 * @return bool
	 */
	private function consume_rate_limit() {
		$settings = $this->get_plugin_settings();
		$limit    = isset( $settings['ai_daily_limit'] ) ? absint( $settings['ai_daily_limit'] ) : 20;
		if ( $limit < 1 ) {
			$limit = 20;
		}

		$count = (int) get_transient( self::RATE_TRANSIENT );
		if ( $count >= $limit ) {
			return false;
		}

		set_transient( self::RATE_TRANSIENT, $count + 1, 86400 );
		return true;
	}

	/**
	 * Returnerer plugin-indstillinger med AI-defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function get_plugin_settings() {
		$stored = get_option( 'cookiedk_settings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args(
			$stored,
			array(
				'ai_descriptions' => true,
				'ai_daily_limit'  => 20,
			)
		);
	}
}
