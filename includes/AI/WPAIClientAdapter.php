<?php

namespace AiSeoAssistant\AI;

class WPAIClientAdapter implements AIClientInterface
{
    public function generate(string $prompt, array $context = [], array $options = []): AIResponse
    {
        if (!$this->isAvailable()) {
            return AIResponse::error('WP AI Client is not available');
        }

        try {
            $client = new \WordPress\AI_Client\AI_Client();

            $args = [
                'prompt' => $prompt,
            ];

            if (!empty($context['system'])) {
                $args['system_instruction'] = $context['system'];
            }

            if (!empty($options['model'])) {
                $args['model'] = $options['model'];
            }

            if (isset($options['temperature'])) {
                $args['temperature'] = $options['temperature'];
            }

            if (isset($options['max_tokens'])) {
                $args['max_tokens'] = $options['max_tokens'];
            }

            $result = $client->generate_text($args);

            if (is_wp_error($result)) {
                return AIResponse::error($result->get_error_message());
            }

            return AIResponse::success(
                content: $result->get_text(),
                model: $result->get_model() ?? 'wp-ai-client',
                inputTokens: $result->get_input_tokens() ?? 0,
                outputTokens: $result->get_output_tokens() ?? 0,
                rawResponse: ['wp_ai_client' => true],
            );
        } catch (\Throwable $e) {
            return AIResponse::error($e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        return class_exists('WordPress\\AI_Client\\AI_Client');
    }

    public function getProviderName(): string
    {
        return 'WP AI Client';
    }
}
