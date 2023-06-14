<?php
/**
 * Yachtino boat listing
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 *
 * Fired when the plugin is uninstalled.
 *
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function yachtino_uninstall()
{
    global $wpdb;

    delete_option('yachtino_settings');

    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'yachtino_routes');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'yachtino_route_master');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'yachtino_modules');

    // delete PHP files/code from hard disc
    $pathToPluginDir = __DIR__;
    require_once __DIR__ . '/includes/api/class-yachtino-library.php';
    Yachtino_Library::truncateDirectory($pathToPluginDir, true);

    flush_rewrite_rules();
}

yachtino_uninstall();
