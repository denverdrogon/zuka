<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use PlugTemplates\Library\ManifestBuilder;

final class ManifestBuilderTest extends TestCase {

	private function templates_dir(): string {
		return dirname( __DIR__, 3 ) . '/templates';
	}

	public function test_builds_sorted_entries_for_shipped_templates(): void {
		$result = ( new ManifestBuilder() )->build( $this->templates_dir() );
		$ids    = array_column( $result['manifest']['templates'], 'id' );

		$this->assertSame( ManifestBuilder::SCHEMA_VERSION, $result['manifest']['schema'] );
		$this->assertNotEmpty( $ids );
		$sorted = $ids;
		sort( $sorted, SORT_STRING );
		$this->assertSame( $sorted, $ids );

		foreach ( $result['manifest']['templates'] as $entry ) {
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', $entry['hash'] );
			$this->assertSame( $entry['id'] . '/thumbnail.webp', $entry['thumbnail'] );
			$this->assertNotEmpty( $entry['widgets'] );
		}
	}

	public function test_output_is_deterministic(): void {
		$builder = new ManifestBuilder();
		$first   = ManifestBuilder::encode( $builder->build( $this->templates_dir() )['manifest'] );
		$second  = ManifestBuilder::encode( $builder->build( $this->templates_dir() )['manifest'] );
		$this->assertSame( $first, $second );
	}

	public function test_committed_manifest_is_up_to_date(): void {
		$expected = ManifestBuilder::encode( ( new ManifestBuilder() )->build( $this->templates_dir() )['manifest'] );
		$current  = str_replace( "\r\n", "\n", (string) file_get_contents( $this->templates_dir() . '/manifest.json' ) );
		$this->assertSame( $expected, $current, 'Lancez `composer templates:build`.' );
	}
}
