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
	 * CookieDK_Consent_Export-instans.
	 *
	 * @var CookieDK_Consent_Export
	 */
	private $exporter;

	/**
	 * Gyldige cookie-kategorier.
	 *
	 * @var array
	 */
	private $valid_categories = array( 'necessary', 'functional', 'analytics', 'marketing' );

	/**
	 * Konstruktør.
	 */
	public function __construct() {
		$this->storage  = new CookieDK_Cookie_Storage();
		$this->exporter = new CookieDK_Consent_Export();
	}

	/**
	 * Registrerer WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Eksisterende AJAX-handler til samtykkelogning.
		add_action( 'wp_ajax_cookiedk_log_consent', array( $this, 'ajax_log_consent' ) );
		add_action( 'wp_ajax_nopriv_cookiedk_log_consent', array( $this, 'ajax_log_consent' ) );

		// Fase 6: Nye AJAX-endpoints.
		add_action( 'wp_ajax_cookiedk_save_consent', array( $this, 'ajax_save_consent' ) );
		add_action( 'wp_ajax_nopriv_cookiedk_save_consent', array( $this, 'ajax_save_consent' ) );

		add_action( 'wp_ajax_cookiedk_export_user_data', array( $this, 'ajax_export_user_data' ) );

		add_action( 'wp_ajax_cookiedk_revoke_consent', array( $this, 'ajax_revoke_consent' ) );
		add_action( 'wp_ajax_nopriv_cookiedk_revoke_consent', array( $this, 'ajax_revoke_consent' ) );

		add_action( 'wp_ajax_cookiedk_delete_user_cookies', array( $this, 'ajax_delete_user_cookies' ) );
		add_action( 'wp_ajax_nopriv_cookiedk_delete_user_cookies', array( $this, 'ajax_delete_user_cookies' ) );

		// WordPress privacy-hooks (GDPR data-export og -sletning).
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_data_eraser' ) );

		// WordPress bruger-hooks.
		add_action( 'user_register', array( $this, 'on_user_register' ) );
		add_action( 'delete_user', array( $this, 'on_delete_user' ) );
		add_action( 'wp_logout', array( $this, 'on_user_logout' ) );

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
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_log_consent', 30, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		$raw_consent  = isset( $_POST['consent'] ) ? wp_unslash( $_POST['consent'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$consent_data = json_decode( sanitize_text_field( $raw_consent ), true );

		if ( ! is_array( $consent_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldige samtykke-data.', 'cookiedk' ) ) );
		}

		$sanitized_consent = $this->sanitize_consent_data( $consent_data );

		// Generér anonymt fingerprint.
		$fingerprint = $this->generate_fingerprint();

		// Hent IP (til logning, anonymiseres efter 30 dage).
		$ip_address = $this->get_client_ip();

		// Hent user-agent.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$log_id = $this->storage->log_consent( $fingerprint, $sanitized_consent, $ip_address, $user_agent );

		if ( false === $log_id ) {
			wp_send_json_error( array( 'message' => __( 'Samtykke kunne ikke gemmes.', 'cookiedk' ) ) );
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
	 * AJAX-handler: Gem samtykke (Fase 6 endpoint).
	 *
	 * Endpoint: cookiedk_save_consent
	 *
	 * @return void
	 */
	public function ajax_save_consent() {
		check_ajax_referer( 'cookiedk_save_consent', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_save_consent', 30, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		$raw_consent  = isset( $_POST['consent'] ) ? wp_unslash( $_POST['consent'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$consent_data = json_decode( sanitize_text_field( $raw_consent ), true );

		if ( ! is_array( $consent_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldige samtykke-data.', 'cookiedk' ) ) );
		}

		$sanitized_consent = $this->sanitize_consent_data( $consent_data );

		$fingerprint = $this->generate_fingerprint();
		$ip_address  = $this->get_client_ip();
		$user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$log_id = $this->storage->log_consent( $fingerprint, $sanitized_consent, $ip_address, $user_agent );

		if ( false === $log_id ) {
			wp_send_json_error( array( 'message' => __( 'Samtykke kunne ikke gemmes.', 'cookiedk' ) ) );
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
	 * AJAX-handler: Eksporterer bruger-data som JSON (Fase 6 endpoint).
	 *
	 * Endpoint: cookiedk_export_user_data
	 * Kræver indlogget bruger.
	 *
	 * @return void
	 */
	public function ajax_export_user_data() {
		check_ajax_referer( 'cookiedk_export_user_data', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_export_user_data', 20, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Du skal være logget ind for at eksportere data.', 'cookiedk' ) ) );
		}

		$user_id     = get_current_user_id();
		$fingerprint = $this->generate_fingerprint_for_user( $user_id );
		$export_data = $this->exporter->export_as_json( $fingerprint, $user_id );

		wp_send_json_success(
			array(
				'data'    => $export_data,
				'message' => __( 'Data eksporteret.', 'cookiedk' ),
			)
		);
	}

	/**
	 * AJAX-handler: Tilbagekald samtykke (Fase 6 endpoint).
	 *
	 * Endpoint: cookiedk_revoke_consent
	 *
	 * @return void
	 */
	public function ajax_revoke_consent() {
		check_ajax_referer( 'cookiedk_revoke_consent', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_revoke_consent', 20, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		$fingerprint = '';

		if ( is_user_logged_in() ) {
			$fingerprint = $this->generate_fingerprint_for_user( get_current_user_id() );
		} else {
			$raw_fp = isset( $_POST['fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['fingerprint'] ) ) : '';
			if ( $raw_fp && preg_match( '/^[a-f0-9]{64}$/i', $raw_fp ) ) {
				$fingerprint = $raw_fp;
			}
		}

		if ( empty( $fingerprint ) ) {
			wp_send_json_error( array( 'message' => __( 'Fingerprint mangler.', 'cookiedk' ) ) );
		}

		$deleted = $this->storage->delete_consent_log_by_fingerprint( $fingerprint );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'Samtykke tilbagekaldt.', 'cookiedk' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Samtykke kunne ikke tilbagekaldes.', 'cookiedk' ) ) );
		}
	}

	/**
	 * AJAX-handler: Slet bruger-cookies fra log (Fase 6 endpoint).
	 *
	 * Endpoint: cookiedk_delete_user_cookies
	 *
	 * @return void
	 */
	public function ajax_delete_user_cookies() {
		check_ajax_referer( 'cookiedk_delete_user_cookies', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_delete_user_cookies', 20, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		$fingerprint = '';

		if ( is_user_logged_in() ) {
			$fingerprint = $this->generate_fingerprint_for_user( get_current_user_id() );
		} else {
			$raw_fp = isset( $_POST['fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['fingerprint'] ) ) : '';
			if ( $raw_fp && preg_match( '/^[a-f0-9]{64}$/i', $raw_fp ) ) {
				$fingerprint = $raw_fp;
			}
		}

		if ( empty( $fingerprint ) ) {
			wp_send_json_error( array( 'message' => __( 'Fingerprint mangler.', 'cookiedk' ) ) );
		}

		$deleted = $this->storage->delete_consent_log_by_fingerprint( $fingerprint );

		if ( $deleted ) {
			wp_send_json_success( array( 'message' => __( 'Samtykke-data slettet.', 'cookiedk' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Data kunne ikke slettes.', 'cookiedk' ) ) );
		}
	}

	/**
	 * Hook: Kører ved bruger-registrering.
	 *
	 * Logger at en ny bruger er registreret (ingen persondata gemt).
	 *
	 * @param  int $user_id WordPress bruger-ID.
	 * @return void
	 */
	public function on_user_register( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}

		// Gem en metaværdi der noterer registreringstidspunktet for GDPR-overholdelse.
		update_user_meta( $user_id, '_cookiedk_registered_at', current_time( 'mysql' ) );
	}

	/**
	 * Hook: Kører ved sletning af WordPress-bruger.
	 *
	 * Sletter alle samtykke-log-poster for den pågældende bruger.
	 *
	 * @param  int $user_id WordPress bruger-ID.
	 * @return void
	 */
	public function on_delete_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}

		$fingerprint = $this->generate_fingerprint_for_user( $user_id );
		$this->storage->delete_consent_log_by_fingerprint( $fingerprint );
	}

	/**
	 * Hook: Kører ved logout.
	 *
	 * Rydder op i session-relaterede data (ingen samtykke-sletning).
	 *
	 * @return void
	 */
	public function on_user_logout() {
		// Placeholder til fremtidig session-håndtering.
	}

	/**
	 * Registrerer data-exporter til WordPress privacy-tool.
	 *
	 * @param  array $exporters Eksisterende exporters.
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
	 * @param  array $erasers Eksisterende erasers.
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
	 * @param  string $email_address Brugerens e-mail.
	 *
	 * @return array Export-data i WordPress-format.
	 */
	public function export_user_data( $email_address ) {
		$email_address = sanitize_email( $email_address );

		// Find WordPress-bruger.
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		// Generér fingerprint for denne bruger og eksportér via CookieDK_Consent_Export.
		$fingerprint    = $this->generate_fingerprint_for_user( $user->ID );
		$consent_log    = $this->storage->get_consent_log( $fingerprint, 100 );
		$data_to_export = $this->exporter->to_wordpress_export_format( $consent_log );

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Sletter samtykke-data for en given e-mail (retten til at blive glemt).
	 *
	 * @param  string $email_address Brugerens e-mail.
	 * @return array Resultat af sletning.
	 */
	public function erase_user_data( $email_address ) {
		$email_address = sanitize_email( $email_address );
		$items_removed = false;
		$messages      = array();

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
		$settings       = get_option( 'cookiedk_settings', array() );
		$anonymize_ip   = ! empty( $settings['anonymize_ip'] ) ? true : false;
		$retention_days = isset( $settings['log_retention_days'] ) ? absint( $settings['log_retention_days'] ) : 365;

		if ( $anonymize_ip ) {
			$this->storage->anonymize_old_ips( 30 );
		}

		$this->delete_old_consent_logs( $retention_days );
	}

	/**
	 * Sletter samtykke-log-poster ældre end et givent antal dage.
	 *
	 * @param  int $days Antal dage at bevare.
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
	 * @param  int $user_id WordPress bruger-ID.
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

	/**
	 * Saniterer og validerer samtykke-data.
	 *
	 * Nødvendige cookies er altid aktive (true).
	 * Kun tilladte kategorier bevares.
	 *
	 * @param  array $consent_data Rå samtykke-data fra request.
	 * @return array Saniteret samtykke-array.
	 */
	private function sanitize_consent_data( array $consent_data ) {
		$sanitized = array();

		foreach ( $this->valid_categories as $cat ) {
			$sanitized[ $cat ] = ! empty( $consent_data[ $cat ] );
		}

		// Nødvendige cookies er altid aktive.
		$sanitized['necessary'] = true;

		return $sanitized;
	}
}
