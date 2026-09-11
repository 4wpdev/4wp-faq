/**
 * Front-end Interactivity API store for 4WP FAQ List / Categories / Search.
 *
 * Do not initialize `category` / `search` here: client defaults overwrite
 * server state from wp_interactivity_state() (direct /page/term-slug/ loads).
 */
import { store, getContext, withSyncEvent } from '@wordpress/interactivity';

const OPEN_BRANCHES_KEY = 'forwp-faq-cat-open';

const readOpenBranches = () => {
	try {
		const raw = window.localStorage.getItem( OPEN_BRANCHES_KEY );
		const parsed = raw ? JSON.parse( raw ) : {};
		if ( ! parsed || typeof parsed !== 'object' || Array.isArray( parsed ) ) {
			return {};
		}
		return parsed;
	} catch ( e ) {
		return {};
	}
};

const writeOpenBranches = ( map ) => {
	try {
		window.localStorage.setItem( OPEN_BRANCHES_KEY, JSON.stringify( map ) );
	} catch ( e ) {
		// Private mode / quota — keep the in-memory state either way.
	}
};

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
		openBranches: readOpenBranches(),
		get isNavActive() {
			const ctx = getContext() || {};
			return ( state.category || '' ) === ( ctx.slug || '' );
		},
		get navAriaCurrent() {
			return state.isNavActive ? 'page' : null;
		},
		get isBranchOpen() {
			const ctx = getContext() || {};
			if ( ! ctx.collapseChildren ) {
				return true;
			}
			const slug = ctx.slug || '';
			if (
				state.openBranches &&
				Object.prototype.hasOwnProperty.call( state.openBranches, slug )
			) {
				return !! state.openBranches[ slug ];
			}
			return !! ctx.forceOpen;
		},
		get isBranchHidden() {
			return ! state.isBranchOpen;
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
		toggleBranch: withSyncEvent( ( event ) => {
			if ( event && typeof event.preventDefault === 'function' ) {
				event.preventDefault();
			}
			if ( event && typeof event.stopPropagation === 'function' ) {
				event.stopPropagation();
			}
			const ctx = getContext() || {};
			if ( ! ctx.collapseChildren ) {
				return;
			}
			const slug = ctx.slug || '';
			if ( ! slug ) {
				return;
			}
			const next = {
				...( state.openBranches || {} ),
				[ slug ]: ! state.isBranchOpen,
			};
			state.openBranches = next;
			writeOpenBranches( next );
		} ),
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
