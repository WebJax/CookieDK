<?php
/**
 * Cookie-management partial for CookieDK admin.
 *
 * Viser tabel med detekterede cookies og mulighed for at tilføje/redigere/slette.
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
if ( isset( $_POST['cookiedk_cookie_nonce'] ) ) {
	if ( ! wp_verify_nonce( wp_unslash( $_POST['cookiedk_cookie_nonce'] ), 'cookiedk_save_cookie' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		wp_die( esc_html__( 'Ugyldig nonce. Cookie blev ikke gemt.', 'cookiedk' ) );
	}
	$this->handle_cookie_form();
}

settings_errors( 'cookiedk_cookies' );

$storage       = $this->get_storage();
$cookies       = $storage->get_all_cookies();
$categories    = CookieDK_Cookie_Detector::get_categories();
$origin_filter = isset( $_GET['origin'] ) ? sanitize_key( wp_unslash( $_GET['origin'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$origin_labels = array(
	'catalog'  => __( 'Katalog', 'cookiedk' ),
	'wildcard' => __( 'Katalog', 'cookiedk' ),
	'ai'       => __( 'AI', 'cookiedk' ),
	'manual'   => __( 'Manuel', 'cookiedk' ),
	'unknown'  => __( 'Ukendt', 'cookiedk' ),
);
$sync          = new CookieDK_Catalog_Sync();
$meta          = $sync->get_meta();
$describer     = new CookieDK_Cookie_Describer();
$ai_ready      = $describer->is_ai_available();

if ( '' !== $origin_filter ) {
	$cookies = array_values(
		array_filter(
			$cookies,
			static function ( $cookie ) use ( $origin_filter ) {
				$origin = isset( $cookie->origin ) ? (string) $cookie->origin : 'unknown';
				if ( 'catalog' === $origin_filter ) {
					return in_array( $origin, array( 'catalog', 'wildcard' ), true );
				}
				return $origin === $origin_filter;
			}
		)
	);
}
?>

<div class="cookiedk-notice"></div>

<h2><?php esc_html_e( 'Cookie-administration', 'cookiedk' ); ?></h2>

<div class="cookiedk-actions">
	<button type="button" class="button button-primary" id="cookiedk-add-cookie-btn">
		<?php esc_html_e( '+ Tilføj cookie', 'cookiedk' ); ?>
	</button>
	<button type="button" class="button" id="cookiedk-sync-catalog">
		<?php esc_html_e( 'Opdater katalog nu', 'cookiedk' ); ?>
	</button>
	<button type="button" class="button" id="cookiedk-export-cookies">
		<?php esc_html_e( 'Eksportér cookies (JSON)', 'cookiedk' ); ?>
	</button>
	<button type="button" class="button" id="cookiedk-export-catalog-cookies">
		<?php esc_html_e( 'Eksportér lokale cookies (katalog-format)', 'cookiedk' ); ?>
	</button>
</div>

<p class="description" id="cookiedk-catalog-sync-status">
	<?php if ( ! empty( $meta['synced_at'] ) ) : ?>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: Datetime. */
				__( 'Sidste katalog-sync: %s', 'cookiedk' ),
				$meta['synced_at']
			)
		);
		?>
	<?php else : ?>
		<?php esc_html_e( 'Kataloget er endnu ikke synkroniseret fra GitHub. Bundled JSON bruges indtil første sync.', 'cookiedk' ); ?>
	<?php endif; ?>
	<?php if ( ! empty( $meta['last_error'] ) ) : ?>
		<br><span class="cookiedk-sync-error"><?php echo esc_html( $meta['last_error'] ); ?></span>
	<?php endif; ?>
	<?php if ( ! empty( $meta['last_push_at'] ) ) : ?>
		<br>
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: Cookie name, 2: Datetime. */
				__( 'Sidste GitHub-push: %1$s (%2$s)', 'cookiedk' ),
				$meta['last_push_cookie'],
				$meta['last_push_at']
			)
		);
		?>
	<?php endif; ?>
	<?php if ( ! empty( $meta['last_push_error'] ) ) : ?>
		<br><span class="cookiedk-sync-error"><?php echo esc_html( $meta['last_push_error'] ); ?></span>
	<?php endif; ?>
</p>

<p>
	<label for="cookiedk-origin-filter"><?php esc_html_e( 'Vis', 'cookiedk' ); ?></label>
	<select id="cookiedk-origin-filter">
		<option value=""><?php esc_html_e( 'Alle cookies', 'cookiedk' ); ?></option>
		<option value="unknown" <?php selected( $origin_filter, 'unknown' ); ?>><?php esc_html_e( 'Ukendte', 'cookiedk' ); ?></option>
		<option value="ai" <?php selected( $origin_filter, 'ai' ); ?>><?php esc_html_e( 'AI-genererede', 'cookiedk' ); ?></option>
		<option value="catalog" <?php selected( $origin_filter, 'catalog' ); ?>><?php esc_html_e( 'Katalog', 'cookiedk' ); ?></option>
		<option value="manual" <?php selected( $origin_filter, 'manual' ); ?>><?php esc_html_e( 'Manuelle', 'cookiedk' ); ?></option>
	</select>
</p>

<div class="cookiedk-table-wrap">
	<table class="cookiedk-table" id="cookiedk-cookies-table">
		<thead>
			<tr>
				<th data-sortable><?php esc_html_e( 'Navn', 'cookiedk' ); ?></th>
				<th data-sortable><?php esc_html_e( 'Kategori', 'cookiedk' ); ?></th>
				<th><?php esc_html_e( 'Udbyder', 'cookiedk' ); ?></th>
				<th><?php esc_html_e( 'Varighed', 'cookiedk' ); ?></th>
				<th><?php esc_html_e( 'Beskrivelse', 'cookiedk' ); ?></th>
				<th><?php esc_html_e( 'Kilde', 'cookiedk' ); ?></th>
				<th><?php esc_html_e( 'Handlinger', 'cookiedk' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $cookies ) ) : ?>
				<tr>
					<td colspan="7">
						<em><?php esc_html_e( 'Ingen cookies detekteret endnu. Besøg hjemmesiden for at starte detektion.', 'cookiedk' ); ?></em>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $cookies as $cookie ) : ?>
					<?php
					$origin       = isset( $cookie->origin ) ? (string) $cookie->origin : 'unknown';
					$origin_label = isset( $origin_labels[ $origin ] ) ? $origin_labels[ $origin ] : $origin_labels['unknown'];
					?>
					<tr id="cookiedk-cookie-<?php echo esc_attr( $cookie->id ); ?>">
						<td><code><?php echo esc_html( $cookie->name ); ?></code></td>
						<td>
							<span class="cookiedk-badge cookiedk-badge--<?php echo esc_attr( $cookie->category ); ?>">
					<?php echo esc_html( isset( $categories[ $cookie->category ] ) ? $categories[ $cookie->category ] : $cookie->category ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $cookie->provider ); ?></td>
						<td><?php echo esc_html( $cookie->duration ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $cookie->description_da, 10 ) ); ?></td>
						<td>
							<span class="cookiedk-badge cookiedk-badge--origin-<?php echo esc_attr( $origin ); ?>">
								<?php echo esc_html( $origin_label ); ?>
							</span>
						</td>
						<td class="cookiedk-table__actions">
							<button
								type="button"
								class="button button-small cookiedk-edit-cookie"
								data-id="<?php echo esc_attr( $cookie->id ); ?>"
								data-category="<?php echo esc_attr( $cookie->category ); ?>"
								data-description="<?php echo esc_attr( $cookie->description_da ); ?>"
								data-duration="<?php echo esc_attr( $cookie->duration ); ?>"
								data-provider="<?php echo esc_attr( $cookie->provider ); ?>"
							>
					<?php esc_html_e( 'Rediger', 'cookiedk' ); ?>
							</button>
							<?php if ( 'unknown' === $origin && $ai_ready ) : ?>
								<button
									type="button"
									class="button button-small cookiedk-describe-cookie"
									data-id="<?php echo esc_attr( $cookie->id ); ?>"
									data-name="<?php echo esc_attr( $cookie->name ); ?>"
								>
									<?php esc_html_e( 'Generér med AI', 'cookiedk' ); ?>
								</button>
							<?php endif; ?>
							<button
								type="button"
								class="button button-small cookiedk-delete-cookie"
								data-id="<?php echo esc_attr( $cookie->id ); ?>"
							>
					<?php esc_html_e( 'Slet', 'cookiedk' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Tilføj/Rediger cookie modal -->
<div class="cookiedk-modal-overlay" id="cookiedk-edit-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cookiedk-edit-modal-title">
	<div class="cookiedk-modal" id="cookiedk-edit-modal">
		<div class="cookiedk-modal__header">
			<h3 class="cookiedk-modal__title" id="cookiedk-edit-modal-title">
				<?php esc_html_e( 'Rediger cookie', 'cookiedk' ); ?>
			</h3>
			<button type="button" class="cookiedk-modal__close" aria-label="<?php esc_attr_e( 'Luk', 'cookiedk' ); ?>">&#x2715;</button>
		</div>
		<div class="cookiedk-modal__body">
			<form id="cookiedk-edit-form">
				<input type="hidden" name="cookie_id" value="">

				<table class="form-table">
					<tr>
						<th scope="row"><label for="edit-category"><?php esc_html_e( 'Kategori', 'cookiedk' ); ?></label></th>
						<td>
							<select name="cookie_category" id="edit-category">
								<?php foreach ( $categories as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="edit-provider"><?php esc_html_e( 'Udbyder', 'cookiedk' ); ?></label></th>
						<td><input type="text" name="cookie_provider" id="edit-provider" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="edit-duration"><?php esc_html_e( 'Varighed', 'cookiedk' ); ?></label></th>
						<td><input type="text" name="cookie_duration" id="edit-duration" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="edit-description"><?php esc_html_e( 'Beskrivelse (dansk)', 'cookiedk' ); ?></label></th>
						<td><textarea name="cookie_description" id="edit-description" rows="4" class="large-text"></textarea></td>
					</tr>
				</table>
			</form>
		</div>
		<div class="cookiedk-modal__footer">
			<button type="button" class="cookiedk-modal__close button"><?php esc_html_e( 'Annuller', 'cookiedk' ); ?></button>
			<button type="submit" form="cookiedk-edit-form" class="button button-primary"><?php esc_html_e( 'Gem', 'cookiedk' ); ?></button>
		</div>
	</div>
</div>

<!-- Tilføj ny cookie modal -->
<div class="cookiedk-modal-overlay" id="cookiedk-add-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cookiedk-add-modal-title">
	<div class="cookiedk-modal" id="cookiedk-add-modal">
		<div class="cookiedk-modal__header">
			<h3 class="cookiedk-modal__title" id="cookiedk-add-modal-title">
				<?php esc_html_e( 'Tilføj cookie manuelt', 'cookiedk' ); ?>
			</h3>
			<button type="button" class="cookiedk-modal__close" aria-label="<?php esc_attr_e( 'Luk', 'cookiedk' ); ?>">&#x2715;</button>
		</div>
		<div class="cookiedk-modal__body">
			<form method="post" action="">
				<?php wp_nonce_field( 'cookiedk_save_cookie', 'cookiedk_cookie_nonce' ); ?>
				<input type="hidden" name="cookie_id" value="0">

				<table class="form-table">
					<tr>
						<th scope="row"><label for="add-name"><?php esc_html_e( 'Cookie-navn *', 'cookiedk' ); ?></label></th>
						<td><input type="text" name="cookie_name" id="add-name" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="add-category"><?php esc_html_e( 'Kategori', 'cookiedk' ); ?></label></th>
						<td>
							<select name="cookie_category" id="add-category">
								<?php foreach ( $categories as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="add-provider"><?php esc_html_e( 'Udbyder', 'cookiedk' ); ?></label></th>
						<td><input type="text" name="cookie_provider" id="add-provider" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="add-duration"><?php esc_html_e( 'Varighed', 'cookiedk' ); ?></label></th>
						<td><input type="text" name="cookie_duration" id="add-duration" class="regular-text" placeholder="<?php esc_attr_e( 'f.eks. 1 år', 'cookiedk' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="add-description"><?php esc_html_e( 'Beskrivelse (dansk)', 'cookiedk' ); ?></label></th>
						<td><textarea name="cookie_description" id="add-description" rows="4" class="large-text"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Nødvendig', 'cookiedk' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cookie_necessary" value="1">
								<?php esc_html_e( 'Denne cookie er nødvendig for websitets funktion', 'cookiedk' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<div class="cookiedk-modal__footer" style="padding: 12px 0 0; border-top: 1px solid #dcdcde; display: flex; gap: 10px; justify-content: flex-end;">
					<button type="button" class="cookiedk-modal__close button"><?php esc_html_e( 'Annuller', 'cookiedk' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Tilføj cookie', 'cookiedk' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
jQuery( function ( $ ) {
	// Åbn "Tilføj cookie"-modal.
	$( '#cookiedk-add-cookie-btn' ).on( 'click', function () {
		$( '#cookiedk-add-modal-overlay' ).addClass( 'active' );
	} );
} );
</script>
