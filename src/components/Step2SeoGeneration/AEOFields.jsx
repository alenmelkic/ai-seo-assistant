import {
	TextareaControl,
	TextControl,
	Button,
	Card,
	CardBody,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../../store';
import RegenerateButton from '../RegenerateButton';

export default function AEOFields( { onRegenerate } ) {
	const dispatch = useDispatch( STORE_NAME );

	const { aeo } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return { aeo: store.getEditedAeo() };
	}, [] );

	const handleFieldChange = ( field, value ) => {
		dispatch.updateAeoField( field, value );
	};

	const handleFaqChange = ( index, key, value ) => {
		const newFaq = [ ...aeo.faq ];
		newFaq[ index ] = { ...newFaq[ index ], [ key ]: value };
		dispatch.updateAeoField( 'faq', newFaq );
	};

	const handleAddFaq = () => {
		if ( aeo.faq.length >= 8 ) return;
		dispatch.updateAeoField( 'faq', [
			...aeo.faq,
			{ question: '', answer: '' },
		] );
	};

	const handleRemoveFaq = ( index ) => {
		if ( aeo.faq.length <= 2 ) return;
		const newFaq = aeo.faq.filter( ( _, i ) => i !== index );
		dispatch.updateAeoField( 'faq', newFaq );
	};

	const handleEntityRemove = ( index ) => {
		const newEntities = aeo.entities.filter( ( _, i ) => i !== index );
		dispatch.updateAeoField( 'entities', newEntities );
	};

	const wordCount = ( aeo.tldr || '' )
		.trim()
		.split( /\s+/ )
		.filter( Boolean ).length;

	return (
		<div className="aisa-field aisa-aeo-fields">
			<div className="aisa-field__header">
				<label className="aisa-field__label">
					{ __( 'AEO Fields', 'ai-seo-assistant' ) }
				</label>
				<RegenerateButton
					abilityKey="generate_aeo"
					field="aeo"
					onRegenerate={ onRegenerate }
				/>
			</div>

			{/* TL;DR */}
			<div className="aisa-aeo-section">
				<h4>{ __( 'TL;DR', 'ai-seo-assistant' ) }</h4>
				<TextareaControl
					value={ aeo.tldr || '' }
					onChange={ ( val ) => handleFieldChange( 'tldr', val ) }
					rows={ 3 }
				/>
				<div
					className={ `aisa-field__counter ${
						wordCount < 40 || wordCount > 60
							? 'aisa-field__counter--over'
							: ''
					}` }
				>
					{ wordCount } { __( 'words', 'ai-seo-assistant' ) } (40-60{' '}
					{ __( 'recommended', 'ai-seo-assistant' ) })
				</div>
			</div>

			{/* Main Question */}
			<div className="aisa-aeo-section">
				<h4>{ __( 'Main Question', 'ai-seo-assistant' ) }</h4>
				<TextControl
					value={ aeo.main_question || '' }
					onChange={ ( val ) =>
						handleFieldChange( 'main_question', val )
					}
				/>
			</div>

			{/* FAQ */}
			<div className="aisa-aeo-section">
				<h4>
					{ __( 'FAQ', 'ai-seo-assistant' ) } ({ aeo.faq?.length || 0 }
					)
				</h4>
				{ ( aeo.faq || [] ).map( ( item, index ) => (
					<Card key={ index } size="small" className="aisa-faq-item">
						<CardBody>
							<TextControl
								label={ `Q${ index + 1 }` }
								value={ item.question || '' }
								onChange={ ( val ) =>
									handleFaqChange( index, 'question', val )
								}
							/>
							<TextareaControl
								label={ __( 'Answer', 'ai-seo-assistant' ) }
								value={ item.answer || '' }
								onChange={ ( val ) =>
									handleFaqChange( index, 'answer', val )
								}
								rows={ 2 }
							/>
							{ aeo.faq.length > 2 && (
								<Button
									variant="link"
									isDestructive
									size="small"
									onClick={ () => handleRemoveFaq( index ) }
								>
									{ __( 'Remove', 'ai-seo-assistant' ) }
								</Button>
							) }
						</CardBody>
					</Card>
				) ) }
				{ aeo.faq?.length < 8 && (
					<Button variant="secondary" size="small" onClick={ handleAddFaq }>
						{ __( 'Add FAQ', 'ai-seo-assistant' ) }
					</Button>
				) }
			</div>

			{/* Entities */}
			<div className="aisa-aeo-section">
				<h4>{ __( 'Entities', 'ai-seo-assistant' ) }</h4>
				<div className="aisa-entities">
					{ ( aeo.entities || [] ).map( ( entity, index ) => (
						<span key={ index } className="aisa-entity-chip">
							<span className="aisa-entity-chip__type">
								{ entity.type }
							</span>
							{ entity.name }
							<button
								className="aisa-entity-chip__remove"
								onClick={ () => handleEntityRemove( index ) }
								aria-label={ __( 'Remove', 'ai-seo-assistant' ) }
							>
								&times;
							</button>
						</span>
					) ) }
				</div>
			</div>
		</div>
	);
}
