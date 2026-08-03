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
	if ( ! wp_verify_nonce( wp_unslash( $_POST['cookiedk_settings_nonce'] ), 'cookiedk_save_settings' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		wp_die( esc_html__( 'Ugyldig nonce. Indstillingerne blev ikke gemt.', 'cookiedk' ) );
	}
	$this->handle_settings_form();
}

settings_errors( 'cookiedk_settings' );

$settings = $this->get_settings();
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
					<option value="bottom" <?php selected( $settings['banner_position'], 'bottom' ); ?>><?php esc_html_e( 'Bund (standard)', 'cookiedk' ); ?></option>
					<option value="top" <?php selected( $settings['banner_position'], 'top' ); ?>><?php esc_html_e( 'Top', 'cookiedk' ); ?></option>
					<option value="side" <?php selected( $settings['banner_position'], 'side' ); ?>><?php esc_html_e( 'Side (højre hjørne)', 'cookiedk' ); ?></option>
					<option value="top-left" <?php selected( $settings['banner_position'], 'top-left' ); ?>><?php esc_html_e( 'Venstre top', 'cookiedk' ); ?></option>
					<option value="top-right" <?php selected( $settings['banner_position'], 'top-right' ); ?>><?php esc_html_e( 'Højre top', 'cookiedk' ); ?></option>
					<option value="center" <?php selected( $settings['banner_position'], 'center' ); ?>><?php esc_html_e( 'Centreret på skærmen', 'cookiedk' ); ?></option>
					<option value="bottom-left" <?php selected( $settings['banner_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Venstre bund', 'cookiedk' ); ?></option>
					<option value="bottom-right" <?php selected( $settings['banner_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Højre bund', 'cookiedk' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Hjørne- og centerpositioner fylder ca. 1/3 af skærmbredden på PC. På mobil vises banneret i bunden i fuld bredde.', 'cookiedk' ); ?></p>
			</td>
		</tr>

		<!-- Farvetema -->
		<tr>
			<th scope="row">
				<label for="color_theme"><?php esc_html_e( 'Farvetema', 'cookiedk' ); ?></label>
			</th>
			<td>
				<select name="color_theme" id="color_theme">
					<option value="light" <?php selected( $settings['color_theme'], 'light' ); ?>><?php esc_html_e( 'Lyst', 'cookiedk' ); ?></option>
					<option value="dark"  <?php selected( $settings['color_theme'], 'dark' ); ?>><?php esc_html_e( 'Mørkt', 'cookiedk' ); ?></option>
					<option value="auto"  <?php selected( $settings['color_theme'], 'auto' ); ?>><?php esc_html_e( 'Automatisk (følger system)', 'cookiedk' ); ?></option>
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
					value="<?php echo esc_attr( $settings['primary_color'] ); ?>"
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
					value="<?php echo esc_attr( $settings['secondary_color'] ); ?>"
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
					value="<?php echo esc_attr( $settings['cookie_policy_url'] ); ?>"
					class="regular-text"
					placeholder="https://eksempel.dk/cookiepolitik"
				>
				<p class="description"><?php esc_html_e( 'Link til din cookiepolitik-side (vises i banneret).', 'cookiedk' ); ?></p>

				<div class="cookiedk-policy-builder" id="cookiedk-policy-builder">
					<p class="cookiedk-policy-builder__intro">
						<?php esc_html_e( 'Udfyld oplysninger om ejeren af hjemmesiden for at oprette en cookiepolitik-side med standardformulering.', 'cookiedk' ); ?>
					</p>

					<div class="cookiedk-policy-builder__fields">
						<p>
							<label for="policy_owner_name">
								<?php esc_html_e( 'Ejer af hjemmesiden', 'cookiedk' ); ?>
								<span class="required">*</span>
							</label>
							<input
								type="text"
								name="policy_owner_name"
								id="policy_owner_name"
								value="<?php echo esc_attr( $settings['policy_owner_name'] ); ?>"
								class="regular-text cookiedk-policy-required"
								placeholder="<?php esc_attr_e( 'Virksomhed / forening / navn', 'cookiedk' ); ?>"
								autocomplete="organization"
							>
						</p>
						<p>
							<label for="policy_owner_address">
								<?php esc_html_e( 'Adresse', 'cookiedk' ); ?>
								<span class="required">*</span>
							</label>
							<input
								type="text"
								name="policy_owner_address"
								id="policy_owner_address"
								value="<?php echo esc_attr( $settings['policy_owner_address'] ); ?>"
								class="regular-text cookiedk-policy-required"
								autocomplete="street-address"
							>
						</p>
						<p class="cookiedk-policy-builder__row">
							<span>
								<label for="policy_owner_postal">
									<?php esc_html_e( 'Postnr', 'cookiedk' ); ?>
									<span class="required">*</span>
								</label>
								<input
									type="text"
									name="policy_owner_postal"
									id="policy_owner_postal"
									value="<?php echo esc_attr( $settings['policy_owner_postal'] ); ?>"
									class="small-text cookiedk-policy-required"
									inputmode="numeric"
									autocomplete="postal-code"
								>
							</span>
							<span>
								<label for="policy_owner_city">
									<?php esc_html_e( 'By', 'cookiedk' ); ?>
									<span class="required">*</span>
								</label>
								<input
									type="text"
									name="policy_owner_city"
									id="policy_owner_city"
									value="<?php echo esc_attr( $settings['policy_owner_city'] ); ?>"
									class="regular-text cookiedk-policy-required"
									autocomplete="address-level2"
								>
							</span>
						</p>
						<p>
							<label for="policy_owner_cvr"><?php esc_html_e( 'CVR-nr. (valgfrit)', 'cookiedk' ); ?></label>
							<input
								type="text"
								name="policy_owner_cvr"
								id="policy_owner_cvr"
								value="<?php echo esc_attr( $settings['policy_owner_cvr'] ); ?>"
								class="regular-text"
								inputmode="numeric"
								placeholder="12345678"
							>
						</p>
					</div>

					<p class="cookiedk-policy-builder__actions">
						<button
							type="button"
							class="button button-secondary"
							id="cookiedk-create-policy-page"
							disabled
						>
							<?php esc_html_e( 'Opret cookiepolitik-side', 'cookiedk' ); ?>
						</button>
						<span class="description" id="cookiedk-create-policy-hint">
							<?php esc_html_e( 'Knappen aktiveres, når ejer, adresse, postnr og by er udfyldt.', 'cookiedk' ); ?>
						</span>
					</p>
				</div>
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
					value="<?php echo esc_attr( $settings['consent_expiry_days'] ); ?>"
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
							<?php checked( $settings['enable_functional'] ); ?>
						>
						<?php esc_html_e( 'Funktionelle cookies', 'cookiedk' ); ?>
					</label><br>
					<label>
						<input
							type="checkbox"
							name="enable_analytics"
							value="1"
							<?php checked( $settings['enable_analytics'] ); ?>
						>
						<?php esc_html_e( 'Analytiske cookies', 'cookiedk' ); ?>
					</label><br>
					<label>
						<input
							type="checkbox"
							name="enable_marketing"
							value="1"
							<?php checked( $settings['enable_marketing'] ); ?>
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
						<?php checked( $settings['anonymize_ip'] ); ?>
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
					value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>"
					min="30"
					max="3650"
					class="small-text"
				>
				<p class="description"><?php esc_html_e( 'Antal dage samtykkeloggen bevares. Anbefalede minimum er 365 dage.', 'cookiedk' ); ?></p>
			</td>
		</tr>

	</table>

	<p class="submit">
		<button type="submit" class="button button-primary" id="cookiedk-save-settings-ajax">
			<?php esc_html_e( 'Gem indstillinger', 'cookiedk' ); ?>
		</button>
	</p>
</form>
