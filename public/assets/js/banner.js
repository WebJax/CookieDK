/**
 * CookieDK – Banner rendering og interaktion
 *
 * Håndterer banner-visning, indstillingspanel-toggle,
 * responsivt design og tilgængelighed (ARIA, keyboard navigation).
 *
 * @package CookieDK
 * @since   1.0.0
 */

( function () {
	'use strict';

	/**
	 * Banner-elementet.
	 *
	 * @type {HTMLElement|null}
	 */
	var banner = null;

	/**
	 * Indstillingspanel-elementet.
	 *
	 * @type {HTMLElement|null}
	 */
	var settingsPanel = null;

	/**
	 * Overlay-elementet.
	 *
	 * @type {HTMLElement|null}
	 */
	var overlay = null;

	/**
	 * Sidst fokuserede element.
	 *
	 * @type {HTMLElement|null}
	 */
	var lastFocusedElement = null;

	/**
	 * Henter et element via ID.
	 *
	 * @param  {string} id Element-ID.
	 * @return {HTMLElement|null}
	 */
	function el( id )
	{
		return document.getElementById( id );
	}

	/**
	 * Finder banner-div'en (ikke et <link>/<style> med samme id fra asset-optimizers).
	 *
	 * @return {HTMLElement|null}
	 */
	function getBannerElement()
	{
		return document.querySelector( 'div#cookiedk-banner[role="dialog"]' )
			|| document.querySelector( 'div#cookiedk-banner' );
	}

	/**
	 * Viser cookie-banneret.
	 */
	function showBanner()
	{
		if ( ! banner ) {
			return;
		}
		banner.classList.remove( 'cookiedk-hidden' );
		banner.removeAttribute( 'hidden' );
		banner.setAttribute( 'aria-hidden', 'false' );

		// Sæt fokus på "Accepter alle"-knap.
		var firstBtn = banner.querySelector( '.cookiedk-btn--primary' );
		if (firstBtn ) {
			firstBtn.focus();
		}
	}

	/**
	 * Skjuler cookie-banneret.
	 */
	function hideBanner()
	{
		if ( ! banner ) {
			return;
		}
		banner.classList.add( 'cookiedk-hidden' );
		banner.setAttribute( 'aria-hidden', 'true' );
	}

	/**
	 * Åbner indstillingspanelet.
	 */
	function openSettingsPanel()
	{
		if ( ! settingsPanel || ! overlay ) {
			return;
		}
		lastFocusedElement = document.activeElement;

		overlay.classList.add( 'cookiedk-overlay--active' );
		settingsPanel.classList.add( 'cookiedk-panel--active' );
		settingsPanel.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';

		// Sæt fokus på luk-knap.
		var closeBtn = settingsPanel.querySelector( '.cookiedk-panel__close' );
		if (closeBtn ) {
			closeBtn.focus();
		}

		// Synkroniser toggle-tilstande med gemt samtykke.
		syncToggles();
	}

	/**
	 * Lukker indstillingspanelet.
	 */
	function closeSettingsPanel()
	{
		if ( ! settingsPanel || ! overlay ) {
			return;
		}
		overlay.classList.remove( 'cookiedk-overlay--active' );
		settingsPanel.classList.remove( 'cookiedk-panel--active' );
		settingsPanel.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';

		// Returnér fokus.
		if (lastFocusedElement && lastFocusedElement.focus ) {
			lastFocusedElement.focus();
		}
	}

	/**
	 * Synkroniserer toggle-knapper med gemt samtykke.
	 */
	function syncToggles()
	{
		var consent     = window.CookieDKConsent ? window.CookieDKConsent.getCurrentConsent() : null;
		var toggles     = settingsPanel ? settingsPanel.querySelectorAll( '[data-cookiedk-category]' ) : [];
		var toggleCount = toggles.length;

		for ( var i = 0; i < toggleCount; i++ ) {
			var toggle   = toggles[ i ];
			var category = toggle.getAttribute( 'data-cookiedk-category' );
			if ('necessary' === category ) {
				toggle.checked = true;
				continue;
			}
			if (consent ) {
				toggle.checked = ! ! consent[ category ];
			}
		}
	}

	/**
	 * Læser de aktuelle toggle-værdier fra indstillingspanelet.
	 *
	 * @return {Object} Samtykke-objekt.
	 */
	function readToggles()
	{
		var consent     = { necessary: true };
		var toggles     = settingsPanel ? settingsPanel.querySelectorAll( '[data-cookiedk-category]' ) : [];
		var toggleCount = toggles.length;

		for ( var i = 0; i < toggleCount; i++ ) {
			var toggle          = toggles[ i ];
			var category        = toggle.getAttribute( 'data-cookiedk-category' );
			consent[ category ] = toggle.checked;
		}
		consent.necessary = true;
		return consent;
	}

	/**
	 * Ekspanderer/kollapser en cookie-liste for en kategori.
	 *
	 * @param {HTMLElement} btn Expander-knap.
	 */
	function toggleCategoryList( btn )
	{
		var category = btn.getAttribute( 'data-cookiedk-expand' );
		var list     = document.getElementById( 'cookiedk-list-' + category );

		if ( ! list ) {
			return;
		}

		var expanded = 'true' === btn.getAttribute( 'aria-expanded' );

		btn.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
		list.classList.toggle( 'cookiedk-expanded', ! expanded );
	}

	/**
	 * Fange tab-tast inde i modalen (fokus-trap).
	 *
	 * @param {KeyboardEvent} e
	 */
	function trapFocus( e )
	{
		if ('Tab' !== e.key || ! settingsPanel ) {
			return;
		}
		var focusable = settingsPanel.querySelectorAll(
			'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
		);
		if (0 === focusable.length ) {
			e.preventDefault();
			return;
		}
		var first = focusable[ 0 ];
		var last  = focusable[ focusable.length - 1 ];

		if (e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	/**
	 * Tilknytter event-handlers til banner og indstillingspanel.
	 */
	function bindEvents()
	{
		// "Accepter alle"-knap i banner.
		var acceptAllBtn = el( 'cookiedk-accept-all' );
		if (acceptAllBtn ) {
			acceptAllBtn.addEventListener(
				'click',
				function () {
					if (window.CookieDKConsent ) {
						window.CookieDKConsent.acceptAll();
					}
					hideBanner();
					announceToScreenReader( 'Alle cookies er accepteret.' );
				}
			);
		}

		// "Kun nødvendige"-knap i banner.
		var necessaryBtn = el( 'cookiedk-accept-necessary' );
		if (necessaryBtn ) {
			necessaryBtn.addEventListener(
				'click',
				function () {
					if (window.CookieDKConsent ) {
						window.CookieDKConsent.acceptNecessaryOnly();
					}
					hideBanner();
					announceToScreenReader( 'Kun nødvendige cookies er accepteret.' );
				}
			);
		}

		// "Indstillinger"-knap i banner.
		var settingsBtn = el( 'cookiedk-open-settings' );
		if (settingsBtn ) {
			settingsBtn.addEventListener(
				'click',
				function () {
					openSettingsPanel();
				}
			);
		}

		// Luk-knap i indstillingspanel.
		var closeBtn = el( 'cookiedk-panel-close' );
		if (closeBtn ) {
			closeBtn.addEventListener(
				'click',
				function () {
					closeSettingsPanel();
				}
			);
		}

		// "Gem indstillinger"-knap.
		var saveBtn = el( 'cookiedk-save-settings' );
		if (saveBtn ) {
			saveBtn.addEventListener(
				'click',
				function () {
					var consent = readToggles();
					if (window.CookieDKConsent ) {
						window.CookieDKConsent.saveCustomConsent( consent );
					}
					closeSettingsPanel();
					hideBanner();
					announceToScreenReader( 'Cookie-indstillinger er gemt.' );
				}
			);
		}

		// "Accepter alle" i indstillingspanel.
		var panelAcceptAllBtn = el( 'cookiedk-panel-accept-all' );
		if (panelAcceptAllBtn ) {
			panelAcceptAllBtn.addEventListener(
				'click',
				function () {
					if (window.CookieDKConsent ) {
						window.CookieDKConsent.acceptAll();
					}
					closeSettingsPanel();
					hideBanner();
					announceToScreenReader( 'Alle cookies er accepteret.' );
				}
			);
		}

		// Ekspander/kollaps cookie-lister.
		var expanders      = document.querySelectorAll( '[data-cookiedk-expand]' );
		var expandersCount = expanders.length;

		for ( var i = 0; i < expandersCount; i++ ) {
			( function ( btn ) {
				btn.addEventListener(
					'click',
					function () {
						toggleCategoryList( btn );
					}
				);
			}( expanders[ i ] ) );
		}

		// Luk ved klik på overlay.
		if (overlay ) {
			overlay.addEventListener(
				'click',
				function () {
					closeSettingsPanel();
				}
			);
		}

		// Luk ved Escape.
		document.addEventListener(
			'keydown',
			function ( e ) {
				if ('Escape' === e.key && settingsPanel && settingsPanel.classList.contains( 'cookiedk-panel--active' ) ) {
					closeSettingsPanel();
				}
				trapFocus( e );
			}
		);
	}

	/**
	 * Annoncerer besked til skærmlæsere via live region.
	 *
	 * @param {string} message Beskeden.
	 */
	function announceToScreenReader( message )
	{
		var liveRegion = el( 'cookiedk-live-region' );
		if (liveRegion ) {
			liveRegion.textContent = '';
			// Forsink for at sikre at skærmlæseren opfanger ændringen.
			setTimeout(
				function () {
					liveRegion.textContent = message;
				},
				50
			);
		}
	}

	/**
	 * Initialiserer banner-komponenten.
	 */
	function init()
	{
		banner        = getBannerElement();
		settingsPanel = el( 'cookiedk-settings-panel' );
		overlay       = el( 'cookiedk-overlay' );

		if ( ! banner ) {
			return;
		}

		bindEvents();

		// Vis banner hvis samtykke er nødvendigt.
		// Hvis CookieDKConsent ikke er tilgængeligt, vises banneret som GDPR-sikker fallback.
		if ( ! window.CookieDKConsent || window.CookieDKConsent.consentRequired() ) {
			showBanner();
		} else {
			hideBanner();
		}

		// Tilføj position-klasse fra server-data.
		var data = window.cookieDKData;
		if (data && data.bannerPosition ) {
			banner.classList.add( 'cookiedk-position-' + data.bannerPosition );
		}
	}

	// Start ved DOMContentLoaded.
	if ('loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
