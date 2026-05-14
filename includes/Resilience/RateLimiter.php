<?php

namespace AiSeoAssistant\Resilience;

use AiSeoAssistant\Activator;

class RateLimiter
{
    private int $limit;

    public function __construct(?int $limit = null)
    {
        if ($limit !== null) {
            $this->limit = $limit;
        } else {
            $settings = get_option('aisa_settings', Activator::getDefaultSettings());
            $this->limit = (int) ($settings['rate_limit'] ?? 10);
        }
    }

    public function check(int $userId, int $postId): bool
    {
        $count = $this->getCount($userId, $postId);
        return $count < $this->limit;
    }

    public function increment(int $userId, int $postId): void
    {
        $key = $this->getKey($userId, $postId);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }

    public function getCount(int $userId, int $postId): int
    {
        return (int) get_transient($this->getKey($userId, $postId));
    }

    public function getRemainingMinutes(int $userId, int $postId): int
    {
        $key = $this->getKey($userId, $postId);
        $timeout = get_option('_transient_timeout_' . $key);

        if (!$timeout) {
            return 0;
        }

        $remaining = (int) $timeout - time();
        return max(0, (int) ceil($remaining / 60));
    }

    private function getKey(int $userId, int $postId): string
    {
        return "aisa_rate_{$userId}_{$postId}";
    }
}
