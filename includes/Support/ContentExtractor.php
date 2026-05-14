<?php

namespace AiSeoAssistant\Support;

class ContentExtractor
{
    public static function extract(\WP_Post $post): string
    {
        if (self::isBlockContent($post->post_content)) {
            return self::extractFromBlocks($post->post_content);
        }

        return self::extractFromClassic($post->post_content);
    }

    private static function isBlockContent(string $content): bool
    {
        return str_contains($content, '<!-- wp:');
    }

    private static function extractFromBlocks(string $content): string
    {
        $blocks = parse_blocks($content);
        $output = [];

        foreach ($blocks as $block) {
            $text = self::extractBlock($block);
            if ($text !== '') {
                $output[] = $text;
            }
        }

        return implode("\n\n", $output);
    }

    private static function extractBlock(array $block): string
    {
        $blockName = $block['blockName'] ?? '';
        $innerHTML = trim($block['innerHTML'] ?? '');

        if (empty($blockName) && empty($innerHTML)) {
            return '';
        }

        $text = match (true) {
            str_starts_with($blockName, 'core/heading') => self::extractHeading($block),
            $blockName === 'core/paragraph' => self::stripTags($innerHTML),
            $blockName === 'core/list' => self::extractList($block),
            $blockName === 'core/quote' => '> ' . self::stripTags($innerHTML),
            $blockName === 'core/image' => self::extractImage($block),
            $blockName === 'core/embed' => self::extractEmbed($block),
            $blockName === 'core/code' => '[code block]',
            $blockName === 'core/table' => self::stripTags($innerHTML),
            default => self::stripTags($innerHTML),
        };

        // Process inner blocks
        if (!empty($block['innerBlocks'])) {
            $innerTexts = array_filter(array_map([self::class, 'extractBlock'], $block['innerBlocks']));
            if (!empty($innerTexts)) {
                $text .= "\n" . implode("\n", $innerTexts);
            }
        }

        return trim($text);
    }

    private static function extractHeading(array $block): string
    {
        $level = $block['attrs']['level'] ?? 2;
        $text = self::stripTags($block['innerHTML'] ?? '');
        $prefix = str_repeat('#', $level);
        return "{$prefix} {$text}";
    }

    private static function extractList(array $block): string
    {
        $html = $block['innerHTML'] ?? '';
        preg_match_all('/<li[^>]*>(.*?)<\/li>/s', $html, $matches);

        if (empty($matches[1])) {
            return self::stripTags($html);
        }

        return implode("\n", array_map(function ($item) {
            return '- ' . self::stripTags($item);
        }, $matches[1]));
    }

    private static function extractImage(array $block): string
    {
        $alt = $block['attrs']['alt'] ?? '';
        if (empty($alt)) {
            preg_match('/alt=["\']([^"\']*)["\']/', $block['innerHTML'] ?? '', $matches);
            $alt = $matches[1] ?? '';
        }
        return "[image: alt='{$alt}']";
    }

    private static function extractEmbed(array $block): string
    {
        $url = $block['attrs']['url'] ?? '';
        $providerSlug = $block['attrs']['providerNameSlug'] ?? 'embed';
        return "[embed: {$providerSlug}" . ($url ? " {$url}" : '') . "]";
    }

    private static function extractFromClassic(string $content): string
    {
        $content = apply_filters('the_content', $content);
        $text = wp_strip_all_tags($content);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private static function stripTags(string $html): string
    {
        return trim(wp_strip_all_tags($html));
    }
}
