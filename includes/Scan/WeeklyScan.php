<?php

namespace AiSeoAssistant\Scan;

use AiSeoAssistant\Activator;
use AiSeoAssistant\GSC\GSCAuthHandler;
use AiSeoAssistant\GSC\GSCContextBuilder;
use AiSeoAssistant\GSC\GSCDataFetcher;
use AiSeoAssistant\Plugin;
use AiSeoAssistant\Suggestions\SuggestionRepository;
use AiSeoAssistant\Support\ContentExtractor;
use AiSeoAssistant\Support\Logger;

class WeeklyScan
{
    private const CRON_HOOK = 'aisa_weekly_scan';
    private const POSTS_PER_RUN = 100;

    /**
     * Register the cron event.
     */
    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule for next Sunday at 03:00
            $nextSunday = strtotime('next Sunday 03:00:00');
            wp_schedule_event($nextSunday, 'weekly', self::CRON_HOOK);
        }
    }

    /**
     * Unschedule the cron event.
     */
    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Run the weekly scan. Called by WP-Cron.
     */
    public static function run(): void
    {
        if (!GSCAuthHandler::isConnected()) {
            Logger::info('Weekly scan skipped: GSC not connected');
            return;
        }

        Logger::info('Weekly scan started');

        // Expire old pending suggestions
        $expired = SuggestionRepository::expireOld(90);
        if ($expired > 0) {
            Logger::info("Expired {$expired} old suggestions");
        }

        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $activeTypes = $settings['active_post_types'] ?? ['post'];

        // Query published posts
        $posts = get_posts([
            'post_type' => $activeTypes,
            'post_status' => 'publish',
            'posts_per_page' => self::POSTS_PER_RUN,
            'orderby' => 'modified',
            'order' => 'ASC', // Oldest-modified first (most likely to need refresh)
            'fields' => 'ids',
        ]);

        $suggestionsCreated = 0;

        foreach ($posts as $postId) {
            try {
                $created = self::scanPost($postId);
                $suggestionsCreated += $created;
            } catch (\Throwable $e) {
                Logger::error("Weekly scan failed for post {$postId}: " . $e->getMessage());
            }
        }

        Logger::info("Weekly scan complete: scanned " . count($posts) . " posts, created {$suggestionsCreated} suggestions");
    }

    /**
     * Scan a single post. Returns number of suggestions created.
     */
    private static function scanPost(int $postId): int
    {
        // Force-refresh GSC data for this scan
        $gscData = GSCDataFetcher::getPostData($postId, true);
        if (empty($gscData) || empty($gscData['top_queries'])) {
            return 0;
        }

        // Score the post
        $health = HealthScorer::score($gscData);
        if (!$health['needs_attention']) {
            return 0;
        }

        // Determine suggestion types based on issues
        $suggestionTypes = self::determineSuggestionTypes($health['issues']);
        if (empty($suggestionTypes)) {
            return 0;
        }

        // Generate AI suggestions for the problematic areas
        $gscSnapshot = GSCContextBuilder::buildSnapshot($postId);
        $created = 0;

        foreach ($suggestionTypes as $type) {
            // Check if a pending suggestion of this type already exists
            $existing = SuggestionRepository::query([
                'post_id' => $postId,
                'type' => $type,
                'status' => 'pending',
                'per_page' => 1,
            ]);

            if (!empty($existing)) {
                continue; // Don't duplicate
            }

            $suggestion = self::generateSuggestion($postId, $type, $health['issues']);
            if ($suggestion !== null) {
                SuggestionRepository::create([
                    'post_id' => $postId,
                    'type' => $type,
                    'current_value' => $suggestion['current'],
                    'suggested_value' => $suggestion['suggested'],
                    'reason' => $suggestion['reason'],
                    'gsc_snapshot' => $gscSnapshot,
                ]);
                $created++;
            }
        }

        return $created;
    }

    private static function determineSuggestionTypes(array $issues): array
    {
        $types = [];
        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'low_ctr':
                case 'zero_clicks':
                    $types[] = 'meta_title';
                    $types[] = 'meta_description';
                    break;
                case 'position_drop':
                case 'impressions_drop':
                    $types[] = 'aeo_refresh';
                    break;
            }
        }
        return array_unique($types);
    }

    private static function generateSuggestion(int $postId, string $type, array $issues): ?array
    {
        $post = get_post($postId);
        if (!$post) {
            return null;
        }

        $seoAdapter = Plugin::getInstance()->getSEOAdapter();
        $aiService = Plugin::getInstance()->getAIService();

        $issueText = implode("\n", array_map(fn($i) => "- [{$i['severity']}] {$i['detail']}", $issues));
        $gscContext = GSCContextBuilder::build($postId);

        switch ($type) {
            case 'meta_title':
                $current = $seoAdapter->getMetaTitle($postId);
                $prompt = "Based on the GSC data and issues below, suggest an improved meta title (max 60 chars) for this article.\n\n"
                    . "Article: {$post->post_title}\n"
                    . "Current meta title: " . ($current ?: '(empty)') . "\n\n"
                    . "Issues:\n{$issueText}\n\n{$gscContext}\n\n"
                    . "Return JSON: {\"suggested\": \"...\", \"reason\": \"...\"}";
                break;

            case 'meta_description':
                $current = $seoAdapter->getMetaDescription($postId);
                $prompt = "Based on the GSC data and issues below, suggest an improved meta description (max 160 chars) for this article.\n\n"
                    . "Article: {$post->post_title}\n"
                    . "Current meta description: " . ($current ?: '(empty)') . "\n\n"
                    . "Issues:\n{$issueText}\n\n{$gscContext}\n\n"
                    . "Return JSON: {\"suggested\": \"...\", \"reason\": \"...\"}";
                break;

            case 'aeo_refresh':
                $current = get_post_meta($postId, '_aisa_aeo_tldr', true);
                $prompt = "Based on declining search performance, suggest an updated TL;DR (40-60 words) for this article.\n\n"
                    . "Article: {$post->post_title}\n"
                    . "Current TL;DR: " . ($current ?: '(empty)') . "\n\n"
                    . "Issues:\n{$issueText}\n\n{$gscContext}\n\n"
                    . "Return JSON: {\"suggested\": \"...\", \"reason\": \"...\"}";
                break;

            default:
                return null;
        }

        try {
            $response = $aiService->chat([
                ['role' => 'system', 'content' => 'You are an SEO expert. Always respond with valid JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ]);

            if (!$response->success) {
                return null;
            }

            $data = json_decode($response->content, true);
            if (empty($data['suggested'])) {
                return null;
            }

            return [
                'current' => $current ?? '',
                'suggested' => $data['suggested'],
                'reason' => $data['reason'] ?? 'AI-generated suggestion based on GSC data',
            ];
        } catch (\Throwable $e) {
            Logger::error("Failed to generate suggestion for post {$postId}, type {$type}: " . $e->getMessage());
            return null;
        }
    }
}
