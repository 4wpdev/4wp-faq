/**
 * Classical Gutenberg Styles-tab typography for FAQ question / answer.
 */

import {
	LineHeightControl,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalFontAppearanceControl as FontAppearanceControl,
	__experimentalFontFamilyControl as FontFamilyControl,
	__experimentalLetterSpacingControl as LetterSpacingControl,
	__experimentalTextDecorationControl as TextDecorationControl,
	__experimentalTextTransformControl as TextTransformControl,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	FontSizePicker,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useViewportMatch } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Flatten theme.json font family settings into FontFamilyControl list.
 *
 * @param {unknown} raw Font family settings.
 * @return {Array<{name: string, slug: string, fontFamily: string}>}
 */
const flattenFontFamilyObjects = ( raw ) => {
	const list = [];
	const seen = new Set();

	const pushFamily = ( family ) => {
		if ( ! family || typeof family !== 'object' ) {
			return;
		}
		const fontFamily =
			typeof family.fontFamily === 'string' ? family.fontFamily : '';
		const slug = typeof family.slug === 'string' ? family.slug : '';
		const key = fontFamily || slug;
		if ( ! key || seen.has( key ) ) {
			return;
		}
		seen.add( key );
		list.push( {
			name:
				( typeof family.name === 'string' && family.name ) ||
				slug ||
				fontFamily,
			slug: slug || key.replace( /[^a-z0-9\-]+/gi, '-' ).toLowerCase(),
			fontFamily:
				fontFamily ||
				( slug ? `var(--wp--preset--font-family--${ slug })` : '' ),
		} );
	};

	if ( Array.isArray( raw ) ) {
		raw.forEach( pushFamily );
		return list;
	}

	if ( raw && typeof raw === 'object' ) {
		Object.values( raw ).forEach( ( group ) => {
			if ( Array.isArray( group ) ) {
				group.forEach( pushFamily );
			}
		} );
	}

	return list;
};

/**
 * Normalize FontSizePicker value to a CSS size string.
 *
 * @param {string|number|undefined} value Picker value.
 * @return {string} CSS size or empty.
 */
export const normalizeFontSize = ( value ) => {
	if ( value === undefined || value === null || value === '' ) {
		return '';
	}
	if ( typeof value === 'number' && Number.isFinite( value ) ) {
		return `${ value }px`;
	}
	return String( value );
};

/**
 * Normalize color picker values (incl. Gutenberg `var:preset|color|slug`).
 *
 * @param {string|undefined} value Raw color.
 * @return {string}
 */
export const normalizeColor = ( value ) => {
	if ( value === undefined || value === null || value === '' ) {
		return '';
	}
	const raw = String( value ).trim();
	const preset = raw.match( /^var:preset\|color\|([a-z0-9\-]+)$/i );
	if ( preset ) {
		return `var(--wp--preset--color--${ preset[ 1 ].toLowerCase() })`;
	}
	return raw;
};

const EMPTY_STYLE = {
	color: '',
	fontFamily: '',
	fontSize: '',
	fontStyle: '',
	fontWeight: '',
	lineHeight: '',
	letterSpacing: '',
	textDecoration: '',
	textTransform: '',
};

const STYLE_VAR_SUFFIX = {
	fontSize: 'size',
	fontFamily: 'family',
	fontWeight: 'weight',
	fontStyle: 'style',
	lineHeight: 'line',
	letterSpacing: 'spacing',
	textDecoration: 'decoration',
	textTransform: 'transform',
	color: 'color',
};

/**
 * CSS custom properties for one style object (mirrors PHP output).
 *
 * @param {string} prefix `q`, `a` or `g`.
 * @param {Object} style  Style attribute value.
 * @return {Object} Inline style map.
 */
export const typographyStyleVars = ( prefix, style ) => {
	const vars = {};
	if ( ! style || typeof style !== 'object' ) {
		return vars;
	}
	Object.entries( STYLE_VAR_SUFFIX ).forEach( ( [ key, suffix ] ) => {
		if ( style[ key ] ) {
			vars[ `--forwp-faq-${ prefix }-${ suffix }` ] = style[ key ];
		}
	} );
	return vars;
};

/**
 * Theme / editor typography presets.
 *
 * @return {{ fontSizes: Array|undefined, fontFamilies: Array }}
 */
export const useEditorTypography = () =>
	useSelect( ( select ) => {
		const settings = select( 'core/block-editor' )?.getSettings?.() || {};
		const features = settings.__experimentalFeatures || {};
		const families =
			features?.typography?.fontFamilies ??
			settings.fontFamilies ??
			null;

		return {
			fontSizes: Array.isArray( settings.fontSizes )
				? settings.fontSizes
				: Array.isArray( features?.typography?.fontSizes )
					? features.typography.fontSizes
					: undefined,
			fontFamilies: flattenFontFamilyObjects( families ),
		};
	}, [] );

/**
 * Same popover placement as the core block inspector panels.
 *
 * @return {Object}
 */
export const useFaqDropdownMenuProps = () => {
	const isMobile = useViewportMatch( 'medium', '<' );
	return isMobile
		? {}
		: { popoverProps: { placement: 'left-start', offset: 259 } };
};

const EMPTY_COLOR_GRADIENT_SETTINGS = {
	colors: [],
	gradients: [],
	disableCustomColors: false,
	disableCustomGradients: true,
	hasColorsOrGradients: false,
};

/**
 * Core-style Typography ToolsPanel for one FAQ text part.
 *
 * @param {Object}   props
 * @param {Object}   props.value    Current style attrs.
 * @param {Function} props.onChange Receive full style object.
 * @param {string}   props.title    ToolsPanel label.
 * @param {string}   props.panelId  Unique ToolsPanel id.
 */
export function FaqTypographyPanel( { value = {}, onChange, title, panelId } ) {
	const { fontSizes, fontFamilies } = useEditorTypography();
	const dropdownMenuProps = useFaqDropdownMenuProps();
	const colorGradientSettings =
		( typeof useMultipleOriginColorsAndGradients === 'function'
			? useMultipleOriginColorsAndGradients()
			: null ) || EMPTY_COLOR_GRADIENT_SETTINGS;

	const style = value && typeof value === 'object' ? value : {};
	const patch = ( next ) => onChange( { ...style, ...next } );
	const has = ( key ) => () => !! style[ key ];
	const clear = ( ...keys ) => () =>
		patch( Object.fromEntries( keys.map( ( key ) => [ key, '' ] ) ) );

	return (
		<ToolsPanel
			label={ title }
			resetAll={ () => onChange( { ...EMPTY_STYLE } ) }
			panelId={ panelId }
			__experimentalFirstVisibleItemClass="first"
			dropdownMenuProps={ dropdownMenuProps }
		>
			<ColorGradientSettingsDropdown
				__experimentalIsRenderedInSidebar
				settings={ [
					{
						colorValue: style.color || undefined,
						label: __( 'Color', '4wp-faq' ),
						onColorChange: ( next ) =>
							patch( { color: normalizeColor( next ) } ),
						isShownByDefault: true,
						resetAllFilter: () => patch( { color: '' } ),
						hasValue: has( 'color' ),
						onDeselect: clear( 'color' ),
					},
				] }
				panelId={ panelId }
				{ ...colorGradientSettings }
			/>

			{ fontFamilies.length > 0 ? (
				<ToolsPanelItem
					label={ __( 'Font', '4wp-faq' ) }
					hasValue={ has( 'fontFamily' ) }
					onDeselect={ clear( 'fontFamily' ) }
					isShownByDefault
					panelId={ panelId }
				>
					<FontFamilyControl
						fontFamilies={ fontFamilies }
						value={ style.fontFamily || '' }
						onChange={ ( next ) => patch( { fontFamily: next || '' } ) }
					/>
				</ToolsPanelItem>
			) : null }

			<ToolsPanelItem
				label={ __( 'Size', '4wp-faq' ) }
				hasValue={ has( 'fontSize' ) }
				onDeselect={ clear( 'fontSize' ) }
				isShownByDefault
				panelId={ panelId }
			>
				<FontSizePicker
					fontSizes={ fontSizes }
					value={ style.fontSize || undefined }
					onChange={ ( next ) =>
						patch( { fontSize: normalizeFontSize( next ) } )
					}
					withReset={ false }
					withSlider
				/>
			</ToolsPanelItem>

			<ToolsPanelItem
				label={ __( 'Appearance', '4wp-faq' ) }
				hasValue={ () => !! style.fontWeight || !! style.fontStyle }
				onDeselect={ clear( 'fontWeight', 'fontStyle' ) }
				isShownByDefault
				panelId={ panelId }
			>
				<FontAppearanceControl
					value={ {
						fontStyle: style.fontStyle || undefined,
						fontWeight: style.fontWeight || undefined,
					} }
					onChange={ ( next ) =>
						patch( {
							fontStyle: next?.fontStyle ? String( next.fontStyle ) : '',
							fontWeight: next?.fontWeight ? String( next.fontWeight ) : '',
						} )
					}
					hasFontStyles
					hasFontWeights
				/>
			</ToolsPanelItem>

			<ToolsPanelItem
				className="single-column"
				label={ __( 'Line height', '4wp-faq' ) }
				hasValue={ has( 'lineHeight' ) }
				onDeselect={ clear( 'lineHeight' ) }
				isShownByDefault
				panelId={ panelId }
			>
				<LineHeightControl
					__unstableInputWidth="auto"
					value={ style.lineHeight || undefined }
					onChange={ ( next ) =>
						patch( { lineHeight: next ? String( next ) : '' } )
					}
				/>
			</ToolsPanelItem>

			<ToolsPanelItem
				className="single-column"
				label={ __( 'Letter spacing', '4wp-faq' ) }
				hasValue={ has( 'letterSpacing' ) }
				onDeselect={ clear( 'letterSpacing' ) }
				isShownByDefault
				panelId={ panelId }
			>
				<LetterSpacingControl
					__unstableInputWidth="auto"
					value={ style.letterSpacing || undefined }
					onChange={ ( next ) =>
						patch( { letterSpacing: next || '' } )
					}
				/>
			</ToolsPanelItem>

			<ToolsPanelItem
				label={ __( 'Decoration', '4wp-faq' ) }
				hasValue={ has( 'textDecoration' ) }
				onDeselect={ clear( 'textDecoration' ) }
				isShownByDefault
				panelId={ panelId }
			>
				<TextDecorationControl
					value={ style.textDecoration || undefined }
					onChange={ ( next ) =>
						patch( { textDecoration: next || '' } )
					}
				/>
			</ToolsPanelItem>

			<ToolsPanelItem
				label={ __( 'Letter case', '4wp-faq' ) }
				hasValue={ has( 'textTransform' ) }
				onDeselect={ clear( 'textTransform' ) }
				isShownByDefault
				panelId={ panelId }
			>
				<TextTransformControl
					value={ style.textTransform || undefined }
					onChange={ ( next ) =>
						patch( { textTransform: next || '' } )
					}
				/>
			</ToolsPanelItem>
		</ToolsPanel>
	);
}

