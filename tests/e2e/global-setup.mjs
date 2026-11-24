// Connexion admin (storageState) et réglages Elementor préalables.
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { wp } from './helpers.mjs';

export default async function globalSetup( config ) {
	const baseURL =
		config.projects[ 0 ].use.baseURL ||
		process.env.WP_BASE_URL ||
		'http://localhost:8888';
	const statePath = path.join(
		path.dirname( fileURLToPath( import.meta.url ) ),
		'.auth/admin.json'
	);
	mkdirSync( path.dirname( statePath ), { recursive: true } );

	// Conteneurs Flexbox : requis par les templates qui en utilisent.
	wp( 'option', 'update', 'elementor_experiment-container', 'active' );
	// Évite la redirection de bienvenue d'Elementor.
	wp( 'transient', 'delete', 'elementor_activation_redirect' );

	const browser = await chromium.launch();
	const page = await browser.newPage( { baseURL } );
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
	await page.context().storageState( { path: statePath } );
	await browser.close();
}
