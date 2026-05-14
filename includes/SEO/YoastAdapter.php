<?php

namespace AiSeoAssistant\SEO;

class YoastAdapter implements SEOAdapterInterface
{
    public function isActive(): bool
    {
        return defined('WPSEO_VERSION');
    }

    public function getPluginName(): string
    {
        return 'Yoast SEO';
    }

    public function getMetaTitle(int $postId): string
    {
        return get_post_meta($postId, '_yoast_wpseo_title', true) ?: '';
    }

    public function setMetaTitle(int $postId, string $title): void
    {
        update_post_meta($postId, '_yoast_wpseo_title', sanitize_text_field($title));
    }

    public function getMetaDescription(int $postId): string
    {
        return get_post_meta($postId, '_yoast_wpseo_metadesc', true) ?: '';
    }

    public function setMetaDescription(int $postId, string $desc): void
    {
        update_post_meta($postId, '_yoast_wpseo_metadesc', sanitize_textarea_field($desc));
    }

    public function getFocusKeyword(int $postId): string
    {
        return get_post_meta($postId, '_yoast_wpseo_focuskw', true) ?: '';
    }

    public function setFocusKeyword(int $postId, string $kw): void
    {
        update_post_meta($postId, '_yoast_wpseo_focuskw', sanitize_text_field($kw));
    }

    public function getCanonicalUrl(int $postId): ?string
    {
        $canonical = get_post_meta($postId, '_yoast_wpseo_canonical', true);
        return $canonical ?: null;
    }
}
