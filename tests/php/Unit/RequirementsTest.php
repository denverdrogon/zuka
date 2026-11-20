<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use PlugTemplates\Requirements;

final class RequirementsTest extends TestCase {

	private function requirements(): Requirements {
		return new Requirements(
			array(
				Requirements::PHP       => '7.4',
				Requirements::WORDPRESS => '6.6',
				Requirements::ELEMENTOR => '3.25.0',
			)
		);
	}

	public function test_all_met(): void {
		$unmet = $this->requirements()->unmet(
			array(
				Requirements::PHP       => '8.3.26',
				Requirements::WORDPRESS => '7.0',
				Requirements::ELEMENTOR => '4.1.0',
			)
		);
		$this->assertSame( array(), $unmet );
	}

	public function test_missing_elementor(): void {
		$unmet = $this->requirements()->unmet(
			array(
				Requirements::PHP       => '8.3.26',
				Requirements::WORDPRESS => '7.0',
				Requirements::ELEMENTOR => null,
			)
		);
		$this->assertSame(
			array(
				Requirements::ELEMENTOR => array(
					'required' => '3.25.0',
					'current'  => null,
				),
			),
			$unmet
		);
	}

	public function test_old_versions(): void {
		$unmet = $this->requirements()->unmet(
			array(
				Requirements::PHP       => '7.3.33',
				Requirements::WORDPRESS => '6.5.5',
				Requirements::ELEMENTOR => '3.24.9',
			)
		);
		$this->assertSame(
			array( Requirements::PHP, Requirements::WORDPRESS, Requirements::ELEMENTOR ),
			array_keys( $unmet )
		);
	}

	public function test_prerelease_wordpress_is_accepted(): void {
		$unmet = $this->requirements()->unmet(
			array(
				Requirements::PHP       => '8.4.0',
				Requirements::WORDPRESS => '7.1-beta1',
				Requirements::ELEMENTOR => '4.2.0-beta1',
			)
		);
		$this->assertSame( array(), $unmet );
	}
}
