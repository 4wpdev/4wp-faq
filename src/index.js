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
	FormTokenField,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { serialize } from '@wordpress/blocks';
import './search-binding';

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

const isHeadingParagraphPairs = ( blocks ) => {
	if ( ! Array.isArray( blocks ) || blocks.length < 2 || blocks.length % 2 !== 0 ) {
		return false;
	}

	if ( blocks.length === 2 ) {
		return isHeadingParagraphPair( blocks );
	}

	for ( let i = 0; i < blocks.length; i += 2 ) {
		if (
			blocks[ i ]?.name !== 'core/heading' ||
			blocks[ i + 1 ]?.name !== 'core/paragraph'
		) {
			return false;
		}
	}

	return true;
};

const TEXT_FAQ_SOURCE_BLOCKS = [
	'core/paragraph',
	'core/heading',
	'core/list',
	'core/quote',
];

const isConvertibleTextBlocks = ( blocks ) => {
	if ( ! Array.isArray( blocks ) || blocks.length < 2 ) {
		return false;
	}

	return blocks.every( ( block ) =>
		TEXT_FAQ_SOURCE_BLOCKS.includes( block?.name )
	);
};

const cloneBlockTree = ( block ) =>
	createBlock(
		block.name,
		{ ...( block.attributes || {} ) },
		( block.innerBlocks || [] ).map( cloneBlockTree )
	);

const stripHtmlToText = ( html ) =>
	String( html || '' )
		.replace( /<[^>]+>/g, ' ' )
		.replace( /&nbsp;/gi, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();

const getQuestionContent = ( block ) => {
	if ( ! block ) {
		return '';
	}

	if ( block.name === 'core/heading' || block.name === 'core/paragraph' ) {
		return block.attributes?.content || '';
	}

	if ( block.name === 'core/list' ) {
		const questions = extractListItemQuestions( block.innerBlocks );
		return questions[ 0 ] || '';
	}

	return extractTextFromBlocks( block.innerBlocks?.length ? block.innerBlocks : [ block ] );
};

const looksLikeQuestion = ( block ) => {
	if ( block?.name === 'core/heading' ) {
		return true;
	}

	return /\?\s*$/.test( stripHtmlToText( getQuestionContent( block ) ) );
};

const groupTextBlocksIntoFaqItems = ( blocks ) => {
	const useCues = blocks.some( looksLikeQuestion );

	if ( useCues ) {
		const items = [];
		let current = null;

		blocks.forEach( ( block ) => {
			if ( ! current || looksLikeQuestion( block ) ) {
				if ( current ) {
					items.push( current );
				}
				current = {
					question: getQuestionContent( block ),
					answers: [],
				};
				return;
			}

			current.answers.push( block );
		} );

		if ( current ) {
			items.push( current );
		}

		return items;
	}

	const items = [];
	for ( let i = 0; i < blocks.length; i += 2 ) {
		items.push( {
			question: getQuestionContent( blocks[ i ] ),
			answers: blocks[ i + 1 ] ? [ blocks[ i + 1 ] ] : [],
		} );
	}

	return items;
};

const createAccordionItemFromQa = ( question, answerBlocks ) => {
	const heading = stripHtmlToText( question )
		? question
		: __( 'Question', '4wp-faq' );
	const panelInner =
		answerBlocks.length > 0
			? answerBlocks.map( cloneBlockTree )
			: [
					createBlock( 'core/paragraph', {
						placeholder: __( 'Answer…', '4wp-faq' ),
					} ),
			  ];

	return createBlock( 'core/accordion-item', {}, [
		createBlock( 'core/accordion-heading', { content: heading } ),
		createBlock( 'core/accordion-panel', {}, panelInner ),
	] );
};

const createFaqFromTextBlocks = ( blocks ) => {
	if ( isHeadingParagraphPairs( blocks ) ) {
		return createFaqFromHeadingParagraphPairs(
			blocks.map( ( block ) => block.attributes ),
			blocks.map( ( block ) => block.innerBlocks )
		);
	}

	const items = groupTextBlocksIntoFaqItems( blocks );

	return createBlock( 'forwp/faq', {}, [
		createBlock(
			'core/accordion',
			{},
			items.map( ( item ) =>
				createAccordionItemFromQa( item.question, item.answers )
			)
		),
	] );
};

const createFaqFromDetails = ( attributes, innerBlocks ) => {
	const isMulti = Array.isArray( attributes );
	const attrsList = isMulti ? attributes : [ attributes ];
	const innersList = isMulti ? innerBlocks : [ innerBlocks || [] ];

	return createBlock(
		'forwp/faq',
		{},
		attrsList.map( ( attrs, i ) =>
			createBlock( 'core/details', attrs || {}, innersList[ i ] || [] )
		)
	);
};

const createFaqFromAccordionItems = ( attributes, innerBlocks ) => {
	const isMulti = Array.isArray( attributes );
	const attrsList = isMulti ? attributes : [ attributes ];
	const innersList = isMulti ? innerBlocks : [ innerBlocks || [] ];

	return createBlock( 'forwp/faq', {}, [
		createBlock(
			'core/accordion',
			{},
			attrsList.map( ( attrs, i ) =>
				createBlock( 'core/accordion-item', attrs || {}, innersList[ i ] || [] )
			)
		),
	] );
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

const createFaqFromHeadingParagraphPairs = ( attributes, innerBlocks ) => {
	if ( attributes.length === 2 && ! isHeadingAttrs( attributes[ 0 ] ) ) {
		return createFaqFromHeadingParagraphPair( attributes, innerBlocks );
	}

	const items = [];
	for ( let i = 0; i < attributes.length; i += 2 ) {
		const question = attributes[ i ]?.content || '';
		const answerBlock = createBlock(
			'core/paragraph',
			attributes[ i + 1 ] || {},
			innerBlocks[ i + 1 ] || []
		);
		items.push(
			createBlock( 'core/accordion-item', {}, [
				createBlock( 'core/accordion-heading', { content: question } ),
				createBlock( 'core/accordion-panel', {}, [ answerBlock ] ),
			] )
		);
	}

	return createBlock( 'forwp/faq', {}, [
		createBlock( 'core/accordion', {}, items ),
	] );
};

const CONVERT_TO_FAQ_LABEL = __( 'Convert to 4WP FAQ', '4wp-faq' );

const textBlocksToFaqTransform = {
	type: 'block',
	blocks: [ 'forwp/faq' ],
	isMultiBlock: true,
	isMatch: ( attributes, blocks ) => isConvertibleTextBlocks( blocks ),
	__experimentalConvert: ( blocks ) => createFaqFromTextBlocks( blocks ),
};

const textBlocksFaqFromTransform = {
	type: 'block',
	blocks: [ '*' ],
	isMultiBlock: true,
	isMatch: ( attributes, blocks ) => isConvertibleTextBlocks( blocks ),
	__experimentalConvert: ( blocks ) => createFaqFromTextBlocks( blocks ),
};

const detailsToFaqTransform = {
	type: 'block',
	blocks: [ 'forwp/faq' ],
	isMultiBlock: true,
	transform: ( attributes, innerBlocks ) =>
		createFaqFromDetails( attributes, innerBlocks ),
};

const accordionItemToFaqTransform = {
	type: 'block',
	blocks: [ 'forwp/faq' ],
	isMultiBlock: true,
	transform: ( attributes, innerBlocks ) =>
		createFaqFromAccordionItems( attributes, innerBlocks ),
};

const accordionToFaqTransform = {
	type: 'block',
	blocks: [ 'forwp/faq' ],
	transform: ( attributes, innerBlocks ) =>
		createBlock( 'forwp/faq', {}, [
			createBlock( 'core/accordion', attributes, innerBlocks ),
		] ),
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
		const categoryTermIds = Array.isArray( attributes.categoryTermIds )
			? attributes.categoryTermIds
			: [];
		const categoryTermId = attributes.categoryTermId || 0;
		const selectedTermIds =
			categoryTermIds.length > 0
				? categoryTermIds
				: categoryTermId
				? [ categoryTermId ]
				: [];
		const categoryName = attributes.categoryName || '';
		const [ showNewCategory, setShowNewCategory ] = useState(
			!! ( categoryName && String( categoryName ).trim() )
		);
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
						) : termsLoading ? (
							<Spinner />
						) : (
							<>
								<FormTokenField
									label={ __( 'FAQ categories', '4wp-faq' ) }
									help={ __(
										'Search by name. The first category is the primary group for this FAQ. Applied on scan.',
										'4wp-faq'
									) }
									value={ ( selectedTermIds || [] )
										.map(
											( id ) =>
												terms.find( ( term ) => term.id === id )?.name
										)
										.filter( Boolean ) }
									suggestions={ terms.map( ( term ) => term.name ) }
									onChange={ ( tokens ) => {
										const ids = ( tokens || [] )
											.map(
												( name ) =>
													terms.find( ( term ) => term.name === name )?.id
											)
											.filter( Boolean );
										setAttributes( {
											categoryTermIds: ids,
											categoryTermId: ids[ 0 ] || 0,
											categoryMode: ids.length
												? CATEGORY_MODE_EXISTING
												: categoryName
												? CATEGORY_MODE_NEW
												: CATEGORY_MODE_NONE,
										} );
									} }
									__experimentalExpandOnFocus
									__nextHasNoMarginBottom
								/>
								{ showNewCategory ? (
									<TextControl
										label={ __( 'New category name', '4wp-faq' ) }
										help={ __(
											'Created in the FAQ registry taxonomy on scan (language follows this page). Becomes primary if no existing category is selected.',
											'4wp-faq'
										) }
										value={ categoryName }
										onChange={ ( value ) =>
											setAttributes( {
												categoryName: value || '',
												categoryMode: selectedTermIds.length
													? CATEGORY_MODE_EXISTING
													: value
													? CATEGORY_MODE_NEW
													: CATEGORY_MODE_NONE,
											} )
										}
									/>
								) : (
									<Button
										variant="secondary"
										onClick={ () => setShowNewCategory( true ) }
									>
										{ __( 'Add new', '4wp-faq' ) }
									</Button>
								) }
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
				isMultiBlock: true,
				transform: ( attributes, innerBlocks ) =>
					createFaqFromAccordionItems( attributes, innerBlocks ),
			},
			{
				type: 'block',
				blocks: [ 'core/details' ],
				isMultiBlock: true,
				transform: ( attributes, innerBlocks ) =>
					createFaqFromDetails( attributes, innerBlocks ),
			},
			textBlocksFaqFromTransform,
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
			const isDetails = props.name === 'core/details';
			const isList = props.name === 'core/list';

			if ( ! isAccordion && ! isAccordionItem && ! isDetails && ! isList ) {
				return <BlockEdit { ...props } />;
			}

			const { replaceBlock, replaceBlocks } = useDispatch( 'core/block-editor' );
			const { getBlock, getBlockRootClientId, getMultiSelectedBlockClientIds } =
				useSelect(
					( select ) => ( {
						getBlock: select( 'core/block-editor' ).getBlock,
						getBlockRootClientId:
							select( 'core/block-editor' ).getBlockRootClientId,
						getMultiSelectedBlockClientIds:
							select( 'core/block-editor' ).getMultiSelectedBlockClientIds,
					} ),
					[ props.clientId ]
				);

			const selectedIds = getMultiSelectedBlockClientIds();
			const selectedBlocks = ( selectedIds || [] )
				.map( ( id ) => getBlock( id ) )
				.filter( Boolean );
			const sameTypeMulti =
				selectedBlocks.length > 1 &&
				selectedBlocks.every( ( block ) => block.name === props.name ) &&
				( isDetails || isAccordionItem );
			const showConvert =
				! sameTypeMulti || selectedIds[ 0 ] === props.clientId;

			const onConvert = () => {
				if ( sameTypeMulti ) {
					const attrs = selectedBlocks.map( ( block ) => block.attributes );
					const inners = selectedBlocks.map( ( block ) => block.innerBlocks );
					const faqBlock = isDetails
						? createFaqFromDetails( attrs, inners )
						: createFaqFromAccordionItems( attrs, inners );
					replaceBlocks( selectedIds, [ faqBlock ] );
					return;
				}

				if ( isList ) {
					replaceBlock(
						props.clientId,
						createFaqFromList( props.attributes, props.innerBlocks )
					);
					return;
				}

				if ( isDetails ) {
					replaceBlock(
						props.clientId,
						createFaqFromDetails( props.attributes, props.innerBlocks )
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
					createFaqFromAccordionItems( props.attributes, props.innerBlocks )
				);
			};

			if ( ! showConvert ) {
				return <BlockEdit { ...props } />;
			}

			return (
				<Fragment>
					<BlockEdit { ...props } />
					<BlockControls>
						<ToolbarGroup>
							<ToolbarButton
								icon="editor-help"
								text={ CONVERT_TO_FAQ_LABEL }
								label={ CONVERT_TO_FAQ_LABEL }
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
			if ( ! TEXT_FAQ_SOURCE_BLOCKS.includes( props.name ) ) {
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
				selectedIds.length >= 2
					? selectedIds.map( ( id ) => getBlock( id ) ).filter( Boolean )
					: [];
			const canConvert = isConvertibleTextBlocks( selectedBlocks );
			const showConvert = canConvert && selectedIds[ 0 ] === props.clientId;

			const onConvert = () => {
				if ( ! canConvert ) {
					return;
				}

				replaceBlocks( selectedIds, [ createFaqFromTextBlocks( selectedBlocks ) ] );
			};

			return (
				<Fragment>
					<BlockEdit { ...props } />
					{ showConvert ? (
						<BlockControls>
							<ToolbarGroup>
								<ToolbarButton
									icon="editor-help"
									text={ CONVERT_TO_FAQ_LABEL }
									label={ CONVERT_TO_FAQ_LABEL }
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
	if (
		blockName === 'core/heading' ||
		blockName === 'core/paragraph' ||
		blockName === 'core/quote'
	) {
		const existingTo = settings.transforms?.to || [];

		return {
			...settings,
			transforms: {
				...settings.transforms,
				to: [ ...existingTo, textBlocksToFaqTransform ],
			},
		};
	}

	if ( blockName === 'core/list' ) {
		const existingTo = settings.transforms?.to || [];

		return {
			...settings,
			transforms: {
				...settings.transforms,
				to: [ ...existingTo, listToFaqTransform, textBlocksToFaqTransform ],
			},
		};
	}

	if ( blockName === 'core/details' ) {
		const existingTo = settings.transforms?.to || [];

		return {
			...settings,
			transforms: {
				...settings.transforms,
				to: [ ...existingTo, detailsToFaqTransform ],
			},
		};
	}

	if ( blockName === 'core/accordion-item' ) {
		const existingTo = settings.transforms?.to || [];

		return {
			...settings,
			transforms: {
				...settings.transforms,
				to: [ ...existingTo, accordionItemToFaqTransform ],
			},
		};
	}

	if ( blockName === 'core/accordion' || blockName === 'core/accordion-group' ) {
		const existingTo = settings.transforms?.to || [];

		return {
			...settings,
			transforms: {
				...settings.transforms,
				to: [ ...existingTo, accordionToFaqTransform ],
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

