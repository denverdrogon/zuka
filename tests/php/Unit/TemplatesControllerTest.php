<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use Brain\Monkey\Functions;
use PlugTemplates\Elementor\Adapter;
use PlugTemplates\Library\ContentTransformer;
use PlugTemplates\Library\Importer;
use PlugTemplates\Library\MediaSource;
use PlugTemplates\Library\Repository;
use PlugTemplates\Rest\TemplatesController;

final class TemplatesControllerTest extends TestCase {

	public function test_list_exposes_display_fields(): void {
		Functions\stubTranslationFunctions();
		$elementor = $this->createMock( Adapter::class );
		$elementor->method( 'version' )->willReturn( '4.1.0' );
		$elementor->method( 'is_atomic_active' )->willReturn( false );

		$repository = new Repository( dirname( __DIR__, 3 ) . '/templates', 'https://site.test/templates' );
		$media      = $this->createMock( MediaSource::class );
		$controller = new TemplatesController( $repository, new Importer( $repository, $elementor, $media, new ContentTransformer() ) );

		$items = $controller->list_templates()->get_data();
		$byId  = array_column( $items, null, 'id' );

		$this->assertArrayHasKey( 'budget-transparency', $byId );
		$budget = $byId['budget-transparency'];
		$this->assertSame( 'TPL-01', $budget['code'] );
		$this->assertSame( 'public-sector', $budget['category'] );
		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/', $budget['accent'] );
		$this->assertNotEmpty( $budget['motion'] );
		$this->assertNull( $budget['preview'] );
		$this->assertNull( $byId['header-simple']['motion'] );
	}
}
