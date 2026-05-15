<?php

namespace AiSeoAssistant\Access;

use AiSeoAssistant\Audit\AuditLogger;

class BypassManager
{
    public const CAPABILITY = 'bypass_aisa_review';

    /**
     * Register the bypass capability on plugin activation.
     */
    public static function addCapability(): void
    {
        $adminRole = get_role('administrator');
        if ($adminRole) {
            $adminRole->add_cap(self::CAPABILITY);
        }

        $editorRole = get_role('editor');
        if ($editorRole) {
            $editorRole->add_cap(self::CAPABILITY);
        }
    }

    /**
     * Remove the bypass capability on plugin deactivation.
     */
    public static function removeCapability(): void
    {
        foreach (['administrator', 'editor'] as $roleName) {
            $role = get_role($roleName);
            if ($role) {
                $role->remove_cap(self::CAPABILITY);
            }
        }
    }

    /**
     * Check if the current user can bypass SEO review.
     */
    public static function canBypass(?int $userId = null): bool
    {
        if ($userId !== null) {
            return user_can($userId, self::CAPABILITY);
        }

        return current_user_can(self::CAPABILITY);
    }

    /**
     * Process a bypass — log it and mark the post as confirmed (skipped).
     */
    public static function processBypass(int $postId, int $userId): void
    {
        update_post_meta($postId, '_aisa_confirmed', [
            'date' => current_time('mysql'),
            'user_id' => $userId,
            'version' => AISA_VERSION,
            'skipped' => true,
            'bypass' => true,
        ]);

        $auditLogger = new AuditLogger();
        $auditLogger->logSkip($postId, $userId, 'bypass_capability');
    }
}
