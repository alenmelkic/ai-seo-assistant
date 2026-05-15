<?php

namespace AiSeoAssistant\Bulk;

use AiSeoAssistant\AI\AIServiceFactory;
use AiSeoAssistant\SEO\SEOAdapterFactory;
use AiSeoAssistant\Audit\AuditLogger;
use AiSeoAssistant\Support\ContentExtractor;
use AiSeoAssistant\Prompts\PromptRegistry;
use AiSeoAssistant\Resilience\RetryHandler;

class BulkProcessor
{
    private BulkBatch $batch;
    private AuditLogger $auditLogger;

    public function __construct()
    {
        $this->batch = new BulkBatch();
        $this->auditLogger = new AuditLogger();
    }

    /**
     * Start processing a batch — generates AI suggestions in preview mode.
     */
    public function generatePreview(string $batchId): void
    {
        $batchData = $this->batch->get($batchId);
        if (!$batchData || $batchData['status'] !== BulkBatch::STATUS_PENDING) {
            return;
        }

        $this->batch->update($batchId, ['status' => BulkBatch::STATUS_PROCESSING]);

        $postIds = $batchData['post_ids'];
        $results = [];
        $processed = 0;

        $aiClient = AIServiceFactory::create();
        $seoAdapter = SEOAdapterFactory::create();
        $promptRegistry = new PromptRegistry();
        $retryHandler = new RetryHandler();

        foreach ($postIds as $postId) {
            try {
                $post = get_post($postId);
                if (!$post) {
                    $results[$postId] = ['error' => 'Post not found'];
                    $processed++;
                    continue;
                }

                $content = ContentExtractor::extract($post);

                // Generate meta
                $metaPrompt = $promptRegistry->get('generate_meta');
                $metaResponse = $retryHandler->execute(
                    fn() => $aiClient->generate($metaPrompt->render(['content' => $content]))
                );

                $current = [
                    'meta_title' => $seoAdapter->getMetaTitle($postId),
                    'meta_description' => $seoAdapter->getMetaDescription($postId),
                ];

                $suggestion = $metaResponse->success ? $metaResponse->getDecodedContent() : null;

                $results[$postId] = [
                    'current' => $current,
                    'suggestion' => $suggestion,
                    'success' => $metaResponse->success,
                    'error' => $metaResponse->errorMessage,
                ];
            } catch (\Throwable $e) {
                $results[$postId] = ['error' => $e->getMessage(), 'success' => false];
            }

            $processed++;
            $this->batch->update($batchId, [
                'processed_posts' => $processed,
                'results' => $results,
            ]);
        }

        $this->batch->update($batchId, [
            'status' => BulkBatch::STATUS_PREVIEW,
            'results' => $results,
        ]);
    }

    /**
     * Apply the previewed suggestions to actual posts.
     */
    public function apply(string $batchId, int $userId): array
    {
        $batchData = $this->batch->get($batchId);
        if (!$batchData || $batchData['status'] !== BulkBatch::STATUS_PREVIEW) {
            return ['error' => 'Batch not in preview state'];
        }

        $seoAdapter = SEOAdapterFactory::create();
        $results = $batchData['results'];
        $applied = 0;
        $errors = 0;

        foreach ($results as $postId => $result) {
            if (!($result['success'] ?? false) || empty($result['suggestion'])) {
                continue;
            }

            try {
                $suggestion = $result['suggestion'];

                // Store snapshot for rollback
                $snapshot = [
                    'meta_title' => $seoAdapter->getMetaTitle((int) $postId),
                    'meta_description' => $seoAdapter->getMetaDescription((int) $postId),
                ];
                update_post_meta((int) $postId, '_aisa_bulk_snapshot_' . $batchId, $snapshot);

                // Apply changes
                if (!empty($suggestion['meta_title'])) {
                    $seoAdapter->setMetaTitle((int) $postId, $suggestion['meta_title']);
                    $this->auditLogger->logBulk(
                        (int) $postId,
                        $userId,
                        'meta_title',
                        $snapshot['meta_title'],
                        $suggestion['meta_title'],
                        $batchId
                    );
                }

                if (!empty($suggestion['meta_description'])) {
                    $seoAdapter->setMetaDescription((int) $postId, $suggestion['meta_description']);
                    $this->auditLogger->logBulk(
                        (int) $postId,
                        $userId,
                        'meta_description',
                        $snapshot['meta_description'],
                        $suggestion['meta_description'],
                        $batchId
                    );
                }

                // Mark as confirmed
                update_post_meta((int) $postId, '_aisa_confirmed', [
                    'date' => current_time('mysql'),
                    'user_id' => $userId,
                    'version' => AISA_VERSION,
                    'source' => 'bulk',
                    'batch_id' => $batchId,
                ]);

                $results[$postId]['applied'] = true;
                $applied++;
            } catch (\Throwable $e) {
                $results[$postId]['apply_error'] = $e->getMessage();
                $errors++;
            }
        }

        $this->batch->update($batchId, [
            'status' => BulkBatch::STATUS_APPLIED,
            'results' => $results,
        ]);

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * Rollback an applied batch.
     */
    public function rollback(string $batchId, int $userId): array
    {
        $batchData = $this->batch->get($batchId);
        if (!$batchData || $batchData['status'] !== BulkBatch::STATUS_APPLIED) {
            return ['error' => 'Batch not in applied state'];
        }

        $seoAdapter = SEOAdapterFactory::create();
        $restored = 0;

        foreach ($batchData['post_ids'] as $postId) {
            $snapshot = get_post_meta($postId, '_aisa_bulk_snapshot_' . $batchId, true);
            if (!$snapshot) {
                continue;
            }

            $currentTitle = $seoAdapter->getMetaTitle($postId);
            $currentDesc = $seoAdapter->getMetaDescription($postId);

            if (isset($snapshot['meta_title'])) {
                $seoAdapter->setMetaTitle($postId, $snapshot['meta_title']);
                $this->auditLogger->logRollback(
                    $postId, $userId, 'meta_title',
                    $currentTitle, $snapshot['meta_title'], $batchId
                );
            }

            if (isset($snapshot['meta_description'])) {
                $seoAdapter->setMetaDescription($postId, $snapshot['meta_description']);
                $this->auditLogger->logRollback(
                    $postId, $userId, 'meta_description',
                    $currentDesc, $snapshot['meta_description'], $batchId
                );
            }

            delete_post_meta($postId, '_aisa_bulk_snapshot_' . $batchId);
            $restored++;
        }

        $this->batch->update($batchId, ['status' => BulkBatch::STATUS_ROLLED_BACK]);

        return ['restored' => $restored];
    }
}
