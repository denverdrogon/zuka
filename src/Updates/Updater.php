<?php
/**
 * Updates from GitHub Releases (Plugin Update Checker).
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Updates;

/**
 * Wires Plugin Update Checker to the GitHub repository releases.
 */
class Updater {

	/**
	 * GitHub repository URL.
	 *
	 * @var string
	 */
	private string $repo;

	/**
	 * Main plugin file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Constructor.
	 *
	 * @param string $repo        GitHub repository URL.
	 * @param string $plugin_file Absolute path of the main plugin file.
	 * @param string $slug        Plugin slug.
	 */
	public function __construct( string $repo, string $plugin_file, string $slug ) {
		$this->repo        = $repo;
		$this->plugin_file = $plugin_file;
		$this->slug        = $slug;
	}

	/**
	 * Registers the update checker unless disabled or unavailable.
	 */
	public function register(): void {
		/**
		 * Filters whether plugin updates from GitHub Releases are enabled.
		 *
		 * @param bool $enabled Enabled by default.
		 */
		if ( ! apply_filters( 'plugt_update_enabled', true ) ) {
			return;
		}

		// Dev checkout without `vendor/`: nothing to do.
		if ( ! $this->is_available() ) {
			return;
		}

		$checker = $this->build_checker();
		if ( null === $checker ) {
			return;
		}

		$checker->getVcsApi()->enableReleaseAssets( '/plug-templates\.zip$/' );
		$checker->setBranch( 'main' );

		if ( defined( 'PLUGT_GITHUB_TOKEN' ) && PLUGT_GITHUB_TOKEN ) {
			$checker->setAuthentication( PLUGT_GITHUB_TOKEN );
		}
	}

	/**
	 * Whether Plugin Update Checker is loaded.
	 */
	protected function is_available(): bool {
		return class_exists( \YahnisElsts\PluginUpdateChecker\v5\PucFactory::class );
	}

	/**
	 * Builds the update checker.
	 *
	 * @return object|null
	 */
	protected function build_checker() {
		return \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( $this->repo, $this->plugin_file, $this->slug );
	}
}
