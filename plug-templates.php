<?php
/**
 * Plugin Name:       Zuka
 * Description:       Bibliothèque de templates personnalisés pour Elementor (v3 et v4 Atomic), compatibles Elementor Free.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Requires Plugins:  elementor
 * Author:            Zuka
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plug-templates
 * Domain Path:       /languages
 * Update URI:        plug-templates
 *
 * Elementor tested up to: 4.1
 *
 * @package PlugTemplates
 */

defined( 'ABSPATH' ) || exit;

define( 'PLUGT_VERSION', '0.1.0' );
define( 'PLUGT_FILE', __FILE__ );
define( 'PLUGT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PLUGT_URL', plugin_dir_url( __FILE__ ) );
define( 'PLUGT_MIN_PHP', '7.4' );
define( 'PLUGT_MIN_WP', '7.0' );
define( 'PLUGT_MIN_ELEMENTOR', '3.25.0' );

if ( ! defined( 'PLUGT_UPDATE_REPO' ) ) {
	define( 'PLUGT_UPDATE_REPO', 'https://github.com/denverdrogon/zuka/' );
}

if ( is_readable( PLUGT_PATH . 'vendor/autoload.php' ) ) {
	require_once PLUGT_PATH . 'vendor/autoload.php';
} else {
	// Fallback PSR-4 autoloader (dev checkout without `composer install`).
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'PlugTemplates\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$file = PLUGT_PATH . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

add_action( 'plugins_loaded', array( \PlugTemplates\Plugin::class, 'boot' ), 20 );
