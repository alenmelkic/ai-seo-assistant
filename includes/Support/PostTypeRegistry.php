<?php

namespace AiSeoAssistant\Support;

class PostTypeRegistry
{
    private static array $excludeBuiltIn = [
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
        'wp_font_family',
        'wp_font_face',
    ];

    public static function getAvailablePostTypes(): array
    {
        $types = get_post_types(['public' => true], 'objects');
        $result = [];

        foreach ($types as $slug => $type) {
            if (in_array($slug, self::$excludeBuiltIn, true)) {
                continue;
            }

            $label = $type->labels->singular_name ?? $type->label;
            $suffix = $type->_builtin ? __('(built-in)', 'ai-seo-assistant') : __('(custom)', 'ai-seo-assistant');
            $result[$slug] = "{$label} {$suffix}";
        }

        return $result;
    }

    public function detectNewPostTypes(): void
    {
        $known = get_option('aisa_known_post_types', ['post', 'page']);
        $current = array_keys(self::getAvailablePostTypes());

        $new = array_diff($current, $known);

        if (!empty($new)) {
            update_option('aisa_known_post_types', $current);

            foreach ($new as $slug) {
                $type = get_post_type_object($slug);
                $label = $type ? ($type->labels->singular_name ?? $slug) : $slug;

                add_action('admin_notices', function () use ($label, $slug) {
                    $settingsUrl = admin_url('options-general.php?page=ai-seo-assistant');
                    printf(
                        '<div class="notice notice-info is-dismissible"><p>%s</p></div>',
                        sprintf(
                            /* translators: 1: post type label, 2: post type slug, 3: settings URL */
                            esc_html__('AI SEO Assistant: New post type detected: "%1$s" (%2$s). %3$s', 'ai-seo-assistant'),
                            esc_html($label),
                            esc_html($slug),
                            '<a href="' . esc_url($settingsUrl) . '">' . esc_html__('Open settings to enable it', 'ai-seo-assistant') . '</a>'
                        )
                    );
                });
            }
        }
    }
}
