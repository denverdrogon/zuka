<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use Brain\Monkey\Functions;
use PlugTemplates\Elementor\Adapter;
use PlugTemplates\Frontend\MotionAssets;

final class MotionAssetsTest extends TestCase {

	public function test_detects_motion_classes(): void {
		$this->assertTrue( MotionAssets::uses_motion( 'foo plugt-m-tabs' ) );
		$this->assertTrue( MotionAssets::uses_motion( 'plugt-glass' ) );
		$this->assertFalse( MotionAssets::uses_motion( 'foo plugt-other bar' ) );
		$this->assertFalse( MotionAssets::uses_motion( '' ) );
	}

	public function test_enqueues_only_for_motion_classes(): void {
		$assets = new MotionAssets( new Adapter(), '/nope', 'https://example.test/build' );

		Functions\when( 'wp_style_is' )->justReturn( true );
		Functions\expect( 'wp_enqueue_style' )->once()->with( MotionAssets::HANDLE );
		Functions\expect( 'wp_enqueue_script' )->once()->with( MotionAssets::HANDLE );

		$assets->maybe_enqueue( 'elementor-foo' );
		$assets->maybe_enqueue( 'plugt-m-accordion__panel is-active' );
	}

	public function test_queried_post_scan(): void {
		$adapter = $this->createMock( Adapter::class );
		$adapter->method( 'post_data_contains' )->with( 42, 'plugt-' )->willReturn( false );
		$assets = new MotionAssets( $adapter, '/nope', 'https://example.test/build' );

		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_queried_object_id' )->justReturn( 42 );
		Functions\expect( 'wp_enqueue_style' )->never();

		$assets->enqueue_for_queried_post();
	}
}
