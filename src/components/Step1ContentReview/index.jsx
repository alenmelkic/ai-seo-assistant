import { __ } from '@wordpress/i18n';
import { Spinner, Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useCallback } from '@wordpress/element';
import { STORE_NAME } from '../../store';
import IssueCard from './IssueCard';
import RegenerateButton from '../RegenerateButton';

export default function Step1ContentReview( { onRegenerate } ) {
	const dispatch = useDispatch( STORE_NAME );
	const [ dismissed, setDismissed ] = useState( [] );

	const { analysis, isLoading, error, appliedFixes } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return {
				analysis: store.getAnalysis(),
				isLoading: store.isLoading( 'analyze_content' ),
				error: store.getError( 'analyze_content' ),
				appliedFixes: store.getAppliedFixes(),
			};
		},
		[]
	);

	const handleAutoFixApplied = useCallback(
		( autofix ) => {
			dispatch.setAppliedFixes( [ ...appliedFixes, autofix ] );
		},
		[ appliedFixes, dispatch ]
	);

	const handleDismiss = useCallback(
		( issue ) => {
			setDismissed( ( prev ) => [ ...prev, issue.type ] );
		},
		[]
	);

	if ( isLoading ) {
		return (
			<div className="aisa-step1-loading">
				<Spinner />
				<p>{ __( 'Analyzing content quality...', 'ai-seo-assistant' ) }</p>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="aisa-step1">
				<Notice status="error" isDismissible={ false }>
					{ __(
						'Content analysis failed. You can skip to Step 2 or try again.',
						'ai-seo-assistant'
					) }
					<br />
					<em>{ error }</em>
				</Notice>
				<RegenerateButton
					abilityKey="analyze_content"
					field="analyze_content"
					onRegenerate={ onRegenerate }
				/>
			</div>
		);
	}

	if ( ! analysis ) {
		return null;
	}

	const issues = ( analysis.issues || [] ).filter(
		( issue ) => ! dismissed.includes( issue.type )
	);

	const scoreColor =
		analysis.overall_score >= 80
			? '#00a32a'
			: analysis.overall_score >= 60
			? '#dba617'
			: '#d63638';

	return (
		<div className="aisa-step1">
			<div className="aisa-step1__score-bar">
				<div className="aisa-step1__score">
					<span
						className="aisa-step1__score-number"
						style={ { color: scoreColor } }
					>
						{ analysis.overall_score }
					</span>
					<span className="aisa-step1__score-label">/100</span>
				</div>
				<p className="aisa-step1__summary">{ analysis.summary }</p>
				<RegenerateButton
					abilityKey="analyze_content"
					field="analyze_content"
					onRegenerate={ onRegenerate }
				/>
			</div>

			{ appliedFixes.length > 0 && (
				<Notice status="info" isDismissible={ false }>
					{ appliedFixes.length }{ ' ' }
					{ __(
						'fix(es) applied. Content will be re-analyzed when you proceed to Step 2.',
						'ai-seo-assistant'
					) }
				</Notice>
			) }

			{ issues.length === 0 ? (
				<Notice status="success" isDismissible={ false }>
					{ __(
						'No issues found! Your content looks great.',
						'ai-seo-assistant'
					) }
				</Notice>
			) : (
				<div className="aisa-step1__issues">
					{ issues.map( ( issue, index ) => (
						<IssueCard
							key={ `${ issue.type }-${ index }` }
							issue={ issue }
							onAutoFixApplied={ handleAutoFixApplied }
							onDismiss={ handleDismiss }
						/>
					) ) }
				</div>
			) }
		</div>
	);
}
