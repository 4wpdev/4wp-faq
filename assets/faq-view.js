/**
 * Front-end Interactivity API store for 4WP FAQ List / Categories / Search.
 *
 * Do not initialize `category` / `search` here: client defaults overwrite
 * server state from wp_interactivity_state() (direct /page/term-slug/ loads).
 */
import { store, getContext } from '@wordpress/interactivity';

const categoryFromLocation = () => {
	const links = document.querySelectorAll(
		'.forwp-faq-categories__link[data-faq-cat]'
	);
	const currentPath = ( window.location.pathname || '' ).replace( /\/+$/, '' );
	let match = '';
	links.forEach( ( link ) => {
		let hrefPath = '';
		try {
			hrefPath = new URL( link.href, window.location.origin ).pathname.replace(
				/\/+$/,
				''
			);
		} catch ( err ) {
			hrefPath = ( link.getAttribute( 'href' ) || '' )
				.split( '?' )[ 0 ]
				.replace( /\/+$/, '' );
		}
		if ( hrefPath && hrefPath === currentPath ) {
			match = link.getAttribute( 'data-faq-cat' ) || '';
		}
	} );
	return match;
};

const { state } = store( 'forwp/faq', {
	state: {
		get isNavActive() {
			const ctx = getContext() || {};
			return ( state.category || '' ) === ( ctx.slug || '' );
		},
		get isGroupVisible() {
			const ctx = getContext() || {};
			if ( ! state.category ) {
				return true;
			}
			return ( ctx.slug || '' ) === state.category;
		},
		get isItemVisible() {
			const ctx = getContext() || {};
			const cats = ( ctx.cats || '' ).split( /\s+/ ).filter( Boolean );
			if ( state.category && cats.indexOf( state.category ) === -1 ) {
				return false;
			}
			const query = ( state.search || '' ).trim().toLowerCase();
			if ( query && ( ctx.searchText || '' ).indexOf( query ) === -1 ) {
				return false;
			}
			return true;
		},
	},
	actions: {
		selectCategory( event ) {
			if ( event && typeof event.preventDefault === 'function' ) {
				event.preventDefault();
			}
			const ctx = getContext() || {};
			state.category = ctx.slug || '';
			if (
				ctx.seoUrls &&
				ctx.url &&
				window.history &&
				window.history.pushState
			) {
				window.history.pushState(
					{ forwpFaqCat: state.category },
					'',
					ctx.url
				);
			}
		},
		setSearch( event ) {
			const target = event && event.target;
			state.search =
				target && typeof target.value === 'string' ? target.value : '';
		},
		preventSearchSubmit( event ) {
			if ( event && typeof event.preventDefault === 'function' ) {
				event.preventDefault();
			}
		},
	},
} );

if ( ! state.category ) {
	const fromUrl = categoryFromLocation();
	if ( fromUrl ) {
		state.category = fromUrl;
	}
}

if ( typeof state.search !== 'string' ) {
	state.search = '';
}

window.addEventListener( 'popstate', () => {
	state.category = categoryFromLocation();
} );
