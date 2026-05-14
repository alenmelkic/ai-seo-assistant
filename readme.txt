=== AI SEO Assistant ===
Contributors: alenmelkic
Tags: seo, ai, aeo, meta tags, content optimization
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered SEO and AEO content optimization for WordPress. Analyzes content quality, generates meta tags, and creates structured data.

== Description ==

AI SEO Assistant helps editors optimize their content for search engines and answer engines (AEO) using AI. When publishing an article for the first time, a two-step review modal guides the editor through:

1. **Content Quality Review** - AI analyzes the article for structural issues, missing headings, thin content, and more. Auto-fix suggestions can be applied directly to Gutenberg blocks.

2. **SEO & AEO Generation** - AI generates optimized meta titles, descriptions, tags, and AEO structured data (TL;DR, FAQ, entities) that are written to Yoast SEO or Rank Math.

**Key Features:**

* Works with Yoast SEO and Rank Math
* Supports multiple AI providers (OpenAI, Anthropic, WP AI Client)
* Generates FAQ and Article JSON-LD schema
* Headless-ready with REST API and webhook support
* Supports both Gutenberg and Classic Editor
* Bosnian/Croatian/Serbian language enforcement
* Rate limiting and failure resilience

== Installation ==

1. Upload the `ai-seo-assistant` folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory
3. Run `npm install && npm run build` for frontend assets
4. Activate the plugin through the 'Plugins' menu
5. Go to Settings > SEO AI Assistant to configure your AI provider

== Cron Setup ==

For reliable weekly scanning (Phase 1.5), we recommend using real cron instead of WP-Cron:

1. Add to wp-config.php: `define('DISABLE_WP_CRON', true);`
2. Add to your server's crontab: `*/15 * * * * curl -s https://your-site.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1`

== Changelog ==

= 1.0.0 =
* Initial release
* Phase 0: Plugin infrastructure, settings, SEO adapters, AI service layer
* Phase 1: Publish flow modal, content review, SEO/AEO generation
