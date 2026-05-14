<?php

namespace AiSeoAssistant;

class Activator
{
    public static function activate(): void
    {
        self::createDefaultOptions();
        self::addCustomCapability();
        self::createLogTable();

        flush_rewrite_rules();
    }

    public static function getDefaultSettings(): array
    {
        return [
            'ai_provider_strategy' => 'auto',
            'openai_api_key' => '',
            'anthropic_api_key' => '',
            'default_model' => 'gpt-4o-mini',
            'model_overrides' => [
                'analyze_content' => 'default',
                'generate_meta' => 'default',
                'generate_tags' => 'default',
                'generate_aeo' => 'default',
            ],
            'brand_voice' => '',
            'sample_content' => '',
            'audience_description' => '',
            'active_post_types' => ['post'],
            'rate_limit' => 10,
            'failure_behavior' => 'skip',
            'headless_mode' => false,
            'headless_webhook_url' => '',
            'headless_webhook_secret' => '',
            'language' => 'auto',
            'classic_editor_support' => true,
            'delete_data_on_uninstall' => false,
        ];
    }

    private static function createDefaultOptions(): void
    {
        if (get_option('aisa_settings') === false) {
            add_option('aisa_settings', self::getDefaultSettings());
        }

        if (get_option('aisa_prompt_versions') === false) {
            add_option('aisa_prompt_versions', [
                'analyze_content' => 'v1',
                'generate_meta' => 'v1',
                'generate_tags' => 'v1',
                'generate_aeo' => 'v1',
            ]);
        }

        if (get_option('aisa_known_post_types') === false) {
            add_option('aisa_known_post_types', ['post', 'page']);
        }
    }

    private static function addCustomCapability(): void
    {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_aisa');
        }
    }

    private static function createLogTable(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aisa_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT,
            post_id BIGINT NULL,
            user_id BIGINT NULL,
            action VARCHAR(100) NOT NULL,
            provider VARCHAR(50) NULL,
            model VARCHAR(100) NULL,
            input_tokens INT DEFAULT 0,
            output_tokens INT DEFAULT 0,
            success TINYINT(1) DEFAULT 1,
            error_message TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_post_id (post_id),
            INDEX idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
