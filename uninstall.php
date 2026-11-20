<?php
/**
 * Uninstall: removes plugin options only. Imported pages/templates are user content and are kept.
 *
 * @package PlugTemplates
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'plugt_db_version' );
delete_option( 'plugt_settings' );
delete_transient( 'plugt_manifest' );
