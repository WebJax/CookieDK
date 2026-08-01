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
	 * Indlæser listen over kendte cookies med klassificering og dansk beskrivelse.
	 *
	 * @return void
	 */
	private function load_known_cookies() {
		$this->known_cookies = array(

			// WordPress core.
			'wordpress_*'             => array(
				'category'       => 'necessary',
				'description_da' => 'WordPress sessions-cookie til at holde dig logget ind.',
				'duration'       => 'Session',
				'provider'       => 'WordPress',
				'necessary'      => true,
			),
			'wordpress_logged_in_*'   => array(
				'category'       => 'necessary',
				'description_da' => 'WordPress login-cookie til at bekræfte din identitet.',
				'duration'       => '14 dage',
				'provider'       => 'WordPress',
				'necessary'      => true,
			),
			'wp-settings-*'           => array(
				'category'       => 'functional',
				'description_da' => 'Gemmer dine personlige præferencer i WordPress admin.',
				'duration'       => '1 år',
				'provider'       => 'WordPress',
				'necessary'      => false,
			),
			'wordpress_test_cookie'   => array(
				'category'       => 'necessary',
				'description_da' => 'Tester om din browser understøtter cookies.',
				'duration'       => 'Session',
				'provider'       => 'WordPress',
				'necessary'      => true,
			),
			'comment_author_*'        => array(
				'category'       => 'functional',
				'description_da' => 'Gemmer dit navn og e-mail til fremtidige kommentarer.',
				'duration'       => '1 år',
				'provider'       => 'WordPress',
				'necessary'      => false,
			),

			// WooCommerce.
			'woocommerce_cart_hash'   => array(
				'category'       => 'necessary',
				'description_da' => 'Hjælper WooCommerce med at huske indholdet af din indkøbskurv.',
				'duration'       => 'Session',
				'provider'       => 'WooCommerce',
				'necessary'      => true,
			),
			'woocommerce_items_in_cart' => array(
				'category'       => 'necessary',
				'description_da' => 'Holder styr på om du har varer i din indkøbskurv.',
				'duration'       => 'Session',
				'provider'       => 'WooCommerce',
				'necessary'      => true,
			),
			'wc_session_cookie'       => array(
				'category'       => 'necessary',
				'description_da' => 'Gemmer din WooCommerce-session og indkøbskurv.',
				'duration'       => '2 dage',
				'provider'       => 'WooCommerce',
				'necessary'      => true,
			),

			// Google Analytics (Universal Analytics).
			'_ga'                     => array(
				'category'       => 'analytics',
				'description_da' => 'Google Analytics bruger-ID til at skelne besøgende fra hinanden.',
				'duration'       => '2 år',
				'provider'       => 'Google',
				'necessary'      => false,
			),
			'_gid'                    => array(
				'category'       => 'analytics',
				'description_da' => 'Google Analytics cookie til at skelne brugere. Udløber efter 24 timer.',
				'duration'       => '24 timer',
				'provider'       => 'Google',
				'necessary'      => false,
			),
			'_gat'                    => array(
				'category'       => 'analytics',
				'description_da' => 'Google Analytics cookie til at begrænse antallet af forespørgsler.',
				'duration'       => '1 minut',
				'provider'       => 'Google',
				'necessary'      => false,
			),
			'_gat_*'                  => array(
				'category'       => 'analytics',
				'description_da' => 'Google Analytics cookie til at begrænse forespørgselshastighed.',
				'duration'       => '1 minut',
				'provider'       => 'Google',
				'necessary'      => false,
			),

			// Google Analytics 4.
			'_ga_*'                   => array(
				'category'       => 'analytics',
				'description_da' => 'Google Analytics 4 cookie til at gemme og tælle sidevisninger.',
				'duration'       => '2 år',
				'provider'       => 'Google',
				'necessary'      => false,
			),

			// Google Ads / DoubleClick.
			'_gcl_au'                 => array(
				'category'       => 'marketing',
				'description_da' => 'Google Ads cookie til at måle konverteringer fra annoncer.',
				'duration'       => '90 dage',
				'provider'       => 'Google Ads',
				'necessary'      => false,
			),
			'IDE'                     => array(
				'category'       => 'marketing',
				'description_da' => 'Google DoubleClick cookie til at vise personlige annoncer.',
				'duration'       => '1 år',
				'provider'       => 'Google DoubleClick',
				'necessary'      => false,
			),

			// Facebook Pixel.
			'_fbp'                    => array(
				'category'       => 'marketing',
				'description_da' => 'Facebook Pixel cookie til at spore besøg og vise relevante annoncer.',
				'duration'       => '90 dage',
				'provider'       => 'Meta (Facebook)',
				'necessary'      => false,
			),
			'_fbc'                    => array(
				'category'       => 'marketing',
				'description_da' => 'Facebook click ID cookie til at spore annoncekonverteringer.',
				'duration'       => '90 dage',
				'provider'       => 'Meta (Facebook)',
				'necessary'      => false,
			),

			// Hotjar.
			'_hjid'                   => array(
				'category'       => 'analytics',
				'description_da' => 'Hotjar bruger-ID cookie til at analysere brugeradfærd på siden.',
				'duration'       => '1 år',
				'provider'       => 'Hotjar',
				'necessary'      => false,
			),
			'_hjSessionUser_*'        => array(
				'category'       => 'analytics',
				'description_da' => 'Hotjar session-cookie til at holde styr på brugerens session.',
				'duration'       => '1 år',
				'provider'       => 'Hotjar',
				'necessary'      => false,
			),

			// Matomo (Piwik).
			'_pk_id*'                 => array(
				'category'       => 'analytics',
				'description_da' => 'Matomo cookie til at identificere unikke besøgende.',
				'duration'       => '13 måneder',
				'provider'       => 'Matomo',
				'necessary'      => false,
			),
			'_pk_ses*'                => array(
				'category'       => 'analytics',
				'description_da' => 'Matomo session-cookie til at spore den aktuelle besøgs-session.',
				'duration'       => '30 minutter',
				'provider'       => 'Matomo',
				'necessary'      => false,
			),

			// LinkedIn Insight.
			'li_gc'                   => array(
				'category'       => 'marketing',
				'description_da' => 'LinkedIn cookie til at gemme dit samtykke til ikke-essentielle cookies.',
				'duration'       => '2 år',
				'provider'       => 'LinkedIn',
				'necessary'      => false,
			),
			'AnalyticsSyncHistory'    => array(
				'category'       => 'analytics',
				'description_da' => 'LinkedIn cookie til at gemme information om synkronisering af analysedata.',
				'duration'       => '30 dage',
				'provider'       => 'LinkedIn',
				'necessary'      => false,
			),

			// Twitter/X.
			'personalization_id'      => array(
				'category'       => 'marketing',
				'description_da' => 'Twitter/X cookie til at tilpasse indhold og annoncer til dig.',
				'duration'       => '2 år',
				'provider'       => 'Twitter/X',
				'necessary'      => false,
			),

			// Cloudflare.
			'__cfduid'                => array(
				'category'       => 'necessary',
				'description_da' => 'Cloudflare sikkerhedscookie til at beskytte mod ondsindede angreb.',
				'duration'       => '1 år',
				'provider'       => 'Cloudflare',
				'necessary'      => true,
			),
			'cf_clearance'            => array(
				'category'       => 'necessary',
				'description_da' => 'Cloudflare cookie til at bevise, at besøgende har bestået en sikkerhedskontrol.',
				'duration'       => '30 minutter',
				'provider'       => 'Cloudflare',
				'necessary'      => true,
			),

			// Cookie-samtykke (egne).
			'cookiedk_consent'        => array(
				'category'       => 'necessary',
				'description_da' => 'Gemmer dine cookie-præferencer for dette website.',
				'duration'       => '1 år',
				'provider'       => 'Dette website',
				'necessary'      => true,
			),

			// Generisk session-cookie.
			'PHPSESSID'               => array(
				'category'       => 'necessary',
				'description_da' => 'PHP sessions-cookie til at holde din session aktiv under dit besøg.',
				'duration'       => 'Session',
				'provider'       => 'Dette website',
				'necessary'      => true,
			),
		);

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
					array( 'name' => $cookie_name, 'source' => 'server' ),
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
	 * @param string $cookie_name Cookiens navn.
	 * @return array|false Cookie-metadata eller false hvis ukendt.
	 */
	public function classify_cookie( $cookie_name ) {
		// Eksakt match.
		if ( isset( $this->known_cookies[ $cookie_name ] ) ) {
			return $this->known_cookies[ $cookie_name ];
		}

		// Wildcard-match (f.eks. wordpress_* eller _gat_*).
		foreach ( $this->known_cookies as $pattern => $meta ) {
			if ( false !== strpos( $pattern, '*' ) ) {
				$regex = '/^' . str_replace( '*', '.*', preg_quote( $pattern, '/' ) ) . '$/';
				if ( preg_match( $regex, $cookie_name ) ) {
					return $meta;
				}
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
	 * @param array $cookies Array af cookie-data.
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

		$raw_cookies = isset( $_POST['cookies'] ) ? wp_unslash( $_POST['cookies'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$cookie_names = json_decode( sanitize_text_field( $raw_cookies ), true );

		if ( ! is_array( $cookie_names ) ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldige cookie-data.', 'cookiedk' ) ) );
			return;
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
					array( 'name' => $name, 'source' => 'client' ),
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
