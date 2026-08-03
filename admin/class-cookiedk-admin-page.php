<?php
/**
 * Admin-side håndtering for CookieDK.
 *
 * Renderer alle admin-sider og håndterer AJAX-endpoints.
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Admin_Page.
 *
 * Renderer dashboard, cookies, indstillinger, samtykkelogning og test.
 */
class CookieDK_Admin_Page {


	/**
	 * Tilladte tabs.
	 *
	 * @var array
	 */
	private $tabs = array(
		'dashboard'   => 'Dashboard',
		'cookies'     => 'Cookies',
		'settings'    => 'Indstillinger',
		'consent-log' => 'Samtykker',
		'test'        => 'Test',
	);

	/**
	 * CookieDK_Cookie_Storage-instans.
	 *
	 * @var CookieDK_Cookie_Storage
	 */
	private $storage;

	/**
	 * Plugin-indstillinger.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Konstruktør.
	 */
	public function __construct() {
		$this->storage = new CookieDK_Cookie_Storage();
		$this->load_settings();
	}

	/**
	 * Indlæser aktuelle plugin-indstillinger fra databasen.
	 *
	 * @return void
	 */
	private function load_settings() {
		$stored = get_option( 'cookiedk_settings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$this->settings = $stored;
	}

	/**
	 * Registrerer AJAX-handlers.
	 *
	 * @return void
	 */
	public function register_ajax_handlers() {
		$actions = array(
			'cookiedk_update_cookie',
			'cookiedk_delete_cookie',
			'cookiedk_export_cookies',
			'cookiedk_save_settings',
			'cookiedk_create_policy_page',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'handle_ajax_' . str_replace( 'cookiedk_', '', $action ) ) );
		}
	}

	/**
	 * Renderer admin-siden med tab-navigation.
	 *
	 * @return void
	 */
	public function render() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! array_key_exists( $current_tab, $this->tabs ) ) {
			$current_tab = 'dashboard';
		}
		?>
		<div class="wrap cookiedk-admin">
			<h1 class="cookiedk-admin__heading">
				<span class="cookiedk-admin__logo">🍪</span>
		<?php esc_html_e( 'CookieDK', 'cookiedk' ); ?>
			</h1>

			<nav class="cookiedk-admin__tabs" aria-label="<?php esc_attr_e( 'CookieDK sektioner', 'cookiedk' ); ?>">
				<ul>
		<?php foreach ( $this->tabs as $slug => $label ) : ?>
						<li>
							<a
								href="<?php echo esc_url( admin_url( 'options-general.php?page=cookiedk&tab=' . $slug ) ); ?>"
								class="cookiedk-admin__tab <?php echo $current_tab === $slug ? 'active' : ''; ?>"
								<?php echo $current_tab === $slug ? 'aria-current="page"' : ''; ?>
							>
							<?php echo esc_html( $label ); ?>
							</a>
						</li>
		<?php endforeach; ?>
				</ul>
			</nav>

			<div class="cookiedk-admin__content">
		<?php $this->render_tab( $current_tab ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderer indholdet for den aktive tab.
	 *
	 * @param  string $tab Tab-slug.
	 * @return void
	 */
	private function render_tab( $tab ) {
		$partial = COOKIEDK_PLUGIN_DIR . 'admin/partials/' . $tab . '.php';
		if ( file_exists( $partial ) ) {
			include $partial;
		} else {
			echo '<p>' . esc_html__( 'Siden blev ikke fundet.', 'cookiedk' ) . '</p>';
		}
	}

	// =========================================================
	// AJAX-handlers
	// =========================================================

	/**
	 * AJAX: Opdater en cookie.
	 *
	 * @return void
	 */
	public function handle_ajax_update_cookie() {
		check_ajax_referer( 'cookiedk_admin_nonce', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_update_cookie', 40, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Utilstrækkelige rettigheder.', 'cookiedk' ) ) );
		}

		$id          = absint( isset( $_POST['id'] ) ? $_POST['id'] : 0 );
		$description = isset( $_POST['description_da'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description_da'] ) ) : '';
		$category    = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
		$duration    = isset( $_POST['duration'] ) ? sanitize_text_field( wp_unslash( $_POST['duration'] ) ) : '';
		$provider    = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldigt cookie-ID.', 'cookiedk' ) ) );
		}

		$result = $this->storage->update_cookie(
			$id,
			array(
				'description_da' => $description,
				'category'       => $category,
				'duration'       => $duration,
				'provider'       => $provider,
			)
		);

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Cookie opdateret.', 'cookiedk' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Cookie kunne ikke opdateres.', 'cookiedk' ) ) );
		}
	}

	/**
	 * AJAX: Slet en cookie.
	 *
	 * @return void
	 */
	public function handle_ajax_delete_cookie() {
		check_ajax_referer( 'cookiedk_admin_nonce', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_delete_cookie', 40, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Utilstrækkelige rettigheder.', 'cookiedk' ) ) );
		}

		$id = absint( isset( $_POST['id'] ) ? $_POST['id'] : 0 );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Ugyldigt cookie-ID.', 'cookiedk' ) ) );
		}

		$result = $this->storage->delete_cookie( $id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Cookie slettet.', 'cookiedk' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Cookie kunne ikke slettes.', 'cookiedk' ) ) );
		}
	}

	/**
	 * AJAX: Eksportér cookies som JSON.
	 *
	 * @return void
	 */
	public function handle_ajax_export_cookies() {
		check_ajax_referer( 'cookiedk_admin_nonce', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_export_cookies', 20, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Utilstrækkelige rettigheder.', 'cookiedk' ) ) );
		}

		$cookies = $this->storage->get_all_cookies();
		$export  = array();

		foreach ( $cookies as $cookie ) {
			$export[] = array(
				'name'           => $cookie->name,
				'category'       => $cookie->category,
				'description_da' => $cookie->description_da,
				'duration'       => $cookie->duration,
				'provider'       => $cookie->provider,
				'necessary'      => (bool) $cookie->necessary,
			);
		}

		wp_send_json_success( array( 'cookies' => $export ) );
	}

	/**
	 * AJAX: Opret cookiepolitik-side med standardformulering.
	 *
	 * @return void
	 */
	public function handle_ajax_create_policy_page() {
		check_ajax_referer( 'cookiedk_admin_nonce', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_create_policy_page', 10, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Utilstrækkelige rettigheder.', 'cookiedk' ) ) );
		}

		$owner = array(
			'name'    => isset( $_POST['policy_owner_name'] ) ? sanitize_text_field( wp_unslash( $_POST['policy_owner_name'] ) ) : '',
			'address' => isset( $_POST['policy_owner_address'] ) ? sanitize_text_field( wp_unslash( $_POST['policy_owner_address'] ) ) : '',
			'postal'  => isset( $_POST['policy_owner_postal'] ) ? sanitize_text_field( wp_unslash( $_POST['policy_owner_postal'] ) ) : '',
			'city'    => isset( $_POST['policy_owner_city'] ) ? sanitize_text_field( wp_unslash( $_POST['policy_owner_city'] ) ) : '',
			'cvr'     => isset( $_POST['policy_owner_cvr'] ) ? sanitize_text_field( wp_unslash( $_POST['policy_owner_cvr'] ) ) : '',
		);

		if ( '' === $owner['name'] || '' === $owner['address'] || '' === $owner['postal'] || '' === $owner['city'] ) {
			wp_send_json_error( array( 'message' => __( 'Udfyld ejer, adresse, postnr og by før du opretter siden.', 'cookiedk' ) ) );
		}

		$privacy = new CookieDK_Privacy_Policy();
		$result  = $privacy->create_or_update_policy_page( $owner );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$settings                          = $this->get_settings();
		$settings['cookie_policy_url']     = $result['url'];
		$settings['cookie_policy_page_id'] = $result['page_id'];
		$settings['policy_owner_name']     = $owner['name'];
		$settings['policy_owner_address']  = $owner['address'];
		$settings['policy_owner_postal']   = $owner['postal'];
		$settings['policy_owner_city']     = $owner['city'];
		$settings['policy_owner_cvr']      = $owner['cvr'];
		update_option( 'cookiedk_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success(
			array(
				'message'  => $result['created']
					? __( 'Cookiepolitik-side oprettet.', 'cookiedk' )
					: __( 'Cookiepolitik-side opdateret.', 'cookiedk' ),
				'url'      => $result['url'],
				'page_id'  => $result['page_id'],
				'edit_url' => $result['edit_url'],
			)
		);
	}

	/**
	 * AJAX: Gem indstillinger.
	 *
	 * @return void
	 */
	public function handle_ajax_save_settings() {
		check_ajax_referer( 'cookiedk_admin_nonce', 'nonce' );
		if ( class_exists( 'CookieDK_Security' ) && ! CookieDK_Security::check_rate_limit( 'cookiedk_save_settings', 20, 60 ) ) {
			status_header( 429 );
			wp_send_json_error( array( 'message' => __( 'For mange forespørgsler. Prøv igen senere.', 'cookiedk' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Utilstrækkelige rettigheder.', 'cookiedk' ) ) );
		}

		$settings = $this->sanitize_settings( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing – nonce checked above.

		update_option( 'cookiedk_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success( array( 'message' => __( 'Indstillinger gemt.', 'cookiedk' ) ) );
	}

	/**
	 * Behandler og gemmer indstillinger fra HTML-formular (POST).
	 *
	 * @return void
	 */
	public function handle_settings_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['cookiedk_settings_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['cookiedk_settings_nonce'] ), 'cookiedk_save_settings' )
		) {
			return;
		}

		$settings = $this->sanitize_settings( $_POST );
		update_option( 'cookiedk_settings', $settings );
		$this->settings = $settings;

		add_settings_error( 'cookiedk_settings', 'settings_saved', __( 'Indstillinger gemt.', 'cookiedk' ), 'updated' );
	}

	/**
	 * Behandler og gemmer en ny/redigeret cookie fra HTML-formular (POST).
	 *
	 * @return void
	 */
	public function handle_cookie_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['cookiedk_cookie_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['cookiedk_cookie_nonce'] ), 'cookiedk_save_cookie' )
		) {
			return;
		}

		$id = absint( isset( $_POST['cookie_id'] ) ? $_POST['cookie_id'] : 0 );

		$data = array(
			'name'           => sanitize_text_field( wp_unslash( isset( $_POST['cookie_name'] ) ? $_POST['cookie_name'] : '' ) ),
			'category'       => sanitize_key( wp_unslash( isset( $_POST['cookie_category'] ) ? $_POST['cookie_category'] : 'functional' ) ),
			'description_da' => sanitize_textarea_field( wp_unslash( isset( $_POST['cookie_description'] ) ? $_POST['cookie_description'] : '' ) ),
			'duration'       => sanitize_text_field( wp_unslash( isset( $_POST['cookie_duration'] ) ? $_POST['cookie_duration'] : '' ) ),
			'provider'       => sanitize_text_field( wp_unslash( isset( $_POST['cookie_provider'] ) ? $_POST['cookie_provider'] : '' ) ),
			'necessary'      => ! empty( $_POST['cookie_necessary'] ) ? 1 : 0,
			'source'         => 'manual',
		);

		if ( $id ) {
			$this->storage->update_cookie( $id, $data );
		} else {
			$this->storage->save_cookie( $data );
		}

		add_settings_error( 'cookiedk_cookies', 'cookie_saved', __( 'Cookie gemt.', 'cookiedk' ), 'updated' );
	}

	/**
	 * Saniterer indstillinger fra POST.
	 *
	 * @param  array $input Rå POST-data.
	 * @return array Saniteret indstillinger.
	 */
	private function sanitize_settings( array $input ) {
		$valid_positions = array(
			'bottom',
			'top',
			'side',
			'top-left',
			'top-right',
			'center',
			'bottom-left',
			'bottom-right',
		);
		$valid_themes    = array( 'light', 'dark', 'auto' );

		$position = isset( $input['banner_position'] ) ? sanitize_key( $input['banner_position'] ) : 'bottom';
		if ( ! in_array( $position, $valid_positions, true ) ) {
			$position = 'bottom';
		}

		$theme = isset( $input['color_theme'] ) ? sanitize_key( $input['color_theme'] ) : 'light';
		if ( ! in_array( $theme, $valid_themes, true ) ) {
			$theme = 'light';
		}

		$existing = $this->get_settings();

		return array(
			'banner_position'       => $position,
			'color_theme'           => $theme,
			'cookie_policy_url'     => isset( $input['cookie_policy_url'] ) ? esc_url_raw( wp_unslash( $input['cookie_policy_url'] ) ) : '',
			'cookie_policy_page_id' => isset( $input['cookie_policy_page_id'] ) ? absint( $input['cookie_policy_page_id'] ) : absint( $existing['cookie_policy_page_id'] ),
			'policy_owner_name'     => isset( $input['policy_owner_name'] ) ? sanitize_text_field( wp_unslash( $input['policy_owner_name'] ) ) : '',
			'policy_owner_address'  => isset( $input['policy_owner_address'] ) ? sanitize_text_field( wp_unslash( $input['policy_owner_address'] ) ) : '',
			'policy_owner_postal'   => isset( $input['policy_owner_postal'] ) ? sanitize_text_field( wp_unslash( $input['policy_owner_postal'] ) ) : '',
			'policy_owner_city'     => isset( $input['policy_owner_city'] ) ? sanitize_text_field( wp_unslash( $input['policy_owner_city'] ) ) : '',
			'policy_owner_cvr'      => isset( $input['policy_owner_cvr'] ) ? sanitize_text_field( wp_unslash( $input['policy_owner_cvr'] ) ) : '',
			'consent_expiry_days'   => isset( $input['consent_expiry_days'] ) ? absint( $input['consent_expiry_days'] ) : 365,
			'enable_analytics'      => ! empty( $input['enable_analytics'] ),
			'enable_marketing'      => ! empty( $input['enable_marketing'] ),
			'enable_functional'     => ! empty( $input['enable_functional'] ),
			'anonymize_ip'          => ! empty( $input['anonymize_ip'] ),
			'log_retention_days'    => isset( $input['log_retention_days'] ) ? absint( $input['log_retention_days'] ) : 365,
			'primary_color'         => isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '#2271b1',
			'secondary_color'       => isset( $input['secondary_color'] ) ? sanitize_hex_color( $input['secondary_color'] ) : '#135e96',
		);
	}

	/**
	 * Standardindstillinger for pluginet.
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'banner_position'       => 'bottom',
			'color_theme'           => 'light',
			'cookie_policy_url'     => '',
			'cookie_policy_page_id' => 0,
			'policy_owner_name'     => '',
			'policy_owner_address'  => '',
			'policy_owner_postal'   => '',
			'policy_owner_city'     => '',
			'policy_owner_cvr'      => '',
			'consent_expiry_days'   => 365,
			'enable_analytics'      => true,
			'enable_marketing'      => true,
			'enable_functional'     => true,
			'anonymize_ip'          => true,
			'log_retention_days'    => 365,
			'primary_color'         => '#2271b1',
			'secondary_color'       => '#135e96',
		);
	}

	/**
	 * Hjælper: Returnerer de aktuelle indstillinger med standardværdier.
	 *
	 * @return array
	 */
	public function get_settings() {
		$stored = is_array( $this->settings ) ? $this->settings : array();
		return wp_parse_args( $stored, $this->get_default_settings() );
	}

	/**
	 * Hjælper: Returnerer storage-instansen (bruges i partials).
	 *
	 * @return CookieDK_Cookie_Storage
	 */
	public function get_storage() {
		return $this->storage;
	}
}
