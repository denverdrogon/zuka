import { test, expect } from '@playwright/test';
import {
	attachments,
	card,
	openLibrary,
	resetImports,
	template,
	wp,
} from './helpers.mjs';

test.describe( 'Import « Créer une page »', () => {
	test.beforeEach( () => resetImports() );
	test.afterAll( () => resetImports() );

	test( 'crée un brouillon Elementor avec sa source et son média', async ( {
		page,
	} ) => {
		const tpl = template( 'header-simple' );
		await openLibrary( page );
		await card( page, tpl.title )
			.getByRole( 'button', { name: 'Créer une page' } )
			.click();

		const editLink = page.locator(
			'.components-notice a[href*="action=elementor"]'
		);
		await expect( editLink ).toBeVisible();

		const ids = wp(
			'post',
			'list',
			'--post_type=page',
			'--post_status=draft',
			'--meta_key=_plugt_source',
			`--meta_value=${ tpl.id }@${ tpl.version }`,
			'--format=ids'
		)
			.split( /\s+/ )
			.filter( Boolean );
		expect( ids ).toHaveLength( 1 );
		expect(
			wp( 'post', 'meta', 'get', ids[ 0 ], '_elementor_edit_mode' )
		).toBe( 'builder' );

		expect( attachments( /logo[^/]*\.webp$/ ).length ).toBeGreaterThan( 0 );

		await editLink.click();
		await expect( page ).toHaveURL( /action=elementor/ );
		await expect( page.locator( '#elementor-preview-iframe' ) ).toBeAttached(
			{ timeout: 60_000 }
		);
	} );
} );
