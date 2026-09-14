/**
 * Shared AI field panel. Case config: window.forwpAiField.
 */
( function () {
	'use strict';

	const config = window.forwpAiField || {};
	if ( ! config.field || ! config.promptPath || ! config.generatePath || typeof wp === 'undefined' || ! wp.apiFetch ) {
		return;
	}

	const target = document.querySelector( config.field );
	if ( ! target ) {
		return;
	}

	const i18n = config.i18n || {};
	const posKey = config.storageKey || 'forwpAiPanelPos';
	const sparkleSvg =
		'<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.6 4.9L18.5 9.5l-4.9 1.6L12 16l-1.6-4.9L5.5 9.5l4.9-1.6L12 3z"/><path d="M19 15l.7 2.1L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.9L19 15z"/><path d="M5 14l.6 1.8L7.5 16.5l-1.9.6L5 19l-.6-1.9L2.5 16.5l1.9-.7L5 14z"/></svg>';

	function iconButton( extraClass, dashicon ) {
		return (
			'<button type="button" class="forwp-ai-icon ' +
			extraClass +
			'">' +
			'<span class="dashicons ' +
			dashicon +
			'" aria-hidden="true"></span>' +
			'</button>'
		);
	}

	function fieldBlock( fieldId, fieldClass, rows, actionsHtml ) {
		return (
			'<label class="forwp-ai-label" for="' +
			fieldId +
			'"></label>' +
			'<textarea id="' +
			fieldId +
			'" class="' +
			fieldClass +
			'" rows="' +
			String( rows ) +
			'"></textarea>' +
			'<div class="forwp-ai-actions">' +
			actionsHtml +
			'</div>'
		);
	}

	const openButton = document.createElement( 'button' );
	openButton.type = 'button';
	openButton.className = 'button forwp-ai-field-open';
	openButton.textContent = i18n.generate || 'Generate with AI';
	target.insertAdjacentElement( 'afterend', openButton );

	const root = document.createElement( 'div' );
	root.id = 'forwp-ai-root';
	root.innerHTML =
		'<div class="forwp-ai-fab-wrap">' +
			'<button type="button" class="forwp-ai-fab" aria-expanded="false">' +
				sparkleSvg +
			'</button>' +
		'</div>' +
		'<div class="forwp-ai-stack" hidden>' +
			'<div class="forwp-ai-panel">' +
				'<div class="forwp-ai-panel__head">' +
					'<span class="forwp-ai-panel__drag dashicons dashicons-move" aria-hidden="true"></span>' +
					'<h2 class="forwp-ai-panel__title"></h2>' +
					iconButton( 'forwp-ai-panel__close', 'dashicons-no-alt' ) +
				'</div>' +
				fieldBlock(
					'forwp-ai-prompt',
					'forwp-ai-prompt',
					8,
					iconButton( 'forwp-ai-send', 'dashicons-arrow-right-alt' ) +
						iconButton( 'forwp-ai-clear', 'dashicons-dismiss' ) +
						iconButton( 'forwp-ai-restore', 'dashicons-image-rotate' )
				) +
				fieldBlock(
					'forwp-ai-result',
					'forwp-ai-result',
					5,
					iconButton( 'forwp-ai-apply', 'dashicons-yes-alt' )
				) +
				fieldBlock(
					'forwp-ai-refine',
					'forwp-ai-refine',
					2,
					iconButton( 'forwp-ai-send-refine', 'dashicons-arrow-right-alt' )
				) +
				'<p class="forwp-ai-status" role="status"></p>' +
			'</div>' +
		'</div>';
	document.body.appendChild( root );

	const fab = root.querySelector( '.forwp-ai-fab' );
	const stack = root.querySelector( '.forwp-ai-stack' );
	const head = root.querySelector( '.forwp-ai-panel__head' );
	const closeBtn = root.querySelector( '.forwp-ai-panel__close' );
	const promptField = root.querySelector( '.forwp-ai-prompt' );
	const resultField = root.querySelector( '.forwp-ai-result' );
	const refineField = root.querySelector( '.forwp-ai-refine' );
	const sendBtn = root.querySelector( '.forwp-ai-send' );
	const clearBtn = root.querySelector( '.forwp-ai-clear' );
	const restoreBtn = root.querySelector( '.forwp-ai-restore' );
	const applyBtn = root.querySelector( '.forwp-ai-apply' );
	const sendRefineBtn = root.querySelector( '.forwp-ai-send-refine' );
	const statusEl = root.querySelector( '.forwp-ai-status' );
	const busyButtons = [ sendBtn, clearBtn, restoreBtn, applyBtn, sendRefineBtn, openButton, fab ];

	let promptLoaded = false;
	let busy = false;
	let drag = null;

	function fieldValue() {
		return 'value' in target ? String( target.value || '' ) : '';
	}

	function setFieldValue( next ) {
		if ( 'value' in target ) {
			target.value = next;
		}
	}

	function setStatus( text ) {
		if ( statusEl ) {
			statusEl.textContent = text || '';
		}
	}

	function setBusy( next, message ) {
		busy = !! next;
		busyButtons.forEach( function ( btn ) {
			if ( btn ) {
				btn.disabled = busy;
			}
		} );
		if ( busy && message ) {
			setStatus( message );
		}
	}

	function isOpen() {
		return ! stack.hasAttribute( 'hidden' );
	}

	function clampPos( left, top ) {
		const width = stack.offsetWidth;
		const height = stack.offsetHeight;
		const maxLeft = Math.max( 8, window.innerWidth - width - 8 );
		const maxTop = Math.max( 8, window.innerHeight - height - 8 );
		return {
			left: Math.min( Math.max( 8, left ), maxLeft ),
			top: Math.min( Math.max( 8, top ), maxTop ),
		};
	}

	function applyPos( pos ) {
		stack.style.left = String( pos.left ) + 'px';
		stack.style.top = String( pos.top ) + 'px';
		stack.style.right = 'auto';
		stack.style.bottom = 'auto';
	}

	function readSavedPos() {
		try {
			const raw = window.sessionStorage.getItem( posKey );
			if ( ! raw ) {
				return null;
			}
			const pos = JSON.parse( raw );
			if ( typeof pos.left !== 'number' || typeof pos.top !== 'number' ) {
				return null;
			}
			return pos;
		} catch ( err ) {
			return null;
		}
	}

	function savePos() {
		const rect = stack.getBoundingClientRect();
		try {
			window.sessionStorage.setItem(
				posKey,
				JSON.stringify( {
					left: Math.round( rect.left ),
					top: Math.round( rect.top ),
				} )
			);
		} catch ( err ) {
			/* Ignore quota / private mode. */
		}
	}

	function restorePos() {
		const saved = readSavedPos();
		if ( ! saved ) {
			return;
		}
		applyPos( clampPos( saved.left, saved.top ) );
	}

	function openPanel() {
		stack.removeAttribute( 'hidden' );
		fab.setAttribute( 'aria-expanded', 'true' );
		restorePos();
		if ( ! promptLoaded ) {
			loadPrompt();
			return;
		}
		promptField.focus();
	}

	function closePanel() {
		stack.setAttribute( 'hidden', '' );
		fab.setAttribute( 'aria-expanded', 'false' );
	}

	function togglePanel() {
		if ( isOpen() ) {
			closePanel();
			return;
		}
		openPanel();
	}

	function loadPrompt() {
		setBusy( true, i18n.loading || '' );
		wp.apiFetch( { path: config.promptPath } )
			.then( function ( res ) {
				if ( res && typeof res.prompt === 'string' ) {
					promptField.value = res.prompt;
					promptLoaded = true;
					setStatus( '' );
					promptField.focus();
					return;
				}
				setStatus( i18n.error || '' );
			} )
			.catch( function ( err ) {
				setStatus( ( err && err.message ) || i18n.error || '' );
			} )
			.finally( function () {
				setBusy( false );
			} );
	}

	function sendPrompt( text ) {
		const value = ( text || '' ).trim();
		if ( ! value ) {
			setStatus( i18n.empty || '' );
			promptField.focus();
			return;
		}

		setBusy( true, i18n.working || '' );
		wp.apiFetch( {
			path: config.generatePath,
			method: 'POST',
			data: { prompt: value },
		} )
			.then( function ( res ) {
				if ( res && res.text ) {
					resultField.value = res.text;
					setStatus( '' );
					resultField.focus();
					return;
				}
				setStatus( i18n.error || '' );
			} )
			.catch( function ( err ) {
				setStatus( ( err && err.message ) || i18n.error || '' );
			} )
			.finally( function () {
				setBusy( false );
			} );
	}

	function applyResult() {
		const next = resultField.value.trim();
		if ( ! next ) {
			setStatus( i18n.error || '' );
			return;
		}
		if ( fieldValue().trim() && i18n.confirm && ! window.confirm( i18n.confirm ) ) {
			return;
		}
		setFieldValue( next );
		setStatus( i18n.done || '' );
	}

	function sendRefine() {
		const note = refineField.value.trim();
		if ( ! note ) {
			refineField.focus();
			return;
		}

		const parts = [];
		if ( promptField.value.trim() ) {
			parts.push( promptField.value.trim() );
		}
		if ( resultField.value.trim() ) {
			parts.push( 'Previous draft:\n' + resultField.value.trim() );
		}
		parts.push( 'Revise as follows:\n' + note );

		const combined = parts.join( '\n\n' );
		promptField.value = combined;
		refineField.value = '';
		sendPrompt( combined );
	}

	head.addEventListener( 'pointerdown', function ( event ) {
		if ( event.button !== 0 ) {
			return;
		}
		if ( event.target.closest( 'button' ) ) {
			return;
		}
		const rect = stack.getBoundingClientRect();
		drag = {
			id: event.pointerId,
			dx: event.clientX - rect.left,
			dy: event.clientY - rect.top,
		};
		stack.classList.add( 'is-dragging' );
		event.preventDefault();
	} );
	document.addEventListener( 'pointermove', function ( event ) {
		if ( ! drag || event.pointerId !== drag.id ) {
			return;
		}
		applyPos( clampPos( event.clientX - drag.dx, event.clientY - drag.dy ) );
	} );
	document.addEventListener( 'pointerup', function ( event ) {
		if ( ! drag || event.pointerId !== drag.id ) {
			return;
		}
		drag = null;
		stack.classList.remove( 'is-dragging' );
		savePos();
	} );
	window.addEventListener( 'resize', function () {
		if ( ! isOpen() || ! readSavedPos() ) {
			return;
		}
		restorePos();
	} );

	document.addEventListener( 'click', function ( event ) {
		const node = event.target && event.target.closest ? event.target.closest( 'button' ) : null;
		if ( ! node ) {
			return;
		}
		if ( node.classList.contains( 'forwp-ai-field-open' ) ) {
			event.preventDefault();
			openPanel();
			return;
		}
		if ( node.classList.contains( 'forwp-ai-fab' ) ) {
			event.preventDefault();
			togglePanel();
			return;
		}
		if ( node.classList.contains( 'forwp-ai-panel__close' ) ) {
			event.preventDefault();
			closePanel();
			return;
		}
		if ( node.classList.contains( 'forwp-ai-send' ) && ! node.classList.contains( 'forwp-ai-send-refine' ) ) {
			event.preventDefault();
			sendPrompt( promptField.value );
			return;
		}
		if ( node.classList.contains( 'forwp-ai-clear' ) ) {
			event.preventDefault();
			promptField.value = '';
			setStatus( '' );
			promptField.focus();
			return;
		}
		if ( node.classList.contains( 'forwp-ai-restore' ) ) {
			event.preventDefault();
			promptLoaded = false;
			loadPrompt();
			return;
		}
		if ( node.classList.contains( 'forwp-ai-apply' ) ) {
			event.preventDefault();
			applyResult();
			return;
		}
		if ( node.classList.contains( 'forwp-ai-send-refine' ) ) {
			event.preventDefault();
			sendRefine();
		}
	}, true );
	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && isOpen() && ! busy ) {
			closePanel();
		}
	} );

	function labelIcon( btn, text ) {
		btn.setAttribute( 'aria-label', text );
		btn.setAttribute( 'title', text );
	}

	fab.setAttribute( 'aria-label', i18n.fab || 'AI prompt' );
	fab.setAttribute( 'title', i18n.fab || 'AI prompt' );
	head.setAttribute( 'title', i18n.move || 'Drag to move' );
	root.querySelector( '.forwp-ai-panel__title' ).textContent = i18n.title || 'Prompt';
	labelIcon( closeBtn, i18n.close || 'Close' );
	root.querySelector( 'label[for="forwp-ai-prompt"]' ).textContent = i18n.prompt || 'Prompt';
	root.querySelector( 'label[for="forwp-ai-result"]' ).textContent = i18n.result || 'Result';
	root.querySelector( 'label[for="forwp-ai-refine"]' ).textContent = i18n.refine || 'Refine';
	labelIcon( sendBtn, i18n.send || 'Send' );
	labelIcon( clearBtn, i18n.clear || 'Clear' );
	labelIcon( restoreBtn, i18n.restore || 'Restore default' );
	labelIcon( applyBtn, i18n.apply || 'Apply' );
	labelIcon( sendRefineBtn, i18n.send || 'Send' );

	root.setAttribute( 'data-forwp-ai-bound', '1' );
}() );
