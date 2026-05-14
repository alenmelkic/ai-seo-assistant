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
}
