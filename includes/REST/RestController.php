<?php

namespace AiSeoAssistant\REST;

class RestController
{
    public function registerRoutes(): void
    {
        $generate = new GenerateController();
        $generate->registerRoutes();

        $confirm = new ConfirmController();
        $confirm->registerRoutes();

        $webhook = new WebhookController();
        $webhook->registerRoutes();

        $this->registerAEORoute();

        // Phase 2: Bulk, Audit, Stats routes
        $bulk = new BulkController();
        $bulk->registerRoutes();

        // Phase 1.5: Suggestions + GSC routes
        $suggestions = new SuggestionsController();
        $suggestions->registerRoutes();
    }

    private function registerAEORoute(): void
    {
        register_rest_route('ai-seo-assistant/v1', '/aeo/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getAEOData'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }

    public function getAEOData(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('id');
        $post = get_post($postId);

        if (!$post || $post->post_status !== 'publish') {
            return new \WP_REST_Response(['error' => 'Post not found'], 404);
        }

        $tldr = get_post_meta($postId, '_aisa_aeo_tldr', true);
        $faq = get_post_meta($postId, '_aisa_aeo_faq', true);
        $mainQuestion = get_post_meta($postId, '_aisa_aeo_main_question', true);
        $entities = get_post_meta($postId, '_aisa_aeo_entities', true);

        $jsonLd = $this->buildJsonLd($post, $faq, $entities);

        return new \WP_REST_Response([
            'post_id' => $postId,
            'tldr' => $tldr ?: null,
            'main_question' => $mainQuestion ?: null,
            'faq' => $faq ?: [],
            'entities' => $entities ?: [],
            'json_ld' => $jsonLd,
        ]);
    }

    private function buildJsonLd(\WP_Post $post, mixed $faq, mixed $entities): array
    {
        $schemas = [];

        if (!empty($faq) && is_array($faq)) {
            $faqEntities = array_map(fn($item) => [
                '@type' => 'Question',
                'name' => $item['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'] ?? '',
                ],
            ], $faq);

            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqEntities,
            ];
        }

        if (!empty($entities) && is_array($entities)) {
            $aboutEntities = array_map(fn($entity) => array_filter([
                '@type' => 'Thing',
                'name' => $entity['name'] ?? '',
                'url' => $entity['url'] ?? null,
            ]), $entities);

            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'name' => $post->post_title,
                'about' => $aboutEntities,
            ];
        }

        return $schemas;
    }
}
