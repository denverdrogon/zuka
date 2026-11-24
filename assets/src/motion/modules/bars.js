/**
 * Module `bars`: horizontal bars growing from 0 to their value (scaleX) + counted total.
 *
 * - `.plugt-m-bars`        root container (may also be a `.plugt-m-tabs__panel`).
 * - `.plugt-m-bars__row`   one line (label + bar); rows are staggered.
 * - `.plugt-m-bars__fill`  the bar: a container whose Elementor width (%) is the value.
 * - `.plugt-m-bars__total` optional heading/text; its number is counted from the previous value.
 *
 * Plays when the root enters the viewport, and again on `plugt:activate` of the parent
 * `.plugt-m-tabs__panel` (e.g. year change). Everything stays visible in the editor.
 */
import './bars.css';
import { registerModule } from '../core';

const DURATION = 600;
const STAGGER = 80;
// Last counted total per tabs group, so a year change counts from the previous year.
const lastTotals = new WeakMap();

/** @param {string} text Text such as « 4,79 Md € ». */
function parseNumber( text ) {
	const match = /\d+(?:[.,]\d+)?/.exec( text );
	if ( ! match ) {
		return null;
	}
	const raw = match[ 0 ];
	const sep = /[.,]/.exec( raw );
	return {
		value: parseFloat( raw.replace( ',', '.' ) ),
		decimals: sep ? raw.length - sep.index - 1 : 0,
		sep: sep ? sep[ 0 ] : ',',
		start: match.index,
		end: match.index + raw.length,
	};
}

registerModule(
	'bars',
	( root, { parts, onVisible, prefersReducedMotion } ) => {
		if ( document.body.classList.contains( 'elementor-editor-active' ) ) {
			return;
		}

		const fills = Array.from(
			root.querySelectorAll( '.plugt-m-bars__fill' )
		).filter( ( el ) => el.closest( '.plugt-m-bars' ) === root );
		const total = parts( root, 'bars', 'total' )[ 0 ];
		const totalText = total ? total.textContent : '';
		const target = total ? parseNumber( totalText ) : null;
		const group = root.closest( '.plugt-m-tabs' ) || root;
		const panel = root.closest( '.plugt-m-tabs__panel' );

		fills.forEach( ( fill, i ) => {
			fill.style.transitionDelay = i * STAGGER + 'ms';
		} );

		let frame = 0;
		let played = false;

		const setTotal = ( n ) => {
			const el =
				total.querySelector( '.elementor-heading-title' ) || total;
			el.textContent =
				totalText.slice( 0, target.start ) +
				n.toFixed( target.decimals ).replace( '.', target.sep ) +
				totalText.slice( target.end );
		};

		const count = () => {
			window.cancelAnimationFrame( frame );
			if ( ! target ) {
				return;
			}
			const from = lastTotals.has( group ) ? lastTotals.get( group ) : 0;
			lastTotals.set( group, target.value );
			if ( prefersReducedMotion() || from === target.value ) {
				setTotal( target.value );
				return;
			}
			const t0 = performance.now();
			const tick = ( now ) => {
				const p = Math.min( 1, ( now - t0 ) / DURATION );
				const eased = 1 - Math.pow( 1 - p, 3 );
				setTotal(
					p < 1
						? from + ( target.value - from ) * eased
						: target.value
				);
				if ( p < 1 ) {
					frame = window.requestAnimationFrame( tick );
				}
			};
			frame = window.requestAnimationFrame( tick );
		};

		const play = () => {
			played = true;
			root.classList.add( 'is-reset' );
			root.classList.remove( 'is-in' );
			// Commit the collapsed state, then let the transition run.
			void root.offsetWidth;
			root.classList.remove( 'is-reset' );
			root.classList.add( 'is-in' );
			count();
		};

		const onActivate = ( e ) => {
			if ( e.target === panel ) {
				play();
			}
		};
		if ( panel ) {
			panel.addEventListener( 'plugt:activate', onActivate );
		}
		const stopVisible = onVisible( root, () => {
			if ( ! played ) {
				play();
			}
		} );

		return () => {
			stopVisible();
			window.cancelAnimationFrame( frame );
			if ( panel ) {
				panel.removeEventListener( 'plugt:activate', onActivate );
			}
		};
	}
);
