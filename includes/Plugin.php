<?php

namespace AiSeoAssistant;

use AiSeoAssistant\Admin\AdminMenu;
use AiSeoAssistant\Admin\EditorAssets;
use AiSeoAssistant\Admin\PostMetaRegistrar;
use AiSeoAssistant\SEO\SEOAdapterFactory;
use AiSeoAssistant\AI\AIServiceFactory;
use AiSeoAssistant\AEO\JsonLdRenderer;
use AiSeoAssistant\REST\RestController;
use AiSeoAssistant\Support\PostTypeRegistry;
use AiSeoAssistant\Tracking\DashboardWidget;
use AiSeoAssistant\Bulk\BulkScheduler;
use AiSeoAssistant\Access\BypassManager;
use AiSeoAssistant\GSC\GSCAuthHandler;
use AiSeoAssistant\GSC\GSCContextBuilder;
use AiSeoAssistant\Scan\WeeklyScan;
use AiSeoAssistant\Scan\EmailDigest;
use AiSeoAssistant\Scan\OutcomeTracker;
use AiSeoAssistant\Scan\PerformanceDashboardWidget;

class Plugin
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void
    {
        $this->loadTextDomain();
        $this->registerHooks();
    }

    private function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'ai-seo-assistant',
            false,
            dirname(AISA_PLUGIN_BASENAME) . '/languages'
        );
    }

    private function registerHooks(): void
    {
        // Admin
        if (is_admin()) {
            add_action('admin_menu', [new AdminMenu(), 'register']);
            add_action('admin_enqueue_scripts', [new EditorAssets(), 'enqueue']);
            add_action('admin_init', [new PostTypeRegistry(), 'detectNewPostTypes']);

            // Dashboard widget (Phase 2)
            add_action('wp_dashboard_setup', [new DashboardWidget(), 'register']);
        }

        // Post meta registration (needed for REST API)
        add_action('init', [new PostMetaRegistrar(), 'register']);

        // REST API
        add_action('rest_api_init', [new RestController(), 'registerRoutes']);

        // AEO JSON-LD output on frontend
        if (!is_admin()) {
            add_action('wp_head', [new JsonLdRenderer(), 'render']);
        }

        // Abilities API (WP 7.0+)
        if (function_exists('register_ability')) {
            add_action('init', [$this, 'registerAbilities']);
        }

        // Bulk Scheduler hooks (Phase 2)
        $bulkScheduler = new BulkScheduler();
        $bulkScheduler->register();

        // WP-CLI commands (Phase 2)
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('aisa', CLI\BulkSEOCommand::class);
        }

        // Localize bypass capability info for JS (Phase 2)
        add_filter('aisa_editor_script_data', [$this, 'addBypassData']);

        // Phase 1.5: GSC OAuth callback handler
        add_action('admin_init', [GSCAuthHandler::class, 'handleCallback']);

        // Phase 1.5: Weekly scan + digest + outcome crons
        add_action('init', [WeeklyScan::class, 'schedule']);
        add_action('init', [EmailDigest::class, 'schedule']);
        add_action('init', [OutcomeTracker::class, 'schedule']);
        add_action('aisa_weekly_scan', [WeeklyScan::class, 'run']);
        add_action('aisa_weekly_digest', [EmailDigest::class, 'send']);
        add_action('aisa_outcome_check', [OutcomeTracker::class, 'check']);

        // Phase 1.5: Performance dashboard widget
        if (is_admin()) {
            add_action('wp_dashboard_setup', [new PerformanceDashboardWidget(), 'register']);
        }

        // Phase 1.5: Inject GSC context into AI prompts
        add_filter('aisa_prompt_variables', [$this, 'addGSCContext'], 10, 2);

        // Phase 1.5: Add GSC connection status to editor script data
        add_filter('aisa_editor_script_data', [$this, 'addGSCData']);
    }

    /**
     * Add bypass capability data to the editor script localization.
     */
    public function addBypassData(array $data): array
    {
        $data['canBypass'] = BypassManager::canBypass();
        return $data;
    }

    public function registerAbilities(): void
    {
        $registrar = new Abilities\AbilityRegistrar();
        $registrar->register();
    }

    public function getSEOAdapter(): SEO\SEOAdapterInterface
    {
        return SEOAdapterFactory::create();
    }

    public function getAIService(): AI\AIClientInterface
    {
        return AIServiceFactory::create();
    }

    public function getSettings(): array
    {
        return get_option('aisa_settings', Activator::getDefaultSettings());
    }

    /**
     * Inject GSC context into AI prompt variables (Phase 1.5).
     */
    public function addGSCContext(array $variables, int $postId): array
    {
        if (empty($variables['gsc_context'])) {
            $variables['gsc_context'] = GSCContextBuilder::build($postId);
        }
        return $variables;
    }

    /**
     * Add GSC connection status to editor script data (Phase 1.5).
     */
    public function addGSCData(array $data): array
    {
        $data['gscConnected'] = GSCAuthHandler::isConnected();
        return $data;
    }
}
