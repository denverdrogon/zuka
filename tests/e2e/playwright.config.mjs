// Configuration Playwright : suppose `npx wp-env start` (site sur http://localhost:8888).
import { defineConfig, devices } from '@playwright/test';
import { fileURLToPath } from 'node:url';

export default defineConfig( {
	testDir: '.',
	testMatch: '*.spec.mjs',
	globalSetup: './global-setup.mjs',
	// Une seule base WordPress partagée : pas de parallélisme.
	workers: 1,
	fullyParallel: false,
	retries: process.env.CI ? 1 : 0,
	timeout: 90_000,
	expect: { timeout: 15_000 },
	reporter: process.env.CI
		? [ [ 'github' ], [ 'html', { open: 'never' } ] ]
		: 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		storageState: fileURLToPath(
			new URL( './.auth/admin.json', import.meta.url )
		),
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [ { name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } } ],
} );
