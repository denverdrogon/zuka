import { test, expect } from '@playwright/test';
import { manifestTemplates, openLibrary } from './helpers.mjs';

const PER_PAGE = 12; // Taille de page par défaut de la bibliothèque.
const CARDS = '.dataviews-view-grid__card';

test.describe( 'Bibliothèque Zuka', () => {
	test( 'liste une carte par entrée du manifest', async ( { page } ) => {
		await openLibrary( page );
		await expect( page.locator( CARDS ) ).toHaveCount(
			Math.min( manifestTemplates().length, PER_PAGE )
		);
	} );

	test( 'la recherche « anif » filtre les cartes', async ( { page } ) => {
		await openLibrary( page );
		await page
			.getByRole( 'searchbox', { name: 'Rechercher un template' } )
			.fill( 'anif' );

		const matching = manifestTemplates().filter( ( t ) =>
			JSON.stringify( t ).toLowerCase().includes( 'anif' )
		).length;
		expect( matching ).toBeGreaterThan( 0 );
		await expect( page.locator( CARDS ) ).toHaveCount(
			Math.min( matching, PER_PAGE )
		);
	} );

	test( 'le filtre catégorie restreint la liste', async ( { page } ) => {
		await openLibrary( page );
		const total = await page.locator( CARDS ).count();

		await page.getByRole( 'button', { name: /Filtres/ } ).click();
		await page
			.getByRole( 'button', { name: /Ajouter un filtre|Add filter/ } )
			.click();
		await page.getByRole( 'menuitem', { name: 'Catégorie' } ).click();
		// Les libellés des catégories sont traduits côté PHP : on prend la première entrée proposée.
		await page.getByRole( 'menuitemradio' ).first().click();

		await expect
			.poll( () => page.locator( CARDS ).count() )
			.toBeLessThan( total );
		expect( await page.locator( CARDS ).count() ).toBeGreaterThan( 0 );
	} );
} );
