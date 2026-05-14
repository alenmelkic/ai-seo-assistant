<?php

namespace AiSeoAssistant\Admin;

use AiSeoAssistant\Activator;
use AiSeoAssistant\Support\PostTypeRegistry;

class EditorAssets
{
    public function enqueue(string $hook): void
    {
        $this->enqueueSettingsAssets($hook);
        $this->enqueueEditorAssets();
    }

    private function enqueueSettingsAssets(string $hook): void
    {
        if ($hook !== 'settings_page_ai-seo-assistant') {
            return;
        }

        wp_enqueue_style(
            'aisa-admin',
            AISA_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AISA_VERSION
        );
    }

    private function enqueueEditorAssets(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return;
        }

        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $active_types = $settings['active_post_types'] ?? ['post'];

        if (!in_array($screen->post_type, $active_types, true)) {
            return;
        }

        $asset_file = AISA_PLUGIN_DIR . 'assets/js/index.asset.php';
        $asset = file_exists($asset_file) ? require $asset_file : ['dependencies' => [], 'version' => AISA_VERSION];

        wp_enqueue_script(
            'aisa-editor',
            AISA_PLUGIN_URL . 'assets/js/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'aisa-editor',
            AISA_PLUGIN_URL . 'assets/css/editor.css',
            ['wp-components'],
            AISA_VERSION
        );

        wp_localize_script('aisa-editor', 'aiSeoAssistant', [
            'restUrl' => rest_url('ai-seo-assistant/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'postId' => get_the_ID(),
            'settings' => [
                'failureBehavior' => $settings['failure_behavior'],
                'classicEditorSupport' => $settings['classic_editor_support'],
                'language' => $settings['language'],
            ],
            'hasAbilitiesApi' => function_exists('register_ability'),
        ]);
    }
}
