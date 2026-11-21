/**
 * Admin page « Plug Templates » (built to assets/build/admin.{js,css}).
 */
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Library from '../library/Library';

function App() {
	return (
		<>
			<div className="plugt-library-header">
				<h1 className="wp-heading-inline">{ __( 'Plug Templates', 'plug-templates' ) }</h1>
			</div>
			<p>
				{ __(
					'Importez un template comme page brouillon ou dans Elementor > Mes modèles. Dans l\'éditeur Elementor, le bouton Plug Templates insère un template directement dans la page.',
					'plug-templates'
				) }
			</p>
			<Library context="admin" />
		</>
	);
}

const root = document.getElementById( 'plugt-library-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
