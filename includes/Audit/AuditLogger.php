<?php

namespace AiSeoAssistant\Audit;

class AuditLogger
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aisa_audit_log';
    }

    public function log(AuditEntry $entry): int
    {
        global $wpdb;

        $data = $entry->toArray();

        $wpdb->insert($this->table, $data, [
            '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Log a confirm action — records what the editor decided for each field.
     */
    public function logConfirm(int $postId, int $userId, string $field, ?string $previous, ?string $newValue, ?string $aiSuggestion, string $source = 'modal'): void
    {
        $action = $previous === $newValue ? 'kept' : ($newValue === $aiSuggestion ? 'accepted' : 'modified');

        $this->log(new AuditEntry(
            postId: $postId,
            userId: $userId,
            action: $action,
            field: $field,
            previousValue: $previous,
            newValue: $newValue,
            aiSuggestion: $aiSuggestion,
            source: $source,
        ));
    }

    /**
     * Log a skip/bypass action.
     */
    public function logSkip(int $postId, int $userId, string $reason = 'user_skipped'): void
    {
        $this->log(new AuditEntry(
            postId: $postId,
            userId: $userId,
            action: 'skipped',
            field: 'all',
            previousValue: null,
            newValue: null,
            aiSuggestion: null,
            source: $reason,
        ));
    }

    /**
     * Log a bulk operation action.
     */
    public function logBulk(int $postId, int $userId, string $field, ?string $previous, ?string $newValue, string $batchId): void
    {
        $this->log(new AuditEntry(
            postId: $postId,
            userId: $userId,
            action: 'bulk_applied',
            field: $field,
            previousValue: $previous,
            newValue: $newValue,
            aiSuggestion: $newValue,
            source: 'bulk',
            metadata: ['batch_id' => $batchId],
        ));
    }

    /**
     * Log a rollback action.
     */
    public function logRollback(int $postId, int $userId, string $field, ?string $previous, ?string $restored, string $batchId): void
    {
        $this->log(new AuditEntry(
            postId: $postId,
            userId: $userId,
            action: 'rollback',
            field: $field,
            previousValue: $previous,
            newValue: $restored,
            aiSuggestion: null,
            source: 'bulk_rollback',
            metadata: ['batch_id' => $batchId],
        ));
    }

    /**
     * Get audit history for a post.
     */
    public function getPostHistory(int $postId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE post_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $postId,
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    /**
     * Get audit stats for dashboard.
     */
    public function getStats(int $days = 30): array
    {
        global $wpdb;

        $since = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $totals = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN action = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN action = 'modified' THEN 1 ELSE 0 END) as modified,
                    SUM(CASE WHEN action = 'kept' THEN 1 ELSE 0 END) as kept,
                    SUM(CASE WHEN action = 'skipped' THEN 1 ELSE 0 END) as skipped,
                    SUM(CASE WHEN action = 'bulk_applied' THEN 1 ELSE 0 END) as bulk_applied,
                    SUM(CASE WHEN action = 'rollback' THEN 1 ELSE 0 END) as rollbacks,
                    COUNT(DISTINCT post_id) as posts_affected
                FROM {$this->table}
                WHERE created_at >= %s",
                $since
            ),
            ARRAY_A
        );

        return $totals ?: [];
    }

    /**
     * Get recent entries for admin listing.
     */
    public function getRecent(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['post_id'])) {
            $where[] = 'post_id = %d';
            $params[] = (int) $filters['post_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = %s';
            $params[] = sanitize_text_field($filters['action']);
        }

        if (!empty($filters['source'])) {
            $where[] = 'source = %s';
            $params[] = sanitize_text_field($filters['source']);
        }

        $where_clause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $query = "SELECT * FROM {$this->table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        return $wpdb->get_results(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );
    }
}
