<?php

namespace AiSeoAssistant\Bulk;

/**
 * Integrates with Action Scheduler for background bulk processing.
 * Falls back to WP-Cron if Action Scheduler is not available.
 */
class BulkScheduler
{
    public const HOOK_GENERATE = 'aisa_bulk_generate';
    public const HOOK_APPLY = 'aisa_bulk_apply';

    public function register(): void
    {
        add_action(self::HOOK_GENERATE, [$this, 'handleGenerate']);
        add_action(self::HOOK_APPLY, [$this, 'handleApply']);
    }

    /**
     * Schedule a batch for background generation.
     */
    public function scheduleGenerate(string $batchId): void
    {
        if ($this->hasActionScheduler()) {
            as_enqueue_async_action(self::HOOK_GENERATE, ['batch_id' => $batchId], 'aisa-bulk');
        } else {
            wp_schedule_single_event(time(), self::HOOK_GENERATE, [$batchId]);
        }
    }

    /**
     * Schedule a batch for background application.
     */
    public function scheduleApply(string $batchId, int $userId): void
    {
        if ($this->hasActionScheduler()) {
            as_enqueue_async_action(self::HOOK_APPLY, [
                'batch_id' => $batchId,
                'user_id' => $userId,
            ], 'aisa-bulk');
        } else {
            wp_schedule_single_event(time(), self::HOOK_APPLY, [$batchId, $userId]);
        }
    }

    public function handleGenerate(string $batchId): void
    {
        $processor = new BulkProcessor();
        $processor->generatePreview($batchId);
    }

    public function handleApply(string $batchId, int $userId = 0): void
    {
        $processor = new BulkProcessor();
        $processor->apply($batchId, $userId);
    }

    private function hasActionScheduler(): bool
    {
        return function_exists('as_enqueue_async_action');
    }
}
