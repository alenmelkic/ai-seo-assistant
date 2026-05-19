<?php

namespace AiSeoAssistant\Scan;

use AiSeoAssistant\Suggestions\SuggestionRepository;
use AiSeoAssistant\Support\Logger;

class EmailDigest
{
    private const CRON_HOOK = 'aisa_weekly_digest';

    /**
     * Schedule the weekly email digest.
     */
    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule for next Monday at 08:00
            $nextMonday = strtotime('next Monday 08:00:00');
            wp_schedule_event($nextMonday, 'weekly', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Send the weekly digest email.
     */
    public static function send(): void
    {
        $pendingCount = SuggestionRepository::countPending();
        if ($pendingCount === 0) {
            Logger::info('Email digest skipped: no pending suggestions');
            return;
        }

        // Get top 5 most recent pending suggestions
        $topSuggestions = SuggestionRepository::query([
            'status' => 'pending',
            'per_page' => 5,
            'exclude_snoozed' => true,
        ]);

        // Get users with manage_aisa capability
        $recipients = self::getRecipients();
        if (empty($recipients)) {
            Logger::info('Email digest skipped: no recipients found');
            return;
        }

        $subject = sprintf(
            __('SEO AI: %d new suggestions for review', 'ai-seo-assistant'),
            $pendingCount
        );

        $body = self::buildEmailBody($pendingCount, $topSuggestions);

        foreach ($recipients as $email) {
            wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        }

        Logger::info("Email digest sent to " . count($recipients) . " recipients ({$pendingCount} pending suggestions)");
    }

    private static function getRecipients(): array
    {
        $users = get_users([
            'capability' => 'manage_aisa',
            'fields' => ['user_email'],
        ]);

        return array_map(fn($u) => $u->user_email, $users);
    }

    private static function buildEmailBody(int $pendingCount, array $suggestions): string
    {
        $inboxUrl = admin_url('admin.php?page=aisa-suggestions');
        $siteName = get_bloginfo('name');

        $html = '<div style="font-family: -apple-system, BlinkMacSystemFont, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<h2 style="color: #1e1e1e;">' . esc_html($siteName) . ' - SEO AI Weekly Digest</h2>';
        $html .= '<p style="color: #757575;">';
        $html .= sprintf(
            __('You have <strong>%d pending suggestions</strong> to review.', 'ai-seo-assistant'),
            $pendingCount
        );
        $html .= '</p>';

        if (!empty($suggestions)) {
            $html .= '<h3 style="color: #1e1e1e; margin-top: 24px;">' . __('Top Suggestions', 'ai-seo-assistant') . '</h3>';
            $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 14px;">';
            $html .= '<tr style="background: #f6f7f7;">';
            $html .= '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #e0e0e0;">' . __('Post', 'ai-seo-assistant') . '</th>';
            $html .= '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #e0e0e0;">' . __('Type', 'ai-seo-assistant') . '</th>';
            $html .= '<th style="text-align: left; padding: 8px; border-bottom: 1px solid #e0e0e0;">' . __('Reason', 'ai-seo-assistant') . '</th>';
            $html .= '</tr>';

            foreach ($suggestions as $suggestion) {
                $post = get_post($suggestion->postId);
                $postTitle = $post ? $post->post_title : '(deleted)';
                $typeLabels = [
                    'meta_title' => __('Meta Title', 'ai-seo-assistant'),
                    'meta_description' => __('Meta Desc', 'ai-seo-assistant'),
                    'aeo_refresh' => __('AEO Refresh', 'ai-seo-assistant'),
                ];

                $html .= '<tr>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #e0e0e0;">' . esc_html(wp_trim_words($postTitle, 8)) . '</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #e0e0e0;">' . esc_html($typeLabels[$suggestion->type] ?? $suggestion->type) . '</td>';
                $html .= '<td style="padding: 8px; border-bottom: 1px solid #e0e0e0;">' . esc_html(wp_trim_words($suggestion->reason ?? '', 12)) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table>';
        }

        $html .= '<p style="margin-top: 24px;">';
        $html .= '<a href="' . esc_url($inboxUrl) . '" style="display: inline-block; background: #3858e9; color: #fff; text-decoration: none; padding: 10px 24px; border-radius: 4px; font-weight: 600;">';
        $html .= __('Review Suggestions', 'ai-seo-assistant');
        $html .= '</a></p>';

        $html .= '<p style="color: #757575; font-size: 12px; margin-top: 32px;">';
        $html .= __('This email was sent by AI SEO Assistant. You can manage your settings in WordPress admin.', 'ai-seo-assistant');
        $html .= '</p></div>';

        return $html;
    }
}
