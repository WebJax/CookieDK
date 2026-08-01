<?php
/**
 * Indstillinger partial for CookieDK admin.
 *
 * Viser og behandler plugin-indstillingerne.
 *
 * @package CookieDK
 * @since   1.0.0
 *
 * @var CookieDK_Admin_Page $this Admin-side-instans.
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Behandl formular-indsendelse.
if ( isset( $_POST['cookiedk_settings_nonce'] ) ) {
	$this->handle_settings_form();
}

settings_errors( 'cookiedk_settings' );

$s = $this->get_settings();
?>

<h2><?php esc_html_e( 'Indstillinger', 'cookiedk' ); ?></h2>

<form method="post" action="" id="cookiedk-settings-form">
	<?php wp_nonce_field( 'cookiedk_save_settings', 'cookiedk_settings_nonce' ); ?>

	<table class="cookiedk-form-table">

		<!-- Banner-position -->
		<tr>
			<th scope="row">
				<label for="banner_position"><?php esc_html_e( 'Banner-position', 'cookiedk' ); ?></label>
			</th>
			<td>
				<select name="banner_position" id="banner_position">
					<option value="bottom" <?php selected( $s['banner_position'], 'bottom' ); ?>><?php esc_html_e( 'Bund (standard)', 'cookiedk' ); ?></option>
					<option value="top"    <?php selected( $s['banner_position'], 'top' ); ?>><?php esc_html_e( 'Top', 'cookiedk' ); ?></option>
					<option value="side"   <?php selected( $s['banner_position'], 'side' ); ?>><?php esc_html_e( 'Side (højre hjørne)', 'cookiedk' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Angiv, hvor cookie-banneret vises på siden.', 'cookiedk' ); ?></p>
			</td>
		</tr>

		<!-- Farvetema -->
		<tr>
			<th scope="row">
				<label for="color_theme"><?php esc_html_e( 'Farvetema', 'cookiedk' ); ?></label>
			</th>
			<td>
				<select name="color_theme" id="color_theme">
					<option value="light" <?php selected( $s['color_theme'], 'light' ); ?>><?php esc_html_e( 'Lyst', 'cookiedk' ); ?></option>
					<option value="dark"  <?php selected( $s['color_theme'], 'dark' ); ?>><?php esc_html_e( 'Mørkt', 'cookiedk' ); ?></option>
					<option value="auto"  <?php selected( $s['color_theme'], 'auto' ); ?>><?php esc_html_e( 'Automatisk (følger system)', 'cookiedk' ); ?></option>
				</select>
			</td>
		</tr>

		<!-- Primær farve -->
		<tr>
			<th scope="row">
				<label for="primary_color"><?php esc_html_e( 'Primær farve', 'cookiedk' ); ?></label>
			</th>
			<td>
				<input
					type="color"
					name="primary_color"
					id="primary_color"
					value="<?php echo esc_attr( $s['primary_color'] ); ?>"
				>
				<p class="description"><?php esc_html_e( 'Baggrundsfarve for "Accepter alle"-knappen.', 'cookiedk' ); ?></p>
			</td>
		</tr>

		<!-- Sekundær farve -->
		<tr>
			<th scope="row">
				<label for="secondary_color"><?php esc_html_e( 'Sekundær farve', 'cookiedk' ); ?></label>
			</th>
			<td>
				<input
					type="color"
					name="secondary_color"
					id="secondary_color"
					value="<?php echo esc_attr( $s['secondary_color'] ); ?>"
				>
				<p class="description"><?php esc_html_e( 'Hover-farve for knapper.', 'cookiedk' ); ?></p>
			</td>
		</tr>

		<!-- Cookiepolitik-link -->
		<tr>
			<th scope="row">
				<label for="cookie_policy_url"><?php esc_html_e( 'Cookiepolitik URL', 'cookiedk' ); ?></label>
			</th>
			<td>
				<input
					type="url"
					name="cookie_policy_url"
					id="cookie_policy_url"
					value="<?php echo esc_attr( $s['cookie_policy_url'] ); ?>"
					class="regular-text"
					placeholder="https://eksempel.dk/cookiepolitik"
				>
				<p class="description"><?php esc_html_e( 'Link til din cookiepolitik-side (vises i banneret).', 'cookiedk' ); ?></p>
			</td>
		</tr>

		<!-- Samtykkefrist -->
		<tr>
			<th scope="row">
				<label for="consent_expiry_days"><?php esc_html_e( 'Samtykkefrist (dage)', 'cookiedk' ); ?></label>
			</th>
			<td>
				<input
					type="number"
					name="consent_expiry_days"
					id="consent_expiry_days"
					value="<?php echo esc_attr( $s['consent_expiry_days'] ); ?>"
					min="1"
					max="730"
					class="small-text"
				>
				<p class="description"><?php esc_html_e( 'Antal dage et samtykke er gyldigt (maks. 730). GDPR anbefaler 365 dage.', 'cookiedk' ); ?></p>
			</td>
		</tr>

		<!-- Aktive kategorier -->
		<tr>
			<th scope="row"><?php esc_html_e( 'Aktive kategorier', 'cookiedk' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Aktive cookie-kategorier', 'cookiedk' ); ?></legend>
					<label>
						<input type="checkbox" disabled checked>
						<?php esc_html_e( 'Nødvendige (altid aktiv)', 'cookiedk' ); ?>
					</label><br>
					<label>
						<input
							type="checkbox"
							name="enable_functional"
							value="1"
							<?php checked( $s['enable_functional'] ); ?>
						>
						<?php esc_html_e( 'Funktionelle cookies', 'cookiedk' ); ?>
					</label><br>
					<label>
						<input
							type="checkbox"
							name="enable_analytics"
							value="1"
							<?php checked( $s['enable_analytics'] ); ?>
						>
						<?php esc_html_e( 'Analytiske cookies', 'cookiedk' ); ?>
					</label><br>
					<label>
						<input
							type="checkbox"
							name="enable_marketing"
							value="1"
							<?php checked( $s['enable_marketing'] ); ?>
						>
						<?php esc_html_e( 'Marketing cookies', 'cookiedk' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<!-- IP-anonymisering -->
		<tr>
			<th scope="row"><?php esc_html_e( 'GDPR: IP-anonymisering', 'cookiedk' ); ?></th>
			<td>
				<label>
					<input
						type="checkbox"
						name="anonymize_ip"
						value="1"
						<?php checked( $s['anonymize_ip'] ); ?>
					>
					<?php esc_html_e( 'Anonymisér IP-adresser efter 30 dage', 'cookiedk' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Anbefales for GDPR-overholdelse.', 'cookiedk' ); ?></p>
			</td>
		</tr>

		<!-- Logopbevaringsperiode -->
		<tr>
			<th scope="row">
				<label for="log_retention_days"><?php esc_html_e( 'Logopbevaring (dage)', 'cookiedk' ); ?></label>
			</th>
			<td>
				<input
					type="number"
					name="log_retention_days"
					id="log_retention_days"
					value="<?php echo esc_attr( $s['log_retention_days'] ); ?>"
					min="30"
					max="3650"
					class="small-text"
				>
				<p class="description"><?php esc_html_e( 'Antal dage samtykkeloggen bevares. Anbefalede minimum er 365 dage.', 'cookiedk' ); ?></p>
			</td>
		</tr>

	</table>

	<p class="submit">
		<button type="submit" class="button button-primary">
			<?php esc_html_e( 'Gem indstillinger', 'cookiedk' ); ?>
		</button>
	</p>
</form>
