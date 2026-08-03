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

$storage     = $this->get_storage();
$cookies     = $storage->get_all_cookies();
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
		++$counts[ $c->category ];
	}
}

$total_consents     = count( $consent_log );
$accepted_all       = 0;
$accepted_partial   = 0;
$accepted_necessary = 0;
$categories         = CookieDK_Cookie_Detector::get_categories();
$recent_consents    = array_slice( $consent_log, 0, 10 );

foreach ( $consent_log as $log ) {
	$consent = json_decode( $log->consent_data, true );
	if ( ! is_array( $consent ) ) {
		continue;
	}

	$non_necessary = array_filter(
		$consent,
		function ( $v, $k ) {
			return 'necessary' !== $k && ! empty( $v );
		},
		ARRAY_FILTER_USE_BOTH
	);

	if ( count( $non_necessary ) >= 3 ) {
		++$accepted_all;
	} elseif ( count( $non_necessary ) > 0 ) {
		++$accepted_partial;
	} else {
		++$accepted_necessary;
	}
}
?>
<h2><?php esc_html_e( 'Dashboard', 'cookiedk' ); ?></h2>

<div class="cookiedk-stats">
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( (string) count( $cookies ) ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Detekterede cookies', 'cookiedk' ); ?></span>
	</div>
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( (string) $total_consents ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Samtykker logget', 'cookiedk' ); ?></span>
	</div>
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( (string) $accepted_all ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Accepterede alle', 'cookiedk' ); ?></span>
	</div>
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( (string) $accepted_partial ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Delvist accepteret', 'cookiedk' ); ?></span>
	</div>
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( (string) $accepted_necessary ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Kun nødvendige', 'cookiedk' ); ?></span>
	</div>
</div>

<h3><?php esc_html_e( 'Seneste samtykker', 'cookiedk' ); ?></h3>
<div class="cookiedk-table-wrap">
	<table class="cookiedk-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Tidspunkt', 'cookiedk' ); ?></th>
				<th><?php esc_html_e( 'Fingerprint', 'cookiedk' ); ?></th>
				<?php foreach ( $categories as $cat_label ) : ?>
					<th><?php echo esc_html( $cat_label ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $recent_consents ) ) : ?>
				<tr>
					<td colspan="<?php echo esc_attr( (string) ( 2 + count( $categories ) ) ); ?>">
						<em><?php esc_html_e( 'Ingen samtykker logget endnu.', 'cookiedk' ); ?></em>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $recent_consents as $entry ) : ?>
					<?php $consent = json_decode( $entry->consent_data, true ); ?>
					<tr>
						<td><?php echo esc_html( $entry->consent_timestamp ); ?></td>
						<td>
							<code title="<?php echo esc_attr( $entry->user_fingerprint ); ?>">
								<?php echo esc_html( substr( $entry->user_fingerprint, 0, 8 ) ); ?>…
							</code>
						</td>
						<?php foreach ( $categories as $cat_slug => $cat_label ) : ?>
							<td>
								<?php if ( is_array( $consent ) && array_key_exists( $cat_slug, $consent ) ) : ?>
									<?php if ( ! empty( $consent[ $cat_slug ] ) ) : ?>
										<span style="color: #00a32a;" aria-label="<?php esc_attr_e( 'Ja', 'cookiedk' ); ?>">✓</span>
									<?php else : ?>
										<span style="color: #d63638;" aria-label="<?php esc_attr_e( 'Nej', 'cookiedk' ); ?>">✗</span>
									<?php endif; ?>
								<?php else : ?>
									<span style="color: #646970;">—</span>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
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
		<?php foreach ( $counts as $category => $count ) : ?>
			<tr>
				<td>
					<span class="cookiedk-badge cookiedk-badge--<?php echo esc_attr( $category ); ?>">
			<?php echo esc_html( ucfirst( $category ) ); ?>
					</span>
				</td>
				<td><?php echo esc_html( (string) $count ); ?></td>
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
