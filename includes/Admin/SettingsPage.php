<?php

namespace AiSeoAssistant\Admin;

use AiSeoAssistant\Activator;
use AiSeoAssistant\SEO\SEOAdapterFactory;
use AiSeoAssistant\Support\PostTypeRegistry;

class SettingsPage
{
    public function render(): void
    {
        if (!current_user_can('manage_aisa')) {
            wp_die(__('You do not have permission to access this page.', 'ai-seo-assistant'));
        }

        $this->handleSave();

        $settings = get_option('aisa_settings', Activator::getDefaultSettings());
        $seo_adapter = SEOAdapterFactory::create();
        $post_types = PostTypeRegistry::getAvailablePostTypes();
        $cron_status = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $has_wp_ai_client = class_exists('WordPress\\AI_Client\\AI_Client');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI SEO Assistant Settings', 'ai-seo-assistant'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('aisa_settings_save', 'aisa_nonce'); ?>

                <!-- AI Provider -->
                <h2><?php esc_html_e('AI Provider', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aisa_ai_provider_strategy"><?php esc_html_e('Provider Strategy', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <select name="aisa_settings[ai_provider_strategy]" id="aisa_ai_provider_strategy">
                                <option value="auto" <?php selected($settings['ai_provider_strategy'], 'auto'); ?>>
                                    <?php esc_html_e('Auto-detect', 'ai-seo-assistant'); ?>
                                </option>
                                <?php if ($has_wp_ai_client): ?>
                                <option value="wp_ai_client" <?php selected($settings['ai_provider_strategy'], 'wp_ai_client'); ?>>
                                    <?php esc_html_e('WP AI Client only', 'ai-seo-assistant'); ?>
                                </option>
                                <?php endif; ?>
                                <option value="openai" <?php selected($settings['ai_provider_strategy'], 'openai'); ?>>
                                    <?php esc_html_e('OpenAI', 'ai-seo-assistant'); ?>
                                </option>
                                <option value="anthropic" <?php selected($settings['ai_provider_strategy'], 'anthropic'); ?>>
                                    <?php esc_html_e('Anthropic', 'ai-seo-assistant'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aisa_openai_api_key"><?php esc_html_e('OpenAI API Key', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="aisa_settings[openai_api_key]" id="aisa_openai_api_key"
                                   value="<?php echo esc_attr($settings['openai_api_key']); ?>"
                                   class="regular-text" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aisa_anthropic_api_key"><?php esc_html_e('Anthropic API Key', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="aisa_settings[anthropic_api_key]" id="aisa_anthropic_api_key"
                                   value="<?php echo esc_attr($settings['anthropic_api_key']); ?>"
                                   class="regular-text" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aisa_default_model"><?php esc_html_e('Default Model', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <select name="aisa_settings[default_model]" id="aisa_default_model">
                                <option value="gpt-4o-mini" <?php selected($settings['default_model'], 'gpt-4o-mini'); ?>>gpt-4o-mini (fast, affordable)</option>
                                <option value="gpt-4o" <?php selected($settings['default_model'], 'gpt-4o'); ?>>gpt-4o (balanced)</option>
                                <option value="claude-sonnet-4-7" <?php selected($settings['default_model'], 'claude-sonnet-4-7'); ?>>claude-sonnet-4-7 (quality)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Per-Ability Model Override', 'ai-seo-assistant'); ?></th>
                        <td>
                            <?php
                            $abilities = [
                                'analyze_content' => __('Content Analysis', 'ai-seo-assistant'),
                                'generate_meta' => __('Meta Generation', 'ai-seo-assistant'),
                                'generate_tags' => __('Tags Generation', 'ai-seo-assistant'),
                                'generate_aeo' => __('AEO Generation', 'ai-seo-assistant'),
                            ];
                            $model_options = ['default', 'gpt-4o-mini', 'gpt-4o', 'claude-sonnet-4-7'];
                            foreach ($abilities as $key => $label): ?>
                                <p>
                                    <label><?php echo esc_html($label); ?>:
                                        <select name="aisa_settings[model_overrides][<?php echo esc_attr($key); ?>]">
                                            <?php foreach ($model_options as $model): ?>
                                                <option value="<?php echo esc_attr($model); ?>"
                                                    <?php selected($settings['model_overrides'][$key] ?? 'default', $model); ?>>
                                                    <?php echo $model === 'default' ? esc_html__('Use default', 'ai-seo-assistant') : esc_html($model); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </p>
                            <?php endforeach; ?>
                            <p>
                                <button type="button" class="button" id="aisa-apply-recommended">
                                    <?php esc_html_e('Apply Recommended', 'ai-seo-assistant'); ?>
                                </button>
                                <span class="description">
                                    <?php esc_html_e('claude-sonnet for Analysis & AEO, gpt-4o-mini for Meta & Tags', 'ai-seo-assistant'); ?>
                                </span>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Content Strategy -->
                <h2><?php esc_html_e('Content Strategy', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aisa_brand_voice"><?php esc_html_e('Brand Voice', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea name="aisa_settings[brand_voice]" id="aisa_brand_voice"
                                      rows="4" class="large-text" maxlength="1000"
                                      placeholder="<?php esc_attr_e('Example: We write informally, use "ti" form, avoid jargon, keep 2-3 sentences per paragraph...', 'ai-seo-assistant'); ?>"
                            ><?php echo esc_textarea($settings['brand_voice']); ?></textarea>
                            <p class="description"><?php esc_html_e('Inserted into every AI prompt. The more specific, the better. Leave empty if not yet defined.', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aisa_sample_content"><?php esc_html_e('Sample Content', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea name="aisa_settings[sample_content]" id="aisa_sample_content"
                                      rows="4" class="large-text" maxlength="2000"
                                      placeholder="<?php esc_attr_e('Paste 1-2 representative paragraphs of your existing content...', 'ai-seo-assistant'); ?>"
                            ><?php echo esc_textarea($settings['sample_content']); ?></textarea>
                            <p class="description"><?php esc_html_e('Optional. Gives better results than just a description.', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aisa_audience_description"><?php esc_html_e('Audience Description', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <textarea name="aisa_settings[audience_description]" id="aisa_audience_description"
                                      rows="3" class="large-text" maxlength="500"
                                      placeholder="<?php esc_attr_e('Who are your readers? Experts, beginners, mixed audience?', 'ai-seo-assistant'); ?>"
                            ><?php echo esc_textarea($settings['audience_description']); ?></textarea>
                        </td>
                    </tr>
                </table>

                <!-- Post Types -->
                <h2><?php esc_html_e('Active Post Types', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable for', 'ai-seo-assistant'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($post_types as $slug => $label): ?>
                                    <label>
                                        <input type="checkbox"
                                               name="aisa_settings[active_post_types][]"
                                               value="<?php echo esc_attr($slug); ?>"
                                            <?php checked(in_array($slug, $settings['active_post_types'] ?? [])); ?> />
                                        <?php echo esc_html($label); ?>
                                    </label><br/>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <!-- Language -->
                <h2><?php esc_html_e('Language', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aisa_language"><?php esc_html_e('AI Output Language', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <select name="aisa_settings[language]" id="aisa_language">
                                <option value="auto" <?php selected($settings['language'], 'auto'); ?>><?php esc_html_e('Auto-detect from WordPress locale', 'ai-seo-assistant'); ?></option>
                                <option value="bs" <?php selected($settings['language'], 'bs'); ?>>Bosanski</option>
                                <option value="hr" <?php selected($settings['language'], 'hr'); ?>>Hrvatski</option>
                                <option value="sr" <?php selected($settings['language'], 'sr'); ?>>Srpski</option>
                                <option value="en" <?php selected($settings['language'], 'en'); ?>>English</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- SEO Plugin Status -->
                <h2><?php esc_html_e('SEO Plugin', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Detected SEO Plugin', 'ai-seo-assistant'); ?></th>
                        <td>
                            <strong><?php echo esc_html($seo_adapter->getPluginName()); ?></strong>
                            <?php if (!$seo_adapter->isActive()): ?>
                                <p class="description" style="color: #d63638;">
                                    <?php esc_html_e('No SEO plugin detected. Install Yoast SEO or Rank Math for full functionality.', 'ai-seo-assistant'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <!-- Behavior -->
                <h2><?php esc_html_e('Behavior', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aisa_rate_limit"><?php esc_html_e('Rate Limit (Legacy)', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="aisa_settings[rate_limit]" id="aisa_rate_limit"
                                   value="<?php echo esc_attr($settings['rate_limit']); ?>"
                                   min="1" max="100" class="small-text" />
                            <span class="description"><?php esc_html_e('Max regenerations per article per user per hour (fallback)', 'ai-seo-assistant'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Rate Limits per Role', 'ai-seo-assistant'); ?></th>
                        <td>
                            <?php
                            $role_limits = $settings['rate_limits_per_role'] ?? [
                                'administrator' => 50, 'editor' => 20, 'author' => 10, 'contributor' => 5,
                            ];
                            foreach ($role_limits as $role => $limit): ?>
                                <p>
                                    <label><?php echo esc_html(ucfirst($role)); ?>:
                                        <input type="number"
                                               name="aisa_settings[rate_limits_per_role][<?php echo esc_attr($role); ?>]"
                                               value="<?php echo esc_attr($limit); ?>"
                                               min="1" max="200" class="small-text" />
                                        <?php esc_html_e('per hour', 'ai-seo-assistant'); ?>
                                    </label>
                                </p>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aisa_global_daily_cap"><?php esc_html_e('Global Daily Cap', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="aisa_settings[global_daily_cap]" id="aisa_global_daily_cap"
                                   value="<?php echo esc_attr($settings['global_daily_cap'] ?? 500); ?>"
                                   min="10" max="10000" class="small-text" />
                            <span class="description"><?php esc_html_e('Max AI calls per day across all users', 'ai-seo-assistant'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('On AI Failure', 'ai-seo-assistant'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="aisa_settings[failure_behavior]" value="skip"
                                        <?php checked($settings['failure_behavior'], 'skip'); ?> />
                                    <?php esc_html_e('Skip and continue publishing', 'ai-seo-assistant'); ?>
                                </label><br/>
                                <label>
                                    <input type="radio" name="aisa_settings[failure_behavior]" value="block"
                                        <?php checked($settings['failure_behavior'], 'block'); ?> />
                                    <?php esc_html_e('Block publish until resolved', 'ai-seo-assistant'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aisa_classic_editor"><?php esc_html_e('Classic Editor Support', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="aisa_settings[classic_editor_support]" id="aisa_classic_editor"
                                       value="1" <?php checked($settings['classic_editor_support']); ?> />
                                <?php esc_html_e('Enable support for Classic Editor', 'ai-seo-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <!-- Headless Mode -->
                <h2><?php esc_html_e('Headless Mode', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aisa_headless_mode"><?php esc_html_e('Enable Headless', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="aisa_settings[headless_mode]" id="aisa_headless_mode"
                                       value="1" <?php checked($settings['headless_mode']); ?> />
                                <?php esc_html_e('Enable headless revalidation webhook', 'ai-seo-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aisa_headless_webhook_url"><?php esc_html_e('Webhook URL', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="aisa_settings[headless_webhook_url]" id="aisa_headless_webhook_url"
                                   value="<?php echo esc_attr($settings['headless_webhook_url']); ?>"
                                   class="regular-text" placeholder="https://frontend.com/api/revalidate" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aisa_headless_webhook_secret"><?php esc_html_e('Webhook Secret', 'ai-seo-assistant'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="aisa_settings[headless_webhook_secret]" id="aisa_headless_webhook_secret"
                                   value="<?php echo esc_attr($settings['headless_webhook_secret']); ?>"
                                   class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e('Used for HMAC signature verification', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- System Status -->
                <h2><?php esc_html_e('System Status', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('WP AI Client', 'ai-seo-assistant'); ?></th>
                        <td>
                            <?php if ($has_wp_ai_client): ?>
                                <span style="color: #00a32a;">&#10003; <?php esc_html_e('Available (WP 7.0+)', 'ai-seo-assistant'); ?></span>
                            <?php else: ?>
                                <span style="color: #d63638;">&#10007; <?php esc_html_e('Not available (requires WP 7.0+)', 'ai-seo-assistant'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Cron Status', 'ai-seo-assistant'); ?></th>
                        <td>
                            <?php if ($cron_status): ?>
                                <span style="color: #00a32a;">&#10003; <?php esc_html_e('Real cron detected (DISABLE_WP_CRON is true)', 'ai-seo-assistant'); ?></span>
                            <?php else: ?>
                                <span style="color: #dba617;">&#9888; <?php esc_html_e('WP-Cron active — we recommend real cron for reliable weekly scanning', 'ai-seo-assistant'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <!-- Data -->
                <h2><?php esc_html_e('Data', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Uninstall Behavior', 'ai-seo-assistant'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aisa_settings[delete_data_on_uninstall]"
                                       value="1" <?php checked($settings['delete_data_on_uninstall']); ?> />
                                <?php esc_html_e('Delete all plugin data when uninstalling', 'ai-seo-assistant'); ?>
                            </label>
                            <p class="description" style="color: #d63638;">
                                <?php esc_html_e('Warning: This will permanently delete all AI-generated meta data, logs, and settings.', 'ai-seo-assistant'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'ai-seo-assistant')); ?>
            </form>
        </div>

        <script>
        document.getElementById('aisa-apply-recommended')?.addEventListener('click', function() {
            const overrides = {
                analyze_content: 'claude-sonnet-4-7',
                generate_meta: 'gpt-4o-mini',
                generate_tags: 'gpt-4o-mini',
                generate_aeo: 'claude-sonnet-4-7'
            };
            Object.entries(overrides).forEach(([key, val]) => {
                const sel = document.querySelector(`select[name="aisa_settings[model_overrides][${key}]"]`);
                if (sel) sel.value = val;
            });
        });
        </script>
        <?php
    }

    private function handleSave(): void
    {
        if (!isset($_POST['aisa_nonce']) || !wp_verify_nonce($_POST['aisa_nonce'], 'aisa_settings_save')) {
            return;
        }

        if (!current_user_can('manage_aisa')) {
            return;
        }

        $input = $_POST['aisa_settings'] ?? [];
        $defaults = Activator::getDefaultSettings();

        $sanitized = [
            'ai_provider_strategy' => sanitize_text_field($input['ai_provider_strategy'] ?? $defaults['ai_provider_strategy']),
            'openai_api_key' => sanitize_text_field($input['openai_api_key'] ?? ''),
            'anthropic_api_key' => sanitize_text_field($input['anthropic_api_key'] ?? ''),
            'default_model' => sanitize_text_field($input['default_model'] ?? $defaults['default_model']),
            'model_overrides' => array_map('sanitize_text_field', $input['model_overrides'] ?? $defaults['model_overrides']),
            'brand_voice' => sanitize_textarea_field($input['brand_voice'] ?? ''),
            'sample_content' => sanitize_textarea_field($input['sample_content'] ?? ''),
            'audience_description' => sanitize_textarea_field($input['audience_description'] ?? ''),
            'active_post_types' => array_map('sanitize_text_field', $input['active_post_types'] ?? []),
            'rate_limit' => absint($input['rate_limit'] ?? $defaults['rate_limit']),
            'rate_limits_per_role' => array_map('absint', $input['rate_limits_per_role'] ?? $defaults['rate_limits_per_role']),
            'global_daily_cap' => max(10, min(10000, absint($input['global_daily_cap'] ?? 500))),
            'failure_behavior' => in_array($input['failure_behavior'] ?? '', ['skip', 'block']) ? $input['failure_behavior'] : $defaults['failure_behavior'],
            'headless_mode' => !empty($input['headless_mode']),
            'headless_webhook_url' => esc_url_raw($input['headless_webhook_url'] ?? ''),
            'headless_webhook_secret' => sanitize_text_field($input['headless_webhook_secret'] ?? ''),
            'language' => sanitize_text_field($input['language'] ?? $defaults['language']),
            'classic_editor_support' => !empty($input['classic_editor_support']),
            'delete_data_on_uninstall' => !empty($input['delete_data_on_uninstall']),
        ];

        // Clamp rate limit
        $sanitized['rate_limit'] = max(1, min(100, $sanitized['rate_limit']));

        update_option('aisa_settings', $sanitized);

        add_settings_error('aisa_settings', 'aisa_saved', __('Settings saved.', 'ai-seo-assistant'), 'success');
    }
}
