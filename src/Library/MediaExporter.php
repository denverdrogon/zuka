<?php
/**
 * Locates the file of an attachment for export, resizing oversized images.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

/**
 * Attachment locator used by Exporter.
 */
class MediaExporter {

	/** Longest side kept when an image is converted. */
	public const MAX_WIDTH = 1920;

	/**
	 * Temporary files created during the run.
	 *
	 * @var list<string>
	 */
	private array $temp_files = array();

	/**
	 * Absolute path of the file to ship for an attachment: the original, or a
	 * WebP copy of at most 1920 px when the image is wider.
	 *
	 * @param int    $attachment_id Attachment id.
	 * @param string $url           Attachment URL (unused: the id is authoritative).
	 */
	public function locate( int $attachment_id, string $url ): ?string {
		unset( $url );
		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! is_file( $path ) ) {
			return null;
		}

		$mime = (string) get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return $path;
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return $path;
		}
		$size = $editor->get_size();
		if ( $size['width'] <= self::MAX_WIDTH ) {
			return $path;
		}
		if ( is_wp_error( $editor->resize( self::MAX_WIDTH, null ) ) ) {
			return $path;
		}

		$dir = trailingslashit( get_temp_dir() ) . 'plugt-export-' . wp_generate_password( 8, false );
		if ( ! wp_mkdir_p( $dir ) ) {
			return $path;
		}
		$saved = $editor->save( $dir . '/' . pathinfo( $path, PATHINFO_FILENAME ) . '.webp', 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			return $path;
		}

		$this->temp_files[] = $saved['path'];
		$this->temp_files[] = $dir;
		return $saved['path'];
	}

	/**
	 * Deletes the temporary converted files.
	 */
	public function cleanup(): void {
		foreach ( $this->temp_files as $item ) {
			if ( is_file( $item ) ) {
				wp_delete_file( $item );
			} elseif ( is_dir( $item ) ) {
				rmdir( $item ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Own temporary folder.
			}
		}
		$this->temp_files = array();
	}
}
