<?php

declare(strict_types=1);

/**
 * Yachtino boat listing
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

// Don't access directly
if (!defined('ABSPATH')) {
    exit();
};

class Yachtino_Public
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles(): void
    {
        wp_enqueue_style(
            'yachtino-public',
            plugins_url('/assets/css/yachtino-public.css', YACHTINO_DIR_PATH . '/yachtino-boat-listing.php'),
            [],
            YACHTINO_VERSION,
            'all',
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts(): void
    {
        wp_enqueue_script(
            'yachtino-public',
            plugins_url('/assets/js/yachtino-public.js', YACHTINO_DIR_PATH . '/yachtino-boat-listing.php'),
            ['jquery'],
            YACHTINO_VERSION,
            false,
        );
        wp_localize_script('yachtino-public', 'yachtino_public', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    public static function get_shortcode_head(array $atts): StdClass
    {
        $output = new StdClass();
        $output->errors = '';
        $output->info   = [
            'title'       => '',
            'description' => '',
        ];

        $basicInfo = self::define_shortcode_basics($atts);

        if ($basicInfo['errors']) {
            $output->errors = $basicInfo['errors'];
            return $output;
        }

        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-api.php';
        $classApi = Yachtino_Api::get_instance();

        $apiResponse = $classApi->get_data_from_api(
            $basicInfo['routingData']->module['pageType'],
            $basicInfo['routingData']->module,
            $basicInfo['routingData']->module['itemId'],
        );

        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-article.php';
        $output->info = Yachtino_Article::get_head_shortcode(
            $basicInfo['routingData']->module['pageType'],
            $apiResponse
        );

        return $output;
    }

    public static function define_shortcode_basics(array $atts): array
    {
        global $wpdb;

        $output = [
            'errors'      => '',
            'routingData' => null,
        ];
        require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-router.php';
        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-api.php';
        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-library.php';

        if (empty($atts['lg']) || !is_string($atts['lg']) || !preg_match('/^[a-z]{2}$/', $atts['lg'])) {
            $output['errors'] .= 'No language given. Please add attribute lg="en" or other language to the shortcode.<br>';
        }
        if (empty($atts['name']) || !is_string($atts['name'])) {
            $output['errors'] .= 'No name of Yachtino module given. Please add attribute name="xxxxxx" to the shortcode.<br>';
        }

        $itemId = '';
        if (!$output['errors']) {

            // Get module for this shortcode.
            $sql = 'SELECT `md`.`module_id`, `md`.`itemType`, `md`.`pageType`, `md`.`settings`, '
                    . '`md`.`filter`, `md`.`searchForm`, '
                    . '`md`.`linkedDetailMaster`, `md`.`linkedDetailUrl` '
                . 'FROM `' . $wpdb->prefix . 'yachtino_modules` AS `md` '
                . 'WHERE `md`.`name` = "' . addslashes($atts['name']) . '"';
            $module = $wpdb->get_results($sql);
            if (!$module || empty($module[0])) {
                $output['errors'] .= 'There is no module named "' . $atts['name'] . '".<br>';


            } elseif ($module[0]->pageType == 'detail') {
                if (!empty($atts['itemid']) && preg_match('/^[csetmg]?[0-9]+$/', $atts['itemid'])) {
                    $itemId = $atts['itemid'];

                } else {
                    $output['errors'] .= 'No item ID (eg. boat ID) given. Please add attribute '
                        . 'itemid="s12345" or itemid="c12345" to the shortcode.<br>';
                }
            }
        }

        if ($output['errors']) {
            return $output;
        }

        if ($module[0]->pageType == 'list' && !empty($atts['criteria'])) {
            $addCriteria = Yachtino_Library::explodeSearchString($atts['criteria']);
            $addCriteria = Yachtino_Api::filter_criteria($module[0]->itemType, $addCriteria);
        } else {
            $addCriteria = [];
        }

        $classRouting = new Yachtino_Router();
        $classRouting->set_routing_data([], $atts['lg'], $module, null, $itemId, $addCriteria);

        $classApi = Yachtino_Api::get_instance();
        $output['routingData'] = $classApi->get_routing_data();

        return $output;
    }

    public static function get_shortcode(array $atts, ?string $content = null): string
    {
        Yachtino::remove_emoji();

        $basicInfo = self::define_shortcode_basics($atts);

        if ($basicInfo['errors']) {
            return $basicInfo['errors'];
        }

        $classApi = Yachtino_Api::get_instance();

        // Article detail view.
        if ($basicInfo['routingData']->module['pageType'] == 'detail') {
            return $classApi->get_article_detail(
                $basicInfo['routingData']->module,
                $basicInfo['routingData']->module['itemId'],
                $basicInfo['routingData']->route,
            );

        // Article list.
        } else {
            return $classApi->get_article_list($basicInfo['routingData']->module, $basicInfo['routingData']->route);
        }
    }

    public function ajax_get_url_for_list()
    {
        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-library.php';
        require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-router.php';

        $frm = filter_input(INPUT_POST, 'frm');
        if ($frm) {
            $formData = json_decode($frm, true);
            $formData = Yachtino_Library::adjustArray($formData, ['stripslashes', 'strip_tags', 'trim',]);

            if (!empty($formData['itemtype'])) {
                $articleTypes = Yachtino::article_types();
                if (!in_array($formData['itemtype'], $articleTypes)) {
                    $formData['itemtype'] = '';
                }
            }

        } else {
            $formData = [];
        }

        if (empty($formData['lg'])) {
            $locale = determine_locale();
            $formData['lg'] = substr($locale, 0, 2);
        }
        if (empty($formData['itemtype'])) {
            $formData['itemtype'] = 'sboat';
        }

        $language = $formData['lg'];
        unset($formData['lg']);
        $itemType = $formData['itemtype'];
        unset($formData['itemtype']);

        // Get route.
        $classRouter = new Yachtino_Router();
        $arrTmp = $classRouter->get_url_for_list($language, $itemType, $formData);

        $arrayResult = [
            'redir' => '',
        ];
        if ($arrTmp['correctData']) {
            $arrayResult['redir'] = stripslashes($arrTmp['correctData']['path']);
            if ($arrTmp['allCriteria']) {
                $arrTmp['allCriteria'] = Yachtino_Api::filter_criteria($itemType, $arrTmp['allCriteria']);
            }
            if ($arrTmp['allCriteria']) {
                $arrayResult['redir'] .= '?' . Yachtino_Library::makePostToUrl($arrTmp['allCriteria']);
            }
        }

        // Make your array as json.
        wp_send_json($arrayResult);

        // Don't forget to stop execution afterward.
        wp_die();
    }

}
