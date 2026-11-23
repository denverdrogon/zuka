/**
 * Builds templates/<slug>/thumbnail.webp (600 x 400) from the REAL render of each
 * template: the same page as the live preview (Elementor, site kit and theme CSS).
 *
 * Usage:
 *   npm run thumbnails -- --wp=C:/laragon/www/anif-neuf --url=http://anif-neuf.test:8080 [<slug> ...]
 *
 * 1. bin/export-previews.php renders every template through the site's WordPress
 *    into <wp>/wp-content/uploads/plugt-thumbs/ (same origin: fonts load).
 * 2. Chrome (Playwright "chrome" channel, no browser download) opens each page at
 *    1200 px wide, captures the top of the template and scales it to 600 x 400.
 * 3. The temporary folder is deleted.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const root = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const WIDTH = 600;
const HEIGHT = 400;
const RENDER_WIDTH = 960; // Desktop layout, scaled down to 600 (text stays legible).
const QUALITY = 0.88;

const args = process.argv.slice( 2 );
const option = ( name ) => ( args.find( ( a ) => a.startsWith( `--${ name }=` ) ) || '' ).split( '=' ).slice( 1 ).join( '=' );
const wp = option( 'wp' );
const url = option( 'url' ).replace( /\/$/, '' );
const only = args.filter( ( a ) => ! a.startsWith( '--' ) );
if ( ! wp || ! url ) {
	console.error( 'Usage : npm run thumbnails -- --wp=<racine WordPress> --url=<URL du site> [<slug> ...]' );
	process.exit( 1 );
}

const out = join( wp, 'wp-content/uploads/plugt-thumbs' );
let failed = 0;
try {
	const exported = execFileSync( 'php', [ join( root, 'bin/export-previews.php' ), wp, out, ...only ], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'inherit' ] } );
	const slugs = [ ...exported.matchAll( /^\[OK\]\s+(\S+)/gm ) ].map( ( m ) => m[ 1 ] );

	const browser = await chromium.launch( { channel: 'chrome' } );
	try {
		const page = await browser.newPage( { viewport: { width: RENDER_WIDTH, height: RENDER_WIDTH * HEIGHT / WIDTH } } );
		for ( const slug of slugs ) {
			const target = join( root, 'templates', slug );
			if ( ! existsSync( target ) ) {
				console.error( `[FAIL] ${ slug } : dossier template absent.` );
				failed++;
				continue;
			}
			await page.goto( `${ url }/wp-content/uploads/plugt-thumbs/${ slug }.html`, { waitUntil: 'networkidle' } );
			// Entrance animations hidden until scrolled into view: show them.
			await page.addStyleTag( { content: '.elementor-invisible{visibility:visible!important;opacity:1!important}' } );
			await page.evaluate( async () => {
				document.querySelectorAll( 'img[loading="lazy"]' ).forEach( ( img ) => ( img.loading = 'eager' ) );
				await document.fonts.ready;
				await Promise.all( [ ...document.images ].map( ( img ) => ( img.complete ? null : img.decode().catch( () => null ) ) ) );
			} );
			await page.waitForTimeout( 2000 ); // Let bars, counters and transitions finish.
			const height = await page.evaluate( () => Math.ceil( document.body.getBoundingClientRect().height ) );
			const clipHeight = Math.min( Math.max( height, 1 ), RENDER_WIDTH * HEIGHT / WIDTH );
			const png = await page.screenshot( { clip: { x: 0, y: 0, width: RENDER_WIDTH, height: clipHeight } } );

			// Scale to 600 px wide; a short section is centred on its own background colour.
			// Chrome screenshots only PNG/JPEG: encode WebP through a canvas.
			const webp = await page.evaluate(
				async ( { data, quality, w, h } ) => {
					const img = new Image();
					img.src = `data:image/png;base64,${ data }`;
					await img.decode();
					const canvas = document.createElement( 'canvas' );
					canvas.width = w;
					canvas.height = h;
					const ctx = canvas.getContext( '2d' );
					const probe = document.createElement( 'canvas' ).getContext( '2d' );
					probe.drawImage( img, 0, 0 );
					const [ r, g, b ] = probe.getImageData( 1, 1, 1, 1 ).data;
					ctx.fillStyle = `rgb(${ r },${ g },${ b })`;
					ctx.fillRect( 0, 0, w, h );
					ctx.imageSmoothingQuality = 'high';
					const dh = ( img.height * w ) / img.width;
					ctx.drawImage( img, 0, Math.max( 0, ( h - dh ) / 2 ), w, dh );
					return canvas.toDataURL( 'image/webp', quality ).split( ',' )[ 1 ];
				},
				{ data: png.toString( 'base64' ), quality: QUALITY, w: WIDTH, h: HEIGHT }
			);
			writeFileSync( join( target, 'thumbnail.webp' ), Buffer.from( webp, 'base64' ) );
			console.log( `[OK]   ${ slug }` );
		}
	} finally {
		await browser.close();
	}
} catch ( error ) {
	console.error( error.message );
	failed++;
} finally {
	rmSync( out, { recursive: true, force: true } );
}
process.exit( failed ? 1 : 0 );
