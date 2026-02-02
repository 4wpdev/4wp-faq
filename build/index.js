(() => {
	const { registerBlockType, createBlock, serialize } = wp.blocks;
	const { InnerBlocks, BlockControls, InspectorControls } = wp.blockEditor;
	const { ToolbarGroup, ToolbarButton, PanelBody, TextareaControl, Notice } = wp.components;
	const { useDispatch, useSelect } = wp.data;
	const { createElement, Fragment, useMemo } = wp.element;
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;

	const extractTextFromBlocks = (blocks) => {
		if (!blocks || !blocks.length) {
			return '';
		}
		const html = serialize(blocks);
		const doc = new DOMParser().parseFromString(html, 'text/html');
		return (doc.body.textContent || '').trim();
	};

	const extractQuestionAnswer = (block) => {
		const attrs = block.attributes || {};
		let question = (attrs.title || attrs.summary || attrs.question || attrs.heading || '').trim();
		let answer = '';
		const innerBlocks = block.innerBlocks || [];

		if (!question && innerBlocks.length) {
			question = extractTextFromBlocks([innerBlocks[0]]);
			if (innerBlocks.length > 1) {
				answer = extractTextFromBlocks(innerBlocks.slice(1));
			}
		}

		if (!answer && innerBlocks.length) {
			answer = extractTextFromBlocks(innerBlocks);
		}

		return {
			question: question.trim(),
			answer: answer.trim(),
		};
	};

	const isAccordionItemBlock = (name) =>
		typeof name === 'string' && (name === 'core/accordion-item' || name.includes('accordion-item'));

	const isDetailsBlock = (name) =>
		typeof name === 'string' && (name === 'core/details' || name.endsWith('/details'));

	const collectFaqItems = (blocks) => {
		const items = [];
		const walk = (list) => {
			list.forEach((block) => {
				const name = block.name;
				if (isAccordionItemBlock(name) || isDetailsBlock(name)) {
					const { question, answer } = extractQuestionAnswer(block);
					if (question && answer) {
						items.push({ question, answer });
					}
				}
				if (block.innerBlocks && block.innerBlocks.length) {
					walk(block.innerBlocks);
				}
			});
		};
		walk(blocks || []);
		return items;
	};

	registerBlockType('forwp/faq', {
		edit: (props) => {
			const { getBlock } = useSelect(
				(select) => ({
					getBlock: select('core/block-editor').getBlock,
				}),
				[props.clientId]
			);

			const currentBlock = getBlock(props.clientId);
			const items = useMemo(
				() => collectFaqItems((currentBlock && currentBlock.innerBlocks) || []),
				[currentBlock]
			);
			const schema = useMemo(() => {
				if (!items.length) {
					return '';
				}
				return JSON.stringify(
					{
						'@context': 'https://schema.org',
						'@type': 'FAQPage',
						mainEntity: items.map((item) => ({
							'@type': 'Question',
							name: item.question,
							acceptedAnswer: {
								'@type': 'Answer',
								text: item.answer,
							},
						})),
					},
					null,
					2
				);
			}, [items]);

			return createElement(
				Fragment,
				null,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'FAQ Info', initialOpen: true },
						!items.length
							? createElement(
									Notice,
									{ status: 'warning', isDismissible: false },
									'Add accordion items to generate FAQ schema.'
								)
							: null,
						createElement('p', null, `Items: ${items.length}`),
						createElement(TextareaControl, {
							label: 'Schema JSON-LD',
							value: schema,
							rows: Math.min(12, Math.max(6, items.length * 2)),
							readOnly: true,
						})
					)
				),
				createElement(
					'div',
					{ className: props.className },
					createElement(InnerBlocks, {
						allowedBlocks: ['core/accordion', 'core/details'],
					})
				)
			);
		},
		save: () => createElement(InnerBlocks.Content),
		transforms: {
			from: [
				{
					type: 'block',
					blocks: ['core/accordion'],
					transform: (attributes, innerBlocks) => {
						return createBlock('forwp/faq', {}, [
							createBlock('core/accordion', attributes, innerBlocks),
						]);
					},
				},
				{
					type: 'block',
					blocks: ['core/accordion-item'],
					transform: (attributes, innerBlocks) => {
						return createBlock('forwp/faq', {}, [
							createBlock('core/accordion', {}, [
								createBlock('core/accordion-item', attributes, innerBlocks),
							]),
						]);
					},
				},
				{
					type: 'block',
					blocks: ['core/details'],
					transform: (attributes, innerBlocks) => {
						return createBlock('forwp/faq', {}, [
							createBlock('core/details', attributes, innerBlocks),
						]);
					},
				},
			],
		},
	});

	const withFaqTransform = createHigherOrderComponent(
		(BlockEdit) =>
			(props) => {
				const isAccordion = props.name === 'core/accordion';
				const isAccordionItem = props.name === 'core/accordion-item';

				if (!isAccordion && !isAccordionItem) {
					return createElement(BlockEdit, props);
				}

				const { replaceBlock } = useDispatch('core/block-editor');
				const { getBlock, getBlockRootClientId } = useSelect(
					(select) => ({
						getBlock: select('core/block-editor').getBlock,
						getBlockRootClientId: select('core/block-editor').getBlockRootClientId,
					}),
					[props.clientId]
				);

				const onConvert = () => {
					if (isAccordion) {
						replaceBlock(
							props.clientId,
							createBlock('forwp/faq', {}, [
								createBlock('core/accordion', props.attributes, props.innerBlocks),
							])
						);
						return;
					}

					const parentId = getBlockRootClientId(props.clientId);
					const parentBlock = parentId ? getBlock(parentId) : null;
					if (parentBlock && parentBlock.name === 'core/accordion') {
						replaceBlock(
							parentId,
							createBlock('forwp/faq', {}, [
								createBlock('core/accordion', parentBlock.attributes, parentBlock.innerBlocks),
							])
						);
						return;
					}

					replaceBlock(
						props.clientId,
						createBlock('forwp/faq', {}, [
							createBlock('core/accordion', {}, [
								createBlock('core/accordion-item', props.attributes, props.innerBlocks),
							]),
						])
					);
				};

				return createElement(
					Fragment,
					null,
					createElement(BlockEdit, props),
					createElement(
						BlockControls,
						null,
						createElement(
							ToolbarGroup,
							null,
							createElement(ToolbarButton, {
								icon: 'editor-help',
								label: 'Convert to FAQ',
								onClick: onConvert,
							})
						)
					)
				);
			},
		'withFaqTransform'
	);

	addFilter('editor.BlockEdit', 'forwp/faq/with-transform', withFaqTransform);
})();