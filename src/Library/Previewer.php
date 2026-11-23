<?php
/**
 * Live preview of a template: rendered by Elementor with the site's styles
 * (kit colours and fonts, theme CSS), without the theme header and footer.
 *
 * Each template is rendered from a private, hidden library post, created on
 * first preview and rebuilt when the template version changes. The preview
 * page (`/?plugt_preview=<id>`) is reserved to users who can edit posts.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

use PlugTemplates\Elementor\Adapter;
use WP_Error;

/**
 * Template previewer.
 */
class Previewer {

	public const QUERY_VAR    = 'plugt_preview';
	public const META         = '_plugt_preview';
	public const VERSION_META = '_plugt_preview_version';
	public const CAPABILITY   = 'edit_posts';

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
	 * Elementor adapter.
	 *
	 * @var Adapter
	 */
	private Adapter $elementor;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 * @param Importer   $importer   Importer.
	 * @param Adapter    $elementor  Elementor adapter.
	 */
	public function __construct( Repository $repository, Importer $importer, Adapter $elementor ) {
		$this->repository = $repository;
		$this->importer   = $importer;
		$this->elementor  = $elementor;
	}

	/**
	 * Hooks the preview page and hides preview posts from template lists.
	 */
	public function register(): void {
		$this->elementor->hide_library_posts( self::META );
		// Before canonical and language redirects.
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 0 );
	}

	/**
	 * Preview URL of a template, with `__ID__` when no id is given (JS template).
	 *
	 * @param string $id Template id.
	 */
	public static function url( string $id = '__ID__' ): string {
		return add_query_arg( self::QUERY_VAR, $id, home_url( '/' ) );
	}

	/**
	 * Version stamp of a manifest entry: a rebuilt template gets a new preview.
	 *
	 * @param array<string, mixed> $entry Manifest entry.
	 */
	public static function stamp( array $entry ): string {
		return (string) ( $entry['version'] ?? '' ) . '#' . (string) ( $entry['hash'] ?? '' );
	}

	/**
	 * Id of the preview post of a template, created or rebuilt when needed.
	 *
	 * @param string $id Template id.
	 * @return int|WP_Error
	 */
	public function document( string $id ) {
		$entry = $this->repository->get( $id );
		if ( null === $entry ) {
			return new WP_Error( 'plugt_not_found', __( 'Template introuvable.', 'plug-templates' ), array( 'status' => 404 ) );
		}
		$stamp = self::stamp( $entry );

		// Keep one up-to-date preview per template; drop stale ones and duplicates.
		$current = null;
		foreach ( $this->elementor->find_library_posts( self::META, $id ) as $post_id ) {
			if ( null === $current && get_post_meta( $post_id, self::VERSION_META, true ) === $stamp ) {
				$current = $post_id;
				continue;
			}
			wp_delete_post( $post_id, true );
		}
		if ( null !== $current ) {
			return $current;
		}

		$prepared = $this->importer->prepare( $id );
		if ( $prepared instanceof WP_Error ) {
			return $prepared;
		}
		$post_id = $this->elementor->create_hidden_template(
			sprintf( 'Zuka — %s', (string) $entry['title'] ),
			(string) $entry['type'],
			$prepared['content'],
			$prepared['page_settings']
		);
		if ( $post_id instanceof WP_Error ) {
			return $post_id;
		}
		update_post_meta( $post_id, self::META, $id );
		update_post_meta( $post_id, self::VERSION_META, $stamp );
		return $post_id;
	}

	/**
	 * Renders the preview page when the query var is present.
	 */
	public function maybe_render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page, capability checked below.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same.
		$id = sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) );

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );

		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Vous n\'avez pas l\'autorisation de prévisualiser les templates.', 'plug-templates' ), '', array( 'response' => 403 ) );
		}

		$post_id = $this->document( $id );
		if ( $post_id instanceof WP_Error ) {
			$status = $post_id->get_error_data();
			wp_die( esc_html( $post_id->get_error_message() ), '', array( 'response' => is_array( $status ) && isset( $status['status'] ) ? (int) $status['status'] : 500 ) );
		}

		// Render first so Elementor enqueues the document CSS before wp_head().
		$content = $this->elementor->render_document( $post_id );
		show_admin_bar( false );
		$this->output( $content );
		exit;
	}

	/**
	 * Prints the bare preview document.
	 *
	 * @param string $content Elementor HTML.
	 */
	private function output( string $content ): void {
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
		<?php wp_head(); ?>
<style>html{margin-top:0!important}body.plugt-preview{margin:0}</style>
</head>
<body <?php body_class( 'plugt-preview elementor-page' ); ?>>
		<?php
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor output, escaped by Elementor.
		wp_footer();
		?>
</body>
</html>
		<?php
	}
}
