<?php

namespace AiSeoAssistant\GSC;

use AiSeoAssistant\Support\Logger;

class GSCDataFetcher
{
    private const API_BASE = 'https://www.googleapis.com/webmasters/v3';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get GSC data for a specific post, with caching.
     */
    public static function getPostData(int $postId, bool $forceRefresh = false): ?array
    {
        if (!GSCAuthHandler::isConnected()) {
            return null;
        }

        $cacheKey = 'aisa_gsc_' . $postId;

        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $url = self::getPostUrl($postId);
        if (!$url) {
            return null;
        }

        $property = GSCAuthHandler::getSelectedProperty();
        if (empty($property)) {
            return null;
        }

        $data = self::fetchSearchAnalytics($property, $url);
        if ($data === null) {
            return null;
        }

        set_transient($cacheKey, $data, self::CACHE_TTL);
        return $data;
    }

    /**
     * Fetch search analytics from GSC API.
     */
    private static function fetchSearchAnalytics(string $property, string $pageUrl): ?array
    {
        $accessToken = GSCAuthHandler::getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $endDate = gmdate('Y-m-d');
        $startDate28 = gmdate('Y-m-d', strtotime('-28 days'));
        $startDate7 = gmdate('Y-m-d', strtotime('-7 days'));

        // Fetch 28-day data with query dimension
        $queries28 = self::apiRequest($property, $accessToken, [
            'startDate' => $startDate28,
            'endDate' => $endDate,
            'dimensions' => ['query'],
            'dimensionFilterGroups' => [[
                'filters' => [[
                    'dimension' => 'page',
                    'operator' => 'equals',
                    'expression' => $pageUrl,
                ]],
            ]],
            'rowLimit' => 10,
        ]);

        // Fetch 7-day data for trend comparison
        $queries7 = self::apiRequest($property, $accessToken, [
            'startDate' => $startDate7,
            'endDate' => $endDate,
            'dimensionFilterGroups' => [[
                'filters' => [[
                    'dimension' => 'page',
                    'operator' => 'equals',
                    'expression' => $pageUrl,
                ]],
            ]],
        ]);

        // Fetch 28-day totals (21-28 days ago) for trend
        $startDateOld = gmdate('Y-m-d', strtotime('-28 days'));
        $endDateOld = gmdate('Y-m-d', strtotime('-21 days'));
        $queriesOld = self::apiRequest($property, $accessToken, [
            'startDate' => $startDateOld,
            'endDate' => $endDateOld,
            'dimensionFilterGroups' => [[
                'filters' => [[
                    'dimension' => 'page',
                    'operator' => 'equals',
                    'expression' => $pageUrl,
                ]],
            ]],
        ]);

        if ($queries28 === null) {
            return null;
        }

        // Build top queries
        $topQueries = [];
        foreach ($queries28['rows'] ?? [] as $row) {
            $topQueries[] = [
                'query' => $row['keys'][0] ?? '',
                'clicks' => $row['clicks'] ?? 0,
                'impressions' => $row['impressions'] ?? 0,
                'ctr' => round(($row['ctr'] ?? 0) * 100, 2),
                'position' => round($row['position'] ?? 0, 1),
            ];
        }

        // Calculate trends
        $recentPosition = $queries7['rows'][0]['position'] ?? null;
        $oldPosition = $queriesOld['rows'][0]['position'] ?? null;
        $recentImpressions = $queries7['rows'][0]['impressions'] ?? 0;
        $oldImpressions = $queriesOld['rows'][0]['impressions'] ?? 0;

        return [
            'top_queries' => $topQueries,
            'position_recent' => $recentPosition ? round($recentPosition, 1) : null,
            'position_old' => $oldPosition ? round($oldPosition, 1) : null,
            'position_trend' => ($recentPosition && $oldPosition)
                ? round($recentPosition - $oldPosition, 1)
                : null,
            'impressions_recent' => $recentImpressions,
            'impressions_old' => $oldImpressions,
            'total_clicks_28d' => array_sum(array_column($topQueries, 'clicks')),
            'total_impressions_28d' => array_sum(array_column($topQueries, 'impressions')),
            'fetched_at' => current_time('mysql'),
        ];
    }

    private static function apiRequest(string $property, string $accessToken, array $body): ?array
    {
        $encodedProperty = urlencode($property);
        $url = self::API_BASE . "/sites/{$encodedProperty}/searchAnalytics/query";

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            Logger::error('GSC API request failed: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            Logger::error('GSC API returned HTTP ' . $code);
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private static function getPostUrl(int $postId): ?string
    {
        $permalink = get_permalink($postId);
        return $permalink ?: null;
    }

    /**
     * Clear cached GSC data for a post.
     */
    public static function clearCache(int $postId): void
    {
        delete_transient('aisa_gsc_' . $postId);
    }
}
