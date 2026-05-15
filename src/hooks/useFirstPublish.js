import { useSelect } from '@wordpress/data';

/**
 * Detects whether this is the first publish of a post.
 * Returns true if the post is being published and has no _aisa_confirmed meta.
 */
export default function useFirstPublish() {
	return useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		if ( ! editor ) {
			return { isFirstPublish: false, isPublishing: false, confirmed: null };
		}

		const postId = editor.getCurrentPostId();
		const postStatus = editor.getEditedPostAttribute( 'status' );
		const savedStatus = editor.getCurrentPost()?.status;
		const isPublishing =
			postStatus === 'publish' && savedStatus !== 'publish';
		const isSavingPost = editor.isSavingPost();
		const isAutosaving = editor.isAutosavingPost();

		// Get confirmed meta
		const meta = editor.getEditedPostAttribute( 'meta' ) || {};
		const confirmed = meta._aisa_confirmed || null;

		return {
			postId,
			isFirstPublish: ! confirmed && isPublishing,
			isPublishing,
			isSavingPost: isSavingPost && ! isAutosaving,
			confirmed,
		};
	}, [] );
}
