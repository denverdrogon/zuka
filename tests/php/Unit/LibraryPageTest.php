<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PlugTemplates\Admin\LibraryPage;
use PlugTemplates\Elementor\Adapter;
use PlugTemplates\Library\Schema;

final class LibraryPageTest extends TestCase {

	/**
	 * Temporary build folder.
	 *
	 * @var string
	 */
	private string $build = '';

	protected function set_up(): void {
		parent::set_up();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		$this->build = sys_get_temp_dir() . '/plugt-build-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->build );
		file_put_contents( $this->build . '/admin.asset.php', "<?php return array( 'dependencies' => array( 'react', 'wp-api-fetch' ), 'version' => 'abc' );" );
	}

	protected function tear_down(): void {
		foreach ( (array) glob( $this->build . '/*' ) as $file ) {
			unlink( (string) $file );
		}
		rmdir( $this->build );
		parent::tear_down();
	}

	private function page( ?string $build = null ): LibraryPage {
		$adapter = $this->createMock( Adapter::class );
		$adapter->method( 'library_admin_url' )->willReturn( 'https://site.test/wp-admin/edit.php?post_type=elementor_library' );
		$adapter->method( 'is_atomic_active' )->willReturn( false );
		return new LibraryPage( $adapter, $build ?? $this->build, 'https://site.test/build/' );
	}

	private function page_with_menu(): LibraryPage {
		Functions\when( 'add_menu_page' )->justReturn( 'toplevel_page_plug-templates' );
		$page = $this->page();
		$page->add_menu();
		return $page;
	}

	public function test_register_hooks_menu_and_assets(): void {
		$page = $this->page();
		Actions\expectAdded( 'admin_menu' )->once()->with( array( $page, 'add_menu' ) );
		Actions\expectAdded( 'admin_enqueue_scripts' )->once()->with( array( $page, 'enqueue' ) );
		$page->register();
	}

	public function test_menu_uses_edit_posts_and_slug(): void {
		Functions\expect( 'add_menu_page' )
			->once()
			->with( 'Plug Templates', 'Plug Templates', 'edit_posts', 'plug-templates', \Mockery::type( 'array' ), 'dashicons-layout', 58 )
			->andReturn( 'toplevel_page_plug-templates' );
		$this->page()->add_menu();
	}

	public function test_no_assets_on_other_pages(): void {
		$page = $this->page_with_menu();
		Functions\expect( 'wp_enqueue_script' )->never();
		Functions\expect( 'wp_enqueue_style' )->never();
		$page->enqueue( 'edit.php' );
		$page->enqueue( 'index.php' );
	}

	public function test_enqueues_app_with_config_on_library_page(): void {
		$page = $this->page_with_menu();
		Functions\when( 'current_user_can' )->alias( static fn( $cap ) => 'edit_pages' !== $cap );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with( LibraryPage::HANDLE, 'https://site.test/build/admin.js', array( 'react', 'wp-api-fetch' ), 'abc', true );
		Functions\expect( 'wp_enqueue_style' )->once();
		Functions\expect( 'wp_set_script_translations' )->once();

		$inline = '';
		Functions\when( 'wp_add_inline_script' )->alias(
			static function ( $handle, $code ) use ( &$inline ) {
				$inline = $code;
				return true;
			}
		);

		$page->enqueue( 'toplevel_page_plug-templates' );

		$this->assertStringStartsWith( 'window.plugtLibrary = ', $inline );
		$config = json_decode( substr( rtrim( $inline, ';' ), strlen( 'window.plugtLibrary = ' ) ), true );
		$this->assertSame( '/plugt/v1', $config['restPath'] );
		$this->assertFalse( $config['caps']['importPage'] );
		$this->assertTrue( $config['caps']['importLibrary'] );
		$this->assertSame( Schema::CATEGORIES, array_keys( $config['categories'] ) );
	}

	public function test_render_root_or_notice(): void {
		ob_start();
		$this->page()->render();
		$this->assertStringContainsString( 'id="plugt-library-root"', (string) ob_get_clean() );

		ob_start();
		$this->page( sys_get_temp_dir() . '/plugt-nowhere' )->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringNotContainsString( 'plugt-library-root', $html );
	}
}
