<?php
/**
 * Plugin bootstrap.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates;

use PlugTemplates\Admin\LibraryPage;
use PlugTemplates\Editor\EditorIntegration;
use PlugTemplates\Elementor\Adapter;
use PlugTemplates\Frontend\MotionAssets;
use PlugTemplates\Library\ContentTransformer;
use PlugTemplates\Library\Importer;
use PlugTemplates\Library\MediaImporter;
use PlugTemplates\Library\Previewer;
use PlugTemplates\Library\Repository;
use PlugTemplates\Rest\TemplatesController;

/**
 * Main plugin class: checks requirements then wires services.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Elementor adapter.
	 *
	 * @var Adapter
	 */
	private Adapter $elementor;

	/**
	 * Template repository (null until requirements are met).
	 *
	 * @var Repository|null
	 */
	private ?Repository $repository = null;

	/**
	 * Template importer (null until requirements are met).
	 *
	 * @var Importer|null
	 */
	private ?Importer $importer = null;

	/**
	 * Unmet requirements found at boot.
	 *
	 * @var array<string, array{required: string, current: string|null}>
	 */
	private array $unmet = array();

	/**
	 * Private: use Plugin::boot().
	 */
	private function __construct() {
		$this->elementor = new Adapter();
	}

	/**
	 * Entry point, hooked on `plugins_loaded`.
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = new self();
		self::$instance->init();
	}

	/**
	 * Returns the booted instance.
	 */
	public static function instance(): ?Plugin {
		return self::$instance;
	}

	/**
	 * Elementor adapter accessor.
	 */
	public function elementor(): Adapter {
		return $this->elementor;
	}

	/**
	 * Template repository, null when requirements are not met.
	 */
	public function repository(): ?Repository {
		return $this->repository;
	}

	/**
	 * Template importer, null when requirements are not met.
	 */
	public function importer(): ?Importer {
		return $this->importer;
	}

	/**
	 * Loads translations, checks requirements and registers services when they are met.
	 */
	private function init(): void {
		load_plugin_textdomain( 'plug-templates', false, dirname( plugin_basename( PLUGT_FILE ) ) . '/languages' );

		$requirements = new Requirements(
			array(
				Requirements::PHP       => PLUGT_MIN_PHP,
				Requirements::WORDPRESS => PLUGT_MIN_WP,
				Requirements::ELEMENTOR => PLUGT_MIN_ELEMENTOR,
			)
		);

		$this->unmet = $requirements->unmet(
			array(
				Requirements::PHP       => PHP_VERSION,
				Requirements::WORDPRESS => get_bloginfo( 'version' ),
				Requirements::ELEMENTOR => $this->elementor->is_loaded() ? $this->elementor->version() : null,
			)
		);

		if ( array() !== $this->unmet ) {
			add_action( 'admin_notices', array( $this, 'render_requirements_notice' ) );
			return;
		}

		$this->register_services();
	}

	/**
	 * Wires feature services. Filled in by later phases.
	 */
	private function register_services(): void {
		$this->repository = new Repository( PLUGT_PATH . 'templates', PLUGT_URL . 'templates' );
		$this->importer   = new Importer( $this->repository, $this->elementor, new MediaImporter(), new ContentTransformer() );

		( new TemplatesController( $this->repository, $this->importer ) )->register();
		( new MotionAssets( $this->elementor, PLUGT_PATH . 'assets/build', PLUGT_URL . 'assets/build' ) )->register();
		( new Previewer( $this->repository, $this->importer, $this->elementor ) )->register();

		if ( is_admin() ) {
			$library = new LibraryPage( $this->elementor, PLUGT_PATH . 'assets/build', PLUGT_URL . 'assets/build' );
			$library->register();
			( new EditorIntegration( $this->elementor, $library, PLUGT_PATH . 'assets/build', PLUGT_URL . 'assets/build' ) )->register();
		}

		/**
		 * Fires once requirements are met and core services are registered.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'plugt_loaded', $this );
	}

	/**
	 * Admin notice listing unmet requirements.
	 */
	public function render_requirements_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$labels = array(
			Requirements::PHP       => 'PHP',
			Requirements::WORDPRESS => 'WordPress',
			Requirements::ELEMENTOR => 'Elementor',
		);

		echo '<div class="notice notice-error"><p><strong>Zuka</strong> — ';
		echo esc_html__( 'Le plugin est inactif car les prérequis ne sont pas remplis :', 'plug-templates' );
		echo '</p><ul style="list-style:disc;padding-left:2em">';
		foreach ( $this->unmet as $component => $info ) {
			$current = null === $info['current'] ? __( 'absent', 'plug-templates' ) : $info['current'];
			printf(
				'<li>%s</li>',
				esc_html(
					sprintf(
						/* translators: 1: component name, 2: required version, 3: current version. */
						__( '%1$s %2$s ou supérieur requis (actuel : %3$s).', 'plug-templates' ),
						$labels[ $component ] ?? $component,
						$info['required'],
						$current
					)
				)
			);
		}
		echo '</ul></div>';
	}
}
