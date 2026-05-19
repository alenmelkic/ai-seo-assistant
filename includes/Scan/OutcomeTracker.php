<?php

namespace AiSeoAssistant\Scan;

use AiSeoAssistant\GSC\GSCContextBuilder;
use AiSeoAssistant\GSC\GSCDataFetcher;
use AiSeoAssistant\Suggestions\SuggestionRepository;
use AiSeoAssistant\Support\Logger;

class OutcomeTracker
{
    private const CRON_HOOK = 'aisa_outcome_check';
    private const DAYS_AFTER = 28;

    /**
     * Schedule the daily outcome check.
     */
    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 3600, 'daily', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Check accepted suggestions that are 28+ days old and compare GSC before/after.
     */
    public static function check(): void
    {
        global $wpdb;

        $suggestionsTable = $wpdb->prefix . 'aisa_suggestions';
        $outcomesTable = $wpdb->prefix . 'aisa_outcomes';

        // Find accepted suggestions from ~28 days ago that haven't been measured yet
        $cutoffStart = gmdate('Y-m-d H:i:s', strtotime('-' . (self::DAYS_AFTER + 3) . ' days'));
        $cutoffEnd = gmdate('Y-m-d H:i:s', strtotime('-' . self::DAYS_AFTER . ' days'));

        $suggestions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.* FROM {$suggestionsTable} s
            LEFT JOIN {$outcomesTable} o ON o.suggestion_id = s.id
            WHERE s.status = 'accepted'
            AND s.reviewed_at BETWEEN %s AND %s
            AND o.id IS NULL
            LIMIT 50",
            $cutoffStart,
            $cutoffEnd
        ));

        if (empty($suggestions)) {
            return;
        }

        $measured = 0;

        foreach ($suggestions as $row) {
            try {
                self::measureOutcome($row);
                $measured++;
            } catch (\Throwable $e) {
                Logger::error("Outcome measurement failed for suggestion {$row->id}: " . $e->getMessage());
            }
        }

        if ($measured > 0) {
            Logger::info("Measured outcomes for {$measured} suggestions");
        }
    }

    private static function measureOutcome(object $row): void
    {
        global $wpdb;

        $gscBefore = $row->gsc_snapshot ? json_decode($row->gsc_snapshot, true) : null;
        $gscAfterData = GSCDataFetcher::getPostData((int) $row->post_id, true);

        if (!$gscAfterData) {
            return;
        }

        $gscAfter = GSCContextBuilder::buildSnapshot((int) $row->post_id);

        // Calculate changes
        $positionBefore = $gscBefore['position_avg'] ?? null;
        $positionAfter = $gscAfter['position_avg'] ?? null;
        $positionChange = ($positionBefore !== null && $positionAfter !== null)
            ? round($positionBefore - $positionAfter, 1) // Positive = improvement (lower position number)
            : null;

        $ctrBefore = $gscBefore['top_query_ctr'] ?? null;
        $ctrAfter = $gscAfter['top_query_ctr'] ?? null;
        $ctrChange = ($ctrBefore !== null && $ctrAfter !== null)
            ? round($ctrAfter - $ctrBefore, 2)
            : null;

        $impressionsBefore = $gscBefore['total_impressions_28d'] ?? 0;
        $impressionsAfter = $gscAfter['total_impressions_28d'] ?? 0;
        $impressionsChange = $impressionsAfter - $impressionsBefore;

        $wpdb->insert($wpdb->prefix . 'aisa_outcomes', [
            'suggestion_id' => (int) $row->id,
            'post_id' => (int) $row->post_id,
            'type' => $row->type,
            'gsc_before' => $row->gsc_snapshot,
            'gsc_after' => wp_json_encode($gscAfter),
            'position_change' => $positionChange,
            'ctr_change' => $ctrChange,
            'impressions_change' => $impressionsChange,
            'measured_at' => current_time('mysql'),
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']);
    }

    /**
     * Get aggregate outcome stats for the dashboard widget.
     */
    public static function getStats(int $days = 30): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aisa_outcomes';
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_measured,
                AVG(position_change) as avg_position_change,
                AVG(ctr_change) as avg_ctr_change,
                SUM(CASE WHEN position_change > 0 THEN 1 ELSE 0 END) as improved_count,
                SUM(CASE WHEN ctr_change > 0 THEN 1 ELSE 0 END) as ctr_improved_count
            FROM {$table}
            WHERE measured_at >= %s",
            $cutoff
        ));

        $acceptedCount = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aisa_suggestions WHERE status = 'accepted' AND reviewed_at >= %s",
            $cutoff
        ));

        return [
            'total_measured' => (int) ($row->total_measured ?? 0),
            'total_accepted' => (int) $acceptedCount,
            'avg_position_change' => $row->avg_position_change !== null ? round((float) $row->avg_position_change, 1) : null,
            'avg_ctr_change' => $row->avg_ctr_change !== null ? round((float) $row->avg_ctr_change, 2) : null,
            'improved_count' => (int) ($row->improved_count ?? 0),
            'ctr_improved_count' => (int) ($row->ctr_improved_count ?? 0),
        ];
    }
}
