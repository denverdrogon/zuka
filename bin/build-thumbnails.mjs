/**
 * Renders bin/thumbnails/<slug>.html to templates/<slug>/thumbnail.webp (600 x 400).
 *
 * Usage: npm run thumbnails [-- <slug> ...]   (all sources when no slug is given)
 * Needs Google Chrome installed (Playwright "chrome" channel), no browser download.
 */
import { readdirSync, existsSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from 'playwright-core';

const root = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const sources = join( root, 'bin/thumbnails' );
const WIDTH = 600;
const HEIGHT = 400;
const QUALITY = 0.88;

const all = readdirSync( sources )
	.filter( ( file ) => file.endsWith( '.html' ) )
	.map( ( file ) => file.slice( 0, -5 ) );
const slugs = process.argv.length > 2 ? process.argv.slice( 2 ) : all;

const browser = await chromium.launch( { channel: 'chrome' } );
let failed = 0;
try {
	const page = await browser.newPage( { viewport: { width: WIDTH, height: HEIGHT } } );
	for ( const slug of slugs ) {
		const source = join( sources, `${ slug }.html` );
		const target = join( root, 'templates', slug );
		if ( ! existsSync( source ) || ! existsSync( target ) ) {
			console.error( `[FAIL] ${ slug } : source ou dossier template absent.` );
			failed++;
			continue;
		}
		await page.goto( pathToFileURL( source ).href );
		await page.evaluate( () => document.fonts.ready );
		const png = await page.screenshot( { clip: { x: 0, y: 0, width: WIDTH, height: HEIGHT } } );

		// Chrome screenshots only PNG/JPEG: encode WebP through a canvas.
		const webp = await page.evaluate(
			async ( { data, quality } ) => {
				const img = new Image();
				img.src = `data:image/png;base64,${ data }`;
				await img.decode();
				const canvas = document.createElement( 'canvas' );
				canvas.width = img.width;
				canvas.height = img.height;
				canvas.getContext( '2d' ).drawImage( img, 0, 0 );
				return canvas.toDataURL( 'image/webp', quality ).split( ',' )[ 1 ];
			},
			{ data: png.toString( 'base64' ), quality: QUALITY }
		);
		writeFileSync( join( target, 'thumbnail.webp' ), Buffer.from( webp, 'base64' ) );
		console.log( `[OK]   ${ slug }` );
	}
} finally {
	await browser.close();
}
process.exit( failed ? 1 : 0 );
