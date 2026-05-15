<?php

namespace AiSeoAssistant\Prompts;

class ABTest
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aisa_ab_tests';
    }

    /**
     * Create a new A/B test for a prompt ability.
     */
    public function create(string $ability, string $variantA, string $variantB, int $trafficSplit = 50): int
    {
        global $wpdb;

        $wpdb->insert($this->table, [
            'ability' => $ability,
            'variant_a' => $variantA,
            'variant_b' => $variantB,
            'traffic_split' => $trafficSplit,
            'status' => 'active',
            'impressions_a' => 0,
            'impressions_b' => 0,
            'accepts_a' => 0,
            'accepts_b' => 0,
            'created_at' => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Get the active test for an ability, if any.
     */
    public function getActiveTest(string $ability): ?array
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE ability = %s AND status = 'active' LIMIT 1",
                $ability
            ),
            ARRAY_A
        );
    }

    /**
     * Assign a variant to a user/post combination.
     * Uses deterministic hashing for consistency.
     */
    public function assignVariant(int $testId, int $postId, int $trafficSplit): string
    {
        $hash = crc32($testId . '-' . $postId);
        $bucket = abs($hash) % 100;

        return $bucket < $trafficSplit ? 'a' : 'b';
    }

    /**
     * Get the prompt version to use, factoring in A/B tests.
     */
    public function resolvePromptVersion(string $ability): string
    {
        $test = $this->getActiveTest($ability);

        if (!$test) {
            // No active test, use the default version
            $versions = get_option('aisa_prompt_versions', []);
            return $versions[$ability] ?? 'v1';
        }

        $postId = get_the_ID() ?: 0;
        $variant = $this->assignVariant(
            (int) $test['id'],
            $postId,
            (int) $test['traffic_split']
        );

        return $variant === 'a' ? $test['variant_a'] : $test['variant_b'];
    }

    /**
     * Record an impression for a variant.
     */
    public function recordImpression(int $testId, string $variant): void
    {
        global $wpdb;

        $column = $variant === 'a' ? 'impressions_a' : 'impressions_b';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table} SET {$column} = {$column} + 1 WHERE id = %d",
                $testId
            )
        );
    }

    /**
     * Record an accept (user accepted the AI suggestion) for a variant.
     */
    public function recordAccept(int $testId, string $variant): void
    {
        global $wpdb;

        $column = $variant === 'a' ? 'accepts_a' : 'accepts_b';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table} SET {$column} = {$column} + 1 WHERE id = %d",
                $testId
            )
        );
    }

    /**
     * Get test results with statistical significance indicator.
     */
    public function getResults(int $testId): ?array
    {
        global $wpdb;

        $test = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $testId),
            ARRAY_A
        );

        if (!$test) {
            return null;
        }

        $rateA = $test['impressions_a'] > 0
            ? (int) $test['accepts_a'] / (int) $test['impressions_a']
            : 0;
        $rateB = $test['impressions_b'] > 0
            ? (int) $test['accepts_b'] / (int) $test['impressions_b']
            : 0;

        $totalImpressions = (int) $test['impressions_a'] + (int) $test['impressions_b'];

        return [
            ...$test,
            'rate_a' => round($rateA * 100, 2),
            'rate_b' => round($rateB * 100, 2),
            'lift' => $rateA > 0 ? round(($rateB - $rateA) / $rateA * 100, 2) : 0,
            'significant' => $totalImpressions >= 100, // Simple threshold
            'winner' => $rateA > $rateB ? 'a' : ($rateB > $rateA ? 'b' : 'tie'),
        ];
    }

    /**
     * End a test, optionally promoting the winner.
     */
    public function endTest(int $testId, bool $promoteWinner = false): void
    {
        global $wpdb;

        if ($promoteWinner) {
            $results = $this->getResults($testId);
            if ($results && $results['winner'] !== 'tie') {
                $winnerVersion = $results['winner'] === 'a'
                    ? $results['variant_a']
                    : $results['variant_b'];

                $versions = get_option('aisa_prompt_versions', []);
                $versions[$results['ability']] = $winnerVersion;
                update_option('aisa_prompt_versions', $versions);
            }
        }

        $wpdb->update(
            $this->table,
            ['status' => 'ended', 'ended_at' => current_time('mysql')],
            ['id' => $testId]
        );
    }

    /**
     * Get all tests for admin listing.
     */
    public function getAll(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY created_at DESC",
            ARRAY_A
        );
    }
}
