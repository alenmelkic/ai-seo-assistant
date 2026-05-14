<?php

namespace AiSeoAssistant\SEO;

class RankMathAdapter implements SEOAdapterInterface
{
    public function isActive(): bool
    {
        return defined('RANK_MATH_VERSION');
    }

    public function getPluginName(): string
    {
        return 'Rank Math';
    }

    public function getMetaTitle(int $postId): string
    {
        return get_post_meta($postId, 'rank_math_title', true) ?: '';
    }

    public function setMetaTitle(int $postId, string $title): void
    {
        update_post_meta($postId, 'rank_math_title', sanitize_text_field($title));
    }

    public function getMetaDescription(int $postId): string
    {
        return get_post_meta($postId, 'rank_math_description', true) ?: '';
    }

    public function setMetaDescription(int $postId, string $desc): void
    {
        update_post_meta($postId, 'rank_math_description', sanitize_textarea_field($desc));
    }

    public function getFocusKeyword(int $postId): string
    {
        return get_post_meta($postId, 'rank_math_focus_keyword', true) ?: '';
    }

    public function setFocusKeyword(int $postId, string $kw): void
    {
        update_post_meta($postId, 'rank_math_focus_keyword', sanitize_text_field($kw));
    }

    public function getCanonicalUrl(int $postId): ?string
    {
        $canonical = get_post_meta($postId, 'rank_math_canonical_url', true);
        return $canonical ?: null;
    }
}
