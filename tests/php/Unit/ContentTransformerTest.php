<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use PlugTemplates\Library\ContentTransformer;

final class ContentTransformerTest extends TestCase {

	/**
	 * Resolver returning a predictable attachment per file; records calls.
	 *
	 * @var list<string>
	 */
	private array $resolved = array();

	private function resolver(): callable {
		return function ( string $relative ): ?array {
			$this->resolved[] = $relative;
			if ( 'missing.webp' === $relative ) {
				return null;
			}
			return array(
				'id'  => 42,
				'url' => 'https://site.test/wp-content/uploads/' . $relative,
			);
		};
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function sample(): array {
		return array(
			array(
				'id'       => 'aaaaaa1',
				'elType'   => 'container',
				'settings' => array(
					'background_image' => array(
						'url' => '{{PLUGT_MEDIA}}/bg.webp',
						'id'  => '',
					),
				),
				'elements' => array(
					array(
						'id'         => 'aaaaaa2',
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array( 'editor' => '<img src="{{PLUGT_MEDIA}}/inline.webp"> <img src="{{PLUGT_MEDIA}}/missing.webp">' ),
						'elements'   => array(),
					),
					array(
						'id'         => 'aaaaaa3',
						'elType'     => 'widget',
						'widgetType' => 'icon-list',
						'settings'   => array(
							'icon_list' => array(
								array(
									'_id'  => 'item001',
									'text' => 'Un',
								),
								array(
									'_id'  => 'item002',
									'text' => 'Deux',
								),
							),
						),
						'elements'   => array(),
					),
					array(
						'id'         => 'aaaaaa4',
						'elType'     => 'widget',
						'widgetType' => 'image',
						'settings'   => array(
							'image' => array(
								'url' => '{{PLUGT_MEDIA}}/missing.webp',
								'id'  => '',
								'alt' => 'Alt',
							),
						),
						'elements'   => array(),
					),
				),
			),
		);
	}

	public function test_ids_are_regenerated_and_unique(): void {
		$result = ( new ContentTransformer() )->transform( $this->sample(), $this->resolver() );

		$ids = array( $result[0]['id'] );
		foreach ( $result[0]['elements'] as $child ) {
			$ids[] = $child['id'];
		}
		foreach ( $result[0]['elements'][1]['settings']['icon_list'] as $item ) {
			$ids[] = $item['_id'];
		}

		$this->assertCount( 6, array_unique( $ids ) );
		foreach ( $ids as $id ) {
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{7}$/', $id );
			$this->assertNotContains( $id, array( 'aaaaaa1', 'aaaaaa2', 'aaaaaa3', 'aaaaaa4', 'item001', 'item002' ) );
		}
	}

	public function test_structure_and_other_settings_are_preserved(): void {
		$result = ( new ContentTransformer() )->transform( $this->sample(), $this->resolver() );
		$this->assertSame( 'container', $result[0]['elType'] );
		$this->assertCount( 3, $result[0]['elements'] );
		$this->assertSame( 'Deux', $result[0]['elements'][1]['settings']['icon_list'][1]['text'] );
		$this->assertSame( 'Alt', $result[0]['elements'][2]['settings']['image']['alt'] );
	}

	public function test_media_objects_and_inline_urls_are_resolved(): void {
		$result = ( new ContentTransformer() )->transform( $this->sample(), $this->resolver() );

		$this->assertSame(
			array(
				'url' => 'https://site.test/wp-content/uploads/bg.webp',
				'id'  => 42,
			),
			$result[0]['settings']['background_image']
		);
		$this->assertSame(
			'<img src="https://site.test/wp-content/uploads/inline.webp"> <img src="">',
			$result[0]['elements'][0]['settings']['editor']
		);
	}

	public function test_unresolved_media_object_is_emptied(): void {
		$result = ( new ContentTransformer() )->transform( $this->sample(), $this->resolver() );
		$image  = $result[0]['elements'][2]['settings']['image'];
		$this->assertSame( '', $image['url'] );
		$this->assertSame( '', $image['id'] );
		$this->assertStringNotContainsString( '{{PLUGT_MEDIA}}', (string) json_encode( $result ) );
	}

	public function test_page_settings_media_are_resolved(): void {
		$settings = ( new ContentTransformer() )->transform_settings(
			array(
				'template'         => 'elementor_header_footer',
				'background_image' => array( 'url' => '{{PLUGT_MEDIA}}/bg.webp' ),
			),
			$this->resolver()
		);
		$this->assertSame( 'elementor_header_footer', $settings['template'] );
		$this->assertSame( 42, $settings['background_image']['id'] );
	}
}
