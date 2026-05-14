<?php

namespace AiSeoAssistant;

class Deactivator
{
    public static function deactivate(): void
    {
        // Clear all scheduled cron events
        wp_clear_scheduled_hook('aisa_weekly_scan');
        wp_clear_scheduled_hook('aisa_weekly_digest');
        wp_clear_scheduled_hook('aisa_track_outcomes');
    }
}
