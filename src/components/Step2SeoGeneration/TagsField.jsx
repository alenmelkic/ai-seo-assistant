import { Button, FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../../store';
import RegenerateButton from '../RegenerateButton';
import DiffView from '../DiffView';

export default function TagsField( { onRegenerate } ) {
	const dispatch = useDispatch( STORE_NAME );

	const { tags, previousTags, isDiffOpen } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			tags: store.getEditedTags(),
			previousTags: store.getPreviousValue( 'tags' ),
			isDiffOpen: store.isDiffVisible( 'tags' ),
		};
	}, [] );

	const handleChange = ( newTags ) => {
		dispatch.updateTags( newTags );
	};

	const handleRegen = ( abilityKey, field ) => {
		dispatch.setPreviousValue( 'tags', [ ...tags ] );
		onRegenerate( abilityKey, field );
		dispatch.setDiffVisible( 'tags', true );
	};

	return (
		<div className="aisa-field">
			<div className="aisa-field__header">
				<label className="aisa-field__label">
					{ __( 'Tags', 'ai-seo-assistant' ) }
				</label>
				<div className="aisa-field__actions">
					<Button
						variant="link"
						size="small"
						onClick={ () =>
							dispatch.setDiffVisible( 'tags', ! isDiffOpen )
						}
						disabled={ ! previousTags }
					>
						{ isDiffOpen
							? __( 'Hide Diff', 'ai-seo-assistant' )
							: __( 'Show Diff', 'ai-seo-assistant' ) }
					</Button>
					<RegenerateButton
						abilityKey="generate_tags"
						field="tags"
						onRegenerate={ handleRegen }
					/>
				</div>
			</div>

			<FormTokenField
				value={ tags || [] }
				onChange={ handleChange }
				label=""
				placeholder={ __( 'Add a tag', 'ai-seo-assistant' ) }
			/>

			{ isDiffOpen && previousTags && (
				<DiffView
					oldValue={ previousTags }
					newValue={ tags }
					type="tags"
				/>
			) }
		</div>
	);
}
