import { test, expect } from '@playwright/test';
import { resetImports, wp } from './helpers.mjs';

const MOTION_SCRIPT = 'script[src*="plugt-motion"], script[id^="plugt-motion"]';

/**
 * Importe un template en page publiée avec `wp plugt import` et renvoie son ID.
 *
 * @param {string} id Identifiant du template.
 * @return {string} ID du post.
 */
function importPublished( id ) {
	const out = wp( 'plugt', 'import', id );
	const postId = /ID (\d+)/.exec( out )?.[ 1 ];
	if ( ! postId ) {
		throw new Error( `Import impossible : ${ out }` );
	}
	wp( 'post', 'update', postId, '--post_status=publish' );
	return postId;
}

test.describe( 'Couche motion', () => {
	test.beforeAll( () => resetImports() );
	test.afterAll( () => resetImports() );

	test( "le script motion est chargé et l'onglet s'active", async ( {
		page,
	} ) => {
		const postId = importPublished( 'institutional-tabs' );
		await page.goto( `/?page_id=${ postId }` );

		await expect( page.locator( MOTION_SCRIPT ).first() ).toBeAttached();
		const tabs = page.getByRole( 'tab' );
		await expect( tabs.nth( 1 ) ).toBeVisible();
		await tabs.nth( 1 ).click();
		await expect( tabs.nth( 1 ) ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	test( 'une page sans classe plugt- ne charge pas le script', async ( {
		page,
	} ) => {
		const postId = wp(
			'post',
			'create',
			'--post_type=page',
			'--post_status=publish',
			'--post_title=E2E sans motion',
			'--post_content=Bonjour',
			'--porcelain'
		);
		try {
			await page.goto( `/?page_id=${ postId }` );
			await expect( page.locator( MOTION_SCRIPT ) ).toHaveCount( 0 );
		} finally {
			wp( 'post', 'delete', postId, '--force' );
		}
	} );
} );
