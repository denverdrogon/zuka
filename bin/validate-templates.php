<?php
/**
 * Validates every template in templates/.
 *
 * Usage: php bin/validate-templates.php [--strict]
 *   --strict  warnings also fail.
 * Exit code: 0 = OK, 1 = errors.
 *
 * @package PlugTemplates
 */

$plugt_root = require __DIR__ . '/bootstrap.php';

use PlugTemplates\Library\ManifestBuilder;

$plugt_strict = in_array( '--strict', $argv, true );
$plugt_result = ( new ManifestBuilder() )->build( $plugt_root . '/templates' );

$plugt_errors   = 0;
$plugt_warnings = 0;

if ( array() === $plugt_result['reports'] ) {
	fwrite( STDERR, "Aucun template trouvé dans templates/.\n" );
	exit( 1 );
}

foreach ( $plugt_result['reports'] as $plugt_slug => $plugt_report ) {
	$plugt_ok = array() === $plugt_report['errors'];
	echo ( $plugt_ok ? '[OK]   ' : '[FAIL] ' ) . $plugt_slug . "\n";
	foreach ( $plugt_report['errors'] as $plugt_message ) {
		echo "       ✗ $plugt_message\n";
	}
	foreach ( $plugt_report['warnings'] as $plugt_message ) {
		echo "       ! $plugt_message\n";
	}
	$plugt_errors   += count( $plugt_report['errors'] );
	$plugt_warnings += count( $plugt_report['warnings'] );
}

printf( "\n%d template(s), %d erreur(s), %d avertissement(s).\n", count( $plugt_result['reports'] ), $plugt_errors, $plugt_warnings );

exit( $plugt_errors > 0 || ( $plugt_strict && $plugt_warnings > 0 ) ? 1 : 0 );
