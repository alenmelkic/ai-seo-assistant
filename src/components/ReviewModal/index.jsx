import { Modal, Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useCallback } from '@wordpress/element';
import { STORE_NAME } from '../../store';
import useAIGenerate from '../../hooks/useAIGenerate';
import ModalHeader from './ModalHeader';
import Step1ContentReview from '../Step1ContentReview';
import Step2SeoGeneration from '../Step2SeoGeneration';
import { confirmSEO } from '../../services/api';

export default function ReviewModal() {
	const dispatch = useDispatch( STORE_NAME );
	const { runAll, reAnalyze, regenerate } = useAIGenerate();

	const {
		isModalOpen,
		currentStep,
		isAnyLoading,
		isConfirming,
		hasAppliedFixes,
		editedMeta,
		editedTags,
		editedAeo,
		analysis,
	} = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			isModalOpen: store.isModalOpen(),
			currentStep: store.getCurrentStep(),
			isAnyLoading: store.isAnyLoading(),
			isConfirming: store.isConfirming(),
			hasAppliedFixes: store.hasAppliedFixes(),
			editedMeta: store.getEditedMeta(),
			editedTags: store.getEditedTags(),
			editedAeo: store.getEditedAeo(),
			analysis: store.getAnalysis(),
		};
	}, [] );

	// Run all AI abilities when modal opens
	useEffect( () => {
		if ( isModalOpen ) {
			runAll();
		}
	}, [ isModalOpen, runAll ] );

	const handleClose = useCallback( () => {
		dispatch.setModalOpen( false );
		dispatch.setCurrentStep( 1 );
	}, [ dispatch ] );

	const handleNextStep = useCallback( async () => {
		// If fixes were applied, re-analyze with fresh content
		if ( hasAppliedFixes ) {
			await reAnalyze();
			dispatch.setAppliedFixes( [] );
		}
		dispatch.setCurrentStep( 2 );
	}, [ hasAppliedFixes, reAnalyze, dispatch ] );

	const handlePrevStep = useCallback( () => {
		dispatch.setCurrentStep( 1 );
	}, [ dispatch ] );

	const handleSkipStep1 = useCallback( () => {
		dispatch.setCurrentStep( 2 );
	}, [ dispatch ] );

	const handleConfirm = useCallback( async () => {
		const postId =
			wp.data.select( 'core/editor' )?.getCurrentPostId();
		if ( ! postId ) return;

		dispatch.setConfirming( true );

		try {
			const result = await confirmSEO( {
				postId,
				meta_title: editedMeta.meta_title,
				meta_description: editedMeta.meta_description,
				focus_keyword: editedMeta.focus_keyword,
				tags: editedTags,
				aeo_tldr: editedAeo.tldr,
				aeo_main_question: editedAeo.main_question,
				aeo_faq: editedAeo.faq,
				aeo_entities: editedAeo.entities,
			} );

			if ( result?.success ) {
				dispatch.setConfirmed( {
					date: new Date().toISOString(),
					user_id: 0,
					version: '1.0',
					skipped: false,
				} );
				dispatch.setModalOpen( false );
				dispatch.setCurrentStep( 1 );

				// unlockPostSaving in editor-integration.js will resume the save
			}
		} catch ( err ) {
			dispatch.setError( 'confirm', err.message );
		} finally {
			dispatch.setConfirming( false );
		}
	}, [ editedMeta, editedTags, editedAeo, dispatch ] );

	const handleSkipAll = useCallback( async () => {
		const postId =
			wp.data.select( 'core/editor' )?.getCurrentPostId();
		if ( ! postId ) return;

		dispatch.setConfirming( true );

		try {
			await confirmSEO( { postId, skipped: true } );

			dispatch.setConfirmed( {
				date: new Date().toISOString(),
				user_id: 0,
				version: '1.0',
				skipped: true,
			} );
			dispatch.setModalOpen( false );
			dispatch.setCurrentStep( 1 );

			// unlockPostSaving in editor-integration.js will resume the save
		} catch ( err ) {
			dispatch.setError( 'confirm', err.message );
		} finally {
			dispatch.setConfirming( false );
		}
	}, [ dispatch ] );

	if ( ! isModalOpen ) {
		return null;
	}

	return (
		<Modal
			title=""
			onRequestClose={ handleClose }
			className="aisa-modal"
			isDismissible={ ! isConfirming }
			shouldCloseOnClickOutside={ false }
		>
			<ModalHeader currentStep={ currentStep } />

			<div className="aisa-modal__content">
				{ currentStep === 1 && (
					<Step1ContentReview onRegenerate={ regenerate } />
				) }
				{ currentStep === 2 && (
					<Step2SeoGeneration onRegenerate={ regenerate } />
				) }
			</div>

			<div className="aisa-modal__footer">
				{ currentStep === 1 && (
					<>
						<Button
							variant="tertiary"
							onClick={ handleSkipStep1 }
							disabled={ isAnyLoading }
						>
							{ __( 'Skip to SEO', 'ai-seo-assistant' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ handleNextStep }
							disabled={ isAnyLoading }
						>
							{ isAnyLoading ? (
								<>
									<Spinner />
									{ __( 'Analyzing...', 'ai-seo-assistant' ) }
								</>
							) : (
								__( 'Continue to Step 2', 'ai-seo-assistant' )
							) }
						</Button>
					</>
				) }
				{ currentStep === 2 && (
					<>
						<Button
							variant="tertiary"
							onClick={ handlePrevStep }
							disabled={ isConfirming }
						>
							{ __( 'Back to Step 1', 'ai-seo-assistant' ) }
						</Button>
						<Button
							variant="link"
							onClick={ handleSkipAll }
							disabled={ isConfirming }
							className="aisa-modal__skip-btn"
						>
							{ __( 'Skip & Publish', 'ai-seo-assistant' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ handleConfirm }
							disabled={ isAnyLoading || isConfirming }
						>
							{ isConfirming ? (
								<>
									<Spinner />
									{ __( 'Saving...', 'ai-seo-assistant' ) }
								</>
							) : (
								__( 'Confirm SEO', 'ai-seo-assistant' )
							) }
						</Button>
					</>
				) }
			</div>
		</Modal>
	);
}
