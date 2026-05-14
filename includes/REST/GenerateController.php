<?php

namespace AiSeoAssistant\REST;

use AiSeoAssistant\Abilities\AnalyzeContentAbility;
use AiSeoAssistant\Abilities\GenerateMetaAbility;
use AiSeoAssistant\Abilities\GenerateTagsAbility;
use AiSeoAssistant\Abilities\GenerateAEOAbility;

class GenerateController
{
    private array $abilityMap = [
        'analyze_content' => AnalyzeContentAbility::class,
        'generate_meta' => GenerateMetaAbility::class,
        'generate_tags' => GenerateTagsAbility::class,
        'generate_aeo' => GenerateAEOAbility::class,
    ];

    public function registerRoutes(): void
    {
        register_rest_route('ai-seo-assistant/v1', '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'ability' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => array_keys($this->abilityMap),
                ],
                'postId' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'regenField' => [
                    'type' => 'string',
                    'default' => null,
                ],
            ],
        ]);
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $abilityKey = $request->get_param('ability');
        $postId = (int) $request->get_param('postId');

        $class = $this->abilityMap[$abilityKey] ?? null;
        if ($class === null) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Unknown ability'], 400);
        }

        $ability = new $class();

        if (!$ability->permissionCheck()) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Permission denied'], 403);
        }

        $input = [
            'postId' => $postId,
            'regenField' => $request->get_param('regenField'),
        ];

        $result = $ability->execute($input);

        $statusCode = 200;
        if (!$result['success']) {
            $statusCode = !empty($result['rateLimited']) ? 429 : 500;
        }

        return new \WP_REST_Response($result, $statusCode);
    }
}
