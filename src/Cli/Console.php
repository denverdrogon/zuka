<?php
/**
 * Thin wrapper around WP-CLI output, so commands stay testable.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Cli;

/**
 * WP-CLI console.
 */
class Console {

	/**
	 * Prints a line.
	 *
	 * @param string $message Message.
	 */
	public function line( string $message ): void {
		\WP_CLI::line( $message );
	}

	/**
	 * Prints a success message.
	 *
	 * @param string $message Message.
	 */
	public function success( string $message ): void {
		\WP_CLI::success( $message );
	}

	/**
	 * Prints a warning.
	 *
	 * @param string $message Message.
	 */
	public function warning( string $message ): void {
		\WP_CLI::warning( $message );
	}

	/**
	 * Prints an error and stops the command with exit code 1.
	 *
	 * @param string $message Message.
	 */
	public function error( string $message ): void {
		\WP_CLI::error( $message );
	}

	/**
	 * Prints rows in the requested format (table, json, csv…).
	 *
	 * @param string                          $format Output format.
	 * @param list<array<string, string|int>> $items  Rows.
	 * @param string[]                        $fields Columns.
	 */
	public function table( string $format, array $items, array $fields ): void {
		\WP_CLI\Utils\format_items( $format, $items, $fields );
	}
}
