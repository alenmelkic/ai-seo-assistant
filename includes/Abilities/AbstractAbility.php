<?php

namespace AiSeoAssistant\Abilities;

use AiSeoAssistant\AI\AIServiceFactory;
use AiSeoAssistant\Prompts\PromptRegistry;
use AiSeoAssistant\Resilience\RetryHandler;
use AiSeoAssistant\Resilience\RateLimiter;
use AiSeoAssistant\Support\Logger;
use AiSeoAssistant\Support\ContentExtractor;

abstract class AbstractAbility
{
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function getInputSchema(): array;
    abstract public function getOutputSchema(): array;
    abstract protected function getPromptAbilityName(): string;
    abstract protected function buildPromptVariables(int $postId, array $input): array;

    public function permissionCheck(): bool
    {
        if (!current_user_can('edit_posts')) {
            return false;
        }

        return true;
    }

    public function execute(array $input): array
    {
        $postId = $input['postId'] ?? 0;
        $post = get_post($postId);

        if (!$post) {
            return ['success' => false, 'error' => 'Post not found'];
        }

        // Check post ownership or edit capability
        if (!current_user_can('edit_post', $postId)) {
            return ['success' => false, 'error' => 'Permission denied'];
        }

        // Rate limit check
        $rateLimiter = new RateLimiter();
        if (!$rateLimiter->check(get_current_user_id(), $postId)) {
            return ['success' => false, 'error' => 'Rate limit exceeded. Try again later.', 'rateLimited' => true];
        }

        try {
            $aiService = AIServiceFactory::create();
            $model = AIServiceFactory::getModelForAbility($this->getPromptAbilityName());
            $prompt = PromptRegistry::get($this->getPromptAbilityName());

            $variables = $this->buildPromptVariables($postId, $input);
            $renderedPrompt = $prompt->render($variables);
            $systemMessage = $prompt->getSystemMessage($variables);

            $retryHandler = new RetryHandler();
            $response = $retryHandler->execute(function () use ($aiService, $renderedPrompt, $systemMessage, $model) {
                return $aiService->generate(
                    $renderedPrompt,
                    ['system' => $systemMessage],
                    ['model' => $model, 'response_format' => 'json']
                );
            });

            // Log the AI call
            Logger::logAICall($postId, $this->getName(), $aiService->getProviderName(), $model, $response);

            if (!$response->success) {
                return ['success' => false, 'error' => $response->errorMessage];
            }

            $decoded = $response->getDecodedContent();
            if ($decoded === null) {
                return ['success' => false, 'error' => 'Failed to parse AI response'];
            }

            $rateLimiter->increment(get_current_user_id(), $postId);

            return ['success' => true, 'data' => $decoded];

        } catch (\Throwable $e) {
            Logger::error("Ability {$this->getName()} failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function getPostContent(int $postId): string
    {
        return ContentExtractor::extract(get_post($postId));
    }

    protected function getPostTitle(int $postId): string
    {
        return get_the_title($postId);
    }
}
