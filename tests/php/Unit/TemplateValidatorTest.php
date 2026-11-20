<?php
/**
 * @package PlugTemplates
 */

namespace PlugTemplates\Tests\Unit;

use PlugTemplates\Library\TemplateValidator;

final class TemplateValidatorTest extends TestCase {

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
	 * @return iterable<string, array{string}>
	 */
	public function shipped_templates(): iterable {
		foreach ( glob( dirname( __DIR__, 3 ) . '/templates/*', GLOB_ONLYDIR ) as $dir ) {
			yield basename( $dir ) => array( $dir );
		}
	}

	/**
	 * @dataProvider shipped_templates
	 */
	public function test_shipped_templates_are_valid( string $dir ): void {
		$result = ( new TemplateValidator() )->validate( $dir );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array(), $result['warnings'] );
		$this->assertContains( 'v3', $result['variants'] );
	}

	public function test_minimal_template_is_valid_and_lists_widgets(): void {
		$result = ( new TemplateValidator() )->validate( $this->make_template() );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array( 'heading', 'image' ), $result['widgets'] );
	}

	public function test_pro_widget_is_rejected(): void {
		$dir    = $this->make_template( array( 'content' => array( $this->widget( 'abc0001', 'form' ) ) ) );
		$result = ( new TemplateValidator() )->validate( $dir );
		$this->assertErrorContains( 'widget « form » absent de la liste blanche', $result['errors'] );
	}

	public function test_pro_setting_is_rejected(): void {
		$widget                                        = $this->widget( 'abc0001', 'heading' );
		$widget['settings']['motion_fx_motion_fx_scrolling'] = 'yes';
		$result = ( new TemplateValidator() )->validate( $this->make_template( array( 'content' => array( $widget ) ) ) );
		$this->assertErrorContains( 'réservé à Elementor Pro', $result['errors'] );
	}

	public function test_external_media_is_rejected(): void {
		$widget                      = $this->widget( 'abc0001', 'image' );
		$widget['settings']['image'] = array( 'url' => 'https://cdn.example.com/photo.jpg' );
		$result                      = ( new TemplateValidator() )->validate( $this->make_template( array( 'content' => array( $widget ) ) ) );
		$this->assertErrorContains( 'média externe', $result['errors'] );
	}

	public function test_missing_and_unused_media(): void {
		$dir = $this->make_template();
		unlink( $dir . '/media/photo.webp' );
		file_put_contents( $dir . '/media/unused.webp', 'x' );

		$result = ( new TemplateValidator() )->validate( $dir );
		$this->assertErrorContains( 'introuvable : media/photo.webp', $result['errors'] );
		$this->assertErrorContains( 'inutilisé : media/unused.webp', $result['warnings'] );
	}

	public function test_media_path_traversal_is_rejected(): void {
		$widget                      = $this->widget( 'abc0001', 'image' );
		$widget['settings']['image'] = array( 'url' => '{{PLUGT_MEDIA}}/../../wp-config.php' );
		$result                      = ( new TemplateValidator() )->validate( $this->make_template( array( 'content' => array( $widget ) ) ) );
		$this->assertErrorContains( 'chemin média', $result['errors'] );
	}

	public function test_duplicate_and_invalid_ids(): void {
		$content = array( $this->widget( 'abc0001', 'heading' ), $this->widget( 'abc0001', 'heading' ), $this->widget( 'BAD', 'heading' ) );
		$result  = ( new TemplateValidator() )->validate( $this->make_template( array( 'content' => $content ) ) );
		$this->assertErrorContains( 'dupliqué', $result['errors'] );
		$this->assertErrorContains( 'id manquant ou invalide', $result['errors'] );
	}

	public function test_meta_errors(): void {
		$dir = $this->make_template(
			null,
			array(
				'id'       => 'autre-slug',
				'type'     => 'popup',
				'version'  => '1.0',
				'variants' => array( 'v3', 'v9' ),
			)
		);
		$errors = ( new TemplateValidator() )->validate( $dir )['errors'];
		$this->assertErrorContains( 'différent du dossier', $errors );
		$this->assertErrorContains( 'type « popup » invalide', $errors );
		$this->assertErrorContains( 'SemVer', $errors );
		$this->assertErrorContains( 'variante « v9 » inconnue', $errors );
	}

	public function test_invalid_json_and_missing_thumbnail(): void {
		$dir = $this->make_template();
		file_put_contents( $dir . '/content.v3.json', '{ invalid' );
		unlink( $dir . '/thumbnail.webp' );

		$errors = ( new TemplateValidator() )->validate( $dir )['errors'];
		$this->assertErrorContains( 'JSON invalide', $errors );
		$this->assertErrorContains( 'thumbnail.webp manquant', $errors );
	}

	public function test_v4_elements_only_allowed_in_v4_variant(): void {
		$content = array(
			array(
				'id'       => 'abc0001',
				'elType'   => 'e-flexbox',
				'settings' => array(),
				'elements' => array( $this->widget( 'abc0002', 'e-heading' ) ),
			),
		);
		$validator = new TemplateValidator();

		$v3 = $validator->validate( $this->make_template( array( 'content' => $content ) ) );
		$this->assertErrorContains( 'elType « e-flexbox » non autorisé en v3', $v3['errors'] );

		$dir = $this->make_template( array( 'content' => $content ), array( 'variants' => array( 'v4' ) ), 'v4' );
		$v4  = $validator->validate( $dir );
		$this->assertSame( array(), $v4['errors'] );
		$this->assertErrorContains( 'Pas de variante v3', $v4['warnings'] );
	}

	/**
	 * Creates a valid template folder, optionally overriding content/meta.
	 *
	 * @param array<string, mixed>|null $content Content file data.
	 * @param array<string, mixed>      $meta    Meta overrides.
	 * @param string                    $variant Variant file to write.
	 */
	private function make_template( ?array $content = null, array $meta = array(), string $variant = 'v3' ): string {
		$dir = sys_get_temp_dir() . '/plugt-test-' . bin2hex( random_bytes( 4 ) ) . '/mon-template';
		mkdir( $dir . '/media', 0777, true );
		$this->tmp_dirs[] = dirname( $dir );

		$image                      = $this->widget( 'img0001', 'image' );
		$image['settings']['image'] = array(
			'url' => '{{PLUGT_MEDIA}}/photo.webp',
			'id'  => '',
		);

		$meta = array_merge(
			array(
				'id'       => 'mon-template',
				'title'    => 'Mon template',
				'type'     => 'section',
				'category' => 'test',
				'version'  => '1.0.0',
				'variants' => array( 'v3' ),
			),
			$meta
		);
		$content = $content ?? array( 'content' => array( $this->widget( 'abc0001', 'heading' ), $image ) );

		file_put_contents( $dir . '/meta.json', json_encode( $meta ) );
		file_put_contents( $dir . "/content.$variant.json", json_encode( $content ) );
		file_put_contents( $dir . '/media/photo.webp', 'x' );
		file_put_contents( $dir . '/thumbnail.webp', 'x' );
		return $dir;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function widget( string $id, string $type ): array {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => array( 'title' => 'Titre' ),
			'elements'   => array(),
		);
	}

	/**
	 * @param list<string> $messages Messages.
	 */
	private function assertErrorContains( string $needle, array $messages ): void {
		foreach ( $messages as $message ) {
			if ( false !== strpos( $message, $needle ) ) {
				$this->assertTrue( true );
				return;
			}
		}
		$this->fail( sprintf( "« %s » introuvable dans :\n- %s", $needle, implode( "\n- ", $messages ) ) );
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
