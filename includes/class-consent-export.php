<?php
/**
 * GDPR Data-export (DSAR) for CookieDK.
 *
 * Denne klasse håndterer eksport af samtykke-data i JSON-format
 * til brug ved Data Subject Access Requests (DSAR).
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Consent_Export.
 *
 * Eksporterer og anonymiserer samtykke-data i overensstemmelse med GDPR.
 */
class CookieDK_Consent_Export {

	/**
	 * CookieDK_Cookie_Storage-instans.
	 *
	 * @var CookieDK_Cookie_Storage
	 */
	private $storage;

	/**
	 * Konstruktør.
	 */
	public function __construct() {
		$this->storage = new CookieDK_Cookie_Storage();
	}

	/**
	 * Eksporterer samtykke-data for en bruger som JSON.
	 *
	 * @param string $fingerprint Bruger-fingerprint.
	 * @param int    $user_id     WordPress bruger-ID (0 for gæster).
	 * @return array Eksport-data.
	 */
	public function export_as_json( $fingerprint, $user_id = 0 ) {
		$fingerprint  = sanitize_text_field( $fingerprint );
		$consent_log  = $this->storage->get_consent_log( $fingerprint, 500 );
		$cookies      = $this->storage->get_all_cookies();

		$export = array(
			'generated_at'  => gmdate( 'c' ),
			'plugin'        => 'CookieDK',
			'version'       => COOKIEDK_VERSION,
			'data_subject'  => $this->format_data_subject( $user_id ),
			'consents'      => $this->format_consents( $consent_log ),
			'cookies'       => $this->format_cookies( $cookies ),
		);

		return $export;
	}

	/**
	 * Formaterer data-subject-oplysninger (anonymiseret).
	 *
	 * @param int $user_id WordPress bruger-ID.
	 * @return array
	 */
	private function format_data_subject( $user_id ) {
		$user_id = absint( $user_id );

		if ( $user_id > 0 ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				return array(
					'type'  => 'registered_user',
					'email' => sanitize_email( $user->user_email ),
				);
			}
		}

		return array(
			'type'  => 'anonymous_visitor',
			'email' => null,
		);
	}

	/**
	 * Formaterer samtykke-log-poster.
	 *
	 * @param array $consent_log Liste af samtykke-poster fra databasen.
	 * @return array
	 */
	private function format_consents( array $consent_log ) {
		$formatted = array();

		foreach ( $consent_log as $log ) {
			$consent_data = json_decode( $log->consent_data, true );
			$categories   = array();

			if ( is_array( $consent_data ) ) {
				foreach ( $consent_data as $category => $accepted ) {
					$categories[ sanitize_text_field( $category ) ] = (bool) $accepted;
				}
			}

			$formatted[] = array(
				'id'           => absint( $log->id ),
				'timestamp'    => sanitize_text_field( $log->consent_timestamp ),
				'categories'   => $categories,
				'ip_address'   => $log->ip_anonymized ? __( '[anonymiseret]', 'cookiedk' ) : ( $log->ip_address ? sanitize_text_field( $log->ip_address ) : null ),
				'ip_anonymized' => (bool) $log->ip_anonymized,
				'user_agent'   => $log->user_agent ? sanitize_text_field( $log->user_agent ) : null,
			);
		}

		return $formatted;
	}

	/**
	 * Formaterer liste over registrerede cookies.
	 *
	 * @param array $cookies Liste af cookie-objekter fra databasen.
	 * @return array
	 */
	private function format_cookies( array $cookies ) {
		$formatted = array();

		foreach ( $cookies as $cookie ) {
			$formatted[] = array(
				'name'           => sanitize_text_field( $cookie->name ),
				'category'       => sanitize_text_field( $cookie->category ),
				'description_da' => sanitize_textarea_field( $cookie->description_da ),
				'duration'       => sanitize_text_field( $cookie->duration ),
				'provider'       => sanitize_text_field( $cookie->provider ),
				'necessary'      => (bool) $cookie->necessary,
			);
		}

		return $formatted;
	}

	/**
	 * Eksporterer data til WordPress DSAR-format (wp_privacy_personal_data_exporters).
	 *
	 * @param array $consent_log Samtykke-log-poster.
	 * @return array WordPress-formateret data.
	 */
	public function to_wordpress_export_format( array $consent_log ) {
		$data_to_export = array();

		foreach ( $consent_log as $log ) {
			$consent_data   = json_decode( $log->consent_data, true );
			$consent_labels = array();

			if ( is_array( $consent_data ) ) {
				$category_names = array(
					'necessary'  => __( 'Nødvendige', 'cookiedk' ),
					'functional' => __( 'Funktionelle', 'cookiedk' ),
					'analytics'  => __( 'Analyser', 'cookiedk' ),
					'marketing'  => __( 'Marketing', 'cookiedk' ),
				);

				foreach ( $consent_data as $cat => $accepted ) {
					$cat_label        = isset( $category_names[ $cat ] ) ? $category_names[ $cat ] : esc_html( $cat );
					$consent_labels[] = $cat_label . ': ' . ( $accepted ? __( 'Ja', 'cookiedk' ) : __( 'Nej', 'cookiedk' ) );
				}
			}

			$ip_display = '';
			if ( $log->ip_anonymized ) {
				$ip_display = __( '[anonymiseret]', 'cookiedk' );
			} elseif ( ! empty( $log->ip_address ) ) {
				$ip_display = sanitize_text_field( $log->ip_address );
			}

			$item_data = array(
				array(
					'name'  => __( 'Tidspunkt', 'cookiedk' ),
					'value' => esc_html( $log->consent_timestamp ),
				),
				array(
					'name'  => __( 'Samtykke', 'cookiedk' ),
					'value' => esc_html( implode( ', ', $consent_labels ) ),
				),
			);

			if ( $ip_display ) {
				$item_data[] = array(
					'name'  => __( 'IP-adresse', 'cookiedk' ),
					'value' => esc_html( $ip_display ),
				);
			}

			if ( ! empty( $log->user_agent ) ) {
				$item_data[] = array(
					'name'  => __( 'Browser', 'cookiedk' ),
					'value' => esc_html( $log->user_agent ),
				);
			}

			$data_to_export[] = array(
				'group_id'    => 'cookiedk_consent',
				'group_label' => __( 'CookieDK Samtykker', 'cookiedk' ),
				'item_id'     => 'consent-' . absint( $log->id ),
				'data'        => $item_data,
			);
		}

		return $data_to_export;
	}

	/**
	 * Returnerer antal samtykker for et fingerprint.
	 *
	 * @param string $fingerprint Bruger-fingerprint.
	 * @return int
	 */
	public function count_consents( $fingerprint ) {
		$fingerprint = sanitize_text_field( $fingerprint );
		$log         = $this->storage->get_consent_log( $fingerprint, 9999 );
		return count( $log );
	}
}
