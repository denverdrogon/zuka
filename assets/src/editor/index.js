/**
 * Elementor editor integration (built to assets/build/editor.{js,css}).
 */
import { createRoot, lazy, Suspense, useState } from '@wordpress/element';
import { Modal, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { watchPreview, insertContent } from './inject';
import './editor.scss';

// DataViews (~900 Ko) is only downloaded when the modal is first opened.
const Library = lazy( () =>
	import( /* webpackChunkName: "library" */ '../library/Library' )
);

const config = window.plugtLibrary || {};
const contract = config.editor || {};

let open = () => {};

function EditorModal() {
	const [ at, setAt ] = useState( null );
	open = ( index ) => setAt( { index } );

	if ( null === at ) {
		return null;
	}
	return (
		<Modal
			title={ __( 'Zuka', 'plug-templates' ) }
			className="plugt-editor-modal"
			onRequestClose={ () => setAt( null ) }
		>
			<Suspense fallback={ <Spinner /> }>
				<Library
					context="editor"
					onInsert={ ( item, result ) => {
						insertContent( contract, result, item.title, at.index );
						setAt( null );
					} }
				/>
			</Suspense>
		</Modal>
	);
}

const host = document.createElement( 'div' );
host.id = 'plugt-editor-root';
document.body.appendChild( host );
createRoot( host ).render( <EditorModal /> );

watchPreview( contract, config.icon, ( index ) => open( index ) );
