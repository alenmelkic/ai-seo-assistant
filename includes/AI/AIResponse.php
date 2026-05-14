<?php

namespace AiSeoAssistant\AI;

class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly array $rawResponse,
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function success(string $content, string $model, int $inputTokens, int $outputTokens, array $rawResponse = []): self
    {
        return new self(
            content: $content,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            rawResponse: $rawResponse,
            success: true,
        );
    }

    public static function error(string $errorMessage, string $model = '', array $rawResponse = []): self
    {
        return new self(
            content: '',
            model: $model,
            inputTokens: 0,
            outputTokens: 0,
            rawResponse: $rawResponse,
            success: false,
            errorMessage: $errorMessage,
        );
    }

    public function getDecodedContent(): ?array
    {
        if (!$this->success) {
            return null;
        }

        // Strip markdown code fences if present
        $content = trim($this->content);
        $content = preg_replace('/^```(?:json)?\s*\n?/', '', $content);
        $content = preg_replace('/\n?```\s*$/', '', $content);

        $decoded = json_decode($content, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
