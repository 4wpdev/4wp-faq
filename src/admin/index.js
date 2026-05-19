/**
 * React settings shell (Gutenberg-style components).
 */
import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

const rootEl = document.getElementById( 'forwp-faq-admin-root' );

if ( rootEl ) {
	createRoot( rootEl ).render( <App /> );
}
