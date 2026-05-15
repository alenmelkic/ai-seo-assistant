<?php

namespace AiSeoAssistant\Tracking;

use AiSeoAssistant\AI\AIResponse;

class TokenCostTracker
{
    /**
     * Approximate cost per 1K tokens by model.
     * Input / output pricing in USD.
     */
    private const MODEL_PRICING = [
        'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
        'gpt-4o' => ['input' => 0.0025, 'output' => 0.01],
        'claude-sonnet-4-7' => ['input' => 0.003, 'output' => 0.015],
        'claude-haiku-4-5' => ['input' => 0.0008, 'output' => 0.004],
    ];

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aisa_log';
    }

    /**
     * Calculate estimated cost for an AI response.
     */
    public function estimateCost(AIResponse $response, string $model): float
    {
        $pricing = self::MODEL_PRICING[$model] ?? self::MODEL_PRICING['gpt-4o-mini'];

        $inputCost = ($response->inputTokens / 1000) * $pricing['input'];
        $outputCost = ($response->outputTokens / 1000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Get token usage summary for a time period.
     */
    public function getUsageSummary(int $days = 30): array
    {
        global $wpdb;

        $since = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    provider,
                    model,
                    COUNT(*) as calls,
                    SUM(input_tokens) as total_input_tokens,
                    SUM(output_tokens) as total_output_tokens,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed
                FROM {$this->table}
                WHERE created_at >= %s
                GROUP BY provider, model
                ORDER BY calls DESC",
                $since
            ),
            ARRAY_A
        );
    }

    /**
     * Get total estimated cost for a time period.
     */
    public function getTotalCost(int $days = 30): float
    {
        $usage = $this->getUsageSummary($days);
        $total = 0.0;

        foreach ($usage as $row) {
            $model = $row['model'] ?? 'gpt-4o-mini';
            $pricing = self::MODEL_PRICING[$model] ?? self::MODEL_PRICING['gpt-4o-mini'];

            $inputCost = ((int) $row['total_input_tokens'] / 1000) * $pricing['input'];
            $outputCost = ((int) $row['total_output_tokens'] / 1000) * $pricing['output'];
            $total += $inputCost + $outputCost;
        }

        return round($total, 4);
    }

    /**
     * Get daily usage for chart data.
     */
    public function getDailyUsage(int $days = 30): array
    {
        global $wpdb;

        $since = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    DATE(created_at) as date,
                    COUNT(*) as calls,
                    SUM(input_tokens) as input_tokens,
                    SUM(output_tokens) as output_tokens
                FROM {$this->table}
                WHERE created_at >= %s
                GROUP BY DATE(created_at)
                ORDER BY date ASC",
                $since
            ),
            ARRAY_A
        );
    }

    /**
     * Get per-user usage breakdown.
     */
    public function getPerUserUsage(int $days = 30): array
    {
        global $wpdb;

        $since = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    user_id,
                    COUNT(*) as calls,
                    SUM(input_tokens) as input_tokens,
                    SUM(output_tokens) as output_tokens
                FROM {$this->table}
                WHERE created_at >= %s
                GROUP BY user_id
                ORDER BY calls DESC",
                $since
            ),
            ARRAY_A
        );
    }
}
