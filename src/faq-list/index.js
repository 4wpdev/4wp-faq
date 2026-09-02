import { registerBlockType } from '@wordpress/blocks';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Disabled,
	FormTokenField,
	Notice,
	PanelBody,
	RadioControl,
	Spinner,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

import { useSyncCategoryFilters } from '../display/sync-filters';

import './style.scss';

const TEMPLATE = [ [ 'forwp/faq-card', {} ] ];

const getDisplayConfig = () =>
	typeof window !== 'undefined' && window.forwpFaqDisplay
		? window.forwpFaqDisplay
		: {};

const useRegistryCategories = () => {
	const [ terms, setTerms ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const config = getDisplayConfig();
	const registryReady = !! config.registrySetupComplete;
	const categoriesPath =
		config.categoriesPath || '/forwp-faq/v1/editor/categories';

	useEffect( () => {
		if ( ! registryReady ) {
			setTerms( [] );
			return;
		}

		let cancelled = false;
		setLoading( true );

		apiFetch( { path: categoriesPath } )
			.then( ( response ) => {
				if ( ! cancelled ) {
					setTerms( Array.isArray( response?.terms ) ? response.terms : [] );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setTerms( [] );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ registryReady, categoriesPath ] );

	return { terms, loading, registryReady };
};

const idsToNames = ( ids, terms ) =>
	( ids || [] )
		.map( ( id ) => terms.find( ( term ) => term.id === id )?.name )
		.filter( Boolean );

const namesToIds = ( names, terms ) =>
	( names || [] )
		.map( ( name ) => terms.find( ( term ) => term.name === name )?.id )
		.filter( Boolean );

registerBlockType( 'forwp/faq-list', {
	edit: ( { attributes, setAttributes, clientId } ) => {
		const {
			layout = 'grouped',
			includeTermIds = [],
			excludeTermIds = [],
			cardDisplayMode = 'accordion',
			cardShowSources = false,
			cardSourcesLabel = 'Used in',
			cardShowPostType = false,
		} = attributes;
		const { terms, loading, registryReady } = useRegistryCategories();
		useSyncCategoryFilters( clientId, includeTermIds, excludeTermIds );
		const blockProps = useBlockProps( {
			className: 'forwp-faq-list-editor',
		} );
		const suggestions = useMemo(
			() => terms.map( ( term ) => term.name ),
			[ terms ]
		);

		const innerCard = useSelect(
			( select ) => {
				const block = select( 'core/block-editor' ).getBlock( clientId );
				return block?.innerBlocks?.[ 0 ] || null;
			},
			[ clientId ]
		);

		useEffect( () => {
			if ( ! innerCard || innerCard.name !== 'forwp/faq-card' ) {
				return;
			}

			const nextMode = innerCard.attributes?.displayMode || 'accordion';
			const nextSources = !! innerCard.attributes?.showSources;
			const nextLabel =
				typeof innerCard.attributes?.sourcesLabel === 'string'
					? innerCard.attributes.sourcesLabel
					: 'Used in';
			const nextPostType = !! innerCard.attributes?.showPostType;

			if (
				nextMode !== cardDisplayMode ||
				nextSources !== cardShowSources ||
				nextLabel !== cardSourcesLabel ||
				nextPostType !== cardShowPostType
			) {
				setAttributes( {
					cardDisplayMode: nextMode,
					cardShowSources: nextSources,
					cardSourcesLabel: nextLabel,
					cardShowPostType: nextPostType,
				} );
			}
		}, [
			innerCard?.attributes?.displayMode,
			innerCard?.attributes?.showSources,
			innerCard?.attributes?.sourcesLabel,
			innerCard?.attributes?.showPostType,
			cardDisplayMode,
			cardShowSources,
			cardSourcesLabel,
			cardShowPostType,
			setAttributes,
			innerCard,
		] );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'List', '4wp-faq' ) } initialOpen>
						<RadioControl
							label={ __( 'Layout', '4wp-faq' ) }
							selected={ layout }
							options={ [
								{
									label: __( 'Grouped by category', '4wp-faq' ),
									value: 'grouped',
								},
								{
									label: __( 'Single list', '4wp-faq' ),
									value: 'flat',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { layout: value || 'grouped' } )
							}
						/>
					</PanelBody>
					<PanelBody title={ __( 'Categories', '4wp-faq' ) } initialOpen>
						{ ! registryReady ? (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Complete FAQ registry setup to filter by category.',
									'4wp-faq'
								) }
							</Notice>
						) : loading ? (
							<Spinner />
						) : (
							<>
								<FormTokenField
									label={ __( 'Include categories', '4wp-faq' ) }
									value={ idsToNames( includeTermIds, terms ) }
									suggestions={ suggestions }
									onChange={ ( tokens ) =>
										setAttributes( {
											includeTermIds: namesToIds( tokens, terms ),
										} )
									}
									__experimentalExpandOnFocus
									__nextHasNoMarginBottom
								/>
								<p className="forwp-faq-list-editor__help">
									{ __(
										'Shared with 4WP FAQ Categories on this page. Leave empty to include all categories.',
										'4wp-faq'
									) }
								</p>
								<FormTokenField
									label={ __( 'Exclude categories', '4wp-faq' ) }
									value={ idsToNames( excludeTermIds, terms ) }
									suggestions={ suggestions }
									onChange={ ( tokens ) =>
										setAttributes( {
											excludeTermIds: namesToIds( tokens, terms ),
										} )
									}
									__experimentalExpandOnFocus
									__nextHasNoMarginBottom
								/>
							</>
						) }
					</PanelBody>
				</InspectorControls>
				<div className="forwp-faq-list-editor__template">
					<p className="forwp-faq-list-editor__hint">
						{ __(
							'FAQ Card inside this list controls accordion vs heading and source links.',
							'4wp-faq'
						) }
					</p>
					<InnerBlocks
						allowedBlocks={ [ 'forwp/faq-card' ] }
						template={ TEMPLATE }
						templateLock="all"
					/>
				</div>
				<Disabled>
					<ServerSideRender
						block="forwp/faq-list"
						attributes={ attributes }
					/>
				</Disabled>
			</div>
		);
	},
	save: () => <InnerBlocks.Content />,
} );
