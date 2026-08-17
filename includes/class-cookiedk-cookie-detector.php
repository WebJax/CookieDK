<?php
/**
 * Cookie-detektion for CookieDK.
 *
 * Denne klasse håndterer server-side og client-side detektion af cookies,
 * klassificering i kategorier og integration med WordPress hooks.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Cookie_Detector.
 *
 * Detekterer og klassificerer cookies på websitet.
 */
class CookieDK_Cookie_Detector {


	/**
	 * Liste over kendte cookies med metadata.
	 *
	 * @var array
	 */
	private $known_cookies = array();

	/**
	 * Konstruktør – indlæser kendte cookies.
	 */
	public function __construct() {
		$this->load_known_cookies();
	}

	/**
	 * Registrerer WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'detect_server_side_cookies' ) );
		add_action( 'wp_footer', array( $this, 'enqueue_detector_script' ) );
		add_action( 'wp_ajax_cookiedk_report_cookies', array( $this, 'ajax_receive_client_cookies' ) );
		add_action( 'wp_ajax_nopriv_cookiedk_report_cookies', array( $this, 'ajax_receive_client_cookies' ) );
	}

	/**
	 * Indlæser kendte cookies fra JSON-databasen med mappede kategorier.
	 *
	 * @return void
	 */
	private function load_known_cookies() {
		if ( ! class_exists( 'CookieDK_Cookie_Database' ) ) {
			require_once COOKIEDK_PLUGIN_DIR . 'includes/class-cookiedk-cookie-database.php';
		}

		$database            = new CookieDK_Cookie_Database();
		$this->known_cookies = $database->get_known_cookies();

		/**
		 * Filter til at tilføje eller ændre kendte cookies.
		 *
		 * @param array $known_cookies Liste over kendte cookies.
		 */
		$this->known_cookies = apply_filters( 'cookiedk_known_cookies', $this->known_cookies );
	}

	/**
	 * Server-side detektion: Scanner $_COOKIE og gemmer ukendte cookies.
	 *
	 * @return void
	 */
	public function detect_server_side_cookies() {
		if ( empty( $_COOKIE ) ) {
			return;
		}

		$detected = array();

		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			$cookie_name = sanitize_text_field( $cookie_name );
			$meta        = $this->classify_cookie( $cookie_name );
			if ( $meta ) {
				$detected[ $cookie_name ] = array_merge(
					array(
						'name'   => $cookie_name,
						'source' => 'server',
					),
					$meta
				);
			}
		}

		if ( ! empty( $detected ) ) {
			$this->save_detected_cookies( $detected );
		}
	}

	/**
	 * Klassificerer en cookie baseret på dens navn.
	 *
	 * @param  string $cookie_name Cookiens navn.
	 * @return array|false Cookie-metadata eller false hvis ukendt.
	 */
	public function classify_cookie( $cookie_name ) {
		// Eksakt match (wildcard-nøgler springes over).
		if ( isset( $this->known_cookies[ $cookie_name ] ) ) {
			$exact = $this->known_cookies[ $cookie_name ];
			if ( empty( $exact['match_type'] ) || 'wildcard' !== $exact['match_type'] ) {
				return $exact;
			}
		}

		// Wildcard-match (f.eks. wordpress_* eller _gat_*).
		foreach ( $this->known_cookies as $pattern => $meta ) {
			$is_wildcard = ( isset( $meta['match_type'] ) && 'wildcard' === $meta['match_type'] ) || false !== strpos( $pattern, '*' );
			if ( ! $is_wildcard ) {
				continue;
			}

			$glob  = isset( $meta['pattern'] ) ? (string) $meta['pattern'] : (string) $pattern;
			$regex = '/^' . str_replace( '\*', '.*', preg_quote( $glob, '/' ) ) . '$/';
			if ( preg_match( $regex, $cookie_name ) ) {
				return $meta;
			}
		}

		// Ukendt cookie – returner standard-klassificering.
		return array(
			'category'       => 'functional',
			'description_da' => sprintf(
			/* translators: %s: Cookie-navn. */
				__( 'Cookie ved navn "%s". Formålet med denne cookie er ikke identificeret.', 'cookiedk' ),
				esc_html( $cookie_name )
			),
			'duration'       => __( 'Ukendt', 'cookiedk' ),
			'provider'       => __( 'Ukendt udbyder', 'cookiedk' ),
			'necessary'      => false,
		);
	}

	/**
	 * Gemmer detekterede cookies via CookieDK_Cookie_Storage.
	 *
	 * @param  array $cookies Array af cookie-data.
	 * @return void
	 */
	private function save_detected_cookies( array $cookies ) {
		if ( ! class_exists( 'CookieDK_Cookie_Storage' ) ) {
			return;
		}

		$storage = new CookieDK_Cookie_Storage();
		foreach ( $cookies as $cookie ) {
			$storage->save_cookie( $cookie );
		}
	}

	/**
	 * Tilføjer JavaScript til footer til client-side cookie-detektion.
	 *
	 * @return void
	 */
	public function enqueue_detector_script() {
		if ( is_admin() ) {
			return;
		}

		$nonce = wp_create_nonce( 'cookiedk_report_cookies' );
		?>
		<script id="cookiedk-detector" type="text/javascript">
		/* CookieDK – Client-side Cookie-detektor */
		(function() {
			'use strict';

			/**
			 * Henter alle cookies fra document.cookie som et objekt.
			 */
			function getAllCookies() {
				var result = {};
				var cookies = document.cookie ? document.cookie.split( '; ' ) : [];
				for ( var i = 0; i < cookies.length; i++ ) {
					var parts = cookies[ i ].split( '=' );
					var name  = decodeURIComponent( parts[0].trim() );
					if ( name ) {
						result[ name ] = true;
					}
				}
				return result;
			}

			/**
			 * Sender opdagede cookies til WordPress via AJAX.
			 */
			function reportCookies( cookieNames ) {
				if ( ! cookieNames.length || ! window.XMLHttpRequest ) {
					return;
				}

				var xhr  = new XMLHttpRequest();
				var data = 'action=cookiedk_report_cookies'
					+ '&nonce=<?php echo esc_js( $nonce ); ?>'
					+ '&cookies=' + encodeURIComponent( JSON.stringify( cookieNames ) );

				xhr.open( 'POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', true );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.send( data );
			}

			// Kør detektion når siden er loadet.
			function init() {
				var cookies = getAllCookies();
				var names   = Object.keys( cookies );
				if ( names.length > 0 ) {
					// Rapportér kun én gang per session.
					var storageKey = 'cookiedk_reported';
					try {
						if ( ! sessionStorage.getItem( storageKey ) ) {
							reportCookies( names );
							sessionStorage.setItem( storageKey, '1' );
						}
					} catch ( e ) {
						reportCookies( names );
					}
				}
			}

			if ( document.readyState === 'loading' ) {
				document.addEventListener( 'DOMContentLoaded', init );
			} else {
				init();
			}
		}());
		</script>
		<?php
	}

	/**
	 * AJAX-handler: Modtager liste af cookie-navne fra client-side detektion.
	 *
	 * @return void
	 */
	public function ajax_receive_client_cookies() {
		// Verificér nonce.
		check_ajax_referer( 'cookiedk_report_cookies', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_report_cookies', 60, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		$raw_cookies  = isset( $_POST['cookies'] ) ? wp_unslash( $_POST['cookies'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$cookie_names = json_decode( sanitize_text_field( $raw_cookies ), true );

		if ( ! is_array( $cookie_names ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldige cookie-data.', 'cookiedk' ) ) );
		}

		$detected = array();
		foreach ( $cookie_names as $name ) {
			$name = sanitize_text_field( $name );
			if ( empty( $name ) ) {
				continue;
			}
			$meta = $this->classify_cookie( $name );
			if ( $meta ) {
				$detected[ $name ] = array_merge(
					array(
						'name'   => $name,
						'source' => 'client',
					),
					$meta
				);
			}
		}

		if ( ! empty( $detected ) ) {
			$this->save_detected_cookies( $detected );
		}

		wp_send_json_success( array( 'detected' => count( $detected ) ) );
	}

	/**
	 * Returnerer alle kendte cookies.
	 *
	 * @return array
	 */
	public function get_known_cookies() {
		return $this->known_cookies;
	}

	/**
	 * Returnerer gyldige cookie-kategorier.
	 *
	 * @return array
	 */
	public static function get_categories() {
		return array(
			'necessary'  => __( 'Nødvendige', 'cookiedk' ),
			'functional' => __( 'Funktionelle', 'cookiedk' ),
			'analytics'  => __( 'Analyser', 'cookiedk' ),
			'marketing'  => __( 'Marketing', 'cookiedk' ),
		);
	}
}
