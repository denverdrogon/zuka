<?php
/**
 * Generates templates/manifest.json.
 *
 * Usage: php bin/build-manifest.php [--check]
 *   --check  do not write; fail if manifest.json is missing or out of date (CI).
 * Exit code: 0 = OK, 1 = invalid templates or outdated manifest.
 *
 * @package PlugTemplates
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- CLI script, runs outside WordPress.

$plugt_root = require __DIR__ . '/bootstrap.php';

use PlugTemplates\Library\ManifestBuilder;

$plugt_check  = in_array( '--check', $argv, true );
$plugt_file   = $plugt_root . '/templates/manifest.json';
$plugt_result = ( new ManifestBuilder() )->build( $plugt_root . '/templates' );

foreach ( $plugt_result['reports'] as $plugt_slug => $plugt_report ) {
	if ( array() !== $plugt_report['errors'] ) {
		fwrite( STDERR, "Template invalide : $plugt_slug. Lancez `composer templates:validate`.\n" );
		exit( 1 );
	}
}

$plugt_json = ManifestBuilder::encode( $plugt_result['manifest'] );

if ( $plugt_check ) {
	$plugt_current = is_file( $plugt_file ) ? str_replace( "\r\n", "\n", (string) file_get_contents( $plugt_file ) ) : '';
	if ( $plugt_current !== $plugt_json ) {
		fwrite( STDERR, "templates/manifest.json n'est pas à jour. Lancez `composer templates:build`.\n" );
		exit( 1 );
	}
	echo "manifest.json à jour.\n";
	exit( 0 );
}

file_put_contents( $plugt_file, $plugt_json );
printf( "manifest.json écrit (%d template(s)).\n", count( $plugt_result['manifest']['templates'] ) );
