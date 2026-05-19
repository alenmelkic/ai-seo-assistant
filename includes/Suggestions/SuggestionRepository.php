<?php

namespace AiSeoAssistant\Suggestions;

class SuggestionRepository
{
    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'aisa_suggestions';
    }

    public static function create(array $data): int
    {
        global $wpdb;

        $wpdb->insert(self::table(), [
            'post_id' => $data['post_id'],
            'type' => $data['type'],
            'current_value' => $data['current_value'] ?? null,
            'suggested_value' => $data['suggested_value'] ?? null,
            'reason' => $data['reason'] ?? null,
            'gsc_snapshot' => isset($data['gsc_snapshot']) ? wp_json_encode($data['gsc_snapshot']) : null,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public static function findById(int $id): ?Suggestion
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d",
            $id
        ));
        return $row ? Suggestion::fromRow($row) : null;
    }

    public static function query(array $args = []): array
    {
        global $wpdb;

        $table = self::table();
        $where = ['1=1'];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }

        if (!empty($args['post_id'])) {
            $where[] = 'post_id = %d';
            $params[] = $args['post_id'];
        }

        // Exclude snoozed
        if (!empty($args['exclude_snoozed'])) {
            $where[] = '(snoozed_until IS NULL OR snoozed_until < %s)';
            $params[] = current_time('mysql');
        }

        $whereClause = implode(' AND ', $where);
        $orderBy = $args['orderby'] ?? 'created_at';
        $order = strtoupper($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limit = min(absint($args['per_page'] ?? 20), 100);
        $offset = absint($args['offset'] ?? 0);

        $sql = "SELECT * FROM {$table} WHERE {$whereClause} ORDER BY {$orderBy} {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        return array_map([Suggestion::class, 'fromRow'], $rows ?: []);
    }

    public static function count(array $args = []): int
    {
        global $wpdb;

        $table = self::table();
        $where = ['1=1'];
        $params = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }

        $whereClause = implode(' AND ', $where);

        if (empty($params)) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$whereClause}");
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$whereClause}",
            ...$params
        ));
    }

    public static function updateStatus(int $id, string $status, ?int $userId = null): bool
    {
        global $wpdb;

        $data = [
            'status' => $status,
            'reviewed_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%s'];

        if ($userId !== null) {
            $data['reviewed_by'] = $userId;
            $formats[] = '%d';
        }

        return $wpdb->update(self::table(), $data, ['id' => $id], $formats, ['%d']) !== false;
    }

    public static function snooze(int $id, int $days = 7): bool
    {
        global $wpdb;

        $snoozedUntil = gmdate('Y-m-d H:i:s', strtotime("+{$days} days"));

        return $wpdb->update(
            self::table(),
            ['snoozed_until' => $snoozedUntil],
            ['id' => $id],
            ['%s'],
            ['%d']
        ) !== false;
    }

    public static function countPending(): int
    {
        return self::count(['status' => 'pending']);
    }

    /**
     * Expire old pending suggestions (older than 90 days).
     */
    public static function expireOld(int $daysOld = 90): int
    {
        global $wpdb;

        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE " . self::table() . " SET status = 'expired' WHERE status = 'pending' AND created_at < %s",
            $cutoff
        ));
    }
}
