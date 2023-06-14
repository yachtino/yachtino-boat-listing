<?php

declare(strict_types=1);

/**
 * Yachtino boat listing
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

class Yachtino
{
    private object $loader;

    /**
     * singleton pattern
     */
    private static ?self $instance = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public static function get_instance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
            self::$instance->load_dependencies();

            self::$instance->define_public_hooks();

            if (is_admin()) {
                self::$instance->define_admin_hooks();
            }

            self::$instance->loader->run();
        }
        return self::$instance;
    }

    public static function get_plugin_settings(): StdClass
    {
        static $settings;

        if (!empty($settings)) {
            return $settings;
        }

        $settings = new StdClass();

        $setts = get_option('yachtino_settings');
        if ($setts) {
            $settings->apiKey    = $setts['apiKey'];
            $settings->apiSiteId = $setts['apiSiteId'];
            $settings->languages = $setts['languages'];
            $settings->version   = $setts['version'];
        } else {
            $settings->apiKey    = '';
            $settings->apiSiteId = '';
            $settings->languages = [];
            $settings->version   = '';
        }
        return $settings;
    }

    // WP calls wp-emoji-release.min.js and this js calls remote servers (bad for GDPR)
    public static function remove_emoji(): void
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        // add_filter('tiny_mce_plugins', 'remove_tinymce_emoji');
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies(): void
    {
        /**
         * Main class for getting boats from yachtino API
         */
        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-api.php';

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-loader.php';

        if (is_admin()) {
            /**
             * Help functions
             */
            require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-library.php';

            /**
             * The class responsible for defining all actions that occur in the admin area.
             */
            require_once YACHTINO_DIR_PATH . '/includes/class-yachtino-admin.php';

            /**
             * The class responsible for defining internationalization functionality
             * of the plugin.
             */
            require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-i18n.php';
        }

        /**
         * The class for sending customer request for a boat/trailer/engine...
         */
        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-request.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once YACHTINO_DIR_PATH . '/includes/class-yachtino-public.php';

        $this->loader = new Yachtino_Loader();
    }

    public function define_public_hooks(): void
    {
        $pluginPublic = new Yachtino_Public();

        $this->loader->add_action('wp_enqueue_scripts', $pluginPublic, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $pluginPublic, 'enqueue_scripts');

        // getting new URL after submitting search form
        $this->loader->add_action('wp_ajax_nopriv_yachtino_get_searchurl', $pluginPublic, 'ajax_get_url_for_list');
        $this->loader->add_action('wp_ajax_yachtino_get_searchurl', $pluginPublic, 'ajax_get_url_for_list');

        $pluginRequest = Yachtino_Request::get_instance();

        // sending request from boat/trailer detail view
        $this->loader->add_action('wp_ajax_nopriv_yachtino_send_request', $pluginRequest, 'ajax_send_request');
        $this->loader->add_action('wp_ajax_yachtino_send_request', $pluginRequest, 'ajax_send_request');

        // show form for special requests (insurance, financing, transport)
        $this->loader->add_action('wp_ajax_nopriv_yachtino_get_specrequest', $pluginRequest, 'ajax_get_specrequest');
        $this->loader->add_action('wp_ajax_yachtino_get_specrequest', $pluginRequest, 'ajax_get_specrequest');

        // send special requests (insurance, financing, transport)
        $this->loader->add_action('wp_ajax_nopriv_yachtino_send_specrequest', $pluginRequest, 'ajax_send_specrequest');
        $this->loader->add_action('wp_ajax_yachtino_send_specrequest', $pluginRequest, 'ajax_send_specrequest');

        // add_action('init', self::class . '::remove_emoji');
    }

    public function define_admin_hooks(): void
    {
        $pluginAdmin = Yachtino_Admin::get_instance();

        // $this->loader->add_action('init', $pluginAdmin, 'check_version');
        $this->loader->add_action('init', $pluginAdmin, 'check_access');

        $this->loader->add_action('admin_enqueue_scripts', $pluginAdmin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $pluginAdmin, 'enqueue_scripts');

        $this->loader->add_action('init', 'Yachtino_i18n', 'load_plugin_textdomain');
        $this->loader->add_filter('load_textdomain_mofile', 'Yachtino_i18n', 'load_my_own_textdomain', 10, 2);

        $this->loader->add_action('admin_menu', $pluginAdmin, 'adminmenu');
    }

    public static function article_types(): array
    {
        return ['cboat', 'sboat', 'engine', 'trailer',];
    }

    // important for extracting string to be translated
    public function poedit(): void
    {
        __('Display your boats and yachts for sale from yachtall.com or yacht charter offers from happycharter.com in your own website.', 'yachtino-boat-listing');
        __('You need to have an account on yachtall.com or happycharter.com to use this plugin. Then you can integrate your boats into your website with a few clicks.', 'yachtino-boat-listing');
    }

}