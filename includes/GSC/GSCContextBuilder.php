<?php

namespace AiSeoAssistant\GSC;

class GSCContextBuilder
{
    /**
     * Build the GSC context string for injection into AI prompts.
     * Returns empty string if no GSC data is available.
     */
    public static function build(int $postId): string
    {
        $data = GSCDataFetcher::getPostData($postId);
        if (empty($data) || empty($data['top_queries'])) {
            return '';
        }

        $lines = [
            'GSC DATA FOR THIS ARTICLE (last 28 days):',
            '- Top queries:',
        ];

        foreach ($data['top_queries'] as $i => $query) {
            $num = $i + 1;
            $lines[] = sprintf(
                '  %d. "%s" — %d impressions, CTR %.1f%%, avg position %.1f',
                $num,
                $query['query'],
                $query['impressions'],
                $query['ctr'],
                $query['position']
            );
        }

        // Position trend
        if ($data['position_old'] !== null && $data['position_recent'] !== null) {
            $direction = $data['position_trend'] > 0 ? 'dropped' : 'improved';
            $lines[] = sprintf(
                '- Trend: position %s from %.1f (28 days ago) to %.1f (now)',
                $direction,
                $data['position_old'],
                $data['position_recent']
            );
        }

        // Impressions trend
        if ($data['impressions_old'] > 0) {
            $pctChange = round(
                (($data['impressions_recent'] - $data['impressions_old']) / $data['impressions_old']) * 100,
                1
            );
            $lines[] = sprintf(
                '- Impressions trend: %s%s%% (recent 7d vs older period)',
                $pctChange >= 0 ? '+' : '',
                $pctChange
            );
        }

        // Identify low-CTR opportunities
        $lowCtrQueries = array_filter(
            $data['top_queries'],
            fn($q) => $q['ctr'] < 2.0 && $q['impressions'] > 100
        );
        if (!empty($lowCtrQueries)) {
            $lines[] = '- Low CTR alerts:';
            foreach ($lowCtrQueries as $q) {
                $lines[] = sprintf(
                    '  * "%s" has %d impressions but only %.1f%% CTR — meta description may not match search intent',
                    $q['query'],
                    $q['impressions'],
                    $q['ctr']
                );
            }
        }

        $lines[] = '';
        $lines[] = 'Use this data to recommend improvements. Especially optimize for queries with high impressions but low CTR.';

        return implode("\n", $lines);
    }

    /**
     * Build a compact GSC snapshot for storage (used in suggestions table).
     */
    public static function buildSnapshot(int $postId): ?array
    {
        $data = GSCDataFetcher::getPostData($postId);
        if (empty($data)) {
            return null;
        }

        return [
            'position_avg' => $data['position_recent'],
            'impressions_7d' => $data['impressions_recent'],
            'total_clicks_28d' => $data['total_clicks_28d'],
            'total_impressions_28d' => $data['total_impressions_28d'],
            'top_query' => $data['top_queries'][0]['query'] ?? null,
            'top_query_ctr' => $data['top_queries'][0]['ctr'] ?? null,
            'captured_at' => current_time('mysql'),
        ];
    }
}
