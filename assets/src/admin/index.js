/**
 * Admin page « Zuka » (built to assets/build/admin.{js,css}).
 */
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Library from '../library/Library';

const config = window.plugtLibrary || {};

function App() {
	return (
		<>
			<div className="plugt-library-header">
				{ config.logo && <img src={ config.logo } alt="" /> }
				<h1 className={ config.logo ? 'screen-reader-text' : 'wp-heading-inline' }>
					{ __( 'Zuka', 'plug-templates' ) }
				</h1>
			</div>
			<p>
				{ __(
					'Importez un template comme page brouillon ou dans Elementor > Mes modèles. Dans l\'éditeur Elementor, le bouton Zuka insère un template directement dans la page.',
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
