<?php

namespace AiSeoAssistant\Support;

use AiSeoAssistant\AI\AIResponse;

class Logger
{
    public static function info(string $message): void
    {
        self::log('info', $message);
    }

    public static function warning(string $message): void
    {
        self::log('warning', $message);
    }

    public static function error(string $message): void
    {
        self::log('error', $message);
    }

    public static function logAICall(
        int $postId,
        string $action,
        string $provider,
        string $model,
        AIResponse $response
    ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'aisa_log';

        $wpdb->insert($table, [
            'post_id' => $postId,
            'user_id' => get_current_user_id(),
            'action' => $action,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'success' => $response->success ? 1 : 0,
            'error_message' => $response->errorMessage,
            'created_at' => current_time('mysql'),
        ], [
            '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s',
        ]);
    }

    private static function log(string $level, string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[AI SEO Assistant][{$level}] {$message}");
        }
    }
}
