<?php
/**
 * Test-side partial for CookieDK admin.
 *
 * Giver mulighed for at forhåndsvise banneret, teste cookie-detektion
 * og nulstille samtykker i test-mode.
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

$site_url    = get_site_url();
$preview_url = $site_url;
$settings    = $this->get_settings();
?>
<h2><?php esc_html_e( 'Test', 'cookiedk' ); ?></h2>

<div class="cookiedk-notice"></div>

<!-- Handlinger -->
<div class="cookiedk-test-actions">
	<a
		href="<?php echo esc_url( $preview_url ); ?>"
		target="_blank"
		rel="noopener noreferrer"
		class="button button-primary"
	>
		<?php esc_html_e( '🔍 Forhåndsvis banner (ny fane)', 'cookiedk' ); ?>
	</a>

	<button type="button" class="button" id="cookiedk-reset-consent">
		<?php esc_html_e( '🔄 Nulstil samtykke (localStorage)', 'cookiedk' ); ?>
	</button>
</div>

<h3><?php esc_html_e( 'Banner-konfiguration', 'cookiedk' ); ?></h3>
<table class="cookiedk-form-table">
	<tr>
		<th><?php esc_html_e( 'Position', 'cookiedk' ); ?></th>
		<td><?php echo esc_html( $settings['banner_position'] ); ?></td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Farvetema', 'cookiedk' ); ?></th>
		<td><?php echo esc_html( $settings['color_theme'] ); ?></td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Cookiepolitik URL', 'cookiedk' ); ?></th>
		<td>
			<?php if ( $settings['cookie_policy_url'] ) : ?>
				<a href="<?php echo esc_url( $settings['cookie_policy_url'] ); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo esc_html( $settings['cookie_policy_url'] ); ?>
				</a>
			<?php else : ?>
				<em><?php esc_html_e( 'Ikke angivet', 'cookiedk' ); ?></em>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Samtykkefrist', 'cookiedk' ); ?></th>
		<td>
			<?php
			printf(
				/* translators: %d: Antal dage. */
				esc_html( _n( '%d dag', '%d dage', $settings['consent_expiry_days'], 'cookiedk' ) ),
				(int) $settings['consent_expiry_days']
			);
			?>
		</td>
	</tr>
</table>

<h3><?php esc_html_e( 'Cookie-detektion', 'cookiedk' ); ?></h3>
<p><?php esc_html_e( 'Detekterede cookies under din seneste session (server-side):', 'cookiedk' ); ?></p>

<?php
$storage = $this->get_storage();
$cookies = $storage->get_all_cookies();
?>
<?php if ( empty( $cookies ) ) : ?>
	<p><em><?php esc_html_e( 'Ingen cookies detekteret endnu. Besøg forsiden for at starte detektion.', 'cookiedk' ); ?></em></p>
<?php else : ?>
	<div class="cookiedk-table-wrap">
		<table class="cookiedk-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Navn', 'cookiedk' ); ?></th>
					<th><?php esc_html_e( 'Kategori', 'cookiedk' ); ?></th>
					<th><?php esc_html_e( 'Udbyder', 'cookiedk' ); ?></th>
					<th><?php esc_html_e( 'Detekteret', 'cookiedk' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cookies as $cookie ) : ?>
					<tr>
						<td><code><?php echo esc_html( $cookie->name ); ?></code></td>
						<td>
							<span class="cookiedk-badge cookiedk-badge--<?php echo esc_attr( $cookie->category ); ?>">
								<?php echo esc_html( $cookie->category ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $cookie->provider ); ?></td>
						<td><?php echo esc_html( $cookie->detected_at ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<h3><?php esc_html_e( 'Fejlfinding', 'cookiedk' ); ?></h3>
<p><?php esc_html_e( 'Trin til at teste banneret:', 'cookiedk' ); ?></p>
<ol>
	<li><?php esc_html_e( 'Klik "Nulstil samtykke" ovenfor for at rydde localStorage.', 'cookiedk' ); ?></li>
	<li><?php esc_html_e( 'Åbn forsiden i en privat browser-fane.', 'cookiedk' ); ?></li>
	<li><?php esc_html_e( 'Banneret bør nu vises nederst på siden.', 'cookiedk' ); ?></li>
	<li><?php esc_html_e( 'Klik "Accepter alle" og genindlæs siden — banneret bør forsvinde.', 'cookiedk' ); ?></li>
</ol>
