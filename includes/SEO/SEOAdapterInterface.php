<?php

namespace AiSeoAssistant\SEO;

interface SEOAdapterInterface
{
    public function isActive(): bool;

    public function getPluginName(): string;

    public function getMetaTitle(int $postId): string;

    public function setMetaTitle(int $postId, string $title): void;

    public function getMetaDescription(int $postId): string;

    public function setMetaDescription(int $postId, string $desc): void;

    public function getFocusKeyword(int $postId): string;

    public function setFocusKeyword(int $postId, string $kw): void;

    public function getCanonicalUrl(int $postId): ?string;
}
