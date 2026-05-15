import { TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../../store';
import RegenerateButton from '../RegenerateButton';
import DiffView from '../DiffView';

const MAX_CHARS = 60;

export default function MetaTitleField( { onRegenerate } ) {
	const dispatch = useDispatch( STORE_NAME );

	const { value, previousValue, isDiffOpen, isLoading } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return {
				value: store.getEditedMeta().meta_title,
				previousValue: store.getPreviousValue( 'meta_title' ),
				isDiffOpen: store.isDiffVisible( 'meta_title' ),
				isLoading: store.getFieldLoading( 'meta_title' ),
			};
		},
		[]
	);

	const handleChange = ( newValue ) => {
		dispatch.updateMetaField( 'meta_title', newValue );
	};

	const handleRegen = ( abilityKey, field ) => {
		dispatch.setPreviousValue( 'meta_title', value );
		onRegenerate( abilityKey, field );
		dispatch.setDiffVisible( 'meta_title', true );
	};

	const charCount = ( value || '' ).length;
	const isOverLimit = charCount > MAX_CHARS;

	return (
		<div className="aisa-field">
			<div className="aisa-field__header">
				<label className="aisa-field__label">
					{ __( 'Meta Title', 'ai-seo-assistant' ) }
				</label>
				<div className="aisa-field__actions">
					<Button
						variant="link"
						size="small"
						onClick={ () =>
							dispatch.setDiffVisible( 'meta_title', ! isDiffOpen )
						}
						disabled={ ! previousValue }
					>
						{ isDiffOpen
							? __( 'Hide Diff', 'ai-seo-assistant' )
							: __( 'Show Diff', 'ai-seo-assistant' ) }
					</Button>
					<RegenerateButton
						abilityKey="generate_meta"
						field="meta_title"
						onRegenerate={ handleRegen }
					/>
				</div>
			</div>

			<TextControl
				value={ value || '' }
				onChange={ handleChange }
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
