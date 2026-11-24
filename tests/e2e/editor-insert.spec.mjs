import { test, expect } from '@playwright/test';
import { resetImports, template, wp } from './helpers.mjs';

test.describe( "Insertion depuis l'éditeur Elementor", () => {
	let postId;

	test.beforeAll( () => {
		postId = wp(
			'post',
			'create',
			'--post_type=page',
			'--post_status=draft',
			'--post_title=E2E insertion',
			'--porcelain'
		);
		wp( 'post', 'meta', 'update', postId, '_elementor_edit_mode', 'builder' );
		wp( 'post', 'meta', 'update', postId, '_elementor_data', '[]' );
	} );
	test.afterAll( () => {
		wp( 'post', 'delete', postId, '--force' );
		resetImports();
	} );

	test( 'le bouton Zuka insère le template et le rendu public le contient', async ( {
		page,
	} ) => {
		const tpl = template( 'section-temoignages' );
		await page.goto( `/wp-admin/post.php?post=${ postId }&action=elementor` );

		const preview = page.frameLocator( '#elementor-preview-iframe' );
		await preview.locator( 'button[aria-label="Zuka"]' ).first().click();

		const modal = page.locator( '.plugt-editor-modal' );
		await expect( modal ).toBeVisible();
		await modal
			.locator( '.dataviews-view-grid__card' )
			.filter( { hasText: tpl.title } )
			.first()
			.getByRole( 'button', { name: 'Insérer' } )
			.click();
		await expect( modal ).toBeHidden( { timeout: 60_000 } );

		// Publier, puis attendre que le bouton redevienne inactif (sauvegarde terminée).
		await page.locator( '#elementor-panel-saver-button-publish' ).click();
		await expect(
			page.locator(
				'#elementor-panel-saver-button-publish.elementor-disabled'
			)
		).toBeVisible( { timeout: 30_000 } );

		const html = await (
			await page.request.get( `/?page_id=${ postId }` )
		 ).text();
		expect( html ).toContain( tpl.title );
	} );
} );
