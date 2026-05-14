<?php

namespace AiSeoAssistant\REST;

use AiSeoAssistant\SEO\SEOAdapterFactory;
use AiSeoAssistant\Support\Logger;

class ConfirmController
{
    public function registerRoutes(): void
    {
        register_rest_route('ai-seo-assistant/v1', '/confirm', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'postId' => ['required' => true, 'type' => 'integer'],
                'meta_title' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
                'focus_keyword' => ['type' => 'string'],
                'tags' => ['type' => 'array'],
                'aeo_tldr' => ['type' => 'string'],
                'aeo_main_question' => ['type' => 'string'],
                'aeo_faq' => ['type' => 'array'],
                'aeo_entities' => ['type' => 'array'],
                'skipped' => ['type' => 'boolean', 'default' => false],
            ],
        ]);
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('postId');

        if (!current_user_can('edit_post', $postId)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Permission denied'], 403);
        }

        $post = get_post($postId);
        if (!$post) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Post not found'], 404);
        }

        $skipped = $request->get_param('skipped');

        if (!$skipped) {
            $this->saveSEOData($postId, $request);
            $this->saveTags($postId, $request);
            $this->saveAEOData($postId, $request);
        }

        // Set confirmed flag
        update_post_meta($postId, '_aisa_confirmed', [
            'date' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'version' => AISA_VERSION,
            'skipped' => $skipped,
        ]);

        Logger::info("SEO confirmed for post {$postId}" . ($skipped ? ' (skipped)' : ''));

        // Trigger headless webhook if enabled
        $this->triggerWebhook($postId, $post);

        return new \WP_REST_Response(['success' => true]);
    }

    private function saveSEOData(int $postId, \WP_REST_Request $request): void
    {
        $seoAdapter = SEOAdapterFactory::create();

        $metaTitle = $request->get_param('meta_title');
        if ($metaTitle !== null) {
            $seoAdapter->setMetaTitle($postId, $metaTitle);
        }

        $metaDesc = $request->get_param('meta_description');
        if ($metaDesc !== null) {
            $seoAdapter->setMetaDescription($postId, $metaDesc);
        }

        $focusKw = $request->get_param('focus_keyword');
        if ($focusKw !== null) {
            $seoAdapter->setFocusKeyword($postId, $focusKw);
        }
    }

    private function saveTags(int $postId, \WP_REST_Request $request): void
    {
        $tags = $request->get_param('tags');
        if (!empty($tags) && is_array($tags)) {
            $sanitized = array_map('sanitize_text_field', $tags);
            wp_set_post_terms($postId, $sanitized, 'post_tag', false);
        }
    }

    private function saveAEOData(int $postId, \WP_REST_Request $request): void
    {
        $tldr = $request->get_param('aeo_tldr');
        if ($tldr !== null) {
            update_post_meta($postId, '_aisa_aeo_tldr', sanitize_textarea_field($tldr));
        }

        $mainQuestion = $request->get_param('aeo_main_question');
        if ($mainQuestion !== null) {
            update_post_meta($postId, '_aisa_aeo_main_question', sanitize_text_field($mainQuestion));
        }

        $faq = $request->get_param('aeo_faq');
        if (!empty($faq) && is_array($faq)) {
            $sanitizedFaq = array_map(function ($item) {
                return [
                    'question' => sanitize_text_field($item['question'] ?? ''),
                    'answer' => sanitize_textarea_field($item['answer'] ?? ''),
                ];
            }, $faq);
            update_post_meta($postId, '_aisa_aeo_faq', $sanitizedFaq);
        }

        $entities = $request->get_param('aeo_entities');
        if (!empty($entities) && is_array($entities)) {
            $sanitizedEntities = array_map(function ($entity) {
                return [
                    'name' => sanitize_text_field($entity['name'] ?? ''),
                    'type' => sanitize_text_field($entity['type'] ?? ''),
                    'url' => esc_url_raw($entity['url'] ?? ''),
                ];
            }, $entities);
            update_post_meta($postId, '_aisa_aeo_entities', $sanitizedEntities);
        }
    }

    private function triggerWebhook(int $postId, \WP_Post $post): void
    {
        $settings = get_option('aisa_settings', []);

        if (empty($settings['headless_mode']) || empty($settings['headless_webhook_url'])) {
            return;
        }

        $payload = wp_json_encode([
            'post_id' => $postId,
            'slug' => $post->post_name,
            'post_type' => $post->post_type,
            'action' => 'seo_confirmed',
        ]);

        $secret = $settings['headless_webhook_secret'] ?? '';
        $signature = hash_hmac('sha256', $payload, $secret);

        wp_remote_post($settings['headless_webhook_url'], [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-AISA-Signature' => $signature,
            ],
            'body' => $payload,
            'timeout' => 10,
            'blocking' => false,
        ]);
    }
}
