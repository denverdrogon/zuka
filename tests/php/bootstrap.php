<?php
/**
 * Unit test bootstrap (no WordPress; WP functions are mocked with Brain Monkey).
 *
 * @package PlugTemplates
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/phpstan-bootstrap.php';
require_once __DIR__ . '/wp-stubs.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
