import { registerBlockType, createBlock } from '@wordpress/blocks';
import { InnerBlocks, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { Fragment, useMemo } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { PanelBody, TextareaControl, Notice } from '@wordpress/components';
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

registerBlockType( 'forwp/faq', {
	edit: ( props ) => {
		const { getBlock } = useSelect( ( select ) => ( {
			getBlock: select( 'core/block-editor' ).getBlock,
		} ), [ props.clientId ] );

		const currentBlock = getBlock( props.clientId );
		const items = useMemo( () => collectFaqItems( currentBlock?.innerBlocks || [] ), [ currentBlock ] );
		const schema = useMemo( () => {
			if ( ! items.length ) {
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
		}, [ items ] );

		return (
			<Fragment>
				<InspectorControls>
					<PanelBody title="FAQ Info" initialOpen>
						{ items.length === 0 ? (
							<Notice status="warning" isDismissible={ false }>
								Add accordion items to generate FAQ schema.
							</Notice>
						) : null }
						<p>Items: { items.length }</p>
						<TextareaControl
							label="Schema JSON-LD"
							value={ schema }
							rows={ Math.min( 12, Math.max( 6, items.length * 2 ) ) }
							readOnly
						/>
					</PanelBody>
				</InspectorControls>
				<div className={ props.className }>
					<InnerBlocks allowedBlocks={ [ 'core/accordion', 'core/details' ] } />
				</div>
			</Fragment>
		);
	},
	save: () => <InnerBlocks.Content />,
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'core/accordion' ],
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
			const isAccordion = props.name === 'core/accordion';
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
							createBlock(
								'core/accordion',
								props.attributes,
								props.innerBlocks
							),
						] )
					);
					return;
				}

				const parentId = getBlockRootClientId( props.clientId );
				const parentBlock = parentId ? getBlock( parentId ) : null;
				if ( parentBlock && parentBlock.name === 'core/accordion' ) {
					replaceBlock(
						parentId,
						createBlock( 'forwp/faq', {}, [
							createBlock(
								'core/accordion',
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
						createBlock( 'core/accordion', {}, [
							createBlock(
								'core/accordion-item',
								props.attributes,
								props.innerBlocks
							),
						] ),
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

