<?php
/**
 * Indstillingspanel-template for CookieDK.
 *
 * Renderer HTML-strukturen for cookie-indstillingspanelet.
 * Variabler tilgængelige fra class-frontend.php:
 *   $cookies_by_category  (array)  – Cookies grupperet per kategori
 *   $cookie_policy        (string) – URL til cookiepolitik
 *
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string $cookie_policy */
/** @var array $cookies_by_category */
?>
<div
	id="cookiedk-settings-panel"
	role="dialog"
	aria-modal="true"
	aria-labelledby="cookiedk-panel-heading"
	aria-hidden="true"
>
	<div class="cookiedk-panel__header">
		<h2 class="cookiedk-panel__title" id="cookiedk-panel-heading">
			<?php esc_html_e( 'Cookie-indstillinger', 'cookiedk' ); ?>
		</h2>
		<button
			id="cookiedk-panel-close"
			type="button"
			class="cookiedk-panel__close"
			aria-label="<?php esc_attr_e( 'Luk indstillinger', 'cookiedk' ); ?>"
		>&#x2715;</button>
	</div>

	<div class="cookiedk-panel__body">
		<p class="cookiedk-panel__intro">
			<?php esc_html_e( 'Her kan du administrere dine cookie-præferencer. Nødvendige cookies kan ikke deaktiveres, da de er afgørende for at hjemmesiden fungerer korrekt.', 'cookiedk' ); ?>
			<?php if ( $cookie_policy ) : ?>
				<a
					href="<?php echo esc_url( $cookie_policy ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				><?php esc_html_e( 'Læs mere i vores cookiepolitik', 'cookiedk' ); ?></a>.
			<?php endif; ?>
		</p>

		<?php foreach ( $cookies_by_category as $slug => $category_data ) : ?>
			<?php
			$cat_slug    = sanitize_key( $slug );
			$cat_label   = esc_html( $category_data['label'] );
			$is_required = ( 'necessary' === $cat_slug );
			$cookie_count = count( $category_data['cookies'] );
			$is_enabled  = $category_data['enabled'];
			?>
			<div class="cookiedk-category" id="cookiedk-cat-<?php echo esc_attr( $cat_slug ); ?>">
				<div class="cookiedk-category__header">

					<!-- Toggle-switch -->
					<label
						class="cookiedk-toggle"
						aria-label="<?php
							/* translators: %s: Kategorinavn. */
							echo esc_attr( sprintf( __( '%s cookies', 'cookiedk' ), $cat_label ) );
						?>"
					>
						<input
							type="checkbox"
							data-cookiedk-category="<?php echo esc_attr( $cat_slug ); ?>"
							<?php checked( $is_required || $is_enabled ); ?>
							<?php disabled( $is_required ); ?>
							aria-describedby="cookiedk-cat-desc-<?php echo esc_attr( $cat_slug ); ?>"
						>
						<span class="cookiedk-toggle__track" aria-hidden="true"></span>
					</label>

					<div class="cookiedk-category__label-group" id="cookiedk-cat-desc-<?php echo esc_attr( $cat_slug ); ?>">
						<span class="cookiedk-category__name"><?php echo $cat_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped – already escaped above. ?></span>
						<span class="cookiedk-category__count">
							<?php
							printf(
								/* translators: %d: Antal cookies. */
								esc_html( _n( '%d cookie', '%d cookies', $cookie_count, 'cookiedk' ) ),
								(int) $cookie_count
							);
							?>
						</span>
					</div>

					<?php if ( $is_required ) : ?>
						<span class="cookiedk-category__required-badge">
							<?php esc_html_e( 'Altid aktiv', 'cookiedk' ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $cookie_count > 0 ) : ?>
						<button
							type="button"
							class="cookiedk-category__expander"
							data-cookiedk-expand="<?php echo esc_attr( $cat_slug ); ?>"
							aria-expanded="false"
							aria-controls="cookiedk-list-<?php echo esc_attr( $cat_slug ); ?>"
							aria-label="<?php
								/* translators: %s: Kategorinavn. */
								echo esc_attr( sprintf( __( 'Vis %s cookies', 'cookiedk' ), $cat_label ) );
							?>"
						>
							<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
								<path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
					<?php endif; ?>
				</div>

				<?php if ( $cookie_count > 0 ) : ?>
					<ul
						class="cookiedk-cookie-list"
						id="cookiedk-list-<?php echo esc_attr( $cat_slug ); ?>"
						aria-label="<?php
							/* translators: %s: Kategorinavn. */
							echo esc_attr( sprintf( __( 'Cookies i kategorien %s', 'cookiedk' ), $cat_label ) );
						?>"
					>
						<?php foreach ( $category_data['cookies'] as $cookie ) : ?>
							<li class="cookiedk-cookie-list__item">
								<span class="cookiedk-cookie-list__name"><?php echo esc_html( $cookie->name ); ?></span>
								<div class="cookiedk-cookie-list__meta">
									<?php if ( ! empty( $cookie->provider ) ) : ?>
										<span class="cookiedk-cookie-list__meta-item">
											<strong><?php esc_html_e( 'Udbyder:', 'cookiedk' ); ?></strong>
											<?php echo esc_html( $cookie->provider ); ?>
										</span>
									<?php endif; ?>
									<?php if ( ! empty( $cookie->duration ) ) : ?>
										<span class="cookiedk-cookie-list__meta-item">
											<strong><?php esc_html_e( 'Varighed:', 'cookiedk' ); ?></strong>
											<?php echo esc_html( $cookie->duration ); ?>
										</span>
									<?php endif; ?>
								</div>
								<?php if ( ! empty( $cookie->description_da ) ) : ?>
									<p class="cookiedk-cookie-list__desc">
										<?php echo esc_html( $cookie->description_da ); ?>
									</p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="cookiedk-panel__footer">
		<button
			id="cookiedk-save-settings"
			type="button"
			class="cookiedk-btn cookiedk-btn--primary"
		>
			<?php esc_html_e( 'Gem indstillinger', 'cookiedk' ); ?>
		</button>

		<button
			id="cookiedk-panel-accept-all"
			type="button"
			class="cookiedk-btn cookiedk-btn--secondary"
		>
			<?php esc_html_e( 'Accepter alle', 'cookiedk' ); ?>
		</button>
	</div>
</div>
