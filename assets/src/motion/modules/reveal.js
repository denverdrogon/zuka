/**
 * Module `reveal`: fade/slide in when scrolled into view.
 *
 * - `.plugt-m-reveal` on any container or widget.
 * - `--stagger`: its direct children appear one after another.
 */
import './reveal.css';
import { registerModule } from '../core';

registerModule( 'reveal', ( root, { onVisible } ) => {
	if ( root.classList.contains( 'plugt-m-reveal--stagger' ) ) {
		Array.from( root.children ).forEach( ( child, i ) => {
			child.style.setProperty( '--plugt-i', String( i ) );
		} );
	}
	return onVisible( root, () => root.classList.add( 'is-visible' ), 0.15 );
} );
