<?php
/**
 * Fired when the plugin is uninstalled.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Check if user opted to delete all data
$settings = get_option('aisa_settings', []);
$delete_data = $settings['delete_data_on_uninstall'] ?? false;

if ($delete_data) {
    global $wpdb;

    // Delete all plugin options
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aisa_%'");

    // Delete all plugin post meta
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_aisa_%'");

    // Drop custom tables (Phase 1.5)
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aisa_suggestions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aisa_outcomes");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aisa_log");

    // Clear scheduled events
    wp_clear_scheduled_hook('aisa_weekly_scan');
    wp_clear_scheduled_hook('aisa_weekly_digest');
    wp_clear_scheduled_hook('aisa_track_outcomes');

    // Remove custom capability
    $role = get_role('administrator');
    if ($role) {
        $role->remove_cap('manage_aisa');
    }
}
