<?php
/**
 * Validates a template folder (meta.json, content variants, media, thumbnail).
 *
 * Pure PHP (no WordPress): used by bin/validate-templates.php, the CI and the tests.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local files, also runs outside WordPress.

/**
 * Template folder validator.
 */
final class TemplateValidator {

	private const SLUG_PATTERN       = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
	private const SEMVER_PATTERN     = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/';
	private const ELEMENT_ID_PATTERN = '/^[a-z0-9]{7,8}$/';
	private const MEDIA_EXTENSIONS   = 'jpe?g|png|gif|webp|avif|svg|mp4|webm|mp3';

	/**
	 * Errors found during the current run.
	 *
	 * @var list<string>
	 */
	private array $errors = array();

	/**
	 * Warnings found during the current run.
	 *
	 * @var list<string>
	 */
	private array $warnings = array();

	/**
	 * Widget/element types used, keyed by type.
	 *
	 * @var array<string, true>
	 */
	private array $widgets = array();

	/**
	 * Media files referenced through the media token, keyed by relative path.
	 *
	 * @var array<string, true>
	 */
	private array $media_refs = array();

	/**
	 * Element IDs seen in the current variant.
	 *
	 * @var array<string, true>
	 */
	private array $ids = array();

	/**
	 * Validates one template folder.
	 *
	 * @param string $dir Absolute path of templates/<slug>.
	 * @return array{errors: list<string>, warnings: list<string>, meta: array<string, mixed>|null, widgets: list<string>, variants: list<string>}
	 */
	public function validate( string $dir ): array {
		$this->errors     = array();
		$this->warnings   = array();
		$this->widgets    = array();
		$this->media_refs = array();

		$dir  = rtrim( str_replace( '\\', '/', $dir ), '/' );
		$slug = basename( $dir );
		$meta = $this->read_json( $dir . '/meta.json', 'meta.json' );

		$variants = array();
		if ( null !== $meta ) {
			$variants = $this->validate_meta( $meta, $slug );
			foreach ( $variants as $variant ) {
				$this->validate_variant( $dir, $variant, (string) ( $meta['type'] ?? '' ) );
			}
		}

		if ( ! is_file( $dir . '/thumbnail.webp' ) ) {
			$this->errors[] = 'thumbnail.webp manquant.';
		}

		$this->check_media_files( $dir );

		$widgets = array_keys( $this->widgets );
		sort( $widgets );

		return array(
			'errors'   => $this->errors,
			'warnings' => $this->warnings,
			'meta'     => $meta,
			'widgets'  => $widgets,
			'variants' => $variants,
		);
	}

	/**
	 * Validates meta.json and returns the declared, valid variants.
	 *
	 * @param array<string, mixed> $meta Decoded meta.json.
	 * @param string               $slug Folder name.
	 * @return list<string>
	 */
	private function validate_meta( array $meta, string $slug ): array {
		foreach ( Schema::REQUIRED_META as $key ) {
			if ( ! array_key_exists( $key, $meta ) ) {
				$this->errors[] = sprintf( 'meta.json : champ « %s » manquant.', $key );
			}
		}

		if ( ! preg_match( self::SLUG_PATTERN, $slug ) ) {
			$this->errors[] = sprintf( 'Nom de dossier « %s » invalide (attendu : kebab-case).', $slug );
		}
		if ( isset( $meta['id'] ) && $meta['id'] !== $slug ) {
			$this->errors[] = sprintf( 'meta.json : id « %s » différent du dossier « %s ».', (string) $meta['id'], $slug );
		}
		foreach ( array( 'title', 'category' ) as $key ) {
			if ( isset( $meta[ $key ] ) && ( ! is_string( $meta[ $key ] ) || '' === trim( $meta[ $key ] ) ) ) {
				$this->errors[] = sprintf( 'meta.json : « %s » doit être une chaîne non vide.', $key );
			}
		}
		if ( isset( $meta['type'] ) && ! in_array( $meta['type'], Schema::TYPES, true ) ) {
			$this->errors[] = sprintf( 'meta.json : type « %s » invalide (autorisés : %s).', (string) $meta['type'], implode( ', ', Schema::TYPES ) );
		}
		if ( isset( $meta['version'] ) && ( ! is_string( $meta['version'] ) || ! preg_match( self::SEMVER_PATTERN, $meta['version'] ) ) ) {
			$this->errors[] = 'meta.json : « version » doit suivre SemVer (ex. 1.0.0).';
		}
		if ( isset( $meta['elementor_min'] ) && ( ! is_string( $meta['elementor_min'] ) || ! preg_match( '/^\d+\.\d+(?:\.\d+)?$/', $meta['elementor_min'] ) ) ) {
			$this->errors[] = 'meta.json : « elementor_min » doit être une version (ex. 3.25.0).';
		}
		if ( isset( $meta['tags'] ) && ( ! is_array( $meta['tags'] ) || array() !== array_filter( $meta['tags'], static fn( $tag ) => ! is_string( $tag ) ) ) ) {
			$this->errors[] = 'meta.json : « tags » doit être une liste de chaînes.';
		}

		$variants = array();
		if ( isset( $meta['variants'] ) ) {
			if ( ! is_array( $meta['variants'] ) || array() === $meta['variants'] ) {
				$this->errors[] = 'meta.json : « variants » doit être une liste non vide (v3, v4).';
			} else {
				foreach ( $meta['variants'] as $variant ) {
					if ( Schema::VARIANT_V3 === $variant || Schema::VARIANT_V4 === $variant ) {
						$variants[] = $variant;
					} else {
						$this->errors[] = sprintf( 'meta.json : variante « %s » inconnue.', is_scalar( $variant ) ? (string) $variant : gettype( $variant ) );
					}
				}
				if ( ! in_array( Schema::VARIANT_V3, $variants, true ) ) {
					$this->warnings[] = 'Pas de variante v3 : le template sera masqué sur les sites sans éditeur Atomic.';
				}
			}
		}

		return array_values( array_unique( $variants ) );
	}

	/**
	 * Validates content.<variant>.json.
	 *
	 * @param string $dir     Template folder.
	 * @param string $variant v3 or v4.
	 * @param string $type    Template type from meta.json.
	 */
	private function validate_variant( string $dir, string $variant, string $type ): void {
		$file = sprintf( 'content.%s.json', $variant );
		$data = $this->read_json( $dir . '/' . $file, $file );
		if ( null === $data ) {
			return;
		}

		if ( ! isset( $data['content'] ) || ! is_array( $data['content'] ) || array() === $data['content'] ) {
			$this->errors[] = sprintf( '%s : « content » doit être une liste d\'éléments non vide.', $file );
			return;
		}
		if ( isset( $data['type'] ) && '' !== $type && $data['type'] !== $type ) {
			$this->warnings[] = sprintf( '%s : type « %s » différent de meta.json (« %s »).', $file, (string) $data['type'], $type );
		}
		if ( isset( $data['page_settings'] ) && ! is_array( $data['page_settings'] ) ) {
			$this->errors[] = sprintf( '%s : « page_settings » doit être un objet.', $file );
		} elseif ( isset( $data['page_settings'] ) ) {
			$this->check_settings( $data['page_settings'], $file . ' > page_settings' );
		}

		$this->ids = array();
		foreach ( $data['content'] as $index => $element ) {
			$this->validate_element( $element, $variant, sprintf( '%s > content[%d]', $file, $index ) );
		}
	}

	/**
	 * Validates one element and its children.
	 *
	 * @param mixed  $element Decoded element.
	 * @param string $variant v3 or v4.
	 * @param string $path    Location used in messages.
	 */
	private function validate_element( $element, string $variant, string $path ): void {
		if ( ! is_array( $element ) ) {
			$this->errors[] = sprintf( '%s : élément invalide.', $path );
			return;
		}

		$id = $element['id'] ?? null;
		if ( ! is_string( $id ) || ! preg_match( self::ELEMENT_ID_PATTERN, $id ) ) {
			$this->errors[] = sprintf( '%s : id manquant ou invalide (7-8 caractères a-z0-9).', $path );
		} elseif ( isset( $this->ids[ $id ] ) ) {
			$this->errors[] = sprintf( '%s : id « %s » dupliqué.', $path, $id );
		} else {
			$this->ids[ $id ] = true;
			$path            .= sprintf( ' (#%s)', $id );
		}

		$el_type     = $element['elType'] ?? null;
		$widget_type = $element['widgetType'] ?? null;
		$this->check_type( $variant, $el_type, $widget_type, $path );

		if ( isset( $element['settings'] ) ) {
			if ( ! is_array( $element['settings'] ) ) {
				$this->errors[] = sprintf( '%s : « settings » doit être un objet.', $path );
			} else {
				$this->check_settings( $element['settings'], $path );
			}
		}

		if ( isset( $element['elements'] ) ) {
			if ( ! is_array( $element['elements'] ) ) {
				$this->errors[] = sprintf( '%s : « elements » doit être une liste.', $path );
				return;
			}
			foreach ( $element['elements'] as $index => $child ) {
				$this->validate_element( $child, $variant, sprintf( '%s > elements[%d]', $path, $index ) );
			}
		}
	}

	/**
	 * Checks elType/widgetType against the Elementor Free lists.
	 *
	 * @param string $variant     v3 or v4.
	 * @param mixed  $el_type     elType value.
	 * @param mixed  $widget_type widgetType value.
	 * @param string $path        Location used in messages.
	 */
	private function check_type( string $variant, $el_type, $widget_type, string $path ): void {
		if ( ! is_string( $el_type ) ) {
			$this->errors[] = sprintf( '%s : elType manquant.', $path );
			return;
		}

		if ( 'widget' === $el_type ) {
			if ( ! is_string( $widget_type ) || '' === $widget_type ) {
				$this->errors[] = sprintf( '%s : widgetType manquant.', $path );
				return;
			}
			$allowed = Schema::VARIANT_V4 === $variant
				? array_merge( Schema::V3_FREE_WIDGETS, Schema::V4_FREE_ELEMENTS )
				: Schema::V3_FREE_WIDGETS;
			if ( ! in_array( $widget_type, $allowed, true ) && 0 !== strpos( $widget_type, 'plugt-' ) ) {
				$this->errors[] = sprintf( '%s : widget « %s » absent de la liste blanche Elementor Free.', $path, $widget_type );
				return;
			}
			$this->widgets[ $widget_type ] = true;
			return;
		}

		$allowed = Schema::VARIANT_V4 === $variant
			? array_merge( Schema::V3_ELEMENT_TYPES, Schema::V4_FREE_ELEMENTS )
			: Schema::V3_ELEMENT_TYPES;
		if ( ! in_array( $el_type, $allowed, true ) ) {
			$this->errors[] = sprintf( '%s : elType « %s » non autorisé en %s.', $path, $el_type, $variant );
		}
	}

	/**
	 * Rejects Pro-only settings and external media; records media token references.
	 *
	 * @param array<mixed> $settings Settings (possibly nested).
	 * @param string       $path     Location used in messages.
	 */
	private function check_settings( array $settings, string $path ): void {
		foreach ( $settings as $key => $value ) {
			if ( is_string( $key ) ) {
				foreach ( Schema::PRO_SETTING_PREFIXES as $prefix ) {
					if ( 0 === strpos( $key, $prefix ) ) {
						$this->errors[] = sprintf( '%s : réglage « %s » réservé à Elementor Pro.', $path, $key );
						break;
					}
				}
			}

			if ( is_array( $value ) ) {
				$this->check_settings( $value, $path );
				continue;
			}
			if ( ! is_string( $value ) ) {
				continue;
			}

			if ( preg_match( '#^(?:https?:)?//\S+\.(?:' . self::MEDIA_EXTENSIONS . ')(?:\?\S*)?$#i', $value ) ) {
				$this->errors[] = sprintf( '%s : média externe « %s » interdit, utilisez %s/fichier.', $path, $value, Schema::MEDIA_TOKEN );
			}

			$token = preg_quote( Schema::MEDIA_TOKEN, '#' );
			if ( preg_match_all( '#' . $token . '/([A-Za-z0-9._/-]+)#', $value, $matches ) ) {
				foreach ( $matches[1] as $relative ) {
					if ( false !== strpos( $relative, '..' ) ) {
						$this->errors[] = sprintf( '%s : chemin média « %s » invalide.', $path, $relative );
						continue;
					}
					$this->media_refs[ $relative ] = true;
				}
			}
		}
	}

	/**
	 * Checks that referenced media exist and flags unused files.
	 *
	 * @param string $dir Template folder.
	 */
	private function check_media_files( string $dir ): void {
		foreach ( array_keys( $this->media_refs ) as $relative ) {
			if ( ! is_file( $dir . '/media/' . $relative ) ) {
				$this->errors[] = sprintf( 'Média référencé introuvable : media/%s.', $relative );
			}
		}

		if ( ! is_dir( $dir . '/media' ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir . '/media', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( ! $file instanceof \SplFileInfo || ! $file->isFile() ) {
				continue;
			}
			$relative = ltrim( substr( str_replace( '\\', '/', $file->getPathname() ), strlen( $dir . '/media' ) ), '/' );
			if ( ! isset( $this->media_refs[ $relative ] ) ) {
				$this->warnings[] = sprintf( 'Média inutilisé : media/%s.', $relative );
			}
		}
	}

	/**
	 * Reads and decodes a JSON object file, recording errors.
	 *
	 * @param string $file  Absolute path.
	 * @param string $label Name used in messages.
	 * @return array<string, mixed>|null
	 */
	private function read_json( string $file, string $label ): ?array {
		if ( ! is_file( $file ) ) {
			$this->errors[] = sprintf( '%s manquant.', $label );
			return null;
		}
		try {
			$data = json_decode( (string) file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			$this->errors[] = sprintf( '%s : JSON invalide (%s).', $label, $e->getMessage() );
			return null;
		}
		if ( ! is_array( $data ) ) {
			$this->errors[] = sprintf( '%s : un objet JSON est attendu.', $label );
			return null;
		}
		return $data;
	}
}
