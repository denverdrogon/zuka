<?php
/**
 * Base test case with Brain Monkey lifecycle.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use Brain\Monkey;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

abstract class TestCase extends PolyfillTestCase {

	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();
	}

	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}
}
