import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RadioControl, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import './style.scss';

registerBlockType( 'forwp/faq-card', {
	edit: ( { attributes, setAttributes } ) => {
		const {
			displayMode = 'accordion',
			showSources = false,
			sourcesLabel = __( 'Used in', '4wp-faq' ),
			showPostType = false,
		} = attributes;
		const blockProps = useBlockProps( {
			className: `forwp-faq-card-editor is-display-${ displayMode }`,
		} );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Card', '4wp-faq' ) } initialOpen>
						<RadioControl
							label={ __( 'Display', '4wp-faq' ) }
							selected={ displayMode }
							options={ [
								{
									label: __( 'Accordion', '4wp-faq' ),
									value: 'accordion',
								},
								{
									label: __( 'Heading and description', '4wp-faq' ),
									value: 'heading',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( {
									displayMode: value || 'accordion',
								} )
							}
						/>
						<ToggleControl
							label={ __( 'Show source links', '4wp-faq' ) }
							checked={ !! showSources }
							onChange={ ( value ) =>
								setAttributes( { showSources: !! value } )
							}
						/>
						{ showSources ? (
							<>
								<TextControl
									label={ __( 'Sources label', '4wp-faq' ) }
									placeholder={ __( 'Used in', '4wp-faq' ) }
									help={ __(
										'Placeholder above the source links. Leave empty to hide the label.',
										'4wp-faq'
									) }
									value={ sourcesLabel }
									onChange={ ( value ) =>
										setAttributes( { sourcesLabel: value } )
									}
								/>
								<ToggleControl
									label={ __( 'Show CPT type', '4wp-faq' ) }
									help={ __(
										'Show the source post type next to each link.',
										'4wp-faq'
									) }
									checked={ !! showPostType }
									onChange={ ( value ) =>
										setAttributes( { showPostType: !! value } )
									}
								/>
							</>
						) : null }
					</PanelBody>
				</InspectorControls>
				{ displayMode === 'heading' ? (
					<div className="forwp-faq-card-editor__heading">
						<strong className="forwp-faq-card-editor__question">
							{ __( 'Question', '4wp-faq' ) }
						</strong>
						<p className="forwp-faq-card-editor__answer">
							{ __( 'Answer', '4wp-faq' ) }
						</p>
					</div>
				) : (
					<details className="forwp-faq-card-editor__details" open>
						<summary className="forwp-faq-card-editor__question">
							{ __( 'Question', '4wp-faq' ) }
						</summary>
						<p className="forwp-faq-card-editor__answer">
							{ __( 'Answer', '4wp-faq' ) }
						</p>
					</details>
				) }
				{ showSources ? (
					<p className="forwp-faq-card-editor__sources">
						{ sourcesLabel }
						{ showPostType
							? ( sourcesLabel ? ' · ' : '' ) + __( 'CPT type', '4wp-faq' )
							: '' }
					</p>
				) : null }
			</div>
		);
	},
	save: () => null,
} );
