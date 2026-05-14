<?php

namespace AiSeoAssistant\Abilities;

class GenerateTagsAbility extends AbstractAbility
{
    public function getName(): string
    {
        return 'ai-seo-assistant/generate-tags';
    }

    public function getDescription(): string
    {
        return 'Generates relevant tags for the article';
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
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }

    protected function getPromptAbilityName(): string
    {
        return 'generate_tags';
    }

    protected function buildPromptVariables(int $postId, array $input): array
    {
        $tags = wp_get_post_tags($postId, ['fields' => 'names']);

        return [
            'post_title' => $this->getPostTitle($postId),
            'post_content' => $this->getPostContent($postId),
            'existing_tags' => implode(', ', $tags),
        ];
    }
}
