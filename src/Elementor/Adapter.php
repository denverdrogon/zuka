<?php
/**
 * Single point of contact with Elementor internals.
 *
 * Every Elementor class, constant, hook or JS command used by the plugin must
 * be reached through this class so that Elementor upgrades only touch one file.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Elementor;

/**
 * Elementor adapter.
 */
final class Adapter {

	/**
	 * Whether Elementor has finished loading.
	 */
	public function is_loaded(): bool {
		return (bool) did_action( 'elementor/loaded' );
	}

	/**
	 * Running Elementor version, or null when Elementor is not loaded.
	 */
	public function version(): ?string {
		return defined( 'ELEMENTOR_VERSION' ) ? (string) constant( 'ELEMENTOR_VERSION' ) : null;
	}

	/**
	 * Whether the v4 Atomic editor (atomic elements) is available on this site.
	 *
	 * The experiment name must be confirmed against Elementor's source for each
	 * major release; keep the list here.
	 */
	public function is_atomic_active(): bool {
		if ( ! $this->is_loaded() || ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! isset( $plugin->experiments ) || ! method_exists( $plugin->experiments, 'is_feature_active' ) ) {
			return false;
		}
		foreach ( array( 'e_atomic_elements', 'atomic_widgets' ) as $feature ) {
			if ( $plugin->experiments->is_feature_active( $feature ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Registers a callback fired once Elementor is fully initialised.
	 *
	 * @param callable $callback Callback.
	 */
	public function on_init( callable $callback ): void {
		add_action( 'elementor/init', $callback );
	}
}
