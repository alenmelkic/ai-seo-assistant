import { TextareaControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../../store';
import RegenerateButton from '../RegenerateButton';
import DiffView from '../DiffView';

const MAX_CHARS = 160;

export default function MetaDescField( { onRegenerate } ) {
	const dispatch = useDispatch( STORE_NAME );

	const { value, previousValue, isDiffOpen } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			value: store.getEditedMeta().meta_description,
			previousValue: store.getPreviousValue( 'meta_description' ),
			isDiffOpen: store.isDiffVisible( 'meta_description' ),
		};
	}, [] );

	const handleChange = ( newValue ) => {
		dispatch.updateMetaField( 'meta_description', newValue );
	};

	const handleRegen = ( abilityKey, field ) => {
		dispatch.setPreviousValue( 'meta_description', value );
		onRegenerate( abilityKey, field );
		dispatch.setDiffVisible( 'meta_description', true );
	};

	const charCount = ( value || '' ).length;
	const isOverLimit = charCount > MAX_CHARS;

	return (
		<div className="aisa-field">
			<div className="aisa-field__header">
				<label className="aisa-field__label">
					{ __( 'Meta Description', 'ai-seo-assistant' ) }
				</label>
				<div className="aisa-field__actions">
					<Button
						variant="link"
						size="small"
						onClick={ () =>
							dispatch.setDiffVisible(
								'meta_description',
								! isDiffOpen
							)
						}
						disabled={ ! previousValue }
					>
						{ isDiffOpen
							? __( 'Hide Diff', 'ai-seo-assistant' )
							: __( 'Show Diff', 'ai-seo-assistant' ) }
					</Button>
					<RegenerateButton
						abilityKey="generate_meta"
						field="meta_description"
						onRegenerate={ handleRegen }
					/>
				</div>
			</div>

			<TextareaControl
				value={ value || '' }
				onChange={ handleChange }
				rows={ 3 }
				className="aisa-field__input"
			/>

			<div
				className={ `aisa-field__counter ${
					isOverLimit ? 'aisa-field__counter--over' : ''
				}` }
			>
				{ charCount }/{ MAX_CHARS }
			</div>

			{ isDiffOpen && previousValue && (
				<DiffView oldValue={ previousValue } newValue={ value } />
			) }
		</div>
	);
}
