import { createRoot } from '@wordpress/element';
import { App } from './App';

const roots = document.querySelectorAll( '[data-forwp-faq-setup-root]' );

roots.forEach( ( node ) => {
	const config = window.forwpFaqSetup || {};
	createRoot( node ).render( <App config={ config } /> );
} );
