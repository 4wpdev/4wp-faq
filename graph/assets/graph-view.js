/**
 * 4WP Graph view — renders the neutral graph contract with Cytoscape.js.
 * Domain-agnostic: works on node roles (group / item / context) and Analyzer flags.
 * Texts come from the owning plugin via window.forwpGraphConfigs[source].i18n.
 */
( function () {
	'use strict';

	var ROLE_COLORS = { group: '#3858e9', item: '#50575e', context: '#d63638' };
	var ISSUE_COLOR = '#dba617';

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			var value = attrs[ key ];
			if ( key === 'text' ) {
				node.textContent = value;
			} else if ( key === 'className' ) {
				node.className = value;
			} else if ( key.indexOf( 'on' ) === 0 ) {
				node.addEventListener( key.slice( 2 ), value );
			} else {
				node.setAttribute( key, value );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
			}
		} );
		return node;
	}

	function fmt( template, value ) {
		return String( template || '' ).replace( '%d', value ).replace( '%s', value );
	}

	function GraphApp( root, config ) {
		this.root = root;
		this.config = config;
		this.t = config.i18n || {};
		this.graph = null;
		this.cy = null;
		this.slice = 'all';
		this.layout = 'cose';
		this.group = '';
		this.issuesBox = null;
		this.build();
		this.load();
	}

	GraphApp.prototype.text = function ( key, fallback ) {
		return this.t[ key ] || fallback || key;
	};

	GraphApp.prototype.button = function ( label, onclick, extra ) {
		return el( 'button', { type: 'button', className: 'button' + ( extra ? ' ' + extra : '' ), text: label, onclick: onclick } );
	};

	GraphApp.prototype.build = function () {
		var self = this;

		this.status = el( 'div', { className: 'forwp-graph__status', role: 'status' } );
		this.tiles = el( 'div', { className: 'forwp-graph__tiles' } );

		var slices = [
			[ 'all', this.text( 'sliceAll', 'Everything' ) ],
			[ 'groups', this.text( 'sliceGroups', 'Groups and items' ) ],
			[ 'context', this.text( 'sliceContext', 'Items and usage' ) ],
			[ 'issues', this.text( 'sliceIssues', 'Issues' ) ],
		];
		this.sliceButtons = {};
		var sliceBar = el( 'div', { className: 'forwp-graph__segmented', role: 'group', 'aria-label': this.text( 'slice', 'Slice' ) } );
		slices.forEach( function ( slice ) {
			var button = self.button( slice[ 1 ], function () {
				self.setSlice( slice[ 0 ] );
			} );
			self.sliceButtons[ slice[ 0 ] ] = button;
			sliceBar.appendChild( button );
		} );
		this.syncSliceButtons();

		this.groupSelect = el( 'select', {
			className: 'forwp-graph__select',
			'aria-label': this.text( 'groupFilter', 'Group' ),
			onchange: function () {
				self.group = self.groupSelect.value;
				self.load();
			},
		} );

		this.layoutSelect = el(
			'select',
			{
				className: 'forwp-graph__select',
				'aria-label': this.text( 'layout', 'Layout' ),
				onchange: function () {
					self.layout = self.layoutSelect.value;
					self.runLayout();
				},
			},
			[
				el( 'option', { value: 'cose', text: this.text( 'layoutClusters', 'Clusters' ) } ),
				el( 'option', { value: 'concentric', text: this.text( 'layoutRings', 'Rings by links' ) } ),
				el( 'option', { value: 'breadthfirst', text: this.text( 'layoutTree', 'Tree' ) } ),
				el( 'option', { value: 'grid', text: this.text( 'layoutGrid', 'Grid' ) } ),
			]
		);

		this.search = el( 'input', {
			type: 'search',
			className: 'forwp-graph__search',
			placeholder: this.text( 'search', 'Search' ),
			'aria-label': this.text( 'search', 'Search' ),
			oninput: function () {
				self.applySearch( self.search.value );
			},
		} );

		var fit = this.button( this.text( 'fit', 'Fit' ), function () {
			if ( self.cy ) {
				self.cy.animate( { fit: { eles: self.cy.elements( ':visible' ), padding: 30 } }, { duration: 250 } );
			}
		} );
		var refresh = this.button( this.text( 'refresh', 'Refresh' ), function () {
			self.load( true );
		} );

		var toolbar = el( 'div', { className: 'forwp-graph__toolbar' }, [
			sliceBar,
			el( 'div', { className: 'forwp-graph__toolbar-right' }, [ this.groupSelect, this.layoutSelect, this.search, fit, refresh ] ),
		] );

		this.canvas = el( 'div', { className: 'forwp-graph__canvas', role: 'img', 'aria-label': this.text( 'canvasLabel', 'Relations graph' ) } );
		this.legend = el( 'div', { className: 'forwp-graph__legend' } );
		this.panel = el( 'aside', { className: 'forwp-graph__panel', 'aria-live': 'polite' } );
		this.charts = el( 'div', { className: 'forwp-graph__charts' } );

		this.root.appendChild(
			el( 'div', { className: 'forwp-graph' }, [
				this.tiles,
				toolbar,
				this.status,
				el( 'div', { className: 'forwp-graph__stage' }, [ el( 'div', { className: 'forwp-graph__main' }, [ this.canvas, this.legend ] ), this.panel ] ),
				this.charts,
			] )
		);
		this.renderPanel( null );
	};

	GraphApp.prototype.load = function ( force ) {
		var self = this;
		var url = new URL( this.config.endpoint );
		if ( this.group && this.config.groupArg ) {
			url.searchParams.set( this.config.groupArg, this.group.split( ':' ).pop() );
		}
		if ( force ) {
			url.searchParams.set( '_', String( Date.now() ) );
		}

		this.status.textContent = this.text( 'loading', 'Building graph…' );
		this.root.classList.add( 'is-loading' );

		return window
			.fetch( url.toString(), { credentials: 'same-origin', headers: { 'X-WP-Nonce': this.config.nonce } } )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					if ( ! response.ok ) {
						throw new Error( ( body && body.message ) || response.statusText );
					}
					return body;
				} );
			} )
			.then( function ( graph ) {
				self.graph = graph;
				self.status.textContent = '';
				self.renderGroups();
				self.renderTiles();
				self.renderGraph();
				self.renderCharts();
			} )
			.catch( function ( error ) {
				self.status.textContent = self.text( 'error', 'Could not build the graph.' ) + ' ' + error.message;
			} )
			.finally( function () {
				self.root.classList.remove( 'is-loading' );
			} );
	};

	GraphApp.prototype.roleOf = function ( type ) {
		var def = this.graph && this.graph.types && this.graph.types.nodes ? this.graph.types.nodes[ type ] : null;
		return def && def.role ? def.role : 'item';
	};

	GraphApp.prototype.typeLabel = function ( type ) {
		var map = this.graph && this.graph.types ? this.graph.types.nodes : null;
		return map && map[ type ] ? map[ type ].label : type;
	};

	GraphApp.prototype.colorOf = function ( type ) {
		var def = this.graph.types.nodes[ type ];
		return def && def.color ? def.color : ROLE_COLORS[ this.roleOf( type ) ] || ROLE_COLORS.item;
	};

	GraphApp.prototype.nodesByRole = function ( role ) {
		var self = this;
		return this.graph.nodes.filter( function ( node ) {
			return self.roleOf( node.type ) === role;
		} );
	};

	GraphApp.prototype.renderGroups = function () {
		if ( this.groupsRendered ) {
			return;
		}
		this.groupsRendered = true;

		var self = this;
		var groups = this.nodesByRole( 'group' ).slice().sort( function ( a, b ) {
			return a.label.localeCompare( b.label );
		} );
		this.groupSelect.appendChild( el( 'option', { value: '', text: this.text( 'allGroups', 'All groups' ) } ) );
		groups.forEach( function ( group ) {
			self.groupSelect.appendChild( el( 'option', { value: group.id, text: group.label } ) );
		} );
		this.groupSelect.hidden = ! this.config.groupArg || ! groups.length;
	};

	GraphApp.prototype.renderTiles = function () {
		var self = this;
		var stats = this.graph.stats || {};
		var totals = stats.totals || {};
		var components = stats.components || {};

		var tiles = [
			[ this.nodesByRole( 'item' ).length, this.text( 'items', 'Items' ), '', 'item' ],
			[ this.nodesByRole( 'group' ).length, this.text( 'groups', 'Groups' ), '', 'group' ],
			[ this.nodesByRole( 'context' ).length, this.text( 'contexts', 'Used on' ), '', 'context' ],
			[ stats.edges || 0, this.text( 'links', 'Links' ), '', '' ],
			[ totals.weak_groups || 0, this.text( 'weakGroups', 'Weak groups' ), this.text( 'weakGroupsHint', '' ), 'issue' ],
			[ totals.no_group || 0, this.text( 'noGroup', 'Without group' ), '', 'issue' ],
			[ totals.no_context || 0, this.text( 'noContext', 'Not used' ), '', 'issue' ],
			[ components.clusters || 0, this.text( 'clusters', 'Clusters' ), fmt( this.text( 'largest', 'Largest: %d' ), components.largest || 0 ), '' ],
		];

		this.tiles.textContent = '';
		tiles.forEach( function ( tile ) {
			var issue = tile[ 3 ] === 'issue';
			var node = el( issue ? 'button' : 'div', { className: 'forwp-graph__tile' + ( issue && tile[ 0 ] > 0 ? ' is-issue' : '' ) }, [
				tile[ 3 ] && ! issue ? el( 'span', { className: 'forwp-graph__swatch shape-' + tile[ 3 ], style: 'background:' + ROLE_COLORS[ tile[ 3 ] ] } ) : null,
				el( 'span', { className: 'forwp-graph__tile-value', text: String( tile[ 0 ] ) } ),
				el( 'span', { className: 'forwp-graph__tile-label', text: tile[ 1 ] } ),
				tile[ 2 ] ? el( 'span', { className: 'forwp-graph__tile-hint', text: tile[ 2 ] } ) : null,
			] );
			if ( issue ) {
				node.type = 'button';
				node.addEventListener( 'click', function () {
					self.setSlice( 'issues' );
				} );
			}
			self.tiles.appendChild( node );
		} );
	};

	GraphApp.prototype.renderGraph = function () {
		var self = this;
		var maxDegree = 1;
		var elements = [];

		this.graph.nodes.forEach( function ( node ) {
			maxDegree = Math.max( maxDegree, node.degree || 0 );
		} );
		this.graph.nodes.forEach( function ( node ) {
			var flags = node.flags || [];
			var role = self.roleOf( node.type );
			var classes = [ 'role-' + role ].concat(
				flags.map( function ( flag ) {
					return 'flag-' + flag;
				} )
			);
			if ( flags.some( function ( flag ) { return flag !== 'hub'; } ) ) {
				classes.push( 'is-issue' );
			}
			elements.push( {
				group: 'nodes',
				data: { id: node.id, label: node.label, type: node.type, url: node.url, degree: node.degree || 0, color: self.colorOf( node.type ), flags: flags },
				classes: classes.join( ' ' ),
			} );
		} );
		this.graph.edges.forEach( function ( edge ) {
			elements.push( { group: 'edges', data: { id: edge.id, source: edge.source, target: edge.target, type: edge.type }, classes: 'edge-' + edge.type } );
		} );

		if ( this.cy ) {
			this.cy.destroy();
		}

		var size = 'mapData(degree, 0, ' + maxDegree + ', 10, 46)';
		this.cy = window.cytoscape( {
			container: this.canvas,
			elements: elements,
			wheelSensitivity: 0.25,
			minZoom: 0.1,
			maxZoom: 3,
			style: [
				{
					selector: 'node',
					style: {
						'background-color': 'data(color)',
						width: size,
						height: size,
						label: '',
						'font-size': 11,
						'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
						color: '#1d2327',
						'text-valign': 'bottom',
						'text-margin-y': 4,
						'text-wrap': 'ellipsis',
						'text-max-width': 160,
						'text-background-color': '#ffffff',
						'text-background-opacity': 0.85,
						'text-background-padding': 2,
					},
				},
				{ selector: 'node.role-group', style: { shape: 'round-rectangle', label: 'data(label)', 'font-weight': 600 } },
				{ selector: 'node.role-context', style: { shape: 'diamond' } },
				{ selector: 'node.role-context.flag-hub', style: { label: 'data(label)' } },
				{ selector: 'node.is-issue', style: { 'border-width': 3, 'border-color': ISSUE_COLOR } },
				{ selector: 'edge', style: { width: 1, 'line-color': '#c3c4c7', 'curve-style': 'haystack', opacity: 0.7 } },
				{ selector: 'edge.edge-used_in', style: { 'line-color': '#f0b8b9' } },
				{ selector: 'edge.edge-child_of', style: { width: 2, 'line-color': '#a7b6f5' } },
				{ selector: '.is-faded', style: { opacity: 0.12, 'text-opacity': 0 } },
				{ selector: 'node.is-neighbor', style: { label: 'data(label)' } },
				{ selector: 'node.is-match', style: { 'border-width': 4, 'border-color': '#00a32a', label: 'data(label)' } },
				{ selector: 'node:selected', style: { 'border-width': 4, 'border-color': '#1d2327', label: 'data(label)', 'z-index': 10 } },
			],
		} );

		this.cy.on( 'tap', 'node', function ( event ) {
			self.focusNode( event.target );
		} );
		this.cy.on( 'tap', function ( event ) {
			if ( event.target === self.cy ) {
				self.clearFocus();
			}
		} );
		this.cy.on( 'dbltap', 'node', function ( event ) {
			var url = event.target.data( 'url' );
			if ( url ) {
				window.location.href = url;
			}
		} );

		this.renderLegend();
		this.applySlice();
		this.applySearch( this.search.value );
	};

	GraphApp.prototype.runLayout = function () {
		if ( ! this.cy ) {
			return;
		}
		var options = { name: this.layout, animate: false, fit: true, padding: 30 };
		if ( this.layout === 'cose' ) {
			options.nodeRepulsion = function () {
				return 9000;
			};
			options.idealEdgeLength = function () {
				return 60;
			};
			options.numIter = 1200;
		} else if ( this.layout === 'concentric' ) {
			options.concentric = function ( node ) {
				return node.data( 'degree' );
			};
			options.levelWidth = function () {
				return 2;
			};
			options.minNodeSpacing = 6;
		} else if ( this.layout === 'breadthfirst' ) {
			options.directed = false;
			options.spacingFactor = 0.9;
			options.roots = this.cy.nodes( '.role-group:visible' );
		}
		this.cy.elements( ':visible' ).layout( options ).run();
	};

	GraphApp.prototype.syncSliceButtons = function () {
		var self = this;
		Object.keys( this.sliceButtons ).forEach( function ( key ) {
			var active = key === self.slice;
			self.sliceButtons[ key ].setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			self.sliceButtons[ key ].classList.toggle( 'button-primary', active );
		} );
	};

	GraphApp.prototype.setSlice = function ( slice ) {
		this.slice = slice;
		this.syncSliceButtons();
		this.applySlice();
	};

	GraphApp.prototype.applySlice = function () {
		if ( ! this.cy ) {
			return;
		}
		var cy = this.cy;
		var slice = this.slice;

		cy.batch( function () {
			cy.elements().style( 'display', 'element' );
			if ( slice === 'groups' ) {
				cy.nodes( '.role-context' ).style( 'display', 'none' );
			} else if ( slice === 'context' ) {
				cy.nodes( '.role-group' ).style( 'display', 'none' );
				cy.nodes( '.role-item' )
					.filter( function ( node ) {
						return node.neighborhood( 'node.role-context' ).length === 0;
					} )
					.style( 'display', 'none' );
			} else if ( slice === 'issues' ) {
				var issues = cy.nodes( '.is-issue' );
				cy.nodes().difference( issues.union( issues.neighborhood( 'node.role-group' ) ) ).style( 'display', 'none' );
			}
		} );

		this.buildIssuesBox();
		this.clearFocus();
		this.runLayout();
	};

	GraphApp.prototype.applySearch = function ( query ) {
		if ( ! this.cy ) {
			return;
		}
		var q = String( query || '' ).trim().toLowerCase();
		this.cy.nodes().removeClass( 'is-match' );
		if ( q.length < 2 ) {
			return;
		}
		var matches = this.cy.nodes( ':visible' ).filter( function ( node ) {
			return String( node.data( 'label' ) ).toLowerCase().indexOf( q ) !== -1;
		} );
		matches.addClass( 'is-match' );
		if ( matches.length ) {
			this.cy.animate( { fit: { eles: matches.closedNeighborhood(), padding: 60 } }, { duration: 250 } );
		}
	};

	GraphApp.prototype.focusNode = function ( node ) {
		var cy = this.cy;
		var hood = node.closedNeighborhood();
		cy.batch( function () {
			cy.elements().addClass( 'is-faded' ).removeClass( 'is-neighbor' );
			hood.removeClass( 'is-faded' );
			hood.nodes().not( node ).addClass( 'is-neighbor' );
		} );
		cy.$( ':selected' ).unselect();
		node.select();
		this.renderPanel( node );
	};

	GraphApp.prototype.clearFocus = function () {
		if ( this.cy ) {
			this.cy.elements().removeClass( 'is-faded is-neighbor' );
			this.cy.$( ':selected' ).unselect();
		}
		this.renderPanel( null );
	};

	GraphApp.prototype.flagLabel = function ( flag ) {
		var map = {
			hub: this.text( 'flagHub', 'Hub' ),
			weak: this.text( 'flagWeak', 'Weak group' ),
			no_group: this.text( 'flagNoGroup', 'Without group' ),
			no_context: this.text( 'flagNoContext', 'Not used' ),
			orphan: this.text( 'flagOrphan', 'Orphan' ),
		};
		return map[ flag ] || flag;
	};

	GraphApp.prototype.nodeList = function ( nodes, limit ) {
		var self = this;
		var list = el( 'ul', { className: 'forwp-graph__list' } );
		nodes.slice( 0, limit ).forEach( function ( node ) {
			list.appendChild(
				el( 'li', {}, [
					el( 'button', {
						type: 'button',
						className: 'button-link',
						text: node.data( 'label' ),
						onclick: function () {
							self.focusNode( node );
						},
					} ),
				] )
			);
		} );
		return list;
	};

	GraphApp.prototype.renderPanel = function ( node ) {
		var self = this;
		this.panel.textContent = '';

		if ( ! node ) {
			this.panel.appendChild( el( 'p', { className: 'forwp-graph__hint', text: this.text( 'panelHint', 'Click a node to see its links. Double-click opens it.' ) } ) );
			if ( this.issuesBox ) {
				this.panel.appendChild( this.issuesBox );
			}
			return;
		}

		var data = node.data();
		this.panel.appendChild(
			el( 'div', { className: 'forwp-graph__panel-head' }, [
				el( 'span', { className: 'forwp-graph__badge', style: 'background:' + data.color, text: this.typeLabel( data.type ) } ),
				el( 'h3', { text: data.label } ),
				el( 'p', { className: 'forwp-graph__meta', text: fmt( this.text( 'linksCount', '%d links' ), data.degree ) } ),
			] )
		);

		if ( data.flags && data.flags.length ) {
			var flags = el( 'div', { className: 'forwp-graph__flags' } );
			data.flags.forEach( function ( flag ) {
				flags.appendChild( el( 'span', { className: 'forwp-graph__flag flag-' + flag, text: self.flagLabel( flag ) } ) );
			} );
			this.panel.appendChild( flags );
		}

		if ( data.url ) {
			this.panel.appendChild( el( 'p', {}, [ el( 'a', { className: 'button button-secondary', href: data.url, text: this.text( 'edit', 'Edit' ) } ) ] ) );
		}

		var byType = {};
		node.neighborhood( 'node' ).forEach( function ( other ) {
			var type = other.data( 'type' );
			( byType[ type ] = byType[ type ] || [] ).push( other );
		} );
		Object.keys( byType ).forEach( function ( type ) {
			var nodes = byType[ type ].sort( function ( a, b ) {
				return b.data( 'degree' ) - a.data( 'degree' );
			} );
			self.panel.appendChild( el( 'h4', { text: self.typeLabel( type ) + ' · ' + nodes.length } ) );
			self.panel.appendChild( self.nodeList( nodes, 30 ) );
		} );
	};

	GraphApp.prototype.buildIssuesBox = function () {
		var self = this;
		this.issuesBox = null;
		if ( this.slice !== 'issues' || ! this.cy ) {
			return;
		}
		var box = el( 'div', { className: 'forwp-graph__issues' } );
		[ 'weak', 'no_group', 'no_context', 'orphan' ].forEach( function ( flag ) {
			var nodes = self.cy.nodes( '.flag-' + flag ).toArray();
			if ( nodes.length ) {
				box.appendChild( el( 'h4', { text: self.flagLabel( flag ) + ' · ' + nodes.length } ) );
				box.appendChild( self.nodeList( nodes, 40 ) );
			}
		} );
		this.issuesBox = box;
	};

	GraphApp.prototype.renderLegend = function () {
		var self = this;
		this.legend.textContent = '';
		Object.keys( this.graph.types.nodes || {} ).forEach( function ( type ) {
			self.legend.appendChild(
				el( 'span', { className: 'forwp-graph__legend-item' }, [
					el( 'span', { className: 'forwp-graph__swatch shape-' + self.roleOf( type ), style: 'background:' + self.colorOf( type ) } ),
					self.typeLabel( type ),
				] )
			);
		} );
		this.legend.appendChild(
			el( 'span', { className: 'forwp-graph__legend-item' }, [
				el( 'span', { className: 'forwp-graph__swatch is-ring', style: 'border-color:' + ISSUE_COLOR } ),
				this.text( 'legendIssue', 'Needs attention' ),
			] )
		);
		this.legend.appendChild( el( 'span', { className: 'forwp-graph__legend-note', text: this.text( 'legendSize', 'Size = number of links' ) } ) );
	};

	GraphApp.prototype.renderCharts = function () {
		this.charts.textContent = '';

		var groups = this.nodesByRole( 'group' )
			.map( function ( node ) {
				return { id: node.id, label: node.label, value: node.members || 0, issue: ( node.flags || [] ).indexOf( 'weak' ) !== -1 };
			} )
			.sort( function ( a, b ) {
				return b.value - a.value;
			} );
		var contexts = this.nodesByRole( 'context' )
			.map( function ( node ) {
				return { id: node.id, label: node.label, value: node.degree || 0, issue: false };
			} )
			.sort( function ( a, b ) {
				return b.value - a.value;
			} )
			.slice( 0, 15 );

		var coverage = this.coverageChart();
		if ( coverage ) {
			this.charts.appendChild( coverage );
		}
		if ( groups.length ) {
			this.charts.appendChild( this.barChart( this.text( 'chartGroups', 'Items per group' ), groups, ROLE_COLORS.group ) );
		}
		if ( contexts.length ) {
			this.charts.appendChild( this.barChart( this.text( 'chartContexts', 'Most linked pages' ), contexts, ROLE_COLORS.context ) );
		}
	};

	GraphApp.prototype.coverageChart = function () {
		var items = this.nodesByRole( 'item' );
		if ( ! items.length ) {
			return null;
		}
		var counts = { both: 0, group: 0, context: 0, none: 0 };
		items.forEach( function ( node ) {
			var flags = node.flags || [];
			var grouped = flags.indexOf( 'no_group' ) === -1;
			var used = flags.indexOf( 'no_context' ) === -1;
			counts[ grouped && used ? 'both' : grouped ? 'group' : used ? 'context' : 'none' ]++;
		} );
		var parts = [
			[ 'both', this.text( 'coverageBoth', 'Grouped and used' ), '#3858e9' ],
			[ 'group', this.text( 'coverageGroup', 'Grouped, not used' ), '#9fb1f4' ],
			[ 'context', this.text( 'coverageContext', 'Used, no group' ), ISSUE_COLOR ],
			[ 'none', this.text( 'coverageNone', 'Neither' ), '#d63638' ],
		];
		var title = this.text( 'chartCoverage', 'Coverage' );
		var bar = el( 'div', { className: 'forwp-graph__stack', role: 'img', 'aria-label': title } );
		var legend = el( 'ul', { className: 'forwp-graph__stack-legend' } );
		parts.forEach( function ( part ) {
			var value = counts[ part[ 0 ] ];
			if ( value ) {
				bar.appendChild( el( 'span', { style: 'flex:' + value + ';background:' + part[ 2 ], title: part[ 1 ] + ': ' + value } ) );
			}
			legend.appendChild(
				el( 'li', {}, [
					el( 'span', { className: 'forwp-graph__swatch', style: 'background:' + part[ 2 ] } ),
					part[ 1 ] + ' — ' + value + ' (' + Math.round( ( value / items.length ) * 100 ) + '%)',
				] )
			);
		} );
		return el( 'section', { className: 'forwp-graph__chart forwp-graph__chart--wide' }, [ el( 'h3', { text: title } ), bar, legend ] );
	};

	GraphApp.prototype.barChart = function ( title, rows, color ) {
		var self = this;
		var max = rows.reduce( function ( m, row ) {
			return Math.max( m, row.value );
		}, 1 );
		var list = el( 'ol', { className: 'forwp-graph__bars' } );
		rows.forEach( function ( row ) {
			list.appendChild(
				el( 'li', { className: row.issue ? 'is-issue' : '' }, [
					el( 'button', {
						type: 'button',
						className: 'forwp-graph__bar-label button-link',
						text: row.label,
						title: row.label,
						onclick: function () {
							self.reveal( row.id );
						},
					} ),
					el( 'span', { className: 'forwp-graph__bar-track' }, [
						el( 'span', {
							className: 'forwp-graph__bar-fill',
							style: 'width:' + Math.max( 2, ( row.value / max ) * 100 ) + '%;background:' + ( row.issue ? ISSUE_COLOR : color ),
						} ),
					] ),
					el( 'span', { className: 'forwp-graph__bar-value', text: String( row.value ) } ),
				] )
			);
		} );
		return el( 'section', { className: 'forwp-graph__chart' }, [ el( 'h3', { text: title } ), list ] );
	};

	GraphApp.prototype.reveal = function ( id ) {
		var node = this.cy ? this.cy.getElementById( id ) : null;
		if ( ! node || ! node.length ) {
			return;
		}
		if ( node.style( 'display' ) === 'none' ) {
			this.setSlice( 'all' );
		}
		this.focusNode( node );
		this.canvas.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		this.cy.animate( { fit: { eles: node.closedNeighborhood(), padding: 60 } }, { duration: 250 } );
	};

	function boot() {
		if ( typeof window.cytoscape !== 'function' ) {
			return;
		}
		var configs = window.forwpGraphConfigs || {};
		document.querySelectorAll( '.forwp-graph-app[data-forwp-graph-source]' ).forEach( function ( root ) {
			var config = configs[ root.getAttribute( 'data-forwp-graph-source' ) ];
			if ( config && ! root.__forwpGraph ) {
				root.__forwpGraph = new GraphApp( root, config );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
