<?php

namespace AiSeoAssistant\Abilities;

use AiSeoAssistant\SEO\SEOAdapterFactory;

class GenerateMetaAbility extends AbstractAbility
{
    public function getName(): string
    {
        return 'ai-seo-assistant/generate-meta';
    }

    public function getDescription(): string
    {
        return 'Generates optimized SEO meta title, description, and focus keyword';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'postId' => ['type' => 'integer', 'description' => 'Post ID'],
                'regenField' => ['type' => 'string', 'description' => 'Optional: specific field to regenerate'],
            ],
            'required' => ['postId'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'meta_title' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
                'focus_keyword' => ['type' => 'string'],
            ],
        ];
    }

    protected function getPromptAbilityName(): string
    {
        return 'generate_meta';
    }

    protected function buildPromptVariables(int $postId, array $input): array
    {
        $seoAdapter = SEOAdapterFactory::create();

        return [
            'post_title' => $this->getPostTitle($postId),
            'post_content' => $this->getPostContent($postId),
            'current_meta_title' => $seoAdapter->getMetaTitle($postId),
            'current_meta_description' => $seoAdapter->getMetaDescription($postId),
            'current_focus_keyword' => $seoAdapter->getFocusKeyword($postId),
        ];
    }
}
