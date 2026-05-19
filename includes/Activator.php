<?php

namespace AiSeoAssistant;

class Activator
{
    public static function activate(): void
    {
        self::createDefaultOptions();
        self::addCustomCapability();
        self::createLogTable();
        self::createAuditTable();
        self::createBulkBatchesTable();
        self::createABTestTable();
        self::createSuggestionsTable();
        self::createOutcomesTable();

        Access\BypassManager::addCapability();

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
            'rate_limits_per_role' => [
                'administrator' => 50,
                'editor' => 20,
                'author' => 10,
                'contributor' => 5,
            ],
            'global_daily_cap' => 500,
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

    private static function createAuditTable(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aisa_audit_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT,
            post_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            action VARCHAR(50) NOT NULL,
            field VARCHAR(100) NOT NULL,
            previous_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            ai_suggestion LONGTEXT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'modal',
            metadata JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_post_id (post_id),
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private static function createBulkBatchesTable(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aisa_bulk_batches';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT,
            batch_id VARCHAR(36) NOT NULL,
            user_id BIGINT NOT NULL,
            post_ids JSON NOT NULL,
            total_posts INT NOT NULL DEFAULT 0,
            processed_posts INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            source VARCHAR(20) NOT NULL DEFAULT 'admin_ui',
            results LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE INDEX idx_batch_id (batch_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private static function createABTestTable(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aisa_ab_tests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT,
            ability VARCHAR(50) NOT NULL,
            variant_a VARCHAR(20) NOT NULL,
            variant_b VARCHAR(20) NOT NULL,
            traffic_split INT NOT NULL DEFAULT 50,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            impressions_a INT NOT NULL DEFAULT 0,
            impressions_b INT NOT NULL DEFAULT 0,
            accepts_a INT NOT NULL DEFAULT 0,
            accepts_b INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME NULL,
            PRIMARY KEY (id),
            INDEX idx_ability_status (ability, status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private static function createSuggestionsTable(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aisa_suggestions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT,
            post_id BIGINT NOT NULL,
            type VARCHAR(50) NOT NULL,
            current_value TEXT NULL,
            suggested_value TEXT NULL,
            reason TEXT NULL,
            gsc_snapshot JSON NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            snoozed_until DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            reviewed_by BIGINT NULL,
            PRIMARY KEY (id),
            INDEX idx_status_created (status, created_at),
            INDEX idx_post_id (post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private static function createOutcomesTable(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aisa_outcomes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT,
            suggestion_id BIGINT NOT NULL,
            post_id BIGINT NOT NULL,
            type VARCHAR(50) NOT NULL,
            gsc_before JSON NULL,
            gsc_after JSON NULL,
            position_change DECIMAL(5,1) NULL,
            ctr_change DECIMAL(5,2) NULL,
            impressions_change INT NULL,
            measured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_suggestion_id (suggestion_id),
            INDEX idx_post_id (post_id),
            INDEX idx_measured_at (measured_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
