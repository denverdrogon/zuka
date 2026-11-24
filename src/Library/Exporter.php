<?php
/**
 * Turns Elementor page data into a template: the exact inverse of ContentTransformer.
 *
 * Pure PHP: attachments are located through a callable, so this class is testable without WordPress.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Local files, CLI messages; also testable outside WordPress.

/**
 * Template exporter.
 */
final class Exporter {

	/**
	 * Ids generated during the current run.
	 *
	 * @var array<string, true>
	 */
	private array $ids = array();

	/**
	 * Media to copy: file name in media/ => absolute source path.
	 *
	 * @var array<string, string>
	 */
	private array $media = array();

	/**
	 * Values left hard-coded (not equal to a kit global), unique.
	 *
	 * @var array<string, true>
	 */
	private array $hardcoded = array();

	/**
	 * Problems that do not stop the export.
	 *
	 * @var list<string>
	 */
	private array $warnings = array();

	/**
	 * Kit globals of the current run.
	 *
	 * @var array{colors: list<array{_id: string, color: string}>, typography: list<array<string, mixed>>}
	 */
	private array $kit = array(
		'colors'     => array(),
		'typography' => array(),
	);

	/**
	 * Converts Elementor data into template content.
	 *
	 * The locator receives an attachment id and URL and returns the absolute path
	 * of the file to ship (possibly resized), or null when it cannot be found.
	 *
	 * @param array<mixed>                                                                                   $elements      Elementor elements.
	 * @param array<mixed>                                                                                   $page_settings Page settings.
	 * @param array{colors: list<array{_id: string, color: string}>, typography: list<array<string, mixed>>} $kit Kit globals.
	 * @param callable                                                                                       $locate        Attachment locator.
	 * @return array{content: list<array<string, mixed>>, page_settings: array<mixed>, media: array<string, string>, hardcoded: list<string>, warnings: list<string>}
	 */
	public function transform( array $elements, array $page_settings, array $kit, callable $locate ): array {
		$this->ids       = array();
		$this->media     = array();
		$this->hardcoded = array();
		$this->warnings  = array();
		$this->kit       = $kit;

		$content = array();
		foreach ( $elements as $element ) {
			if ( is_array( $element ) ) {
				$content[] = $this->element( $element, $locate );
			}
		}

		$settings = $this->settings( $page_settings, $locate );
		$hard     = array_keys( $this->hardcoded );
		sort( $hard );

		return array(
			'content'       => $content,
			'page_settings' => $settings,
			'media'         => $this->media,
			'hardcoded'     => $hard,
			'warnings'      => $this->warnings,
		);
	}

	/**
	 * Builds the meta.json content of an exported template.
	 *
	 * @param string $slug     Folder name.
	 * @param string $title    Title.
	 * @param string $type     page or section.
	 * @param string $category Category key.
	 * @param string $code     Library code (TPL-NN).
	 * @param string $date     Creation date (Y-m-d).
	 * @return array<string, mixed>
	 */
	public function meta( string $slug, string $title, string $type, string $category, string $code, string $date ): array {
		return array(
			'id'            => $slug,
			'title'         => $title,
			'description'   => '',
			'type'          => $type,
			'category'      => $category,
			'code'          => $code,
			'tags'          => array(),
			'version'       => '1.0.0',
			'elementor_min' => defined( 'PLUGT_MIN_ELEMENTOR' ) ? PLUGT_MIN_ELEMENTOR : '3.25.0',
			'variants'      => array( Schema::VARIANT_V3 ),
			'added'         => $date,
		);
	}

	/**
	 * Next free library code (TPL-NN) among the meta.json files of templates/.
	 *
	 * @param string $templates_dir Absolute path of templates/.
	 */
	public function next_code( string $templates_dir ): string {
		$max = 0;
		foreach ( (array) glob( rtrim( $templates_dir, '/\\' ) . '/*/meta.json' ) as $file ) {
			$meta = is_string( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
			if ( is_array( $meta ) && isset( $meta['code'] ) && is_string( $meta['code'] ) && preg_match( '/^TPL-(\d+)$/', $meta['code'], $m ) ) {
				$max = max( $max, (int) $m[1] );
			}
		}
		return sprintf( 'TPL-%02d', $max + 1 );
	}

	/**
	 * Writes templates/<slug>/ (meta.json, content.v3.json, media/).
	 *
	 * @param string               $templates_dir Absolute path of templates/.
	 * @param string               $slug          Folder name.
	 * @param array<string, mixed> $meta          meta.json content.
	 * @param array<string, mixed> $result        Result of transform().
	 * @param bool                 $force         Overwrite an existing folder.
	 * @return string Absolute path of the template folder.
	 * @throws \RuntimeException When the folder exists (without $force) or cannot be written.
	 */
	public function write( string $templates_dir, string $slug, array $meta, array $result, bool $force ): string {
		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
			throw new \RuntimeException( 'Slug invalide (attendu : kebab-case).' );
		}
		$dir = rtrim( str_replace( '\\', '/', $templates_dir ), '/' ) . '/' . $slug;
		if ( file_exists( $dir ) && ! $force ) {
			throw new \RuntimeException( sprintf( 'templates/%s existe déjà (utilisez --force pour l\'écraser).', $slug ) );
		}
		if ( ! is_dir( $dir . '/media' ) && ! mkdir( $dir . '/media', 0755, true ) ) {
			throw new \RuntimeException( 'Impossible de créer le dossier du template.' );
		}

		foreach ( (array) $result['media'] as $name => $source ) {
			if ( ! copy( (string) $source, $dir . '/media/' . $name ) ) {
				throw new \RuntimeException( sprintf( 'Impossible de copier le média « %s ».', $name ) );
			}
		}

		$content = array(
			'version' => '0.4',
			'title'   => (string) $meta['title'],
			'type'    => (string) $meta['type'],
			'content' => $result['content'],
		);
		if ( array() !== $result['page_settings'] ) {
			$content['page_settings'] = $result['page_settings'];
		}

		file_put_contents( $dir . '/meta.json', self::encode( $meta ) );
		file_put_contents( $dir . '/content.v3.json', self::encode( $content ) );

		return $dir;
	}

	/**
	 * Encodes a template file as indented JSON.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public static function encode( array $data ): string {
		return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Runs outside WordPress.
	}

	/**
	 * Transforms one element and its children.
	 *
	 * @param array<mixed> $element Element.
	 * @param callable     $locate  Attachment locator.
	 * @return array<string, mixed>
	 */
	private function element( array $element, callable $locate ): array {
		$element['id'] = $this->new_id();

		if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
			$element['settings'] = $this->settings( $element['settings'], $locate );
		}
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$children = array();
			foreach ( $element['elements'] as $child ) {
				if ( is_array( $child ) ) {
					$children[] = $this->element( $child, $locate );
				}
			}
			$element['elements'] = $children;
		}

		return $element;
	}

	/**
	 * Walks settings: media objects, kit globals and repeater item ids.
	 *
	 * @param array<mixed> $settings Settings (possibly nested).
	 * @param callable     $locate   Attachment locator.
	 * @return array<mixed>
	 */
	private function settings( array $settings, callable $locate ): array {
		// Media object: { id, url } pointing to an attachment.
		if ( isset( $settings['url'], $settings['id'] ) && is_string( $settings['url'] ) && is_numeric( $settings['id'] ) && (int) $settings['id'] > 0 ) {
			$source = $locate( (int) $settings['id'], $settings['url'] );
			if ( is_string( $source ) && is_file( $source ) ) {
				$settings['url'] = Schema::MEDIA_TOKEN . '/' . $this->register_media( $source );
				$settings['id']  = '';
			} else {
				$this->warnings[] = sprintf( 'Média #%d introuvable : %s', (int) $settings['id'], $settings['url'] );
			}
		}

		if ( isset( $settings['_id'] ) && is_string( $settings['_id'] ) ) {
			$settings['_id'] = $this->new_id();
		}

		$settings = $this->globalize( $settings );

		foreach ( $settings as $key => $value ) {
			if ( '__globals__' === $key ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$settings[ $key ] = $this->settings( $value, $locate );
			} elseif ( is_string( $value ) && preg_match( '#https?://\S+\.(?:jpe?g|png|gif|webp|avif|svg|mp4|webm|mp3)\b#i', $value, $match ) ) {
				$this->warnings[] = sprintf( 'URL de média restée dans « %s » : %s', (string) $key, $match[0] );
			}
		}

		return $settings;
	}

	/**
	 * Replaces colours and typography equal to a kit global with a `__globals__` reference.
	 *
	 * @param array<mixed> $settings Settings of one element (one level).
	 * @return array<mixed>
	 */
	private function globalize( array $settings ): array {
		$globals = isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ? $settings['__globals__'] : array();

		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) || ! is_string( $value ) ) {
				continue;
			}
			if ( preg_match( '/(?:^|_)color$/', $key ) && preg_match( '/^#[0-9a-f]{3,8}$/i', $value ) ) {
				$id = $this->color_id( $value );
				if ( null === $id ) {
					$this->hardcoded[ 'couleur ' . strtolower( $value ) ] = true;
					continue;
				}
				$globals[ $key ] = 'globals/colors?id=' . $id;
				unset( $settings[ $key ] );
			} elseif ( 'typography_typography' === substr( $key, -21 ) && 'custom' === $value && empty( $globals[ $key ] ) ) {
				$prefix = substr( $key, 0, -10 );
				$id     = $this->typography_id( $settings, $prefix );
				if ( null !== $id ) {
					$globals[ $key ] = 'globals/typography?id=' . $id;
					foreach ( array_keys( $settings ) as $name ) {
						if ( 0 === strpos( (string) $name, $prefix ) ) {
							unset( $settings[ $name ] );
						}
					}
				}
			}
		}

		foreach ( $settings as $key => $value ) {
			if ( is_string( $key ) && '' !== $value && is_string( $value ) && 'typography_font_family' === substr( $key, -22 ) ) {
				$this->hardcoded[ 'police ' . $value ] = true;
			}
		}

		if ( array() !== $globals ) {
			$settings['__globals__'] = $globals;
		}
		return $settings;
	}

	/**
	 * Kit colour id whose value equals the given colour, or null.
	 *
	 * @param string $value Hex colour.
	 */
	private function color_id( string $value ): ?string {
		foreach ( $this->kit['colors'] as $color ) {
			if ( strtolower( trim( $color['color'] ) ) === strtolower( $value ) ) {
				return $color['_id'];
			}
		}
		return null;
	}

	/**
	 * Kit typography id strictly equal to the typography keys under a prefix, or null.
	 *
	 * @param array<mixed> $settings Element settings.
	 * @param string       $prefix   Key prefix (`typography_`, `title_typography_`…).
	 */
	private function typography_id( array $settings, string $prefix ): ?string {
		$own = array();
		foreach ( $settings as $key => $value ) {
			if ( is_string( $key ) && 0 === strpos( $key, $prefix ) && $prefix . 'typography' !== $key && '' !== $value && array() !== $value ) {
				$own[ substr( $key, strlen( $prefix ) ) ] = $this->normalize( $value );
			}
		}

		foreach ( $this->kit['typography'] as $item ) {
			$fields = array();
			foreach ( $item as $key => $value ) {
				if ( 0 === strpos( (string) $key, 'typography_' ) && 'typography_typography' !== $key && '' !== $value && array() !== $value ) {
					$fields[ substr( (string) $key, 11 ) ] = $this->normalize( $value );
				}
			}
			ksort( $fields );
			ksort( $own );
			if ( array() !== $fields && $fields === $own ) {
				return (string) $item['_id'];
			}
		}
		return null;
	}

	/**
	 * Comparable string form of a setting value.
	 *
	 * @param mixed $value Value.
	 */
	private function normalize( $value ): string {
		return is_scalar( $value ) ? strtolower( (string) $value ) : (string) json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Runs outside WordPress.
	}

	/**
	 * Records a file to ship and returns its name in media/ (unique).
	 *
	 * @param string $source Absolute path.
	 */
	private function register_media( string $source ): string {
		$existing = array_search( $source, $this->media, true );
		if ( false !== $existing ) {
			return (string) $existing;
		}
		$info = pathinfo( $source );
		$name = $info['basename'];
		$n    = 2;
		while ( isset( $this->media[ $name ] ) ) {
			$name = $info['filename'] . '-' . $n . ( isset( $info['extension'] ) ? '.' . $info['extension'] : '' );
			++$n;
		}
		$this->media[ $name ] = $source;
		return $name;
	}

	/**
	 * New 7-character element id, unique within the run.
	 */
	private function new_id(): string {
		do {
			$id = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
		} while ( isset( $this->ids[ $id ] ) );
		$this->ids[ $id ] = true;
		return $id;
	}
}
