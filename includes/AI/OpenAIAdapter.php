<?php

namespace AiSeoAssistant\AI;

use AiSeoAssistant\Activator;

class OpenAIAdapter implements AIClientInterface
{
    private string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $this->apiKey = $apiKey ?? $settings['openai_api_key'] ?? '';
    }

    public function generate(string $prompt, array $context = [], array $options = []): AIResponse
    {
        $model = $options['model'] ?? 'gpt-4o-mini';
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 4096;

        $messages = [];

        if (!empty($context['system'])) {
            $messages[] = ['role' => 'system', 'content' => $context['system']];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if (!empty($options['response_format'])) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
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

        $content = $responseBody['choices'][0]['message']['content'] ?? '';
        $usage = $responseBody['usage'] ?? [];

        return AIResponse::success(
            content: $content,
            model: $responseBody['model'] ?? $model,
            inputTokens: $usage['prompt_tokens'] ?? 0,
            outputTokens: $usage['completion_tokens'] ?? 0,
            rawResponse: $responseBody,
        );
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getProviderName(): string
    {
        return 'OpenAI';
    }
}
