import { select, useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

const FILTER_BLOCKS = [ 'forwp/faq-list', 'forwp/faq-categories' ];

const collectFilterBlocks = ( blocks, acc = [] ) => {
	( blocks || [] ).forEach( ( block ) => {
		if ( FILTER_BLOCKS.includes( block.name ) ) {
			acc.push( block );
		}
		if ( block.innerBlocks && block.innerBlocks.length ) {
			collectFilterBlocks( block.innerBlocks, acc );
		}
	} );
	return acc;
};

export const idsKey = ( ids ) =>
	[ ...( ids || [] ) ]
		.map( ( id ) => Number( id ) )
		.filter( ( id ) => id > 0 )
		.sort( ( a, b ) => a - b )
		.join( ',' );

const normalizeIds = ( ids ) =>
	[ ...( ids || [] ) ]
		.map( ( id ) => Number( id ) )
		.filter( ( id ) => id > 0 );

const getOtherFilterBlocks = ( clientId ) =>
	collectFilterBlocks(
		select( 'core/block-editor' ).getBlocks()
	).filter( ( block ) => block.clientId !== clientId );

/**
 * Keep include/exclude in sync between 4WP FAQ List and 4WP FAQ Categories
 * even when they sit in different columns.
 *
 * @param {string}   clientId        Current block client ID.
 * @param {number[]} includeTermIds  Include filter.
 * @param {number[]} excludeTermIds  Exclude filter.
 */
export const useSyncCategoryFilters = (
	clientId,
	includeTermIds,
	excludeTermIds
) => {
	const { updateBlockAttributes } = useDispatch( 'core/block-editor' );
	const includeKey = idsKey( includeTermIds );
	const excludeKey = idsKey( excludeTermIds );
	const filterKey = `${ includeKey }|${ excludeKey }`;
	const lastPushedKey = useRef( '' );
	const didMount = useRef( false );

	// Re-run only when another filter block's filters change (adopt / echo guard).
	const siblingFilterKey = useSelect(
		( selector ) =>
			collectFilterBlocks( selector( 'core/block-editor' ).getBlocks() )
				.filter( ( block ) => block.clientId !== clientId )
				.map(
					( block ) =>
						`${ idsKey( block.attributes?.includeTermIds ) }:${ idsKey(
							block.attributes?.excludeTermIds
						) }`
				)
				.join( '|' ),
		[ clientId ]
	);

	useEffect( () => {
		const others = getOtherFilterBlocks( clientId );
		const nextInclude = normalizeIds( includeTermIds );
		const nextExclude = normalizeIds( excludeTermIds );

		if ( ! didMount.current ) {
			didMount.current = true;
			const source = others.find(
				( block ) =>
					( block.attributes?.includeTermIds || [] ).length > 0 ||
					( block.attributes?.excludeTermIds || [] ).length > 0
			);
			const selfEmpty = ! includeKey && ! excludeKey;

			if ( source && selfEmpty ) {
				const adoptedInclude = normalizeIds(
					source.attributes.includeTermIds
				);
				const adoptedExclude = normalizeIds(
					source.attributes.excludeTermIds
				);
				lastPushedKey.current = `${ idsKey( adoptedInclude ) }|${ idsKey(
					adoptedExclude
				) }`;
				updateBlockAttributes( clientId, {
					includeTermIds: adoptedInclude,
					excludeTermIds: adoptedExclude,
				} );
				return;
			}

			lastPushedKey.current = filterKey;
			return;
		}

		// Sibling wrote the same filters onto us — do not push back.
		if ( lastPushedKey.current === filterKey ) {
			return;
		}

		lastPushedKey.current = filterKey;

		others.forEach( ( block ) => {
			if (
				idsKey( block.attributes?.includeTermIds ) === includeKey &&
				idsKey( block.attributes?.excludeTermIds ) === excludeKey
			) {
				return;
			}

			updateBlockAttributes( block.clientId, {
				includeTermIds: nextInclude,
				excludeTermIds: nextExclude,
			} );
		} );
		// includeTermIds / excludeTermIds read from this render when keys change.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [
		clientId,
		includeKey,
		excludeKey,
		filterKey,
		siblingFilterKey,
		updateBlockAttributes,
	] );
};
