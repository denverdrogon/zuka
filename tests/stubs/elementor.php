<?php
/**
 * Minimal Elementor stubs for static analysis. Only what Adapter.php touches.
 *
 * Types are deliberately loose: Adapter guards against Elementor internals
 * changing between releases, so the stubs must not promise more than Elementor does.
 *
 * @package PlugTemplates
 */

namespace Elementor;

class Plugin {
	/** @var Plugin|null */
	public static $instance;

	/** @var object|null Experiments manager (Elementor\Core\Experiments\Manager). */
	public $experiments;
}
