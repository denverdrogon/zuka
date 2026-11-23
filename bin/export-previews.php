<?php
/**
 * Exports the live preview of every template (Elementor render, site kit and
 * theme CSS) as static HTML files, for bin/build-thumbnails.mjs.
 *
 * Usage: php bin/export-previews.php <wordpress-root> <output-dir> [<slug> ...]
 * The WordPress site must have Zuka and Elementor active. Output files are
 * named <slug>.html and reference the site's CSS by absolute URL, so the
 * output dir must be served by the same site (same origin for fonts).
 *
 * @package PlugTemplates
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.ValidatedSanitizedInput, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- CLI script: WordPress constant and fake request.

if ( 'cli' !== PHP_SAPI || $argc < 3 ) {
	fwrite( STDERR, "Usage : php bin/export-previews.php <racine-wordpress> <dossier-sortie> [<slug> ...]\n" );
	exit( 1 );
}

$plugt_wp   = rtrim( $argv[1], '/\\' );
$plugt_out  = rtrim( $argv[2], '/\\' );
$plugt_only = array_slice( $argv, 3 );

$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_USE_THEMES', false );
require $plugt_wp . '/wp-load.php';

$plugt_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( array() === $plugt_admins ) {
	fwrite( STDERR, "Aucun administrateur sur ce site.\n" );
	exit( 1 );
}
wp_set_current_user( (int) $plugt_admins[0] );
wp();

$plugt_plugin = \PlugTemplates\Plugin::instance();
if ( null === $plugt_plugin || null === $plugt_plugin->repository() || null === $plugt_plugin->importer() ) {
	fwrite( STDERR, "Zuka n'est pas actif sur ce site (ou ses prérequis manquent).\n" );
	exit( 1 );
}
$plugt_previewer = new \PlugTemplates\Library\Previewer( $plugt_plugin->repository(), $plugt_plugin->importer(), $plugt_plugin->elementor() );

if ( ! is_dir( $plugt_out ) ) {
	mkdir( $plugt_out, 0775, true );
}

$plugt_failed = 0;
foreach ( $plugt_plugin->repository()->all() as $plugt_entry ) {
	$plugt_slug = (string) $plugt_entry['id'];
	if ( array() !== $plugt_only && ! in_array( $plugt_slug, $plugt_only, true ) ) {
		continue;
	}
	$plugt_post = $plugt_previewer->document( $plugt_slug );
	if ( $plugt_post instanceof WP_Error ) {
		fwrite( STDERR, "[FAIL] $plugt_slug : " . $plugt_post->get_error_message() . "\n" );
		++$plugt_failed;
		continue;
	}
	// Each template renders in a fresh set of enqueued styles.
	wp_styles()->done  = array();
	wp_scripts()->done = array();
	file_put_contents( "$plugt_out/$plugt_slug.html", $plugt_previewer->html( $plugt_post ) );
	echo "[OK]   $plugt_slug\n";
}
exit( $plugt_failed ? 1 : 0 );
