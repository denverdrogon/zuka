import { test, expect } from '@playwright/test';
import { card, openLibrary, template } from './helpers.mjs';

test.describe( 'Aperçu en direct', () => {
	test( "les boutons d'appareil changent la largeur de l'iframe", async ( {
		page,
	} ) => {
		const tpl = template( 'header-simple' );
		await openLibrary( page );
		await card( page, tpl.title )
			.getByRole( 'button', { name: 'Aperçu', exact: true } )
			.click();

		const modal = page.locator( '.plugt-preview-modal' );
		await expect( modal ).toBeVisible();
		const iframe = modal.locator( 'iframe' );
		const width = async () => ( await iframe.boundingBox() ).width;

		await modal.getByRole( 'button', { name: 'Ordinateur' } ).click();
		const desktop = await width();
		await modal.getByRole( 'button', { name: 'Tablette' } ).click();
		await expect.poll( width ).toBeLessThan( desktop );
		const tablet = await width();
		await modal.getByRole( 'button', { name: 'Mobile' } ).click();
		await expect.poll( width ).toBeLessThan( tablet );
	} );
} );
