<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\MockObject\MockObject;
use PlugTemplates\Elementor\Adapter;
use PlugTemplates\Library\ContentTransformer;
use PlugTemplates\Library\Importer;
use PlugTemplates\Library\MediaSource;
use PlugTemplates\Library\Repository;
use WP_Error;

final class ImporterTest extends TestCase {

	/**
	 * Elementor adapter mock.
	 *
	 * @var Adapter&MockObject
	 */
	private $elementor;

	/**
	 * Files passed to the media source.
	 *
	 * @var list<string>
	 */
	private array $media_calls = array();

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		$this->elementor = $this->createMock( Adapter::class );
		$this->elementor->method( 'version' )->willReturn( '4.1.0' );
	}

	private function importer( ?Repository $repository = null ): Importer {
		$media = new class( $this->media_calls ) implements MediaSource {
			/** @var list<string> */
			private $calls;

			/** @param list<string> $calls Calls log. */
			public function __construct( array &$calls ) {
				$this->calls = &$calls;
			}

			public function resolve( string $template_id, string $path ): ?array {
				$this->calls[] = basename( $path );
				return array(
					'id'  => 100 + count( $this->calls ),
					'url' => 'https://site.test/uploads/' . basename( $path ),
				);
			}
		};

		return new Importer(
			$repository ?? new Repository( dirname( __DIR__, 3 ) . '/templates', 'https://site.test/templates' ),
			$this->elementor,
			$media,
			new ContentTransformer()
		);
	}

	public function test_prepare_uses_v3_and_imports_each_media_once(): void {
		$prepared = $this->importer()->prepare( 'section-temoignages' );

		$this->assertIsArray( $prepared );
		$this->assertSame( 'v3', $prepared['variant'] );
		$this->assertSame( array( 'avatar-1.webp', 'avatar-2.webp', 'avatar-3.webp' ), $this->media_calls );
		$this->assertStringNotContainsString( '{{PLUGT_MEDIA}}', (string) json_encode( $prepared['content'] ) );
	}

	public function test_v4_variant_is_preferred_when_atomic_is_active(): void {
		$this->elementor->method( 'is_atomic_active' )->willReturn( true );
		$importer = $this->importer();

		$this->assertSame( 'v4', $importer->pick_variant( array( 'variants' => array( 'v3', 'v4' ) ) ) );
		$this->assertSame( 'v3', $importer->pick_variant( array( 'variants' => array( 'v3' ) ) ) );
	}

	public function test_v4_only_template_is_incompatible_without_atomic(): void {
		$this->elementor->method( 'is_atomic_active' )->willReturn( false );
		$error = $this->importer()->incompatibility( array( 'variants' => array( 'v4' ) ) );
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'plugt_no_variant', $error->get_error_code() );
	}

	public function test_old_elementor_is_incompatible(): void {
		$elementor = $this->createMock( Adapter::class );
		$elementor->method( 'version' )->willReturn( '3.20.0' );
		$this->elementor = $elementor;

		$result = $this->importer()->prepare( 'header-simple' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'plugt_elementor_too_old', $result->get_error_code() );
	}

	public function test_unknown_template_and_invalid_mode(): void {
		$importer = $this->importer();

		$missing = $importer->prepare( 'inexistant' );
		$this->assertInstanceOf( WP_Error::class, $missing );
		$this->assertSame( 404, $missing->get_error_data()['status'] );

		$mode = $importer->import( 'header-simple', 'zip' );
		$this->assertInstanceOf( WP_Error::class, $mode );
		$this->assertSame( 'plugt_invalid_mode', $mode->get_error_code() );
	}

	public function test_import_as_page_creates_document_and_records_source(): void {
		$this->elementor->expects( $this->once() )
			->method( 'create_page' )
			->with( 'Ma page', $this->isType( 'array' ), $this->callback( static fn( $settings ) => 'elementor_header_footer' === $settings['template'] ) )
			->willReturn( 55 );
		$this->elementor->method( 'edit_url' )->with( 55 )->willReturn( 'https://site.test/wp-admin/post.php?post=55&action=elementor' );

		Functions\expect( 'update_post_meta' )->once()->with( 55, Importer::SOURCE_META, 'page-accueil-agence@1.0.0' );
		Functions\expect( 'update_post_meta' )->once()->with( 55, Importer::VARIANT_META, 'v3' );

		$result = $this->importer()->import( 'page-accueil-agence', Importer::MODE_PAGE, 'Ma page' );

		$this->assertSame( 55, $result['post_id'] );
		$this->assertStringContainsString( 'action=elementor', $result['edit_url'] );
	}

	public function test_import_to_library_uses_template_type_and_title(): void {
		$this->elementor->expects( $this->once() )
			->method( 'create_library_template' )
			->with( 'En-tête simple', 'section', $this->isType( 'array' ), $this->isType( 'array' ) )
			->willReturn( 77 );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$result = $this->importer()->import( 'header-simple', Importer::MODE_LIBRARY );
		$this->assertSame( 77, $result['post_id'] );
	}

	public function test_import_for_editor_returns_content_without_creating_posts(): void {
		$this->elementor->expects( $this->never() )->method( 'create_page' );
		$this->elementor->expects( $this->never() )->method( 'create_library_template' );

		$result = $this->importer()->import( 'header-simple', Importer::MODE_EDITOR );
		$this->assertSame( 'v3', $result['variant'] );
		$this->assertNotEmpty( $result['content'] );
		$this->assertArrayNotHasKey( 'post_id', $result );
	}

	public function test_creation_error_is_returned(): void {
		$this->elementor->method( 'create_page' )->willReturn( new WP_Error( 'db', 'Échec' ) );
		$result = $this->importer()->import( 'page-accueil-agence', Importer::MODE_PAGE );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'db', $result->get_error_code() );
	}
}
