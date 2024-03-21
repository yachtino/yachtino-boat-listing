<?php

declare(strict_types=1);

/**
 * Yachtino boat listing
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

/*
 * Checks whether called URL is a URL of Yachtino page and sets content and meta tags
 */

if (!defined('ABSPATH')) {
    exit(); // Don't access directly
};

class Yachtino_Router
{
    const POST_TYPE = 'yachtino_page';
    const QUERY_VAR = 'Yachtino_Route';
    private static int $postId = 0; // The ID of the post this plugin uses
    private static string $routeId = 'yachtino-route';

    private StdClass $routingData;
    private object $wpdb;

    private ?string $thisUrl;
    private string $pageContent;
    private string $template = '';

    // URL path of called page
    private string $path = '';
    private array $rules = [];

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function run(): void
    {
        self::check_plugin_version();

        add_action('init', [$this, 'get_route'], 1000, 0);
    }

    public static function check_plugin_version(): void
    {
        $setts = Yachtino::get_plugin_settings();
        if (!$setts->version || version_compare($setts->version, YACHTINO_VERSION, '<')) {
            require_once YACHTINO_DIR_PATH . '/includes/basics/class-yachtino-activator.php';
            Yachtino_Activator::update_plugin_version();
            $redirectUrl = home_url() . YACHTINO_REQUEST_URI;
            require_once ABSPATH . 'wp-includes/pluggable.php';
            wp_redirect($redirectUrl);
        }
    }

    /**
     * Looks for given URL in yachtino db table
     * If found, set $this->routingData with all informations
     */
    public function get_route(): void
    {
        // $this->thisUrl = filter_input(INPUT_SERVER, 'REQUEST_URI');
        // if (!$this->thisUrl && !empty($_SERVER['REQUEST_URI'])) {
            // $this->thisUrl = $_SERVER['REQUEST_URI'];
        // }
        $this->thisUrl = YACHTINO_REQUEST_URI;

        // called from command line or other reasons why this variable is not set
        if ($this->thisUrl == '/') {
            return;
        }
        $uriParts = explode('?', $this->thisUrl);
        $requestUri = $uriParts[0];

        // if administration -> do not check public paths
        if (stripos($requestUri, '/wp-admin') === 0 || stripos($requestUri, '/wp-login') === 0) {
            return;
        }

        // remove trailing slash at the end
        if (substr($requestUri, -1, 1) == '/') {
            $requestUri = substr($requestUri, 0, -1);
        }

        $resultsRoutes = $this->wpdb->get_results('SELECT * FROM ' . $this->wpdb->prefix . 'yachtino_routes');
        foreach ($resultsRoutes as $route) {
            $pathWithoutEnd = $route->path;

            // remove trailing slash
            if (mb_substr($pathWithoutEnd, -1, 1) == '/') {
                $pathWithoutEnd = mb_substr($pathWithoutEnd, 0, -2); // plus backslash
            }

            if (!preg_match('/^' . $pathWithoutEnd . '\/?$/iu', $requestUri, $matches)) {
                continue;
            }

            // route found

            // set rules for rewriting
            // slash at the end has already be removed if any
            // must be without trailing slashes at the begin and at the end
            $this->path = mb_substr($pathWithoutEnd, 2);

            $rule = 'index.php?' . self::QUERY_VAR . '=' . self::$routeId;
            add_rewrite_rule($this->path, $rule, 'top');
            add_rewrite_tag('%' . self::POST_TYPE . '%', '([^&]+)');
            flush_rewrite_rules();

            // boat ID must be string
            if (!empty($matches[1])) {
                $itemId = $matches[1];
            } else {
                $itemId = '0';
            }

            // if URL without trailing slash called but route has a trailing slash ->redirect
            $lastLetterRoute = mb_substr($route->path, -1, 1);
            $lastLetterUri   = mb_substr($uriParts[0], -1, 1);
            $redirectUrl = '';
            if ($lastLetterRoute == '/' && $lastLetterUri != '/') {
                $redirectUrl = $uriParts[0] . '/';
                if (!empty($uriParts[1])) {
                    $redirectUrl .= '?' . $uriParts[1];
                }
            } elseif ($lastLetterRoute != '/' && $lastLetterUri == '/') {
                $redirectUrl = mb_substr($uriParts[0], 0, -1);
                if (!empty($uriParts[1])) {
                    $redirectUrl .= '?' . $uriParts[1];
                }
            }
            if ($redirectUrl) {
                header('Location: ' . $redirectUrl, true, 301);
                exit();
            }

            $sql = 'SELECT `md`.`module_id`, `md`.`itemType`, `md`.`pageType`, `md`.`settings`, '
                    . '`md`.`filter`, `md`.`searchForm`, '
                    . '`md`.`linkedDetailMaster`, `md`.`linkedDetailUrl`, `ms`.`master_id` '
                . 'FROM `' . $this->wpdb->prefix . 'yachtino_modules` AS `md` '
                . 'INNER JOIN `' . $this->wpdb->prefix . 'yachtino_route_master` AS `ms` '
                . 'ON `md`.`module_id` = `ms`.`fk_module_id` '
                . 'WHERE `ms`.`master_id` = ' . $route->fk_master_id;

            $module = $this->wpdb->get_results($sql);

            Yachtino::remove_emoji();

            $this->set_routing_data($uriParts, $route->language, $module, $route, $itemId);

            $this->hooks_for_found_route();
            return;
        }
    }

    /**
     * Creates $routingData with all important info for given route.
     * Also called from class-yachtino-public.php for shortcodes.
     */
    public function set_routing_data(
        array $uriParts,
        string $language,
        array $module,
        ?object $route = null,
        string $itemId = '',
        array $addCriteria = [],
    ): void {

        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-library.php';

        if ($module[0]->filter) {
            $moduleFilter = json_decode($module[0]->filter, true);
            foreach ($moduleFilter as $key => $value) {
                if (!is_array($value)) {
                    $moduleFilter[$key] = rawurldecode((string)$value);
                }
            }

        } else {
            $moduleFilter = [];
        }
        if ($module[0]->settings) {
            $settings = json_decode($module[0]->settings, true);
        } else {
            $settings = [];
        }
        if ($module[0]->searchForm) {
            $searchForm = json_decode($module[0]->searchForm, true);
        } else {
            $searchForm = [];
        }

        // Get search criteria from URL for article list.
        if ($module[0]->pageType == 'list') {
            $criteria = Yachtino_Api::get_criteria_from_url($module[0]->itemType);

            // Criteria set by shortcode.
            if ($addCriteria) {
                foreach ($addCriteria as $key => $value) {
                    if (empty($criteria[$key])) {
                        $criteria[$key] = $value;
                    }
                }
            }
        }

        // If eg. path for sailboats (/en/sailboats) and in search form selected another boat type (/en/sailboats?btid=3)
        //  => forward to another path.
        $pathIsCorrect = true;
        if ($uriParts && $module[0]->pageType == 'list' && $criteria && $moduleFilter) {
            foreach ($moduleFilter as $key => $value) {
                if (isset($criteria[$key]) && $criteria[$key] != $value) {
                    $pathIsCorrect = false;
                    break;
                }
            }

            if (!$pathIsCorrect) {
                $criteria = array_merge($moduleFilter, $criteria);
                $moduleFilter = [];
            }
        }

        if ($uriParts && $module[0]->pageType == 'list') {
            if ($criteria) {
                if ($moduleFilter) {
                    $allCriteria = array_merge($criteria, $moduleFilter);
                } else {
                    $allCriteria = $criteria;
                }

                $arrTmp = $this->get_url_for_list($language, $module[0]->itemType, $allCriteria);
                $criteria = $arrTmp['allCriteria'];

                if ($arrTmp['correctData']) {
                    $moduleFilter = $arrTmp['correctData']['filters'];
                    if ($arrTmp['correctData']['routeId'] != $route->route_id) {
                        $uriParts[0] = stripslashes($arrTmp['correctData']['path']);
                    }
                }

                if (!empty($criteria['pg']) && $criteria['pg'] == 1) {
                    unset($criteria['pg']);
                }
                $addToUrl = Yachtino_Library::makePostToUrl($criteria);

                // if the criteria are not in the correct ordering in the URL -> redirect to correct permalink
                $rightUrl = $uriParts[0] . ($addToUrl ? '?' . $addToUrl : '');
                if (YACHTINO_REQUEST_URI != $rightUrl) {
                    $redirectUrl = $rightUrl;
                    wp_redirect($redirectUrl, 301);
                    exit();
                }

            } else {
                $addToUrl = '';
            }
        }

        // set parameters from URL to search criteria
        if ($module[0]->pageType == 'list') {
            foreach ($moduleFilter as $key => $val) {
                $criteria[$key] = $val;
            }
        }

        // ######### save the data into a container for later use ##########
        $this->routingData = new StdClass();
        if ($route) {
            $this->routingData->route = [
                'masterId'    => (int)$route->fk_master_id,
                'routeId'     => (int)$route->route_id,
                'title'       => $route->title,
                'description' => $route->description,
            ];
        } else {
            $this->routingData->route = [];
        }
        $this->routingData->module = [
            'moduleId'    => (int)$module[0]->module_id,
            'itemType'    => $module[0]->itemType,
            'pageType'    => $module[0]->pageType,
            'language'    => $language,
            'settings'    => $settings,
        ];
        if ($module[0]->pageType == 'list') {
            $this->routingData->module['itemId']     = '';
            $this->routingData->module['criteria']   = $criteria;
            $this->routingData->module['filter']     = $moduleFilter;
            $this->routingData->module['searchForm'] = $searchForm;
            $this->routingData->module['linkedDetailMaster'] = $module[0]->linkedDetailMaster;
            $this->routingData->module['linkedDetailUrl']    = json_decode($module[0]->linkedDetailUrl, true);

        // article detail page, item ID necessary
        } else {
            $this->routingData->module['itemId'] = $itemId;
        }

        if ($route) {
            $this->routingData->routeMeta = [
                'title'       => $route->title,
                'description' => $route->description,
            ];

            $this->routingData->lgSwitcher = [];

            // check languages
            // language switcher possible only with Polylang plugin (at the moment)
            $pluginSettings = Yachtino::get_plugin_settings();
            if (count($pluginSettings->languages) > 1 && is_plugin_active('polylang/polylang.php')) {

                // get other languages
                $sql = 'SELECT `path`, `language` FROM ' . $this->wpdb->prefix . 'yachtino_routes '
                    . 'WHERE `fk_master_id` = ' . $route->fk_master_id . ' AND `path` != ""';
                $results = $this->wpdb->get_results($sql);
                if ($results) {
                    foreach ($results as $lgRoute) {
                        $path = $lgRoute->path;

                        // item ID (eg. boat ID) in the url
                        if ($this->routingData->module['pageType'] == 'detail') {
                            $path = str_replace('([a-z0-9]+)', $this->routingData->module['itemId'], $path);

                        // add criteria
                        } elseif ($addToUrl) {
                            $path .= '?' . $addToUrl;
                        }
                        $this->routingData->lgSwitcher[$lgRoute->language] = stripslashes($path);
                    }
                }

                // NOT add_filter('pll_the_language_link', ...),
                // because this would only add a link for lang. switcher, but not also for meta hreflang
                add_filter('pll_translation_url', [$this, 'set_pll_language_link'], 10, 2);
            }
        }
        $classApi = Yachtino_Api::get_instance();
        $classApi->set_routing_data($this->routingData);
    }

    public function get_url_for_list(string $language, string $itemType, array $allCriteria): array
    {
        $output = [
            'allCriteria' => $allCriteria,
            'correctData' => [],
        ];

        // if sent some criteria -> look in possible routes whether there is a suitable route for these criteria
        // if yes, forward to this route (eg. /en/boats?btid=1 => /en/sailboats)
        $sql = 'SELECT `md`.`filter`, `rt`.`route_id`, `rt`.`path` FROM `' . $this->wpdb->prefix . 'yachtino_modules` AS `md` '
            . 'INNER JOIN `' . $this->wpdb->prefix . 'yachtino_route_master` AS `rtm` ON `md`.`module_id` = `rtm`.`fk_module_id` '
            . 'INNER JOIN `' . $this->wpdb->prefix . 'yachtino_routes` AS `rt` ON `rtm`.`master_id` = `rt`.`fk_master_id` '
            . 'WHERE `rt`.`language` = "' . $language . '" '
                . 'AND `md`.`itemType` = "' . $itemType . '" AND `md`.`pageType` = "list" ';
        $results = $this->wpdb->get_results($sql);
        if (!$results) {
            return $output;
        }

        $possibleRoutes = [];
        $basicRoutes = [];
        $maxFoundParams = 0;
        foreach ($results as $result) {
            $filters = json_decode($result->filter, true);
            if (!$filters) {
                $basicRoutes[] = [
                    'routeId' => $result->route_id,
                    'path'    => $result->path,
                    'filters' => [],
                ];
                continue;
            }

            $foundFilters = [];
            foreach ($filters as $key => $value) {
                $value = rawurldecode($value);
                if (isset($allCriteria[$key]) && $allCriteria[$key] == $value) {
                    $foundFilters[$key] = $value;
                } else {
                    $foundFilters = []; // delete filters found until now
                    break;
                }
            }
            if ($foundFilters) {
                $foundNr = count($foundFilters);
                if ($foundNr >= $maxFoundParams) {
                    $maxFoundParams = $foundNr;
                }
                $possibleRoutes[] = [
                    'routeId'   => $result->route_id,
                    'path'      => $result->path,
                    'filters'   => $foundFilters,
                    'nrFilters' => $foundNr,
                ];
            }
        }

        if ($possibleRoutes) {
            foreach ($possibleRoutes as $key => $rData) {
                if ($rData['nrFilters'] < $maxFoundParams) {
                    unset($possibleRoutes[$key]);
                }
            }
        }

        // set correct path
        if ($possibleRoutes) {
            foreach ($possibleRoutes as $key => $rData) {
                $output['correctData'] = $rData;
                break;
            }

        } else {
            foreach ($basicRoutes as $key => $rData) {
                $output['correctData'] = $rData;
                break;
            }
        }

        foreach ($output['correctData']['filters'] as $key => $value) {
            if (isset($allCriteria[$key])) {
                unset($allCriteria[$key]);
            }
        }
        $output['allCriteria'] = $allCriteria;

        return $output;
    }

    private function hooks_for_found_route(): void
    {
        self::register_post_type();
        add_filter('get_canonical_url', [$this, 'set_canonical'], 10, 1);
        add_filter('query_vars', [$this, 'add_query_vars'], 10, 1);
        add_action('parse_request', [$this, 'define_page_content'], 10, 1);
        add_action('pre_get_posts', [$this, 'edit_wp_query'], 10, 1);
        add_action('the_title', [$this, 'get_page_title'], 10, 2);
        add_filter('single_post_title', [$this, 'get_single_post_title'], 10, 2);
        add_action('wp_head', [$this, 'set_meta_description'], 1);
        add_action('the_post', [$this, 'set_post_content'], 10, 1);
        add_filter('template_include', [$this, 'override_template'], 10, 1);

        $template = get_page_template();

        // see documentation of OceanWP
        if ($template && stripos($template, 'themes/oceanwp') !== false) {

            // https://docs.oceanwp.org/article/203-altering-layouts
            add_filter('ocean_post_layout_class', self::class . '::change_ocean_wp_layout', 20);

            // https://docs.oceanwp.org/article/437-disable-the-page-title
            add_filter('ocean_display_page_header', self::class . '::disable_ocean_wp_title', 20);
        }

        // not necessary:
        // add_filter('post_type_link', [$this, 'override_permalink'], 10, 2);
        // add_filter('rewrite_rules_array', [$this, 'add_rewrite_rules'], 10, 1);
        // add_filter('redirect_canonical', [$this, 'override_redirect'], 10, 2);
    }

    /**
     * Register a post type to use when displaying found page
     */
    private static function register_post_type(): void
    {
        // a very quiet post type
        $args = [
            'public'              => false,
            'show_ui'             => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => true,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => false,
            'supports'            => ['title'],
            'has_archive'         => true,
            'rewrite' => [
                'slug'       => 'Yachtino_Router',
                'with_front' => false,
                'feeds'      => false,
                'pages'      => false,
            ]
        ];
        register_post_type(self::POST_TYPE, $args);
    }

    /**
    * redefine the URL for WP - as if the URL would not be slug but direct with variables
    */
    public function add_rewrite_rules(array $rules): array
    {
        $rules = array_merge($rules, $this->rules);

        return $rules;
    }

    public function add_query_vars(array $vars): array
    {
        if (!in_array(self::QUERY_VAR, $vars)) {
            $vars[] = self::QUERY_VAR;
        }

        return $vars;
    }

    public function set_post_content(): void
    {
        global $pages;
        $pages = [$this->pageContent];
    }

    /**
     * ATTENTION! WP $query is not the same as WP_Query $query
     */
    public function define_page_content(WP $query): void
    {
        if (!$this->routingData || !$this->routingData->route) {
            return;
        }

        $classApi = Yachtino_Api::get_instance();
        if ($this->routingData->module['pageType'] == 'list') {
            $this->pageContent = $classApi->get_article_list($this->routingData->module, $this->routingData->route);

        } else {
            $this->pageContent = $classApi->get_article_detail(
                $this->routingData->module, $this->routingData->module['itemId'], $this->routingData->route);
        }

        $this->define_template();
    }

    private function define_template(): void
    {
        $extra = [
            'route-$id.php',
            'route.php',
            'page-$id.php',
            'page.php',
        ];
        foreach ($extra as $key => $path) {
            $extra[$key] = str_replace('$id', self::$routeId, $path);
        }
        $this->template = locate_template($extra);
    }

    public function get_page_title(string $postTitle, int|string $postId): string
    {
        if (self::$postId == $postId && !empty($this->routingData->routeMeta['title'])) {
            return $this->routingData->routeMeta['title'];
        } else {
            return $postTitle;
        }
    }

    /**
     * Gets meta <title>
     */
    public function get_single_post_title(string $postTitle, object $post): string
    {
        return $this->get_page_title($postTitle, $post->ID);
    }

    public function set_meta_description(): void
    {
        if (!empty($this->routingData->routeMeta['description'])) {
            echo "\r\n" . '<meta name="description" content="' . esc_attr($this->routingData->routeMeta['description']) . '">' . "\r\n";
        }
    }

    public function set_canonical(string $canonical): string
    {
        return home_url() . $this->thisUrl;
    }

    /**
     * Edit WordPress's query so it finds our placeholder page
     * ATTENTION! WP $query is not the same as WP_Query $query
     */
    public function edit_wp_query(WP_Query $query): void
    {
        if (isset($query->query_vars[self::QUERY_VAR])) {
            // make sure we get the right post
            $query->query_vars['post_type'] = self::POST_TYPE;
            $query->query_vars['p'] = self::get_post_id();

            // override any vars WordPress set based on the original query
            $query->is_single = true;
            $query->is_singular = true;
            $query->is_404 = false;
            $query->is_home = false;
        }
    }

    /**
     * If asking for a paged router page, or if %category% is in
     * the permastruct, WordPress will try to redirect to the URL
     * for the dummy page. This should stop that from happening.
     */
    public function override_redirect(string $redirectUrl, string $requestedUrl): bool|string
    {
        if ($redirectUrl && get_query_var(self::QUERY_VAR)) {
            return false;
        }
        if ($redirectUrl && get_permalink(self::get_post_id()) == $redirectUrl) {
            return false;
        }

        return $redirectUrl;
    }

    /**
     * Get the ID of the placeholder post
     */
    private static function get_post_id(): int
    {
        if (!self::$postId) {
            $posts = get_posts([
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 1,
            ]);
            if ($posts) {
                self::$postId = $posts[0]->ID;
            } else {
                self::$postId = self::make_post();
            }
        }
        return self::$postId;
    }

    /**
     * Make a new placeholder post
     */
    private static function make_post(): int
    {
        $post = [
            'post_title'  => __('Yachtino Router Placeholder Page', 'yachtino-router'),
            'post_status' => 'publish',
            'post_type'   => self::POST_TYPE,
        ];
        $id = wp_insert_post($post);
        if (is_wp_error($id)) {
            return 0;
        }

        return $id;
    }

    /**
     * Use the specified template file
     */
    public function override_template(string $template): string
    {
        if ($this->template && file_exists($this->template)) { // these checks shouldn't be necessary, but no harm
            return $this->template;
        }

        return $template;
    }

    public function override_permalink(string $postLink, WP_Post $post): string
    {
        if ($post->ID == self::get_post_id()) {
            if ($post->ID == get_queried_object_id()) {
                global $wp;
                return home_url($wp->request);
            }
        }

        return $postLink;
    }

    /**
     * Defines the permalink of given language ($slug) for language switcher of Polylang
     * Function for add_filter from Polylang plugin, see: add_filter('pll_the_language_link'...)
     */
    public function set_pll_language_link(?string $url, string $slug): ?string
    {
        if (!empty($this->routingData->lgSwitcher[$slug])) {
            return $this->routingData->lgSwitcher[$slug];
        }
        return $url;
    }

    public static function change_ocean_wp_layout(string $class): string
    {
        return 'full-width';
    }

    public static function disable_ocean_wp_title(bool $return): bool
    {
        return false;
    }

}