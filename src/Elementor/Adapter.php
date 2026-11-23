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

use WP_Error;

/**
 * Elementor adapter. Not final so tests can substitute it.
 */
class Adapter {

	/** Elementor document type of a regular WordPress page. */
	private const PAGE_DOCUMENT = 'wp-page';

	/** Elementor library post type and its type taxonomy. */
	private const LIBRARY_POST_TYPE = 'elementor_library';
	private const LIBRARY_TAXONOMY  = 'elementor_library_type';

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
		if ( null === $plugin || ! isset( $plugin->experiments ) || ! is_object( $plugin->experiments ) || ! method_exists( $plugin->experiments, 'is_feature_active' ) ) {
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
	 * Whether Flexbox Containers are enabled. Without them, Elementor silently
	 * drops every `container` element on save.
	 */
	public function is_container_active(): bool {
		if ( ! $this->is_loaded() || ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( null === $plugin || ! isset( $plugin->experiments ) || ! is_object( $plugin->experiments ) || ! method_exists( $plugin->experiments, 'is_feature_active' ) ) {
			return false;
		}
		return (bool) $plugin->experiments->is_feature_active( 'container' );
	}

	/**
	 * Registers a callback fired once Elementor is fully initialised.
	 *
	 * @param callable $callback Callback.
	 */
	public function on_init( callable $callback ): void {
		add_action( 'elementor/init', $callback );
	}

	/**
	 * Calls $callback with the CSS classes (`css_classes` / `_css_classes`) of
	 * every element Elementor renders on the front end.
	 *
	 * @param callable $callback Receives a string of space-separated classes.
	 */
	public function on_element_classes( callable $callback ): void {
		add_action(
			'elementor/frontend/before_render',
			static function ( $element ) use ( $callback ): void {
				if ( ! is_object( $element ) || ! method_exists( $element, 'get_settings' ) ) {
					return;
				}
				$classes = '';
				foreach ( array( 'css_classes', '_css_classes' ) as $key ) {
					$value = $element->get_settings( $key );
					if ( is_string( $value ) && '' !== $value ) {
						$classes .= ' ' . $value;
					}
				}
				if ( '' !== $classes ) {
					$callback( trim( $classes ) );
				}
			}
		);
	}

	/**
	 * Registers a callback enqueuing assets inside the editor preview iframe.
	 *
	 * @param callable $callback Callback.
	 */
	public function on_preview_enqueue( callable $callback ): void {
		add_action( 'elementor/preview/enqueue_styles', $callback );
	}

	/**
	 * Registers a callback enqueuing scripts in the Elementor editor window.
	 *
	 * @param callable $callback Callback.
	 */
	public function on_editor_enqueue( callable $callback ): void {
		add_action( 'elementor/editor/after_enqueue_scripts', $callback );
	}

	/**
	 * JS integration points of the Elementor editor, passed to the editor script
	 * so that it never hard-codes Elementor internals.
	 *
	 * @return array<string, string>
	 */
	public function editor_js_contract(): array {
		return array(
			'importCommand'     => 'document/elements/import',
			'addSectionArea'    => '.elementor-add-new-section',
			'addTemplateButton' => '.elementor-add-template-button',
			'previewLoaded'     => 'preview:loaded',
		);
	}

	/**
	 * Whether the Elementor data saved for a post contains a string.
	 *
	 * @param int    $post_id Post id.
	 * @param string $needle  String to look for.
	 */
	public function post_data_contains( int $post_id, string $needle ): bool {
		$data = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_array( $data ) ) {
			$data = (string) wp_json_encode( $data );
		}
		return is_string( $data ) && false !== strpos( $data, $needle );
	}

	/**
	 * Creates a draft WordPress page built with Elementor.
	 *
	 * @param string       $title    Page title.
	 * @param array<mixed> $elements Elementor elements.
	 * @param array<mixed> $settings Page settings.
	 * @return int|WP_Error Post id.
	 */
	public function create_page( string $title, array $elements, array $settings ) {
		return $this->create_document(
			self::PAGE_DOCUMENT,
			array(
				'post_title'  => $title,
				'post_status' => 'draft',
				'post_type'   => 'page',
			),
			$elements,
			$settings
		);
	}

	/**
	 * Creates a template in Elementor > Templates (« Mes modèles »).
	 *
	 * @param string       $title    Template title.
	 * @param string       $type     Library type: page or section.
	 * @param array<mixed> $elements Elementor elements.
	 * @param array<mixed> $settings Page settings.
	 * @return int|WP_Error Post id.
	 */
	public function create_library_template( string $title, string $type, array $elements, array $settings ) {
		return $this->create_document(
			$type,
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => self::LIBRARY_POST_TYPE,
			),
			$elements,
			$settings
		);
	}

	/**
	 * Creates a private library template used only for previews.
	 *
	 * @param string       $title    Template title.
	 * @param string       $type     Library type: page or section.
	 * @param array<mixed> $elements Elementor elements.
	 * @param array<mixed> $settings Page settings.
	 * @return int|WP_Error Post id.
	 */
	public function create_hidden_template( string $title, string $type, array $elements, array $settings ) {
		return $this->create_document(
			$type,
			array(
				'post_title'  => $title,
				'post_status' => 'private',
				'post_type'   => self::LIBRARY_POST_TYPE,
			),
			$elements,
			$settings
		);
	}

	/**
	 * Keeps library posts carrying `$meta_key` out of every list of templates
	 * (Elementor > Mes modèles, the editor's template library, exports).
	 *
	 * @param string $meta_key Meta key marking hidden posts.
	 */
	public function hide_library_posts( string $meta_key ): void {
		add_action(
			'pre_get_posts',
			static function ( $query ) use ( $meta_key ): void {
				if ( ! $query instanceof \WP_Query ) {
					return;
				}
				$type = $query->get( 'post_type' );
				if ( self::LIBRARY_POST_TYPE !== $type && array( self::LIBRARY_POST_TYPE ) !== $type ) {
					return;
				}
				if ( $query->get( 'p' ) || $query->get( 'name' ) || $query->get( 'post__in' ) || $query->get( 'plugt_include_hidden' ) ) {
					return;
				}
				$meta_query   = (array) $query->get( 'meta_query' );
				$meta_query[] = array(
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				);
				$query->set( 'meta_query', $meta_query );
			}
		);
	}

	/**
	 * Ids of library posts (any status, hidden ones included) whose meta matches.
	 *
	 * @param string $meta_key   Meta key.
	 * @param string $meta_value Meta value.
	 * @return list<int>
	 */
	public function find_library_posts( string $meta_key, string $meta_value ): array {
		$ids = get_posts(
			array(
				'post_type'            => self::LIBRARY_POST_TYPE,
				'post_status'          => 'any',
				'posts_per_page'       => -1,
				'fields'               => 'ids',
				'meta_key'             => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Few posts, preview only.
				'meta_value'           => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Same.
				'plugt_include_hidden' => true,
				'suppress_filters'     => false,
			)
		);
		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * Front-end HTML of an Elementor document, its CSS enqueued.
	 *
	 * @param int $post_id Post id.
	 */
	public function render_document( int $post_id ): string {
		if ( ! $this->is_loaded() || ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( null === $plugin || ! isset( $plugin->frontend ) || ! method_exists( $plugin->frontend, 'get_builder_content_for_display' ) ) {
			return '';
		}
		return (string) $plugin->frontend->get_builder_content_for_display( $post_id, true );
	}

	/**
	 * Body class of the active kit (`elementor-kit-<id>`): global colours and
	 * typography are scoped to it, so a page without it renders unstyled.
	 */
	public function kit_body_class(): string {
		if ( ! $this->is_loaded() || ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( null === $plugin || ! isset( $plugin->kits_manager ) || ! method_exists( $plugin->kits_manager, 'get_active_id' ) ) {
			return '';
		}
		$kit_id = (int) $plugin->kits_manager->get_active_id();
		return $kit_id > 0 ? 'elementor-kit-' . $kit_id : '';
	}

	/**
	 * Admin URL of Elementor > Templates (« Mes modèles »).
	 */
	public function library_admin_url(): string {
		return add_query_arg( 'post_type', self::LIBRARY_POST_TYPE, admin_url( 'edit.php' ) );
	}

	/**
	 * URL opening a post in the Elementor editor.
	 *
	 * @param int $post_id Post id.
	 */
	public function edit_url( int $post_id ): string {
		return add_query_arg(
			array(
				'post'   => $post_id,
				'action' => 'elementor',
			),
			admin_url( 'post.php' )
		);
	}

	/**
	 * Creates an Elementor document through Elementor's documents manager
	 * (handles meta, CSS and version), with a raw-meta fallback.
	 *
	 * @param string               $type      Elementor document type.
	 * @param array<string, mixed> $post_data wp_insert_post() data.
	 * @param array<mixed>         $elements  Elementor elements.
	 * @param array<mixed>         $settings  Page settings.
	 * @return int|WP_Error Post id.
	 */
	private function create_document( string $type, array $post_data, array $elements, array $settings ) {
		$documents = $this->documents_manager();
		if ( null !== $documents ) {
			$document = $documents->create( $type, $post_data );
			if ( $document instanceof WP_Error ) {
				return $document;
			}
			if ( is_object( $document ) && method_exists( $document, 'save' ) && method_exists( $document, 'get_main_id' ) ) {
				$document->save(
					array(
						'elements' => $elements,
						'settings' => $settings,
					)
				);
				return (int) $document->get_main_id();
			}
		}

		return $this->create_document_fallback( $type, $post_data, $elements, $settings );
	}

	/**
	 * Elementor documents manager, when available.
	 */
	private function documents_manager(): ?object {
		if ( ! $this->is_loaded() || ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( null === $plugin || ! isset( $plugin->documents ) || ! is_object( $plugin->documents ) || ! method_exists( $plugin->documents, 'create' ) ) {
			return null;
		}
		return $plugin->documents;
	}

	/**
	 * Writes Elementor's post meta directly, when the documents manager is unavailable.
	 *
	 * @param string               $type      Elementor document type.
	 * @param array<string, mixed> $post_data wp_insert_post() data.
	 * @param array<mixed>         $elements  Elementor elements.
	 * @param array<mixed>         $settings  Page settings.
	 * @return int|WP_Error Post id.
	 */
	private function create_document_fallback( string $type, array $post_data, array $elements, array $settings ) {
		$post_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( $post_id instanceof WP_Error ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', $type );
		update_post_meta( $post_id, '_elementor_version', (string) $this->version() );
		update_post_meta( $post_id, '_elementor_data', wp_slash( (string) wp_json_encode( $elements ) ) );
		if ( array() !== $settings ) {
			update_post_meta( $post_id, '_elementor_page_settings', $settings );
		}
		if ( isset( $settings['template'] ) && is_string( $settings['template'] ) ) {
			update_post_meta( $post_id, '_wp_page_template', $settings['template'] );
		}
		if ( self::LIBRARY_POST_TYPE === ( $post_data['post_type'] ?? '' ) ) {
			wp_set_object_terms( $post_id, $type, self::LIBRARY_TAXONOMY );
		}

		return $post_id;
	}
}
