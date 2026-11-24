<?php
/**
 * Data migrations runner.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Migrations;

/**
 * Runs once, in order, the data migrations between versions.
 */
class Runner {

	const OPTION  = 'plugt_db_version';
	const LOCK    = 'plugt_migrating';
	const CURRENT = 1;

	/**
	 * Applies pending migrations.
	 */
	public function run(): void {
		$stored = (int) get_option( self::OPTION, 0 );
		if ( $stored >= self::CURRENT ) {
			return;
		}

		if ( get_transient( self::LOCK ) ) {
			return;
		}
		set_transient( self::LOCK, 1, 5 * MINUTE_IN_SECONDS );

		try {
			for ( $version = $stored + 1; $version <= self::CURRENT; $version++ ) {
				$this->{"migrate_$version"}();
				update_option( self::OPTION, $version, false );
			}
		} catch ( \Throwable $e ) {
			error_log( '[plugt] Migration failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		delete_transient( self::LOCK );
	}

	/**
	 * Initial installation marker.
	 */
	protected function migrate_1(): void {
	}
}
