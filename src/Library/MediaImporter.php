<?php
/**
 * Copies template media into the WordPress media library, without duplicates.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

/**
 * Media library importer.
 */
final class MediaImporter implements MediaSource {

	public const HASH_META   = '_plugt_media_hash';
	public const SOURCE_META = '_plugt_source';

	/**
	 * {@inheritDoc}
	 *
	 * @param string $template_id Template id.
	 * @param string $path        Absolute path.
	 * @return array{id: int, url: string}|null
	 */
	public function resolve( string $template_id, string $path ): ?array {
		$hash = is_readable( $path ) ? sha1_file( $path ) : false;
		if ( false === $hash ) {
			return null;
		}

		$existing = $this->find_by_hash( $hash );
		if ( null !== $existing ) {
			return $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// media_handle_sideload() moves the file: work on a temporary copy.
		$tmp = wp_tempnam( basename( $path ) );
		if ( '' === $tmp || ! copy( $path, $tmp ) ) {
			return null;
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( basename( $path ) ),
				'tmp_name' => $tmp,
			),
			0
		);
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return null;
		}

		update_post_meta( $attachment_id, self::HASH_META, $hash );
		update_post_meta( $attachment_id, self::SOURCE_META, $template_id );

		$url = wp_get_attachment_url( $attachment_id );
		return false === $url ? null : array(
			'id'  => $attachment_id,
			'url' => $url,
		);
	}

	/**
	 * Attachment previously imported from the same file content.
	 *
	 * @param string $hash SHA-1 of the file.
	 * @return array{id: int, url: string}|null
	 */
	private function find_by_hash( string $hash ): ?array {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_key'         => self::HASH_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Runs only during an import, on a narrow set.
				'meta_value'       => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Same.
			)
		);
		if ( array() === $ids ) {
			return null;
		}

		$id  = (int) $ids[0];
		$url = wp_get_attachment_url( $id );
		return false === $url ? null : array(
			'id'  => $id,
			'url' => $url,
		);
	}
}
