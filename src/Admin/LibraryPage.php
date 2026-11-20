<?php
/**
 * Admin page « Plug Templates »: React + DataViews library (assets/build/admin.*).
 *
 * Assets are enqueued on this page only. Reads and imports go through the
 * plugt/v1 REST routes (cookie + wp_rest nonce handled by wp-api-fetch).
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Admin;

use PlugTemplates\Elementor\Adapter;
use PlugTemplates\Library\Schema;
use PlugTemplates\Rest\TemplatesController;

/**
 * Library admin page.
 */
final class LibraryPage {

	public const SLUG       = 'plug-templates';
	public const HANDLE     = 'plugt-admin';
	public const CAPABILITY = 'edit_posts';
	public const ROOT_ID    = 'plugt-library-root';

	/**
	 * Elementor adapter.
	 *
	 * @var Adapter
	 */
	private Adapter $elementor;

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
	 * Hook suffix returned by add_menu_page().
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param Adapter $elementor  Elementor adapter.
	 * @param string  $build_path Absolute path of assets/build.
	 * @param string  $build_url  URL of assets/build.
	 */
	public function __construct( Adapter $elementor, string $build_path, string $build_url ) {
		$this->elementor  = $elementor;
		$this->build_path = rtrim( $build_path, '/\\' );
		$this->build_url  = rtrim( $build_url, '/' );
	}

	/**
	 * Hooks the menu and the assets.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds the top-level menu entry.
	 */
	public function add_menu(): void {
		$hook = add_menu_page(
			__( 'Plug Templates', 'plug-templates' ),
			__( 'Plug Templates', 'plug-templates' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-layout',
			58
		);
		$this->hook_suffix = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Enqueues the app on the library page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( $hook ): void {
		if ( '' === $this->hook_suffix || $hook !== $this->hook_suffix ) {
			return;
		}
		$asset = $this->asset();
		if ( null === $asset ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			$this->build_url . '/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style( self::HANDLE, $this->build_url . '/admin.css', array( 'wp-components' ), $asset['version'] );
		wp_set_script_translations( self::HANDLE, 'plug-templates', PLUGT_PATH . 'languages' );
		wp_add_inline_script(
			self::HANDLE,
			'window.plugtLibrary = ' . wp_json_encode( $this->config() ) . ';',
			'before'
		);
	}

	/**
	 * Page markup: the React root, or a notice when the build is missing.
	 */
	public function render(): void {
		echo '<div class="wrap plugt-library-wrap">';
		if ( null === $this->asset() ) {
			echo '<h1>' . esc_html__( 'Plug Templates', 'plug-templates' ) . '</h1>';
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Les fichiers de l\'interface sont absents : lancez « npm run build ».', 'plug-templates' ) . '</p></div>';
		} else {
			printf( '<div id="%s"></div>', esc_attr( self::ROOT_ID ) );
		}
		echo '</div>';
	}

	/**
	 * Configuration passed to the app.
	 *
	 * @return array<string, mixed>
	 */
	public function config(): array {
		return array(
			'version'    => PLUGT_VERSION,
			'restPath'   => '/' . TemplatesController::NAMESPACE,
			'categories' => $this->category_labels(),
			'caps'       => array(
				'importPage'    => current_user_can( 'edit_pages' ) && current_user_can( 'upload_files' ),
				'importLibrary' => current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' ),
			),
			'libraryUrl' => $this->elementor->library_admin_url(),
			'atomic'     => $this->elementor->is_atomic_active(),
		);
	}

	/**
	 * Translated labels of Schema::CATEGORIES.
	 *
	 * @return array<string, string>
	 */
	public function category_labels(): array {
		$labels = array(
			'hero'          => __( 'Heros', 'plug-templates' ),
			'carousel'      => __( 'Carrousels & accordéons', 'plug-templates' ),
			'bento'         => __( 'Bento & matrices', 'plug-templates' ),
			'split'         => __( 'Splits & sélecteurs', 'plug-templates' ),
			'tabs'          => __( 'Onglets', 'plug-templates' ),
			'public-sector' => __( 'Secteur public', 'plug-templates' ),
			'header'        => __( 'En-têtes', 'plug-templates' ),
			'footer'        => __( 'Pieds de page', 'plug-templates' ),
			'testimonials'  => __( 'Témoignages', 'plug-templates' ),
			'landing'       => __( 'Pages d\'accueil', 'plug-templates' ),
		);
		$out = array();
		foreach ( Schema::CATEGORIES as $key ) {
			$out[ $key ] = $labels[ $key ] ?? $key;
		}
		return $out;
	}

	/**
	 * Reads assets/build/admin.asset.php.
	 *
	 * @return array{dependencies: list<string>, version: string}|null
	 */
	private function asset(): ?array {
		$file = $this->build_path . '/admin.asset.php';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$asset = require $file;
		if ( ! is_array( $asset ) ) {
			return null;
		}
		return array(
			'dependencies' => isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? array_values( array_map( 'strval', $asset['dependencies'] ) ) : array(),
			'version'      => isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : PLUGT_VERSION,
		);
	}
}
