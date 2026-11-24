<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use Brain\Monkey\Functions;
use PlugTemplates\Migrations\Runner;

final class RunnerTest extends TestCase {

	public function test_fresh_install_runs_to_current(): void {
		Functions\expect( 'get_option' )->once()->with( Runner::OPTION, 0 )->andReturn( 0 );
		Functions\expect( 'get_transient' )->once()->with( Runner::LOCK )->andReturn( false );
		Functions\expect( 'set_transient' )->once();
		Functions\expect( 'update_option' )->once()->with( Runner::OPTION, 1, false );
		Functions\expect( 'delete_transient' )->once()->with( Runner::LOCK );
		( new Runner() )->run();
	}

	public function test_up_to_date_does_nothing(): void {
		Functions\expect( 'get_option' )->once()->andReturn( Runner::CURRENT );
		Functions\expect( 'get_transient' )->never();
		Functions\expect( 'update_option' )->never();
		( new Runner() )->run();
	}

	public function test_lock_prevents_run(): void {
		Functions\expect( 'get_option' )->once()->andReturn( 0 );
		Functions\expect( 'get_transient' )->once()->andReturn( 1 );
		Functions\expect( 'set_transient' )->never();
		Functions\expect( 'update_option' )->never();
		( new Runner() )->run();
	}

	public function test_exception_keeps_version(): void {
		Functions\expect( 'get_option' )->once()->andReturn( 0 );
		Functions\expect( 'get_transient' )->once()->andReturn( false );
		Functions\expect( 'set_transient' )->once();
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'delete_transient' )->once();
		$log = ini_get( 'error_log' );
		ini_set( 'error_log', sys_get_temp_dir() . '/plugt-test.log' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		$runner = new class() extends Runner {
			protected function migrate_1(): void {
				throw new \RuntimeException( 'boom' );
			}
		};
		$runner->run();
		ini_set( 'error_log', (string) $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	}
}
