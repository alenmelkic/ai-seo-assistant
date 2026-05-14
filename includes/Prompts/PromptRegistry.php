<?php

namespace AiSeoAssistant\Prompts;

use AiSeoAssistant\Activator;

class PromptRegistry
{
    private static array $prompts = [
        'analyze_content' => AnalyzeContentPrompt::class,
        'generate_meta' => GenerateMetaPrompt::class,
        'generate_tags' => GenerateTagsPrompt::class,
        'generate_aeo' => GenerateAEOPrompt::class,
    ];

    public static function get(string $ability): PromptInterface
    {
        $class = self::$prompts[$ability] ?? null;

        if ($class === null) {
            throw new \InvalidArgumentException("Unknown ability: {$ability}");
        }

        return new $class();
    }

    public static function getVersion(string $ability): string
    {
        $versions = get_option('aisa_prompt_versions', []);
        return $versions[$ability] ?? 'v1';
    }

    public static function loadTemplate(string $ability, ?string $version = null): string
    {
        $version = $version ?? self::getVersion($ability);
        $file = AISA_PLUGIN_DIR . "includes/Prompts/versions/{$ability}_{$version}.txt";

        if (!file_exists($file)) {
            // Fallback to v1
            $file = AISA_PLUGIN_DIR . "includes/Prompts/versions/{$ability}_v1.txt";
        }

        if (!file_exists($file)) {
            throw new \RuntimeException("Prompt template not found: {$ability}");
        }

        return file_get_contents($file);
    }

    public static function renderTemplate(string $template, array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($variables) {
            return $variables[$matches[1]] ?? '';
        }, $template);
    }

    public static function buildLanguageBlock(string $language): string
    {
        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $langSetting = $settings['language'] ?? 'auto';

        if ($langSetting === 'auto') {
            $locale = get_locale();
            $language = match (true) {
                str_starts_with($locale, 'bs') => 'bs',
                str_starts_with($locale, 'hr') => 'hr',
                str_starts_with($locale, 'sr') => 'sr',
                default => 'en',
            };
        } else {
            $language = $langSetting;
        }

        return $language;
    }

    public static function buildBrandVoiceBlock(): string
    {
        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $parts = [];

        if (!empty($settings['brand_voice'])) {
            $parts[] = "Brand voice instructions: " . $settings['brand_voice'];
        }

        if (!empty($settings['sample_content'])) {
            $parts[] = "Sample content for style reference:\n" . $settings['sample_content'];
        }

        if (!empty($settings['audience_description'])) {
            $parts[] = "Target audience: " . $settings['audience_description'];
        }

        return implode("\n\n", $parts);
    }
}
