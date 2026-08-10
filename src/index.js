import { registerBlockType, createBlock } from '@wordpress/blocks';
import { InnerBlocks, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { Fragment, useMemo, useEffect, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import apiFetch from '@wordpress/api-fetch';
import {
	PanelBody,
	TextareaControl,
	Notice,
	SelectControl,
	TextControl,
	Spinner,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { serialize } from '@wordpress/blocks';

const extractTextFromBlocks = ( blocks ) => {
	if ( ! blocks || ! blocks.length ) {
		return '';
	}
	const html = serialize( blocks );
	const doc = new DOMParser().parseFromString( html, 'text/html' );
	return ( doc.body.textContent || '' ).trim();
};

const extractQuestionAnswer = ( block ) => {
	const attrs = block.attributes || {};
	let question =
		( attrs.title || attrs.summary || attrs.question || attrs.heading || '' ).trim();
	let answer = '';
	const innerBlocks = block.innerBlocks || [];

	if ( ! question && innerBlocks.length ) {
		question = extractTextFromBlocks( [ innerBlocks[ 0 ] ] );
		if ( innerBlocks.length > 1 ) {
			answer = extractTextFromBlocks( innerBlocks.slice( 1 ) );
		}
	}

	if ( ! answer && innerBlocks.length ) {
		answer = extractTextFromBlocks( innerBlocks );
	}

	return {
		question: question.trim(),
		answer: answer.trim(),
	};
};

const isAccordionItemBlock = ( name ) =>
	typeof name === 'string' &&
	( name === 'core/accordion-item' || name.includes( 'accordion-item' ) );

const isAccordionBlock = ( name ) =>
	typeof name === 'string' && name.includes( 'accordion' ) && ! name.includes( 'accordion-item' );

const isDetailsBlock = ( name ) =>
	typeof name === 'string' &&
	( name === 'core/details' || name.endsWith( '/details' ) );

const collectFaqItems = ( blocks ) => {
	const items = [];
	const walk = ( list ) => {
		list.forEach( ( block ) => {
			const name = block.name;
			if ( isAccordionItemBlock( name ) || isDetailsBlock( name ) ) {
				const { question, answer } = extractQuestionAnswer( block );
				if ( question && answer ) {
					items.push( { question, answer } );
				}
			}
			if ( block.innerBlocks && block.innerBlocks.length ) {
				walk( block.innerBlocks );
			}
		} );
	};
	walk( blocks || [] );
	return items;
};

const getEditorConfig = () =>
	typeof window !== 'undefined' && window.forwpFaqEditor ? window.forwpFaqEditor : {};

const getGlobalJsonLd = () => getEditorConfig().globalJsonLdEnabled;

const CATEGORY_MODE_NONE = 'none';
const CATEGORY_MODE_EXISTING = 'existing';
const CATEGORY_MODE_NEW = 'new';

const ALLOWED_FAQ_INNER_BLOCKS = [ 'core/accordion', 'core/accordion-group', 'core/details' ];

const createDefaultAccordionItem = () =>
	createBlock( 'core/accordion-item', {}, [
		createBlock( 'core/accordion-heading', {
			content: __( 'Question', '4wp-faq' ),
		} ),
		createBlock( 'core/accordion-panel', {}, [
			createBlock( 'core/paragraph', {
				placeholder: __( 'Answer…', '4wp-faq' ),
			} ),
		] ),
	] );

const createDefaultAccordionBlock = () =>
	createBlock( 'core/accordion', {}, [ createDefaultAccordionItem() ] );

const isHeadingAttrs = ( attrs ) =>
	attrs && typeof attrs.level === 'number';

const isHeadingParagraphPair = ( blocks ) => {
	if ( ! Array.isArray( blocks ) || blocks.length !== 2 ) {
		return false;
	}

	const names = blocks.map( ( block ) => block.name );
	return (
		( names[ 0 ] === 'core/heading' && names[ 1 ] === 'core/paragraph' ) ||
		( names[ 0 ] === 'core/paragraph' && names[ 1 ] === 'core/heading' )
	);
};

const createFaqFromHeadingParagraphPair = ( attributes, innerBlocks ) => {
	let headingAttrs = attributes[ 0 ];
	let paragraphAttrs = attributes[ 1 ];
	let paragraphInners = innerBlocks[ 1 ] || [];

	if ( ! isHeadingAttrs( headingAttrs ) ) {
		headingAttrs = attributes[ 1 ];
		paragraphAttrs = attributes[ 0 ];
		paragraphInners = innerBlocks[ 0 ] || [];
	}

	const question = headingAttrs.content || '';
	const answerBlock = createBlock(
		'core/paragraph',
		paragraphAttrs,
		paragraphInners
	);

	return createBlock( 'forwp/faq', {}, [
		createBlock( 'core/accordion', {}, [
			createBlock( 'core/accordion-item', {}, [
				createBlock( 'core/accordion-heading', { content: question } ),
				createBlock( 'core/accordion-panel', {}, [ answerBlock ] ),
			] ),
		] ),
	] );
};

const headingParagraphToFaqTransform = {
	type: 'block',
	blocks: [ 'forwp/faq' ],
	isMultiBlock: true,
	isMatch: ( attributes, blocks ) => isHeadingParagraphPair( blocks ),
	transform: ( attributes, innerBlocks ) =>
		createFaqFromHeadingParagraphPair( attributes, innerBlocks ),
};

const headingParagraphFaqFromTransform = {
	type: 'block',
	blocks: [ 'core/heading', 'core/paragraph' ],
	isMultiBlock: true,
	isMatch: ( attributes, blocks ) => isHeadingParagraphPair( blocks ),
	transform: ( attributes, innerBlocks ) =>
		createFaqFromHeadingParagraphPair( attributes, innerBlocks ),
};

const extractListItemQuestions = ( innerBlocks ) => {
	const questions = [];

	( innerBlocks || [] ).forEach( ( block ) => {
		if ( block.name !== 'core/list-item' ) {
			return;
		}

		const content = ( block.attributes?.content || '' ).trim();
		if ( content ) {
			questions.push( content );
		}
	} );

	return questions;
};

const extractQuestionsFromListValues = ( values ) => {
	if ( ! values || typeof values !== 'string' ) {
		return [];
	}

	const doc = new DOMParser().parseFromString(
		`<ul>${ values }</ul>`,
		'text/html'
	);

	return Array.from( doc.querySelectorAll( 'li' ) )
		.map( ( item ) => ( item.innerHTML || item.textContent || '' ).trim() )
		.filter( Boolean );
};

const createFaqAccordionItemFromQuestion = ( question ) =>
	createBlock( 'core/accordion-item', {}, [
		createBlock( 'core/accordion-heading', { content: question } ),
		createBlock( 'core/accordion-panel', {}, [
			createBlock( 'core/paragraph', {
				placeholder: __( 'Answer…', '4wp-faq' ),
			} ),
		] ),
	] );

const createFaqFromList = ( attributes, innerBlocks ) => {
	let questions = extractListItemQuestions( innerBlocks );

	if ( ! questions.length && attributes?.values ) {
		questions = extractQuestionsFromListValues( attributes.values );
	}

	if ( ! questions.length ) {
		return createBlock( 'forwp/faq', {}, [ createDefaultAccordionBlock() ] );
	}

	return createBlock( 'forwp/faq', {}, [
		createBlock(
			'core/accordion',
			{},
			questions.map( ( question ) => createFaqAccordionItemFromQuestion( question ) )
		),
	] );
};

const listToFaqTransform = {
	type: 'block',
	blocks: [ 'forwp/faq' ],
	transform: ( attributes, innerBlocks ) => createFaqFromList( attributes, innerBlocks ),
};

const getDefaultFaqInnerTemplate = () => [
	[
		'core/accordion',
		{},
		[
			[
				'core/accordion-item',
				{},
				[
					[
						'core/accordion-heading',
						{ content: __( 'Question', '4wp-faq' ) },
					],
					[
						'core/accordion-panel',
						{},
						[
							[
								'core/paragraph',
								{ placeholder: __( 'Answer…', '4wp-faq' ) },
							],
						],
					],
				],
			],
		],
	],
];

const useRegistryCategories = ( postId ) => {
	const [ terms, setTerms ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const registryReady = !! getEditorConfig().registrySetupComplete;
	const categoriesPath = getEditorConfig().categoriesPath || '/forwp-faq/v1/editor/categories';

	useEffect( () => {
		if ( ! registryReady ) {
			setTerms( [] );
			return;
		}

		let cancelled = false;
		setLoading( true );

		const query = postId ? `?post_id=${ postId }` : '';
		apiFetch( { path: `${ categoriesPath }${ query }` } )
			.then( ( response ) => {
				if ( cancelled ) {
					return;
				}
				setTerms( Array.isArray( response?.terms ) ? response.terms : [] );
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
	}, [ registryReady, categoriesPath, postId ] );

	return { terms, loading, registryReady };
};

const blockOutputsJsonLd = ( jsonLdAttr ) => {
	const mode = jsonLdAttr || '';
	if ( mode === 'enable' ) {
		return true;
	}
	if ( mode === 'disable' ) {
		return false;
	}
	return !! getGlobalJsonLd();
};

registerBlockType( 'forwp/faq', {
	edit: ( props ) => {
		const { attributes, setAttributes, clientId } = props;
		const { getBlock, postId } = useSelect( ( select ) => ( {
			getBlock: select( 'core/block-editor' ).getBlock,
			postId: select( 'core/editor' )?.getCurrentPostId?.() || 0,
		} ), [ props.clientId ] );

		const { insertBlock } = useDispatch( 'core/block-editor' );

		const currentBlock = getBlock( clientId );
		const items = useMemo( () => collectFaqItems( currentBlock?.innerBlocks || [] ), [ currentBlock ] );
		const accordionBlock = useMemo( () => {
			const inners = currentBlock?.innerBlocks || [];
			return (
				inners.find(
					( block ) =>
						block.name === 'core/accordion' || block.name === 'core/accordion-group'
				) || null
			);
		}, [ currentBlock ] );
		const jsonLdMode = attributes.jsonLd || '';
		const categoryMode = attributes.categoryMode || CATEGORY_MODE_NONE;
		const categoryTermId = attributes.categoryTermId || 0;
		const categoryName = attributes.categoryName || '';
		const { terms, loading: termsLoading, registryReady } = useRegistryCategories( postId );
		const outputsJsonLd = blockOutputsJsonLd( jsonLdMode );
		const globalOn = getGlobalJsonLd();

		const schema = useMemo( () => {
			if ( ! items.length || ! outputsJsonLd ) {
				return '';
			}
			return JSON.stringify(
				{
					'@context': 'https://schema.org',
					'@type': 'FAQPage',
					mainEntity: items.map( ( item ) => ( {
						'@type': 'Question',
						name: item.question,
						acceptedAnswer: {
							'@type': 'Answer',
							text: item.answer,
						},
					} ) ),
				},
				null,
				2
			);
		}, [ items, outputsJsonLd ] );

		const jsonLdHelp = globalOn
			? __(
					'Site-wide JSON-LD is on. Choose “Off for this block” to exclude this FAQ from structured data.',
					'4wp-faq'
			  )
			: __(
					'Site-wide JSON-LD is off. Choose “On for this block” to output FAQPage schema for this block only.',
					'4wp-faq'
			  );

		const onAddFaqItem = () => {
			const item = createDefaultAccordionItem();

			if ( accordionBlock ) {
				insertBlock(
					item,
					accordionBlock.clientId,
					accordionBlock.innerBlocks?.length || 0
				);
				return;
			}

			insertBlock( createDefaultAccordionBlock(), clientId, 0 );
		};

		return (
			<Fragment>
				<InspectorControls>
					<PanelBody title={ __( 'SEO', '4wp-faq' ) } initialOpen>
						<SelectControl
							label={ __( 'JSON-LD on front end', '4wp-faq' ) }
							help={ jsonLdHelp }
							value={ jsonLdMode }
							options={ [
								{
									label: globalOn
										? __( 'Default (on — site setting)', '4wp-faq' )
										: __( 'Default (off — site setting)', '4wp-faq' ),
									value: '',
								},
								{
									label: __( 'On for this block', '4wp-faq' ),
									value: 'enable',
								},
								{
									label: __( 'Off for this block', '4wp-faq' ),
									value: 'disable',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { jsonLd: value || '' } )
							}
						/>
					</PanelBody>
					<PanelBody title={ __( 'Registry category', '4wp-faq' ) } initialOpen={ false }>
						{ ! registryReady ? (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Complete FAQ registry setup to assign categories during scan.',
									'4wp-faq'
								) }
							</Notice>
						) : (
							<>
								<SelectControl
									label={ __( 'Category for this FAQ block', '4wp-faq' ) }
									help={ __(
										'Applied to registry entries when you run a scan. Default: none.',
										'4wp-faq'
									) }
									value={ categoryMode }
									options={ [
										{
											label: __( 'None', '4wp-faq' ),
											value: CATEGORY_MODE_NONE,
										},
										{
											label: __( 'Existing category', '4wp-faq' ),
											value: CATEGORY_MODE_EXISTING,
										},
										{
											label: __( 'Create new category', '4wp-faq' ),
											value: CATEGORY_MODE_NEW,
										},
									] }
									onChange={ ( value ) => {
										const next = value || CATEGORY_MODE_NONE;
										setAttributes( {
											categoryMode: next,
											categoryTermId: next === CATEGORY_MODE_EXISTING ? categoryTermId : 0,
											categoryName: next === CATEGORY_MODE_NEW ? categoryName : '',
										} );
									} }
								/>
								{ categoryMode === CATEGORY_MODE_EXISTING ? (
									termsLoading ? (
										<Spinner />
									) : (
										<SelectControl
											label={ __( 'FAQ category', '4wp-faq' ) }
											value={ String( categoryTermId || '' ) }
											options={ [
												{
													label: __( 'Select a category…', '4wp-faq' ),
													value: '',
												},
												...terms.map( ( term ) => ( {
													label: term.name,
													value: String( term.id ),
												} ) ),
											] }
											onChange={ ( value ) =>
												setAttributes( {
													categoryTermId: value ? parseInt( value, 10 ) : 0,
												} )
											}
										/>
									)
								) : null }
								{ categoryMode === CATEGORY_MODE_NEW ? (
									<TextControl
										label={ __( 'New category name', '4wp-faq' ) }
										help={ __(
											'Created in the FAQ registry taxonomy on scan (language follows this page).',
											'4wp-faq'
										) }
										value={ categoryName }
										onChange={ ( value ) =>
											setAttributes( { categoryName: value || '' } )
										}
									/>
								) : null }
							</>
						) }
					</PanelBody>
					<PanelBody title={ __( 'FAQ preview', '4wp-faq' ) } initialOpen={ false }>
						{ items.length === 0 ? (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Add accordion items to generate FAQ schema.',
									'4wp-faq'
								) }
							</Notice>
						) : null }
						{ ! outputsJsonLd && items.length > 0 ? (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'JSON-LD is off for this block on the front end.',
									'4wp-faq'
								) }
							</Notice>
						) : null }
						<p>
							{ __( 'Items:', '4wp-faq' ) } { items.length }
						</p>
						<TextareaControl
							label={ __( 'Schema JSON-LD (preview)', '4wp-faq' ) }
							value={ schema }
							rows={ Math.min( 12, Math.max( 6, items.length * 2 ) ) }
							readOnly
						/>
					</PanelBody>
				</InspectorControls>
				<div className={ props.className }>
					<InnerBlocks
						allowedBlocks={ ALLOWED_FAQ_INNER_BLOCKS }
						template={ getDefaultFaqInnerTemplate() }
						templateLock={ false }
					/>
					<div
						className="forwp-faq-editor-actions"
						style={ { marginTop: '12px' } }
					>
						<Button variant="secondary" onClick={ onAddFaqItem }>
							{ __( 'Add FAQ item', '4wp-faq' ) }
						</Button>
					</div>
				</div>
			</Fragment>
		);
	},
	save: () => <InnerBlocks.Content />,
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'core/accordion', 'core/accordion-group' ],
				transform: ( attributes, innerBlocks ) =>
					createBlock( 'forwp/faq', {}, [
						createBlock( 'core/accordion', attributes, innerBlocks ),
					] ),
			},
			{
				type: 'block',
				blocks: [ 'core/accordion-item' ],
				transform: ( attributes, innerBlocks ) =>
					createBlock( 'forwp/faq', {}, [
						createBlock( 'core/accordion', {}, [
							createBlock( 'core/accordion-item', attributes, innerBlocks ),
						] ),
					] ),
			},
			{
				type: 'block',
				blocks: [ 'core/details' ],
				transform: ( attributes, innerBlocks ) =>
					createBlock( 'forwp/faq', {}, [
						createBlock( 'core/details', attributes, innerBlocks ),
					] ),
			},
			headingParagraphFaqFromTransform,
			{
				type: 'block',
				blocks: [ 'core/list' ],
				transform: ( attributes, innerBlocks ) =>
					createFaqFromList( attributes, innerBlocks ),
			},
		],
	},
} );

const withFaqTransform = createHigherOrderComponent(
	( BlockEdit ) =>
		( props ) => {
			const isAccordion = isAccordionBlock( props.name );
			const isAccordionItem = props.name === 'core/accordion-item';
			const isList = props.name === 'core/list';

			if ( ! isAccordion && ! isAccordionItem && ! isList ) {
				return <BlockEdit { ...props } />;
			}

			const { replaceBlock } = useDispatch( 'core/block-editor' );
			const { getBlock, getBlockRootClientId } = useSelect(
				( select ) => ( {
					getBlock: select( 'core/block-editor' ).getBlock,
					getBlockRootClientId:
						select( 'core/block-editor' ).getBlockRootClientId,
				} ),
				[ props.clientId ]
			);

			const onConvert = () => {
				if ( isList ) {
					replaceBlock(
						props.clientId,
						createFaqFromList( props.attributes, props.innerBlocks )
					);
					return;
				}

				if ( isAccordion ) {
					replaceBlock(
						props.clientId,
						createBlock( 'forwp/faq', {}, [
							createBlock( props.name, props.attributes, props.innerBlocks ),
						] )
					);
					return;
				}

				const parentId = getBlockRootClientId( props.clientId );
				const parentBlock = parentId ? getBlock( parentId ) : null;
				if ( parentBlock && isAccordionBlock( parentBlock.name ) ) {
					replaceBlock(
						parentId,
						createBlock( 'forwp/faq', {}, [
							createBlock(
								parentBlock.name,
								parentBlock.attributes,
								parentBlock.innerBlocks
							),
						] )
					);
					return;
				}

				replaceBlock(
					props.clientId,
					createBlock( 'forwp/faq', {}, [
						createBlock( props.name, props.attributes, props.innerBlocks ),
					] )
				);
			};

			return (
				<Fragment>
					<BlockEdit { ...props } />
					<BlockControls>
						<ToolbarGroup>
							<ToolbarButton
								icon="editor-help"
								label="Convert to FAQ"
								onClick={ onConvert }
							/>
						</ToolbarGroup>
					</BlockControls>
				</Fragment>
			);
		},
	'withFaqTransform'
);

addFilter( 'editor.BlockEdit', 'forwp/faq/with-transform', withFaqTransform );

const withHeadingParagraphFaqConvert = createHigherOrderComponent(
	( BlockEdit ) =>
		( props ) => {
			if ( props.name !== 'core/heading' && props.name !== 'core/paragraph' ) {
				return <BlockEdit { ...props } />;
			}

			const { getMultiSelectedBlockClientIds, getBlock } = useSelect(
				( select ) => ( {
					getMultiSelectedBlockClientIds:
						select( 'core/block-editor' ).getMultiSelectedBlockClientIds,
					getBlock: select( 'core/block-editor' ).getBlock,
				} ),
				[]
			);

			const { replaceBlocks } = useDispatch( 'core/block-editor' );

			const selectedIds = getMultiSelectedBlockClientIds();
			const selectedBlocks =
				selectedIds.length === 2
					? selectedIds.map( ( id ) => getBlock( id ) ).filter( Boolean )
					: [];
			const canConvert = isHeadingParagraphPair( selectedBlocks );
			const showConvert = canConvert && selectedIds[ 0 ] === props.clientId;

			const onConvert = () => {
				if ( ! canConvert ) {
					return;
				}

				let ordered = selectedBlocks;
				if ( ordered[ 0 ].name === 'core/paragraph' ) {
					ordered = [ ordered[ 1 ], ordered[ 0 ] ];
				}

				const faqBlock = createFaqFromHeadingParagraphPair(
					[ ordered[ 0 ].attributes, ordered[ 1 ].attributes ],
					[ ordered[ 0 ].innerBlocks, ordered[ 1 ].innerBlocks ]
				);

				replaceBlocks( selectedIds, [ faqBlock ] );
			};

			return (
				<Fragment>
					<BlockEdit { ...props } />
					{ showConvert ? (
						<BlockControls>
							<ToolbarGroup>
								<ToolbarButton
									icon="editor-help"
									label={ __( 'Convert to FAQ', '4wp-faq' ) }
									onClick={ onConvert }
								/>
							</ToolbarGroup>
						</BlockControls>
					) : null }
				</Fragment>
			);
		},
	'withHeadingParagraphFaqConvert'
);

addFilter(
	'editor.BlockEdit',
	'forwp/faq/with-heading-paragraph-convert',
	withHeadingParagraphFaqConvert
);

const addBlockToFaqTransform = ( settings, blockName ) => {
	if ( blockName === 'core/heading' || blockName === 'core/paragraph' ) {
		const existingTo = settings.transforms?.to || [];

		return {
			...settings,
			transforms: {
				...settings.transforms,
				to: [ ...existingTo, headingParagraphToFaqTransform ],
			},
		};
	}

	if ( blockName === 'core/list' ) {
		const existingTo = settings.transforms?.to || [];

		return {
			...settings,
			transforms: {
				...settings.transforms,
				to: [ ...existingTo, listToFaqTransform ],
			},
		};
	}

	return settings;
};

addFilter(
	'blocks.registerBlockType',
	'forwp/faq/block-to-faq-transforms',
	addBlockToFaqTransform
);

