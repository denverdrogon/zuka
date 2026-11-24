<?php
/**
 * Checks that the plugin version is identical everywhere.
 *
 * Usage: php bin/check-version.php [X.Y.Z]
 *
 * @package PlugTemplates
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput

$plugt_root   = dirname( __DIR__ );
$plugt_expect = $argv[1] ?? null;

/**
 * Extracts the first capture of a pattern from a file.
 *
 * @param string $plugt_file    File path.
 * @param string $plugt_pattern Regex with one capture group.
 * @return string|null
 */
function plugt_find_version( string $plugt_file, string $plugt_pattern ): ?string {
	if ( ! is_readable( $plugt_file ) ) {
		return null;
	}
	return preg_match( $plugt_pattern, (string) file_get_contents( $plugt_file ), $plugt_m ) ? $plugt_m[1] : null;
}

$plugt_package = json_decode( (string) file_get_contents( $plugt_root . '/package.json' ), true );

$plugt_found = array(
	'plug-templates.php (en-tête Version)' => plugt_find_version( $plugt_root . '/plug-templates.php', '/^\s*\*\s*Version:\s*(\S+)\s*$/m' ),
	'plug-templates.php (PLUGT_VERSION)'   => plugt_find_version( $plugt_root . '/plug-templates.php', "/define\(\s*'PLUGT_VERSION',\s*'([^']+)'/" ),
	'package.json (version)'               => is_array( $plugt_package ) && isset( $plugt_package['version'] ) ? (string) $plugt_package['version'] : null,
);

// The readme is checked only once it exists (T05).
if ( is_readable( $plugt_root . '/readme.txt' ) ) {
	$plugt_found['readme.txt (Stable tag)'] = plugt_find_version( $plugt_root . '/readme.txt', '/^Stable tag:\s*(\S+)\s*$/mi' );
}

$plugt_reference = null !== $plugt_expect ? $plugt_expect : reset( $plugt_found );
$plugt_errors    = 0;

foreach ( $plugt_found as $plugt_label => $plugt_version ) {
	if ( $plugt_version === $plugt_reference ) {
		echo "OK    $plugt_label : $plugt_version\n";
		continue;
	}
	++$plugt_errors;
	echo 'ERREUR ' . $plugt_label . ' : ' . ( $plugt_version ?? 'introuvable' ) . " (attendu : $plugt_reference)\n";
}

exit( $plugt_errors > 0 ? 1 : 0 );
