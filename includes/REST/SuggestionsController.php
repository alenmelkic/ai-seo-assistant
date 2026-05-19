<?php

namespace AiSeoAssistant\REST;

use AiSeoAssistant\Suggestions\SuggestionRepository;
use AiSeoAssistant\GSC\GSCDataFetcher;

class SuggestionsController
{
    private const NAMESPACE = 'ai-seo-assistant/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/suggestions', [
            'methods' => 'GET',
            'callback' => [$this, 'listSuggestions'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'status' => ['default' => 'pending', 'sanitize_callback' => 'sanitize_text_field'],
                'type' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'per_page' => ['default' => 20, 'sanitize_callback' => 'absint'],
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/suggestions/(?P<id>\d+)/(?P<action>accept|reject|snooze)', [
            'methods' => 'POST',
            'callback' => [$this, 'handleAction'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => ['validate_callback' => fn($v) => is_numeric($v)],
                'action' => ['validate_callback' => fn($v) => in_array($v, ['accept', 'reject', 'snooze'])],
                'edited_value' => ['default' => null, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/gsc/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getGSCData'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'post_id' => ['validate_callback' => fn($v) => is_numeric($v)],
                'refresh' => ['default' => false, 'sanitize_callback' => 'rest_sanitize_boolean'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_aisa');
    }

    public function listSuggestions(\WP_REST_Request $request): \WP_REST_Response
    {
        $perPage = min($request->get_param('per_page'), 100);
        $page = max(1, $request->get_param('page'));

        $args = [
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'exclude_snoozed' => true,
        ];

        $status = $request->get_param('status');
        if ($status) {
            $args['status'] = $status;
        }

        $type = $request->get_param('type');
        if ($type) {
            $args['type'] = $type;
        }

        $suggestions = SuggestionRepository::query($args);
        $total = SuggestionRepository::count(array_filter([
            'status' => $status ?: null,
            'type' => $type ?: null,
        ]));

        $items = array_map(function ($s) {
            $data = $s->toArray();
            $post = get_post($s->postId);
            $data['post_title'] = $post ? $post->post_title : '(deleted)';
            $data['edit_url'] = $post ? get_edit_post_link($s->postId, 'raw') : '';
            return $data;
        }, $suggestions);

        return new \WP_REST_Response([
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $perPage),
        ]);
    }

    public function handleAction(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $action = $request->get_param('action');
        $userId = get_current_user_id();

        $suggestion = SuggestionRepository::findById($id);
        if (!$suggestion) {
            return new \WP_REST_Response(['error' => 'Suggestion not found'], 404);
        }

        switch ($action) {
            case 'accept':
                $editedValue = $request->get_param('edited_value');
                if ($editedValue !== null) {
                    // User edited before accepting — use their version
                    $suggestion->suggestedValue = $editedValue;
                }
                $this->applySuggestion($suggestion);
                SuggestionRepository::updateStatus($id, 'accepted', $userId);
                break;

            case 'reject':
                SuggestionRepository::updateStatus($id, 'rejected', $userId);
                break;

            case 'snooze':
                SuggestionRepository::snooze($id, 7);
                break;
        }

        return new \WP_REST_Response(['success' => true, 'action' => $action]);
    }

    public function getGSCData(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $refresh = $request->get_param('refresh');

        $data = GSCDataFetcher::getPostData($postId, $refresh);

        if ($data === null) {
            return new \WP_REST_Response([
                'connected' => \AiSeoAssistant\GSC\GSCAuthHandler::isConnected(),
                'data' => null,
            ]);
        }

        return new \WP_REST_Response([
            'connected' => true,
            'data' => $data,
        ]);
    }

    private function applySuggestion(\AiSeoAssistant\Suggestions\Suggestion $suggestion): void
    {
        $seoAdapter = \AiSeoAssistant\Plugin::getInstance()->getSEOAdapter();

        switch ($suggestion->type) {
            case 'meta_title':
                $seoAdapter->setMetaTitle($suggestion->postId, $suggestion->suggestedValue);
                break;
            case 'meta_description':
                $seoAdapter->setMetaDescription($suggestion->postId, $suggestion->suggestedValue);
                break;
            case 'aeo_refresh':
                update_post_meta($suggestion->postId, '_aisa_aeo_tldr', $suggestion->suggestedValue);
                break;
        }
    }
}
