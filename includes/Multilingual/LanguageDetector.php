<?php

namespace AiSeoAssistant\Multilingual;

class LanguageDetector
{
    /**
     * Detect active multilingual plugin.
     *
     * @return string|null 'wpml' | 'polylang' | null
     */
    public static function detect(): ?string
    {
        if (defined('ICL_SITEPRESS_VERSION')) {
            return 'wpml';
        }

        if (function_exists('pll_current_language')) {
            return 'polylang';
        }

        return null;
    }

    /**
     * Get current post language.
     */
    public static function getPostLanguage(int $postId): ?string
    {
        $plugin = self::detect();

        if ($plugin === 'wpml') {
            $details = apply_filters('wpml_post_language_details', null, $postId);
            return $details['language_code'] ?? null;
        }

        if ($plugin === 'polylang') {
            return pll_get_post_language($postId, 'slug');
        }

        // Fallback to WP locale
        return substr(get_locale(), 0, 2);
    }

    /**
     * Get all translations of a post (post_id => language_code).
     */
    public static function getTranslations(int $postId): array
    {
        $plugin = self::detect();

        if ($plugin === 'wpml') {
            $type = get_post_type($postId);
            $trid = apply_filters('wpml_element_trid', null, $postId, 'post_' . $type);
            $translations = apply_filters('wpml_get_element_translations', null, $trid, 'post_' . $type);

            $result = [];
            if (is_array($translations)) {
                foreach ($translations as $lang => $data) {
                    $result[(int) $data->element_id] = $lang;
                }
            }
            return $result;
        }

        if ($plugin === 'polylang') {
            $translations = pll_get_post_translations($postId);
            $result = [];
            foreach ($translations as $lang => $translatedId) {
                $result[(int) $translatedId] = $lang;
            }
            return $result;
        }

        return [$postId => substr(get_locale(), 0, 2)];
    }

    /**
     * Get all active languages.
     */
    public static function getActiveLanguages(): array
    {
        $plugin = self::detect();

        if ($plugin === 'wpml') {
            $languages = apply_filters('wpml_active_languages', null);
            return array_column($languages, 'native_name', 'language_code');
        }

        if ($plugin === 'polylang') {
            $languages = pll_languages_list(['fields' => '']);
            $result = [];
            foreach ($languages as $lang) {
                $result[$lang->slug] = $lang->name;
            }
            return $result;
        }

        $locale = get_locale();
        return [substr($locale, 0, 2) => $locale];
    }

    /**
     * Check if multilingual is active.
     */
    public static function isMultilingual(): bool
    {
        return self::detect() !== null;
    }
}
