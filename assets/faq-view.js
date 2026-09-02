/**
 * Front-end Interactivity API store for 4WP FAQ List / Categories / Search.
 *
 * Do not initialize `category` / `search` here: client defaults overwrite
 * server state from wp_interactivity_state() (direct /page/term-slug/ loads).
 */
import { store, getContext } from '@wordpress/interactivity';

const categoryFromLocation = () => {
	const params = new URLSearchParams( window.location.search || '' );
	const fromQuery = ( params.get( 'faq_cat' ) || '' ).trim();
	if ( fromQuery ) {
		return fromQuery;
	}

	const currentPath = ( window.location.pathname || '' ).replace( /\/+$/, '' );
	const last = currentPath.split( '/' ).filter( Boolean ).pop() || '';
	if ( ! last ) {
		return '';
	}

	const links = document.querySelectorAll(
		'.forwp-faq-categories__link[data-faq-cat]'
	);
	let match = '';
	links.forEach( ( link ) => {
		const slug = link.getAttribute( 'data-faq-cat' ) || '';
		if ( slug && slug === last ) {
			match = slug;
		}
	} );
	return match;
};

const countVisibleCards = () => {
	const cards = document.querySelectorAll( '.forwp-faq-card' );
	let n = 0;
	cards.forEach( ( card ) => {
		if ( ! card.hasAttribute( 'hidden' ) ) {
			n += 1;
		}
	} );
	return n;
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
			if ( ( ctx.slug || '' ) === state.category ) {
				return true;
			}
			const ancestors = ( ctx.ancestors || '' ).split( /\s+/ ).filter( Boolean );
			return ancestors.indexOf( state.category ) !== -1;
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
			const ctx = getContext() || {};
			if ( ctx.seoUrls ) {
				return;
			}
			if ( event && typeof event.preventDefault === 'function' ) {
				event.preventDefault();
			}
			state.category = ctx.slug || '';
			window.requestAnimationFrame( () => {
				state.visibleCount = countVisibleCards();
			} );
		},
		setSearch( event ) {
			const target = event && event.target;
			state.search =
				target && typeof target.value === 'string' ? target.value : '';
			window.requestAnimationFrame( () => {
				state.visibleCount = countVisibleCards();
			} );
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

if ( typeof state.visibleCount !== 'number' ) {
	state.visibleCount = countVisibleCards();
}

window.addEventListener( 'popstate', () => {
	state.category = categoryFromLocation();
	state.visibleCount = countVisibleCards();
} );
