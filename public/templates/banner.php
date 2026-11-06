<?php
/**
 * Banner-template for CookieDK.
 *
 * Renderer HTML-strukturen for cookie-banneret.
 * Variabler tilgængelige fra class-frontend.php:
 *   $banner_position      (string) – top|bottom|side|top-left|top-right|center|bottom-left|bottom-right
 *   $reopen_position      (string) – samme værdier som banner-position
 *   $cookie_policy        (string) – URL til cookiepolitik
 *   $cookies_by_category  (array)  – Cookies grupperet per kategori
 *
 * @var string $banner_position
 * @var string $reopen_position
 * @var string $cookie_policy
 * @var array  $cookies_by_category
 * @package CookieDK
 * @since   1.0.0
 */

// Direkte adgang forbydes.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	id="cookiedk-banner"
	class="cookiedk-position-<?php echo esc_attr( $banner_position ); ?> cookiedk-hidden"
	role="dialog"
	aria-modal="false"
	aria-label="<?php esc_attr_e( 'Cookie-samtykke', 'cookiedk' ); ?>"
	aria-hidden="true"
>
	<div class="cookiedk-banner__inner">
		<div class="cookiedk-banner__content">
			<p class="cookiedk-banner__title">
				<?php esc_html_e( 'Vi bruger cookies 🍪', 'cookiedk' ); ?>
			</p>
			<p class="cookiedk-banner__text">
				<?php esc_html_e( 'Vi bruger cookies til at forbedre din oplevelse, analysere trafikken og vise relevant indhold. Du kan til enhver tid ændre dine præferencer.', 'cookiedk' ); ?>
				<?php if ( $cookie_policy ) : ?>
					<a
						href="<?php echo esc_url( $cookie_policy ); ?>"
						class="cookiedk-banner__link"
						target="_blank"
						rel="noopener noreferrer"
					><?php esc_html_e( 'Læs vores cookiepolitik', 'cookiedk' ); ?></a>.
				<?php endif; ?>
			</p>
		</div>

		<div class="cookiedk-banner__actions">
			<button
				id="cookiedk-accept-all"
				type="button"
				class="cookiedk-btn cookiedk-btn--primary"
			>
				<?php esc_html_e( 'Accepter alle', 'cookiedk' ); ?>
			</button>

			<button
				id="cookiedk-open-settings"
				type="button"
				class="cookiedk-btn cookiedk-btn--secondary"
				aria-haspopup="dialog"
				aria-controls="cookiedk-settings-panel"
			>
				<?php esc_html_e( 'Indstillinger', 'cookiedk' ); ?>
			</button>

			<button
				id="cookiedk-accept-necessary"
				type="button"
				class="cookiedk-btn cookiedk-btn--text"
			>
				<?php esc_html_e( 'Kun nødvendige', 'cookiedk' ); ?>
			</button>
		</div>
	</div>
</div>

<button
	id="cookiedk-reopen"
	type="button"
	class="cookiedk-reopen cookiedk-reopen--<?php echo esc_attr( $reopen_position ); ?> cookiedk-hidden"
	aria-controls="cookiedk-banner"
	aria-hidden="true"
	aria-label="<?php esc_attr_e( 'Åbn cookie-banner', 'cookiedk' ); ?>"
>
	<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
		<circle cx="12" cy="12" r="8.2" fill="none" stroke="currentColor" stroke-width="1.8"></circle>
		<circle cx="9" cy="10" r="1.25" fill="currentColor"></circle>
		<circle cx="14.2" cy="8.6" r="1.05" fill="currentColor"></circle>
		<circle cx="13.6" cy="14.2" r="1.35" fill="currentColor"></circle>
		<circle cx="8.7" cy="14.8" r="0.95" fill="currentColor"></circle>
	</svg>
</button>

<!-- Live region til skærmlæsere -->
<div
	id="cookiedk-live-region"
	class="cookiedk-sr-only"
	aria-live="polite"
	aria-atomic="true"
></div>

<!-- Overlay bag indstillingspanel -->
<div id="cookiedk-overlay" aria-hidden="true"></div>
