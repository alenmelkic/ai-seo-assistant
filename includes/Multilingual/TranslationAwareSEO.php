<?php

namespace AiSeoAssistant\Multilingual;

use AiSeoAssistant\SEO\SEOAdapterInterface;

/**
 * Wraps the SEO adapter to handle translation-aware meta field reading/writing.
 * For WPML, Yoast stores per-translation meta. For Polylang, same but separate posts.
 */
class TranslationAwareSEO
{
    public function __construct(
        private SEOAdapterInterface $seoAdapter,
    ) {}

    /**
     * Get SEO meta for a post in its current language context.
     */
    public function getMeta(int $postId): array
    {
        return [
            'meta_title' => $this->seoAdapter->getMetaTitle($postId),
            'meta_description' => $this->seoAdapter->getMetaDescription($postId),
            'focus_keyword' => $this->seoAdapter->getFocusKeyword($postId),
            'language' => LanguageDetector::getPostLanguage($postId),
        ];
    }

    /**
     * Set SEO meta for a post, respecting translation boundaries.
     */
    public function setMeta(int $postId, array $meta): void
    {
        if (isset($meta['meta_title'])) {
            $this->seoAdapter->setMetaTitle($postId, $meta['meta_title']);
        }
        if (isset($meta['meta_description'])) {
            $this->seoAdapter->setMetaDescription($postId, $meta['meta_description']);
        }
        if (isset($meta['focus_keyword'])) {
            $this->seoAdapter->setFocusKeyword($postId, $meta['focus_keyword']);
        }
    }

    /**
     * Check if all translations of a post have been SEO-reviewed.
     */
    public function allTranslationsReviewed(int $postId): array
    {
        $translations = LanguageDetector::getTranslations($postId);
        $status = [];

        foreach ($translations as $translatedId => $lang) {
            $confirmed = get_post_meta($translatedId, '_aisa_confirmed', true);
            $status[$lang] = [
                'post_id' => $translatedId,
                'reviewed' => !empty($confirmed),
                'confirmed' => $confirmed ?: null,
            ];
        }

        return $status;
    }

    /**
     * Get the language to use for AI prompts for this post.
     */
    public function getPromptLanguage(int $postId): string
    {
        $postLang = LanguageDetector::getPostLanguage($postId);

        if ($postLang) {
            return $postLang;
        }

        // Fallback to plugin settings
        $settings = get_option('aisa_settings', []);
        $lang = $settings['language'] ?? 'auto';

        if ($lang === 'auto') {
            return substr(get_locale(), 0, 2);
        }

        return $lang;
    }
}
