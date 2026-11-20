<?php
/**
 * Read-only access to the templates shipped in templates/.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin files.

/**
 * Template repository backed by templates/manifest.json.
 */
class Repository {

	/**
	 * Absolute path of templates/ (no trailing slash).
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Public URL of templates/ (no trailing slash).
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Manifest entries keyed by id, loaded on first use.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $entries = null;

	/**
	 * Constructor.
	 *
	 * @param string $dir Absolute path of templates/.
	 * @param string $url Public URL of templates/.
	 */
	public function __construct( string $dir, string $url ) {
		$this->dir = rtrim( str_replace( '\\', '/', $dir ), '/' );
		$this->url = rtrim( $url, '/' );
	}

	/**
	 * All manifest entries, in manifest order.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function all(): array {
		return array_values( $this->entries() );
	}

	/**
	 * One manifest entry.
	 *
	 * @param string $id Template id.
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		return $this->entries()[ $id ] ?? null;
	}

	/**
	 * Decoded content.<variant>.json of a template.
	 *
	 * @param string $id      Template id.
	 * @param string $variant v3 or v4.
	 * @return array<string, mixed>|null Null when missing or invalid.
	 */
	public function content( string $id, string $variant ): ?array {
		$entry = $this->get( $id );
		if ( null === $entry || ! in_array( $variant, (array) $entry['variants'], true ) ) {
			return null;
		}
		$file = $this->dir . '/' . $id . '/content.' . $variant . '.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		return is_array( $data ) && isset( $data['content'] ) && is_array( $data['content'] ) ? $data : null;
	}

	/**
	 * Absolute path of a media file, guaranteed to stay inside the template's media/ folder.
	 *
	 * @param string $id       Template id.
	 * @param string $relative Path relative to media/.
	 */
	public function media_path( string $id, string $relative ): ?string {
		if ( null === $this->get( $id ) ) {
			return null;
		}
		$base = realpath( $this->dir . '/' . $id . '/media' );
		$path = realpath( $this->dir . '/' . $id . '/media/' . $relative );
		if ( false === $base || false === $path || ! is_file( $path ) ) {
			return null;
		}
		$base = rtrim( str_replace( '\\', '/', $base ), '/' ) . '/';
		$path = str_replace( '\\', '/', $path );
		return 0 === strpos( $path, $base ) ? $path : null;
	}

	/**
	 * Public URL of a template thumbnail.
	 *
	 * @param string $id Template id.
	 */
	public function thumbnail_url( string $id ): string {
		return $this->url . '/' . rawurlencode( $id ) . '/thumbnail.webp';
	}

	/**
	 * Public URL of the optional preview clip, null when the template has none.
	 *
	 * @param string $id Template id.
	 */
	public function preview_url( string $id ): ?string {
		$entry = $this->get( $id );
		if ( null === $entry || ! is_string( $entry['preview'] ?? null ) || '' === $entry['preview'] ) {
			return null;
		}
		return $this->url . '/' . rawurlencode( $id ) . '/' . Schema::PREVIEW_FILE;
	}

	/**
	 * Loads the manifest once per request.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function entries(): array {
		if ( null !== $this->entries ) {
			return $this->entries;
		}
		$this->entries = array();

		$file = $this->dir . '/manifest.json';
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		if ( ! is_array( $data ) || ManifestBuilder::SCHEMA_VERSION !== ( $data['schema'] ?? null ) || ! is_array( $data['templates'] ?? null ) ) {
			return $this->entries;
		}

		foreach ( $data['templates'] as $entry ) {
			if ( is_array( $entry ) && isset( $entry['id'] ) && is_string( $entry['id'] ) ) {
				$this->entries[ $entry['id'] ] = $entry;
			}
		}
		return $this->entries;
	}
}
