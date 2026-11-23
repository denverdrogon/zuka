/**
 * Elementor editor integration (built to assets/build/editor.{js,css}).
 */
import { createRoot, useState } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import Library from '../library/Library';
import { watchPreview, insertContent } from './inject';

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
			<Library
				context="editor"
				onInsert={ ( item, result ) => {
					insertContent( contract, result, item.title, at.index );
					setAt( null );
				} }
			/>
		</Modal>
	);
}

const host = document.createElement( 'div' );
host.id = 'plugt-editor-root';
document.body.appendChild( host );
createRoot( host ).render( <EditorModal /> );

watchPreview( contract, config.icon, ( index ) => open( index ) );
