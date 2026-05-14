<?php

namespace AiSeoAssistant\Prompts;

interface PromptInterface
{
    public function getAbilityName(): string;

    public function render(array $variables = []): string;

    public function getSystemMessage(array $variables = []): string;
}
