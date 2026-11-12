<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'coma_cookie_consent_settings' );

// Drop the consent-log table.
global $wpdb;
$table = $wpdb->prefix . 'ccc_consent_log';
$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB
