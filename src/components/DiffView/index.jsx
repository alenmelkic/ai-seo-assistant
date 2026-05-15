import { __ } from '@wordpress/i18n';
import { diffChars, diffWords } from 'diff';

/**
 * Side-by-side diff view component.
 * Uses the `diff` npm package for character/word-level diffs.
 */
export default function DiffView( { oldValue, newValue, type = 'text' } ) {
	if ( type === 'tags' ) {
		return <TagsDiff oldTags={ oldValue || [] } newTags={ newValue || [] } />;
	}

	if ( type === 'faq' ) {
		return <FaqDiff oldFaq={ oldValue || [] } newFaq={ newValue || [] } />;
	}

	return <TextDiff oldText={ oldValue || '' } newText={ newValue || '' } />;
}

function TextDiff( { oldText, newText } ) {
	const changes = diffWords( oldText, newText );

	return (
		<div className="aisa-diff">
			<div className="aisa-diff__side">
				<div className="aisa-diff__label">
					{ __( 'Current', 'ai-seo-assistant' ) }
				</div>
				<div className="aisa-diff__content">
					{ changes.map( ( part, i ) => {
						if ( part.added ) return null;
						const cls = part.removed ? 'aisa-diff__removed' : '';
						return (
							<span key={ i } className={ cls }>
								{ part.value }
							</span>
						);
					} ) }
				</div>
			</div>
			<div className="aisa-diff__side">
				<div className="aisa-diff__label">
					{ __( 'AI Suggestion', 'ai-seo-assistant' ) }
				</div>
				<div className="aisa-diff__content">
					{ changes.map( ( part, i ) => {
						if ( part.removed ) return null;
						const cls = part.added ? 'aisa-diff__added' : '';
						return (
							<span key={ i } className={ cls }>
								{ part.value }
							</span>
						);
					} ) }
				</div>
			</div>
		</div>
	);
}

function TagsDiff( { oldTags, newTags } ) {
	const added = newTags.filter( ( t ) => ! oldTags.includes( t ) );
	const removed = oldTags.filter( ( t ) => ! newTags.includes( t ) );
	const kept = newTags.filter( ( t ) => oldTags.includes( t ) );

	return (
		<div className="aisa-diff aisa-diff--tags">
			<div className="aisa-diff__tags-list">
				{ removed.map( ( tag ) => (
					<span key={ tag } className="aisa-diff__tag aisa-diff__removed">
						{ tag }
					</span>
				) ) }
				{ kept.map( ( tag ) => (
					<span key={ tag } className="aisa-diff__tag">
						{ tag }
					</span>
				) ) }
				{ added.map( ( tag ) => (
					<span key={ tag } className="aisa-diff__tag aisa-diff__added">
						{ tag }
					</span>
				) ) }
			</div>
		</div>
	);
}

function FaqDiff( { oldFaq, newFaq } ) {
	const maxLen = Math.max( oldFaq.length, newFaq.length );

	return (
		<div className="aisa-diff aisa-diff--faq">
			{ Array.from( { length: maxLen } ).map( ( _, i ) => {
				const oldItem = oldFaq[ i ];
				const newItem = newFaq[ i ];

				return (
					<div key={ i } className="aisa-diff__faq-item">
						{ oldItem && ! newItem && (
							<div className="aisa-diff__removed">
								<strong>Q:</strong> { oldItem.question }
								<br />
								<strong>A:</strong> { oldItem.answer }
							</div>
						) }
						{ ! oldItem && newItem && (
							<div className="aisa-diff__added">
								<strong>Q:</strong> { newItem.question }
								<br />
								<strong>A:</strong> { newItem.answer }
							</div>
						) }
						{ oldItem && newItem && (
							<TextDiff
								oldText={ `Q: ${ oldItem.question }\nA: ${ oldItem.answer }` }
								newText={ `Q: ${ newItem.question }\nA: ${ newItem.answer }` }
							/>
						) }
					</div>
				);
			} ) }
		</div>
	);
}
