<?php

declare(strict_types=1);

/**
 * Yachtino boat listing
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

if (!defined('ABSPATH')) {
    exit(); // Don't access directly
};

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-router.php';
require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-shiplisting.php';

class Yachtino_Activator
{
    private object $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Activates this plugin
     * @since 1.0.0
     */
    public function activate(): void
    {
        // first create all tables
        self::create_db_tables();

        // if the former plugin Shiplisting is installed -> remove it and save data in new tables
        $classShiplisting = new Yachtino_Shiplisting();
        $hadShiplisting = $classShiplisting->upgrade_from_shiplisting();
        if ($hadShiplisting) { // already installed
            return;
        }

        // add image URL to css and create one js file from parts
        self::update_assets();
    }

    public static function table_exists(string $tableName): bool
    {
        global $wpdb;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName)) === $tableName) {
            return true;
        } else {
            return false;
        }
    }

    // !! called also from Yachtino_Shiplisting in class-yachtino-shiplisting.php
    public static function create_db_tables(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();

        $settings = get_option('yachtino_settings');
        if ($settings === false) {
            $settings = [
                'apiKey'    => '',
                'apiSiteId' => null,
                'languages' => [],
                'version'   => YACHTINO_VERSION,
            ];
            add_option('yachtino_settings', $settings);

        } elseif ($settings['version'] != YACHTINO_VERSION) {
            self::update_plugin_version();
        }

        if (!self::table_exists($wpdb->prefix . 'yachtino_modules')) {
            $sql = 'CREATE TABLE `' . $wpdb->prefix . 'yachtino_modules` (
                `module_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL DEFAULT "",
                `itemType` ENUM("cboat","sboat","engine","trailer","mooring","gear") NULL DEFAULT NULL,
                `pageType` ENUM("list","detail") NULL DEFAULT NULL,
                `settings` TEXT NOT NULL COMMENT "number of hits and other params (json array)",
                `filter` TEXT NOT NULL COMMENT "for list page, eg. btid=1 if only sailboats should be shown (json array)",
                `searchForm` VARCHAR(255) NOT NULL DEFAULT "" COMMENT "which fields are shown in search form (json array)",
                `linkedDetailMaster` INT(10) UNSIGNED NULL DEFAULT NULL COMMENT "id of the detail page in yachtino_route_master",
                `linkedDetailUrl` TEXT NOT NULL COMMENT "possible: not master ID but a foreign URL with placeholder {itemId}",
                `added` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                `updated` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`module_id`),
                UNIQUE INDEX `' . $wpdb->prefix . 'yachtino_modules_name` (`name`)
            ) ' . $charsetCollate . ' COMMENT "boat list or boat detail view that can be embedded as shortcode";';
            dbDelta($sql);
        }
        if (!self::table_exists($wpdb->prefix . 'yachtino_route_master')) {
            $sql = 'CREATE TABLE `' . $wpdb->prefix . 'yachtino_route_master` (
                `master_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL DEFAULT "",
                `fk_module_id` INT(10) UNSIGNED NULL DEFAULT NULL COMMENT "ID from table yachtino_modules",
                `added` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                `updated` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`master_id`),
                UNIQUE INDEX `' . $wpdb->prefix . 'yachtino_master_name` (`name`),
                INDEX `' . $wpdb->prefix . 'yachtino_master_module` (`fk_module_id`)
            ) ' . $charsetCollate . ' COMMENT "master for pages that calls yachtino modules (content by API)";';
            dbDelta($sql);
        }
        if (!self::table_exists($wpdb->prefix . 'yachtino_routes')) {
            $sql = 'CREATE TABLE `' . $wpdb->prefix . 'yachtino_routes` (
                `route_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `fk_master_id` INT(10) UNSIGNED NOT NULL COMMENT "parent master ID",
                `path` VARCHAR(50) NOT NULL DEFAULT "",
                `language` VARCHAR(15) NULL DEFAULT NULL,
                `title` VARCHAR(255) NOT NULL DEFAULT "" COMMENT "meta tag title for HTML",
                `h1` VARCHAR(255) NOT NULL DEFAULT "" COMMENT "H1 tag in HTML",
                `description` TEXT NOT NULL COMMENT "meta tag description for HTML",
                `added` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                `updated` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`route_id`),
                INDEX `' . $wpdb->prefix . 'yachtino_routes_master` (`fk_master_id`)
            ) ' . $charsetCollate . ' COMMENT "pages in single languages that calls yachtino modules (content by API)";';
            dbDelta($sql);
        }
    }

    // creates output css and js (merge all files, remove comments)
    public static function update_assets(): void
    {
        // ########## css #####################

        $pathToShared = realpath(YACHTINO_DIR_PATH . '/assets/css/origin/yachtino-shared-raw.css');
        $contentShared = file_get_contents($pathToShared);

        // css for image slider
        $pathToGallery = realpath(YACHTINO_DIR_PATH . '/assets/css/origin/yachtino-gallery-raw.css');
        $contentGallery = file_get_contents($pathToGallery);

        // css for calendar (charter request)
        $pathToCalendar = realpath(YACHTINO_DIR_PATH . '/assets/css/origin/jquery-ui.css');
        $contentCalendar1 = file_get_contents($pathToCalendar);

        $pathToCalendar = realpath(YACHTINO_DIR_PATH . '/assets/css/origin/jquery-ui.structure.css');
        $contentCalendar2 = file_get_contents($pathToCalendar);

        $pathToCalendar = realpath(YACHTINO_DIR_PATH . '/assets/css/origin/jquery-ui.theme.css');
        $contentCalendar3 = file_get_contents($pathToCalendar);

        // css for public
        $pathToMain = realpath(YACHTINO_DIR_PATH . '/assets/css/origin/yachtino-public-raw.css');
        $content = $contentShared . "\n" . $contentGallery . "\n" . $contentCalendar1 . "\n" . $contentCalendar2 . "\n" . $contentCalendar3
            . "\n" . file_get_contents($pathToMain);

        // remove comments
        $content = self::minimalizeCss($content);

        $pathToMain = str_replace('origin' . DIRECTORY_SEPARATOR . 'yachtino-public-raw.css', 'yachtino-public.css', $pathToMain);
        file_put_contents($pathToMain, $content);

        // css for admin
        $pathToMain = realpath(YACHTINO_DIR_PATH . '/assets/css/origin/yachtino-admin-raw.css');
        $content = $contentShared . "\n" . file_get_contents($pathToMain);

        // remove comments
        $content = self::minimalizeCss($content);

        $pathToMain = str_replace('origin' . DIRECTORY_SEPARATOR . 'yachtino-admin-raw.css', 'yachtino-admin.css', $pathToMain);
        file_put_contents($pathToMain, $content);


        // ########## js #####################

        $pathToShared = realpath(YACHTINO_DIR_PATH . '/assets/js/origin/yachtino-shared-raw.js');
        $contentShared = file_get_contents($pathToShared);

        // js for image slider
        $pathToGallery = realpath(YACHTINO_DIR_PATH . '/assets/js/origin/yachtino-gallery-raw.js');
        $contentGallery = file_get_contents($pathToGallery);

        // js for calendar (charter request)
        $pathToCalendar = realpath(YACHTINO_DIR_PATH . '/assets/js/origin/calendar.js');
        $contentCalendar = file_get_contents($pathToCalendar);

        // js for calendar languages (charter request)
        $pathToCalendarLgs = realpath(YACHTINO_DIR_PATH . '/assets/js/origin/daterangepicker-all-lg.js');
        $contentCalendarLgs = file_get_contents($pathToCalendarLgs);

        // js for public
        $pathToMain = realpath(YACHTINO_DIR_PATH . '/assets/js/origin/yachtino-public-raw.js');
        $content = $contentShared . "\n" . $contentGallery . "\n" . $contentCalendar . "\n" . $contentCalendarLgs
            . "\n" . file_get_contents($pathToMain);

        // remove comments
        $content = self::remove_comments($content);

        $pathToMain = str_replace('origin' . DIRECTORY_SEPARATOR . 'yachtino-public-raw.js', 'yachtino-public.js', $pathToMain);
        file_put_contents($pathToMain, $content);

        // js for admin
        $pathToMain = realpath(YACHTINO_DIR_PATH . '/assets/js/origin/yachtino-admin-raw.js');
        $content = $contentShared . "\n" . file_get_contents($pathToMain);

        // remove comments
        $content = self::remove_comments($content);

        $pathToMain = str_replace('origin' . DIRECTORY_SEPARATOR . 'yachtino-admin-raw.js', 'yachtino-admin.js', $pathToMain);
        file_put_contents($pathToMain, $content);
    }

    public static function remove_comments(string $content): string
    {
        $content = preg_replace('/\/\/ [^\n]+\n/ui', '', $content);
        $content = preg_replace('/\/\* [^\*]+\*\//ui', '', $content);
        $content = preg_replace('/\n +/ui', "\n", $content);
        $content = preg_replace('/\n{2,}/ui', "\n", $content);
        return $content;
    }

    public static function update_plugin_version(): void
    {
        $settings = get_option('yachtino_settings');

        // Do some special things when we update
        global $wpdb;

        // in version 1.3.0 new settings for module for detail view (special requests)
        if (version_compare($settings['version'], '1.3.0', '<')
        && version_compare(YACHTINO_VERSION, '1.3.0', '>=')) {
            $sql = 'SELECT `module_id`, `settings` FROM `' . $wpdb->prefix . 'yachtino_modules` '
                . 'WHERE `pageType` = "detail"';
            $results = $wpdb->get_results($sql);
            if ($results) {
                foreach ($results as $result) {
                    $setts = json_decode($result->settings, true);
                    if (!isset($setts['insuranceRequest'])) {
                        $setts['insuranceRequest'] = 0;
                    }
                    if (!isset($setts['financingRequest'])) {
                        $setts['financingRequest'] = 0;
                    }
                    if (!isset($setts['transportRequest'])) {
                        $setts['transportRequest'] = 0;
                    }

                    $dataDb = [
                        'settings' => json_encode($setts),
                    ];
                    $where = [
                        'module_id' => $result->module_id,
                    ];
                    $wpdb->update($wpdb->prefix . 'yachtino_modules', $dataDb, $where);
                }
            }
        }

        // new in version 1.4.0: possibility to define where the search form for boat is is (top / side)
        if (version_compare($settings['version'], '1.4.0', '<')
        && version_compare(YACHTINO_VERSION, '1.4.0', '>=')) {

            $sql = 'SELECT `module_id`, `settings`, `searchForm` FROM `' . $wpdb->prefix . 'yachtino_modules` '
                . 'WHERE `pageType` = "list"';
            $results = $wpdb->get_results($sql);
            if ($results) {
                foreach ($results as $result) {
                    $setts = json_decode($result->settings, true);
                    if (isset($setts['searchPlace'])) {
                        continue;
                    }
                    if ($result->searchForm != '[]') {
                        if ($setts['layout'] == 'list') {
                            $setts['searchPlace'] = 'left';
                        } else {
                            $setts['searchPlace'] = 'top';
                        }
                    } else {
                        $setts['searchPlace'] = '';
                    }

                    $dataDb = [
                        'settings' => json_encode($setts),
                    ];
                    $where = [
                        'module_id' => $result->module_id,
                    ];
                    $wpdb->update($wpdb->prefix . 'yachtino_modules', $dataDb, $where);
                }
            }
        }

        // change in version 1.4.3: db fields other length for VARCHAR
        if (version_compare($settings['version'], '1.4.3', '<')
        && version_compare(YACHTINO_VERSION, '1.4.3', '>=')) {
            self::update_database_fields();
        }

        $settings['version'] = YACHTINO_VERSION;
        update_option('yachtino_settings', $settings);
    }

    public static function update_database_fields(): void
    {
        global $wpdb;
        $newLength = 100;

        $tables = [
            $wpdb->prefix . 'yachtino_modules' => 'module_id',
            $wpdb->prefix . 'yachtino_route_master' => 'master_id',
        ];

        foreach ($tables as $table => $idName) {
            if (!self::table_exists($table)) {
                continue;
            }

            $sql = 'SELECT `' . $idName . '`, `name` FROM `' . $table . '`';
            $results = $wpdb->get_results($sql);
            if ($results) {
                foreach ($results as $result) {
                    $lng = mb_strlen($result->name);
                    if ($lng <= $newLength) {
                        continue;
                    }

                    $nameTmp = mb_substr($result->name, 0, ($newLength - 3));

                    for ($i = 1; $i <= 999; $i++) {
                        $nameNew = $nameTmp . (string)$i;
                        $sql = 'SELECT `name` FROM `' . $table . '` WHERE `name` = "' . $nameNew . '" LIMIT 1';
                        $res = $wpdb->get_results($sql);
                        if (empty($res[0])) {
                            break;
                        }
                    }

                    $dataDb = [
                        'name' => $nameNew,
                    ];
                    $where = [
                        $idName => $result->{$idName},
                    ];
                    $wpdb->update($table, $dataDb, $where);
                }
            }

            // length former 255, now 100
            $sql = 'ALTER TABLE `' . $table . '` CHANGE `name` `name` VARCHAR(' . (string)$newLength . ') NOT NULL DEFAULT "";';
            // dbDelta($sql); - does not work
            $wpdb->get_results($sql);
        }
    }

    /**
     * Minimalizes code for css - removes to many white spaces, breaks...
     */
    public static function minimalizeCss(string $text): string
    {
        // lines
        $text = str_ireplace("\r\n", "", $text);
        $text = str_ireplace("\n", "", $text);

        // comments
        $text = str_replace('/*', 'α', $text);
        $text = str_replace('*/', 'ω', $text);
        $text = preg_replace('/α[^ω]*?ω/i', '', $text);
        $text = str_replace('α', '/*', $text);
        $text = str_replace('ω', '*/', $text);

        // white spaces
        $text = str_replace("	", " ", $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = str_replace("\n ", "\n", $text);
        $text = str_replace(', ', ',', $text);
        $text = str_replace('; ', ';', $text);
        $text = str_replace(': ', ':', $text);
        $text = str_replace(') ', ')', $text);
        $text = str_replace(' )', ')', $text);
        $text = str_replace('( ', '(', $text);
        $text = str_replace(' (', '(', $text);
        $text = str_replace('{ ', '{', $text);
        $text = str_replace(' {', '{', $text);
        $text = str_replace('} ', '}', $text);
        $text = str_replace(' }', '}', $text);
        $text = str_replace('> ', '>', $text);
        $text = str_replace(' >', '>', $text);
        $text = str_replace(';}', '}', $text);

        $text = str_replace(' and(', ' and (', $text);
        $text = str_replace(')-', ') -', $text);

        // content
        $text = preg_replace_callback('/( |\:)(yellow|black|white)([^a-z])/', self::class . '::_callbackCssColor', $text);
        $text = str_replace('weight:bold', 'weight:700', $text);

        // numbers 0.5 => .5
        $text = preg_replace('/( |\:)(0\.)([0-9])/', '$1.$3', $text);
        $text = preg_replace('/( |\:)0(px|em)/', '$10', $text);
        $text = str_replace(' 0em', ' 0', $text);

        // add rows
        // $text = str_replace('}', "}\n", $text);
        $text = str_replace('*/', "*/\n", $text);

        $text = trim($text);
        return $text;
    }

    private static function _callbackCssColor(array $result): string
    {
        switch ($result[2]) {
            case 'yellow':
                $color = '#ff0';
                break;
            case 'white':
                $color = '#fff';
                break;
            case 'black':
                $color = '#000';
                break;
            default:
                $color = $result[2];
                break;
        }
        return $result[1] . $color . $result[3];
    }

}