<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use PlugTemplates\Library\ContentTransformer;
use PlugTemplates\Library\Exporter;
use PlugTemplates\Library\TemplateValidator;

final class ExporterTest extends TestCase {

	/**
	 * Temporary folders to remove.
	 *
	 * @var list<string>
	 */
	private array $tmp_dirs = array();

	protected function tear_down(): void {
		foreach ( $this->tmp_dirs as $dir ) {
			$this->remove( $dir );
		}
		parent::tear_down();
	}

	/**
	 * @return array{colors: list<array{_id: string, color: string}>, typography: list<array<string, mixed>>}
	 */
	private function kit(): array {
		return array(
			'colors'     => array(
				array(
					'_id'   => 'primary',
					'color' => '#1A2B3C',
				),
			),
			'typography' => array(
				array(
					'_id'                    => 'primary',
					'typography_typography'  => 'custom',
					'typography_font_family' => 'Lora',
					'typography_font_weight' => '600',
				),
			),
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function elements( string $image_file ): array {
		return array(
			array(
				'id'       => 'aaaaaaa',
				'elType'   => 'container',
				'settings' => array(),
				'elements' => array(
					array(
						'id'         => 'bbbbbbb',
						'elType'     => 'widget',
						'widgetType' => 'image',
						'settings'   => array(
							'image' => array(
								'id'  => 12,
								'url' => 'https://site.test/uploads/logo.webp',
							),
						),
						'elements'   => array(),
					),
					array(
						'id'         => 'ccccccc',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array(
							'title'                  => 'Bonjour',
							'title_color'            => '#1a2b3c',
							'text_color'             => '#abcdef',
							'typography_typography'  => 'custom',
							'typography_font_family' => 'Lora',
							'typography_font_weight' => '600',
						),
						'elements'   => array(),
					),
					array(
						'id'         => 'ddddddd',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array(
							'typography_typography'  => 'custom',
							'typography_font_family' => 'Roboto',
							'icon_list'              => array(
								array(
									'_id'  => 'zzzzzzz',
									'text' => $image_file,
								),
							),
						),
						'elements'   => array(),
					),
				),
			),
		);
	}

	public function test_transform_media_globals_and_ids(): void {
		$dir    = $this->tmp_dir();
		$source = $dir . '/logo.webp';
		file_put_contents( $source, 'x' );

		$result = ( new Exporter() )->transform( $this->elements( 'x' ), array(), $this->kit(), static fn( int $id, string $url ) => 12 === $id ? $source : null );

		$image = $result['content'][0]['elements'][0]['settings']['image'];
		$this->assertSame( '{{PLUGT_MEDIA}}/logo.webp', $image['url'] );
		$this->assertSame( '', $image['id'] );
		$this->assertSame( array( 'logo.webp' => $source ), $result['media'] );

		$heading = $result['content'][0]['elements'][1]['settings'];
		$this->assertSame( 'globals/colors?id=primary', $heading['__globals__']['title_color'] );
		$this->assertSame( 'globals/typography?id=primary', $heading['__globals__']['typography_typography'] );
		$this->assertArrayNotHasKey( 'title_color', $heading );
		$this->assertArrayNotHasKey( 'typography_font_family', $heading );
		$this->assertSame( '#abcdef', $heading['text_color'] );

		$this->assertSame( array( 'couleur #abcdef', 'police Roboto' ), $result['hardcoded'] );

		$ids = array();
		array_walk_recursive(
			$result['content'],
			static function ( $value, $key ) use ( &$ids ): void {
				if ( 'id' === $key && is_string( $value ) && '' !== $value ) {
					$ids[] = $value;
				}
			}
		);
		$this->assertCount( 4, $ids );
		foreach ( $ids as $id ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9]{7}$/', $id );
		}
		$this->assertNotContains( 'aaaaaaa', $ids );
		$this->assertNotSame( 'zzzzzzz', $result['content'][0]['elements'][2]['settings']['icon_list'][0]['_id'] );
	}

	public function test_missing_media_is_reported(): void {
		$result = ( new Exporter() )->transform( $this->elements( 'x' ), array(), $this->kit(), static fn() => null );
		$this->assertSame( array(), $result['media'] );
		$this->assertStringContainsString( 'Média #12 introuvable', $result['warnings'][0] );
	}

	public function test_export_is_the_inverse_of_the_content_transformer(): void {
		$dir    = $this->tmp_dir();
		$source = $dir . '/logo.webp';
		file_put_contents( $source, 'x' );

		$exported = ( new Exporter() )->transform( $this->elements( 'x' ), array(), $this->kit(), static fn() => $source );
		$imported = ( new ContentTransformer() )->transform(
			$exported['content'],
			static fn( string $relative ) => array(
				'id'  => 12,
				'url' => 'https://site.test/uploads/' . $relative,
			)
		);

		$image = $imported[0]['elements'][0]['settings']['image'];
		$this->assertSame( 12, $image['id'] );
		$this->assertSame( 'https://site.test/uploads/logo.webp', $image['url'] );
		$this->assertSame( 'globals/colors?id=primary', $imported[0]['elements'][1]['settings']['__globals__']['title_color'] );
	}

	public function test_write_produces_a_valid_template_and_refuses_overwrite(): void {
		$dir    = $this->tmp_dir();
		$source = $dir . '/logo.webp';
		file_put_contents( $source, 'x' );

		$exporter = new Exporter();
		$result   = $exporter->transform( $this->elements( 'x' ), array( 'template' => 'elementor_canvas' ), $this->kit(), static fn() => $source );
		$meta     = $exporter->meta( 'mon-export', 'Mon export', 'section', 'hero', 'TPL-99', '2026-11-24' );

		$folder = $exporter->write( $dir, 'mon-export', $meta, $result, false );
		file_put_contents( $folder . '/thumbnail.webp', 'x' );

		$report = ( new TemplateValidator() )->validate( $folder );
		$this->assertSame( array(), $report['errors'] );
		$this->assertSame( '2026-11-24', json_decode( (string) file_get_contents( $folder . '/meta.json' ), true )['added'] );
		$this->assertSame( 'elementor_canvas', json_decode( (string) file_get_contents( $folder . '/content.v3.json' ), true )['page_settings']['template'] );
		$this->assertFileExists( $folder . '/media/logo.webp' );

		$this->expectException( \RuntimeException::class );
		$exporter->write( $dir, 'mon-export', $meta, $result, false );
	}

	public function test_next_code_follows_existing_codes(): void {
		$dir = $this->tmp_dir();
		mkdir( $dir . '/a' );
		mkdir( $dir . '/b' );
		file_put_contents( $dir . '/a/meta.json', '{"code":"TPL-07"}' );
		file_put_contents( $dir . '/b/meta.json', '{"code":"TPL-12"}' );
		$this->assertSame( 'TPL-13', ( new Exporter() )->next_code( $dir ) );
	}

	private function tmp_dir(): string {
		$dir = sys_get_temp_dir() . '/plugt-export-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir, 0777, true );
		$this->tmp_dirs[] = $dir;
		return $dir;
	}

	private function remove( string $path ): void {
		if ( is_dir( $path ) ) {
			foreach ( array_diff( (array) scandir( $path ), array( '.', '..' ) ) as $entry ) {
				$this->remove( $path . '/' . $entry );
			}
			rmdir( $path );
		} elseif ( is_file( $path ) ) {
			unlink( $path );
		}
	}
}
