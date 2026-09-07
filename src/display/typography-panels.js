/**
 * Classical Gutenberg Styles-tab typography for FAQ question / answer.
 */

import {
	FontSizePicker,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalFontAppearanceControl as FontAppearanceControl,
	__experimentalFontFamilyControl as FontFamilyControl,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	SelectControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
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
	fontSize: '',
	fontFamily: '',
	fontWeight: '',
	color: '',
};

/**
 * Theme / editor typography presets.
 *
 * @return {{ fontSizes: Array|undefined, fontFamilies: Array }}
 */
const useEditorTypography = () =>
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
 * Stable empty payload when the experimental colors hook is missing.
 */
const EMPTY_COLOR_GRADIENT_SETTINGS = {
	colors: [],
	gradients: [],
	disableCustomColors: false,
	disableCustomGradients: true,
	hasColorsOrGradients: false,
};

/**
 * @param {Object}   props
 * @param {Object}   props.value     Current style attrs for one side.
 * @param {Function} props.onChange  Receive full style object.
 * @param {string}   props.title     ToolsPanel label.
 * @param {string}   props.panelId   Unique ToolsPanel id.
 * @param {string}   props.help      Optional help under the panel.
 */
export function FaqTypographyPanel( {
	value = {},
	onChange,
	title,
	panelId,
	help = '',
} ) {
	const { fontSizes, fontFamilies } = useEditorTypography();
	// Call only when present; presence is stable for a given WP version.
	const colorGradientSettings =
		( typeof useMultipleOriginColorsAndGradients === 'function'
			? useMultipleOriginColorsAndGradients()
			: null ) || EMPTY_COLOR_GRADIENT_SETTINGS;

	const safeValue = value && typeof value === 'object' ? value : {};
	const fontSize = safeValue.fontSize || '';
	const fontFamily = safeValue.fontFamily || '';
	const fontWeight = safeValue.fontWeight || '';
	const color = safeValue.color || '';

	const patch = ( next ) => onChange( { ...safeValue, ...next } );
	const resetAll = () => onChange( { ...EMPTY_STYLE } );

	const hasFont = () => !! fontFamily;
	const hasSize = () => !! fontSize;
	const hasWeight = () => !! fontWeight;
	const hasColor = () => !! color;

	return (
		<>
			{ help ? (
				<p className="forwp-faq-typography-help">{ help }</p>
			) : null }
			<ToolsPanel
				label={ title }
				resetAll={ resetAll }
				panelId={ panelId }
				hasInnerWrapper
			>
				{ ColorGradientSettingsDropdown ? (
					<ColorGradientSettingsDropdown
						__experimentalIsRenderedInSidebar
						settings={ [
							{
								colorValue: color || undefined,
								label: __( 'Color', '4wp-faq' ),
								onColorChange: ( next ) =>
									patch( { color: normalizeColor( next ) } ),
								isShownByDefault: true,
								resetAllFilter: () => patch( { color: '' } ),
								hasValue: hasColor,
								onDeselect: () => patch( { color: '' } ),
							},
						] }
						panelId={ panelId }
						{ ...colorGradientSettings }
					/>
				) : null }

				{ fontFamilies.length > 0 && FontFamilyControl ? (
					<ToolsPanelItem
						hasValue={ hasFont }
						label={ __( 'Font', '4wp-faq' ) }
						onDeselect={ () => patch( { fontFamily: '' } ) }
						isShownByDefault
						panelId={ panelId }
					>
						<FontFamilyControl
							fontFamilies={ fontFamilies }
							value={ fontFamily }
							onChange={ ( next ) =>
								patch( { fontFamily: next || '' } )
							}
							size="__unstable-large"
							__nextHasNoMarginBottom
						/>
					</ToolsPanelItem>
				) : fontFamilies.length > 0 ? (
					<ToolsPanelItem
						hasValue={ hasFont }
						label={ __( 'Font', '4wp-faq' ) }
						onDeselect={ () => patch( { fontFamily: '' } ) }
						isShownByDefault
						panelId={ panelId }
					>
						<SelectControl
							label={ __( 'Font', '4wp-faq' ) }
							hideLabelFromVision
							value={ fontFamily }
							options={ [
								{
									label: __( 'Default', '4wp-faq' ),
									value: '',
								},
								...fontFamilies.map( ( family ) => ( {
									label: family.name,
									value: family.fontFamily,
								} ) ),
							] }
							onChange={ ( next ) =>
								patch( { fontFamily: next || '' } )
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</ToolsPanelItem>
				) : null }

				<ToolsPanelItem
					hasValue={ hasSize }
					label={ __( 'Size', '4wp-faq' ) }
					onDeselect={ () => patch( { fontSize: '' } ) }
					isShownByDefault
					panelId={ panelId }
				>
					<FontSizePicker
						fontSizes={ fontSizes }
						value={ fontSize || undefined }
						onChange={ ( next ) =>
							patch( { fontSize: normalizeFontSize( next ) } )
						}
						withReset={ false }
						size="__unstable-large"
						__next40pxDefaultSize
					/>
				</ToolsPanelItem>

				{ FontAppearanceControl ? (
					<ToolsPanelItem
						hasValue={ hasWeight }
						label={ __( 'Appearance', '4wp-faq' ) }
						onDeselect={ () => patch( { fontWeight: '' } ) }
						isShownByDefault
						panelId={ panelId }
					>
						<FontAppearanceControl
							value={ {
								fontStyle: 'normal',
								fontWeight: fontWeight || undefined,
							} }
							onChange={ ( next ) =>
								patch( {
									fontWeight: next?.fontWeight
										? String( next.fontWeight )
										: '',
								} )
							}
							hasFontStyles={ false }
							hasFontWeights
						/>
					</ToolsPanelItem>
				) : (
					<ToolsPanelItem
						hasValue={ hasWeight }
						label={ __( 'Appearance', '4wp-faq' ) }
						onDeselect={ () => patch( { fontWeight: '' } ) }
						isShownByDefault
						panelId={ panelId }
					>
						<SelectControl
							label={ __( 'Weight', '4wp-faq' ) }
							hideLabelFromVision
							value={ fontWeight }
							options={ [
								{
									label: __( 'Default', '4wp-faq' ),
									value: '',
								},
								{
									label: __( 'Normal', '4wp-faq' ),
									value: '400',
								},
								{
									label: __( 'Medium', '4wp-faq' ),
									value: '500',
								},
								{
									label: __( 'Semibold', '4wp-faq' ),
									value: '600',
								},
								{
									label: __( 'Bold', '4wp-faq' ),
									value: '700',
								},
							] }
							onChange={ ( next ) =>
								patch( { fontWeight: next || '' } )
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</ToolsPanelItem>
				) }
			</ToolsPanel>
		</>
	);
}
