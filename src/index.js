/**
 * AI SEO Assistant - Editor Entry Point
 *
 * Registers Gutenberg sidebar panel, publish flow intercept, Redux store,
 * and the review modal.
 */

import { registerPlugin } from '@wordpress/plugins';
import { createRoot, render } from '@wordpress/element';

// Initialize Redux store
import './store';

// Components
import ErrorBoundary from './components/ErrorBoundary';
import SidebarPanel from './components/SidebarPanel';
import ReviewModal from './components/ReviewModal';

// Editor integration
import {
	initGutenbergIntegration,
	initClassicIntegration,
} from './editor-integration';

// Styles
import './styles/main.scss';

/**
 * Gutenberg plugin registration — sidebar + modal
 */
registerPlugin( 'ai-seo-assistant', {
	render: () => (
		<ErrorBoundary>
			<SidebarPanel />
			<ReviewModal />
		</ErrorBoundary>
	),
	icon: 'search',
} );

// Start Gutenberg publish intercept
initGutenbergIntegration();

/**
 * Classic editor support — render modal into a portal container.
 * Only initialized if the classic editor is detected.
 */
function initClassicEditorModal() {
	const config = window.aiSeoAssistant || {};
	if ( ! config.settings?.classicEditorSupport ) return;

	// Check if Gutenberg is NOT active (classic editor)
	if ( document.querySelector( '.block-editor' ) ) return;

	// Create portal container
	const container = document.createElement( 'div' );
	container.id = 'aisa-classic-modal-root';
	document.body.appendChild( container );

	const App = () => (
		<ErrorBoundary>
			<ReviewModal />
		</ErrorBoundary>
	);

	// Use createRoot if available (React 18+), fallback to render
	if ( createRoot ) {
		createRoot( container ).render( <App /> );
	} else {
		render( <App />, container );
	}

	initClassicIntegration();
}

// Wait for DOM ready for classic editor
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initClassicEditorModal );
} else {
	initClassicEditorModal();
}
