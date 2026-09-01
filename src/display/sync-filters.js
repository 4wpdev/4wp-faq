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

const idsKey = ( ids ) =>
	[ ...( ids || [] ) ]
		.map( ( id ) => Number( id ) )
		.filter( ( id ) => id > 0 )
		.sort( ( a, b ) => a - b )
		.join( ',' );

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
	const siblingKey = useSelect(
		( selector ) =>
			collectFilterBlocks( selector( 'core/block-editor' ).getBlocks() )
				.filter( ( block ) => block.clientId !== clientId )
				.map(
					( block ) =>
						`${ block.clientId }:${ idsKey(
							block.attributes?.includeTermIds
						) }:${ idsKey( block.attributes?.excludeTermIds ) }`
				)
				.join( '|' ),
		[ clientId ]
	);
	const didMount = useRef( false );

	useEffect( () => {
		const others = getOtherFilterBlocks( clientId );

		if ( ! didMount.current ) {
			didMount.current = true;
			const source = others.find(
				( block ) =>
					( block.attributes?.includeTermIds || [] ).length > 0 ||
					( block.attributes?.excludeTermIds || [] ).length > 0
			);
			const selfEmpty =
				( includeTermIds || [] ).length === 0 &&
				( excludeTermIds || [] ).length === 0;

			if ( source && selfEmpty ) {
				updateBlockAttributes( clientId, {
					includeTermIds: source.attributes.includeTermIds || [],
					excludeTermIds: source.attributes.excludeTermIds || [],
				} );
			}
			return;
		}

		const nextInclude = includeTermIds || [];
		const nextExclude = excludeTermIds || [];

		others.forEach( ( block ) => {
			if (
				idsKey( block.attributes?.includeTermIds ) === idsKey( nextInclude ) &&
				idsKey( block.attributes?.excludeTermIds ) === idsKey( nextExclude )
			) {
				return;
			}

			updateBlockAttributes( block.clientId, {
				includeTermIds: nextInclude,
				excludeTermIds: nextExclude,
			} );
		} );
	}, [
		clientId,
		includeTermIds,
		excludeTermIds,
		siblingKey,
		updateBlockAttributes,
	] );
};
