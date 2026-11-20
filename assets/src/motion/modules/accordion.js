/**
 * Module `accordion`: N panels share a row, the active one grows (flex-grow 1 → 4.3).
 *
 * - `.plugt-m-accordion`          root container (holds the header and the track).
 * - `.plugt-m-accordion__track`   container laying the panels out (row, column on mobile).
 * - `.plugt-m-accordion__panel`   one panel; the first one carries `is-active` so it is
 *                                 already open without JS.
 * - `.plugt-m-accordion__bg`      absolutely positioned background (gradient container).
 * - `.plugt-m-accordion__inner`   content layer (copy + HUD).
 * - `.plugt-m-accordion__title`   panel title (heading widget).
 * - `.plugt-m-accordion__desc`    description revealed on the active panel.
 * - `.plugt-m-accordion__hud`     glass card revealed on the active panel (hidden on mobile).
 * - `.plugt-m-accordion__counter` « 01 / 04 » (heading widget).
 * - `.plugt-m-accordion__prev` / `__next`  arrow buttons.
 *
 * Panels fire `plugt:activate` (see core.js) so a nested `gauge` replays its animation.
 */
import './accordion.css';
import { registerModule } from '../core';

registerModule( 'accordion', ( root, { parts, setActive, pressable } ) => {
	const panels = parts( root, 'accordion', 'panel' );
	if ( ! panels.length ) {
		return;
	}
	const [ prev ] = parts( root, 'accordion', 'prev' );
	const [ next ] = parts( root, 'accordion', 'next' );
	const [ counter ] = parts( root, 'accordion', 'counter' );
	const counterText = counter
		? counter.querySelector( '.elementor-heading-title' ) || counter
		: null;
	const pad = ( n ) => String( n ).padStart( 2, '0' );

	let current = Math.max( 0, panels.findIndex( ( p ) => p.classList.contains( 'is-active' ) ) );

	const show = ( index, focus ) => {
		current = setActive( panels, index );
		if ( counterText ) {
			counterText.textContent = pad( current + 1 ) + ' / ' + pad( panels.length );
		}
		if ( focus ) {
			panels[ current ].focus();
		}
	};

	const cleanups = panels.map( ( panel, i ) =>
		pressable( panel, () => i !== current && show( i ), {
			role: 'button',
			'aria-expanded': 'false',
		} )
	);

	[
		[ prev, -1, 'Panneau précédent' ],
		[ next, 1, 'Panneau suivant' ],
	].forEach( ( [ el, delta, label ] ) => {
		if ( ! el ) {
			return;
		}
		cleanups.push( pressable( el, () => show( current + delta ) ) );
		( el.querySelector( 'a, button' ) || el ).setAttribute( 'aria-label', label );
	} );

	const onKey = ( e ) => {
		const i = panels.indexOf( e.target );
		const delta = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[ e.key ];
		if ( i >= 0 && delta ) {
			e.preventDefault();
			show( i + delta, true );
		}
	};
	root.addEventListener( 'keydown', onKey );

	show( current );

	return () => {
		cleanups.forEach( ( c ) => c() );
		root.removeEventListener( 'keydown', onKey );
	};
} );
