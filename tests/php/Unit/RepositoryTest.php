<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use PlugTemplates\Library\Repository;

final class RepositoryTest extends TestCase {

	private function repository(): Repository {
		return new Repository( dirname( __DIR__, 3 ) . '/templates', 'https://site.test/wp-content/plugins/plug-templates/templates/' );
	}

	public function test_lists_and_gets_shipped_templates(): void {
		$repository = $this->repository();
		$ids        = array_column( $repository->all(), 'id' );

		$this->assertContains( 'page-accueil-agence', $ids );
		$this->assertSame( 'page', $repository->get( 'page-accueil-agence' )['type'] );
		$this->assertNull( $repository->get( 'inexistant' ) );
	}

	public function test_content_is_loaded_for_declared_variant_only(): void {
		$repository = $this->repository();
		$this->assertNotEmpty( $repository->content( 'section-temoignages', 'v3' )['content'] );
		$this->assertNull( $repository->content( 'section-temoignages', 'v4' ) );
		$this->assertNull( $repository->content( 'inexistant', 'v3' ) );
	}

	public function test_media_path_stays_inside_media_folder(): void {
		$repository = $this->repository();
		$this->assertStringEndsWith( '/section-temoignages/media/avatar-1.webp', (string) $repository->media_path( 'section-temoignages', 'avatar-1.webp' ) );
		$this->assertNull( $repository->media_path( 'section-temoignages', '../meta.json' ) );
		$this->assertNull( $repository->media_path( 'section-temoignages', '../../header-simple/media/logo.webp' ) );
		$this->assertNull( $repository->media_path( 'section-temoignages', 'absent.webp' ) );
		$this->assertNull( $repository->media_path( 'inexistant', 'avatar-1.webp' ) );
	}

	public function test_thumbnail_url(): void {
		$this->assertSame(
			'https://site.test/wp-content/plugins/plug-templates/templates/header-simple/thumbnail.webp',
			$this->repository()->thumbnail_url( 'header-simple' )
		);
	}

	public function test_missing_manifest_gives_empty_library(): void {
		$repository = new Repository( sys_get_temp_dir() . '/plugt-nowhere', 'https://site.test' );
		$this->assertSame( array(), $repository->all() );
	}
}
