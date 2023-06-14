<?php
/**
 * Plugin Name:       Yachtino boat listing
 * Plugin URI:        https://update.yachtino.com/wordpress-plugin
 * Description:       Display your boats and yachts for sale from yachtall.com or yacht charter offers from happycharter.com in your own website.
 * Version:           1.4.3
 * Requires at least: 6.0
 * Rquires PHP:       8.0
 * Author:            Yachtino GmbH
 * Author URI:        https://www.yachtino.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       yachtino-boat-listing
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('YACHTINO_VERSION', '1.4.3');
define('YACHTINO_MIN_PHP_VERSION', '8.0');
define('YACHTINO_MIN_WP_VERSION', '6.0');
define('YACHTINO_DIR_PATH', __DIR__); // NO trailing slash

// Because WP automatically adds </p> to every line ending, remove that function completely on yachtino pages.
add_filter('the_content', 'yachtino_remove_autop', 0);
function yachtino_remove_autop($content)
{
    'yachtino_page' === get_post_type() && remove_filter('the_content', 'wpautop');
    return $content;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in included file
 */
function yachtino_activate_plugin()
{
    // first check prerequisites (PHP version, WP version)
    // prerequisites class is compatible also with older PHP versions
    require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-prerequisites.php';
    $isOk = Yachtino_Prerequisites::check_prerequisites();

    if ($isOk) {
        require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-activator.php';
        $classActivator = new Yachtino_Activator();
        $classActivator->activate();
    }
}
register_activation_hook(__FILE__, 'yachtino_activate_plugin');

/**
 * The code that runs during plugin deactivation.
 * This action is documented in included file
 */
function yachtino_deactivate_plugin()
{
    require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-deactivator.php';
    $classActivator = new Yachtino_Deactivator();
    $classActivator->deactivate();
}
register_deactivation_hook(__FILE__, 'yachtino_deactivate_plugin');

require_once(ABSPATH . 'wp-admin/includes/plugin.php');

/**
 * only if this plugin is active
 */
if (is_plugin_active('yachtino-boat-listing/yachtino-boat-listing.php')) {

    // we try to call as few functions as possible
    // not to create unnecessary hooks
    require_once YACHTINO_DIR_PATH . '/includes/class-yachtino.php';
    require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-router.php';
    require_once YACHTINO_DIR_PATH . '/includes/class-yachtino-public.php';

    add_shortcode('yachtino_module', 'Yachtino_Public::get_shortcode');

    $plugin = new Yachtino_Router();
    $plugin->run();

    // run plugin
    Yachtino::get_instance();
}
