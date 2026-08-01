/**
 * CookieDK – Cookie-samtykke logik
 *
 * Håndterer aktivering/deaktivering af cookies baseret på brugerens valg,
 * AJAX-logning til backend, nonce-verificering og event-triggering.
 *
 * @package CookieDK
 * @since   1.0.0
 */
( function () {
	'use strict';

	/** @type {string} Nøgle til localStorage. */
	var STORAGE_KEY = 'cookiedk_consent';

	/**
	 * Henter gemt samtykke fra localStorage.
	 *
	 * @return {Object|null}
	 */
	function getStoredConsent() {
		try {
			var raw = localStorage.getItem( STORAGE_KEY );
			if ( raw ) {
				return JSON.parse( raw );
			}
		} catch ( e ) {
			// localStorage ikke tilgængelig.
		}
		return null;
	}

	/**
	 * Gemmer samtykke i localStorage med udløbstidspunkt.
	 *
	 * @param {Object} consent Samtykke-objekt.
	 * @param {number} expiryDays Antal dage til udløb.
	 */
	function storeConsent( consent, expiryDays ) {
		try {
			var expiry = new Date();
			expiry.setDate( expiry.getDate() + ( expiryDays || 365 ) );
			var data = {
				consent:  consent,
				expiry:   expiry.toISOString(),
				version:  '1.0',
				timestamp: new Date().toISOString(),
			};
			localStorage.setItem( STORAGE_KEY, JSON.stringify( data ) );
		} catch ( e ) {
			// Ignorer fejl.
		}
	}

	/**
	 * Kontrollerer om gemt samtykke er udløbet.
	 *
	 * @param {Object} stored Gemt samtykke-data.
	 * @return {boolean}
	 */
	function isConsentExpired( stored ) {
		if ( ! stored || ! stored.expiry ) {
			return true;
		}
		return new Date( stored.expiry ) < new Date();
	}

	/**
	 * Sender samtykke til WordPress-backend via AJAX.
	 *
	 * @param {Object} consent Samtykke-objekt.
	 */
	function logConsentToServer( consent ) {
		var data = window.cookieDKData;
		if ( ! data || ! data.ajaxUrl || ! data.nonce ) {
			return;
		}

		var body = 'action=cookiedk_log_consent'
			+ '&nonce=' + encodeURIComponent( data.nonce )
			+ '&consent=' + encodeURIComponent( JSON.stringify( consent ) );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', data.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.send( body );
	}

	/**
	 * Aktiverer tredjeparts-scripts baseret på samtykke-kategorier.
	 * Triggerer custom events så tredjeparts-integrationer kan reagere.
	 *
	 * @param {Object} consent Samtykke-objekt.
	 */
	function activateCookies( consent ) {
		var categories = [ 'necessary', 'functional', 'analytics', 'marketing' ];

		categories.forEach( function ( cat ) {
			var accepted = !! consent[ cat ];

			// Trigger event: cookiedk:consent:{kategori}.
			var evt = new CustomEvent( 'cookiedk:consent:' + cat, {
				detail: { accepted: accepted },
				bubbles: true,
			} );
			document.dispatchEvent( evt );

			// Sæt data-attribut på body til CSS-targeting.
			document.body.setAttribute( 'data-cookiedk-' + cat, accepted ? '1' : '0' );
		} );

		// Globalt event med fuldt samtykke-objekt.
		var globalEvt = new CustomEvent( 'cookiedk:consent', {
			detail: { consent: consent },
			bubbles: true,
		} );
		document.dispatchEvent( globalEvt );
	}

	/**
	 * Kontrollerer om samtykke er nødvendigt (ikke gemt eller udløbet).
	 *
	 * @return {boolean}
	 */
	function consentRequired() {
		var stored = getStoredConsent();
		if ( ! stored ) {
			return true;
		}
		return isConsentExpired( stored );
	}

	/**
	 * Gemmer og aktiverer samtykke.
	 *
	 * @param {Object} consent         Samtykke-objekt.
	 * @param {boolean} logToServer    Om der skal logges til server.
	 */
	function saveConsent( consent, logToServer ) {
		var data       = window.cookieDKData || {};
		var expiryDays = data.consentExpiry || 365;

		// Nødvendige cookies er altid accepteret.
		consent.necessary = true;

		storeConsent( consent, expiryDays );
		activateCookies( consent );

		if ( false !== logToServer ) {
			logConsentToServer( consent );
		}
	}

	/**
	 * Accepterer alle cookies.
	 */
	function acceptAll() {
		saveConsent( {
			necessary:  true,
			functional: true,
			analytics:  true,
			marketing:  true,
		} );
	}

	/**
	 * Accepterer kun nødvendige cookies.
	 */
	function acceptNecessaryOnly() {
		saveConsent( {
			necessary:  true,
			functional: false,
			analytics:  false,
			marketing:  false,
		} );
	}

	/**
	 * Gemmer brugerdefineret samtykke (fra indstillingspanel).
	 *
	 * @param {Object} consent Samtykke-objekt.
	 */
	function saveCustomConsent( consent ) {
		saveConsent( consent );
	}

	/**
	 * Henter nuværende samtykke-status.
	 *
	 * @return {Object|null}
	 */
	function getCurrentConsent() {
		var stored = getStoredConsent();
		if ( stored && ! isConsentExpired( stored ) ) {
			return stored.consent;
		}
		return null;
	}

	/**
	 * Tjekker om en specifik kategori er accepteret.
	 *
	 * @param {string} category Kategorinavn.
	 * @return {boolean}
	 */
	function isCategoryAccepted( category ) {
		var consent = getCurrentConsent();
		if ( ! consent ) {
			return 'necessary' === category;
		}
		return !! consent[ category ];
	}

	/**
	 * Initialiserer samtykke-logik ved sideindlæsning.
	 * Aktiverer cookies hvis samtykke allerede er gemt.
	 */
	function init() {
		var stored = getStoredConsent();
		if ( stored && ! isConsentExpired( stored ) && stored.consent ) {
			activateCookies( stored.consent );
		}
	}

	// Eksponér public API.
	window.CookieDKConsent = {
		consentRequired:    consentRequired,
		acceptAll:          acceptAll,
		acceptNecessaryOnly: acceptNecessaryOnly,
		saveCustomConsent:  saveCustomConsent,
		getCurrentConsent:  getCurrentConsent,
		isCategoryAccepted: isCategoryAccepted,
		init:               init,
	};

	// Kør ved DOMContentLoaded.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
