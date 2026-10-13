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

const normalizePath = ( path ) =>
	( path || '' ).replace( /\/+$/, '' ) || '/';

/**
 * Category from ?faq_cat= or a pretty /hub/term-slug/ URL.
 * Match the full nav href path — never the host page slug alone
 * (e.g. /4wp-faq/ must not activate category "4wp-faq").
 */
const categoryFromLocation = () => {
	const params = new URLSearchParams( window.location.search || '' );
	const fromQuery = ( params.get( 'faq_cat' ) || '' ).trim();
	if ( fromQuery ) {
		return fromQuery;
	}

	const currentPath = normalizePath( window.location.pathname || '' );
	const links = document.querySelectorAll(
		'.forwp-faq-categories__link[data-faq-cat]'
	);
	let match = '';
	links.forEach( ( link ) => {
		const slug = ( link.getAttribute( 'data-faq-cat' ) || '' ).trim();
		if ( ! slug || match ) {
			return;
		}
		const href = link.getAttribute( 'href' ) || '';
		if ( ! href || href === '#' ) {
			return;
		}
		try {
			const linkPath = normalizePath(
				new URL( href, window.location.origin ).pathname || ''
			);
			if ( linkPath === currentPath ) {
				match = slug;
			}
		} catch ( e ) {
			// Ignore malformed hrefs.
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

// Without a search the badge shows the category nav totals, not the cards on screen (preview limit).
const currentCount = () => {
	if ( ! String( state.search || '' ).trim() ) {
		const total = state.totals?.[ state.category || '' ];
		if ( typeof total === 'number' ) {
			return total;
		}
	}
	return countVisibleCards();
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
		get isMenuOpen() {
			return !! state.menuOpen;
		},
		get hasNoResults() {
			return (
				!! String( state.search || '' ).trim() && state.visibleCount === 0
			);
		},
		get isGroupVisible() {
			const ctx = getContext() || {};
			if ( state.category ) {
				const slug = ctx.slug || '';
				const ancestors = ( ctx.ancestors || '' )
					.split( /\s+/ )
					.filter( Boolean );
				const isSelf = slug === state.category;
				const isDescendant =
					ancestors.indexOf( state.category ) !== -1;
				// Keep parents of the active category visible (nested list HTML).
				const isAncestor =
					( state.categoryAncestors || [] ).indexOf( slug ) !== -1;
				if ( ! isSelf && ! isDescendant && ! isAncestor ) {
					return false;
				}
			}
			const query = ( state.search || '' ).trim().toLowerCase();
			if ( query && ( ctx.searchText || '' ).indexOf( query ) === -1 ) {
				return false;
			}
			return true;
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
		toggleMenu( event ) {
			if ( event && typeof event.preventDefault === 'function' ) {
				event.preventDefault();
			}
			state.menuOpen = ! state.menuOpen;
		},
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
			applyCategory( ctx.slug || '', ctx.ancestors || '' );
			window.requestAnimationFrame( () => {
				state.visibleCount = currentCount();
			} );
		},
		setSearch( event ) {
			const target = event && event.target;
			state.search =
				target && typeof target.value === 'string' ? target.value : '';
			window.requestAnimationFrame( () => {
				state.visibleCount = currentCount();
			} );
		},
		preventSearchSubmit( event ) {
			if ( event && typeof event.preventDefault === 'function' ) {
				event.preventDefault();
			}
		},
	},
} );

const ancestorsForSlug = ( slug ) => {
	if ( ! slug ) {
		return [];
	}
	const groups = document.querySelectorAll( '.forwp-faq-list__group' );
	for ( let i = 0; i < groups.length; i++ ) {
		const raw = groups[ i ].getAttribute( 'data-wp-context' );
		if ( ! raw ) {
			continue;
		}
		try {
			const ctx = JSON.parse( raw );
			if ( ( ctx.slug || '' ) === slug ) {
				return String( ctx.ancestors || '' )
					.split( /\s+/ )
					.filter( Boolean );
			}
		} catch ( e ) {
			// Ignore malformed context.
		}
	}
	return [];
};

const applyCategory = ( slug, ancestorsRaw ) => {
	state.category = slug || '';
	if ( ! slug ) {
		state.categoryAncestors = [];
		return;
	}
	if ( typeof ancestorsRaw === 'string' && ancestorsRaw.trim() ) {
		state.categoryAncestors = ancestorsRaw.split( /\s+/ ).filter( Boolean );
		return;
	}
	state.categoryAncestors = ancestorsForSlug( slug );
};

if ( ! state.category ) {
	const fromUrl = categoryFromLocation();
	if ( fromUrl ) {
		applyCategory( fromUrl );
	}
} else if ( ! Array.isArray( state.categoryAncestors ) ) {
	state.categoryAncestors = ancestorsForSlug( state.category );
}

if ( typeof state.search !== 'string' ) {
	state.search = '';
}

if ( typeof state.visibleCount !== 'number' ) {
	state.visibleCount = currentCount();
}

window.addEventListener( 'popstate', () => {
	applyCategory( categoryFromLocation() );
	state.visibleCount = currentCount();
} );
