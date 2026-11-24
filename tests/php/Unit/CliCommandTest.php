<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\MockObject\MockObject;
use PlugTemplates\Cli\Command;
use PlugTemplates\Cli\Console;
use PlugTemplates\Library\Importer;
use PlugTemplates\Library\Repository;
use WP_Error;

final class CliCommandTest extends TestCase {

	/**
	 * @var Repository&MockObject
	 */
	private $repository;

	/**
	 * @var Importer&MockObject
	 */
	private $importer;

	/**
	 * @var Console&MockObject
	 */
	private $console;

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		$this->repository = $this->createMock( Repository::class );
		$this->importer   = $this->createMock( Importer::class );
		$this->console    = $this->createMock( Console::class );
	}

	private function command( string $dir = '' ): Command {
		return new Command( $this->repository, $this->importer, '' === $dir ? dirname( __DIR__, 3 ) . '/templates' : $dir, $this->console );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function entry( string $id, string $category ): array {
		return array(
			'id'       => $id,
			'code'     => 'TPL-01',
			'title'    => 'Titre ' . $id,
			'type'     => 'section',
			'category' => $category,
			'version'  => '1.0.0',
			'variants' => array( 'v3', 'v4' ),
		);
	}

	public function test_list_builds_rows_and_filters_by_category(): void {
		$this->repository->method( 'all' )->willReturn( array( $this->entry( 'a', 'hero' ), $this->entry( 'b', 'footer' ) ) );
		$this->importer->method( 'incompatibility' )->willReturn( null );

		$this->console->expects( $this->once() )->method( 'table' )->with(
			'json',
			array(
				array(
					'id'         => 'b',
					'code'       => 'TPL-01',
					'title'      => 'Titre b',
					'type'       => 'section',
					'category'   => 'footer',
					'version'    => '1.0.0',
					'variants'   => 'v3,v4',
					'compatible' => 'yes',
				),
			),
			array( 'id', 'code', 'title', 'type', 'category', 'version', 'variants', 'compatible' )
		);

		$this->command()->list_(
			array(),
			array(
				'format'   => 'json',
				'category' => 'footer',
			)
		);
	}

	public function test_list_flags_incompatible_templates(): void {
		$this->repository->method( 'all' )->willReturn( array( $this->entry( 'a', 'hero' ) ) );
		$this->importer->method( 'incompatibility' )->willReturn( new WP_Error( 'x', 'non' ) );

		$this->console->expects( $this->once() )->method( 'table' )->with(
			'table',
			$this->callback( static fn( array $rows ) => 'no' === $rows[0]['compatible'] ),
			$this->anything()
		);

		$this->command()->list_( array(), array() );
	}

	public function test_import_creates_a_page_by_default(): void {
		$this->importer->expects( $this->once() )->method( 'import' )->with( 'header-simple', 'page', '' )->willReturn(
			array(
				'template_id' => 'header-simple',
				'post_id'     => 42,
				'edit_url'    => 'https://site.test/edit/42',
			)
		);
		$this->console->expects( $this->once() )->method( 'success' );
		$this->console->expects( $this->once() )->method( 'line' )->with( 'https://site.test/edit/42' );

		$this->command()->import( array( 'header-simple' ), array() );
	}

	public function test_import_refuses_editor_mode(): void {
		$this->importer->expects( $this->never() )->method( 'import' );
		$this->console->expects( $this->once() )->method( 'error' );

		$this->command()->import( array( 'header-simple' ), array( 'mode' => 'editor' ) );
	}

	public function test_import_reports_importer_errors(): void {
		$this->importer->method( 'import' )->willReturn( new WP_Error( 'plugt_not_found', 'Template introuvable.' ) );
		$this->console->expects( $this->once() )->method( 'error' )->with( 'Template introuvable.' );

		$this->command()->import( array( 'nope' ), array() );
	}

	public function test_validate_succeeds_on_the_shipped_templates(): void {
		$this->console->expects( $this->never() )->method( 'error' );
		$this->console->expects( $this->once() )->method( 'success' );

		$this->command()->validate( array(), array() );
	}

	public function test_validate_fails_on_an_invalid_template(): void {
		$dir = sys_get_temp_dir() . '/plugt-cli-' . uniqid();
		mkdir( $dir . '/broken', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->console->expects( $this->once() )->method( 'error' );
		$this->console->expects( $this->never() )->method( 'success' );

		try {
			$this->command( $dir )->validate( array(), array() );
		} finally {
			rmdir( $dir . '/broken' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
}
