import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../../store';

export default function RegenerateButton( {
	abilityKey,
	field,
	onRegenerate,
	size = 'small',
} ) {
	const isLoading = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return store.getFieldLoading( field || abilityKey );
		},
		[ field, abilityKey ]
	);

	const handleClick = () => {
		if ( onRegenerate ) {
			onRegenerate( abilityKey, field );
		}
	};

	return (
		<Button
			variant="tertiary"
			size={ size }
			onClick={ handleClick }
			disabled={ isLoading }
			className="aisa-regenerate-btn"
		>
			{ isLoading ? (
				<>
					<Spinner />
					{ __( 'Regenerating...', 'ai-seo-assistant' ) }
				</>
			) : (
				__( 'Regenerate', 'ai-seo-assistant' )
			) }
		</Button>
	);
}
