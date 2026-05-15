import { useSelect } from '@wordpress/data';

/**
 * Reads current post content and metadata from Gutenberg store.
 */
export default function usePostContent() {
	return useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		if ( ! editor ) {
			return {};
		}

		const postId = editor.getCurrentPostId();
		const title = editor.getEditedPostAttribute( 'title' ) || '';
		const tags = editor.getEditedPostAttribute( 'tags' ) || [];
		const meta = editor.getEditedPostAttribute( 'meta' ) || {};

		return {
			postId,
			title,
			tags,
			aeoTldr: meta._aisa_aeo_tldr || '',
			aeoFaq: meta._aisa_aeo_faq || [],
			aeoMainQuestion: meta._aisa_aeo_main_question || '',
			aeoEntities: meta._aisa_aeo_entities || [],
			confirmed: meta._aisa_confirmed || null,
		};
	}, [] );
}
