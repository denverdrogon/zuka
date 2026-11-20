/**
 * Plug Templates — motion layer core.
 *
 * Contract (see src/Library/Schema.php):
 *  - `.plugt-m-<module>` marks a module root, `.plugt-m-<module>__<part>` one of its
 *    parts, `--<modifier>` a variant. Classes come from Elementor's Free
 *    « Classes CSS » setting, so templates stay plain containers + Free widgets.
 *  - A module is `assets/src/motion/modules/<module>.js` calling
 *    `registerModule( '<module>', init )`; it is bundled automatically.
 *  - `init( root, api )` runs once per root (also for roots added later, e.g. in
 *    the editor preview). It may return a cleanup function.
 *  - State is expressed with the `is-active` class; `setActive()` also fires
 *    `plugt:activate` (bubbles, detail: { index, item }) on the newly active item
 *    so other modules (bars, gauge…) can replay their animation.
 *  - Without JS (or without the plugin) the content stays readable: CSS must only
 *    hide/collapse things under `.plugt-motion-ready`, set on <html> at boot.
 */

const PREFIX = 'plugt-m-';
const modules = new Map();
const mounted = new WeakMap();

/**
 * @param {string}   name Module name (Schema::MOTION_MODULES).
 * @param {Function} init init( root, api ) => cleanup|void.
 */
export function registerModule( name, init ) {
	modules.set( name, init );
	if ( document.readyState !== 'loading' ) {
		scan( document );
	}
}

/** Whether the visitor asked for reduced motion. */
export function prefersReducedMotion() {
	return (
		typeof window.matchMedia === 'function' &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
	);
}

/**
 * Direct children (at any depth) of root carrying a part class, excluding nested roots of the same module.
 *
 * @param {Element} root   Module root.
 * @param {string}  module Module name.
 * @param {string}  part   Part name.
 * @return {Element[]} Parts, in document order.
 */
export function parts( root, module, part ) {
	const rootClass = PREFIX + module;
	return Array.from(
		root.querySelectorAll( '.' + rootClass + '__' + part )
	).filter( ( el ) => el.parentElement.closest( '.' + rootClass ) === root );
}

/**
 * Marks items[ index ] as active (class + aria) and fires `plugt:activate` on it.
 *
 * @param {Element[]} items Items.
 * @param {number}    index Index to activate (wrapped).
 * @return {number} The activated index.
 */
export function setActive( items, index ) {
	if ( ! items.length ) {
		return -1;
	}
	const next = ( ( index % items.length ) + items.length ) % items.length;
	items.forEach( ( item, i ) => {
		const active = i === next;
		item.classList.toggle( 'is-active', active );
		if ( item.hasAttribute( 'aria-selected' ) ) {
			item.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		}
		if ( item.hasAttribute( 'aria-expanded' ) ) {
			item.setAttribute( 'aria-expanded', active ? 'true' : 'false' );
		}
	} );
	items[ next ].dispatchEvent(
		new CustomEvent( 'plugt:activate', {
			bubbles: true,
			detail: { index: next, item: items[ next ] },
		} )
	);
	return next;
}

/**
 * Calls cb once when el enters the viewport (immediately without IntersectionObserver).
 *
 * @param {Element}  el        Element.
 * @param {Function} cb        Callback.
 * @param {number}   threshold Visible ratio.
 * @return {Function} Cleanup.
 */
export function onVisible( el, cb, threshold = 0.25 ) {
	if ( typeof IntersectionObserver === 'undefined' ) {
		cb();
		return () => {};
	}
	const io = new IntersectionObserver(
		( entries ) => {
			if ( entries.some( ( e ) => e.isIntersecting ) ) {
				io.disconnect();
				cb();
			}
		},
		{ threshold }
	);
	io.observe( el );
	return () => io.disconnect();
}

/**
 * Makes a non-button element (Elementor container/widget) behave as a button.
 *
 * @param {Element}  el      Element.
 * @param {Function} onPress Handler.
 * @param {Object}   attrs   Extra attributes (role, aria-*).
 * @return {Function} Cleanup.
 */
export function pressable( el, onPress, attrs = {} ) {
	const target = el.querySelector( 'a, button' ) || el;
	if ( target === el ) {
		el.setAttribute( 'role', attrs.role || 'button' );
		el.setAttribute( 'tabindex', '0' );
	}
	Object.keys( attrs ).forEach( ( k ) => {
		if ( k !== 'role' ) {
			el.setAttribute( k, attrs[ k ] );
		}
	} );
	const click = ( e ) => {
		if ( target.tagName === 'A' ) {
			const href = target.getAttribute( 'href' );
			if ( ! href || href === '#' ) {
				e.preventDefault();
			}
		}
		onPress( e );
	};
	const key = ( e ) => {
		if ( target === el && ( e.key === 'Enter' || e.key === ' ' ) ) {
			e.preventDefault();
			onPress( e );
		}
	};
	el.addEventListener( 'click', click );
	el.addEventListener( 'keydown', key );
	return () => {
		el.removeEventListener( 'click', click );
		el.removeEventListener( 'keydown', key );
	};
}

const api = { parts, setActive, onVisible, pressable, prefersReducedMotion };

function mount( root, name ) {
	let byModule = mounted.get( root );
	if ( ! byModule ) {
		byModule = new Map();
		mounted.set( root, byModule );
	}
	if ( byModule.has( name ) ) {
		return;
	}
	try {
		const cleanup = modules.get( name )( root, api );
		byModule.set( name, typeof cleanup === 'function' ? cleanup : null );
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error( '[plug-templates] motion module "' + name + '"', error );
		byModule.set( name, null );
	}
}

/**
 * Mounts every registered module found under scope.
 *
 * @param {ParentNode} scope Document or element.
 */
export function scan( scope ) {
	modules.forEach( ( _init, name ) => {
		const selector = '.' + PREFIX + name;
		if ( scope.nodeType === 1 && scope.matches( selector ) ) {
			mount( scope, name );
		}
		scope.querySelectorAll( selector ).forEach( ( root ) => mount( root, name ) );
	} );
}

/** Boots the layer and watches DOM changes (editor preview re-renders). */
export function boot() {
	document.documentElement.classList.add( 'plugt-motion-ready' );
	if ( prefersReducedMotion() ) {
		document.documentElement.classList.add( 'plugt-reduced-motion' );
	}
	scan( document );

	if ( typeof MutationObserver === 'undefined' ) {
		return;
	}
	let queued = false;
	new MutationObserver( ( records ) => {
		if ( queued || ! records.some( ( r ) => r.addedNodes.length ) ) {
			return;
		}
		queued = true;
		window.requestAnimationFrame( () => {
			queued = false;
			scan( document );
		} );
	} ).observe( document.body, { childList: true, subtree: true } );
}
