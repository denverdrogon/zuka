<?php
/**
 * Turns a template media file into a usable attachment.
 *
 * @package PlugTemplates
 */

namespace PlugTemplates\Library;

/**
 * Media source contract.
 */
interface MediaSource {

	/**
	 * Imports (or reuses) a media file.
	 *
	 * @param string $template_id Template id, stored as provenance.
	 * @param string $path        Absolute path of the file inside the plugin.
	 * @return array{id: int, url: string}|null Null on failure.
	 */
	public function resolve( string $template_id, string $path ): ?array;
}
