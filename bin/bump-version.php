<?php
/**
 * Sets the plugin version in every file that carries it.
 *
 * Usage: php bin/bump-version.php X.Y.Z
 *
 * @package PlugTemplates
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput

$plugt_version = $argv[1] ?? '';

if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $plugt_version ) ) {
	fwrite( STDERR, "Version invalide « $plugt_version » : format attendu X.Y.Z (semver).\n" );
	exit( 1 );
}

$plugt_root = dirname( __DIR__ );

/**
 * Rewrites a file with a list of regex replacements.
 *
 * @param string                $plugt_file         File path.
 * @param array<string, string> $plugt_replacements Pattern => replacement.
 */
function plugt_bump_file( string $plugt_file, array $plugt_replacements ): void {
	if ( ! is_readable( $plugt_file ) ) {
		echo "Ignoré (absent) : $plugt_file\n";
		return;
	}
	$plugt_content = (string) file_get_contents( $plugt_file );
	foreach ( $plugt_replacements as $plugt_pattern => $plugt_replacement ) {
		$plugt_count   = 0;
		$plugt_content = (string) preg_replace( $plugt_pattern, $plugt_replacement, $plugt_content, 1, $plugt_count );
		if ( 0 === $plugt_count ) {
			fwrite( STDERR, "Motif introuvable dans $plugt_file : $plugt_pattern\n" );
			exit( 1 );
		}
	}
	file_put_contents( $plugt_file, $plugt_content );
	echo "Mis à jour : $plugt_file\n";
}

plugt_bump_file(
	$plugt_root . '/plug-templates.php',
	array(
		'/^(\s*\*\s*Version:\s*)\S+/m'                => '${1}' . $plugt_version,
		"/(define\(\s*'PLUGT_VERSION',\s*')[^']+(')/" => '${1}' . $plugt_version . '${2}',
	)
);
plugt_bump_file( $plugt_root . '/package.json', array( '/^(\s*"version":\s*")[^"]+(")/m' => '${1}' . $plugt_version . '${2}' ) );
plugt_bump_file( $plugt_root . '/readme.txt', array( '/^(Stable tag:\s*)\S+/mi' => '${1}' . $plugt_version ) );
