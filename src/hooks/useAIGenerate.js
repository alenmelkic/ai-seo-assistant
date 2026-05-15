import { useDispatch, useSelect } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import { STORE_NAME } from '../store';
import { executeAbility, executeAllAbilities } from '../services/abilities';

/**
 * Hook for triggering AI generation and managing loading/error state.
 */
export default function useAIGenerate() {
	const dispatch = useDispatch( STORE_NAME );
	const { postId } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return { postId: editor?.getCurrentPostId() };
	}, [] );

	const runAll = useCallback( async () => {
		if ( ! postId ) return;

		// Set all loading
		dispatch.setLoading( 'analyze_content', true );
		dispatch.setLoading( 'generate_meta', true );
		dispatch.setLoading( 'generate_tags', true );
		dispatch.setLoading( 'generate_aeo', true );

		const results = await executeAllAbilities( postId );

		// Process analyze_content
		if ( results.analyze_content?.success ) {
			dispatch.setAnalysisResult( results.analyze_content.data );
		} else {
			dispatch.setError(
				'analyze_content',
				results.analyze_content?.error
			);
		}
		dispatch.setLoading( 'analyze_content', false );

		// Process generate_meta
		if ( results.generate_meta?.success ) {
			dispatch.setMetaResult( results.generate_meta.data );
		} else {
			dispatch.setError( 'generate_meta', results.generate_meta?.error );
		}
		dispatch.setLoading( 'generate_meta', false );

		// Process generate_tags
		if ( results.generate_tags?.success ) {
			dispatch.setTagsResult( results.generate_tags.data );
		} else {
			dispatch.setError( 'generate_tags', results.generate_tags?.error );
		}
		dispatch.setLoading( 'generate_tags', false );

		// Process generate_aeo
		if ( results.generate_aeo?.success ) {
			dispatch.setAeoResult( results.generate_aeo.data );
		} else {
			dispatch.setError( 'generate_aeo', results.generate_aeo?.error );
		}
		dispatch.setLoading( 'generate_aeo', false );
	}, [ postId, dispatch ] );

	const regenerate = useCallback(
		async ( abilityKey, regenField = null ) => {
			if ( ! postId ) return;

			dispatch.setFieldLoading( regenField || abilityKey, true );

			try {
				const result = await executeAbility(
					abilityKey,
					postId,
					regenField
				);

				if ( result?.success ) {
					// Store previous value before updating
					switch ( abilityKey ) {
						case 'analyze_content':
							dispatch.setAnalysisResult( result.data );
							break;
						case 'generate_meta':
							dispatch.setMetaResult( result.data );
							break;
						case 'generate_tags':
							dispatch.setTagsResult( result.data );
							break;
						case 'generate_aeo':
							dispatch.setAeoResult( result.data );
							break;
					}
				} else {
					dispatch.setError(
						abilityKey,
						result?.error || 'Regeneration failed'
					);
				}
			} catch ( err ) {
				dispatch.setError( abilityKey, err.message );
			} finally {
				dispatch.setFieldLoading( regenField || abilityKey, false );
			}
		},
		[ postId, dispatch ]
	);

	const reAnalyze = useCallback( async () => {
		if ( ! postId ) return;

		dispatch.setLoading( 'analyze_content', true );
		dispatch.setLoading( 'generate_meta', true );
		dispatch.setLoading( 'generate_tags', true );
		dispatch.setLoading( 'generate_aeo', true );

		const results = await executeAllAbilities( postId );

		if ( results.analyze_content?.success ) {
			dispatch.setAnalysisResult( results.analyze_content.data );
		}
		dispatch.setLoading( 'analyze_content', false );

		if ( results.generate_meta?.success ) {
			dispatch.setMetaResult( results.generate_meta.data );
		}
		dispatch.setLoading( 'generate_meta', false );

		if ( results.generate_tags?.success ) {
			dispatch.setTagsResult( results.generate_tags.data );
		}
		dispatch.setLoading( 'generate_tags', false );

		if ( results.generate_aeo?.success ) {
			dispatch.setAeoResult( results.generate_aeo.data );
		}
		dispatch.setLoading( 'generate_aeo', false );
	}, [ postId, dispatch ] );

	return { runAll, regenerate, reAnalyze };
}
