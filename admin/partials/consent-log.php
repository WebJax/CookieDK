<?php
/**
 * Samtykkelogning partial for CookieDK admin.
 *
 * Viser log over brugernes samtykker med filtrering og eksport.
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

// Filtrer på dato.
$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$log = $storage->get_consent_log( null, 500 );

// Filtrér lokalt efter dato.
if ( $date_from || $date_to ) {
	$log = array_filter(
		$log,
		function ( $entry ) use ( $date_from, $date_to ) {
			$ts = strtotime( $entry->consent_timestamp );
			if ( $date_from && $ts < strtotime( $date_from ) ) {
				return false;
			}
			if ( $date_to && $ts > strtotime( $date_to . ' 23:59:59' ) ) {
				return false;
			}
			return true;
		}
	);
}

// Statistik.
$total        = count( $log );
$accepted_all = 0;
foreach ( $log as $entry ) {
	$c = json_decode( $entry->consent_data, true );
	if ( is_array( $c ) ) {
		$all = ! empty( $c['functional'] ) && ! empty( $c['analytics'] ) && ! empty( $c['marketing'] );
		if ( $all ) {
			++$accepted_all;
		}
	}
}

$categories = CookieDK_Cookie_Detector::get_categories();
?>
<h2><?php esc_html_e( 'Samtykkelogning', 'cookiedk' ); ?></h2>

<div class="cookiedk-stats" style="margin-bottom: 20px;">
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( (string) $total ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Samtykker (filtreret)', 'cookiedk' ); ?></span>
	</div>
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo esc_html( (string) $accepted_all ); ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Accepterede alle', 'cookiedk' ); ?></span>
	</div>
	<div class="cookiedk-stat-card">
		<span class="cookiedk-stat-card__value"><?php echo $total > 0 ? esc_html( (string) round( $accepted_all / $total * 100 ) ) . '%' : '0%'; ?></span>
		<span class="cookiedk-stat-card__label"><?php esc_html_e( 'Accepteringsrate', 'cookiedk' ); ?></span>
	</div>
</div>

<!-- Filterbar -->
<form method="get" action="">
	<input type="hidden" name="page" value="cookiedk">
	<input type="hidden" name="tab" value="consent-log">
	<div class="cookiedk-filter-bar">
		<div>
			<label for="date_from"><?php esc_html_e( 'Fra dato', 'cookiedk' ); ?></label>
			<input
				type="date"
				name="date_from"
				id="date_from"
				value="<?php echo esc_attr( $date_from ); ?>"
				class="cookiedk-datepicker"
			>
		</div>
		<div>
			<label for="date_to"><?php esc_html_e( 'Til dato', 'cookiedk' ); ?></label>
			<input
				type="date"
				name="date_to"
				id="date_to"
				value="<?php echo esc_attr( $date_to ); ?>"
				class="cookiedk-datepicker"
			>
		</div>
		<div style="display: flex; align-items: flex-end; gap: 8px;">
			<button type="submit" class="button"><?php esc_html_e( 'Filtrer', 'cookiedk' ); ?></button>
			<?php if ( $date_from || $date_to ) : ?>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=cookiedk&tab=consent-log' ) ); ?>" class="button">
				<?php esc_html_e( 'Nulstil filter', 'cookiedk' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</form>

<div class="cookiedk-table-wrap">
	<table class="cookiedk-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Tidspunkt', 'cookiedk' ); ?></th>
				<th><?php esc_html_e( 'Fingerprint', 'cookiedk' ); ?></th>
				<?php foreach ( $categories as $cat_slug => $cat_label ) : ?>
					<th><?php echo esc_html( $cat_label ); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $log ) ) : ?>
				<tr>
					<td colspan="<?php echo esc_attr( (string) ( 2 + count( $categories ) ) ); ?>">
						<em><?php esc_html_e( 'Ingen samtykker fundet.', 'cookiedk' ); ?></em>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $log as $entry ) : ?>
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
						<?php if ( is_array( $consent ) && isset( $consent[ $cat_slug ] ) ) : ?>
							<?php if ( $consent[ $cat_slug ] ) : ?>
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

<p class="description">
	<?php
	printf(
	/* translators: %d: Antal poster. */
		esc_html__( 'Viser op til %d samtykker. Brug datofilter til at indsnævre søgningen.', 'cookiedk' ),
		500
	);
	?>
</p>
