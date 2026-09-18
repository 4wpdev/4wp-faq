/**
 * Copy JSON-LD preview from registry CPT metabox (admin only).
 */
( function () {
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button[data-forwp-faq-copy-target]' );
		if ( ! button ) {
			return;
		}
		var targetId = button.getAttribute( 'data-forwp-faq-copy-target' );
		var textarea = document.getElementById( targetId );
		if ( ! textarea ) {
			return;
		}
		event.preventDefault();
		textarea.focus();
		textarea.select();
		try {
			document.execCommand( 'copy' );
		} catch ( e ) {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( textarea.value );
			}
		}
	} );

	document.addEventListener(
		'submit',
		function ( event ) {
			if ( ! event.target || 'post' !== event.target.id ) {
				return;
			}
			if ( typeof window.tinymce === 'undefined' ) {
				return;
			}
			var editor = window.tinymce.get( 'forwp_faq_answer_html' );
			if ( editor ) {
				editor.save();
			}
		},
		true
	);
}() );
