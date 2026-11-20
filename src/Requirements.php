<?php
/**
 * Environment requirements check (pure logic, no WordPress calls).
 *
 * @package PlugTemplates
 */

namespace PlugTemplates;

/**
 * Compares the running environment against the plugin minimums.
 */
final class Requirements {

	public const PHP       = 'php';
	public const WORDPRESS = 'wordpress';
	public const ELEMENTOR = 'elementor';

	/**
	 * Minimum versions keyed by component.
	 *
	 * @var array<string, string>
	 */
	private array $minimums;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $minimums Minimum versions keyed by component.
	 */
	public function __construct( array $minimums ) {
		$this->minimums = $minimums;
	}

	/**
	 * Returns the unmet requirements.
	 *
	 * A null version means the component is missing (only meaningful for Elementor).
	 *
	 * @param array<string, string|null> $current Current versions keyed by component.
	 * @return array<string, array{required: string, current: string|null}> Unmet requirements.
	 */
	public function unmet( array $current ): array {
		$unmet = array();
		foreach ( $this->minimums as $component => $required ) {
			$version = $current[ $component ] ?? null;
			if ( null === $version || version_compare( $version, $required, '<' ) ) {
				$unmet[ $component ] = array(
					'required' => $required,
					'current'  => $version,
				);
			}
		}
		return $unmet;
	}
}
