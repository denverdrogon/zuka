/**
 * Module `tabs`: N triggers switch N panels (paired by order).
 *
 * - `.plugt-m-tabs`         root container.
 * - `.plugt-m-tabs__tab`    trigger (button widget, heading or container).
 * - `.plugt-m-tabs__panel`  panel container; the first one is shown without JS.
 * - `--autoplay`            on the root: cycles every 6 s until the user interacts.
 *
 * Fires `plugt:activate` on the active tab and panel (see core.js) so nested
 * modules (bars, gauge…) can replay their animation.
 */
import './tabs.css';
import { registerModule } from '../core';

registerModule( 'tabs', ( root, { parts, setActive, pressable } ) => {
	const tabs = parts( root, 'tabs', 'tab' );
	const panels = parts( root, 'tabs', 'panel' );
	if ( ! tabs.length || ! panels.length ) {
		return;
	}

	const uid = 'plugt-tabs-' + Math.random().toString( 36 ).slice( 2, 8 );
	let current = Math.max(
		0,
		tabs.findIndex( ( t ) => t.classList.contains( 'is-active' ) )
	);

	panels.forEach( ( panel, i ) => {
		panel.id = panel.id || uid + '-p' + i;
		panel.setAttribute( 'role', 'tabpanel' );
	} );

	const show = ( index, focus ) => {
		current = setActive( tabs, index );
		setActive( panels, Math.min( current, panels.length - 1 ) );
		if ( focus ) {
			tabs[ current ].focus();
		}
	};

	const cleanups = tabs.map( ( tab, i ) =>
		pressable( tab, () => show( i ), {
			role: 'tab',
			'aria-selected': 'false',
			'aria-controls': panels[ Math.min( i, panels.length - 1 ) ].id,
		} )
	);

	const onKey = ( e ) => {
		const i = tabs.indexOf( e.target.closest( '.plugt-m-tabs__tab' ) );
		if ( i < 0 ) {
			return;
		}
		const delta = {
			ArrowRight: 1,
			ArrowDown: 1,
			ArrowLeft: -1,
			ArrowUp: -1,
		}[ e.key ];
		if ( delta ) {
			e.preventDefault();
			show( i + delta, true );
		}
	};
	root.addEventListener( 'keydown', onKey );

	let timer = null;
	const stop = () => {
		if ( timer ) {
			root.classList.add( 'is-paused' );
		}
		window.clearInterval( timer );
		timer = null;
	};
	if (
		root.classList.contains( 'plugt-m-tabs--autoplay' ) &&
		! document.documentElement.classList.contains( 'plugt-reduced-motion' )
	) {
		timer = window.setInterval( () => show( current + 1 ), 6000 );
		root.addEventListener( 'pointerdown', stop, { once: true } );
		root.addEventListener( 'focusin', stop, { once: true } );
	}

	show( current );

	return () => {
		stop();
		cleanups.forEach( ( c ) => c() );
		root.removeEventListener( 'keydown', onKey );
	};
} );
