<?php

namespace AiSeoAssistant\SEO;

use AiSeoAssistant\Support\Logger;

class NullAdapter implements SEOAdapterInterface
{
    public function isActive(): bool
    {
        return false;
    }

    public function getPluginName(): string
    {
        return __('None detected', 'ai-seo-assistant');
    }

    public function getMetaTitle(int $postId): string
    {
        return '';
    }

    public function setMetaTitle(int $postId, string $title): void
    {
        Logger::warning('No SEO plugin active. Cannot set meta title for post ' . $postId);
    }

    public function getMetaDescription(int $postId): string
    {
        return '';
    }

    public function setMetaDescription(int $postId, string $desc): void
    {
        Logger::warning('No SEO plugin active. Cannot set meta description for post ' . $postId);
    }

    public function getFocusKeyword(int $postId): string
    {
        return '';
    }

    public function setFocusKeyword(int $postId, string $kw): void
    {
        Logger::warning('No SEO plugin active. Cannot set focus keyword for post ' . $postId);
    }

    public function getCanonicalUrl(int $postId): ?string
    {
        return null;
    }
}
