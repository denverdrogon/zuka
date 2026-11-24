<?php
/**
 * Minimal WP-CLI stubs for static analysis.
 *
 * @package PlugTemplates
 */

namespace {
	class WP_CLI {
		/** @param string $message Message. */
		public static function line( $message ) {}
		/** @param string $message Message. */
		public static function success( $message ) {}
		/** @param string $message Message. */
		public static function warning( $message ) {}
		/** @param string $message Message. */
		public static function error( $message ) {}
		/**
		 * @param string $name     Command name.
		 * @param mixed  $callable Callable or object.
		 */
		public static function add_command( $name, $callable ) {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param string       $format Format.
	 * @param array<mixed> $items  Items.
	 * @param array<mixed> $fields Fields.
	 */
	function format_items( $format, $items, $fields ) {}
}
