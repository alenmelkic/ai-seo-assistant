<?php

namespace AiSeoAssistant\AI;

use AiSeoAssistant\Activator;

class AnthropicAdapter implements AIClientInterface
{
    private string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $this->apiKey = $apiKey ?? $settings['anthropic_api_key'] ?? '';
    }

    public function generate(string $prompt, array $context = [], array $options = []): AIResponse
    {
        $model = $options['model'] ?? 'claude-sonnet-4-7';
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 4096;

        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if (!empty($context['system'])) {
            $body['system'] = $context['system'];
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return AIResponse::error($response->get_error_message(), $model);
        }

        $code = wp_remote_retrieve_response_code($response);
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $errorMsg = $responseBody['error']['message'] ?? "HTTP {$code}";
            return AIResponse::error($errorMsg, $model, $responseBody ?? []);
        }

        $content = '';
        foreach (($responseBody['content'] ?? []) as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
        }

        $usage = $responseBody['usage'] ?? [];

        return AIResponse::success(
            content: $content,
            model: $responseBody['model'] ?? $model,
            inputTokens: $usage['input_tokens'] ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
            rawResponse: $responseBody,
        );
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getProviderName(): string
    {
        return 'Anthropic';
    }
}
