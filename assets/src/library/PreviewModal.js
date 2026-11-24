/**
 * Live preview of a template (iframe on /?plugt_preview=<id>), with device widths.
 */
import { useState } from '@wordpress/element';
import { Button, Modal, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { desktop, tablet, mobile } from '@wordpress/icons';

const DEVICES = [
	{
		id: 'desktop',
		label: __( 'Ordinateur', 'plug-templates' ),
		icon: desktop,
		width: '100%',
	},
	{
		id: 'tablet',
		label: __( 'Tablette', 'plug-templates' ),
		icon: tablet,
		width: '768px',
	},
	{
		id: 'mobile',
		label: __( 'Mobile', 'plug-templates' ),
		icon: mobile,
		width: '375px',
	},
];

/**
 * @param {Object}   props
 * @param {Object}   props.item    Template.
 * @param {string}   props.url     Preview URL.
 * @param {Array}    props.actions [{ id, label, onClick, disabled }] shown in the header.
 * @param {Function} props.onClose Close handler.
 */
export default function PreviewModal( { item, url, actions = [], onClose } ) {
	const [ device, setDevice ] = useState( 'desktop' );
	const [ loaded, setLoaded ] = useState( false );
	const width = DEVICES.find( ( d ) => d.id === device ).width;

	return (
		<Modal
			title={
				item.code ? `${ item.code } · ${ item.title }` : item.title
			}
			className="plugt-preview-modal"
			isFullScreen
			onRequestClose={ onClose }
			headerActions={
				<div className="plugt-preview-modal__tools">
					<div
						className="plugt-preview-modal__devices"
						role="group"
						aria-label={ __(
							"Largeur d'affichage",
							'plug-templates'
						) }
					>
						{ DEVICES.map( ( d ) => (
							<Button
								key={ d.id }
								icon={ d.icon }
								label={ d.label }
								isPressed={ device === d.id }
								onClick={ () => setDevice( d.id ) }
								size="compact"
							/>
						) ) }
					</div>
					{ actions.map( ( action, index ) => (
						<Button
							key={ action.id }
							variant={ 0 === index ? 'primary' : 'secondary' }
							onClick={ action.onClick }
							disabled={ action.disabled }
							__next40pxDefaultSize
						>
							{ action.label }
						</Button>
					) ) }
				</div>
			}
		>
			<div className="plugt-preview-modal__stage">
				{ ! loaded && (
					<p className="plugt-preview-modal__loading">
						<Spinner />{ ' ' }
						{ __(
							'Rendu du template par Elementor…',
							'plug-templates'
						) }
					</p>
				) }
				<iframe
					title={ __( 'Aperçu du template', 'plug-templates' ) }
					src={ url }
					style={ { width } }
					onLoad={ () => setLoaded( true ) }
				/>
			</div>
		</Modal>
	);
}
