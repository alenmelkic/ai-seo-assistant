<?php

namespace AiSeoAssistant\Resilience;

use AiSeoAssistant\Activator;

/**
 * Advanced rate limiter with per-role limits and global daily caps.
 * Extends the basic RateLimiter from Phase 0.
 */
class AdvancedRateLimiter
{
    private array $roleLimits;
    private int $globalDailyCap;

    public function __construct()
    {
        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $this->roleLimits = $settings['rate_limits_per_role'] ?? $this->getDefaultRoleLimits();
        $this->globalDailyCap = (int) ($settings['global_daily_cap'] ?? 500);
    }

    private function getDefaultRoleLimits(): array
    {
        return [
            'administrator' => 50,
            'editor' => 20,
            'author' => 10,
            'contributor' => 5,
        ];
    }

    /**
     * Check if the user can make another AI request.
     */
    public function check(int $userId): array
    {
        // Check per-user hourly limit
        $hourlyCount = $this->getHourlyCount($userId);
        $userLimit = $this->getUserLimit($userId);

        if ($hourlyCount >= $userLimit) {
            return [
                'allowed' => false,
                'reason' => 'hourly_limit',
                'limit' => $userLimit,
                'remaining' => 0,
                'retry_after' => $this->getRetryAfter($userId),
            ];
        }

        // Check global daily cap
        $dailyCount = $this->getGlobalDailyCount();
        if ($dailyCount >= $this->globalDailyCap) {
            return [
                'allowed' => false,
                'reason' => 'daily_cap',
                'limit' => $this->globalDailyCap,
                'remaining' => 0,
                'retry_after' => $this->getSecondsUntilMidnight(),
            ];
        }

        return [
            'allowed' => true,
            'remaining_hourly' => $userLimit - $hourlyCount - 1,
            'remaining_daily' => $this->globalDailyCap - $dailyCount - 1,
        ];
    }

    /**
     * Increment counters after a successful request.
     */
    public function increment(int $userId): void
    {
        // Hourly per-user
        $hourlyKey = $this->getHourlyKey($userId);
        $count = (int) get_transient($hourlyKey);
        set_transient($hourlyKey, $count + 1, HOUR_IN_SECONDS);

        // Daily global
        $dailyKey = $this->getDailyKey();
        $count = (int) get_transient($dailyKey);
        set_transient($dailyKey, $count + 1, DAY_IN_SECONDS);
    }

    /**
     * Get the rate limit for a user based on their role.
     */
    public function getUserLimit(int $userId): int
    {
        $user = get_userdata($userId);
        if (!$user) {
            return $this->roleLimits['contributor'] ?? 5;
        }

        foreach ($this->roleLimits as $role => $limit) {
            if (in_array($role, $user->roles, true)) {
                return $limit;
            }
        }

        return $this->roleLimits['contributor'] ?? 5;
    }

    public function getHourlyCount(int $userId): int
    {
        return (int) get_transient($this->getHourlyKey($userId));
    }

    public function getGlobalDailyCount(): int
    {
        return (int) get_transient($this->getDailyKey());
    }

    private function getRetryAfter(int $userId): int
    {
        $key = $this->getHourlyKey($userId);
        $timeout = get_option('_transient_timeout_' . $key);

        if (!$timeout) {
            return 0;
        }

        return max(0, (int) $timeout - time());
    }

    private function getSecondsUntilMidnight(): int
    {
        $now = current_time('timestamp');
        $midnight = strtotime('tomorrow midnight', $now);
        return max(0, $midnight - $now);
    }

    private function getHourlyKey(int $userId): string
    {
        return "aisa_rate_h_{$userId}";
    }

    private function getDailyKey(): string
    {
        $date = current_time('Y-m-d');
        return "aisa_rate_d_{$date}";
    }
}
