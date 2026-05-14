<?php

namespace AiSeoAssistant\AEO;

class AEOFields
{
    public const TLDR = '_aisa_aeo_tldr';
    public const MAIN_QUESTION = '_aisa_aeo_main_question';
    public const FAQ = '_aisa_aeo_faq';
    public const ENTITIES = '_aisa_aeo_entities';

    public static function getAll(int $postId): array
    {
        return [
            'tldr' => get_post_meta($postId, self::TLDR, true) ?: '',
            'main_question' => get_post_meta($postId, self::MAIN_QUESTION, true) ?: '',
            'faq' => get_post_meta($postId, self::FAQ, true) ?: [],
            'entities' => get_post_meta($postId, self::ENTITIES, true) ?: [],
        ];
    }

    public static function saveAll(int $postId, array $data): void
    {
        if (isset($data['tldr'])) {
            update_post_meta($postId, self::TLDR, sanitize_textarea_field($data['tldr']));
        }
        if (isset($data['main_question'])) {
            update_post_meta($postId, self::MAIN_QUESTION, sanitize_text_field($data['main_question']));
        }
        if (isset($data['faq'])) {
            update_post_meta($postId, self::FAQ, $data['faq']);
        }
        if (isset($data['entities'])) {
            update_post_meta($postId, self::ENTITIES, $data['entities']);
        }
    }
}
