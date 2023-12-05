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

class Yachtino_Admin
{
    private object $wpdb;

    private string $placeholderItemId = '([a-z0-9]+)';
    private string $trailingSlash = '';
    private string $adminLg = '';
    private array $sentData;
    private array $oldData;
    private array $articleTypes;

    /**
     * Singleton pattern.
     */
    private static ?self $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function get_instance(): self
    {
        global $wpdb;

        if (is_null(self::$instance)) {
            self::$instance = new self();
            self::$instance->wpdb = $wpdb;

            $permalink = get_option('permalink_structure');
            if (mb_substr($permalink, -1, 1) == '/') {
                self::$instance->trailingSlash = '/';
            } else {
                self::$instance->trailingSlash = '';
            }

            $locale = determine_locale();
            self::$instance->adminLg = substr($locale, 0, 2);
            $allowedLgs = Yachtino_Api::allowed_languages();
            if (!isset($allowedLgs[self::$instance->adminLg])) {
                self::$instance->adminLg = 'en';
            }

            self::$instance->articleTypes = Yachtino::article_types();
        }
        return self::$instance;
    }

    /**
     * When init WP -> check whether user has rights for calling pages.
     */
    public function check_access(): void
    {
        /* These two lines compiles css and javascript files for productive use.
        !! Activate this lines ONLY in development mode!!! */
        // require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-activator.php';
        // Yachtino_Activator::update_assets();

        $requestUri = YACHTINO_REQUEST_URI;

        // If editing pages or modules -> basic configuration must be done.
        if (strpos($requestUri, 'admin.php?page=yachtino-edit-module') !== false
        || strpos($requestUri, 'admin.php?page=yachtino-edit-page') !== false) {

            $pluginSettings = Yachtino::get_plugin_settings();
            if (!$pluginSettings->apiKey) {
                $targetUrl = self::create_url('yachtino-admin', 'first_settings');
                header('Location: ' . $targetUrl, true, 301);
                exit();
            }
        }

        // If editing a page -> at least one module must be already created.
        if (strpos($requestUri, 'admin.php?page=yachtino-edit-page') !== false) {

            $sql = 'SELECT `module_id` FROM ' . $this->wpdb->prefix . 'yachtino_modules';
            $results = $this->wpdb->get_results($sql);
            if (!$results) {
                $targetUrl = self::create_url('yachtino-edit-module', 'first_module');
                header('Location: ' . $targetUrl, true, 301);
                exit();
            }
        }
    }

    /**
     * Registers the stylesheets for the admin page of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles(): void
    {
        wp_enqueue_style(
            'yachtino-admin',
            plugins_url('/assets/css/yachtino-admin.css', YACHTINO_DIR_PATH . '/yachtino-boat-listing.php'),
            [],
            YACHTINO_VERSION,
            'all',
        );
    }

    /**
     * Registers the JavaScript for the admin page of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts(): void
    {
        wp_enqueue_script(
            'yachtino-admin',
            plugins_url('/assets/js/yachtino-admin.js', YACHTINO_DIR_PATH . '/yachtino-boat-listing.php'),
            ['jquery'],
            YACHTINO_VERSION,
            false,
        );
       wp_localize_script('yachtino-admin', 'yachtino_admin', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    public function adminmenu(): void
    {
        add_menu_page(
            __('Yo_plugin_name', 'yachtino-boat-listing'),
            'Yachtino',
            'manage_options',
            'yachtino-admin',
            [$this, 'page_configuration'],
            'dashicons-superhero-alt',
        );

        add_submenu_page(
            'yachtino-admin',
            __('Modules', 'yachtino-boat-listing') . ' - ' . __('Yo_plugin_name', 'yachtino-boat-listing'),
            __('Modules', 'yachtino-boat-listing'),
            'manage_options',
            'yachtino-admin-modules',
            [$this, 'page_modules'],
        );

        // This submenu point will not be displayed.
        add_submenu_page(
            '',
            __('Edit_module', 'yachtino-boat-listing') . ' - ' . __('Yo_plugin_name', 'yachtino-boat-listing'),
            __('Add_module', 'yachtino-boat-listing'),
            'manage_options',
            'yachtino-edit-module',
            [$this, 'page_edit_module'],
        );

        // It is not possible to add variables to the admin URL, so we have to pack the function into another function.
        add_submenu_page(
            'yachtino-admin',
            __('Add_module', 'yachtino-boat-listing') . ' - ' . __('Yo_plugin_name', 'yachtino-boat-listing'),
            __('Add_module', 'yachtino-boat-listing'),
            'manage_options',
            'yachtino-add-module',
            [$this, 'page_add_module'],
        );

        add_submenu_page(
            'yachtino-admin',
            __('Pages', 'yachtino-boat-listing') . ' - ' . __('Yo_plugin_name', 'yachtino-boat-listing'),
            __('Pages', 'yachtino-boat-listing'),
            'manage_options',
            'yachtino-admin-pages',
            [$this, 'page_pages'],
        );

        add_submenu_page(
            'yachtino-admin',
            __('Edit_page', 'yachtino-boat-listing') . ' - ' . __('Yo_plugin_name', 'yachtino-boat-listing'),
            __('Add_page', 'yachtino-boat-listing'),
            'manage_options',
            'yachtino-edit-page',
            [$this, 'page_edit_page'],
        );
    }

// ############### editing main settings #######################################

    /**
     * Basic configuration of the plugin - API key, API site ID, languages...
     */
    public function page_configuration(): void
    {
        self::basic_checks();

        $data = [];

        // Form sent -> update.
        $tplData = [
            'errors' => [],
        ];
        if (filter_input(INPUT_POST, 'isSent')) {

            $this->define_sent_data();

            $apiKey = filter_input(INPUT_POST, 'apikey');
            $apiSiteId = filter_input(INPUT_POST, 'apisiteid');

            if ($apiKey && preg_match('/^[a-z0-9_]+$/', $apiKey)) {
                $this->sentData['apikey'] = $apiKey;
            }
            if ($apiSiteId) {
                $this->sentData['apisiteid'] = (int)$apiSiteId;
            }

            if (!empty($_POST['lgs']) && is_array($_POST['lgs'])) {
                foreach ($_POST['lgs'] as $lg => $one) {
                    if (preg_match('/^[a-z]{2}$/', $lg)) {
                        $this->sentData['lgs'][$lg] = true;
                    }
                }
            }

            // Check required fields and plausibility.
            $required = [
                'apikey'    => 'API Key',
                'apisiteid' => 'Site ID',
                'lgs'       => __('Website_is_in_languages', 'yachtino-boat-listing'),
            ];

            foreach ($required as $fieldKey => $fieldName) {
                if (empty($this->sentData[$fieldKey])) {
                    $tplData['errors']['errMsg'][] = sprintf(
                        __('err_field_required_XXX', 'yachtino-boat-listing'),
                        '<b>"' . $fieldName . '"</b>'
                    );
                    $tplData['errors']['errField'][$fieldKey] = true;
                }
            }

            // Save sent data.
            if (!$tplData['errors']) {
                $langs = [];
                ksort($this->sentData['lgs']);
                foreach ($this->sentData['lgs'] as $lg => $true) {
                    $langs[] = $lg;
                }

                $oldPluginSettings = get_option('yachtino_settings');

                $yoSettings = [
                    'apiKey'    => $this->sentData['apikey'],
                    'apiSiteId' => (int)$this->sentData['apisiteid'],
                    'languages' => $langs,
                ];
                if (isset($oldPluginSettings['version'])) {
                    $yoSettings['version'] = $oldPluginSettings['version'];
                }
                update_option('yachtino_settings', $yoSettings);

                /* At this place, redirecting by PHP is not possible, headers were already sent,
                redirect by javascript in template. */
                $redirectUrl = self::create_url('yachtino-admin', 'data_saved');
                include YACHTINO_DIR_PATH . '/templates/incl-redirect.html';
                exit();

            // If errors -> show sent data in the page again.
            } else {
                $data = $this->sentData;
            }
        }

        // Get saved data if no errors.
        if (!$data) {
            $setts = get_option('yachtino_settings');
            $data['apikey']    = $setts['apiKey'];
            $data['apisiteid'] = $setts['apiSiteId'];
            $data['lgs'] = [];
            foreach ($setts['languages'] as $lg) {
                $data['lgs'][$lg] = 1;
            }
        }

        // Get languages from API - for template.
        $languages = $this->get_languages_from_api();

        $tplData = self::add_tpl_data($tplData);

        $link = 'https://www.boatadmin.com/' . $this->adminLg. '/data-export-user/credentials-api';
        $tplData['textTop'] = sprintf(__('Yo_settings_explain', 'yachtino-boat-listing'),
            '<a href="' . $link . '" target="_blank">' . $link . '</a>',
            'feeds@yachtino.com',);

        $tplData['h1'] = __('Yo_plugin_name', 'yachtino-boat-listing');
        $templateCoreName = 'incl-edit-configuration.html';
        include YACHTINO_DIR_PATH . '/templates/admin/page-edit-template.html';
    }

// ############### editing module ##############################################

    public function page_modules(): void
    {
        self::basic_checks();

        $sql = 'SELECT * FROM ' . $this->wpdb->prefix . 'yachtino_modules ORDER BY `name` ASC';
        $results = $this->wpdb->get_results($sql);
        $allData = [];

        $basicLinkEdit = '/wp-admin/admin.php?page=yachtino-edit-module';
        $counter = 0;
        if ($results) {
            foreach ($results as $result) {
                $filter = '';
                if ($result->filter) {
                    $filters = json_decode($result->filter, true);
                    foreach ($filters as $key => $val) {
                        if (!is_array($val)) {
                            $filter .= (string)$key . '=' . (string)$val . ', ';
                            continue;
                        }

                        $counterKey = 0;
                        foreach ($val as $key2 => $val2) {
                            if ($counterKey == $key2) {
                                $filter .= (string)$key . '[]=' . (string)$val2 . ', ';
                            } else {
                                $filter .= (string)$key . '[' . (string)$key2 . ']=' . (string)$val2 . ', ';
                            }
                            $counterKey++;
                        }
                    }
                    if ($filter) {
                        $filter = mb_substr($filter, 0, -2);
                    }
                }

                $pageLayout = '';
                if ($result->pageType == 'list' && $result->settings) {
                    $settings = json_decode($result->settings, true);
                    $pageLayout = __('page_layout_' . $settings['layout'], 'yachtino-boat-listing');
                }

                $allData[$counter] = [
                    'moduleId'   => $result->module_id,
                    'moduleName' => $result->name,
                    'itemType'   => __($result->itemType, 'yachtino-boat-listing'),
                    'pageType'   => __('page_' . $result->pageType, 'yachtino-boat-listing'),
                    'pageLayout' => $pageLayout,
                    'filter'     => $filter,
                    'linkEdit'   => $basicLinkEdit . '&moduleid=' . $result->module_id,
                    'linkRemove' => $basicLinkEdit . '&action=delete&moduleid=' . $result->module_id,
                ];
                $counter++;
            }
        }

        $addOptions = $this->add_options_module();

        $tplData = self::create_message([]);
        $tplData['textTop'] = sprintf(__('Module_explain', 'yachtino-boat-listing'));
        $tplData['textTop'] .= '<br><br>' . __('Wp_embedding_shortcode', 'yachtino-boat-listing')
            . ' (lg = ' . __('language', 'yachtino-boat-listing');
        if ($this->adminLg != 'en') {
            $tplData['textTop'] .= ' / language';
        }
        $tplData['textTop'] .= ')<br><br>'
            . __('List_of_boats_offers', 'yachtino-boat-listing') . ':<br>'
            . '<b>[yachtino_module lg="' . $this->adminLg . '" name="your_module_name" criteria="q=bavaria&btid=1"]</b><br>'
            . __('Wp_optional_attributes_list', 'yachtino-boat-listing') . '<br><br>'
            . __('One_boat_offer', 'yachtino-boat-listing') . ':<br>'
            . '<b>[yachtino_module lg="' . $this->adminLg . '" name="your_module_name" itemid="boat_id_or_trailer_id"]</b>';

        $tplData['formActionDelete'] = self::create_url('yachtino-edit-module', '', ['action' => 'delete']);
        $tplData['formActionAdd']    = self::create_url('yachtino-edit-module');

        // Coming from menu "Add module" -> open the editing form with javascript after the page is loaded.
        if (!empty($_GET['msg']) && $_GET['msg'] == 'first_step') {
            $tplData['openAddForm'] = true;
        }

        include YACHTINO_DIR_PATH . '/templates/admin/page-list-modules.html';
    }

    /**
     * It is not possible to add variables to the admin menu URL,
     * so we have to pack the function into another function.
     */
    public function page_add_module(): void
    {
        $_GET['msg'] = 'first_step';
        $this->page_modules();
    }

    public function page_edit_module(): void
    {
        self::basic_checks();

        $data = [];
        $tplData = [];

        if (filter_input(INPUT_POST, 'moduleid')) {
            $moduleId = (int)filter_input(INPUT_POST, 'moduleid');
        } elseif (filter_input(INPUT_GET, 'moduleid')) {
            $moduleId = (int)filter_input(INPUT_GET, 'moduleid');
        } else {
            $moduleId = null;
        }

        // Get saved data.
        if ($moduleId) {

            $sql = 'SELECT * FROM ' . $this->wpdb->prefix . 'yachtino_modules WHERE `module_id` = ' . $moduleId;
            $results = $this->wpdb->get_results($sql);

            // check whether this master ID exists
            if (!$results) {
                wp_die('This page does not exist.');
            }

            $this->oldData = [
                'moduleId'    => $results[0]->module_id,
                'name'        => $results[0]->name,
                'itemtype'    => $results[0]->itemType,
                'pagetype'    => $results[0]->pageType,
                'filter'      => $results[0]->filter,
                'searchForm'  => $results[0]->searchForm,
                'linkedm'     => $results[0]->linkedDetailMaster,
                'linkedu'     => [],
                'linked'      => 'm',
                'settings'    => $results[0]->settings,
            ];
            if ($results[0]->linkedDetailUrl) {
                $this->oldData['linkedu'] = json_decode($results[0]->linkedDetailUrl, true);
                $this->oldData['linked'] = 'u';
            }
            if ($results[0]->settings) {
                $settings = json_decode($results[0]->settings, true);

                if (!empty($settings['showH1'])) {
                    $this->oldData['showH1'] = 1;
                } else {
                    $this->oldData['showH1'] = 0;
                }

                $this->oldData['langs'] = [];
                if (!empty($settings['headline'])) {
                    foreach ($settings['headline'] as $lg => $head) {
                        $this->oldData['langs'][$lg]['h1'] = $head;
                    }
                }
                if ($results[0]->pageType == 'list') {
                    if (!isset($settings['layout'])) {
                        $settings['layout'] = 'list';
                    }

                    $this->oldData['bnr']      = $settings['hitsPerPage'];
                    $this->oldData['paging']   = $settings['showPaging'];
                    $this->oldData['layout']   = $settings['layout'];
                    $this->oldData['columns']  = $settings['columns'];
                    $this->oldData['sform']    = $settings['searchPlace'];
                    if (isset($settings['showHits'])) {
                        $this->oldData['showhits'] = $settings['showHits'];
                    }

                } else {
                    $this->oldData['contact'] = $settings['showContactForm'];
                    if (!empty($settings['insuranceRequest'])) {
                        $this->oldData['insurance'] = $settings['insuranceRequest'];
                    }
                    if (!empty($settings['financingRequest'])) {
                        $this->oldData['financing'] = $settings['financingRequest'];
                    }
                    if (!empty($settings['transportRequest'])) {
                        $this->oldData['transport'] = $settings['transportRequest'];
                    }
                }
            }
            if ($results[0]->pageType == 'list') {
                $search = json_decode($results[0]->searchForm, true);
                foreach ($search as $fieldName) {
                    $this->oldData['sf_' . $fieldName] = 1;
                }

                $moduleFilters = json_decode($results[0]->filter, true);
                $possibleFilters = self::get_filters($results[0]->itemType);
                foreach ($moduleFilters as $key => $value) {
                    if (!is_array($value) && in_array($key, $possibleFilters)) {
                        $this->oldData['fi_' . $key] = $value;
                        $this->oldData['fltr'] = 1;
                        unset($moduleFilters[$key]);
                    }
                }

                // Custom filters.
                $others = [];
                foreach ($moduleFilters as $key => $val) {
                    if (!is_array($val)) {
                        $others[] = $key . '=' . $val;
                        continue;
                    }

                    $counterKey = 0;
                    foreach ($val as $key2 => $val2) {
                        if ($counterKey == $key2) {
                            $others[] = (string)$key . '[]=' . (string)$val2;
                        } else {
                            $others[] = (string)$key . '[' . (string)$key2 . ']=' . (string)$val2;
                        }
                        $counterKey++;
                    }
                }
                if ($others) {
                    $this->oldData['fi_custom'] = implode('&', $others);
                    $this->oldData['fltr'] = 1;
                }
            }

        } else {
            $this->oldData = [];

            $itemTypeRaw = filter_input(INPUT_POST, 'itemtype');
            $pageTypeRaw = filter_input(INPUT_POST, 'pagetype');

            if ($itemTypeRaw && in_array($itemTypeRaw, $this->articleTypes)) {
                $itemType = $itemTypeRaw;
            }
            if ($pageTypeRaw && ($pageTypeRaw == 'detail' || $pageTypeRaw == 'list')) {
                $pageType = $pageTypeRaw;
            }

            // For adding a module item type and page type must be given (javascript form sent).
            if (empty($itemType) || empty($pageType)) {
                /* At this place, redirecting by PHP is not possible, headers were already sent,
                redirect by javascript in template */

                // Coming from menu.
                if (filter_input(INPUT_POST, 'firstStep')) {
                    $redirectUrl = self::create_url('yachtino-admin-modules', 'first_step');
                } else {
                    $redirectUrl = self::create_url('yachtino-admin-modules', 'add_module_wrong');
                }
                include YACHTINO_DIR_PATH . '/templates/incl-redirect.html';
                exit();
            }
        }

        // Delete.
        $act = filter_input(INPUT_GET, 'action');
        if ($act && $act == 'delete') {
            if (!$moduleId) {
                wp_die('This page does not exist.');
            }

            // check whether the module can be deleted
            $sql = 'SELECT `master_id`, `name` FROM ' . $this->wpdb->prefix . 'yachtino_route_master '
                . 'WHERE `fk_module_id` = ' . $moduleId;
            $results = $this->wpdb->get_results($sql);
            if ($results) {
                $pages = '';
                foreach ($results as $result) {
                    $pages .= $result->name . ', ';
                }
                $tplData['errors']['errMsg'][] = __('err_wp_module_delete_not_possible_page', 'yachtino-boat-listing')
                    . '<br>(' . __('Page', 'yachtino-boat-listing') . ': ' . mb_substr($pages, 0, -2) . ')';
            }

            // Delete really.
            if (empty($tplData['errors'])) {
                $where = [
                    'module_id' => $moduleId,
                ];
                $this->wpdb->delete($this->wpdb->prefix . 'yachtino_modules', $where);

                /* At this place, redirecting by PHP is not possible, headers were already sent,
                redirect by javascript in template */
                $redirectUrl = self::create_url('yachtino-admin-modules', 'data_saved');
                include YACHTINO_DIR_PATH . '/templates/incl-redirect.html';
                exit();
            }

        // Form sent -> check and save data.
        } elseif (filter_input(INPUT_POST, 'isSent')) {

            $this->adjust_sent_module();

            // Check data.
            $tplData['errors'] = $this->check_form_module($moduleId);

            // Save sent data.
            if (!$tplData['errors']) {
                $moduleId = $this->save_form_module($moduleId);

                /* At this place, redirecting by PHP is not possible, headers were already sent,
                redirect by javascript in template */
                $redirectUrl = self::create_url('yachtino-admin-modules', 'data_saved');
                include YACHTINO_DIR_PATH . '/templates/incl-redirect.html';
                exit();

            } else {
                $data = $this->sentData;
            }
        }

        if (!$data) {
            $data = $this->oldData;
        }

        if (empty($data['itemtype'])) {
            $data['itemtype'] = $itemType;
        }
        if (empty($data['pagetype'])) {
            $data['pagetype'] = $pageType;
        }
        $data['itemTypeWord'] = __($data['itemtype'], 'yachtino-boat-listing');
        $data['pageTypeWord'] = __('page_' . $data['pagetype'], 'yachtino-boat-listing');

        // Get possible options for selectboxes.
        $editOptions = $this->edit_options_module($data['pagetype'], $data['itemtype']);

        $tplData = self::add_tpl_data($tplData);
        $tplData['textTop'] = sprintf(__('Module_explain', 'yachtino-boat-listing'));

        $tplData['linkAbort'] = self::create_url('yachtino-admin-modules', '');

        if ($this->oldData) {
            $tplData['h1'] = __('Edit_module', 'yachtino-boat-listing');
        } else {
            $tplData['h1'] = __('Add_module', 'yachtino-boat-listing');
        }
        $tplData['h1'] .= ' - ' . __('Yo_plugin_name', 'yachtino-boat-listing');

        $templateCoreName = 'incl-edit-module.html';
        include YACHTINO_DIR_PATH . '/templates/admin/page-edit-template.html';
    }

    private function adjust_sent_module(): void
    {
        $this->define_sent_data();

        if (!empty($this->sentData['itemtype']) && in_array($this->sentData['itemtype'], $this->articleTypes)) {
            $this->sentData['itemtype'] = $this->sentData['itemtype'];
        } else {
            $this->sentData['itemtype'] = '';
        }
        if (!empty($this->sentData['pagetype'])
        && ($this->sentData['pagetype'] == 'detail' || $this->sentData['pagetype'] == 'list')) {
            $this->sentData['pagetype'] = $this->sentData['pagetype'];
        } else {
            $this->sentData['pagetype'] = '';
        }

        if (!$this->sentData['itemtype'] || !$this->sentData['pagetype']) {
            wp_die('This page does not exist.');
        }
        if (!empty($this->sentData['showH1'])) {
            $this->sentData['showH1'] = 1;
        } else {
            $this->sentData['showH1'] = 0;
        }

        // Allowed languages for this plugin.
        $pluginSettings = Yachtino::get_plugin_settings();

        // The name of the field is langs[de][h1]
        // = the same structure like title and description in pages = using the same js for placeholders.
        $this->sentData['headline'] = [];
        foreach ($pluginSettings->languages as $lg) {
            if (!empty($this->sentData['langs'][$lg]['h1'])) {
                $this->sentData['headline'][$lg] = $this->sentData['langs'][$lg]['h1'];
            } else {
                $this->sentData['headline'][$lg] = '';
            }
        }

        if ($this->sentData['pagetype'] == 'list') {

            if (!empty($this->sentData['bnr'])) {
                $this->sentData['bnr'] = (int)$this->sentData['bnr'];
            }
            if (!empty($this->sentData['showhits'])) {
                $this->sentData['showhits'] = 1;
            } else {
                $this->sentData['showhits'] = 0;
            }

            // If a plugin page is set for linked detail view -> remove own URLs.
            if (empty($this->sentData['linked']) || $this->sentData['linked'] == 'm') {
                $this->sentData['linkedu'] = [];
                if (!empty($this->sentData['linkedm'])) {
                    $this->sentData['linkedm'] = (int)$this->sentData['linkedm'];
                } else {
                    $this->sentData['linkedm'] = null;
                }
    
            } elseif ($this->sentData['linked'] == 'u') {
                $this->sentData['linkedm'] = null;
    
                // Possible languages -> delete array elements that are manipulated.
                if (!empty($this->sentData['linkedu'])) {
                    foreach ($this->sentData['linkedu'] as $lg => $lgUrl) {
                        if (!in_array($lg, $pluginSettings->languages)) {
                            unset($this->sentData['linkedu'][$lg]);
                        }
                    }
                } else {
                    $this->sentData['linkedu'] = [];
                }
            }

            // Link to own page.
            $this->sentData['linkeduDb'] = '';
            if ($this->sentData['linkedu']) {
                $thisHost = get_option('siteurl');
                $linkedU = [];

                // Possible languages.
                foreach ($this->sentData['linkedu'] as $lg => $lgUrl) {
                    if (!$lgUrl) {
                        continue;
                    }

                    if (stripos($lgUrl, $thisHost) !== false) {
                        $linkOwn = str_ireplace($thisHost, '', $lgUrl);
                        $isHere = true;
                    } else {
                        $linkOwn = $lgUrl;
                        if (stripos($linkOwn, 'http') === 0) {
                            $isHere = false;
                        } else {
                            $isHere = true;
                        }
                    }

                    // Correct trailing slash for this host.
                    if ($isHere) {
                        $p = explode('?', $linkOwn);
                        if ($this->trailingSlash && mb_substr($p[0], -1, 1) != '/') {
                            $p[0] .= '/';
                        } elseif (!$this->trailingSlash && mb_substr($p[0], -1, 1) == '/') {
                            $p[0] = mb_substr($p[0], 0, -1);
                        }
                        $linkOwn = $p[0];
                        if (!empty($p[1])) {
                            $linkOwn .= '?' . $p[1];
                        }
                        if (mb_substr($linkOwn, 0, 1) != '/') {
                            $linkOwn = '/' . $linkOwn;
                        }
                    }
                    $linkedU[$lg] = $linkOwn;
                }
                if ($linkedU) {
                    $this->sentData['linkeduDb'] = json_encode($linkedU);
                }
            }

            if (!empty($this->sentData['layout'])
            && ($this->sentData['layout'] == 'list' || $this->sentData['layout'] == 'tiles')) {
                $this->sentData['layout'] = $this->sentData['layout'];
            } else {
                $this->sentData['layout'] = '';
            }

            // Make number of columns (grid) for boat list as integer.
            if ($this->sentData['layout'] == 'tiles') {
                if (!empty($this->sentData['columns']) && $this->sentData['columns'] == '3') {
                    $this->sentData['columns'] = 3;
                } else {
                    $this->sentData['columns'] = 4;
                }

            } else {
                $this->sentData['columns'] = 0;
            }

            if (empty($this->sentData['sform'])
            || ($this->sentData['sform'] != 'top' && $this->sentData['sform'] != 'left')) {
                $this->sentData['sform'] = '';
            }

            // Search form should not be shown.
            if (!$this->sentData['sform']) {
                foreach ($this->sentData as $key => $value) {
                    if (strpos($key, 'sf_') === 0) {
                        unset($this->sentData[$key]);
                    }
                }

            // Search form selected but no field for search selected.
            } else {
                $hasSearch = false;
                foreach ($this->sentData as $key => $value) {
                    if (strpos($key, 'sf_') === 0) {
                        $hasSearch = true;
                        break;
                    }
                }
                if (!$hasSearch) {
                    $this->sentData['sform'] = '';
                }
            }
            if (empty($this->sentData['fltr'])) {
                foreach ($this->sentData as $key => $value) {
                    if (strpos($key, 'fi_') === 0) {
                        unset($this->sentData[$key]);
                    }
                }
            }
        }
    }

    private function check_form_module(?int $moduleId): array
    {
        $errors = [];

        // Check required fields and plausibility.
        $required = [
            'name' => __('Module_name', 'yachtino-boat-listing'),
        ];
        if ($this->sentData['pagetype'] == 'list') {
            $required['bnr'] = __('Ads_per_page', 'yachtino-boat-listing');
        }

        foreach ($required as $fieldKey => $fieldName) {
            if (empty($this->sentData[$fieldKey])) {
                $errors['errMsg'][] = sprintf(
                    __('err_field_required_XXX', 'yachtino-boat-listing'),
                    '<b>"' . $fieldName . '"</b>'
                );
                $errors['errField'][$fieldKey] = true;
            }
        }

        if ($this->sentData['pagetype'] == 'list') {
            if (empty($this->sentData['layout'])) {
                $errors['errMsg'][] = sprintf(
                    __('err_field_required_XXX', 'yachtino-boat-listing'),
                    '<b>"' . __('Page_layout', 'yachtino-boat-listing') . '"</b>'
                );
                $errors['errField']['layout'] = true;
                $errors['errField']['layout_list'] = true;
                $errors['errField']['layout_tiles'] = true;
            }

            // Linked detail view not filled.
            if (empty($this->sentData['linked'])
            || ($this->sentData['linked'] == 'm' && empty($this->sentData['linkedm']))
            || ($this->sentData['linked'] == 'u' && empty($this->sentData['linkeduDb']))
            ) {
                $errors['errMsg'][] = sprintf(
                    __('err_field_required_XXX', 'yachtino-boat-listing'),
                    '<b>"' . __('Linked_detail_view', 'yachtino-boat-listing') . '"</b>'
                );
                $errors['errField']['linkedm'] = true;
                
                $pluginSettings = Yachtino::get_plugin_settings();
                foreach ($pluginSettings->languages as $lg) {
                    $errors['errField']['linkedu'][$lg] = true;
                }
            }

            // Own link must contain placeholder for item ID.
            if ($this->sentData['linked'] == 'u' && empty($errors['errField']['linkedu'])) {
                foreach ($this->sentData['linkedu'] as $lg => $lgUrl) {
                    if ($lgUrl && !str_contains($lgUrl, '{itemId}')) {
                        $errors['errMsg'][] = strtoupper($lg) . ': ' . __('err_wp_link_item_id', 'yachtino-boat-listing');
                        $errors['errField']['linkedu'][$lg] = true;
                    }
                }
            }
        }

        // Name must be unique.
        if (empty($errors['errField']['name'])) {
            if (!preg_match('/^[a-z0-9_]+$/ui', $this->sentData['name'])) {
                if ($this->adminLg == 'de') {
                    $msg = 'Das Feld <b>"Name"</b> kann nur Ziffern (0-9), lateinische Buchstaben (ohne Sonderzeichen) '
                        . 'und Unterstrich (_) enthalten.';
                } else {
                    $msg = 'The <b>"Name"</b> field can only contain digits (0-9), Latin letters (without special characters) '
                        . 'and underscore (_).';
                }
                $errors['errMsg'][] = $msg;
                $errors['errField']['name'] = true;

            } else {
                $sql = 'SELECT `module_id` FROM ' . $this->wpdb->prefix . 'yachtino_modules WHERE `name` = %s';
                if ($moduleId) {
                    $sql .= ' AND `module_id` != ' . $moduleId;
                }
                $sql = $this->wpdb->prepare($sql, [$this->sentData['name']]);
                $results = $this->wpdb->get_results($sql);
                if ($results) {
                    $errors['errMsg'][] = __('err_name_taken', 'yachtino-boat-listing');
                    $errors['errField']['name'] = true;
                }
            }
        }

        // Field "name" can be max. 100 characters.
        if (empty($errors['errField']['name']) && mb_strlen($this->sentData['name']) > 100) {
            $errors['errMsg'][] = '<b>' . __('Module_name', 'yachtino-boat-listing') . '</b> '
                . sprintf(__('err_text_too_long', 'yachtino-boat-listing'), '100');
            $errors['errField']['name'] = true;
        }

        // Filters for boat list.
        if ($this->sentData['pagetype'] == 'list') {
            if (!empty($this->sentData['fi_custom'])) {
                $wrong = false;
                $p = explode('&', $this->sentData['fi_custom']);
                foreach ($p as $part) {
                    $p2 = explode('=', $part);
                    if (empty($p2[1])) {
                        $wrong = true;
                        break;
                    }

                    // if it is an array (not associative)
                    $p2[0] = str_replace('[]', '', $p2[0]);

                    if (preg_match('/[^-a-z0-9_]/i', $p2[0]) || preg_match('/[^-a-z0-9\%,\._]/i', $p2[1])) {
                        $wrong = true;
                    }
                }

                if ($wrong) {
                    if ($this->adminLg == 'de') {
                        $msg = 'Benutzerdefinierter Filter muss eine Zuordnung key - value sein. '
                            . 'Mehrere Parameter müssen mit "&" getrennt sein. '
                            . 'Der ganze Filter muss als URL formattiert sein, '
                            . 'also keine Leerzeichen oder Sonderzeichen.';
                    } else {
                        $msg = 'Custom filter must be a key - value mapping. Multiple parameters must be separated with '
                            . '"&". The whole filter must be formatted as a URL, i.e. no spaces or special characters.';
                    }
                    $errors['errMsg'][] = $msg;
                    $errors['errField']['fi_custom'] = true;
                }
            }
        }

        return $errors;
    }

    private function save_form_module(?int $moduleId): int
    {
        $time = date('Y-m-d H:i:s');

        // Save main table (yachtino_route_master).
        $dataDb = [];
        if (!$this->oldData || $this->oldData['name'] != $this->sentData['name']) {
            $dataDb['name'] = $this->sentData['name'];
        }
        if (!$this->oldData || $this->oldData['itemtype'] != $this->sentData['itemtype']) {
            $dataDb['itemType'] = $this->sentData['itemtype'];
        }
        if (!$this->oldData || $this->oldData['pagetype'] != $this->sentData['pagetype']) {
            $dataDb['pageType'] = $this->sentData['pagetype'];
        }

        if ($this->sentData['pagetype'] == 'list') {

            $settings = [
                'hitsPerPage' => $this->sentData['bnr'],
                'layout'      => 'list',
                'columns'     => 0,
                'showPaging'  => 0,
                'showHits'    => 0,
                'searchPlace' => '',
                'showH1'      => 0,
                'headline'    => [],
            ];
            if (!empty($this->sentData['layout'])) {
                $settings['layout'] = $this->sentData['layout'];
            }
            if (!empty($this->sentData['columns'])) {
                $settings['columns'] = $this->sentData['columns'];
            }
            if (!empty($this->sentData['paging'])) {
                $settings['showPaging'] = 1;
            }
            if (!empty($this->sentData['sform'])) {
                $settings['searchPlace'] = $this->sentData['sform'];
            }
            if (!empty($this->sentData['showhits'])) {
                $settings['showHits'] = $this->sentData['showhits'];
            }

        } else {
            $settings = [
                'showContactForm'  => 0,
                'insuranceRequest' => 0,
                'financingRequest' => 0,
                'transportRequest' => 0,
                'showH1'           => 0,
                'headline'         => [],
            ];
            if (!empty($this->sentData['contact'])) {
                $settings['showContactForm'] = 1;
            }

            if ($this->sentData['itemtype'] == 'sboat') {
                if (!empty($this->sentData['insurance'])) {
                    $settings['insuranceRequest'] = 1;
                }
                if (!empty($this->sentData['financing'])) {
                    $settings['financingRequest'] = 1;
                }
                if (!empty($this->sentData['transport'])) {
                    $settings['transportRequest'] = 1;
                }
            }
        }
        $settings['showH1']   = $this->sentData['showH1'];
        $settings['headline'] = $this->sentData['headline'];

        $setts = json_encode($settings);
        if (!$this->oldData || $this->oldData['settings'] != $setts) {
            $dataDb['settings'] = $setts;
        }

        if ($this->sentData['pagetype'] == 'list') {
            $filters = [];
            $searchForm = [];
            foreach ($this->sentData as $key => $value) {

                // cabins can be 0
                if ($value === '') {
                    continue;
                }

                if ($key == 'fi_custom') {
                    $filters = Yachtino_Library::explodeSearchString($value);
                    continue;
                }

                $begin = substr($key, 0, 3);
                if ($begin == 'fi_') {
                    $name = substr($key, 3);
                    $filters[$name] = $value;

                } elseif ($begin == 'sf_') {
                    $searchForm[] = substr($key, 3);
                }
            }

            $filter = json_encode($filters);
            $sForm  = json_encode($searchForm);

            if (!$this->oldData || $this->oldData['linkedm'] != $this->sentData['linkedm']) {
                $dataDb['linkedDetailMaster'] = $this->sentData['linkedm'];
            }
            if (!$this->oldData || $this->oldData['linkedu'] != $this->sentData['linkeduDb']) {
                $dataDb['linkedDetailUrl'] = $this->sentData['linkeduDb'];
            }

        } else {
            $filter = json_encode([]);
            $sForm = json_encode([]);
        }
        if (!$this->oldData || $this->oldData['filter'] != $filter) {
            $dataDb['filter'] = $filter;
        }
        if (!$this->oldData || $this->oldData['searchForm'] != $sForm) {
            $dataDb['searchForm'] = $sForm;
        }

        if ($dataDb) {
            if ($moduleId) {
                $dataDb['updated'] = $time;
                $where = [
                    'module_id' => $moduleId,
                ];
                $this->wpdb->update($this->wpdb->prefix . 'yachtino_modules', $dataDb, $where);

            } else {
                $dataDb['added']   = $time;
                $dataDb['updated'] = $time;
                $this->wpdb->insert($this->wpdb->prefix . 'yachtino_modules', $dataDb);
                $moduleId = $this->wpdb->insert_id;
            }
        }

        return $moduleId;
    }

    private function add_options_module(): \StdClass
    {
        $addOptions = new StdClass();
        $addOptions->itemTypes = [];
        foreach ($this->articleTypes as $itemType) {
            $addOptions->itemTypes[] = [
                'id'   => $itemType,
                'name' => __($itemType, 'yachtino-boat-listing'),
            ];
        }

        // If no modules given -> show only detail page, that must be created first.
        $showAll = true;
        $sql = 'SELECT `module_id` FROM ' . $this->wpdb->prefix . 'yachtino_modules '
            . 'WHERE pageType = "detail" LIMIT 1';
        $results = $this->wpdb->get_results($sql);
        if (!$results) {
            $showAll = false;
        }

        $addOptions->pageTypes = [];
        $addOptions->pageTypes[] = [
            'id'   => 'detail',
            'name' => __('page_detail', 'yachtino-boat-listing'),
        ];

        if ($showAll) {
            $addOptions->pageTypes[] = [
                'id'   => 'list',
                'name' => __('page_list', 'yachtino-boat-listing'),
            ];
        }

        return $addOptions;
    }

    private function edit_options_module(string $pageType, string $itemType): \StdClass
    {
        $editOptions = new StdClass();

        // possible languages
        $editOptions->langs = self::get_plugin_language_options();

        // placeholders for H1 tag
        $editOptions->placeholders = self::get_placeholders();

        if ($pageType == 'list') {
            $editOptions->searchForm = self::get_search_form($itemType);

            // Which detail page should be linked to from list.
            $editOptions->detailPages = [];
            $sql = 'SELECT `ms`.`master_id`, `ms`.`name` FROM ' . $this->wpdb->prefix . 'yachtino_route_master AS ms '
                . 'INNER JOIN ' . $this->wpdb->prefix . 'yachtino_modules AS md ON `ms`.`fk_module_id` = `md`.`module_id`'
                . 'WHERE `md`.`pageType` = "detail" AND `md`.`itemType` = "' . $itemType . '"';
            $results = $this->wpdb->get_results($sql);
            if ($results) {
                foreach ($results as $result) {
                    $editOptions->detailPages[] = [
                        'id'   => $result->master_id,
                        'name' => $result->name,
                    ];
                }
            }

            // Get filters from API.
            $fields = '';
            $filters = self::get_filters($itemType);
            foreach ($filters as $filter) {
                $fields .= $filter . ',';
            }
            $fields = mb_substr($fields, 0, -1);

            $urlParts = [
                'language' => $this->adminLg,
                'part1'    => 'article-data',
                'part2'    => 'search-form',
            ];
            $additionalVars = [
                'itemtype' => $itemType,
            ];
            $additionalVars['fields'] = $fields;

            $classApi = Yachtino_Api::get_instance();
            $apiResponse = $classApi->send_request_to_api($urlParts, $additionalVars);
            $editOptions->filters = $apiResponse->selects;

            // Search form at the top or on the side.
            $editOptions->searchPlace = [
                0 => [
                    'id'   => '',
                    'name' => __('no_search_form', 'yachtino-boat-listing'),
                ],
                1 => [
                    'id'   => 'top',
                    'name' => __('search_form_at_top', 'yachtino-boat-listing'),
                ],
                2 => [
                    'id'   => 'left',
                    'name' => __('search_form_at_left', 'yachtino-boat-listing'),
                ],
            ];
        }

        return $editOptions;
    }

// ############### editing page ################################################

    /*
     * Shows generated pages
     */
    public function page_pages(): void
    {
        self::basic_checks();

        $sql = 'SELECT rm.master_id, rm.name AS routeName, md.module_id, md.name AS moduleName, md.itemType, md.pageType '
            . 'FROM ' . $this->wpdb->prefix . 'yachtino_route_master AS rm '
            . 'INNER JOIN ' . $this->wpdb->prefix . 'yachtino_modules AS md ON rm.fk_module_id = md.module_id';
        $results = $this->wpdb->get_results($sql);
        $allData = [];

        $basicLinkEdit = '/wp-admin/admin.php?page=yachtino-edit-page';
        $counter = 0;
        if ($results) {
            foreach ($results as $result) {
                $allData[$counter] = [
                    'masterId'   => $result->master_id,
                    'moduleId'   => $result->module_id,
                    'routeName'  => $result->routeName,
                    'moduleName' => $result->moduleName,
                    'itemType'   => __($result->itemType, 'yachtino-boat-listing'),
                    'pageType'   => __('page_' . $result->pageType, 'yachtino-boat-listing'),
                    'linkEdit'   => $basicLinkEdit . '&masterid=' . $result->master_id,
                    'linkRemove' => $basicLinkEdit . '&action=delete&masterid=' . $result->master_id,
                    'paths'      => '',
                ];

                // get URLs
                $sql = 'SELECT path, language FROM ' . $this->wpdb->prefix . 'yachtino_routes '
                    . 'WHERE fk_master_id = ' . $result->master_id;
                $resultsPath = $this->wpdb->get_results($sql);
                $paths = [];
                if ($resultsPath) {
                    foreach ($resultsPath as $resultPath) {
                        $path = stripslashes($resultPath->path);

                        $link = $path;

                        $path = mb_substr($path, 1);
                        if (mb_substr($path, -1, 1) == '/') {
                            $path = mb_substr($path, 0, -1);
                        }
                        if ($result->pageType == 'list') {
                            $paths[] = '<a href="' . $link . '" target="_blank">' . $path . ' (' . $resultPath->language . ')</a>';
                        } else {
                            $paths[] = $path . ' (' . $resultPath->language . ')';
                        }
                    }
                    if ($paths) {
                        $allData[$counter]['paths'] = implode(', ', $paths);
                    }
                }
                $counter++;
            }
        }

        $tplData = self::create_message([]);
        $tplData['formActionDelete'] = str_replace(
            'page=yachtino-admin-pages', 'page=yachtino-edit-page&action=delete', YACHTINO_REQUEST_URI);

        include YACHTINO_DIR_PATH . '/templates/admin/page-list-pages.html';
    }

    public function page_edit_page(): void
    {
        self::basic_checks();

        $data = [];
        $tplData = [];

        if (filter_input(INPUT_POST, 'masterid')) {
            $masterId = (int)filter_input(INPUT_POST, 'masterid');
        } elseif (filter_input(INPUT_GET, 'masterid')) {
            $masterId = (int)filter_input(INPUT_GET, 'masterid');
        } else {
            $masterId = null;
        }

        // get saved data
        if ($masterId) {

            $sql = 'SELECT * FROM ' . $this->wpdb->prefix . 'yachtino_route_master WHERE `master_id` = ' . $masterId;
            $results = $this->wpdb->get_results($sql);

            // check whether this master ID exists
            if (!$results) {
                wp_die('This page does not exist.');
            }

            $this->oldData = [
                'name'     => $results[0]->name,
                'moduleid' => $results[0]->fk_module_id,
                'langs'    => [],
            ];

            // get pages
            $sql = 'SELECT `route_id`, `path`, `language`, `title`, `description` '
                . 'FROM ' . $this->wpdb->prefix . 'yachtino_routes WHERE `fk_master_id` = ' . $masterId;
            $results = $this->wpdb->get_results($sql);
            if ($results) {
                foreach ($results as $route) {

                    $path = stripslashes($route->path);

                    // detail view
                    if (strpos($path, $this->placeholderItemId) !== false) {
                        $path = preg_replace('/\/?' . preg_quote($this->placeholderItemId, '/') . '\/?/', '', $path);
                        $path = mb_substr($path, 1);

                    // list
                    } else {
                        $path = mb_substr($path, 1, -1);
                    }
                    $this->oldData['langs'][$route->language] = [
                        'route_id' => $route->route_id,
                        'pathOrig' => $route->path,
                        'path'  => $path,
                        'title' => $route->title,
                        'descr' => $route->description,
                    ];
                }
            }

        } else {
            $this->oldData = [];
        }

        // delete
        if (!empty($_GET['action']) && $_GET['action'] == 'delete') {
            if (!$masterId) {
                wp_die('This page does not exist.');
            }

            // check whether the page can be deleted
            // detail view cannot be deleted if there is a module with boat list relying on it
            $sql = 'SELECT `module_id`, `name` FROM ' . $this->wpdb->prefix . 'yachtino_modules '
                . 'WHERE `linkedDetailMaster` = ' . $masterId;
            $results = $this->wpdb->get_results($sql);
            if ($results) {
                $pages = '';
                foreach ($results as $result) {
                    $pages .= $result->name . ', ';
                }
                $tplData['errors']['errMsg'][] = __('err_wp_page_delete_not_possible_module', 'yachtino-boat-listing')
                    . '<br>(' . __('Module', 'yachtino-boat-listing') . ': ' . mb_substr($pages, 0, -2) . ')';
            }

            if (empty($tplData['errors'])) {
                // single routes
                if (!empty($this->oldData['langs'])) {
                    foreach ($this->oldData['langs'] as $lg => $subData) {
                        $where = [
                            'route_id' => $subData['route_id'],
                        ];
                        $this->wpdb->delete($this->wpdb->prefix . 'yachtino_routes', $where);
                    }
                }

                $where = [
                    'master_id' => $masterId,
                ];
                $this->wpdb->delete($this->wpdb->prefix . 'yachtino_route_master', $where);

                // at this place, redirecting by PHP is not possible, headers were already sent
                // redirect by javascript in template
                $redirectUrl = self::create_url('yachtino-admin-pages', 'data_saved');
                include YACHTINO_DIR_PATH . '/templates/incl-redirect.html';
                exit();
            }

        // form sent -> check and save data
        } elseif (filter_input(INPUT_POST, 'isSent')) {

            // check data
            $tplData['errors'] = $this->check_form_page($masterId);

            // save sent data
            if (!$tplData['errors']) {
                $masterId = $this->save_form_page($masterId);

                // at this place, redirecting by PHP is not possible, headers were already sent
                // redirect by javascript in template
                $redirectUrl = self::create_url('yachtino-admin-pages', 'data_saved');
                include YACHTINO_DIR_PATH . '/templates/incl-redirect.html';
                exit();

            } else {
                $data = $this->sentData;
            }
        }

        // get data (if no errors and it is not adding new page)
        if (!$data && $masterId) {
            $data = $this->oldData;
        }

        // get possible options for selectboxes
        $editOptions = $this->edit_options_page();

        $siteUrl = get_option('siteurl');
        $siteUrl = preg_replace('/https?:\/\/(www\.|)/', '', $siteUrl);

        $tplData = self::add_tpl_data($tplData);

        // show text for multi language pages - the path to the page must contain the language (eg. /cs/lode for Czech)
        if (count($editOptions->langs) > 1) {
            $tplData['textTop'] = '<span style="color:#900">' . __('Wp_path_language', 'yachtino-boat-listing') . '</span>';
        }

        $tplData['linkAbort'] = self::create_url('yachtino-admin-pages', '');

        $tplData['h1'] = __('Edit_page', 'yachtino-boat-listing') . ' - ' . __('Yo_plugin_name', 'yachtino-boat-listing');
        $templateCoreName = 'incl-edit-page.html';
        include YACHTINO_DIR_PATH . '/templates/admin/page-edit-template.html';
    }

    private function check_form_page(?int $masterId): array
    {
        $errors = [];

        $this->define_sent_data();

        if (!empty($this->sentData['moduleid'])) {
            $this->sentData['moduleid'] = (int)$this->sentData['moduleid'];
        }
        foreach ($this->sentData['langs'] as $lg => $subData) {
            if (!preg_match('/^[a-z]{2}$/', $lg)) {
                unset($this->sentData['langs'][$lg]);
            }
            if (empty($subData['path'])) {
                $this->sentData['langs'][$lg]['path'] = '';
            }
            if (empty($subData['title'])) {
                $this->sentData['langs'][$lg]['title'] = '';
            }
            if (empty($subData['desc'])) {
                $this->sentData['langs'][$lg]['desc'] = '';
            }
        }

        // check required fields and plausibility
        $required = [
            'name'     => __('Internal_name', 'yachtino-boat-listing'),
            'moduleid' => __('Module', 'yachtino-boat-listing'),
        ];

        foreach ($required as $fieldKey => $fieldName) {
            if (empty($this->sentData[$fieldKey])) {
                $errors['errMsg'][] = sprintf(
                    __('err_field_required_XXX', 'yachtino-boat-listing'),
                    '<b>"' . $fieldName . '"</b>'
                );
                $errors['errField'][$fieldKey] = true;
            }
        }

        // name must contain only allowed characters and cannot be twice in the db
        if (empty($errors['errField']['name'])) {
            if (!preg_match('/^[a-z0-9_]+$/ui', $this->sentData['name'])) {
                if ($this->adminLg == 'de') {
                    $msg = 'Das Feld <b>"Name"</b> kann nur Ziffern (0-9), lateinische Buchstaben (ohne Sonderzeichen) '
                        . 'und Unterstrich (_) enthalten.';
                } else {
                    $msg = 'The <b>"Name"</b> field can only contain digits (0-9), Latin letters (without special characters) '
                        . 'and underscore (_).';
                }
                $errors['errMsg'][] = $msg;
                $errors['errField']['name'] = true;

            } else {
                $sql = 'SELECT `master_id` FROM ' . $this->wpdb->prefix . 'yachtino_route_master WHERE `name` = %s';
                if ($masterId) {
                    $sql .= ' AND `master_id` != ' . $masterId;
                }
                $sql = $this->wpdb->prepare($sql, [$this->sentData['name']]);
                $results = $this->wpdb->get_results($sql);

                if ($results) {
                    $errors['errMsg'][] = __('err_name_taken', 'yachtino-boat-listing');
                    $errors['errField']['name'] = true;
                }
            }
        }

        // field "name" can be max. 100 characters
        if (empty($errors['errField']['name']) && mb_strlen($this->sentData['name']) > 100) {
            $errors['errMsg'][] = '<b>' . __('Internal_name', 'yachtino-boat-listing') . '</b> '
                . sprintf(__('err_text_too_long', 'yachtino-boat-listing'), '100');
            $errors['errField']['name'] = true;
        }

        // at least one language is required
        // remove languages that are not filled
        foreach ($this->sentData['langs'] as $lg => $subData) {

            if (!$subData['path'] && !$subData['title'] && !$subData['descr']) {
                unset($this->sentData['langs'][$lg]);

            // check filled fields
            } elseif (empty($subData['path'])) {
                $errors['errMsg'][] = sprintf(
                    __('err_field_required_XXX', 'yachtino-boat-listing'),
                    '<b>"' . __('Path_slug', 'yachtino-boat-listing') . '"</b>'
                );
                $errors['errField']['langs'][$lg]['path'] = true;
            }
        }

        if (empty($this->sentData['langs'])) {
            $errors['errMsg'][] = __('err_wp_page_lg', 'yachtino-boat-listing');
            foreach ($_POST['langs'] as $lg => $subData) {
                if (preg_match('/^[a-z]{2}$/', $lg)) {
                    $errors['errField']['langs'][$lg]['path'] = true;
                }
            }
        }

        // get data from module
        $moduleData = null;
        if ($this->sentData['moduleid']) {
            $sql = 'SELECT itemType, pageType FROM ' . $this->wpdb->prefix . 'yachtino_modules WHERE module_id = ' . $this->sentData['moduleid'];
            $results = $this->wpdb->get_results($sql);
            if ($results) {
                $moduleData = $results[0];

            } else {
                $errors['errMsg'][] = __('err_wp_module_not_exists', 'yachtino-boat-listing');
                $errors['errField']['moduleid'] = true;
            }
        }

        // format path for inserting into db
        if ($moduleData) {

            // check whether path already in db
            foreach ($this->sentData['langs'] as $lg => $subData) {
                $pathData = $this->adjust_path_detail($subData['path'], $moduleData->pageType);

                // check whether path already in db
                $sql = 'SELECT `route_id` FROM ' . $this->wpdb->prefix . 'yachtino_routes AS `md` '
                    . 'WHERE path = "' . addslashes($pathData['pathDb']) . '"';
                if ($this->oldData && !empty($this->oldData['langs'][$lg]['route_id'])) {
                    $sql .= ' AND route_id != ' . $this->oldData['langs'][$lg]['route_id'];
                }

                $results = $this->wpdb->get_results($sql);
                if ($results) {
                    $errors['errMsg'][] = '<b>"' . $this->sentData['langs'][$lg]['path'] . '"</b> - '
                        . __('err_wp_path_already_in_db', 'yachtino-boat-listing');
                    $errors['errField']['langs'][$lg]['path'] = true;

                } else {
                    $this->sentData['langs'][$lg]['pathDb'] = $pathData['pathDb'];
                }
            }
        }

        return $errors;
    }

    private function save_form_page(?int $masterId): int
    {
        $time = date('Y-m-d H:i:s');

        // save main table (yachtino_route_master)
        $dataDb = [];
        if (!$this->oldData || $this->oldData['name'] != $this->sentData['name']) {
            $dataDb['name'] = $this->sentData['name'];
        }
        if (!$this->oldData || $this->oldData['moduleid'] != $this->sentData['moduleid']) {
            $dataDb['fk_module_id'] = $this->sentData['moduleid'];
        }

        if ($dataDb) {
            if ($masterId) {
                $dataDb['updated'] = $time;
                $where = [
                    'master_id' => $masterId,
                ];
                $this->wpdb->update($this->wpdb->prefix . 'yachtino_route_master', $dataDb, $where);

            } else {
                $dataDb['added']   = $time;
                $dataDb['updated'] = $time;
                $this->wpdb->insert($this->wpdb->prefix . 'yachtino_route_master', $dataDb);
                $masterId = $this->wpdb->insert_id;
            }
        }

        // save each language
        foreach ($this->sentData['langs'] as $lg => $subData) {
            if (isset($this->oldData['langs'][$lg])) {
                $oldSet = $this->oldData['langs'][$lg];
                unset($this->oldData['langs'][$lg]); // remaining languages will be deleted later
            } else {
                $oldSet = [];
            }

            $dataDb = [];
            if (!$oldSet || $oldSet['pathOrig'] != $subData['pathDb']) {
                $dataDb['path'] = $subData['pathDb'];
            }
            if (!$oldSet || $oldSet['title'] != $subData['title']) {
                $dataDb['title'] = $subData['title'];
            }
            if (!$oldSet || $oldSet['descr'] != $subData['descr']) {
                $dataDb['description'] = $subData['descr'];
            }

            if ($dataDb) {
                if ($oldSet) {
                    $dataDb['updated'] = $time;
                    $where = [
                        'route_id' => $oldSet['route_id'],
                    ];
                    $this->wpdb->update($this->wpdb->prefix . 'yachtino_routes', $dataDb, $where);

                } else {
                    $dataDb['fk_master_id'] = $masterId;
                    $dataDb['language']     = $lg;
                    $dataDb['added']        = $time;
                    $dataDb['updated']      = $time;
                    $this->wpdb->insert($this->wpdb->prefix . 'yachtino_routes', $dataDb);
                }
            }
        }

        // if some old sets are remaining -> delete
        if (!empty($this->oldData['langs'])) {
            foreach ($this->oldData['langs'] as $lg => $subData) {
                $where = [
                    'route_id' => $subData['route_id'],
                ];
                $this->wpdb->delete($this->wpdb->prefix . 'yachtino_routes', $where);
            }
        }

        return $masterId;
    }

    private function edit_options_page(): \StdClass
    {
        $editOptions = new StdClass();
        $editOptions->modules = [];
        $sql = 'SELECT `module_id`, `name`, `itemType`, `pageType` '
            . 'FROM ' . $this->wpdb->prefix . 'yachtino_modules '
            . 'ORDER BY name ASC';
        $results = $this->wpdb->get_results($sql);
        if ($results) {
            foreach ($results as $module) {
                if ($module->pageType == 'detail') {
                    $addToPath = '/' . substr($module->itemType, 0, 1) . '1001' . $this->trailingSlash;
                    if ($module->itemType == 'cboat') {
                        $placeholder = '{model} for rent';
                    } else {
                        $placeholder = '{model} for sale';
                    }
                } else {
                    $addToPath = '/';
                    if ($module->itemType == 'cboat') {
                        $placeholder = 'Boats for rent';
                    } elseif ($module->itemType == 'trailer') {
                        $placeholder = 'Boat trailers for sale';
                    } elseif ($module->itemType == 'engine') {
                        $placeholder = 'Boat engines for sale';
                    } elseif ($module->itemType == 'mooring') {
                        $placeholder = 'Boat moorings for sale';
                    } elseif ($module->itemType == 'gear') {
                        $placeholder = 'Marine gear for sale';
                    } else {
                        $placeholder = 'Boats for sale';
                    }
                }
                $editOptions->modules[] = [
                    'id'          => $module->module_id,
                    'name'        => $module->name,
                    'itemType'    => $module->itemType,
                    'addToPath'   => $addToPath,
                    'placeholder' => $placeholder,
                ];
            }
        }

        // possible languages
        $editOptions->langs = self::get_plugin_language_options();

        // placeholders for meta tags
        $editOptions->placeholders = self::get_placeholders();

        return $editOptions;
    }

// ############### help methods ################################################

    private static function basic_checks(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        if (!is_plugin_active('yachtino-boat-listing/yachtino-boat-listing.php')) {
            wp_die(__('You call an inactive plugin.'));
        }
    }

    private static function add_tpl_data(array $tplData): array
    {
        // define URL for form action
        $tplData['formAction'] = self::remove_msg_var();

        // message sent in the URL
        $tplData = self::create_message($tplData);
        return $tplData;
    }

    /**
     * If a message (variable msg) sent in the URL -> display this message as translated text
     */
    private static function create_message(array $tplData): array
    {
        if (empty($_GET['msg'])) {
            return $tplData;
        }

        // if no settings edited yet, redirect from other edit pages to configuration
        if ($_GET['msg'] == 'first_settings') {
            $tplData['errors']['errMsg'] = [
                0 => __('Msg_first_settings', 'yachtino-boat-listing'),
            ];

        // if no module edited yet, redirect from other edit pages to module editing
        } elseif ($_GET['msg'] == 'first_module') {
            $tplData['errors']['errMsg'] = [
                0 => __('Msg_first_module', 'yachtino-boat-listing'),
            ];

        } elseif ($_GET['msg'] == 'data_saved') {
            $tplData['errors']['okMsg'] = [
                0 => __('Msg_data_saved', 'yachtino-boat-listing'),
            ];

        // not all required fields filled for adding module
        } elseif ($_GET['msg'] == 'add_module_wrong') {
            $tplData['errors']['errMsg'] = [
                0 => sprintf(__('err_field_required_XXX', 'yachtino-boat-listing'),
                    '<b>"' . __('Offer') . '"</b>'),
                1 => sprintf(__('err_field_required_XXX', 'yachtino-boat-listing'),
                    '<b>"' . __('Page') . '"</b>'),
            ];
        }

        return $tplData;
    }

    private static function remove_msg_var(): string
    {
        $targetUrl = YACHTINO_REQUEST_URI;
        return preg_replace('/\&msg\=[a-z0-9_]+/', '', $targetUrl);
    }

    private static function create_url(string $newPage, string $message = '', array $otherVars = []): string
    {
        $thisUrl = admin_url() . 'admin.php?page=' . $newPage;
        if ($otherVars) {
            foreach ($otherVars as $key => $value) {
                $thisUrl .= '&' . $key . '=' . $value;
            }
        }
        if ($message) {
            $thisUrl .= '&msg=' . $message;
        }
        return $thisUrl;
    }

    private function get_languages_from_api(): array
    {
        $urlParts = [
            'language' => $this->adminLg,
            'part1'    => 'article-special',
            'part2'    => 'get-mixed',
        ];
        $additionalVars = [
            'lgs' => 1,
        ];
        $classApi = Yachtino_Api::get_instance();
        $json = $classApi->send_request_to_api($urlParts, $additionalVars);

        if (!empty($json->languages)) {
            return (array)$json->languages;

        } else {
            return [];
        }
    }

    public static function get_plugin_language_options(): array
    {
        $output = [];

        $pluginSettings = Yachtino::get_plugin_settings();
        foreach ($pluginSettings->languages as $lg) {
            $name = __('lg_website_' . $lg, 'yachtino-boat-listing');
            $name = mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
            $output[] = [
                'id'   => $lg,
                'name' => $name,
            ];
        }
        return $output;
    }

    private static function get_placeholders(): array
    {
        return [
            'model'       => [
                'name'   => __('Model', 'yachtino-boat-listing'),
                'itypes' => ['cboat', 'sboat', 'engine', 'trailer', 'mooring', 'gear'],
            ],
            'name'        => [
                'name'   => __('Boat_name', 'yachtino-boat-listing'),
                'itypes' => ['cboat', 'sboat',],
            ],
            'yearBuilt'    => [
                'name'   => __('Year_built', 'yachtino-boat-listing'),
                'itypes' => ['cboat', 'sboat', 'engine', 'trailer', 'gear'],
            ],
            'manufacturer' => [
                'name'   => __('Manufacturer', 'yachtino-boat-listing'),
                'itypes' => ['cboat', 'sboat', 'engine', 'trailer', ],
            ],
            'boatType'     => [
                'name'   => __('Boat_type', 'yachtino-boat-listing') . ' (' . __('boattype_1', 'yachtino-boat-listing') . ', ' . __('boattype_2', 'yachtino-boat-listing') . '...)',
                'itypes' => ['cboat', 'sboat',],
            ],
            'category'     => [
                'name'   => __('Category', 'yachtino-boat-listing') . ' (' . __('houseboat', 'yachtino-boat-listing') . ', ' . __('catamaran', 'yachtino-boat-listing') . '...)',
                'itypes' => ['cboat', 'sboat',],
            ],
            'newOrUsed'       => [
                'name'   => __('New_used', 'yachtino-boat-listing'),
                'itypes' => ['engine', 'trailer', 'gear'],
            ],
            'power'       => [
                'name'   => __('Engine_power', 'yachtino-boat-listing'),
                'itypes' => ['engine',],
            ],
            'length'       => [
                'name'   => __('Length', 'yachtino-boat-listing'),
                'itypes' => ['cboat', 'sboat', 'trailer', 'mooring', ],
            ],
            'beam'       => [
                'name'   => __('Beam', 'yachtino-boat-listing'),
                'itypes' => ['cboat', 'sboat',],
            ],
            'country'       => [
                'name'   => __('Country', 'yachtino-boat-listing'),
                'itypes' => ['cboat', 'sboat', 'engine', 'trailer', 'mooring', 'gear'],
            ],
        ];
    }

    private static function get_search_form(string $itemType): array
    {
        $search = [
            'q' => __('Quick_search', 'yachtino-boat-listing'),
        ];

        // for boats
        if ($itemType == 'cboat' || $itemType == 'sboat') {
            $search['btid'] = __('Boat_type', 'yachtino-boat-listing');

            if ($itemType == 'sboat') {
                $search['newused'] = __('New_used', 'yachtino-boat-listing');
            }
            $search['manfb'] = __('Manufacturer', 'yachtino-boat-listing');
            $search['lng']  = __('Length', 'yachtino-boat-listing');
            $search['yb']   = __('Year_built', 'yachtino-boat-listing');
            $search['cab']  = __('Cabins', 'yachtino-boat-listing');
            $search['pow']  = __('Engine_power', 'yachtino-boat-listing');
            if ($itemType == 'cboat') {
                $search['wkprc'] = __('Price_boat_week', 'yachtino-boat-listing');
                $search['daprc'] = __('Price_boat_day', 'yachtino-boat-listing');
                $search['hrprc'] = __('Price_boat_hour', 'yachtino-boat-listing');
                $search['beprc'] = __('Price_berth_week', 'yachtino-boat-listing');

            } else {
                $search['sprc']   = __('Sale_price', 'yachtino-boat-listing');
            }

            $search['fuel'] = __('Fuel', 'yachtino-boat-listing');
            $search['hmid'] = __('Hull_material', 'yachtino-boat-listing');
            $search['ct']   = __('Country', 'yachtino-boat-listing');

            if ($itemType == 'cboat') {
                $search['rgww']  = __('Charter_waterway', 'yachtino-boat-listing');
                $search['tn']    = __('Port', 'yachtino-boat-listing');
            } else {
                $search['provid'] = __('US_state', 'yachtino-boat-listing');
            }
            $search['fly'] = __('Flybridge', 'yachtino-boat-listing');

        } elseif ($itemType == 'engine') {
            $search['oclass'] = __('New_used', 'yachtino-boat-listing');
            $search['yb']     = __('Year_built', 'yachtino-boat-listing');
            $search['pow']    = __('Engine_power', 'yachtino-boat-listing');
            $search['sprc']   = __('Sale_price', 'yachtino-boat-listing');
            $search['ct']     = __('Country', 'yachtino-boat-listing');
            $search['provid'] = __('US_state', 'yachtino-boat-listing');
            $search['manfe']  = __('Brand', 'yachtino-boat-listing');
            $search['etype']  = __('Engine_type', 'yachtino-boat-listing');
            $search['fuel']   = __('Fuel', 'yachtino-boat-listing');

        } elseif ($itemType == 'trailer') {
            $search['oclass'] = __('New_used', 'yachtino-boat-listing');
            $search['manft']  = __('Brand', 'yachtino-boat-listing');
            $search['trmid']  = __('Material', 'yachtino-boat-listing');
            $search['ct']     = __('Country', 'yachtino-boat-listing');
            $search['provid'] = __('US_state', 'yachtino-boat-listing');
            $search['yb']     = __('Year_built', 'yachtino-boat-listing');
            $search['lng']    = __('Length', 'yachtino-boat-listing');
            $search['sprc']   = __('Sale_price', 'yachtino-boat-listing');

        } elseif ($itemType == 'mooring') {
            $search['mcid']   = __('Type_of_berth', 'yachtino-boat-listing');
            $search['ct']     = __('Country', 'yachtino-boat-listing');
            $search['provid'] = __('US_state', 'yachtino-boat-listing');
            $search['lng']    = __('Length', 'yachtino-boat-listing');
            $search['sprc']   = __('Sale_price', 'yachtino-boat-listing');
        }
        $search['orderby'] = __('Sort_by', 'yachtino-boat-listing');
        return $search;
    }

    private static function get_filters(string $itemType): array
    {
        // for boats
        if ($itemType == 'cboat' || $itemType == 'sboat') {
            $filters = ['btid'];
            if ($itemType == 'sboat') {
                $filters[] = 'newused';
            }
            $filters[] = 'manfb';
            $filters[] = 'lng';
            $filters[] = 'yb';
            $filters[] = 'cab';
            $filters[] = 'pow';

            if ($itemType == 'sboat') {
                $filters[] = 'sprc';
            } else {
                $filters[] = 'wkprc';
                $filters[] = 'daprc';
                $filters[] = 'hrprc';
                $filters[] = 'beprc';
            }
            $filters[] = 'fuel';
            $filters[] = 'hmid';
            $filters[] = 'ct';

            if ($itemType == 'cboat') {
                $filters[] = 'rgww';
            }

        } elseif ($itemType == 'engine') {
            $filters = ['oclass', 'yb', 'pow', 'sprc', 'ct', 'manfe', 'etype', 'fuel'];

        } elseif ($itemType == 'trailer') {
            $filters = ['oclass', 'manft', 'trmid', 'ct', 'yb', 'lng', 'sprc'];

        } elseif ($itemType == 'mooring') {
            $filters = ['mcid', 'ct', 'lng', 'sprc'];
        }

        $filters[] = 'orderby';

        return $filters;
    }

    private function define_sent_data(): void
    {
        $tmpData = [];
        foreach ($_POST as $key => $value) {
            if (!is_array($value)) {
                $tmpData[$key] = filter_input(INPUT_POST, $key);
                continue;
            }

            foreach ($value as $key2 => $value2) {
                if (!is_array($value2)) {
                    $tmpData[$key][$key2] = $value2;
                    continue;
                }

                foreach ($value2 as $key3 => $value3) {
                    $tmpData[$key][$key2][$key3] = $value3;
                }
            }
        }

        // adjust sent data - double "stripslashes": first for fu.. WP (magic_quotes compatibility) and second for real stripslashes
        $this->sentData = Yachtino_Library::adjustArray($tmpData, ['stripslashes', 'stripslashes', 'strip_tags', 'trim',]);
    }

    private function adjust_path_detail(string $origPath, string $pageType): array
    {
        $output = [
            'pathDb'   => '',
            'addQuery' => '',
        ];

        $p0 = explode('?', $origPath);
        $p = explode('/', $p0[0]);
        foreach ($p as $part) {
            if ($part) {
                $part = urldecode($part);
                $output['pathDb'] .= '/' . urlencode($part);
            }
        }
        $output['pathDb'] = preg_quote($output['pathDb'], '/');

        // Item ID (boat ID, trailer ID...) as part of the URL for detail view.
        if ($pageType == 'detail') {
            $output['pathDb'] .= '\/' . $this->placeholderItemId;
        }

        if ($this->trailingSlash) {
            $output['pathDb'] .= '\/';
        }

        if (!empty($p0[1])) {
            $output['addQuery'] = $p0[1];
        } else {
            $output['addQuery'] = '';
        }

        return $output;
    }

    /**
     * Help function for automatic extracting strings for translations.
     */
    public function poedit(): void
    {
        __('cboat');
        __('sboat');
        __('engine');
        __('trailer');
        __('mooring');
        __('gear');
        __('page_detail');
        __('page_list');

        __('lg_website_cs');
        __('lg_website_da');
        __('lg_website_de');
        __('lg_website_el');
        __('lg_website_en');
        __('lg_website_es');
        __('lg_website_fi');
        __('lg_website_fr');
        __('lg_website_hr');
        __('lg_website_hu');
        __('lg_website_it');
        __('lg_website_nl');
        __('lg_website_no');
        __('lg_website_pl');
        __('lg_website_pt');
        __('lg_website_ru');
        __('lg_website_sv');
        __('lg_website_tr');
    }

}