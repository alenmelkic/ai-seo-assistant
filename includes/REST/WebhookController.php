<?php

namespace AiSeoAssistant\REST;

class WebhookController
{
    public function registerRoutes(): void
    {
        register_rest_route('ai-seo-assistant/v1', '/webhook/revalidate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'postId' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('postId');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Post not found'], 404);
        }

        $settings = get_option('aisa_settings', []);

        if (empty($settings['headless_mode']) || empty($settings['headless_webhook_url'])) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Headless mode not enabled'], 400);
        }

        $payload = wp_json_encode([
            'post_id' => $postId,
            'slug' => $post->post_name,
            'post_type' => $post->post_type,
            'action' => 'manual_revalidate',
        ]);

        $secret = $settings['headless_webhook_secret'] ?? '';
        $signature = hash_hmac('sha256', $payload, $secret);

        $response = wp_remote_post($settings['headless_webhook_url'], [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-AISA-Signature' => $signature,
            ],
            'body' => $payload,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $response->get_error_message(),
            ], 502);
        }

        return new \WP_REST_Response(['success' => true]);
    }
}
