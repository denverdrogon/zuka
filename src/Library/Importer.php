<?php
/**
 * Imports a template as a page, an Elementor library template, or editor-ready content.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

use PlugTemplates\Elementor\Adapter;
use WP_Error;

/**
 * Template importer.
 */
class Importer {

	public const MODE_PAGE    = 'page';
	public const MODE_LIBRARY = 'library';
	public const MODE_EDITOR  = 'editor';
	public const MODES        = array( self::MODE_PAGE, self::MODE_LIBRARY, self::MODE_EDITOR );

	public const SOURCE_META  = '_plugt_source';
	public const VARIANT_META = '_plugt_variant';

	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Elementor adapter.
	 *
	 * @var Adapter
	 */
	private Adapter $elementor;

	/**
	 * Media source.
	 *
	 * @var MediaSource
	 */
	private MediaSource $media;

	/**
	 * Content transformer.
	 *
	 * @var ContentTransformer
	 */
	private ContentTransformer $transformer;

	/**
	 * Constructor.
	 *
	 * @param Repository         $repository  Repository.
	 * @param Adapter            $elementor   Elementor adapter.
	 * @param MediaSource        $media       Media source.
	 * @param ContentTransformer $transformer Content transformer.
	 */
	public function __construct( Repository $repository, Adapter $elementor, MediaSource $media, ContentTransformer $transformer ) {
		$this->repository  = $repository;
		$this->elementor   = $elementor;
		$this->media       = $media;
		$this->transformer = $transformer;
	}

	/**
	 * Variant to use on this site: v4 when Atomic is active and available, otherwise v3.
	 *
	 * @param array<string, mixed> $entry Manifest entry.
	 */
	public function pick_variant( array $entry ): ?string {
		$variants = (array) ( $entry['variants'] ?? array() );
		if ( $this->elementor->is_atomic_active() && in_array( Schema::VARIANT_V4, $variants, true ) ) {
			return Schema::VARIANT_V4;
		}
		return in_array( Schema::VARIANT_V3, $variants, true ) ? Schema::VARIANT_V3 : null;
	}

	/**
	 * Why a template cannot be used on this site, or null when it can.
	 *
	 * @param array<string, mixed> $entry Manifest entry.
	 */
	public function incompatibility( array $entry ): ?WP_Error {
		$required = isset( $entry['elementor_min'] ) ? (string) $entry['elementor_min'] : '';
		$current  = $this->elementor->version();
		if ( '' !== $required && ( null === $current || version_compare( $current, $required, '<' ) ) ) {
			return new WP_Error(
				'plugt_elementor_too_old',
				/* translators: %s: Elementor version. */
				sprintf( __( 'Ce template nécessite Elementor %s ou supérieur.', 'plug-templates' ), $required ),
				array( 'status' => 409 )
			);
		}
		$variant = $this->pick_variant( $entry );
		if ( null === $variant ) {
			return new WP_Error(
				'plugt_no_variant',
				__( 'Ce template n\'existe qu\'en version Atomic (Elementor 4) : activez l\'éditeur Atomic pour l\'utiliser.', 'plug-templates' ),
				array( 'status' => 409 )
			);
		}
		if ( Schema::VARIANT_V3 === $variant && ! $this->elementor->is_container_active() && $this->uses_containers( (string) $entry['id'] ) ) {
			return new WP_Error(
				'plugt_containers_inactive',
				__( 'Ce template utilise des conteneurs Flexbox : activez « Conteneurs » dans Elementor > Paramètres > Fonctionnalités.', 'plug-templates' ),
				array( 'status' => 409 )
			);
		}
		return null;
	}

	/**
	 * Whether the v3 content of a template contains `container` elements.
	 *
	 * @param string $id Template id.
	 */
	private function uses_containers( string $id ): bool {
		$data = $this->repository->content( $id, Schema::VARIANT_V3 );
		if ( null === $data ) {
			return false;
		}
		return false !== strpos( (string) wp_json_encode( $data['content'] ), '"elType":"container"' );
	}

	/**
	 * Loads a template and prepares its content (fresh ids, media imported).
	 *
	 * @param string $id Template id.
	 * @return array{template: array<string, mixed>, variant: string, content: list<array<string, mixed>>, page_settings: array<mixed>}|WP_Error
	 */
	public function prepare( string $id ) {
		$entry = $this->repository->get( $id );
		if ( null === $entry ) {
			return new WP_Error( 'plugt_not_found', __( 'Template introuvable.', 'plug-templates' ), array( 'status' => 404 ) );
		}

		$error = $this->incompatibility( $entry );
		if ( null !== $error ) {
			return $error;
		}

		$variant = (string) $this->pick_variant( $entry );
		$data    = $this->repository->content( $id, $variant );
		if ( null === $data ) {
			return new WP_Error( 'plugt_invalid_content', __( 'Le contenu du template est illisible.', 'plug-templates' ), array( 'status' => 500 ) );
		}

		$resolve = $this->media_resolver( $id );

		return array(
			'template'      => $entry,
			'variant'       => $variant,
			'content'       => $this->transformer->transform( $data['content'], $resolve ),
			'page_settings' => $this->transformer->transform_settings( is_array( $data['page_settings'] ?? null ) ? $data['page_settings'] : array(), $resolve ),
		);
	}

	/**
	 * Imports a template.
	 *
	 * @param string $id    Template id.
	 * @param string $mode  One of self::MODES.
	 * @param string $title Title of the created post; defaults to the template title.
	 * @return array<string, mixed>|WP_Error
	 */
	public function import( string $id, string $mode, string $title = '' ) {
		if ( ! in_array( $mode, self::MODES, true ) ) {
			return new WP_Error( 'plugt_invalid_mode', __( 'Mode d\'import invalide.', 'plug-templates' ), array( 'status' => 400 ) );
		}

		$prepared = $this->prepare( $id );
		if ( $prepared instanceof WP_Error ) {
			return $prepared;
		}

		if ( self::MODE_EDITOR === $mode ) {
			return array(
				'template_id'   => $id,
				'variant'       => $prepared['variant'],
				'content'       => $prepared['content'],
				'page_settings' => $prepared['page_settings'],
			);
		}

		$entry = $prepared['template'];
		$title = '' !== trim( $title ) ? $title : (string) $entry['title'];

		$post_id = self::MODE_PAGE === $mode
			? $this->elementor->create_page( $title, $prepared['content'], $prepared['page_settings'] )
			: $this->elementor->create_library_template( $title, (string) $entry['type'], $prepared['content'], $prepared['page_settings'] );

		if ( $post_id instanceof WP_Error ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::SOURCE_META, $id . '@' . (string) $entry['version'] );
		update_post_meta( $post_id, self::VARIANT_META, $prepared['variant'] );

		return array(
			'template_id' => $id,
			'variant'     => $prepared['variant'],
			'post_id'     => $post_id,
			'edit_url'    => $this->elementor->edit_url( $post_id ),
		);
	}

	/**
	 * Media resolver for one template, memoised per file.
	 *
	 * @param string $id Template id.
	 */
	private function media_resolver( string $id ): callable {
		$cache = array();
		return function ( string $relative ) use ( $id, &$cache ): ?array {
			if ( ! array_key_exists( $relative, $cache ) ) {
				$path               = $this->repository->media_path( $id, $relative );
				$cache[ $relative ] = null === $path ? null : $this->media->resolve( $id, $path );
			}
			return $cache[ $relative ];
		};
	}
}
