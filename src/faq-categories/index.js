import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Disabled,
	FormTokenField,
	Notice,
	PanelBody,
	RadioControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

import { useSyncCategoryFilters } from '../display/sync-filters';

import './style.scss';

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

registerBlockType( 'forwp/faq-categories', {
	edit: ( { attributes, setAttributes, clientId } ) => {
		const {
			orientation = 'vertical',
			label = __( 'Categories', '4wp-faq' ),
			showAll = true,
			allLabel = __( 'All categories', '4wp-faq' ),
			showCount = true,
			collapseChildren = true,
			seoUrls = false,
			includeTermIds = [],
			excludeTermIds = [],
		} = attributes;
		const { terms, loading, registryReady } = useRegistryCategories();
		useSyncCategoryFilters( clientId, includeTermIds, excludeTermIds );
		const suggestions = useMemo(
			() => terms.map( ( term ) => term.name ),
			[ terms ]
		);
		const blockProps = useBlockProps( {
			className: 'forwp-faq-categories-editor',
		} );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Navigation', '4wp-faq' ) } initialOpen>
						<RadioControl
							label={ __( 'Orientation', '4wp-faq' ) }
							selected={ orientation }
							options={ [
								{
									label: __( 'Vertical', '4wp-faq' ),
									value: 'vertical',
								},
								{
									label: __( 'Horizontal', '4wp-faq' ),
									value: 'horizontal',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( {
									orientation: value || 'vertical',
								} )
							}
						/>
						<TextControl
							label={ __( 'Label', '4wp-faq' ) }
							value={ label }
							onChange={ ( value ) =>
								setAttributes( { label: value || '' } )
							}
						/>
						<ToggleControl
							label={ __( 'Show “All categories”', '4wp-faq' ) }
							help={ __(
								'Shows the full list using the same category filters as 4WP FAQ List.',
								'4wp-faq'
							) }
							checked={ !! showAll }
							onChange={ ( value ) =>
								setAttributes( { showAll: !! value } )
							}
						/>
						{ showAll ? (
							<TextControl
								label={ __( 'All categories label', '4wp-faq' ) }
								value={ allLabel }
								onChange={ ( value ) =>
									setAttributes( { allLabel: value || '' } )
								}
							/>
						) : null }
						<ToggleControl
							label={ __( 'Show counts', '4wp-faq' ) }
							checked={ !! showCount }
							onChange={ ( value ) =>
								setAttributes( { showCount: !! value } )
							}
						/>
						<ToggleControl
							label={ __( 'Collapse subcategories', '4wp-faq' ) }
							help={ __(
								'Parents start closed. Visitors can expand them; the browser remembers their choice. The branch for the current category stays open.',
								'4wp-faq'
							) }
							checked={ collapseChildren !== false }
							onChange={ ( value ) =>
								setAttributes( { collapseChildren: !! value } )
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
								<p className="forwp-faq-categories-editor__help">
									{ __(
										'Shared with 4WP FAQ List on this page. Leave empty to include all categories.',
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
					<PanelBody title={ __( 'SEO', '4wp-faq' ) } initialOpen={ false }>
						{ ! getDisplayConfig().seoUrlsEnabled ? (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Pretty category URLs are off in 4WP FAQ Settings. Turn them on there first, then enable this toggle. Until then, clicks filter the list in place and the page URL stays unchanged.',
									'4wp-faq'
								) }
							</Notice>
						) : null }
						<ToggleControl
							label={ __( 'SEO-friendly category URLs', '4wp-faq' ) }
							help={ __(
								'Adds /term-slug/ after this page when Settings → SEO: pretty category URLs is also on. That URL uses the category Display title as H1 and the category SEO title/description as document meta. Off here: filter in place, URL unchanged.',
								'4wp-faq'
							) }
							checked={ !! seoUrls }
							onChange={ ( value ) =>
								setAttributes( { seoUrls: !! value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<Disabled>
					<ServerSideRender
						block="forwp/faq-categories"
						attributes={ attributes }
					/>
				</Disabled>
			</div>
		);
	},
	save: () => null,
} );
