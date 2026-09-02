/**
 * Drag-and-drop FAQ category order on edit-tags.php.
 */
( function ( $ ) {
	'use strict';

	const config = window.forwpFaqTermOrder || {};

	$( function () {
		const $tbody = $( 'table.wp-list-table tbody' );
		if ( ! $tbody.length || typeof $tbody.sortable !== 'function' ) {
			return;
		}

		$tbody.sortable( {
			items: '> tr.iedit',
			cursor: 'move',
			axis: 'y',
			placeholder: 'forwp-faq-term-order-placeholder',
			helper: function ( event, ui ) {
				ui.children().each( function () {
					$( this ).width( $( this ).width() );
				} );
				return ui;
			},
			update: function () {
				const order = [];
				$tbody.children( 'tr.iedit' ).each( function () {
					const id = String( this.id || '' ).replace( /^tag-/, '' );
					if ( id ) {
						order.push( id );
					}
				} );

				$.post( config.ajaxUrl, {
					action: 'forwp_faq_term_order',
					nonce: config.nonce,
					taxonomy: config.taxonomy,
					order: order,
				} );
			},
		} );
	} );
} )( jQuery );
