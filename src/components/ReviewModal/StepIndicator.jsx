import { __ } from '@wordpress/i18n';

export default function StepIndicator( { currentStep } ) {
	const steps = [
		{ number: 1, label: __( 'Content Review', 'ai-seo-assistant' ) },
		{ number: 2, label: __( 'SEO Generation', 'ai-seo-assistant' ) },
	];

	return (
		<div className="aisa-step-indicator">
			{ steps.map( ( step, index ) => (
				<div
					key={ step.number }
					className={ `aisa-step-indicator__step ${
						step.number === currentStep
							? 'aisa-step-indicator__step--active'
							: ''
					} ${
						step.number < currentStep
							? 'aisa-step-indicator__step--completed'
							: ''
					}` }
				>
					<div className="aisa-step-indicator__number">
						{ step.number < currentStep ? '\u2713' : step.number }
					</div>
					<div className="aisa-step-indicator__label">
						{ step.label }
					</div>
					{ index < steps.length - 1 && (
						<div className="aisa-step-indicator__connector" />
					) }
				</div>
			) ) }
		</div>
	);
}
