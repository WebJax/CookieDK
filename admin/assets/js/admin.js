/**
 * CookieDK Admin JavaScript
 *
 * Håndterer formular-validering, AJAX-handlinger, modal-dialogs
 * og tabel-sortering i admin-interfacet.
 *
 * @package CookieDK
 * @since   1.0.0
 */

/* global wp */

( function ( $, data ) {
	'use strict';

	var wp = window.wp || {};

	// =========================================================
	// Notifikationer
	// =========================================================

	/**
	 * Viser en notifikation til brugeren.
	 *
	 * @param {string}  message  Beskedtekst.
	 * @param {string}  type     'success' eller 'error'.
	 * @param {jQuery}  $target  Mål-element (valgfrit).
	 */
	function applyThemeColors() {
		if ( ! window.cookieDKAdmin || ! window.cookieDKAdmin.themeColors ) {
			return;
		}

		var colors     = window.cookieDKAdmin.themeColors || {};
		var $primary   = $( '#primary_color' );
		var $secondary = $( '#secondary_color' );

		if ( $primary.length && colors.primary && ! $primary.val() ) {
			$primary.val( colors.primary );
		}

		if ( $secondary.length && colors.secondary && ! $secondary.val() ) {
			$secondary.val( colors.secondary );
		}
	}

	function showNotice( message, type, $target )
	{
		var $notice;

		if ($target && $target.length ) {
			$notice = $target.find( '.cookiedk-notice' );
		} else {
			$notice = $( '.cookiedk-notice' ).first();
		}

		if ( ! $notice.length ) {
			$notice = $( '<div class="cookiedk-notice"></div>' );
			if ($target && $target.length ) {
				$target.prepend( $notice );
			} else {
				$( '.cookiedk-admin__content' ).prepend( $notice );
			}
		}

		$notice
		.removeClass( 'success error' )
		.addClass( type || 'success' )
		.text( message )
		.addClass( 'visible' );

		// Skjul efter 5 sekunder.
		setTimeout(
			function () {
				$notice.removeClass( 'visible' );
			},
			5000
		);
	}

	$(
		function () {
			applyThemeColors();
		}
	);

	$( document ).on(
		'click',
		'#cookiedk-use-theme-colors',
		function ( e ) {
			e.preventDefault();
			applyThemeColors();
			showNotice( data.i18n.theme_colors_applied || 'Tema-farver indlæst.', 'success' );
		}
	);

	// =========================================================
	// Modal-dialog
	// =========================================================

	/**
	 * Åbner en modal-dialog.
	 *
	 * @param {string} modalId Modal-ID.
	 */
	function openModal( modalId )
	{
		$( '#' + modalId + '-overlay' ).addClass( 'active' );
		$( '#' + modalId ).find( '.cookiedk-modal__close' ).first().focus();
		$( 'body' ).css( 'overflow', 'hidden' );
	}

	/**
	 * Lukker en modal-dialog.
	 *
	 * @param {string} modalId Modal-ID.
	 */
	function closeModal( modalId )
	{
		$( '#' + modalId + '-overlay' ).removeClass( 'active' );
		$( 'body' ).css( 'overflow', '' );
	}

	// Luk modal ved klik på overlay.
	$( document ).on(
		'click',
		'.cookiedk-modal-overlay',
		function ( e ) {
			if (e.target === this ) {
				$( this ).removeClass( 'active' );
				$( 'body' ).css( 'overflow', '' );
			}
		}
	);

	// Luk modal ved klik på luk-knap.
	$( document ).on(
		'click',
		'.cookiedk-modal__close',
		function () {
			$( this ).closest( '.cookiedk-modal-overlay' ).removeClass( 'active' );
			$( 'body' ).css( 'overflow', '' );
		}
	);

	// Luk modal ved Escape.
	$( document ).on(
		'keydown',
		function ( e ) {
			if (27 === e.which ) {
				$( '.cookiedk-modal-overlay.active' ).removeClass( 'active' );
				$( 'body' ).css( 'overflow', '' );
			}
		}
	);

	// =========================================================
	// Cookie-redigering (AJAX)
	// =========================================================

	/**
	 * Åbner redigerings-modal for en cookie.
	 *
	 * @param {number} id           Cookie-ID.
	 * @param {Object} cookieData   Cookie-data.
	 */
	function openEditModal( id, cookieData )
	{
		var $modal = $( '#cookiedk-edit-modal' );

		if ( ! $modal.length ) {
			return;
		}

		$modal.find( '[name="cookie_id"]' ).val( id );
		$modal.find( '[name="cookie_category"]' ).val( cookieData.category || '' );
		$modal.find( '[name="cookie_description"]' ).val( cookieData.description_da || '' );
		$modal.find( '[name="cookie_duration"]' ).val( cookieData.duration || '' );
		$modal.find( '[name="cookie_provider"]' ).val( cookieData.provider || '' );

		openModal( 'cookiedk-edit-modal' );
	}

	// Rediger-knap klik.
	$( document ).on(
		'click',
		'.cookiedk-edit-cookie',
		function ( e ) {
			e.preventDefault();
			var $btn = $( this );
			openEditModal(
				$btn.data( 'id' ),
				{
					category:       $btn.data( 'category' ),
					description_da: $btn.data( 'description' ),
					duration:       $btn.data( 'duration' ),
					provider:       $btn.data( 'provider' ),
				}
			);
		}
	);

	// Gem redigeret cookie via AJAX.
	$( document ).on(
		'submit',
		'#cookiedk-edit-form',
		function ( e ) {
			e.preventDefault();
			var $form = $( this );
			var $btn  = $form.find( '[type="submit"]' );

			$btn.prop( 'disabled', true ).text( data.i18n.loading );

			$.post(
				data.ajaxUrl,
				{
					action:          'cookiedk_update_cookie',
					nonce:           data.nonce,
					id:              $form.find( '[name="cookie_id"]' ).val(),
					category:        $form.find( '[name="cookie_category"]' ).val(),
					description_da:  $form.find( '[name="cookie_description"]' ).val(),
					duration:        $form.find( '[name="cookie_duration"]' ).val(),
					provider:        $form.find( '[name="cookie_provider"]' ).val(),
				}
			)
			.done(
				function ( response ) {
					if (response.success ) {
						closeModal( 'cookiedk-edit-modal' );
						showNotice( response.data.message, 'success' );
						setTimeout(
							function () {
								window.location.reload(); },
							1200
						);
					} else {
						showNotice( response.data.message, 'error' );
					}
				}
			)
			.fail(
				function () {
					showNotice( data.i18n.error, 'error' );
				}
			)
			.always(
				function () {
					$btn.prop( 'disabled', false ).text( wp.i18n ? wp.i18n.__( 'Gem', 'cookiedk' ) : 'Gem' );
				}
			);
		}
	);

	// =========================================================
	// Slet cookie (AJAX)
	// =========================================================

	$( document ).on(
		'click',
		'.cookiedk-delete-cookie',
		function ( e ) {
			e.preventDefault();

			if ( ! window.confirm( data.i18n.confirm_delete ) ) {
				return;
			}

			var $btn = $( this );
			var id   = $btn.data( 'id' );

			$btn.prop( 'disabled', true );

			$.post(
				data.ajaxUrl,
				{
					action: 'cookiedk_delete_cookie',
					nonce:  data.nonce,
					id:     id,
				}
			)
			.done(
				function ( response ) {
					if (response.success ) {
						$btn.closest( 'tr' ).fadeOut(
							300,
							function () {
								$( this ).remove();
							}
						);
						showNotice( response.data.message, 'success' );
					} else {
						showNotice( response.data.message, 'error' );
						$btn.prop( 'disabled', false );
					}
				}
			)
			.fail(
				function () {
					showNotice( data.i18n.error, 'error' );
					$btn.prop( 'disabled', false );
				}
			);
		}
	);

	// =========================================================
	// Eksportér cookies
	// =========================================================

	$( document ).on(
		'click',
		'#cookiedk-export-cookies',
		function ( e ) {
			e.preventDefault();

			$.post(
				data.ajaxUrl,
				{
					action: 'cookiedk_export_cookies',
					nonce:  data.nonce,
				}
			)
			.done(
				function ( response ) {
					if (response.success ) {
						var json   = JSON.stringify( response.data.cookies, null, 2 );
						var blob   = new Blob( [ json ], { type: 'application/json' } );
						var url    = URL.createObjectURL( blob );
						var a      = document.createElement( 'a' );
						a.href     = url;
						a.download = 'cookiedk-export-' + new Date().toISOString().slice( 0, 10 ) + '.json';
						a.click();
						URL.revokeObjectURL( url );
					} else {
						showNotice( data.i18n.error, 'error' );
					}
				}
			)
			.fail(
				function () {
					showNotice( data.i18n.error, 'error' );
				}
			);
		}
	);

	// =========================================================
	// Gem indstillinger (AJAX – valgfrit; understøtter også POST-form)
	// =========================================================

	$( document ).on(
		'click',
		'#cookiedk-save-settings-ajax',
		function ( e ) {
			e.preventDefault();
			var $form = $( '#cookiedk-settings-form' );
			var $btn  = $( this );

			$btn.prop( 'disabled', true ).text( data.i18n.loading );

			var formData = $form.serialize();

			$.post( data.ajaxUrl, formData + '&action=cookiedk_save_settings&nonce=' + encodeURIComponent( data.nonce ) )
			.done(
				function ( response ) {
					if (response.success ) {
						showNotice( response.data.message, 'success' );
					} else {
						showNotice( data.i18n.error, 'error' );
					}
				}
			)
			.fail(
				function () {
					showNotice( data.i18n.error, 'error' );
				}
			)
			.always(
				function () {
					$btn.prop( 'disabled', false ).text( data.i18n.saved );
					setTimeout(
						function () {
							$btn.text( 'Gem indstillinger' );
						},
						2000
					);
				}
			);
		}
	);

	// =========================================================
	// Tabel-sortering
	// =========================================================

	$( document ).on(
		'click',
		'.cookiedk-table th[data-sortable]',
		function () {
			var $th    = $( this );
			var $table = $th.closest( 'table' );
			var index  = $th.index();
			var asc    = ! $th.hasClass( 'sort-asc' );

			$table.find( 'th' ).removeClass( 'sort-asc sort-desc' );
			$th.addClass( asc ? 'sort-asc' : 'sort-desc' );

			var $tbody = $table.find( 'tbody' );
			var rows   = $tbody.find( 'tr' ).toArray();

			rows.sort(
				function ( a, b ) {
					var aText = $( a ).find( 'td' ).eq( index ).text().trim();
					var bText = $( b ).find( 'td' ).eq( index ).text().trim();
					return asc ? aText.localeCompare( bText, 'da' ) : bText.localeCompare( aText, 'da' );
				}
			);

			$tbody.append( rows );
		}
	);

	// =========================================================
	// Datepicker til samtykke-log filter
	// =========================================================

	if ($.fn.datepicker ) {
		$( '.cookiedk-datepicker' ).datepicker(
			{
				dateFormat:  'yy-mm-dd',
				changeMonth: true,
				changeYear:  true,
			}
		);
	}

	// =========================================================
	// Test-side: Nulstil samtykke
	// =========================================================

	$( document ).on(
		'click',
		'#cookiedk-reset-consent',
		function () {
			try {
				localStorage.removeItem( 'cookiedk_consent' );
				showNotice( 'Samtykke nulstillet. Genindlæs siden for at se banneret.', 'success' );
			} catch ( e ) {
				showNotice( data.i18n.error, 'error' );
			}
		}
	);

	// =========================================================
	// Opret cookiepolitik-side
	// =========================================================

	/**
	 * Aktiverer knappen når påkrævede ejerfelter er udfyldt.
	 */
	function updateCreatePolicyButton()
	{
		var $btn = $( '#cookiedk-create-policy-page' );
		if ( ! $btn.length ) {
			return;
		}

		var required = [
			'#policy_owner_name',
			'#policy_owner_address',
			'#policy_owner_postal',
			'#policy_owner_city',
		];
		var ready    = true;

		var len = required.length;
		for ( var i = 0; i < len; i++ ) {
			if ( ! $( required[ i ] ).val() || ! String( $( required[ i ] ).val() ).trim() ) {
				ready = false;
				break;
			}
		}

		$btn.prop( 'disabled', ! ready );
	}

	$( document ).on(
		'input change',
		'#cookiedk-policy-builder .cookiedk-policy-required',
		updateCreatePolicyButton
	);

	$( document ).on(
		'click',
		'#cookiedk-create-policy-page',
		function ( e ) {
			e.preventDefault();

			var $btn = $( this );
			if ( $btn.prop( 'disabled' ) ) {
				return;
			}

			var label = $btn.text();
			$btn.prop( 'disabled', true ).text( data.i18n.loading );

			$.post(
				data.ajaxUrl,
				{
					action:               'cookiedk_create_policy_page',
					nonce:                data.nonce,
					policy_owner_name:    $( '#policy_owner_name' ).val(),
					policy_owner_address: $( '#policy_owner_address' ).val(),
					policy_owner_postal:  $( '#policy_owner_postal' ).val(),
					policy_owner_city:    $( '#policy_owner_city' ).val(),
					policy_owner_cvr:     $( '#policy_owner_cvr' ).val(),
				}
			)
			.done(
				function ( response ) {
					if ( response.success ) {
						$( '#cookie_policy_url' ).val( response.data.url );
						showNotice( response.data.message, 'success' );
						if ( response.data.edit_url ) {
							$( '#cookiedk-create-policy-hint' ).html(
								$( '<a></a>' )
									.attr( 'href', response.data.edit_url )
									.attr( 'target', '_blank' )
									.attr( 'rel', 'noopener noreferrer' )
									.text( data.i18n.edit_policy || 'Rediger cookiepolitik-siden' )
							);
						}
					} else {
						showNotice( ( response.data && response.data.message ) || data.i18n.error, 'error' );
					}
				}
			)
			.fail(
				function () {
					showNotice( data.i18n.error, 'error' );
				}
			)
			.always(
				function () {
					$btn.text( label );
					updateCreatePolicyButton();
				}
			);
		}
	);

	updateCreatePolicyButton();

}( jQuery, window.cookieDKAdmin || {} ) );
