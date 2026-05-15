<?php

namespace AiSeoAssistant\REST;

use AiSeoAssistant\Bulk\BulkBatch;
use AiSeoAssistant\Bulk\BulkProcessor;
use AiSeoAssistant\Bulk\BulkScheduler;

class BulkController
{
    public function registerRoutes(): void
    {
        register_rest_route('ai-seo-assistant/v1', '/bulk/create', [
            'methods' => 'POST',
            'callback' => [$this, 'createBatch'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'post_ids' => [
                    'required' => true,
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'validate_callback' => function ($param) {
                        return is_array($param) && count($param) > 0 && count($param) <= BulkBatch::MAX_POSTS_UI;
                    },
                ],
            ],
        ]);

        register_rest_route('ai-seo-assistant/v1', '/bulk/(?P<batch_id>[a-f0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getBatch'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('ai-seo-assistant/v1', '/bulk/(?P<batch_id>[a-f0-9-]+)/apply', [
            'methods' => 'POST',
            'callback' => [$this, 'applyBatch'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('ai-seo-assistant/v1', '/bulk/(?P<batch_id>[a-f0-9-]+)/rollback', [
            'methods' => 'POST',
            'callback' => [$this, 'rollbackBatch'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('ai-seo-assistant/v1', '/bulk/batches', [
            'methods' => 'GET',
            'callback' => [$this, 'listBatches'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('ai-seo-assistant/v1', '/audit', [
            'methods' => 'GET',
            'callback' => [$this, 'getAuditLog'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'post_id' => ['type' => 'integer'],
                'action' => ['type' => 'string'],
                'per_page' => ['type' => 'integer', 'default' => 50],
                'page' => ['type' => 'integer', 'default' => 1],
            ],
        ]);

        register_rest_route('ai-seo-assistant/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getStats'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_aisa');
    }

    public function createBatch(\WP_REST_Request $request): \WP_REST_Response
    {
        $postIds = array_map('intval', $request->get_param('post_ids'));
        $userId = get_current_user_id();

        $batch = new BulkBatch();
        $batchId = $batch->create($postIds, $userId, 'admin_ui');

        // Schedule background generation
        $scheduler = new BulkScheduler();
        $scheduler->scheduleGenerate($batchId);

        return new \WP_REST_Response([
            'success' => true,
            'batch_id' => $batchId,
            'total_posts' => count($postIds),
        ]);
    }

    public function getBatch(\WP_REST_Request $request): \WP_REST_Response
    {
        $batchId = $request->get_param('batch_id');
        $batch = new BulkBatch();
        $data = $batch->get($batchId);

        if (!$data) {
            return new \WP_REST_Response(['error' => 'Batch not found'], 404);
        }

        return new \WP_REST_Response(['success' => true, 'batch' => $data]);
    }

    public function applyBatch(\WP_REST_Request $request): \WP_REST_Response
    {
        $batchId = $request->get_param('batch_id');
        $userId = get_current_user_id();

        $processor = new BulkProcessor();
        $result = $processor->apply($batchId, $userId);

        if (isset($result['error'])) {
            return new \WP_REST_Response(['success' => false, 'error' => $result['error']], 400);
        }

        return new \WP_REST_Response(['success' => true, ...$result]);
    }

    public function rollbackBatch(\WP_REST_Request $request): \WP_REST_Response
    {
        $batchId = $request->get_param('batch_id');
        $userId = get_current_user_id();

        $processor = new BulkProcessor();
        $result = $processor->rollback($batchId, $userId);

        if (isset($result['error'])) {
            return new \WP_REST_Response(['success' => false, 'error' => $result['error']], 400);
        }

        return new \WP_REST_Response(['success' => true, ...$result]);
    }

    public function listBatches(\WP_REST_Request $request): \WP_REST_Response
    {
        $batch = new BulkBatch();
        $batches = $batch->getRecent(20);

        return new \WP_REST_Response(['success' => true, 'batches' => $batches]);
    }

    public function getAuditLog(\WP_REST_Request $request): \WP_REST_Response
    {
        $auditLogger = new \AiSeoAssistant\Audit\AuditLogger();

        $perPage = (int) $request->get_param('per_page');
        $page = (int) $request->get_param('page');
        $offset = ($page - 1) * $perPage;

        $filters = array_filter([
            'post_id' => $request->get_param('post_id'),
            'action' => $request->get_param('action'),
        ]);

        $entries = $auditLogger->getRecent($perPage, $offset, $filters);

        return new \WP_REST_Response(['success' => true, 'entries' => $entries]);
    }

    public function getStats(\WP_REST_Request $request): \WP_REST_Response
    {
        $days = (int) $request->get_param('days');

        $tracker = new \AiSeoAssistant\Tracking\TokenCostTracker();
        $auditLogger = new \AiSeoAssistant\Audit\AuditLogger();

        return new \WP_REST_Response([
            'success' => true,
            'usage' => $tracker->getUsageSummary($days),
            'cost' => $tracker->getTotalCost($days),
            'daily' => $tracker->getDailyUsage($days),
            'audit' => $auditLogger->getStats($days),
        ]);
    }
}
