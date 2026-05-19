import { registerBlockType, createBlock } from '@wordpress/blocks';
import { InnerBlocks, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { Fragment, useMemo } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	PanelBody,
	TextareaControl,
	Notice,
	SelectControl,
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

const getGlobalJsonLd = () =>
	typeof window !== 'undefined' &&
	window.forwpFaqEditor &&
	window.forwpFaqEditor.globalJsonLdEnabled;

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
		const { attributes, setAttributes } = props;
		const { getBlock } = useSelect( ( select ) => ( {
			getBlock: select( 'core/block-editor' ).getBlock,
		} ), [ props.clientId ] );

		const currentBlock = getBlock( props.clientId );
		const items = useMemo( () => collectFaqItems( currentBlock?.innerBlocks || [] ), [ currentBlock ] );
		const jsonLdMode = attributes.jsonLd || '';
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
					<InnerBlocks />
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
		],
	},
} );

const withFaqTransform = createHigherOrderComponent(
	( BlockEdit ) =>
		( props ) => {
			const isAccordion = isAccordionBlock( props.name );
			const isAccordionItem = props.name === 'core/accordion-item';

			if ( ! isAccordion && ! isAccordionItem ) {
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

