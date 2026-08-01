<?php
/**
 * Rydning ved afinstallation af CookieDK.
 *
 * Denne fil køres automatisk af WordPress, når pluginen afinstalleres.
 * Sletter alle data, der er oprettet af pluginen.
 *
 * @package CookieDK
 */

// Sikkerhedstjek: Kun WordPress kan kalde denne fil.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Slet database-tabeller.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cookiedk_cookies" );         // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cookiedk_consent_log" );     // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Slet WordPress-options.
$options_to_delete = array(
	'cookiedk_settings',
	'cookiedk_detected_cookies',
	'cookiedk_cookie_descriptions',
	'cookiedk_activated_at',
	'cookiedk_db_version',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Slet multisite-options (hvis relevant).
if ( is_multisite() ) {
	$sites = get_sites();
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cookiedk_cookies" );       // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cookiedk_consent_log" );   // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $options_to_delete as $option ) {
			delete_option( $option );
		}

		restore_current_blog();
	}
}
