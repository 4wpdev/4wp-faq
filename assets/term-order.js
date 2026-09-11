/**
 * Drag-and-drop FAQ category order on edit-tags.php.
 *
 * Term rows use class "level-N" (not "iedit" like posts).
 * Dragging a parent moves its descendant rows with it.
 */
( function ( $ ) {
	'use strict';

	const config = window.forwpFaqTermOrder || {};
	const i18n = config.i18n || {};
	const itemSelector = '> tr[id^="tag-"]';

	function termIdFromRow( row ) {
		return String( row.id || '' ).replace( /^tag-/, '' );
	}

	function rowLevel( $row ) {
		const match = String( $row.attr( 'class' ) || '' ).match(
			/\blevel-(\d+)\b/
		);
		return match ? parseInt( match[ 1 ], 10 ) : 0;
	}

	function descendantRows( $row ) {
		const level = rowLevel( $row );
		const nodes = [];
		$row.nextAll( 'tr[id^="tag-"]' ).each( function () {
			if ( rowLevel( $( this ) ) <= level ) {
				return false;
			}
			nodes.push( this );
			return true;
		} );
		return $( nodes );
	}

	function attachDescendants( $row ) {
		const $kids = $row.data( 'forwpKids' );
		if ( $kids && $kids.length ) {
			$row.after( $kids );
		}
		$row.removeData( 'forwpKids' );
	}

	function collectOrder( $tbody ) {
		const order = [];
		$tbody.children( 'tr[id^="tag-"]' ).each( function () {
			const id = termIdFromRow( this );
			if ( id ) {
				order.push( id );
			}
		} );
		return order;
	}

	function ensureNotice() {
		let $notice = $( '#forwp-faq-term-order-notice' );
		if ( $notice.length ) {
			return $notice;
		}

		$notice = $(
			'<div id="forwp-faq-term-order-notice" class="notice notice-info is-dismissible" style="display:none"><p></p></div>'
		);
		const $wrap = $( '.wrap > h1, .wrap > h2' ).first();
		if ( $wrap.length ) {
			$wrap.after( $notice );
		} else {
			$( '.wrap' ).prepend( $notice );
		}
		return $notice;
	}

	function showNotice( message, type ) {
		const $notice = ensureNotice();
		$notice
			.removeClass( 'notice-info notice-success notice-error' )
			.addClass( 'notice-' + ( type || 'info' ) )
			.show()
			.find( 'p' )
			.text( message || '' );
	}

	$( function () {
		const $tbody = $( '#the-list' );
		if ( ! $tbody.length || typeof $tbody.sortable !== 'function' ) {
			return;
		}

		if ( ! $tbody.children( 'tr[id^="tag-"]' ).length ) {
			return;
		}

		if ( i18n.hint ) {
			showNotice( i18n.hint, 'info' );
		}

		$tbody.addClass( 'forwp-faq-term-order-sortable' );

		$tbody.sortable( {
			items: itemSelector,
			cancel: 'a, button, input, select, textarea, .inline-edit-row',
			cursor: 'move',
			axis: 'y',
			opacity: 0.85,
			tolerance: 'pointer',
			placeholder: 'forwp-faq-term-order-placeholder',
			helper: function ( event, ui ) {
				ui.children().each( function () {
					const $cell = $( this );
					$cell.width( $cell.width() );
				} );
				return ui;
			},
			start: function ( event, ui ) {
				const $kids = descendantRows( ui.item );
				ui.item.data( 'forwpKids', $kids );

				let extra = 0;
				$kids.each( function () {
					extra += $( this ).outerHeight();
				} );
				$kids.detach();

				ui.placeholder.height( ui.item.outerHeight() + extra );
				ui.placeholder.html(
					'<td colspan="' +
						ui.item.children( 'td' ).length +
						'">&nbsp;</td>'
				);
			},
			beforeStop: function ( event, ui ) {
				attachDescendants( ui.item );
			},
			update: function () {
				const order = collectOrder( $tbody );
				if ( ! order.length || ! config.ajaxUrl ) {
					return;
				}

				showNotice( i18n.saving || 'Saving…', 'info' );

				$.post( config.ajaxUrl, {
					action: 'forwp_faq_term_order',
					nonce: config.nonce,
					taxonomy: config.taxonomy,
					order: order,
				} )
					.done( function ( response ) {
						if ( response && response.success ) {
							const msg =
								( response.data && response.data.message ) ||
								i18n.saved ||
								'Saved.';
							showNotice( msg, 'success' );
						} else {
							const msg =
								( response &&
									response.data &&
									response.data.message ) ||
								i18n.error ||
								'Error.';
							showNotice( msg, 'error' );
						}
					} )
					.fail( function () {
						showNotice( i18n.error || 'Error.', 'error' );
					} );
			},
		} );
	} );
} )( jQuery );
