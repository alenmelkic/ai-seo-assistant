<?php

namespace AiSeoAssistant\AI;

interface AIClientInterface
{
    public function generate(
        string $prompt,
        array $context = [],
        array $options = [],
    ): AIResponse;

    public function isAvailable(): bool;

    public function getProviderName(): string;
}
