/**
 * REST client for plugt/v1 (cookie + wp_rest nonce handled by wp-api-fetch).
 */
import apiFetch from '@wordpress/api-fetch';

const config = () => window.plugtLibrary || {};

export function fetchTemplates() {
	return apiFetch( { path: `${ config().restPath || '/plugt/v1' }/templates` } );
}

/**
 * @param {string} id    Template id.
 * @param {string} mode  page | library | editor.
 * @param {string} title Optional title of the created post.
 */
export function importTemplate( id, mode, title = '' ) {
	return apiFetch( {
		path: `${ config().restPath || '/plugt/v1' }/import`,
		method: 'POST',
		data: { id, mode, title },
	} );
}

export function getConfig() {
	return config();
}
