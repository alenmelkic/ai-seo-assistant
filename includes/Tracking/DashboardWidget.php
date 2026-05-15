<?php

namespace AiSeoAssistant\Tracking;

use AiSeoAssistant\Audit\AuditLogger;

class DashboardWidget
{
    public function register(): void
    {
        wp_add_dashboard_widget(
            'aisa_dashboard_widget',
            __('AI SEO Assistant', 'ai-seo-assistant'),
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_aisa')) {
            echo '<p>' . esc_html__('Insufficient permissions.', 'ai-seo-assistant') . '</p>';
            return;
        }

        $tracker = new TokenCostTracker();
        $auditLogger = new AuditLogger();

        $usage = $tracker->getUsageSummary(30);
        $totalCost = $tracker->getTotalCost(30);
        $auditStats = $auditLogger->getStats(30);

        $totalCalls = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $failures = 0;
        foreach ($usage as $row) {
            $totalCalls += (int) $row['calls'];
            $totalInputTokens += (int) $row['total_input_tokens'];
            $totalOutputTokens += (int) $row['total_output_tokens'];
            $failures += (int) $row['failed'];
        }

        ?>
        <div class="aisa-widget">
            <h3><?php esc_html_e('Last 30 Days', 'ai-seo-assistant'); ?></h3>

            <div class="aisa-widget__grid">
                <div class="aisa-widget__stat">
                    <span class="aisa-widget__number"><?php echo esc_html(number_format($totalCalls)); ?></span>
                    <span class="aisa-widget__label"><?php esc_html_e('AI Calls', 'ai-seo-assistant'); ?></span>
                </div>
                <div class="aisa-widget__stat">
                    <span class="aisa-widget__number">$<?php echo esc_html(number_format($totalCost, 2)); ?></span>
                    <span class="aisa-widget__label"><?php esc_html_e('Est. Cost', 'ai-seo-assistant'); ?></span>
                </div>
                <div class="aisa-widget__stat">
                    <span class="aisa-widget__number"><?php echo esc_html(number_format($totalInputTokens + $totalOutputTokens)); ?></span>
                    <span class="aisa-widget__label"><?php esc_html_e('Tokens', 'ai-seo-assistant'); ?></span>
                </div>
                <div class="aisa-widget__stat">
                    <span class="aisa-widget__number"><?php echo esc_html($failures); ?></span>
                    <span class="aisa-widget__label"><?php esc_html_e('Failures', 'ai-seo-assistant'); ?></span>
                </div>
            </div>

            <?php if (!empty($auditStats)): ?>
            <h3><?php esc_html_e('Editor Decisions', 'ai-seo-assistant'); ?></h3>
            <div class="aisa-widget__grid">
                <div class="aisa-widget__stat">
                    <span class="aisa-widget__number"><?php echo esc_html($auditStats['accepted'] ?? 0); ?></span>
                    <span class="aisa-widget__label"><?php esc_html_e('Accepted', 'ai-seo-assistant'); ?></span>
                </div>
                <div class="aisa-widget__stat">
                    <span class="aisa-widget__number"><?php echo esc_html($auditStats['modified'] ?? 0); ?></span>
                    <span class="aisa-widget__label"><?php esc_html_e('Modified', 'ai-seo-assistant'); ?></span>
                </div>
                <div class="aisa-widget__stat">
                    <span class="aisa-widget__number"><?php echo esc_html($auditStats['skipped'] ?? 0); ?></span>
                    <span class="aisa-widget__label"><?php esc_html_e('Skipped', 'ai-seo-assistant'); ?></span>
                </div>
                <div class="aisa-widget__stat">
                    <span class="aisa-widget__number"><?php echo esc_html($auditStats['posts_affected'] ?? 0); ?></span>
                    <span class="aisa-widget__label"><?php esc_html_e('Posts', 'ai-seo-assistant'); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($usage)): ?>
            <h3><?php esc_html_e('Usage by Model', 'ai-seo-assistant'); ?></h3>
            <table class="widefat striped" style="margin-top: 8px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Model', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Calls', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Tokens', 'ai-seo-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usage as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['model']); ?></td>
                        <td><?php echo esc_html(number_format((int) $row['calls'])); ?></td>
                        <td><?php echo esc_html(number_format((int) $row['total_input_tokens'] + (int) $row['total_output_tokens'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <style>
        .aisa-widget__grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 8px 0 16px; }
        .aisa-widget__stat { text-align: center; padding: 8px; background: #f6f7f7; border-radius: 4px; }
        .aisa-widget__number { display: block; font-size: 20px; font-weight: 600; color: #1e1e1e; }
        .aisa-widget__label { font-size: 11px; color: #757575; text-transform: uppercase; }
        </style>
        <?php
    }
}
