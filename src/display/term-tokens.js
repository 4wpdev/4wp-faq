import { useCallback, useMemo } from '@wordpress/element';

import { idsKey } from './sync-filters';

export const idsToNames = ( ids, terms ) =>
	( ids || [] )
		.map( ( id ) => {
			const num = Number( id );
			return terms.find( ( term ) => Number( term.id ) === num )?.name;
		} )
		.filter( Boolean );

export const namesToIds = ( names, terms ) =>
	( names || [] )
		.map( ( name ) => terms.find( ( term ) => term.name === name )?.id )
		.map( ( id ) => Number( id ) )
		.filter( ( id ) => id > 0 );

/**
 * Stable FormTokenField value + onChange that skips no-op setAttributes.
 *
 * @param {number[]} ids            Term IDs.
 * @param {Array}    terms          Registry terms.
 * @param {Function} setAttributes  Block setAttributes.
 * @param {string}   attributeName  includeTermIds | excludeTermIds.
 */
export const useTermTokenField = ( ids, terms, setAttributes, attributeName ) => {
	const valueKey = idsKey( ids );
	const value = useMemo(
		() => idsToNames( ids, terms ),
		// eslint-disable-next-line react-hooks/exhaustive-deps -- key captures id set
		[ valueKey, terms ]
	);

	const onChange = useCallback(
		( tokens ) => {
			const nextIds = namesToIds( tokens, terms );
			if ( idsKey( nextIds ) === valueKey ) {
				return;
			}
			setAttributes( { [ attributeName ]: nextIds } );
		},
		[ attributeName, setAttributes, terms, valueKey ]
	);

	return { value, onChange };
};
