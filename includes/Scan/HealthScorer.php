<?php

namespace AiSeoAssistant\Scan;

class HealthScorer
{
    // CTR benchmarks by position (rough industry averages)
    private const CTR_BENCHMARKS = [
        1 => 28.0, 2 => 15.0, 3 => 11.0, 4 => 8.0, 5 => 7.0,
        6 => 5.0, 7 => 4.0, 8 => 3.5, 9 => 3.0, 10 => 2.5,
    ];

    private const THRESHOLD = 60;

    /**
     * Calculate health score (0-100) for a post based on GSC data.
     * Lower score = more problems = higher priority for suggestion.
     *
     * @return array{score: int, issues: array}
     */
    public static function score(array $gscData): array
    {
        $score = 100;
        $issues = [];

        // 1. Position trend penalty: dropped >2 positions
        if ($gscData['position_trend'] !== null && $gscData['position_trend'] > 2) {
            $penalty = min(30, (int) ($gscData['position_trend'] * 5));
            $score -= $penalty;
            $issues[] = [
                'type' => 'position_drop',
                'detail' => sprintf(
                    'Position dropped by %.1f (from %.1f to %.1f)',
                    $gscData['position_trend'],
                    $gscData['position_old'],
                    $gscData['position_recent']
                ),
                'severity' => $gscData['position_trend'] > 5 ? 'high' : 'medium',
            ];
        }

        // 2. CTR vs benchmark
        $topQuery = $gscData['top_queries'][0] ?? null;
        if ($topQuery && $topQuery['impressions'] > 50) {
            $position = max(1, min(10, (int) round($topQuery['position'])));
            $benchmark = self::CTR_BENCHMARKS[$position] ?? 2.5;
            $ctrRatio = $topQuery['ctr'] / $benchmark;

            if ($ctrRatio < 0.5) {
                $penalty = min(25, (int) ((1 - $ctrRatio) * 30));
                $score -= $penalty;
                $issues[] = [
                    'type' => 'low_ctr',
                    'detail' => sprintf(
                        'Top query "%s" has CTR %.1f%% vs benchmark %.1f%% for position %d',
                        $topQuery['query'],
                        $topQuery['ctr'],
                        $benchmark,
                        $position
                    ),
                    'severity' => $ctrRatio < 0.3 ? 'high' : 'medium',
                ];
            }
        }

        // 3. Impressions declining
        if ($gscData['impressions_old'] > 50 && $gscData['impressions_recent'] > 0) {
            $ratio = $gscData['impressions_recent'] / $gscData['impressions_old'];
            if ($ratio < 0.5) {
                $penalty = min(20, (int) ((1 - $ratio) * 25));
                $score -= $penalty;
                $issues[] = [
                    'type' => 'impressions_drop',
                    'detail' => sprintf(
                        'Impressions dropped from %d to %d (%.0f%% decline)',
                        $gscData['impressions_old'],
                        $gscData['impressions_recent'],
                        (1 - $ratio) * 100
                    ),
                    'severity' => $ratio < 0.3 ? 'high' : 'medium',
                ];
            }
        }

        // 4. Zero clicks despite impressions
        if ($gscData['total_impressions_28d'] > 200 && $gscData['total_clicks_28d'] === 0) {
            $score -= 15;
            $issues[] = [
                'type' => 'zero_clicks',
                'detail' => sprintf(
                    '%d impressions but 0 clicks in 28 days',
                    $gscData['total_impressions_28d']
                ),
                'severity' => 'high',
            ];
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'needs_attention' => $score < self::THRESHOLD,
        ];
    }

    public static function getThreshold(): int
    {
        return self::THRESHOLD;
    }
}
