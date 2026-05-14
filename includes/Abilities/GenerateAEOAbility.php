<?php

namespace AiSeoAssistant\Abilities;

class GenerateAEOAbility extends AbstractAbility
{
    public function getName(): string
    {
        return 'ai-seo-assistant/generate-aeo';
    }

    public function getDescription(): string
    {
        return 'Generates AEO content: TL;DR, FAQ, main question, and entities';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'postId' => ['type' => 'integer', 'description' => 'Post ID'],
            ],
            'required' => ['postId'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tldr' => ['type' => 'string'],
                'main_question' => ['type' => 'string'],
                'faq' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'answer' => ['type' => 'string'],
                        ],
                    ],
                ],
                'entities' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getPromptAbilityName(): string
    {
        return 'generate_aeo';
    }

    protected function buildPromptVariables(int $postId, array $input): array
    {
        return [
            'post_title' => $this->getPostTitle($postId),
            'post_content' => $this->getPostContent($postId),
        ];
    }
}
