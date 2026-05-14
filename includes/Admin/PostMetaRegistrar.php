<?php

namespace AiSeoAssistant\Admin;

class PostMetaRegistrar
{
    public function register(): void
    {
        $this->registerAISEOMeta();
        $this->registerAEOMeta();
    }

    private function registerAISEOMeta(): void
    {
        register_post_meta('', '_aisa_confirmed', [
            'type' => 'object',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string'],
                        'user_id' => ['type' => 'integer'],
                        'version' => ['type' => 'string'],
                        'skipped' => ['type' => 'boolean'],
                    ],
                ],
            ],
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    private function registerAEOMeta(): void
    {
        register_post_meta('', '_aisa_aeo_tldr', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta('', '_aisa_aeo_main_question', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta('', '_aisa_aeo_faq', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'answer' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta('', '_aisa_aeo_entities', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
}
