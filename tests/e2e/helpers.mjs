// Utilitaires partagés : WP-CLI via wp-env, manifest, accès à la page Zuka.
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

export const ROOT = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'../..'
);
export const LIBRARY_URL = '/wp-admin/admin.php?page=plug-templates';

/**
 * Exécute une commande WP-CLI dans le conteneur wp-env et renvoie sa sortie.
 *
 * @param {...string} args Arguments de `wp`.
 * @return {string} Sortie standard.
 */
export function wp( ...args ) {
	return execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		cwd: ROOT,
		encoding: 'utf8',
		shell: process.platform === 'win32',
	} ).trim();
}

/** @return {{id: string, title: string, category: string, version: string}[]} Entrées du manifest. */
export function manifestTemplates() {
	return JSON.parse(
		readFileSync( path.join( ROOT, 'templates/manifest.json' ), 'utf8' )
	).templates;
}

/** @param {string} id Identifiant du template. */
export function template( id ) {
	const entry = manifestTemplates().find( ( t ) => t.id === id );
	if ( ! entry ) {
		throw new Error( `Template inconnu : ${ id }` );
	}
	return entry;
}

/** Supprime les posts et médias créés par un import Zuka (état propre entre deux specs). */
export function resetImports() {
	const ids = wp(
		'post',
		'list',
		'--post_type=any',
		'--post_status=any',
		'--meta_key=_plugt_source',
		'--format=ids'
	);
	if ( ids ) {
		wp( 'post', 'delete', ...ids.split( /\s+/ ), '--force' );
	}
}

/**
 * Médias de la médiathèque dont l'URL correspond au motif.
 *
 * @param {RegExp} pattern Motif appliqué à l'URL du fichier.
 * @return {{ID: number, guid: string}[]} Pièces jointes.
 */
export function attachments( pattern ) {
	const rows = JSON.parse(
		wp(
			'post',
			'list',
			'--post_type=attachment',
			'--post_status=inherit',
			'--fields=ID,guid',
			'--format=json'
		) || '[]'
	);
	return rows.filter( ( row ) => pattern.test( row.guid ) );
}

/**
 * Carte d'un template dans la grille de la bibliothèque.
 *
 * @param {import('@playwright/test').Page|import('@playwright/test').Locator} scope Page ou conteneur.
 * @param {string}                                                             title Titre du template.
 */
export function card( scope, title ) {
	return scope
		.locator( '.dataviews-view-grid__card' )
		.filter( { hasText: title } )
		.first();
}

/**
 * Ouvre la page d'admin Zuka et attend la grille.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
export async function openLibrary( page ) {
	await page.goto( LIBRARY_URL );
	await page.locator( '.dataviews-view-grid__card' ).first().waitFor();
}
