<?php
/**
 * Plugin Name: AI SEO Assistant
 * Plugin URI: https://github.com/alen-melkic/ai-seo-assistant
 * Description: AI-powered SEO and AEO content optimization for WordPress. Analyzes content quality, generates meta tags, and creates AEO-ready structured data.
 * Version: 1.0.0
 * Author: Alen Melkic
 * Author URI: https://github.com/alen-melkic
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-seo-assistant
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

define('AISA_VERSION', '1.0.0');
define('AISA_PLUGIN_FILE', __FILE__);
define('AISA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AISA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AISA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
if (file_exists(AISA_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once AISA_PLUGIN_DIR . 'vendor/autoload.php';
}

// Activation / Deactivation
register_activation_hook(__FILE__, [AiSeoAssistant\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [AiSeoAssistant\Deactivator::class, 'deactivate']);

// Boot the plugin
add_action('plugins_loaded', function () {
    AiSeoAssistant\Plugin::getInstance()->init();
});
