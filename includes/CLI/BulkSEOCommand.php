<?php

namespace AiSeoAssistant\CLI;

use AiSeoAssistant\Bulk\BulkBatch;
use AiSeoAssistant\Bulk\BulkProcessor;

/**
 * AI SEO Assistant WP-CLI commands.
 *
 * ## EXAMPLES
 *
 *     wp aisa bulk-generate --post-type=post --limit=100
 *     wp aisa bulk-apply <batch-id>
 *     wp aisa bulk-rollback <batch-id>
 *     wp aisa bulk-status <batch-id>
 *     wp aisa stats --days=30
 */
class BulkSEOCommand
{
    /**
     * Generate SEO suggestions for posts in bulk.
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Post type to process. Default: post
     *
     * [--limit=<number>]
     * : Maximum posts to process. Default: 100, max: 500
     *
     * [--status=<status>]
     * : Post status filter. Default: publish
     *
     * [--only-unreviewed]
     * : Only process posts without _aisa_confirmed meta.
     *
     * [--dry-run]
     * : Show what would be processed without creating a batch.
     *
     * ## EXAMPLES
     *
     *     wp aisa bulk-generate --post-type=post --limit=50
     *     wp aisa bulk-generate --only-unreviewed --limit=200
     */
    public function bulk_generate($args, $assoc_args): void
    {
        $postType = $assoc_args['post-type'] ?? 'post';
        $limit = min((int) ($assoc_args['limit'] ?? 100), BulkBatch::MAX_POSTS_CLI);
        $status = $assoc_args['status'] ?? 'publish';
        $onlyUnreviewed = isset($assoc_args['only-unreviewed']);
        $dryRun = isset($assoc_args['dry-run']);

        $queryArgs = [
            'post_type' => $postType,
            'post_status' => $status,
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($onlyUnreviewed) {
            $queryArgs['meta_query'] = [
                [
                    'key' => '_aisa_confirmed',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        }

        $postIds = get_posts($queryArgs);

        if (empty($postIds)) {
            \WP_CLI::warning('No matching posts found.');
            return;
        }

        \WP_CLI::log(sprintf('Found %d posts to process.', count($postIds)));

        if ($dryRun) {
            foreach ($postIds as $postId) {
                $title = get_the_title($postId);
                \WP_CLI::log(sprintf('  #%d: %s', $postId, $title));
            }
            \WP_CLI::success('Dry run complete. No batch created.');
            return;
        }

        $batch = new BulkBatch();
        $batchId = $batch->create($postIds, get_current_user_id(), 'wp_cli');

        \WP_CLI::log(sprintf('Batch created: %s', $batchId));
        \WP_CLI::log('Generating previews...');

        $processor = new BulkProcessor();
        $progress = \WP_CLI\Utils\make_progress_bar('Processing', count($postIds));

        // Process synchronously in CLI
        $processor->generatePreview($batchId);
        $progress->finish();

        $batchData = $batch->get($batchId);
        $successful = 0;
        $failed = 0;

        foreach ($batchData['results'] ?? [] as $result) {
            if ($result['success'] ?? false) {
                $successful++;
            } else {
                $failed++;
            }
        }

        \WP_CLI::success(sprintf(
            'Preview generated: %d successful, %d failed. Batch ID: %s',
            $successful,
            $failed,
            $batchId
        ));
        \WP_CLI::log('Review with: wp aisa bulk-status ' . $batchId);
        \WP_CLI::log('Apply with: wp aisa bulk-apply ' . $batchId);
    }

    /**
     * Apply a batch of SEO suggestions.
     *
     * ## OPTIONS
     *
     * <batch-id>
     * : The batch ID to apply.
     *
     * [--yes]
     * : Skip confirmation prompt.
     */
    public function bulk_apply($args, $assoc_args): void
    {
        $batchId = $args[0];
        $batch = new BulkBatch();
        $batchData = $batch->get($batchId);

        if (!$batchData) {
            \WP_CLI::error('Batch not found: ' . $batchId);
        }

        if ($batchData['status'] !== BulkBatch::STATUS_PREVIEW) {
            \WP_CLI::error(sprintf('Batch status is "%s", must be "preview" to apply.', $batchData['status']));
        }

        $successful = count(array_filter($batchData['results'], fn($r) => $r['success'] ?? false));

        \WP_CLI::confirm(sprintf(
            'Apply %d SEO changes from batch %s?',
            $successful,
            $batchId
        ), $assoc_args);

        $processor = new BulkProcessor();
        $result = $processor->apply($batchId, get_current_user_id());

        \WP_CLI::success(sprintf(
            'Applied: %d, Errors: %d',
            $result['applied'] ?? 0,
            $result['errors'] ?? 0
        ));
    }

    /**
     * Rollback an applied batch.
     *
     * ## OPTIONS
     *
     * <batch-id>
     * : The batch ID to rollback.
     *
     * [--yes]
     * : Skip confirmation prompt.
     */
    public function bulk_rollback($args, $assoc_args): void
    {
        $batchId = $args[0];
        $batch = new BulkBatch();
        $batchData = $batch->get($batchId);

        if (!$batchData) {
            \WP_CLI::error('Batch not found: ' . $batchId);
        }

        if ($batchData['status'] !== BulkBatch::STATUS_APPLIED) {
            \WP_CLI::error(sprintf('Batch status is "%s", must be "applied" to rollback.', $batchData['status']));
        }

        \WP_CLI::confirm(sprintf(
            'Rollback %d posts from batch %s?',
            $batchData['total_posts'],
            $batchId
        ), $assoc_args);

        $processor = new BulkProcessor();
        $result = $processor->rollback($batchId, get_current_user_id());

        \WP_CLI::success(sprintf('Restored: %d posts', $result['restored'] ?? 0));
    }

    /**
     * Show status of a batch.
     *
     * ## OPTIONS
     *
     * <batch-id>
     * : The batch ID to check.
     *
     * [--format=<format>]
     * : Output format. Default: table. Options: table, json, yaml.
     */
    public function bulk_status($args, $assoc_args): void
    {
        $batchId = $args[0];
        $batch = new BulkBatch();
        $batchData = $batch->get($batchId);

        if (!$batchData) {
            \WP_CLI::error('Batch not found: ' . $batchId);
        }

        $format = $assoc_args['format'] ?? 'table';

        if ($format === 'json') {
            \WP_CLI::line(wp_json_encode($batchData, JSON_PRETTY_PRINT));
            return;
        }

        \WP_CLI::log(sprintf('Batch: %s', $batchId));
        \WP_CLI::log(sprintf('Status: %s', $batchData['status']));
        \WP_CLI::log(sprintf('Posts: %d total, %d processed', $batchData['total_posts'], $batchData['processed_posts']));
        \WP_CLI::log(sprintf('Source: %s', $batchData['source']));
        \WP_CLI::log(sprintf('Created: %s', $batchData['created_at']));

        if (!empty($batchData['results'])) {
            $items = [];
            foreach ($batchData['results'] as $postId => $result) {
                $items[] = [
                    'post_id' => $postId,
                    'title' => get_the_title((int) $postId),
                    'success' => ($result['success'] ?? false) ? 'yes' : 'no',
                    'error' => $result['error'] ?? '',
                    'applied' => ($result['applied'] ?? false) ? 'yes' : '',
                ];
            }

            \WP_CLI\Utils\format_items('table', $items, ['post_id', 'title', 'success', 'error', 'applied']);
        }
    }

    /**
     * Show usage statistics.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Number of days to look back. Default: 30
     *
     * [--format=<format>]
     * : Output format. Default: table
     */
    public function stats($args, $assoc_args): void
    {
        $days = (int) ($assoc_args['days'] ?? 30);

        $tracker = new \AiSeoAssistant\Tracking\TokenCostTracker();
        $usage = $tracker->getUsageSummary($days);
        $totalCost = $tracker->getTotalCost($days);

        \WP_CLI::log(sprintf('--- AI SEO Assistant Stats (last %d days) ---', $days));
        \WP_CLI::log(sprintf('Estimated total cost: $%s', number_format($totalCost, 4)));
        \WP_CLI::log('');

        if (!empty($usage)) {
            $items = [];
            foreach ($usage as $row) {
                $items[] = [
                    'model' => $row['model'],
                    'provider' => $row['provider'],
                    'calls' => $row['calls'],
                    'input_tokens' => number_format((int) $row['total_input_tokens']),
                    'output_tokens' => number_format((int) $row['total_output_tokens']),
                    'success' => $row['successful'],
                    'failed' => $row['failed'],
                ];
            }

            \WP_CLI\Utils\format_items(
                $assoc_args['format'] ?? 'table',
                $items,
                ['model', 'provider', 'calls', 'input_tokens', 'output_tokens', 'success', 'failed']
            );
        } else {
            \WP_CLI::log('No usage data found.');
        }
    }
}
