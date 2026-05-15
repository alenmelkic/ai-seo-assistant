import { __ } from '@wordpress/i18n';
import { Spinner, Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../../store';
import MetaTitleField from './MetaTitleField';
import MetaDescField from './MetaDescField';
import TagsField from './TagsField';
import AEOFields from './AEOFields';

export default function Step2SeoGeneration( { onRegenerate } ) {
	const { metaLoading, tagsLoading, aeoLoading, metaError, tagsError, aeoError } =
		useSelect( ( select ) => {
			const store = select( STORE_NAME );
			return {
				metaLoading: store.isLoading( 'generate_meta' ),
				tagsLoading: store.isLoading( 'generate_tags' ),
				aeoLoading: store.isLoading( 'generate_aeo' ),
				metaError: store.getError( 'generate_meta' ),
				tagsError: store.getError( 'generate_tags' ),
				aeoError: store.getError( 'generate_aeo' ),
			};
		}, [] );

	const isLoading = metaLoading || tagsLoading || aeoLoading;

	return (
		<div className="aisa-step2">
			{ isLoading && (
				<div className="aisa-step2__loading-bar">
					<Spinner />
					<span>
						{ __( 'Generating SEO suggestions...', 'ai-seo-assistant' ) }
					</span>
				</div>
			) }

			{/* Meta Title */}
			<div className="aisa-step2__section">
				{ metaLoading ? (
					<div className="aisa-skeleton" />
				) : metaError ? (
					<Notice status="error" isDismissible={ false }>
						{ __( 'Meta generation failed:', 'ai-seo-assistant' ) }{ ' ' }
						{ metaError }
					</Notice>
				) : (
					<>
						<MetaTitleField onRegenerate={ onRegenerate } />
						<MetaDescField onRegenerate={ onRegenerate } />
					</>
				) }
			</div>

			{/* Tags */}
			<div className="aisa-step2__section">
				{ tagsLoading ? (
					<div className="aisa-skeleton" />
				) : tagsError ? (
					<Notice status="error" isDismissible={ false }>
						{ __( 'Tags generation failed:', 'ai-seo-assistant' ) }{ ' ' }
						{ tagsError }
					</Notice>
				) : (
					<TagsField onRegenerate={ onRegenerate } />
				) }
			</div>

			{/* AEO */}
			<div className="aisa-step2__section">
				{ aeoLoading ? (
					<div className="aisa-skeleton aisa-skeleton--large" />
				) : aeoError ? (
					<Notice status="error" isDismissible={ false }>
						{ __( 'AEO generation failed:', 'ai-seo-assistant' ) }{ ' ' }
						{ aeoError }
					</Notice>
				) : (
					<AEOFields onRegenerate={ onRegenerate } />
				) }
			</div>
		</div>
	);
}
