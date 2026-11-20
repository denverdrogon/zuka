<?php
/**
 * Shared bootstrap for CLI scripts (outside WordPress).
 *
 * @package PlugTemplates
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$plugt_root = dirname( __DIR__ );

if ( is_readable( $plugt_root . '/vendor/autoload.php' ) ) {
	require_once $plugt_root . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class_name ) use ( $plugt_root ) {
			$prefix = 'PlugTemplates\\';
			if ( 0 === strpos( $class_name, $prefix ) ) {
				$file = $plugt_root . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		}
	);
}

return $plugt_root;
