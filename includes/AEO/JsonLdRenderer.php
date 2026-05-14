<?php

namespace AiSeoAssistant\AEO;

class JsonLdRenderer
{
    public function render(): void
    {
        if (!is_singular()) {
            return;
        }

        $postId = get_the_ID();
        if (!$postId) {
            return;
        }

        $this->renderFaqSchema($postId);
        $this->renderArticleSchema($postId);
    }

    private function renderFaqSchema(int $postId): void
    {
        $faq = get_post_meta($postId, '_aisa_aeo_faq', true);

        if (empty($faq) || !is_array($faq)) {
            return;
        }

        $faqEntities = [];
        foreach ($faq as $item) {
            if (empty($item['question']) || empty($item['answer'])) {
                continue;
            }
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }

        if (empty($faqEntities)) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqEntities,
        ];

        $this->outputJsonLd($schema);
    }

    private function renderArticleSchema(int $postId): void
    {
        $entities = get_post_meta($postId, '_aisa_aeo_entities', true);

        if (empty($entities) || !is_array($entities)) {
            return;
        }

        $aboutEntities = [];
        foreach ($entities as $entity) {
            if (empty($entity['name'])) {
                continue;
            }
            $thing = [
                '@type' => 'Thing',
                'name' => $entity['name'],
            ];
            if (!empty($entity['url'])) {
                $thing['url'] = $entity['url'];
            }
            $aboutEntities[] = $thing;
        }

        if (empty($aboutEntities)) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'name' => get_the_title($postId),
            'about' => $aboutEntities,
        ];

        $this->outputJsonLd($schema);
    }

    private function outputJsonLd(array $schema): void
    {
        $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }
}
