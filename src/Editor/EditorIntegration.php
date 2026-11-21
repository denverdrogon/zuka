<?php
/**
 * Elementor editor integration: a « Plug Templates » button next to Elementor's
 * own « Add Template » button, opening the library in a modal.
 *
 * Insertion happens client-side (POST /plugt/v1/import with mode=editor, then
 * Elementor's import command), so nothing is saved until the user saves.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Editor;

use PlugTemplates\Admin\LibraryPage;
use PlugTemplates\Elementor\Adapter;

/**
 * Editor integration.
 */
final class EditorIntegration {

	public const HANDLE = 'plugt-editor';

	/**
	 * Elementor adapter.
	 *
	 * @var Adapter
	 */
	private Adapter $elementor;

	/**
	 * Library page (shared configuration).
	 *
	 * @var LibraryPage
	 */
	private LibraryPage $library;

	/**
	 * Absolute path of assets/build.
	 *
	 * @var string
	 */
	private string $build_path;

	/**
	 * URL of assets/build.
	 *
	 * @var string
	 */
	private string $build_url;

	/**
	 * Constructor.
	 *
	 * @param Adapter     $elementor  Elementor adapter.
	 * @param LibraryPage $library    Library page.
	 * @param string      $build_path Absolute path of assets/build.
	 * @param string      $build_url  URL of assets/build.
	 */
	public function __construct( Adapter $elementor, LibraryPage $library, string $build_path, string $build_url ) {
		$this->elementor  = $elementor;
		$this->library    = $library;
		$this->build_path = rtrim( $build_path, '/\\' );
		$this->build_url  = rtrim( $build_url, '/' );
	}

	/**
	 * Hooks the editor assets.
	 */
	public function register(): void {
		$this->elementor->on_editor_enqueue( array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues the editor script and its configuration.
	 */
	public function enqueue(): void {
		if ( ! current_user_can( LibraryPage::CAPABILITY ) ) {
			return;
		}
		$file = $this->build_path . '/editor.asset.php';
		if ( ! is_readable( $file ) ) {
			return;
		}
		$asset   = require $file;
		$deps    = is_array( $asset ) && isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? array_values( array_map( 'strval', $asset['dependencies'] ) ) : array();
		$version = is_array( $asset ) && isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : PLUGT_VERSION;

		wp_enqueue_script( self::HANDLE, $this->build_url . '/editor.js', $deps, $version, true );
		wp_enqueue_style( self::HANDLE, $this->build_url . '/editor.css', array( 'wp-components' ), $version );
		wp_set_script_translations( self::HANDLE, 'plug-templates', PLUGT_PATH . 'languages' );

		$config           = $this->library->config();
		$config['editor'] = $this->elementor->editor_js_contract();
		$config['icon']   = PLUGT_URL . 'assets/brand/zuka-mark.svg';
		wp_add_inline_script(
			self::HANDLE,
			'window.plugtLibrary = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}
}
