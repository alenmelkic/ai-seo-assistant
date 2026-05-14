<?php

namespace AiSeoAssistant\Abilities;

class AnalyzeContentAbility extends AbstractAbility
{
    public function getName(): string
    {
        return 'ai-seo-assistant/analyze-content';
    }

    public function getDescription(): string
    {
        return 'Analyzes article content for quality issues and provides improvement suggestions';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'postId' => ['type' => 'integer', 'description' => 'Post ID to analyze'],
            ],
            'required' => ['postId'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'issues' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'severity' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'suggestion' => ['type' => 'string'],
                            'autofix' => ['type' => 'object'],
                        ],
                    ],
                ],
                'overall_score' => ['type' => 'integer'],
                'summary' => ['type' => 'string'],
            ],
        ];
    }

    protected function getPromptAbilityName(): string
    {
        return 'analyze_content';
    }

    protected function buildPromptVariables(int $postId, array $input): array
    {
        return [
            'post_title' => $this->getPostTitle($postId),
            'post_content' => $this->getPostContent($postId),
        ];
    }
}
