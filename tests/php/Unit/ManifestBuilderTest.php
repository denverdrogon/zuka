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

	public function test_display_fields_are_emitted(): void {
		$result = ( new ManifestBuilder() )->build( $this->templates_dir() );
		foreach ( $result['manifest']['templates'] as $entry ) {
			foreach ( array( 'code', 'sector', 'motion', 'accent', 'preview' ) as $key ) {
				$this->assertArrayHasKey( $key, $entry );
			}
			$this->assertContains( $entry['category'], \PlugTemplates\Library\Schema::CATEGORIES );
		}
	}

	public function test_duplicate_code_is_reported(): void {
		$root = sys_get_temp_dir() . '/plugt-dup-' . bin2hex( random_bytes( 4 ) );
		foreach ( array( 'section-temoignages', 'header-simple' ) as $slug ) {
			$this->copy_dir( $this->templates_dir() . '/' . $slug, $root . '/' . $slug );
			$meta         = json_decode( (string) file_get_contents( $root . '/' . $slug . '/meta.json' ), true );
			$meta['code'] = 'TPL-99';
			file_put_contents( $root . '/' . $slug . '/meta.json', json_encode( $meta ) );
		}
		$result = ( new ManifestBuilder() )->build( $root );
		$this->assertCount( 1, $result['manifest']['templates'] );
		$this->assertStringContainsString( 'TPL-99', implode( ' ', $result['reports']['section-temoignages']['errors'] ) );
		$this->copy_dir( '', $root, true );
	}

	public function test_duplicate_thumbnail_is_reported(): void {
		$root = sys_get_temp_dir() . '/plugt-thumb-' . bin2hex( random_bytes( 4 ) );
		foreach ( array( 'section-temoignages', 'header-simple' ) as $slug ) {
			$this->copy_dir( $this->templates_dir() . '/' . $slug, $root . '/' . $slug );
		}
		copy( $root . '/header-simple/thumbnail.webp', $root . '/section-temoignages/thumbnail.webp' );
		$result = ( new ManifestBuilder() )->build( $root );
		$this->assertSame( array(), $result['reports']['header-simple']['errors'] );
		$this->assertStringContainsString( 'header-simple', implode( ' ', $result['reports']['section-temoignages']['errors'] ) );
		$this->copy_dir( '', $root, true );
	}

	private function copy_dir(string $from, string $to, bool $remove = false ): void {
		if ( $remove ) {
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $to, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $it as $file ) {
				$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
			}
			rmdir( $to );
			return;
		}
		mkdir( $to, 0777, true );
		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $from, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $it as $file ) {
			$target = $to . '/' . $it->getSubPathname();
			$file->isDir() ? @mkdir( $target, 0777, true ) : copy( $file->getPathname(), $target );
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
