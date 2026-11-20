<?php
/**
 * Builds templates/manifest.json from every templates/<slug>/ folder.
 *
 * Output is deterministic (sorted, no timestamp) so CI can check it is up to date.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local files, also runs outside WordPress.

/**
 * Manifest builder.
 */
final class ManifestBuilder {

	public const SCHEMA_VERSION = 1;

	/**
	 * Validator.
	 *
	 * @var TemplateValidator
	 */
	private TemplateValidator $validator;

	/**
	 * Constructor.
	 *
	 * @param TemplateValidator|null $validator Validator.
	 */
	public function __construct( ?TemplateValidator $validator = null ) {
		$this->validator = $validator ?? new TemplateValidator();
	}

	/**
	 * Validates every template folder and builds the manifest.
	 *
	 * @param string $templates_dir Absolute path of templates/.
	 * @return array{manifest: array{schema: int, templates: list<array<string, mixed>>}, reports: array<string, array{errors: list<string>, warnings: list<string>}>}
	 */
	public function build( string $templates_dir ): array {
		$templates_dir = rtrim( str_replace( '\\', '/', $templates_dir ), '/' );
		$entries       = array();
		$reports       = array();
		$codes         = array();

		foreach ( $this->template_dirs( $templates_dir ) as $slug => $dir ) {
			$result           = $this->validator->validate( $dir );
			$reports[ $slug ] = array(
				'errors'   => $result['errors'],
				'warnings' => $result['warnings'],
			);
			if ( array() !== $result['errors'] || null === $result['meta'] ) {
				continue;
			}

			$meta = $result['meta'];
			$code = isset( $meta['code'] ) ? (string) $meta['code'] : null;
			if ( null !== $code && isset( $codes[ $code ] ) ) {
				$reports[ $slug ]['errors'][] = sprintf( 'meta.json : code « %s » déjà utilisé par « %s ».', $code, $codes[ $code ] );
				continue;
			}
			if ( null !== $code ) {
				$codes[ $code ] = $slug;
			}

			$entries[] = array(
				'id'            => $slug,
				'title'         => (string) $meta['title'],
				'description'   => isset( $meta['description'] ) ? (string) $meta['description'] : '',
				'type'          => (string) $meta['type'],
				'category'      => (string) $meta['category'],
				'tags'          => array_values( isset( $meta['tags'] ) ? (array) $meta['tags'] : array() ),
				'version'       => (string) $meta['version'],
				'elementor_min' => isset( $meta['elementor_min'] ) ? (string) $meta['elementor_min'] : null,
				'variants'      => $result['variants'],
				'widgets'       => $result['widgets'],
				'thumbnail'     => $slug . '/thumbnail.webp',
				'code'          => $code,
				'sector'        => isset( $meta['sector'] ) ? (string) $meta['sector'] : null,
				'motion'        => isset( $meta['motion'] ) ? (string) $meta['motion'] : null,
				'accent'        => isset( $meta['accent'] ) ? strtolower( (string) $meta['accent'] ) : null,
				'preview'       => is_file( $dir . '/' . Schema::PREVIEW_FILE ) ? $slug . '/' . Schema::PREVIEW_FILE : null,
				'hash'          => $this->hash( $dir, $result['variants'] ),
			);
		}

		return array(
			'manifest' => array(
				'schema'    => self::SCHEMA_VERSION,
				'templates' => $entries,
			),
			'reports'  => $reports,
		);
	}

	/**
	 * Encodes the manifest as stable, readable JSON.
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 */
	public static function encode( array $manifest ): string {
		return json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Runs outside WordPress.
	}

	/**
	 * Lists template folders, sorted by slug.
	 *
	 * @param string $templates_dir Absolute path of templates/.
	 * @return array<string, string> Folder path keyed by slug.
	 */
	private function template_dirs( string $templates_dir ): array {
		$dirs = array();
		foreach ( (array) glob( $templates_dir . '/*', GLOB_ONLYDIR ) as $dir ) {
			if ( is_string( $dir ) ) {
				$dirs[ basename( $dir ) ] = str_replace( '\\', '/', $dir );
			}
		}
		ksort( $dirs, SORT_STRING );
		return $dirs;
	}

	/**
	 * Content hash of a template: lets the plugin detect a newer template version.
	 *
	 * @param string   $dir      Template folder.
	 * @param string[] $variants Variants.
	 */
	private function hash( string $dir, array $variants ): string {
		$context = hash_init( 'sha256' );
		foreach ( array_merge( array( 'meta.json' ), array_map( static fn( $v ) => "content.$v.json", $variants ) ) as $file ) {
			// Normalise line endings so the hash is identical on every OS.
			hash_update( $context, str_replace( "\r\n", "\n", (string) file_get_contents( $dir . '/' . $file ) ) );
		}
		return substr( hash_final( $context ), 0, 16 );
	}
}
