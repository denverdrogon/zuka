<?php
/**
 * REST routes: list templates and import one.
 *
 * Authentication is WordPress' cookie + `wp_rest` nonce (X-WP-Nonce header),
 * checked by core before any permission callback runs.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Rest;

use PlugTemplates\Library\Importer;
use PlugTemplates\Library\Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Templates REST controller.
 */
final class TemplatesController {

	public const NAMESPACE = 'plugt/v1';

	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Importer.
	 *
	 * @var Importer
	 */
	private Importer $importer;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 * @param Importer   $importer   Importer.
	 */
	public function __construct( Repository $repository, Importer $importer ) {
		$this->repository = $repository;
		$this->importer   = $importer;
	}

	/**
	 * Hooks route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/templates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_templates' ),
				'permission_callback' => array( $this, 'can_list' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_import' ),
				'args'                => array(
					'id'    => array(
						'type'              => 'string',
						'required'          => true,
						'pattern'           => '^[a-z0-9]+(?:-[a-z0-9]+)*$',
						'sanitize_callback' => 'sanitize_key',
					),
					'mode'  => array(
						'type'    => 'string',
						'enum'    => Importer::MODES,
						'default' => Importer::MODE_PAGE,
					),
					'title' => array(
						'type'              => 'string',
						'default'           => '',
						'maxLength'         => 200,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Anyone who can write posts may browse the library.
	 */
	public function can_list(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Capability depends on what the import creates.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function can_import( WP_REST_Request $request ): bool {
		$mode = (string) $request->get_param( 'mode' );
		if ( Importer::MODE_PAGE === $mode ) {
			return current_user_can( 'edit_pages' ) && current_user_can( 'upload_files' );
		}
		return current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' );
	}

	/**
	 * GET /plugt/v1/templates
	 */
	public function list_templates(): WP_REST_Response {
		$items = array();
		foreach ( $this->repository->all() as $entry ) {
			$error   = $this->importer->incompatibility( $entry );
			$items[] = array(
				'id'            => $entry['id'],
				'title'         => $entry['title'],
				'description'   => $entry['description'] ?? '',
				'type'          => $entry['type'],
				'category'      => $entry['category'],
				'tags'          => $entry['tags'] ?? array(),
				'version'       => $entry['version'],
				'variants'      => $entry['variants'],
				'widgets'       => $entry['widgets'] ?? array(),
				'thumbnail'     => $this->repository->thumbnail_url( (string) $entry['id'] ),
				'code'          => $entry['code'] ?? null,
				'sector'        => $entry['sector'] ?? null,
				'motion'        => $entry['motion'] ?? null,
				'accent'        => $entry['accent'] ?? null,
				'preview'       => $this->repository->preview_url( (string) $entry['id'] ),
				'variant'       => null === $error ? $this->importer->pick_variant( $entry ) : null,
				'compatible'    => null === $error,
				'compat_reason' => null === $error ? '' : $error->get_error_message(),
			);
		}
		return new WP_REST_Response( $items );
	}

	/**
	 * POST /plugt/v1/import
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$result = $this->importer->import(
			(string) $request->get_param( 'id' ),
			(string) $request->get_param( 'mode' ),
			(string) $request->get_param( 'title' )
		);
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		return new WP_REST_Response( $result, isset( $result['post_id'] ) ? 201 : 200 );
	}
}
