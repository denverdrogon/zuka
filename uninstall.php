<?php
/**
 * Uninstall: removes plugin options and the hidden preview templates only.
 * Imported pages/templates are user content and are kept.
 *
 * @package PlugTemplates
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'plugt_db_version' );
delete_option( 'plugt_settings' );
delete_transient( 'plugt_manifest' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Runs once, at uninstall.
$plugt_previews = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", '_plugt_preview' ) );
foreach ( $plugt_previews as $plugt_preview_id ) {
	wp_delete_post( (int) $plugt_preview_id, true );
}
