<?php

namespace AiSeoAssistant\Resilience;

class FallbackResponder
{
    public static function getAnalyzeContentFallback(): array
    {
        return [
            'success' => false,
            'fallback' => true,
            'data' => [
                'issues' => [],
                'overall_score' => 0,
                'summary' => __('AI service is currently unavailable. You can skip this step or try again.', 'ai-seo-assistant'),
            ],
        ];
    }

    public static function getGenerateMetaFallback(): array
    {
        return [
            'success' => false,
            'fallback' => true,
            'data' => [
                'meta_title' => '',
                'meta_description' => '',
                'focus_keyword' => '',
            ],
        ];
    }

    public static function getGenerateTagsFallback(): array
    {
        return [
            'success' => false,
            'fallback' => true,
            'data' => [
                'tags' => [],
            ],
        ];
    }

    public static function getGenerateAEOFallback(): array
    {
        return [
            'success' => false,
            'fallback' => true,
            'data' => [
                'tldr' => '',
                'main_question' => '',
                'faq' => [],
                'entities' => [],
            ],
        ];
    }

    public static function getFallbackForAbility(string $ability): array
    {
        return match ($ability) {
            'analyze_content' => self::getAnalyzeContentFallback(),
            'generate_meta' => self::getGenerateMetaFallback(),
            'generate_tags' => self::getGenerateTagsFallback(),
            'generate_aeo' => self::getGenerateAEOFallback(),
            default => ['success' => false, 'fallback' => true, 'data' => []],
        };
    }
}
