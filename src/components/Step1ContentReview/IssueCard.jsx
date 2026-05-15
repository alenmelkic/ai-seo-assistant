import { Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import AutoFixButton from './AutoFixButton';

const SEVERITY_LABELS = {
	high: __( 'HIGH', 'ai-seo-assistant' ),
	medium: __( 'MEDIUM', 'ai-seo-assistant' ),
	low: __( 'LOW', 'ai-seo-assistant' ),
};

export default function IssueCard( { issue, onAutoFixApplied, onDismiss } ) {
	return (
		<Card className="aisa-issue-card" size="small">
			<CardBody>
				<div className="aisa-issue-card__header">
					<span
						className={ `aisa-severity-badge aisa-severity-badge--${ issue.severity }` }
					>
						{ SEVERITY_LABELS[ issue.severity ] || issue.severity }
					</span>
					<span className="aisa-issue-card__type">
						{ issue.type?.replace( /_/g, ' ' ) }
					</span>
				</div>

				<p className="aisa-issue-card__message">{ issue.message }</p>

				{ issue.suggestion && (
					<div className="aisa-issue-card__suggestion">
						<strong>
							{ __( 'Suggestion:', 'ai-seo-assistant' ) }
						</strong>{ ' ' }
						{ issue.suggestion }
					</div>
				) }

				<div className="aisa-issue-card__actions">
					<AutoFixButton
						autofix={ issue.autofix }
						onApplied={ onAutoFixApplied }
					/>
					<button
						className="aisa-issue-card__dismiss"
						onClick={ () => onDismiss && onDismiss( issue ) }
					>
						{ __( 'Dismiss', 'ai-seo-assistant' ) }
					</button>
				</div>
			</CardBody>
		</Card>
	);
}
