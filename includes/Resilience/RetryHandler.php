<?php

namespace AiSeoAssistant\Resilience;

use AiSeoAssistant\AI\AIResponse;
use AiSeoAssistant\Support\Logger;

class RetryHandler
{
    private int $maxRetries;
    private array $backoffSeconds;

    public function __construct(int $maxRetries = 3)
    {
        $this->maxRetries = $maxRetries;
        $this->backoffSeconds = [1, 2, 4];
    }

    public function execute(callable $operation): AIResponse
    {
        $lastResponse = null;

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            $response = $operation();

            if ($response->success) {
                return $response;
            }

            $lastResponse = $response;

            if (!$this->isRetryable($response)) {
                Logger::warning("Non-retryable AI error: " . $response->errorMessage);
                return $response;
            }

            if ($attempt < $this->maxRetries - 1) {
                $delay = $this->backoffSeconds[$attempt] ?? 4;
                Logger::info("AI call failed (attempt " . ($attempt + 1) . "), retrying in {$delay}s: " . $response->errorMessage);
                sleep($delay);
            }
        }

        Logger::error("AI call failed after {$this->maxRetries} attempts: " . ($lastResponse?->errorMessage ?? 'Unknown error'));
        return $lastResponse ?? AIResponse::error('All retry attempts exhausted');
    }

    private function isRetryable(AIResponse $response): bool
    {
        $error = strtolower($response->errorMessage ?? '');

        // Timeout and server errors are retryable
        if (str_contains($error, 'timeout') || str_contains($error, 'timed out')) {
            return true;
        }

        // 5xx errors
        if (preg_match('/http\s*5\d{2}/', $error)) {
            return true;
        }

        // Rate limit (429)
        if (str_contains($error, 'rate limit') || str_contains($error, '429')) {
            return true;
        }

        // Overloaded
        if (str_contains($error, 'overloaded') || str_contains($error, 'capacity')) {
            return true;
        }

        // Auth errors, bad request — NOT retryable
        if (str_contains($error, '401') || str_contains($error, '403') || str_contains($error, '400')) {
            return false;
        }

        // Default: retry
        return true;
    }
}
