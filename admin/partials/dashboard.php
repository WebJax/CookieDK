<?php
/**
 * Dashboard partial for CookieDK admin.
 *
 * Viser statistik og overblik.
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

$storage = $this->get_storage();
$cookies = $storage->get_all_cookies();
$consent_log = $storage->get_consent_log( null, 1000 );

// Tæl cookies per kategori.
$counts = array(
	'necessary'  => 0,
	'functional' => 0,
	'analytics'  => 0,
	'marketing'  => 0,
);
foreach ( $cookies as $c ) {
	if ( isset( $counts[ $c->category ] ) ) {
		$counts[ $c->category ]++;
	}
}

$total_consents  = count( $consent_log );
$accepted_all    = 0;
$accepted_partial = 0;
foreach ( $consent_log as $log ) {
	$consent = json_decode( $log->consent_data, true );
	if ( is_array( $consent ) ) {
		$non_necessary = array_filter( $consent, function ( $v, $k ) {
			return 'necessary' !== $k && $v;
		}, ARRAY_FILTER_USE_BOTH );
		if ( count( $non_necessary ) >= 3 ) {
			$accepted_all++;
		} elseif ( count( $non_necessary ) > 0 ) {
			$accepted_partial++;
		}
	}
}
?>
<h2><?php esc_html_e( 'Dashboard', 'cookiedk' ); ?></h2>

<div class="cookiedk-stats">
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( count( $cookies ) ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Detekterede cookies', 'cookiedk' ); ?></span>
	</div>
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( $total_consents ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Samtykker logget', 'cookiedk' ); ?></span>
	</div>
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( $accepted_all ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Accepterede alle', 'cookiedk' ); ?></span>
	</div>
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( $accepted_partial ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Delvist accepteret', 'cookiedk' ); ?></span>
	</div>
</div>

<h3><?php esc_html_e( 'Cookies efter kategori', 'cookiedk' ); ?></h3>
<table class="cookiedk-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Kategori', 'cookiedk' ); ?></th>
			<th><?php esc_html_e( 'Antal cookies', 'cookiedk' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $counts as $cat => $count ) : ?>
			<tr>
				<td>
					<span class="cookiedk-badge cookiedk-badge--<?php echo esc_attr( $cat ); ?>">
						<?php echo esc_html( ucfirst( $cat ) ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $count ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p>
	<a href="<?php echo esc_url( admin_url( 'options-general.php?page=cookiedk&tab=cookies' ) ); ?>" class="button">
		<?php esc_html_e( 'Administrér cookies', 'cookiedk' ); ?>
	</a>
	<a href="<?php echo esc_url( admin_url( 'options-general.php?page=cookiedk&tab=consent-log' ) ); ?>" class="button" style="margin-left: 8px;">
		<?php esc_html_e( 'Se samtykkelogning', 'cookiedk' ); ?>
	</a>
</p>
