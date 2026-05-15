import { useState, useCallback } from '@wordpress/element';
import { executeAbility } from '../services/abilities';

/**
 * Generic hook wrapper around executeAbility for one-off calls.
 */
export default function useAbility( abilityKey ) {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const execute = useCallback(
		async ( postId, regenField = null ) => {
			setLoading( true );
			setError( null );

			try {
				const result = await executeAbility(
					abilityKey,
					postId,
					regenField
				);

				if ( result?.success ) {
					setData( result.data );
					return result.data;
				}
				const errorMsg = result?.error || 'Request failed';
				setError( errorMsg );
				return null;
			} catch ( err ) {
				setError( err.message );
				return null;
			} finally {
				setLoading( false );
			}
		},
		[ abilityKey ]
	);

	return { data, loading, error, execute };
}
