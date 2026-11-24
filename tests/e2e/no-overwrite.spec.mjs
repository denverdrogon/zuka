import { test, expect } from '@playwright/test';
import {
	attachments,
	card,
	openLibrary,
	resetImports,
	template,
	wp,
} from './helpers.mjs';

test.describe( "Jamais d'écrasement", () => {
	test.beforeEach( () => resetImports() );
	test.afterAll( () => resetImports() );

	test( 'deux imports donnent deux posts, le premier reste intact', async ( {
		page,
	} ) => {
		const tpl = template( 'header-simple' );
		const source = `${ tpl.id }@${ tpl.version }`;
		const list = () =>
			wp(
				'post',
				'list',
				'--post_type=page',
				'--post_status=any',
				'--meta_key=_plugt_source',
				`--meta_value=${ source }`,
				'--orderby=ID',
				'--order=ASC',
				'--format=ids'
			)
				.split( /\s+/ )
				.filter( Boolean );
		const importButton = () =>
			card( page, tpl.title ).getByRole( 'button', {
				name: 'Créer une page',
			} );

		await openLibrary( page );
		await importButton().click();
		await expect(
			page.locator( '.components-notice a[href*="action=elementor"]' )
		).toBeVisible();
		const [ first ] = list();
		const modified = wp( 'post', 'get', first, '--field=post_modified' );

		// post_modified a une résolution d'une seconde.
		await page.waitForTimeout( 1500 );
		await importButton().click();
		await expect.poll( () => list().length ).toBe( 2 );

		expect( new Set( list() ).size ).toBe( 2 );
		expect( wp( 'post', 'get', first, '--field=post_modified' ) ).toBe(
			modified
		);
		expect( attachments( /logo[^/]*\.webp$/ ) ).toHaveLength( 1 );
	} );
} );
