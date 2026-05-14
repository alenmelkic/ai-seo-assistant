<?php

namespace AiSeoAssistant\AEO;

class AEODataFormatter
{
    public static function formatForRest(int $postId): array
    {
        $data = AEOFields::getAll($postId);

        return [
            'tldr' => $data['tldr'] ?: null,
            'main_question' => $data['main_question'] ?: null,
            'faq' => !empty($data['faq']) ? $data['faq'] : [],
            'entities' => !empty($data['entities']) ? $data['entities'] : [],
        ];
    }
}
