<?php

declare(strict_types=1);

/**
 * Yachtino boat listing
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

/*
 * Upgrades the Yachtino plugin from old Shiplisting plugin (in year 2023, yachtino version 1.0.0).
 */

// Don't access directly
if (!defined('ABSPATH')) {
    exit();
};

class Yachtino_Shiplisting
{
    private object $wpdb;
    private array $detailViews;
    private string $trailingSlash;
    private string $thisTime;

    // old => new placeholder name
    private array $placeholdersToReplace = [
        'boat_model'    => 'model',
        'boat_name'     => 'name',
        'year_built'    => 'yearBuilt',
        'boat_type'     => 'boatType',
        'boat_category' => 'category',
        'loa'           => 'length',
    ];

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /*
     * Former WordPress plugin's name was Shiplisting, it had another structure.
     * If installing Yachtino plugin, remove Shiplisting but keep data like API key.
     * Returns true if the new tables has been installed and updated, false = shiplisting was not used, installs new yachtino.
     */
    public function upgrade_from_shiplisting(): bool
    {
        // Shiplisting is not installed.
        $tableName = 'wp_shiplisting_settings';
        if ($this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $tableName)) !== $tableName) {
            return false;
        }

        $showContactForm = 1;

        $sql = 'SELECT * FROM wp_shiplisting_settings';
        $result = $this->wpdb->get_results($sql);

        // Shiplisting was not used.
        if (!$result) {

            // Try WP options.
            $apiKey = get_option('shiplisting_api_key');

            if (!$apiKey) {
                $this->remove_shiplisting();
                return false;
            }
            $apiSiteId = (int)get_option('shiplisting_api_siteid');

        } else {
            $apiKey = $result[0]->api_key;
            $apiSiteId = (int)$result[0]->api_site_id;
        }

        $settings = [
            'apiKey'    => $apiKey,
            'apiSiteId' => $apiSiteId,
            'languages' => [],
            'version'   => YACHTINO_VERSION,
        ];
        $showContactForm = (int)$result[0]->contact_form;
        $this->thisTime = date('Y-m-d H:i:s');

        // Get set languages.
        $sql = 'SELECT `language` FROM wp_shiplisting_routes GROUP BY `language`';
        $result = $this->wpdb->get_results($sql);
        if ($result) {
            $lgsArr = [];
            foreach ($result as $res) {
                $lgsArr[$res->language] = true;
            }
            if ($lgsArr) {
                ksort($lgsArr);
            }
            foreach ($lgsArr as $lg => $true) {
                $settings['languages'][] = $lg;
            }
        }
        update_option('yachtino_settings', $settings);

        // Define whether the urls should be with trailing slash.
        $permalink = get_option('permalink_structure');
        if (mb_substr($permalink, -1, 1) == '/') {
            $this->trailingSlash = '/';
        } else {
            $this->trailingSlash = '';
        }

        // Create boat detail views.
        $this->shiplisting_detail($showContactForm);

        // Create boat lists.
        $this->shiplisting_list();

        $this->remove_shiplisting();
        return true;
    }

    /**
     * Create master route and single language routes for boat detail view.
     */
    private function shiplisting_detail(int $showContactForm): void
    {
        $this->detailViews = [];

        $sql = 'SELECT * FROM wp_shiplisting_routes WHERE `callback` = "display_boat_details" ORDER BY id DESC';
        $results = $this->wpdb->get_results($sql);

        if (!$results) {
            return;
        }

        $setMasters = [];
        $setModules = [];
        $masterNames = [];
        $settsTmp = [
            'showContactForm'  => $showContactForm,
            'insuranceRequest' => 0,
            'financingRequest' => 0,
            'transportRequest' => 0,
        ];
        $settings = json_encode($settsTmp);
        $emptyArray = json_encode([]);
        foreach ($results as $result) {
            $vars = json_decode($result->vars, false);

            foreach ($vars as $field) {
                if (isset($field->source)) {
                    if (!$field->source) {
                        $itemType  = 'sboat';
                        $addToName = 'saleboat';
                    } else {
                        $itemType  = 'cboat';
                        $addToName = 'charter';
                    }
                }
            }

            // Module.
            if (!isset($setModules[$itemType])) {
                $dataDb = [
                    'name'             => $addToName . '_detail_view',
                    'itemType'         => $itemType,
                    'pageType'         => 'detail',
                    'settings'         => $settings,
                    'filter'           => $emptyArray,
                    'searchForm'       => $emptyArray,
                    'linkedDetailMaster' => null,
                    'linkedDetailUrl'  => '',
                    'added'            => $this->thisTime,
                    'updated'          => $this->thisTime,
                ];
                $this->wpdb->insert($this->wpdb->prefix . 'yachtino_modules', $dataDb);
                $setModules[$itemType] = $this->wpdb->insert_id;
            }

            // Masters.
            $name = 'page_' . $addToName . '_detail_view';
            if (isset($setMasters[$name][$result->language])) {
                for ($i = 1; $i <= 99999; $i++) {
                    $nameTmp = $name . $i;
                    if (!isset($setMasters[$nameTmp][$result->language])) {
                        $name = $nameTmp;
                        break;
                    }
                }
            }

            // Create master.
            if (!isset($masterNames[$name])) {
                $dataDb = [
                    'name'         => $name,
                    'fk_module_id' => $setModules[$itemType],
                    'added'        => $this->thisTime,
                    'updated'      => $this->thisTime,
                ];
                $this->wpdb->insert($this->wpdb->prefix . 'yachtino_route_master', $dataDb);
                $masterNames[$name] = $this->wpdb->insert_id;
            }

            $setMasters[$name][$result->language] = true;

            // Insert route.
            $this->insert_route((int)$masterNames[$name], $result);

            // Set for boat list -> old route ID mapping to new master ID.
            $this->detailViews[$result->id] = $masterNames[$name];
        }
    }

    /**
     * Create master route and single language routes for boat lists.
     */
    private function shiplisting_list(): void
    {
        $sql = 'SELECT * FROM wp_shiplisting_routes WHERE `callback` = "display_boats" ORDER BY id DESC';
        $results = $this->wpdb->get_results($sql);

        if (!$results) {
            return;
        }

        $setMasters  = [];
        $setModules  = [];
        $moduleNames = [];
        foreach ($results as $result) {

            // No detail view for this list.
            if (empty($this->detailViews[$result->linked_detail_view])) {
                continue;
            }

            $vars = json_decode($result->vars, false);

            $filterOrig = '';
            $hitsPerPage = 15;
            foreach ($vars as $field) {
                if (isset($field->source)) {
                    if (!$field->source) {
                        $itemType  = 'sboat';
                        $addToName = 'saleboat';
                    } else {
                        $itemType  = 'cboat';
                        $addToName = 'charter';
                    }

                } elseif (isset($field->filter)) {
                    $filterOrig = $field->filter;

                } elseif (isset($field->hitsByPage)) {
                    $hitsPerPage = (int)$field->hitsByPage;
                }
            }

            $filterName = '';
            $filterArr = [];
            if ($filterOrig) {
                $sql = 'SELECT name, fields_to_filter FROM wp_shiplisting_filter WHERE id = ' . $filterOrig;
                $res = $this->wpdb->get_results($sql);
                if ($res) {
                    $filterTmp = json_decode($res[0]->fields_to_filter, true);
                    foreach ($filterTmp as $field) {
                        foreach ($field as $key => $value) {
                            $filterArr[$key] = $value;
                        }
                    }
                    $filterName = str_replace('_filter', '', $res[0]->name);
                }
            }
            $filter = json_encode($filterArr);
            $settings = [
                'hitsPerPage' => $hitsPerPage,
                'layout'      => 'list',
                'columns'     => 0,
                'showPaging'  => 1,
                'searchPlace' => '',
            ];

            // Remove fields from search form that are in filters.
            if ($result->adv_filter) {
                $searchForm = json_decode($result->adv_filter, true);
                foreach ($searchForm as $key => $search) {
                    if (isset($filterArr[$search])) {
                        unset($searchForm[$key]);
                    }
                }
            } else {
                $searchForm = [];
            }
            if ($searchForm) {
                $settings['searchPlace'] = 'left';
            }
            $advFilter = json_encode($searchForm);

            // Module + master.
            if (!isset($setModules[$itemType][$filter][$advFilter])) {
                $name = $addToName . '_list_';
                if (!$filterArr) {
                    $name .= 'all';
                } else {
                    $name .= $filterName;
                }
                if ($searchForm) {
                    $name .= '_search';
                }

                if (isset($moduleNames[$name])) {
                    for ($i = 1; $i <= 99999; $i++) {
                        $nameTmp = $name . $i;
                        if (!isset($moduleNames[$nameTmp])) {
                            $name = $nameTmp;
                            break;
                        }
                    }
                }
                $moduleNames[$name] = true;

                $dataDb = [
                    'name'             => $name,
                    'itemType'         => $itemType,
                    'pageType'         => 'list',
                    'settings'         => json_encode($settings),
                    'filter'           => $filter,
                    'searchForm'       => $advFilter,
                    'linkedDetailMaster' => $this->detailViews[$result->linked_detail_view],
                    'added'            => $this->thisTime,
                    'updated'          => $this->thisTime,
                ];
                $this->wpdb->insert($this->wpdb->prefix . 'yachtino_modules', $dataDb);
                $setModules[$itemType][$filter][$result->adv_filter] = $this->wpdb->insert_id;

                $dataDb = [
                    'name'             => 'page_' . $name,
                    'fk_module_id'     => $setModules[$itemType][$filter][$result->adv_filter],
                    'added'            => $this->thisTime,
                    'updated'          => $this->thisTime,
                ];
                $this->wpdb->insert($this->wpdb->prefix . 'yachtino_route_master', $dataDb);
                $setMasters[$itemType][$filter][$result->adv_filter] = (int)$this->wpdb->insert_id;
            }

            $this->insert_route($setMasters[$itemType][$filter][$result->adv_filter], $result);
        }
    }

    private function insert_route(int $masterId, object $result): void
    {
        $path = str_replace('^', '', $result->path);
        $path = str_replace('$', '', $path);
        $path = str_replace('/', '\/', $path);
        $path = str_replace('-', '\-', $path);
        $path = str_replace('(.*)', '([a-z0-9]+)', $path);
        $path = str_replace('(.*?)', '([a-z0-9]+)', $path);

        // Add or remove trailing slash - analogic to settings for permalinks in WP options.
        if (strpos($path, '?') === false) {
            $lastLetter = mb_substr($path, -1, 1);
            if ($lastLetter == '/' && $this->trailingSlash != '/') {
                $path = mb_substr($path, 0, -2); // + backslash
            } elseif ($lastLetter != '/' && $this->trailingSlash == '/') {
                $path .= '\/';
            }
        }

        if (mb_substr($path, 0, 2) != '\/') {
            $path = '\/' . $path;
        }

        $title = $result->title;
        foreach ($this->placeholdersToReplace as $nameOld => $nameNew) {
            $title = str_replace('{' . $nameOld . '}', '{' . $nameNew . '}', $title);
        }

        $dataDb = [
            'fk_master_id' => $masterId,
            'path'         => $path,
            'language'     => $result->language,
            'title'        => $title,
            'h1'           => $title,
            'description'  => '',
            'added'        => $result->added,
            'updated'      => $this->thisTime,
        ];
        $this->wpdb->insert($this->wpdb->prefix . 'yachtino_routes', $dataDb);
    }

    /**
     * Removes shiplisting files (PHP) from hard disc and deletes tables from DB.
     */
    private function remove_shiplisting(): void
    {
        deactivate_plugins('shiplisting/shiplisting.php', false);

        delete_option('shiplisting_api_key');
        delete_option('shiplisting_api_siteid');
        delete_option('shiplisting_version');

        $this->wpdb->query('DROP TABLE IF EXISTS wp_shiplisting_caching');
        $this->wpdb->query('DROP TABLE IF EXISTS wp_shiplisting_filter');
        $this->wpdb->query('DROP TABLE IF EXISTS wp_shiplisting_routes');
        $this->wpdb->query('DROP TABLE IF EXISTS wp_shiplisting_settings');

        // Delete PHP files/code for Shiplisting from hard disc.
        $pathToShiplisting = YACHTINO_DIR_PATH . '/../shiplisting';
        if (is_dir($pathToShiplisting)) {
            require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-library.php';
            Yachtino_Library::truncateDirectory($pathToShiplisting, true);
        }
    }

}