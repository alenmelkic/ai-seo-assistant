import { subscribe, select, dispatch } from '@wordpress/data';
import { STORE_NAME } from './store';

/**
 * Editor integration — intercepts the publish action and opens the
 * AI SEO review modal on first publish (when _aisa_confirmed is not set).
 */

let wasSavingPost = false;
let wasPublishing = false;

export function initGutenbergIntegration() {
	subscribe( () => {
		const editor = select( 'core/editor' );
		if ( ! editor ) return;

		const isSavingPost = editor.isSavingPost();
		const isAutosaving = editor.isAutosavingPost();
		const isPublishing = editor.isPublishingPost();
		const store = select( STORE_NAME );
		const aisaDispatch = dispatch( STORE_NAME );

		// Detect transition to saving (not autosave)
		if ( isSavingPost && ! isAutosaving && ! wasSavingPost ) {
			const post = editor.getCurrentPost();
			const editedStatus = editor.getEditedPostAttribute( 'status' );
			const currentStatus = post?.status;

			// Only intercept on publish (draft->publish or schedule)
			const isGoingToPublish =
				editedStatus === 'publish' && currentStatus !== 'publish';
			const isScheduling =
				editedStatus === 'future' && currentStatus !== 'future';

			if ( isGoingToPublish || isScheduling ) {
				const meta = editor.getEditedPostAttribute( 'meta' ) || {};
				const confirmed = meta._aisa_confirmed;
				const modalAlreadyOpen = store.isModalOpen();

				if ( ! confirmed && ! modalAlreadyOpen ) {
					// Lock the post saving — we need to open the modal first
					dispatch( 'core/editor' ).lockPostSaving( 'aisa-review' );
					aisaDispatch.setModalOpen( true );
				}
			}
		}

		// When modal confirms and sets _aisa_confirmed, unlock saving
		const isModalOpen = store.isModalOpen();
		const confirmedState = store.getConfirmed();

		if ( ! isModalOpen && confirmedState && editor.isPostSavingLocked?.() ) {
			dispatch( 'core/editor' ).unlockPostSaving( 'aisa-review' );
		}

		wasSavingPost = isSavingPost && ! isAutosaving;
		wasPublishing = isPublishing;
	} );
}

/**
 * Classic editor integration — intercepts the form submit.
 * The modal is rendered via React portal from index.js.
 */
export function initClassicIntegration() {
	const config = window.aiSeoAssistant || {};
	if ( ! config.settings?.classicEditorSupport ) return;

	const form = document.getElementById( 'post' );
	if ( ! form ) return;

	const publishButton = document.getElementById( 'publish' );
	if ( ! publishButton ) return;

	// Only intercept if the post status will change to 'publish'
	publishButton.addEventListener( 'click', ( e ) => {
		const postStatus = document.getElementById( 'post_status' );
		const originalStatus = document.getElementById( 'original_post_status' );

		const isNewPublish =
			postStatus?.value === 'publish' &&
			originalStatus?.value !== 'publish';

		if ( ! isNewPublish ) return;

		// Check if already confirmed (via hidden field set by our meta box)
		const confirmedField = document.getElementById( 'aisa-confirmed' );
		if ( confirmedField?.value === '1' ) return;

		e.preventDefault();
		e.stopPropagation();

		// Open modal
		dispatch( STORE_NAME ).setModalOpen( true );
	} );
}
