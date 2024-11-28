<?php
/**
 * Plugin Name:       Yachtino boat listing
 * Plugin URI:        https://update.yachtino.com/wordpress-plugin
 * Description:       Display your boats and yachts for sale from yachtall.com or yacht charter offers from happycharter.com in your own website.
 * Version:           1.5.7
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
 * Current plugin version.
 */
define('YACHTINO_VERSION', '1.5.7');
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

function yachtino_define_uri()
{
    if (defined('YACHTINO_REQUEST_URI')) {
        return;
    }
    // global $wp; - $wp is set too late, we need the server variable earlier
    // // $requestUri = home_url($wp->request);
    // $requestUri = add_query_arg($wp->query_vars, home_url($wp->request));

    $requestUri = filter_input(INPUT_SERVER, 'REQUEST_URI');
    if (!$requestUri && !empty($_SERVER['REQUEST_URI'])) {
        $requestUri = $_SERVER['REQUEST_URI'];
    }

    if (!$requestUri) {
        if (!empty($_SERVER['HTTP_X_ORIGINAL_URL'])) {
            // IIS Mod-Rewrite.
            $requestUri = $_SERVER['HTTP_X_ORIGINAL_URL'];

        } elseif (!empty($_SERVER['HTTP_X_REWRITE_URL'])) {
            // IIS Isapi_Rewrite.
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];

        } else {
            // Use ORIG_PATH_INFO if there is no PATH_INFO.
            if (!isset($_SERVER['PATH_INFO']) && isset($_SERVER['ORIG_PATH_INFO'])) {
                $_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];
            }

            // Some IIS + PHP configurations put the script-name in the path-info (no need to append it twice).
            if (isset($_SERVER['PATH_INFO'])) {
                if ($_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME']) {
                    $requestUri = $_SERVER['PATH_INFO'];
                } else {
                    $requestUri = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
                }
            }

            // Append the query string if it exists and isn't null.
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
        }
    }
    if (!$requestUri) {
        $requestUri = '/';
    }
    define('YACHTINO_REQUEST_URI', $requestUri);
}
yachtino_define_uri();

/**
 * The code that runs during plugin activation.
 * This action is documented in included file.
 */
function yachtino_activate_plugin()
{
    // yachtino_define_uri();
    /* First check prerequisites (PHP version, WP version)
    prerequisites class is compatible also with older PHP versions */
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
 * This action is documented in included file.
 */
function yachtino_deactivate_plugin()
{
    // yachtino_define_uri();
    require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-deactivator.php';
    $classActivator = new Yachtino_Deactivator();
    $classActivator->deactivate();
}
register_deactivation_hook(__FILE__, 'yachtino_deactivate_plugin');

require_once(ABSPATH . 'wp-admin/includes/plugin.php');

/**
 * Run plugin only if this plugin is active.
 */
function yachtino_run_plugin()
{
    // We try to call as few functions as possible, not to create unnecessary hooks.
    require_once YACHTINO_DIR_PATH . '/includes/class-yachtino.php';
    require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-router.php';
    require_once YACHTINO_DIR_PATH . '/includes/class-yachtino-public.php';

    add_shortcode('yachtino_module', 'Yachtino_Public::get_shortcode');

    $plugin = new Yachtino_Router();
    $plugin->run();

    // Run plugin.
    Yachtino::get_instance();
}

if (is_plugin_active('yachtino-boat-listing/yachtino-boat-listing.php')) {
    yachtino_run_plugin();
}
