<?php
/**
 * Privacy Policy-generator for CookieDK.
 *
 * Denne klasse genererer automatisk dansk cookiepolitik-tekst
 * og integrerer med WordPress' Privacy Policy-side.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Privacy_Policy.
 *
 * Auto-genererer cookiepolitik-tekst og integrerer med WordPress.
 */
class CookieDK_Privacy_Policy {


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
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	/**
	 * Tilføjer cookiepolitik-tekst til WordPress Privacy Policy-siden.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = $this->generate_policy_content();

		wp_add_privacy_policy_content(
			'CookieDK',
			wp_kses_post( $content )
		);
	}

	/**
	 * Opretter eller opdaterer en WordPress-side med cookiepolitik.
	 *
	 * @param  array $owner Ejeroplysninger: name, address, postal, city, cvr.
	 * @return array|\WP_Error Resultat med page_id, url, edit_url og created.
	 */
	public function create_or_update_policy_page( array $owner ) {
		$settings = get_option( 'cookiedk_settings', array() );
		$page_id  = isset( $settings['cookie_policy_page_id'] ) ? absint( $settings['cookie_policy_page_id'] ) : 0;
		$content  = $this->generate_policy_content( $owner );
		$created  = false;

		$postarr = array(
			'post_title'   => __( 'Cookiepolitik', 'cookiedk' ),
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);

		if ( $page_id && get_post( $page_id ) ) {
			$postarr['ID'] = $page_id;
			$result_id     = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$existing = get_posts(
				array(
					'name'        => 'cookiepolitik',
					'post_type'   => 'page',
					'post_status' => array( 'publish', 'draft', 'private' ),
					'numberposts' => 1,
				)
			);

			if ( ! empty( $existing[0] ) && $existing[0] instanceof WP_Post ) {
				$postarr['ID'] = $existing[0]->ID;
				$result_id     = wp_update_post( wp_slash( $postarr ), true );
			} else {
				$postarr['post_name'] = 'cookiepolitik';
				$result_id            = wp_insert_post( wp_slash( $postarr ), true );
				$created              = true;
			}
		}

		if ( is_wp_error( $result_id ) ) {
			return $result_id;
		}

		$page_id = (int) $result_id;

		return array(
			'page_id'  => $page_id,
			'url'      => get_permalink( $page_id ),
			'edit_url' => get_edit_post_link( $page_id, 'raw' ),
			'created'  => $created,
		);
	}

	/**
	 * Genererer HTML-indhold til cookiepolitik-siden.
	 *
	 * @param  array $owner Valgfrie ejeroplysninger: name, address, postal, city, cvr.
	 * @return string HTML-indhold.
	 */
	public function generate_policy_content( $owner = array() ) {
		$cookies   = $this->storage->get_all_cookies();
		$site_name = get_bloginfo( 'name' );
		$settings  = get_option( 'cookiedk_settings', array() );

		$owner = wp_parse_args(
			is_array( $owner ) ? $owner : array(),
			array(
				'name'    => isset( $settings['policy_owner_name'] ) ? $settings['policy_owner_name'] : '',
				'address' => isset( $settings['policy_owner_address'] ) ? $settings['policy_owner_address'] : '',
				'postal'  => isset( $settings['policy_owner_postal'] ) ? $settings['policy_owner_postal'] : '',
				'city'    => isset( $settings['policy_owner_city'] ) ? $settings['policy_owner_city'] : '',
				'cvr'     => isset( $settings['policy_owner_cvr'] ) ? $settings['policy_owner_cvr'] : '',
			)
		);

		$content  = '<h2>' . esc_html__( 'Cookiepolitik', 'cookiedk' ) . '</h2>';
		$content .= '<p>' . sprintf(
		/* translators: %s: Webstedets navn. */
			esc_html__( '%s bruger cookies til at forbedre din oplevelse på vores website. Denne side forklarer, hvilke cookies vi bruger, og hvad de bruges til.', 'cookiedk' ),
			esc_html( $site_name )
		) . '</p>';

		if ( ! empty( $owner['name'] ) ) {
			$content .= '<h3>' . esc_html__( 'Dataansvarlig', 'cookiedk' ) . '</h3>';
			$content .= '<p>' . esc_html__( 'Den dataansvarlige for behandling af personoplysninger via cookies på dette website er:', 'cookiedk' ) . '</p>';
			$content .= '<p>';
			$content .= '<strong>' . esc_html( $owner['name'] ) . '</strong><br>';
			if ( ! empty( $owner['address'] ) ) {
				$content .= esc_html( $owner['address'] ) . '<br>';
			}
			$city_line = trim( $owner['postal'] . ' ' . $owner['city'] );
			if ( '' !== $city_line ) {
				$content .= esc_html( $city_line ) . '<br>';
			}
			if ( ! empty( $owner['cvr'] ) ) {
				/* translators: %s: CVR number. */
				$content .= esc_html( sprintf( __( 'CVR-nr.: %s', 'cookiedk' ), $owner['cvr'] ) );
			}
			$content .= '</p>';
		}

		$content .= '<h3>' . esc_html__( 'Hvad er cookies?', 'cookiedk' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'Cookies er små tekstfiler, der gemmes på din computer eller enhed, når du besøger et website. De bruges til at huske dine præferencer, holde dig logget ind og indsamle anonyme statistikker om brug af websitet.', 'cookiedk' ) . '</p>';

		$content .= '<h3>' . esc_html__( 'Samtykke', 'cookiedk' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'Når du besøger vores website for første gang, vises et cookie-banner, hvor du kan vælge, hvilke kategorier af cookies du accepterer. Du kan til enhver tid ændre dine præferencer ved at klikke på cookie-indstillingerne.', 'cookiedk' ) . '</p>';

		// Kategorier.
		$categories = array(
			'necessary'  => array(
				'label'       => __( 'Nødvendige cookies', 'cookiedk' ),
				'description' => __( 'Disse cookies er nødvendige for at websitet fungerer korrekt. De kan ikke deaktiveres. De bruges typisk til at huske dine cookie-præferencer, holde din session aktiv og sikre grundlæggende websitfunktioner.', 'cookiedk' ),
				'legal'       => __( 'Retsgrundlag: Legitim interesse (artikel 6(1)(f) GDPR) – nødvendig for webstedets funktion.', 'cookiedk' ),
			),
			'functional' => array(
				'label'       => __( 'Funktionelle cookies', 'cookiedk' ),
				'description' => __( 'Disse cookies giver websitet mulighed for at huske valg, du træffer (f.eks. dit brugernavn, sprog eller region), og tilbyde forbedrede, mere personlige funktioner.', 'cookiedk' ),
				'legal'       => __( 'Retsgrundlag: Samtykke (artikel 6(1)(a) GDPR).', 'cookiedk' ),
			),
			'analytics'  => array(
				'label'       => __( 'Analytiske cookies', 'cookiedk' ),
				'description' => __( 'Disse cookies hjælper os med at forstå, hvordan besøgende interagerer med websitet, ved at indsamle og rapportere informationer anonymt. De hjælper os med at forbedre websitets indhold og brugervenlighed.', 'cookiedk' ),
				'legal'       => __( 'Retsgrundlag: Samtykke (artikel 6(1)(a) GDPR).', 'cookiedk' ),
			),
			'marketing'  => array(
				'label'       => __( 'Marketing-cookies', 'cookiedk' ),
				'description' => __( 'Disse cookies bruges til at vise dig relevante annoncer baseret på dine interesser. De bruges til at begrænse, hvor mange gange du ser en annonce, og til at måle effektiviteten af annoncekampagner.', 'cookiedk' ),
				'legal'       => __( 'Retsgrundlag: Samtykke (artikel 6(1)(a) GDPR).', 'cookiedk' ),
			),
		);

		foreach ( $categories as $cat_key => $cat_info ) {
			$cat_cookies = array_filter(
				$cookies,
				function ( $c ) use ( $cat_key ) {
					return $c->category === $cat_key;
				}
			);

			$content .= '<h3>' . esc_html( $cat_info['label'] ) . '</h3>';
			$content .= '<p>' . esc_html( $cat_info['description'] ) . '</p>';
			$content .= '<p><em>' . esc_html( $cat_info['legal'] ) . '</em></p>';

			if ( ! empty( $cat_cookies ) ) {
				$content .= $this->render_cookie_table( $cat_cookies );
			}
		}

		// Opbevaring.
		$content .= '<h3>' . esc_html__( 'Opbevaring og sletning', 'cookiedk' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'Dit samtykke gemmes i op til 365 dage. IP-adresser i vores samtykke-log anonymiseres automatisk efter 30 dage.', 'cookiedk' ) . '</p>';

		// Rettigheder.
		$content .= '<h3>' . esc_html__( 'Dine rettigheder', 'cookiedk' ) . '</h3>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Ret til indsigt – Du kan til enhver tid anmode om en kopi af de data, vi har registreret om dig.', 'cookiedk' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Ret til sletning – Du kan anmode om sletning af dine samtykke-data ("retten til at blive glemt").', 'cookiedk' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Ret til at trække samtykke tilbage – Du kan til enhver tid trække dit samtykke tilbage uden begrundelse.', 'cookiedk' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p>' . sprintf(
		/* translators: %s: Link til Datatilsynets hjemmeside. */
			esc_html__( 'Du har ret til at klage til Datatilsynet, hvis du mener, at behandlingen af dine personoplysninger er i strid med GDPR. Læs mere på %s.', 'cookiedk' ),
			'<a href="https://www.datatilsynet.dk" target="_blank" rel="noopener noreferrer">datatilsynet.dk</a>'
		) . '</p>';

		return $content;
	}

	/**
	 * Genererer en HTML-tabel over cookies i en kategori.
	 *
	 * @param  array $cookies Liste af cookie-objekter.
	 * @return string HTML-tabel.
	 */
	private function render_cookie_table( array $cookies ) {
		$html  = '<table class="cookiedk-cookie-table">';
		$html .= '<thead><tr>';
		$html .= '<th>' . esc_html__( 'Cookie', 'cookiedk' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Udbyder', 'cookiedk' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Formål', 'cookiedk' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Levetid', 'cookiedk' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $cookies as $cookie ) {
			$html .= '<tr>';
			$html .= '<td><code>' . esc_html( $cookie->name ) . '</code></td>';
			$html .= '<td>' . esc_html( $cookie->provider ) . '</td>';
			$html .= '<td>' . esc_html( $cookie->description_da ) . '</td>';
			$html .= '<td>' . esc_html( $cookie->duration ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		return $html;
	}

	/**
	 * Henter kategori-baserede GDPR-beskrivelser (dansk).
	 *
	 * @return array Associativt array med kategori => beskrivelse.
	 */
	public static function get_category_descriptions() {
		return array(
			'necessary'  => __( 'Disse cookies er nødvendige for at websitet fungerer', 'cookiedk' ),
			'functional' => __( 'Disse cookies husker dine præferencer', 'cookiedk' ),
			'analytics'  => __( 'Disse cookies hjælper os med at forstå hvordan du bruger websitet', 'cookiedk' ),
			'marketing'  => __( 'Disse cookies bruges til at vise dig relevante annoncer', 'cookiedk' ),
		);
	}

	/**
	 * Henter kortfattede banner-tekster for kategorier.
	 *
	 * @return array
	 */
	public static function get_category_banner_texts() {
		return array(
			'necessary'  => __( 'Teknisk nødvendige cookies, som altid er aktive.', 'cookiedk' ),
			'functional' => __( 'Cookies der husker dine valg og præferencer.', 'cookiedk' ),
			'analytics'  => __( 'Anonyme statistik-cookies til forbedring af websitet.', 'cookiedk' ),
			'marketing'  => __( 'Cookies til personaliserede annoncer og markedsføring.', 'cookiedk' ),
		);
	}
}
