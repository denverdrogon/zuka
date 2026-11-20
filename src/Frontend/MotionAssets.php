<?php
/**
 * Motion layer assets (assets/build/motion.*), loaded only where templates use them.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Frontend;

use PlugTemplates\Elementor\Adapter;
use PlugTemplates\Library\Schema;

/**
 * Registers the motion stylesheet/script and enqueues them on demand.
 */
final class MotionAssets {

	public const HANDLE = 'plugt-motion';

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
	 * Hooks registration and on-demand enqueueing.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_for_queried_post' ) );
		$this->elementor->on_element_classes( array( $this, 'maybe_enqueue' ) );
		$this->elementor->on_preview_enqueue( array( $this, 'enqueue' ) );
	}

	/**
	 * Registers the style and script when the build exists.
	 */
	public function register_assets(): void {
		$asset_file = $this->build_path . '/motion.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		$deps  = is_array( $asset ) && isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();
		$ver   = is_array( $asset ) && isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : PLUGT_VERSION;

		wp_register_style( self::HANDLE, $this->build_url . '/motion.css', array(), $ver );
		wp_register_script(
			self::HANDLE,
			$this->build_url . '/motion.js',
			$deps,
			$ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Enqueues in <head> when the current singular post uses the motion layer.
	 */
	public function enqueue_for_queried_post(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( $post_id > 0 && $this->elementor->post_data_contains( $post_id, 'plugt-' ) ) {
			$this->enqueue();
		}
	}

	/**
	 * Enqueues when a rendered element carries a motion class (templates shown elsewhere than the main post).
	 *
	 * @param string $classes Space-separated classes of the element.
	 */
	public function maybe_enqueue( string $classes ): void {
		if ( self::uses_motion( $classes ) ) {
			$this->enqueue();
		}
	}

	/**
	 * Enqueues the style and script.
	 */
	public function enqueue(): void {
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Whether a class list contains a motion class.
	 *
	 * @param string $classes Space-separated classes.
	 */
	public static function uses_motion( string $classes ): bool {
		foreach ( preg_split( '/\s+/', trim( $classes ) ) as $class ) {
			if ( 0 === strpos( $class, Schema::MOTION_PREFIX ) || in_array( $class, Schema::MOTION_UTILITIES, true ) ) {
				return true;
			}
		}
		return false;
	}
}
