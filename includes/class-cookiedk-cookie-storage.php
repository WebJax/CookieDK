<?php
/**
 * Cookie-lagring for CookieDK.
 *
 * Denne klasse håndterer oprettelse af database-tabeller, CRUD-operationer
 * for detekterede cookies og sanitering/validering af data.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Cookie_Storage.
 *
 * Håndterer al database-kommunikation for cookies.
 */
class CookieDK_Cookie_Storage {


	/**
	 * Cookiestabel.
	 *
	 * @var string
	 */
	private $cookies_table;

	/**
	 * Tabelnavn for samtykke-log.
	 *
	 * @var string
	 */
	private $consent_log_table;

	/**
	 * Gyldige cookie-kategorier.
	 *
	 * @var array
	 */
	private $valid_categories = array( 'necessary', 'functional', 'analytics', 'marketing' );

	/**
	 * Gyldige cookie-kilder.
	 *
	 * @var array
	 */
	private $valid_sources = array( 'server', 'client', 'manual' );

	/**
	 * Konstruktør – sætter tabelnavne.
	 */
	public function __construct() {
		global $wpdb;
		$this->cookies_table     = $wpdb->prefix . 'cookiedk_cookies';
		$this->consent_log_table = $wpdb->prefix . 'cookiedk_consent_log';
	}

	/**
	 * Opretter database-tabeller.
	 *
	 * Benytter dbDelta til sikkert at oprette eller opdatere tabellerne.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql_cookies = "CREATE TABLE {$this->cookies_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			category VARCHAR(50) NOT NULL DEFAULT 'functional',
			description_da TEXT DEFAULT NULL,
			duration VARCHAR(100) DEFAULT NULL,
			provider VARCHAR(255) DEFAULT NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'server',
			necessary TINYINT(1) NOT NULL DEFAULT 0,
			detected_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			last_updated DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			wp_option_name VARCHAR(255) DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) $charset_collate;";

		$sql_consent_log = "CREATE TABLE {$this->consent_log_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_fingerprint VARCHAR(64) NOT NULL,
			consent_data LONGTEXT NOT NULL,
			consent_timestamp DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			ip_address VARCHAR(45) DEFAULT NULL,
			ip_anonymized TINYINT(1) NOT NULL DEFAULT 0,
			user_agent VARCHAR(500) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_fingerprint (user_fingerprint),
			KEY consent_timestamp (consent_timestamp)
		) $charset_collate;";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_cookies );
		dbDelta( $sql_consent_log );
	}

	/**
	 * Gemmer eller opdaterer en detekteret cookie.
	 *
	 * @param  array $cookie_data Cookie-data med keys: name, category, description_da, duration, provider, source, necessary.
	 * @return int|false ID på den gemte cookie eller false ved fejl.
	 */
	public function save_cookie( array $cookie_data ) {
		global $wpdb;

		$sanitized = $this->sanitize_cookie_data( $cookie_data );
		if ( false === $sanitized ) {
			return false;
		}

		// Tjek om cookie allerede eksisterer.
		$existing = $this->get_cookie_by_name( $sanitized['name'] );

		$now = current_time( 'mysql' );

		if ( $existing ) {
			// Opdater eksisterende cookie.
			$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->cookies_table,
				array(
					'category'       => $sanitized['category'],
					'description_da' => $sanitized['description_da'],
					'duration'       => $sanitized['duration'],
					'provider'       => $sanitized['provider'],
					'source'         => $sanitized['source'],
					'necessary'      => $sanitized['necessary'],
					'last_updated'   => $now,
				),
				array( 'id' => $existing->id ),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);

			if ( false === $result ) {
				return false;
			}

			return (int) $existing->id;
		}

		// Indsæt ny cookie.
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->cookies_table,
			array(
				'name'           => $sanitized['name'],
				'category'       => $sanitized['category'],
				'description_da' => $sanitized['description_da'],
				'duration'       => $sanitized['duration'],
				'provider'       => $sanitized['provider'],
				'source'         => $sanitized['source'],
				'necessary'      => $sanitized['necessary'],
				'detected_at'    => $now,
				'last_updated'   => $now,
				'enabled'        => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Henter en cookie fra databasen baseret på navn.
	 *
	 * @param  string $name Cookiens navn.
	 * @return object|null Database-rad eller null.
	 */
	public function get_cookie_by_name( $name ) {
		global $wpdb;

		$name = sanitize_text_field( $name );

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$this->cookies_table} WHERE name = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$name
			)
		);
	}

	/**
	 * Henter en cookie fra databasen baseret på ID.
	 *
	 * @param  int $id Cookie-ID.
	 * @return object|null Database-rad eller null.
	 */
	public function get_cookie_by_id( $id ) {
		global $wpdb;

		$id = absint( $id );

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$this->cookies_table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
	}

	/**
	 * Henter alle cookies fra databasen, eventuelt filtreret på kategori.
	 *
	 * @param  string|null $category Filtrér på kategori eller null for alle.
	 * @return array Liste af cookie-objekter.
	 */
	public function get_all_cookies( $category = null ) {
		global $wpdb;

		if ( $category ) {
			$category = sanitize_text_field( $category );
			$results  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$this->cookies_table} WHERE category = %s ORDER BY name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$category
				)
			);
		} else {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT * FROM {$this->cookies_table} ORDER BY category ASC, name ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		return $results ? $results : array();
	}

	/**
	 * Opdaterer en eksisterende cookie.
	 *
	 * @param  int   $id          Cookie-ID.
	 * @param  array $cookie_data Ny cookie-data.
	 * @return bool True ved succes, false ved fejl.
	 */
	public function update_cookie( $id, array $cookie_data ) {
		global $wpdb;

		$id        = absint( $id );
		$sanitized = $this->sanitize_cookie_data( $cookie_data );

		if ( false === $sanitized || ! $id ) {
			return false;
		}

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->cookies_table,
			array(
				'category'       => $sanitized['category'],
				'description_da' => $sanitized['description_da'],
				'duration'       => $sanitized['duration'],
				'provider'       => $sanitized['provider'],
				'last_updated'   => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Sletter en cookie fra databasen.
	 *
	 * @param  int $id Cookie-ID.
	 * @return bool True ved succes, false ved fejl.
	 */
	public function delete_cookie( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->cookies_table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Gemmer en samtykke-hændelse i loggen.
	 *
	 * @param  string $fingerprint  Bruger-fingerprint (anonymiseret).
	 * @param  array  $consent_data Array over valgte kategorier.
	 * @param  string $ip_address   Brugerens IP-adresse.
	 * @param  string $user_agent   Brugerens user-agent.
	 * @return int|false ID på den gemte log-post eller false ved fejl.
	 */
	public function log_consent( $fingerprint, array $consent_data, $ip_address = '', $user_agent = '' ) {
		global $wpdb;

		$fingerprint  = sanitize_text_field( $fingerprint );
		$ip_address   = sanitize_text_field( $ip_address );
		$user_agent   = sanitize_text_field( substr( $user_agent, 0, 500 ) );
		$consent_json = wp_json_encode( $consent_data );

		if ( false === $consent_json ) {
			return false;
		}

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->consent_log_table,
			array(
				'user_fingerprint'  => $fingerprint,
				'consent_data'      => $consent_json,
				'consent_timestamp' => current_time( 'mysql' ),
				'ip_address'        => $ip_address,
				'ip_anonymized'     => 0,
				'user_agent'        => $user_agent,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Henter samtykke-log-poster, eventuelt filtreret på fingerprint.
	 *
	 * @param  string|null $fingerprint Fingerprint at filtrere på, eller null for alle.
	 * @param  int         $limit       Maks antal poster.
	 * @return array Liste af log-poster.
	 */
	public function get_consent_log( $fingerprint = null, $limit = 100 ) {
		global $wpdb;

		$limit = absint( $limit );
		if ( ! $limit ) {
			$limit = 100;
		}

		if ( $fingerprint ) {
			$fingerprint = sanitize_text_field( $fingerprint );
			$results     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$this->consent_log_table} WHERE user_fingerprint = %s ORDER BY consent_timestamp DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$fingerprint,
					$limit
				)
			);
		} else {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$this->consent_log_table} ORDER BY consent_timestamp DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$limit
				)
			);
		}

		return $results ? $results : array();
	}

	/**
	 * Anonymiserer IP-adresser der er ældre end et givent antal dage.
	 *
	 * @param  int $days Antal dage før IP anonymiseres.
	 * @return int Antal opdaterede rækker.
	 */
	public function anonymize_old_ips( $days = 30 ) {
		global $wpdb;

		$days   = absint( $days );
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$this->consent_log_table} SET ip_address = NULL, ip_anonymized = 1 WHERE consent_timestamp < %s AND ip_anonymized = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);

		return (int) $result;
	}

	/**
	 * Sletter samtykke-log-poster for et givent fingerprint (retten til at blive glemt).
	 *
	 * @param  string $fingerprint Bruger-fingerprint.
	 * @return bool True ved succes.
	 */
	public function delete_consent_log_by_fingerprint( $fingerprint ) {
		global $wpdb;

		$fingerprint = sanitize_text_field( $fingerprint );

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->consent_log_table,
			array( 'user_fingerprint' => $fingerprint ),
			array( '%s' )
		);

		return false !== $result;
	}

	/**
	 * Saniterer og validerer cookie-data.
	 *
	 * @param  array $data Rå cookie-data.
	 * @return array|false Saniteret data eller false ved ugyldige data.
	 */
	private function sanitize_cookie_data( array $data ) {
		if ( empty( $data['name'] ) ) {
			return false;
		}

		$category = isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : 'functional';
		if ( ! in_array( $category, $this->valid_categories, true ) ) {
			$category = 'functional';
		}

		$source = isset( $data['source'] ) ? sanitize_text_field( $data['source'] ) : 'server';
		if ( ! in_array( $source, $this->valid_sources, true ) ) {
			$source = 'server';
		}

		return array(
			'name'           => sanitize_text_field( $data['name'] ),
			'category'       => $category,
			'description_da' => isset( $data['description_da'] ) ? sanitize_textarea_field( $data['description_da'] ) : '',
			'duration'       => isset( $data['duration'] ) ? sanitize_text_field( $data['duration'] ) : '',
			'provider'       => isset( $data['provider'] ) ? sanitize_text_field( $data['provider'] ) : '',
			'source'         => $source,
			'necessary'      => ! empty( $data['necessary'] ) ? 1 : 0,
		);
	}

	/**
	 * Returnerer tabelnavnet for cookies-tabellen.
	 *
	 * @return string
	 */
	public function get_cookies_table() {
		return $this->cookies_table;
	}

	/**
	 * Returnerer tabelnavnet for samtykke-log-tabellen.
	 *
	 * @return string
	 */
	public function get_consent_log_table() {
		return $this->consent_log_table;
	}
}
