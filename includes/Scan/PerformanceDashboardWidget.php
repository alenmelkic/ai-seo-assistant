<?php

namespace AiSeoAssistant\Scan;

use AiSeoAssistant\Suggestions\SuggestionRepository;

class PerformanceDashboardWidget
{
    public function register(): void
    {
        wp_add_dashboard_widget(
            'aisa_performance_widget',
            __('AI SEO Performance', 'ai-seo-assistant'),
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_aisa')) {
            echo '<p>' . esc_html__('Insufficient permissions.', 'ai-seo-assistant') . '</p>';
            return;
        }

        $stats = OutcomeTracker::getStats(30);
        $pendingCount = SuggestionRepository::countPending();

        ?>
        <div class="aisa-performance-widget">
            <?php if ($pendingCount > 0): ?>
                <p>
                    <strong><?php echo esc_html($pendingCount); ?></strong>
                    <?php esc_html_e('pending suggestions', 'ai-seo-assistant'); ?>
                    &mdash;
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aisa-suggestions')); ?>">
                        <?php esc_html_e('Review now', 'ai-seo-assistant'); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ($stats['total_accepted'] > 0): ?>
                <h4><?php esc_html_e('Last 30 days', 'ai-seo-assistant'); ?></h4>
                <table class="widefat" style="border: 0;">
                    <tr>
                        <td><?php esc_html_e('Suggestions accepted', 'ai-seo-assistant'); ?></td>
                        <td><strong><?php echo esc_html($stats['total_accepted']); ?></strong></td>
                    </tr>
                    <?php if ($stats['total_measured'] > 0): ?>
                        <tr>
                            <td><?php esc_html_e('Outcomes measured', 'ai-seo-assistant'); ?></td>
                            <td><strong><?php echo esc_html($stats['total_measured']); ?></strong></td>
                        </tr>
                        <?php if ($stats['avg_ctr_change'] !== null): ?>
                            <tr>
                                <td><?php esc_html_e('Avg CTR change', 'ai-seo-assistant'); ?></td>
                                <td>
                                    <strong style="color: <?php echo esc_attr($stats['avg_ctr_change'] >= 0 ? '#00a32a' : '#d63638'); ?>">
                                        <?php echo $stats['avg_ctr_change'] >= 0 ? '+' : ''; ?><?php echo esc_html($stats['avg_ctr_change']); ?>pp
                                    </strong>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($stats['avg_position_change'] !== null): ?>
                            <tr>
                                <td><?php esc_html_e('Avg position change', 'ai-seo-assistant'); ?></td>
                                <td>
                                    <strong style="color: <?php echo esc_attr($stats['avg_position_change'] >= 0 ? '#00a32a' : '#d63638'); ?>">
                                        <?php echo $stats['avg_position_change'] >= 0 ? '+' : ''; ?><?php echo esc_html($stats['avg_position_change']); ?>
                                    </strong>
                                    <span style="font-size: 12px; color: #757575;">
                                        (<?php echo esc_html($stats['improved_count']); ?>/<?php echo esc_html($stats['total_measured']); ?> <?php esc_html_e('improved', 'ai-seo-assistant'); ?>)
                                    </span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" style="color: #757575;">
                                <?php esc_html_e('Performance data will be available 28 days after accepting suggestions.', 'ai-seo-assistant'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            <?php else: ?>
                <p style="color: #757575;">
                    <?php esc_html_e('No suggestions accepted yet. Connect GSC and run a scan to start getting suggestions.', 'ai-seo-assistant'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
