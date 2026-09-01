import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

addFilter(
	'blocks.registerBlockType',
	'forwp/faq/search-faq-filter-attribute',
	( settings, name ) => {
		if ( name !== 'core/search' ) {
			return settings;
		}

		return {
			...settings,
			attributes: {
				...settings.attributes,
				forwpFaqFilter: {
					type: 'boolean',
					default: false,
				},
			},
		};
	}
);

const withFaqSearchFilter = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( props.name !== 'core/search' ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody title={ __( '4WP FAQ', '4wp-faq' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Filter 4WP FAQ List', '4wp-faq' ) }
							help={ __(
								'When on, this Search filters FAQ cards on the same page via the Interactivity API.',
								'4wp-faq'
							) }
							checked={ !! attributes.forwpFaqFilter }
							onChange={ ( value ) =>
								setAttributes( { forwpFaqFilter: !! value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withFaqSearchFilter' );

addFilter(
	'editor.BlockEdit',
	'forwp/faq/search-faq-filter-control',
	withFaqSearchFilter
);
