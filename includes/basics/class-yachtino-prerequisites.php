<?php
/**
 * Class for checking PHP version and WP version
 * PHP of this class must be compatible with PHP 7 and PHP 8
 */
class Yachtino_Prerequisites
{
    /**
     * Check that the minimum PHP and WP versions are met
     */
    public static function check_prerequisites()
    {
        // versions OK
        if (version_compare(phpversion(), YACHTINO_MIN_PHP_VERSION, '>=')
        && version_compare(get_bloginfo('version'), YACHTINO_MIN_WP_VERSION, '>=')) {
            return true;
        }

        // the versions are not enough
        // add_action('admin_head', self::class . '::prerequisites_failed', 0, 0);
        self::prerequisites_failed();

        return false;
    }

    public static function prerequisites_failed()
    {
        // load translation
        require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-i18n.php';

        if (is_admin()) {
            $pluginName = __('Yo_plugin_name', 'yachtino-boat-listing');
            printf('<div class="error"><p>%s</p></div>',
                sprintf(__('%1$s requires WordPress %2$s or higher and PHP %3$s or higher.', 'yachtino-boat-listing'),
                    $pluginName, YACHTINO_MIN_WP_VERSION, YACHTINO_MIN_PHP_VERSION));
            wp_die();

        } else {
            echo __('err_web_does_not_exist', 'yachtino-boat-listing');
            wp_die();
        }
    }

}
