import { __ } from '@wordpress/i18n';
import StepIndicator from './StepIndicator';

export default function ModalHeader( { currentStep } ) {
	return (
		<div className="aisa-modal-header">
			<h2 className="aisa-modal-header__title">
				{ __( 'AI SEO Review', 'ai-seo-assistant' ) }
			</h2>
			<StepIndicator currentStep={ currentStep } />
		</div>
	);
}
