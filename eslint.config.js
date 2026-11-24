const wordpress = require( '@wordpress/eslint-plugin' );

module.exports = [
	{ ignores: [ 'assets/build/**', 'node_modules/**', 'vendor/**' ] },
	...wordpress.configs.recommended,
	{
		languageOptions: {
			globals: { IntersectionObserver: 'readonly', MutationObserver: 'readonly' },
		},
	},
];
