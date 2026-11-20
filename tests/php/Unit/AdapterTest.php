<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use Brain\Monkey\Functions;
use PlugTemplates\Elementor\Adapter;

final class AdapterTest extends TestCase {

	public function test_not_loaded_without_elementor_action(): void {
		Functions\expect( 'did_action' )->with( 'elementor/loaded' )->andReturn( 0 );
		$adapter = new Adapter();
		$this->assertFalse( $adapter->is_loaded() );
		$this->assertFalse( $adapter->is_atomic_active() );
	}

	public function test_version_null_without_constant(): void {
		$this->assertNull( ( new Adapter() )->version() );
	}
}
