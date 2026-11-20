/**
 * Module `gauge`: SVG arc drawn with stroke-dashoffset, value counted from 0 to N.
 *
 * - `.plugt-m-gauge` sits on an HTML widget holding a static `<svg data-value="57">`
 *   with `.plugt-m-gauge__arc` and `.plugt-m-gauge__value` (the displayed number).
 * - Inside a panel (accordion / tabs) the animation replays on every `plugt:activate`
 *   of that panel; elsewhere it plays once when scrolled into view.
 */
import './gauge.css';
import { registerModule } from '../core';

const DURATION = 800;

registerModule( 'gauge', ( root, { onVisible, prefersReducedMotion } ) => {
	const svg = root.querySelector( 'svg' );
	const value = root.querySelector( '.plugt-m-gauge__value' );
	if ( ! svg ) {
		return;
	}
	const target = Math.max( 0, Number( svg.getAttribute( 'data-value' ) ) || 0 );
	svg.style.setProperty( '--plugt-gauge', String( Math.min( 100, target ) ) );

	let frame = 0;
	const count = () => {
		window.cancelAnimationFrame( frame );
		if ( ! value ) {
			return;
		}
		if ( prefersReducedMotion() ) {
			value.textContent = String( target );
			return;
		}
		value.textContent = '0';
		const start = performance.now();
		const tick = ( now ) => {
			const p = Math.min( ( now - start ) / DURATION, 1 );
			value.textContent = String( Math.round( ( 1 - Math.pow( 1 - p, 3 ) ) * target ) );
			if ( p < 1 ) {
				frame = window.requestAnimationFrame( tick );
			}
		};
		frame = window.requestAnimationFrame( tick );
	};

	const set = ( on ) => {
		// Two frames so the arc starts from its collapsed state.
		window.requestAnimationFrame( () =>
			window.requestAnimationFrame( () => {
				root.classList.toggle( 'is-on', on );
				if ( on ) {
					count();
				}
			} )
		);
	};

	const host = root.closest( '.plugt-m-accordion__panel, .plugt-m-tabs__panel' );
	if ( ! host || ! host.parentElement ) {
		return onVisible( root, () => set( true ) );
	}

	const group = host.parentElement;
	const onActivate = ( e ) => {
		const item = e.detail && e.detail.item;
		if ( item && item.parentElement === group ) {
			set( item === host );
		}
	};
	group.addEventListener( 'plugt:activate', onActivate );
	if ( host.classList.contains( 'is-active' ) ) {
		set( true );
	}

	return () => {
		window.cancelAnimationFrame( frame );
		group.removeEventListener( 'plugt:activate', onActivate );
	};
} );
