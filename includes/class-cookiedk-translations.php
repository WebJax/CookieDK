<?php
/**
 * Oversættelses-hjælpere for CookieDK.
 *
 * Denne klasse indeholder hjælpefunktioner til oversættelser,
 * dynamisk tekstgenerering og cookie-beskrivelser.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Translations.
 *
 * Centraliserer alle oversættelser og tekststrenge for pluginen.
 */
class CookieDK_Translations {


	/**
	 * Returnerer alle banner-tekster.
	 *
	 * @return array
	 */
	public static function get_banner_strings() {
		return array(
			'banner_heading'     => __( 'Vi bruger cookies', 'cookiedk' ),
			'banner_text'        => __( 'Vi bruger cookies til at forbedre din oplevelse på vores website. Vælg hvilke cookies du accepterer, eller læs mere i vores cookiepolitik.', 'cookiedk' ),
			'accept_all'         => __( 'Accepter alle', 'cookiedk' ),
			'accept_necessary'   => __( 'Kun nødvendige', 'cookiedk' ),
			'open_settings'      => __( 'Indstillinger', 'cookiedk' ),
			'cookie_policy_link' => __( 'Læs vores cookiepolitik', 'cookiedk' ),
			'consent_saved'      => __( 'Dine cookie-præferencer er gemt.', 'cookiedk' ),
			'consent_revoked'    => __( 'Dit samtykke er tilbagekaldt.', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer alle indstillingspanel-tekster.
	 *
	 * @return array
	 */
	public static function get_settings_panel_strings() {
		return array(
			'settings_heading' => __( 'Cookie-indstillinger', 'cookiedk' ),
			'settings_text'    => __( 'Her kan du vælge, hvilke kategorier af cookies du accepterer. Nødvendige cookies kan ikke deaktiveres.', 'cookiedk' ),
			'save_settings'    => __( 'Gem indstillinger', 'cookiedk' ),
			'close'            => __( 'Luk', 'cookiedk' ),
			'show_cookies'     => __( 'Vis cookies', 'cookiedk' ),
			'hide_cookies'     => __( 'Skjul cookies', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer kategori-navne (dansk).
	 *
	 * @return array
	 */
	public static function get_category_names() {
		return array(
			'necessary'  => __( 'Nødvendige', 'cookiedk' ),
			'functional' => __( 'Funktionelle', 'cookiedk' ),
			'analytics'  => __( 'Analyser', 'cookiedk' ),
			'marketing'  => __( 'Marketing', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer kategori-beskrivelser (dansk).
	 *
	 * @return array
	 */
	public static function get_category_descriptions() {
		return array(
			'necessary'  => __( 'Disse cookies er nødvendige for at websitet fungerer korrekt og kan ikke deaktiveres.', 'cookiedk' ),
			'functional' => __( 'Disse cookies husker dine præferencer og indstillinger for en bedre oplevelse.', 'cookiedk' ),
			'analytics'  => __( 'Disse cookies hjælper os med at forstå, hvordan du bruger websitet, så vi kan forbedre det.', 'cookiedk' ),
			'marketing'  => __( 'Disse cookies bruges til at vise dig relevante annoncer og måle effekten af markedsføring.', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer admin-interface-tekster.
	 *
	 * @return array
	 */
	public static function get_admin_strings() {
		return array(
			'plugin_name'      => __( 'CookieDK', 'cookiedk' ),
			'dashboard'        => __( 'Dashboard', 'cookiedk' ),
			'cookies'          => __( 'Cookies', 'cookiedk' ),
			'settings'         => __( 'Indstillinger', 'cookiedk' ),
			'consent_log'      => __( 'Samtykker', 'cookiedk' ),
			'test'             => __( 'Test', 'cookiedk' ),
			'save_changes'     => __( 'Gem ændringer', 'cookiedk' ),
			'delete'           => __( 'Slet', 'cookiedk' ),
			'edit'             => __( 'Rediger', 'cookiedk' ),
			'export'           => __( 'Eksportér', 'cookiedk' ),
			'add_cookie'       => __( 'Tilføj cookie', 'cookiedk' ),
			'cookie_name'      => __( 'Cookie-navn', 'cookiedk' ),
			'category'         => __( 'Kategori', 'cookiedk' ),
			'description'      => __( 'Beskrivelse', 'cookiedk' ),
			'duration'         => __( 'Levetid', 'cookiedk' ),
			'provider'         => __( 'Udbyder', 'cookiedk' ),
			'no_cookies_found' => __( 'Ingen cookies fundet.', 'cookiedk' ),
			'confirm_delete'   => __( 'Er du sikker på, at du vil slette denne cookie?', 'cookiedk' ),
			'changes_saved'    => __( 'Indstillinger gemt.', 'cookiedk' ),
			'error_saving'     => __( 'Der opstod en fejl. Prøv igen.', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer fejlmeddelelser (dansk).
	 *
	 * @return array
	 */
	public static function get_error_strings() {
		return array(
			'invalid_nonce'        => __( 'Sikkerhedstokenet er ugyldigt. Genindlæs siden og prøv igen.', 'cookiedk' ),
			'insufficient_caps'    => __( 'Du har ikke tilladelse til at udføre denne handling.', 'cookiedk' ),
			'invalid_consent_data' => __( 'Ugyldige samtykke-data.', 'cookiedk' ),
			'consent_save_failed'  => __( 'Samtykke kunne ikke gemmes. Prøv igen.', 'cookiedk' ),
			'consent_not_found'    => __( 'Samtykke ikke fundet.', 'cookiedk' ),
			'export_failed'        => __( 'Eksport mislykkedes. Prøv igen.', 'cookiedk' ),
			'delete_failed'        => __( 'Sletning mislykkedes. Prøv igen.', 'cookiedk' ),
			'invalid_email'        => __( 'Ugyldig e-mailadresse.', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer privacy policy-tekster (dansk).
	 *
	 * @return array
	 */
	public static function get_privacy_policy_strings() {
		return array(
			'heading'               => __( 'Cookiepolitik', 'cookiedk' ),
			'intro'                 => __( 'Vi bruger cookies til at forbedre din oplevelse. Denne side forklarer, hvilke cookies vi bruger og til hvad.', 'cookiedk' ),
			'what_are_cookies'      => __( 'Hvad er cookies?', 'cookiedk' ),
			'what_are_cookies_text' => __( 'Cookies er små tekstfiler, der gemmes på din enhed, når du besøger et website. De hjælper med at huske dine præferencer og indsamle anonyme statistikker.', 'cookiedk' ),
			'consent_heading'       => __( 'Samtykke', 'cookiedk' ),
			'consent_text'          => __( 'Første gang du besøger websitet, vises et cookie-banner, hvor du kan vælge kategorier. Du kan til enhver tid ændre dine valg.', 'cookiedk' ),
			'your_rights'           => __( 'Dine rettigheder', 'cookiedk' ),
			'right_access'          => __( 'Ret til indsigt – Du kan anmode om en kopi af dine data.', 'cookiedk' ),
			'right_erasure'         => __( 'Ret til sletning – Du kan anmode om sletning af dine samtykke-data.', 'cookiedk' ),
			'right_withdraw'        => __( 'Ret til at trække samtykke tilbage – Til enhver tid og uden begrundelse.', 'cookiedk' ),
			'contact_dpa'           => __( 'Du har ret til at klage til Datatilsynet på datatilsynet.dk.', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer tekster til data-export.
	 *
	 * @return array
	 */
	public static function get_data_export_strings() {
		return array(
			'exporter_name' => __( 'CookieDK Samtykkedata', 'cookiedk' ),
			'group_label'   => __( 'CookieDK Samtykker', 'cookiedk' ),
			'timestamp'     => __( 'Tidspunkt', 'cookiedk' ),
			'consent'       => __( 'Samtykke', 'cookiedk' ),
			'ip_address'    => __( 'IP-adresse', 'cookiedk' ),
			'browser'       => __( 'Browser', 'cookiedk' ),
			'anonymized'    => __( '[anonymiseret]', 'cookiedk' ),
			'yes'           => __( 'Ja', 'cookiedk' ),
			'no'            => __( 'Nej', 'cookiedk' ),
			'data_deleted'  => __( 'CookieDK samtykke-data slettet.', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer tekster til samtykke-sletning (RTBF).
	 *
	 * @return array
	 */
	public static function get_deletion_strings() {
		return array(
			'eraser_name'    => __( 'CookieDK Samtykkedata', 'cookiedk' ),
			'confirm_delete' => __( 'Er du sikker på, at du vil slette alle dine samtykke-data?', 'cookiedk' ),
			'delete_success' => __( 'Dine samtykke-data er blevet slettet.', 'cookiedk' ),
			'delete_failed'  => __( 'Der opstod en fejl ved sletning. Prøv igen.', 'cookiedk' ),
			'no_data_found'  => __( 'Ingen samtykke-data fundet for denne bruger.', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer danske beskrivelser for kendte cookies.
	 *
	 * Kan bruges til at tilsidesætte eller supplere auto-detekterede beskrivelser.
	 *
	 * @return array Cookie-navn => beskrivelse.
	 */
	public static function get_cookie_descriptions() {
		return array(
			// WordPress.
			'wordpress_*'               => __( 'WordPress sessions-cookie til at holde dig logget ind.', 'cookiedk' ),
			'wordpress_logged_in_*'     => __( 'WordPress login-cookie til at bekræfte din identitet.', 'cookiedk' ),
			'wp-settings-*'             => __( 'Gemmer dine personlige præferencer i WordPress admin.', 'cookiedk' ),
			'wordpress_test_cookie'     => __( 'Tester om din browser understøtter cookies.', 'cookiedk' ),
			'comment_author_*'          => __( 'Gemmer dit navn og e-mail til fremtidige kommentarer.', 'cookiedk' ),

			// WooCommerce.
			'woocommerce_cart_hash'     => __( 'Hjælper WooCommerce med at huske indholdet af din indkøbskurv.', 'cookiedk' ),
			'woocommerce_items_in_cart' => __( 'Holder styr på om du har varer i din indkøbskurv.', 'cookiedk' ),
			'wc_session_cookie'         => __( 'Gemmer din WooCommerce-session og indkøbskurv.', 'cookiedk' ),

			// Google Analytics.
			'_ga'                       => __( 'Google Analytics bruger-ID til at skelne besøgende fra hinanden.', 'cookiedk' ),
			'_gid'                      => __( 'Google Analytics cookie til at skelne brugere. Udløber efter 24 timer.', 'cookiedk' ),
			'_gat'                      => __( 'Google Analytics cookie til at begrænse antallet af forespørgsler.', 'cookiedk' ),
			'_ga_*'                     => __( 'Google Analytics 4 cookie til at gemme og tælle sidevisninger.', 'cookiedk' ),

			// Google Ads.
			'_gcl_au'                   => __( 'Google Ads cookie til at måle konverteringer fra annoncer.', 'cookiedk' ),
			'IDE'                       => __( 'Google DoubleClick cookie til at vise personlige annoncer.', 'cookiedk' ),

			// Facebook.
			'_fbp'                      => __( 'Facebook Pixel cookie til at spore besøg og vise relevante annoncer.', 'cookiedk' ),
			'_fbc'                      => __( 'Facebook click ID cookie til at spore annoncekonverteringer.', 'cookiedk' ),

			// Hotjar.
			'_hjid'                     => __( 'Hotjar bruger-ID cookie til at analysere brugeradfærd på siden.', 'cookiedk' ),

			// Matomo.
			'_pk_id*'                   => __( 'Matomo cookie til at identificere unikke besøgende.', 'cookiedk' ),
			'_pk_ses*'                  => __( 'Matomo session-cookie til at spore den aktuelle besøgs-session.', 'cookiedk' ),

			// Cloudflare.
			'__cfduid'                  => __( 'Cloudflare sikkerhedscookie til at beskytte mod ondsindede angreb.', 'cookiedk' ),
			'cf_clearance'              => __( 'Cloudflare cookie til at bevise, at besøgende har bestået en sikkerhedskontrol.', 'cookiedk' ),

			// CookieDK.
			'cookiedk_consent'          => __( 'Gemmer dine cookie-præferencer for dette website.', 'cookiedk' ),

			// PHP.
			'PHPSESSID'                 => __( 'PHP sessions-cookie til at holde din session aktiv under dit besøg.', 'cookiedk' ),
		);
	}

	/**
	 * Returnerer en enkelt oversat tekst baseret på nøgle og kontekst.
	 *
	 * @param  string $key     Tekstnøgle.
	 * @param  string $context Kontekst ('banner', 'admin', 'error', 'privacy', 'export').
	 * @return string Oversat tekst eller tom streng.
	 */
	public static function get( $key, $context = 'banner' ) {
		$map = array(
			'banner'   => array( 'CookieDK_Translations', 'get_banner_strings' ),
			'admin'    => array( 'CookieDK_Translations', 'get_admin_strings' ),
			'error'    => array( 'CookieDK_Translations', 'get_error_strings' ),
			'privacy'  => array( 'CookieDK_Translations', 'get_privacy_policy_strings' ),
			'export'   => array( 'CookieDK_Translations', 'get_data_export_strings' ),
			'deletion' => array( 'CookieDK_Translations', 'get_deletion_strings' ),
			'settings' => array( 'CookieDK_Translations', 'get_settings_panel_strings' ),
		);

		if ( ! isset( $map[ $context ] ) ) {
			return '';
		}

		$strings = call_user_func( $map[ $context ] );

		return isset( $strings[ $key ] ) ? $strings[ $key ] : '';
	}
}
