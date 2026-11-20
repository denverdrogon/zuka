<?php
/**
 * Prepares template content for insertion: fresh element ids and resolved media.
 *
 * Pure PHP: media resolution is delegated to a callable, so this class is testable without WordPress.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

/**
 * Content transformer.
 */
final class ContentTransformer {

	/**
	 * Ids generated during the current run, to guarantee uniqueness.
	 *
	 * @var array<string, true>
	 */
	private array $ids = array();

	/**
	 * Transforms a list of elements.
	 *
	 * The resolver receives a path relative to media/ and returns
	 * array{id: int, url: string} or null when the media cannot be imported.
	 *
	 * @param array<mixed> $elements Elements (content of content.<variant>.json).
	 * @param callable     $resolve  Media resolver.
	 * @return list<array<string, mixed>>
	 */
	public function transform( array $elements, callable $resolve ): array {
		$this->ids = array();
		$result    = array();
		foreach ( $elements as $element ) {
			if ( is_array( $element ) ) {
				$result[] = $this->element( $element, $resolve );
			}
		}
		return $result;
	}

	/**
	 * Resolves media in a settings array (e.g. page_settings).
	 *
	 * @param array<mixed> $settings Settings.
	 * @param callable     $resolve  Media resolver.
	 * @return array<mixed>
	 */
	public function transform_settings( array $settings, callable $resolve ): array {
		return $this->settings( $settings, $resolve );
	}

	/**
	 * Transforms one element and its children.
	 *
	 * @param array<mixed> $element Element.
	 * @param callable     $resolve Media resolver.
	 * @return array<string, mixed>
	 */
	private function element( array $element, callable $resolve ): array {
		$element['id'] = $this->new_id();

		if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
			$element['settings'] = $this->settings( $element['settings'], $resolve );
		}

		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$children = array();
			foreach ( $element['elements'] as $child ) {
				if ( is_array( $child ) ) {
					$children[] = $this->element( $child, $resolve );
				}
			}
			$element['elements'] = $children;
		}

		return $element;
	}

	/**
	 * Walks settings: media objects, inline media URLs and repeater item ids.
	 *
	 * @param array<mixed> $settings Settings (possibly nested).
	 * @param callable     $resolve  Media resolver.
	 * @return array<mixed>
	 */
	private function settings( array $settings, callable $resolve ): array {
		$prefix = Schema::MEDIA_TOKEN . '/';

		// Media object: an array whose "url" starts with the media token.
		$is_media_object = isset( $settings['url'] ) && is_string( $settings['url'] ) && 0 === strpos( $settings['url'], $prefix );
		if ( $is_media_object ) {
			$media           = $resolve( substr( $settings['url'], strlen( $prefix ) ) );
			$settings['url'] = is_array( $media ) ? (string) $media['url'] : '';
			$settings['id']  = is_array( $media ) ? (int) $media['id'] : '';
		}

		// Repeater items (icon_list, tabs…) carry their own ids.
		if ( isset( $settings['_id'] ) && is_string( $settings['_id'] ) ) {
			$settings['_id'] = $this->new_id();
		}

		foreach ( $settings as $key => $value ) {
			if ( $is_media_object && ( 'url' === $key || 'id' === $key ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$settings[ $key ] = $this->settings( $value, $resolve );
			} elseif ( is_string( $value ) && false !== strpos( $value, $prefix ) ) {
				$settings[ $key ] = $this->replace_inline( $value, $resolve );
			}
		}

		return $settings;
	}

	/**
	 * Replaces media tokens inside free text (HTML, CSS url()).
	 *
	 * @param string   $value   Text.
	 * @param callable $resolve Media resolver.
	 */
	private function replace_inline( string $value, callable $resolve ): string {
		$pattern = '#' . preg_quote( Schema::MEDIA_TOKEN, '#' ) . '/([A-Za-z0-9._/-]+)#';
		return (string) preg_replace_callback(
			$pattern,
			static function ( array $matches ) use ( $resolve ): string {
				$media = $resolve( $matches[1] );
				return is_array( $media ) ? (string) $media['url'] : '';
			},
			$value
		);
	}

	/**
	 * New 7-character element id, unique within the run (Elementor format).
	 */
	private function new_id(): string {
		do {
			$id = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
		} while ( isset( $this->ids[ $id ] ) );
		$this->ids[ $id ] = true;
		return $id;
	}
}
