<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use Brain\Monkey\Filters;
use PlugTemplates\Updates\Updater;

final class UpdaterTest extends TestCase {

	/**
	 * Builds an Updater whose checker is a spy.
	 *
	 * @param object|null $checker   Checker returned by build_checker().
	 * @param bool        $available Whether PUC is "loaded".
	 * @param int         $builds    Reference counting build_checker() calls.
	 */
	private function updater( $checker, bool $available, &$builds ): Updater {
		$builds = 0;
		$counter = &$builds;
		return new class( 'https://github.com/o/r/', '/p/plug-templates.php', 'plug-templates', $checker, $available, $counter ) extends Updater {
			private $spy;
			private $available;
			private $counter;
			public function __construct( $repo, $file, $slug, $spy, $available, &$counter ) {
				parent::__construct( $repo, $file, $slug );
				$this->spy       = $spy;
				$this->available = $available;
				$this->counter   = &$counter;
			}
			protected function is_available(): bool {
				return $this->available;
			}
			protected function build_checker() {
				++$this->counter;
				return $this->spy;
			}
		};
	}

	private function checker(): object {
		$api = \Mockery::mock();
		$api->shouldReceive( 'enableReleaseAssets' )->once()->with( '/plug-templates\.zip$/' );
		$checker = \Mockery::mock();
		$checker->shouldReceive( 'getVcsApi' )->andReturn( $api );
		$checker->shouldReceive( 'setBranch' )->once()->with( 'main' );
		return $checker;
	}

	public function test_filter_false_prevents_build(): void {
		Filters\expectApplied( 'plugt_update_enabled' )->once()->andReturn( false );
		$this->updater( null, true, $builds )->register();
		$this->assertSame( 0, $builds );
	}

	public function test_missing_puc_is_silent(): void {
		$this->updater( null, false, $builds )->register();
		$this->assertSame( 0, $builds );
	}

	public function test_builds_and_passes_token(): void {
		if ( ! defined( 'PLUGT_GITHUB_TOKEN' ) ) {
			define( 'PLUGT_GITHUB_TOKEN', 'tok' );
		}
		$checker = $this->checker();
		$checker->shouldReceive( 'setAuthentication' )->once()->with( 'tok' );
		$this->updater( $checker, true, $builds )->register();
		$this->assertSame( 1, $builds );
	}
}
