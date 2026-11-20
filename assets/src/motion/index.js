/**
 * Plug Templates — motion layer entry (built to assets/build/motion.{js,css}).
 *
 * Every modules/<module>.js is bundled automatically: adding a module never
 * touches this file.
 */
import './base.css';
import { boot } from './core';

const context = require.context( './modules', false, /\.js$/ );
context.keys().forEach( context );

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
