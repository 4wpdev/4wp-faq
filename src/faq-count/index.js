import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Disabled, Notice, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'forwp/faq-count', {
	edit: () => {
		const blockProps = useBlockProps( {
			className: 'forwp-faq-count-editor',
		} );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Count', '4wp-faq' ) } initialOpen>
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Shows how many questions are on the page: all categories (preview), the current category URL, or the current search.',
								'4wp-faq'
							) }
						</Notice>
					</PanelBody>
				</InspectorControls>
				<Disabled>
					<ServerSideRender block="forwp/faq-count" />
				</Disabled>
			</div>
		);
	},
	save: () => null,
} );
