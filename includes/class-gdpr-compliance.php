<?php
/**
 * GDPR-compliance for CookieDK.
 *
 * Denne klasse håndterer samtykkelogning, data-export og anonymisering
 * i overensstemmelse med GDPR-reglerne.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_GDPR_Compliance.
 *
 * Håndterer GDPR-overholdelse: samtykkelogning, data-export og anonymisering.
 */
class CookieDK_GDPR_Compliance {

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
	 * Registrerer WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		// AJAX-handler til samtykkelogning.
		add_action( 'wp_ajax_cookiedk_log_consent', array( $this, 'ajax_log_consent' ) );
		add_action( 'wp_ajax_nopriv_cookiedk_log_consent', array( $this, 'ajax_log_consent' ) );

		// WordPress privacy-hooks (GDPR data-export og -sletning).
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_data_eraser' ) );

		// Daglig cron-job til IP-anonymisering.
		add_action( 'cookiedk_daily_cron', array( $this, 'run_daily_maintenance' ) );
		if ( ! wp_next_scheduled( 'cookiedk_daily_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'cookiedk_daily_cron' );
		}
	}

	/**
	 * AJAX-handler: Logger samtykke fra frontend.
	 *
	 * @return void
	 */
	public function ajax_log_consent() {
		// Verificér nonce.
		check_ajax_referer( 'cookiedk_log_consent', 'nonce' );

		$raw_consent = isset( $_POST['consent'] ) ? wp_unslash( $_POST['consent'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$consent_data = json_decode( sanitize_text_field( $raw_consent ), true );

		if ( ! is_array( $consent_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldige samtykke-data.', 'cookiedk' ) ) );
			return;
		}

		// Sanitér samtykke-data – kun tilladte kategorier.
		$valid_categories = array( 'necessary', 'functional', 'analytics', 'marketing' );
		$sanitized_consent = array();
		foreach ( $valid_categories as $cat ) {
			$sanitized_consent[ $cat ] = ! empty( $consent_data[ $cat ] );
		}
		// Nødvendige cookies er altid aktive.
		$sanitized_consent['necessary'] = true;

		// Generér anonymt fingerprint.
		$fingerprint = $this->generate_fingerprint();

		// Hent IP (til logning, anonymiseres efter 30 dage).
		$ip_address = $this->get_client_ip();

		// Hent user-agent.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$log_id = $this->storage->log_consent( $fingerprint, $sanitized_consent, $ip_address, $user_agent );

		if ( false === $log_id ) {
			wp_send_json_error( array( 'message' => __( 'Samtykke kunne ikke gemmes.', 'cookiedk' ) ) );
			return;
		}

		wp_send_json_success(
			array(
				'log_id'      => $log_id,
				'fingerprint' => $fingerprint,
				'consent'     => $sanitized_consent,
				'message'     => __( 'Samtykke gemt.', 'cookiedk' ),
			)
		);
	}

	/**
	 * Registrerer data-exporter til WordPress privacy-tool.
	 *
	 * @param array $exporters Eksisterende exporters.
	 * @return array
	 */
	public function register_data_exporter( array $exporters ) {
		$exporters['cookiedk'] = array(
			'exporter_friendly_name' => __( 'CookieDK Samtykkedata', 'cookiedk' ),
			'callback'               => array( $this, 'export_user_data' ),
		);
		return $exporters;
	}

	/**
	 * Registrerer data-eraser til WordPress privacy-tool.
	 *
	 * @param array $erasers Eksisterende erasers.
	 * @return array
	 */
	public function register_data_eraser( array $erasers ) {
		$erasers['cookiedk'] = array(
			'eraser_friendly_name' => __( 'CookieDK Samtykkedata', 'cookiedk' ),
			'callback'             => array( $this, 'erase_user_data' ),
		);
		return $erasers;
	}

	/**
	 * Eksporterer samtykke-data for en given e-mail.
	 *
	 * @param string $email_address Brugerens e-mail.
	 * @param int    $page          Sidenummer (til paginering).
	 * @return array Export-data i WordPress-format.
	 */
	public function export_user_data( $email_address, $page = 1 ) {
		$email_address = sanitize_email( $email_address );
		$data_to_export = array();

		// Find WordPress-bruger.
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		// Generér fingerprint for denne bruger.
		$fingerprint = $this->generate_fingerprint_for_user( $user->ID );
		$consent_log = $this->storage->get_consent_log( $fingerprint, 100 );

		foreach ( $consent_log as $log ) {
			$consent_decoded = json_decode( $log->consent_data, true );
			$consent_labels  = array();

			if ( is_array( $consent_decoded ) ) {
				foreach ( $consent_decoded as $cat => $accepted ) {
					$consent_labels[] = esc_html( $cat ) . ': ' . ( $accepted ? __( 'Ja', 'cookiedk' ) : __( 'Nej', 'cookiedk' ) );
				}
			}

			$data_to_export[] = array(
				'group_id'    => 'cookiedk_consent',
				'group_label' => __( 'CookieDK Samtykker', 'cookiedk' ),
				'item_id'     => 'consent-' . $log->id,
				'data'        => array(
					array(
						'name'  => __( 'Tidspunkt', 'cookiedk' ),
						'value' => esc_html( $log->consent_timestamp ),
					),
					array(
						'name'  => __( 'Samtykke', 'cookiedk' ),
						'value' => esc_html( implode( ', ', $consent_labels ) ),
					),
				),
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Sletter samtykke-data for en given e-mail (retten til at blive glemt).
	 *
	 * @param string $email_address Brugerens e-mail.
	 * @param int    $page          Sidenummer.
	 * @return array Resultat af sletning.
	 */
	public function erase_user_data( $email_address, $page = 1 ) {
		$email_address  = sanitize_email( $email_address );
		$items_removed  = false;
		$messages       = array();

		$user = get_user_by( 'email', $email_address );
		if ( $user ) {
			$fingerprint   = $this->generate_fingerprint_for_user( $user->ID );
			$items_removed = $this->storage->delete_consent_log_by_fingerprint( $fingerprint );

			if ( $items_removed ) {
				$messages[] = __( 'CookieDK samtykke-data slettet.', 'cookiedk' );
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Daglig vedligeholdelse: Anonymiserer gamle IP-adresser og rydder gammel log.
	 *
	 * @return void
	 */
	public function run_daily_maintenance() {
		$settings         = get_option( 'cookiedk_settings', array() );
		$anonymize_ip     = ! empty( $settings['anonymize_ip'] ) ? true : false;
		$retention_days   = isset( $settings['log_retention_days'] ) ? absint( $settings['log_retention_days'] ) : 365;

		if ( $anonymize_ip ) {
			$this->storage->anonymize_old_ips( 30 );
		}

		$this->delete_old_consent_logs( $retention_days );
	}

	/**
	 * Sletter samtykke-log-poster ældre end et givent antal dage.
	 *
	 * @param int $days Antal dage at bevare.
	 * @return int Antal slettede rækker.
	 */
	private function delete_old_consent_logs( $days ) {
		global $wpdb;

		$days   = absint( $days );
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$table  = $this->storage->get_consent_log_table();

		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE consent_timestamp < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);
	}

	/**
	 * Genererer et anonymt fingerprint baseret på session og site.
	 *
	 * Fingerprint indeholder ingen personhenførbare oplysninger.
	 *
	 * @return string SHA-256 fingerprint.
	 */
	private function generate_fingerprint() {
		$session_id = '';

		// Brug PHP session-ID eller generer et tilfældigt ID.
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			$session_id = wp_generate_uuid4();
		} else {
			$session_id = bin2hex( random_bytes( 16 ) );
		}

		return hash( 'sha256', $session_id . get_site_url() . time() );
	}

	/**
	 * Genererer et deterministisk fingerprint for en WordPress-bruger.
	 *
	 * @param int $user_id WordPress bruger-ID.
	 * @return string SHA-256 fingerprint.
	 */
	private function generate_fingerprint_for_user( $user_id ) {
		$user_id = absint( $user_id );
		return hash( 'sha256', 'cookiedk_user_' . $user_id . '_' . get_site_url() );
	}

	/**
	 * Henter klientens IP-adresse.
	 *
	 * @return string IP-adresse eller tom streng.
	 */
	private function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}
}
