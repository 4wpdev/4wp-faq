import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Disabled,
	FontSizePicker,
	FormTokenField,
	Notice,
	PanelBody,
	RadioControl,
	RangeControl,
	Spinner,
	TextControl,
	ToggleControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

import { useSyncCategoryFilters } from '../display/sync-filters';
import { useTermTokenField } from '../display/term-tokens';
import {
	normalizeFontSize,
	useEditorTypography,
	useFaqDropdownMenuProps,
} from '../display/typography-panels';

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
	const postId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId?.(),
		[]
	);
	const query = typeof postId === 'number' && postId > 0 ? `?post_id=${ postId }` : '';

	useEffect( () => {
		if ( ! registryReady ) {
			setTerms( [] );
			return;
		}

		let cancelled = false;
		setLoading( true );

		apiFetch( { path: `${ categoriesPath }${ query }` } )
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
	}, [ registryReady, categoriesPath, query ] );

	return { terms, loading, registryReady };
};

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
			allItemFontSize = '',
			itemFontSize = '',
			itemGap = '',
		} = attributes;
		const { terms, loading, registryReady } = useRegistryCategories();
		const { fontSizes } = useEditorTypography();
		const dropdownMenuProps = useFaqDropdownMenuProps();
		useSyncCategoryFilters( clientId, includeTermIds, excludeTermIds );
		const includeTokens = useTermTokenField(
			includeTermIds,
			terms,
			setAttributes,
			'includeTermIds'
		);
		const excludeTokens = useTermTokenField(
			excludeTermIds,
			terms,
			setAttributes,
			'excludeTermIds'
		);
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
									value={ includeTokens.value }
									suggestions={ suggestions }
									onChange={ includeTokens.onChange }
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
									value={ excludeTokens.value }
									suggestions={ suggestions }
									onChange={ excludeTokens.onChange }
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
				<InspectorControls group="styles">
					<ToolsPanel
						label={ __( 'Sizes', '4wp-faq' ) }
						panelId="forwp-faq-categories-sizes"
						__experimentalFirstVisibleItemClass="first"
						dropdownMenuProps={ dropdownMenuProps }
						resetAll={ () =>
							setAttributes( {
								allItemFontSize: '',
								itemFontSize: '',
								itemGap: '',
							} )
						}
					>
						<ToolsPanelItem
							label={ __( 'All questions', '4wp-faq' ) }
							hasValue={ () => !! allItemFontSize }
							onDeselect={ () => setAttributes( { allItemFontSize: '' } ) }
							isShownByDefault
							panelId="forwp-faq-categories-sizes"
						>
							<FontSizePicker
								fontSizes={ fontSizes }
								value={ allItemFontSize || undefined }
								onChange={ ( next ) =>
									setAttributes( {
										allItemFontSize: normalizeFontSize( next ),
									} )
								}
								withReset={ false }
								withSlider
							/>
						</ToolsPanelItem>
						<ToolsPanelItem
							label={ __( 'Headings', '4wp-faq' ) }
							hasValue={ () => !! itemFontSize }
							onDeselect={ () => setAttributes( { itemFontSize: '' } ) }
							isShownByDefault
							panelId="forwp-faq-categories-sizes"
						>
							<FontSizePicker
								fontSizes={ fontSizes }
								value={ itemFontSize || undefined }
								onChange={ ( next ) =>
									setAttributes( {
										itemFontSize: normalizeFontSize( next ),
									} )
								}
								withReset={ false }
								withSlider
							/>
						</ToolsPanelItem>
						<ToolsPanelItem
							label={ __( 'Space between items', '4wp-faq' ) }
							hasValue={ () => !! itemGap }
							onDeselect={ () => setAttributes( { itemGap: '' } ) }
							isShownByDefault
							panelId="forwp-faq-categories-sizes"
						>
							<RangeControl
								label={ __( 'Space between items', '4wp-faq' ) }
								value={ itemGap ? parseFloat( itemGap ) : undefined }
								onChange={ ( next ) =>
									setAttributes( {
										itemGap:
											next === undefined || next === null
												? ''
												: `${ next }px`,
									} )
								}
								min={ 0 }
								max={ 48 }
								step={ 1 }
								allowReset
								resetFallbackValue={ undefined }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</ToolsPanelItem>
					</ToolsPanel>
				</InspectorControls>
				<Disabled>
					<ServerSideRender
						block="forwp/faq-categories"
						attributes={ attributes }
						httpMethod="POST"
					/>
				</Disabled>
			</div>
		);
	},
	save: () => null,
} );
