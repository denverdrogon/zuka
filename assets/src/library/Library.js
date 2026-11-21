/**
 * Template library (DataViews grid), shared by the admin page and the editor modal.
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import { Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { fetchTemplates, importTemplate, getConfig } from './api';
import './library.scss';

const DEFAULT_VIEW = {
	type: 'grid',
	search: '',
	filters: [],
	page: 1,
	perPage: 12,
	sort: { field: 'title', direction: 'asc' },
	titleField: 'title',
	mediaField: 'thumbnail',
	descriptionField: 'description',
	fields: [ 'category', 'type', 'sector' ],
	layout: { badgeFields: [ 'category' ] },
};

const TYPE_LABELS = {
	section: __( 'Section', 'plug-templates' ),
	page: __( 'Page', 'plug-templates' ),
};

/**
 * @param {Object}   props
 * @param {string}   props.context  'admin' | 'editor'.
 * @param {Function} props.onInsert Editor only: receives (item, importResult).
 */
export default function Library( { context = 'admin', onInsert } ) {
	const config = getConfig();
	const [ items, setItems ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ view, setView ] = useState( DEFAULT_VIEW );

	useEffect( () => {
		fetchTemplates()
			.then( setItems )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: error.message || __( 'Chargement impossible.', 'plug-templates' ) } )
			)
			.finally( () => setLoading( false ) );
	}, [] );

	const categories = config.categories || {};

	const fields = useMemo(
		() => [
			{
				id: 'thumbnail',
				label: __( 'Aperçu', 'plug-templates' ),
				render: ( { item } ) =>
					item.thumbnail ? (
						<img className="plugt-thumb" src={ item.thumbnail } alt="" loading="lazy" />
					) : (
						<div className="plugt-thumb plugt-thumb--empty" aria-hidden="true" />
					),
				enableSorting: false,
				enableHiding: false,
			},
			{
				id: 'title',
				label: __( 'Titre', 'plug-templates' ),
				enableGlobalSearch: true,
				enableHiding: false,
				render: ( { item } ) => (
					<span className="plugt-title">
						{ item.code && <span className="plugt-code">{ item.code }</span> }
						{ item.title }
					</span>
				),
			},
			{
				id: 'description',
				label: __( 'Description', 'plug-templates' ),
				enableGlobalSearch: true,
				enableSorting: false,
				render: ( { item } ) => (
					<>
						{ item.description }
						{ ! item.compatible && (
							<span className="plugt-incompatible">{ item.compat_reason }</span>
						) }
					</>
				),
			},
			{
				id: 'category',
				label: __( 'Catégorie', 'plug-templates' ),
				elements: Object.keys( categories ).map( ( value ) => ( {
					value,
					label: categories[ value ],
				} ) ),
				filterBy: { operators: [ 'is' ] },
			},
			{
				id: 'type',
				label: __( 'Type', 'plug-templates' ),
				elements: Object.keys( TYPE_LABELS ).map( ( value ) => ( {
					value,
					label: TYPE_LABELS[ value ],
				} ) ),
				filterBy: { operators: [ 'is' ] },
			},
			{
				id: 'sector',
				label: __( 'Secteur', 'plug-templates' ),
				getValue: ( { item } ) => item.sector || '',
				enableGlobalSearch: true,
			},
		],
		[ categories ]
	);

	const run = async ( item, mode ) => {
		setBusy( item.id );
		setNotice( null );
		try {
			const result = await importTemplate( item.id, mode );
			if ( 'editor' === mode ) {
				onInsert( item, result );
				return;
			}
			setNotice( {
				status: 'success',
				message:
					'page' === mode
						? sprintf( __( 'Page brouillon « %s » créée.', 'plug-templates' ), item.title )
						: sprintf( __( '« %s » ajouté à Mes modèles.', 'plug-templates' ), item.title ),
				editUrl: result.edit_url,
			} );
		} catch ( error ) {
			setNotice( { status: 'error', message: error.message || __( 'Import impossible.', 'plug-templates' ) } );
		} finally {
			setBusy( null );
		}
	};

	const caps = config.caps || {};
	const actions = useMemo( () => {
		const eligible = ( item ) => item.compatible && busy === null;
		if ( 'editor' === context ) {
			return [
				{
					id: 'insert',
					label: __( 'Insérer', 'plug-templates' ),
					isPrimary: true,
					isEligible: eligible,
					callback: ( [ item ] ) => run( item, 'editor' ),
				},
			];
		}
		const list = [];
		if ( caps.importPage ) {
			list.push( {
				id: 'page',
				label: __( 'Créer une page', 'plug-templates' ),
				isPrimary: true,
				isEligible: eligible,
				callback: ( [ item ] ) => run( item, 'page' ),
			} );
		}
		if ( caps.importLibrary ) {
			list.push( {
				id: 'library',
				label: __( 'Ajouter à Mes modèles', 'plug-templates' ),
				isEligible: eligible,
				callback: ( [ item ] ) => run( item, 'library' ),
			} );
		}
		list.push( {
			id: 'preview',
			label: __( 'Aperçu en ligne', 'plug-templates' ),
			isEligible: ( item ) => !! item.preview,
			callback: ( [ item ] ) => window.open( item.preview, '_blank', 'noopener' ),
		} );
		return list;
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ context, busy, caps.importPage, caps.importLibrary ] );

	const { data, paginationInfo } = useMemo(
		() => filterSortAndPaginate( items, view, fields ),
		[ items, view, fields ]
	);

	return (
		<div className={ `plugt-library plugt-library--${ context }` }>
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.message }{ ' ' }
					{ notice.editUrl && (
						<a href={ notice.editUrl }>{ __( 'Modifier avec Elementor', 'plug-templates' ) }</a>
					) }				</Notice>
			) }
			{ busy && (
				<p className="plugt-busy">
					<Spinner /> { __( 'Import en cours (médias compris)…', 'plug-templates' ) }
				</p>
			) }
			<DataViews
				data={ data }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				actions={ actions }
				paginationInfo={ paginationInfo }
				defaultLayouts={ { grid: {}, table: {} } }
				isLoading={ loading }
				getItemId={ ( item ) => item.id }
				searchLabel={ __( 'Rechercher un template', 'plug-templates' ) }
			/>
		</div>
	);
}
