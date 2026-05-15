<?php

namespace AiSeoAssistant\Bulk;

class BulkBatch
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PREVIEW = 'preview';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_FAILED = 'failed';

    public const MAX_POSTS_UI = 50;
    public const MAX_POSTS_CLI = 500;

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aisa_bulk_batches';
    }

    /**
     * Create a new batch.
     */
    public function create(array $postIds, int $userId, string $source = 'admin_ui'): string
    {
        global $wpdb;

        $batchId = wp_generate_uuid4();

        $wpdb->insert($this->table, [
            'batch_id' => $batchId,
            'user_id' => $userId,
            'post_ids' => wp_json_encode($postIds),
            'total_posts' => count($postIds),
            'processed_posts' => 0,
            'status' => self::STATUS_PENDING,
            'source' => $source,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s']);

        return $batchId;
    }

    /**
     * Get batch by ID.
     */
    public function get(string $batchId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE batch_id = %s", $batchId),
            ARRAY_A
        );

        if ($row) {
            $row['post_ids'] = json_decode($row['post_ids'], true);
            $row['results'] = $row['results'] ? json_decode($row['results'], true) : [];
        }

        return $row;
    }

    /**
     * Update batch status and progress.
     */
    public function update(string $batchId, array $data): void
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        if (isset($data['results'])) {
            $data['results'] = wp_json_encode($data['results']);
        }

        $wpdb->update(
            $this->table,
            $data,
            ['batch_id' => $batchId],
        );
    }

    /**
     * Get recent batches for admin listing.
     */
    public function getRecent(int $limit = 20): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row['post_ids'] = json_decode($row['post_ids'], true);
            $row['results'] = $row['results'] ? json_decode($row['results'], true) : [];
        }

        return $rows;
    }
}
