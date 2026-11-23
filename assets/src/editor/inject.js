/**
 * Adds a « Zuka » button next to Elementor's « Add Template » button
 * in every add-section area of the preview iframe.
 *
 * Elementor selectors and event names come from window.plugtLibrary.editor
 * (built by the PHP Adapter): nothing Elementor-specific is hard-coded here.
 */
const BUTTON_CLASS = 'plugt-add-template-button';

// Doubled class: beats Elementor's own `.elementor-add-section-area-button` rules.
const SEL = `.${ BUTTON_CLASS }.${ BUTTON_CLASS }`;
const STYLE = `
${ SEL } {
	display: inline-flex; align-items: center; justify-content: center;
	background-color: #4a5e3a; color: #f3f2da; border: 0; cursor: pointer;
}
${ SEL }:hover, ${ SEL }:focus-visible { background-color: #36452a; }
${ SEL } img { width: 20px; height: 20px; pointer-events: none; }
`;

/**
 * Index at which a template inserted from `area` must be placed in the document.
 *
 * @param {HTMLElement} area Add-section area clicked.
 * @return {number|undefined} Undefined means « at the end ».
 */
function insertionIndex( area ) {
	const section = area.closest( '.elementor-add-section' );
	if ( ! section || ! section.parentElement ) {
		return undefined;
	}
	const next = section.nextElementSibling;
	if ( ! next || ! next.classList.contains( 'elementor-element' ) ) {
		return undefined;
	}
	const siblings = Array.from( section.parentElement.children ).filter( ( el ) =>
		el.classList.contains( 'elementor-element' )
	);
	const index = siblings.indexOf( next );
	return index >= 0 ? index : undefined;
}

/**
 * @param {Document}                       doc     Preview document.
 * @param {Object}                         contract Selectors from the Adapter.
 * @param {string}                         icon    Icon URL.
 * @param {(at: number|undefined) => void} onOpen  Called with the insertion index.
 */
function decorate( doc, contract, icon, onOpen ) {
	const add = ( root ) => {
		root.querySelectorAll( contract.addSectionArea ).forEach( ( area ) => {
			if ( area.querySelector( `.${ BUTTON_CLASS }` ) ) {
				return;
			}
			const anchor = area.querySelector( contract.addTemplateButton );
			const button = doc.createElement( 'button' );
			button.type = 'button';
			button.className = `elementor-add-section-area-button ${ BUTTON_CLASS }`;
			button.setAttribute( 'aria-label', 'Zuka' );
			button.setAttribute( 'data-tooltip', 'Zuka' );
			const img = doc.createElement( 'img' );
			img.src = icon;
			img.alt = '';
			button.appendChild( img );
			if ( anchor ) {
				anchor.after( button );
			} else {
				area.appendChild( button );
			}
		} );
	};

	if ( ! doc.getElementById( 'plugt-editor-style' ) ) {
		const style = doc.createElement( 'style' );
		style.id = 'plugt-editor-style';
		style.textContent = STYLE;
		doc.head.appendChild( style );
	}

	add( doc );
	new doc.defaultView.MutationObserver( () => add( doc ) ).observe( doc.body, {
		childList: true,
		subtree: true,
	} );

	doc.addEventListener( 'click', ( event ) => {
		const button = event.target.closest && event.target.closest( `.${ BUTTON_CLASS }` );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		onOpen( insertionIndex( button ) );
	} );
}

/**
 * Hooks the preview: decorates it on every (re)load.
 *
 * @param {Object}                         contract Selectors from the Adapter.
 * @param {string}                         icon     Icon URL.
 * @param {(at: number|undefined) => void} onOpen   Called with the insertion index.
 */
export function watchPreview( contract, icon, onOpen ) {
	const run = () => {
		const frame = window.elementor && window.elementor.$preview && window.elementor.$preview[ 0 ];
		const doc = frame && frame.contentDocument;
		if ( doc && doc.body ) {
			decorate( doc, contract, icon, onOpen );
		}
	};
	const bind = () => {
		window.elementor.on( contract.previewLoaded, run );
		run();
	};
	if ( window.elementor && typeof window.elementor.on === 'function' ) {
		bind();
	} else {
		window.jQuery( window ).one( 'elementor/init', bind );
	}
}

/**
 * Inserts prepared content through Elementor's import command.
 *
 * @param {Object}           contract Selectors and commands from the Adapter.
 * @param {Object}           result   POST /import response (mode=editor).
 * @param {string}           title    Template title (history label).
 * @param {number|undefined} at       Insertion index.
 */
export function insertContent( contract, result, title, at ) {
	const options = { withPageSettings: false };
	if ( typeof at === 'number' ) {
		options.at = at;
	}
	window.$e.run( contract.importCommand, {
		model: new window.Backbone.Model( { title } ),
		data: { content: result.content, page_settings: result.page_settings },
		options,
	} );
}
