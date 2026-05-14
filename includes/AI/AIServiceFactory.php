<?php

namespace AiSeoAssistant\AI;

use AiSeoAssistant\Activator;

class AIServiceFactory
{
    public static function create(?string $forceStrategy = null): AIClientInterface
    {
        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $strategy = $forceStrategy ?? $settings['ai_provider_strategy'] ?? 'auto';

        return match ($strategy) {
            'wp_ai_client' => self::createOrFail(new WPAIClientAdapter()),
            'openai' => self::createOrFail(new OpenAIAdapter()),
            'anthropic' => self::createOrFail(new AnthropicAdapter()),
            default => self::autoDetect(),
        };
    }

    public static function getModelForAbility(string $ability): string
    {
        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $overrides = $settings['model_overrides'] ?? [];
        $override = $overrides[$ability] ?? 'default';

        if ($override !== 'default') {
            return $override;
        }

        return $settings['default_model'] ?? 'gpt-4o-mini';
    }

    private static function autoDetect(): AIClientInterface
    {
        $wpAi = new WPAIClientAdapter();
        if ($wpAi->isAvailable()) {
            return $wpAi;
        }

        $openai = new OpenAIAdapter();
        if ($openai->isAvailable()) {
            return $openai;
        }

        $anthropic = new AnthropicAdapter();
        if ($anthropic->isAvailable()) {
            return $anthropic;
        }

        throw new \RuntimeException(
            __('No AI provider configured. Please add an API key in Settings > SEO AI Assistant.', 'ai-seo-assistant')
        );
    }

    private static function createOrFail(AIClientInterface $client): AIClientInterface
    {
        if (!$client->isAvailable()) {
            throw new \RuntimeException(
                sprintf(
                    __('%s is not available. Check your configuration.', 'ai-seo-assistant'),
                    $client->getProviderName()
                )
            );
        }

        return $client;
    }
}
