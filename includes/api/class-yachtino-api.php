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

class Yachtino_Api
{
    private StdClass $routingData;

    private array $imagePaths;

    private string $apiHost = 'https://api.boatadmin.com';

    /**
     * singleton pattern
     */
    private static ?self $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function get_instance(): self
    {
        global $wpdb;

        if (is_null(self::$instance)) {
            self::$instance = new self();

            // template data
            self::$instance->imagePaths = [
                'placeholderImg' => plugins_url('/assets/images/loading-wheel.gif', YACHTINO_DIR_PATH . '/yachtino-boat-listing.php'),
            ];

            add_filter('the_content', self::class . '::yachtino_correct_content', 100);
        }
        return self::$instance;
    }

    public function set_routing_data(StdClass $routingData): void
    {
        $this->routingData = $routingData;
    }

    public function get_routing_data(): ?StdClass
    {
        if (isset($this->routingData)) {
            return $this->routingData;
        } else {
            return null;
        }
    }

    // languages from API and default locale
    public static function allowed_languages(): array
    {
        return [
            'cs' => 'cs_CZ',
            'da' => 'da_DK',
            'de' => 'de_DE',
            'en' => 'en_GB',
            'es' => 'es_ES',
            'fr' => 'fr_FR',
            'it' => 'it_IT',
            'nl' => 'nl_NL',
            'pl' => 'pl_PL',
            'pt' => 'pt_PT',
            'ru' => 'ru_RU',
            'tr' => 'tr_TR',
        ];
    }

    // remove this silly microsoft minus from output
    public static function yachtino_correct_content(string $content): string
    {
        if ('yachtino_page' === get_post_type()) {
            $content = str_replace('–', '-', $content);
            $content = str_replace('&#8211;', '-', $content);
        }
        return $content;
    }

    // special encoding for Yachtino API
    public static function escape_url_for_api(string $var): string
    {
        $var = str_replace(['/', '\\'], ['~~', '§~§'], $var);
        return rawurlencode($var);
    }

    public function create_api_url(array $urlParts, array $additionalVars = []): string
    {
        $settings = Yachtino::get_plugin_settings();

        // for initial editing of configuration - there is no API key yet; use tempporary key
        if (!$settings->apiKey && $urlParts['part2'] == 'get-mixed' && !empty($additionalVars['lgs'])) {
            $apiKey = 'plgwp_tmp_2zo5k3qs9jvn8wy1plk6i';
            $siteId = '2';
        } else {
            $apiKey = $settings->apiKey;
            $siteId = (string)$settings->apiSiteId;
        }

        $ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        if (!$ip && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (!$ip) {
            $ip = '';
        }

        $userAgent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT');
        if (!$userAgent && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
        }
        if (!$userAgent) {
            $userAgent = '';
        }

        $url = $this->apiHost . '/' . $urlParts['language'] . '/' . $urlParts['part1'] . '/' . $urlParts['part2']
            . '?code=' . $apiKey
            . '&site_id=' . $siteId
            . '&yo_vers=1.1&api_vers=2.0&plg_vers=' . $settings->version
            . '&ip=' . $ip
            . '&http_agent=' . self::escape_url_for_api($userAgent)
            . '&remote_host=' . gethostbyaddr($ip);

        if ($additionalVars) {
            $url .= '&' . Yachtino_Library::makePostToUrl($additionalVars);
        }
        return $url;
    }

    /**
     * Send request to Yachtino API and returns json object
     * $urlParts - language, part1, part2 (for building URL: /en/controller/action)
     * $additionalVars - additional params in the URL ($_GET)
     * $postVars - variables to be sent as $_POST variables
     */
    public function send_request_to_api(array $urlParts, array $additionalVars = [], array $postVars = []): object
    {
        $url = $this->create_api_url($urlParts, $additionalVars);
// echo "\r\n" . $url . '<br />';

        $args = [
            'timeout'    => 15,
            'sslverify'  => false,
            'user-agent' => 'WP plugin Yachtino',
        ];

        if ($postVars) {
            $args['body'] = http_build_query($postVars);
            $args['method'] = 'POST';
        } else {
            $args['method'] = 'GET';
        }

        $response = wp_remote_request($url, $args);

        if ($response instanceof WP_Error) {
            wp_die('Sorry, an unknown error occured.');
        }

        // success
        if ($response['response']['code'] == 200) {
            if (isset($response['body'])) {
                return json_decode($response['body'], false);
            } else {
                return new StdClass();
            }

        } elseif ($response['response']['code'] == 403) {
            wp_die('Forbidden', 403);

        // save errors in a log file on Yachtino server
        // avoid looping this function (plugin-errors already called)
        } elseif ($urlParts['part2'] != 'plugin-errors') {
            $arr = [
                'u' => str_replace('https://api.boatadmin.com', '', $url),
                'c' => $response['response']['code'],
                'b' => $response['body'],
            ];
            $str = json_encode($arr);

            $urlPartsNew = [
                'language' => $urlParts['language'],
                'part1' => 'article-special',
                'part2' => 'plugin-errors',
            ];
            $additionalNew = [
                'errors' => base64_encode($str),
            ];
            if ($postVars) {
                $post = json_encode($postVars);
                $additionalNew['post'] = base64_encode($post);
            }
            $this->send_request_to_api($urlPartsNew, $additionalNew);

        } else {
// echo "\r\n" . '<pre>';
        // ob_start();
        // var_dump($response);
        // $buffer = ob_get_clean();
        // echo htmlentities($buffer, ENT_NOQUOTES, 'UTF-8');
// echo '</pre>';
        }
        wp_die('Sorry, an unknown error occured.');
    }

    /**
     * Gets data for a list of boats/trailers/engines... from API
     * $moduleData - object with yachtino module from wp database
     *              string $itemType: cboat (charter), sboat (for sale), trailer, engine, mooring, gear
     *              array $filter - variables to limit the result (eg. btid=1 for only sailboats)
     *              array $searchForm - fieldNames that should be shown in search form, get options for selectboxes (eg. all countries)
     * $route - is filled if the output is a whole page (not only embedded module/shortcode)
     * @return - HTML code (filled template)
     */
    public function get_article_list(array $moduleData, array $route): string
    {
        $urlParts = [
            'language' => $moduleData['language'],
            'part1'    => 'article-data',
            'part2'    => 'list',
        ];
        $additionalVars = [];
        $additionalVars['itemtype'] = $moduleData['itemType'];
        $additionalVars['trans'] = '1';

        if ($moduleData['settings'] && $moduleData['settings']['hitsPerPage']) {
            $additionalVars['bnr'] = $moduleData['settings']['hitsPerPage'];
        }

        if ($moduleData['criteria']) {
            $additionalVars = array_merge($additionalVars, $moduleData['criteria']);
        }

        // get options for selectboxes/dropdown for search form (eg. all countries)
        $fields = '';
        foreach ($moduleData['searchForm'] as $fieldName) {
            $fields .= $fieldName . ',';
        }
        if ($fields) {
            $additionalVars['fields'] = substr($fields, 0, -1);
        }

        $apiResponse = $this->send_request_to_api($urlParts, $additionalVars);

        // define article data from API response
        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-article.php';
        $classArticle = Yachtino_Article::get_instance();
        $classArticle->set_basics($moduleData, 'list', $apiResponse->translation);

        $allData = new StdClass();
        $allData->itemType = $moduleData['itemType'];
        $allData->items = [];
        $allData->translation = [];
        $allData->pageLayout  = $moduleData['settings']['layout'];
        $allData->searchPlace = $moduleData['settings']['searchPlace'];

        foreach ($apiResponse->adverts as $advert) {
            $allData->items[] = $classArticle->get_data($advert);
        }

        // no search results -> show "There are no hits." in template
        if (!$allData->items) {
            $allData->translation['NoSearchResult'] = $apiResponse->translation->words->NoSearchResult;
        }

        // define paging if desired in module settings
        if ($moduleData['settings']['showPaging']
        && $apiResponse->info->hits > $moduleData['settings']['hitsPerPage']) {
            if (!empty($moduleData['criteria']['pg'])) {
                $page = (int)$moduleData['criteria']['pg'];
            } else {
                $page = 1;
            }

            $requestUri = YACHTINO_REQUEST_URI;
            $allData->paging = self::get_paging(
                $apiResponse->info->hits, $moduleData['settings']['hitsPerPage'], $requestUri, $page);
        }

        if ($moduleData['searchForm']) {
            $allData->criteria = $moduleData['criteria'];

            $allData->search = Yachtino_Article::get_search_form($moduleData['searchForm'], $apiResponse);

            $templateName = '/templates/public/page-list.html';

        } elseif ($allData->pageLayout == 'tiles') {
            $templateName = '/templates/public/incl-tiles.html';

        } else {
            $templateName = '/templates/public/incl-list.html';
        }

        // if a single article has been deleted, there is forwarding to an article list with a message
        if (!empty($_GET['msg'])) {
            if ($_GET['msg'] == 'offer_not') {
                $tplData = [
                    'errors' => [],
                ];
                $tplData['errors']['errMsg'][] = $apiResponse->translation->words->OfferNotExisting;
            }
        }

        // return whole page
        if ($route) {
            $routeMeta = [
                'h1'          => $route['h1'],
                'title'       => $route['title'],
                'description' => $route['description'],
            ];

            $this->routingData->routeMeta = $routeMeta;
        }
        ob_start();
        include YACHTINO_DIR_PATH . $templateName;
        $templateContent = ob_get_clean();

        return $templateContent;
    }

    /**
     * Gets data for a single boat/trailer/engine... from API
     * $moduleData - array with yachtino module from wp database
     *              string $itemType: cboat (charter), sboat (for sale), trailer, engine, mooring, gear
     * $route - is filled if the output is a whole page (not only embedded module/shortcode)
     * @return - HTML code (filled template)
     */
    public function get_article_detail(array $moduleData, string $itemId, array $route): string
    {
        $urlParts = [
            'language' => $moduleData['language'],
            'part1'    => 'article-data',
            'part2'    => 'detail',
        ];
        $additionalVars = [
            'itemtype' => $moduleData['itemType'],
            'itid'     => $itemId,
            'trans'    => '1', // gets also translated field names for given language
        ];

        // list of countries for sending request
        if ($moduleData['settings']['showContactForm']) {
            $additionalVars['fields'] = 'ccountry';
            $additionalVars['mergeccountryspec'] = '1'; // for special countries at the begin
        }
        $apiResponse = $this->send_request_to_api($urlParts, $additionalVars);

        // no article (boat, trailer...) given - it has been deleted/deactivated
        if (empty($apiResponse->advert)) {

            if ($route) {
                global $wpdb;

                // redirect to article list
                // first look for general list
                $sql = 'SELECT `rt`.`path` FROM `' . $wpdb->prefix . 'yachtino_routes` AS `rt` '
                    . 'INNER JOIN `' . $wpdb->prefix . 'yachtino_route_master` AS `ms` ON `rt`.`fk_master_id` = `ms`.`master_id` '
                    . 'INNER JOIN `' . $wpdb->prefix . 'yachtino_modules` AS `md` ON `ms`.`fk_module_id` = `md`.`module_id` '
                    . 'WHERE `rt`.`language` = "' . $moduleData['language'] . '" AND `md`.`linkedDetailMaster` = ' . $route['masterId']
                    . ' AND `md`.`filter` LIKE "[]"';
                $results = $wpdb->get_results($sql);

                // if no general list, take any list (of course, the same item type)
                if (!$results) {
                    $sql = 'SELECT `rt`.`path` FROM `' . $wpdb->prefix . 'yachtino_routes` AS `rt` '
                        . 'INNER JOIN `' . $wpdb->prefix . 'yachtino_route_master` AS `ms` ON `rt`.`fk_master_id` = `ms`.`master_id` '
                        . 'INNER JOIN `' . $wpdb->prefix . 'yachtino_modules` AS `md` ON `ms`.`fk_module_id` = `md`.`module_id` '
                        . 'WHERE `rt`.`language` = "' . $moduleData['language'] . '" AND `md`.`linkedDetailMaster` = ' . $route['masterId'];
                    $results = $wpdb->get_results($sql);
                }

                if ($results) {
                    $redirectUrl = home_url() . stripslashes($results[0]->path) . '?msg=offer_not';
                    wp_redirect($redirectUrl, 301);
                    exit();
                }
            }
            return '';

        // after Big relaunch in October 2022, all boats got a new ID
        // if some link goes to old ID -> redirect to new ID
        } elseif ($itemId != $apiResponse->advert->managing->itemId) {
            $redirectUrl = home_url()
                . str_replace('/' . $itemId, '/' . $apiResponse->advert->managing->itemId, YACHTINO_REQUEST_URI);
            wp_redirect($redirectUrl, 301);
        }

        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-article.php';
        $classArticle = Yachtino_Article::get_instance();
        $classArticle->set_basics($moduleData, 'detail', $apiResponse->translation);
        $itemData = $classArticle->get_data($apiResponse->advert);

        if ($moduleData['settings']['showContactForm']) {
            require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-request.php';
            $classReqest = Yachtino_Request::get_instance();
            $requestData = $classReqest->get_form_data($moduleData['language'], $moduleData['itemType'], $itemData, $apiResponse);
        }

        $specRequests = [];
        if ($moduleData['itemType'] == 'sboat') {
            if (!empty($moduleData['settings']['insuranceRequest']) && !empty($apiResponse->requests->insurance)) {
                $specRequests['insurance'] = $apiResponse->translation->request->InsuranceRequest;
            }
            if (!empty($moduleData['settings']['financingRequest']) && !empty($apiResponse->requests->financing)) {
                $specRequests['financing'] = $apiResponse->translation->request->FinancingRequest;
            }
            if (!empty($moduleData['settings']['transportRequest']) && !empty($apiResponse->requests->transport)) {
                $specRequests['transport'] = $apiResponse->translation->request->TransportRequest;
            }
        }

        // return whole page
        if ($route) {
            $placeholders = self::create_placeholders_for_item($moduleData['itemType'], $apiResponse->advert);
            foreach ($this->routingData->routeMeta as $metaKey => $metaValue) {
                foreach ($placeholders as $phKey => $phValue) {
                    $metaValue = str_replace('{' . $phKey . '}', $phValue, $metaValue);
                }
                $this->routingData->routeMeta[$metaKey] = $metaValue;
            }
            $routeMeta = $this->routingData->routeMeta;

            $templateName = '/templates/public/page-detail.html';

        // return only a module / shortcode for embedding
        } else {
            $templateName = '/templates/public/incl-detail.html';
        }
        $itemData->itemType = $moduleData['itemType'];

        ob_start();
        include YACHTINO_DIR_PATH . $templateName;
        $templateContent = ob_get_clean();

        return $templateContent;
    }

    public static function create_placeholders_for_item(string $itemType, object $advertDataApi): array
    {
        if ($itemType == 'cboat' || $itemType == 'sboat') {
            $output = [
                'model'        => $advertDataApi->boatData->general->model,
                'name'         => $advertDataApi->boatData->general->name,
                'yearBuilt'    => $advertDataApi->boatData->general->yearBuilt,
                'manufacturer' => $advertDataApi->boatData->general->manufacturer,
                'boatType'     => $advertDataApi->boatData->general->boatType,
                'category'     => $advertDataApi->boatData->general->category,
            ];
            if ($advertDataApi->boatData->measures->loa->m->value) {
                $output['length'] = $advertDataApi->boatData->measures->loa->m->value . ' '
                    . $advertDataApi->boatData->measures->loa->m->unit;
            } else {
                $output['length'] = '';
            }
            if ($advertDataApi->boatData->measures->beam->m->value) {
                $output['beam'] = $advertDataApi->boatData->measures->beam->m->value . ' '
                    . $advertDataApi->boatData->measures->beam->m->unit;
            } else {
                $output['beam'] = '';
            }

        } elseif ($itemType == 'engine') {
            $output = [
                'model'        => $advertDataApi->engineData->general->model,
                'yearBuilt'    => $advertDataApi->engineData->general->yearBuilt,
                'manufacturer' => $advertDataApi->engineData->general->manufacturer,
                'newOrUsed'    => $advertDataApi->saleData->articleClass,
                'power'        => trim($advertDataApi->engineData->power->hp->value . ' '
                    . $advertDataApi->engineData->power->hp->unit),
            ];

        } elseif ($itemType == 'trailer') {
            $output = [
                'model'        => $advertDataApi->trailerData->general->model,
                'yearBuilt'    => $advertDataApi->trailerData->general->yearBuilt,
                'manufacturer' => $advertDataApi->trailerData->general->manufacturer,
                'newOrUsed'    => $advertDataApi->saleData->articleClass,
                'length'       => trim($advertDataApi->trailerData->metresFeet->length->m->value . ' '
                    . $advertDataApi->trailerData->metresFeet->length->m->unit),
            ];
        }
        if ($itemType == 'cboat') {
            if (!empty($advertDataApi->charterData->areas->countries[0]->name)) {
                $output['country'] = $advertDataApi->charterData->areas->countries[0]->name;
            } else {
                $output['country'] = '';
            }
        } else {
            $output['country'] = $advertDataApi->saleData->lying->country;
        }
        return $output;
    }

    /**
     * Creates navigation for paging (more result pages)
     * $hits - number of results
     * $hitsPerPage - the number of results per page
     * $basicPage - url of the page (without page number)
     * $page - current page number
     * returns html code with links to each page
     */
    public static function get_paging(int $hits, int $hitsPerPage, string $basicPage, int $page = 0): string
    {
        if (!$page) {
            $page = 1;
        }

        // remove page number from $basicPage
        $basicPage = preg_replace('/([\?&])pg\=[0-9]+/u', '$1', $basicPage);
        $basicPage = str_replace('?&', '?', $basicPage);
        $basicPage = str_replace('&&', '&', $basicPage);
        $basicPage = preg_replace('/[\?&]$/u', '', $basicPage);

        if (strpos($basicPage, '?') !== false) {
            $signNewVar = '&';
        } else {
            $signNewVar = '?';
        }

        $output = '<div class="yachtino-paging-wrapper"><ul>';

        $pages = ceil($hits / $hitsPerPage);

        if ($page > 1) {
            $output .= '<li class="yachtino-paging-page previous-page"><a href="' . $basicPage . '">«</a></li>';
        }

        $maxRange = ($page + 5);
        if ($maxRange > $pages) {
            $maxRange = $pages;
        }

        $minRange = ($page - 3);
        if ($minRange < 1) {
            $minRange = 1;
        }

        for ($i = $minRange; $i <= $maxRange; $i++) {

            // this/current page does not have a link
            if ($i == $page) {
                $output .= '<li class="yachtino-paging-page active">' . $i . '</li>';

            // first page does not have "pg" in the url
            } elseif ($i == 1) {
                $output .= '<li class="yachtino-paging-page"><a href="' . $basicPage . '">' . $i . '</a></li>';

            } else {
                $output .= '<li class="yachtino-paging-page"><a href="' . $basicPage . $signNewVar . 'pg=' . $i . '">' . $i . '</a></li>';
            }
        }

        if ($page < $pages) {
            $output .= '<li class="yachtino-paging-page next-page"><a href="' . $basicPage . $signNewVar . 'pg=' . ($page + 1) . '">»</a></li>';
        }

        $output .= '</ul></div>';

        return $output;
    }

    /**
     * Gets search criteria from URL and brings them in the right ordering
     * IMPORTANT!!! for permalinks the ordering must be everytime the same
     */
    public static function get_criteria_from_url(string $itemType): array
    {
        $criteria = [];
        $currentUrl = home_url() . YACHTINO_REQUEST_URI;
        $urlComponents = parse_url($currentUrl);
        if (isset($urlComponents['query'])) {
            parse_str($urlComponents['query'], $criteriaTmp);

            $criteria = self::filter_criteria($itemType, $criteriaTmp);
        }

        return $criteria;
    }

    public static function filter_criteria(string $itemType, array $criteriaOrig): array
    {
        $criteria = [];
        $possibleCriteria = self::possible_criteria($itemType);
        foreach ($possibleCriteria as $key => $one) {
            if (isset($criteriaOrig[$key])) {
                if ($criteriaOrig[$key]) {
                    $criteria[$key] = $criteriaOrig[$key];
                }
            }
        }
        return $criteria;
    }

    /**
     * Defines possible search criteria and their ordering in the URL
     */
    public static function possible_criteria(string $itemType): array
    {
        // IMPORTANT!!!!
        // If you change the ordering of the params here -> also change in assets/js/yachtino-public.js
        if ($itemType == 'cboat' || $itemType == 'sboat') {
            $output = [
                'btid'   => true, // boat type (sailboat, motorboat, inflatable, small boat, jet ski / gadgets)
                'bcid'   => true, // boat category (catamaran, houseboat...)
                'btcid'  => true, // combined btid AND bcid
                'manfb'  => true, // boat manufacturer
                'ct'     => true, // country
                'provid' => true, // US state, Canadian province...
                'fuel'   => true, // engine fuel
                'hmid'   => true, // hull material
                'lngf'   => true, // boat length from (min)
                'lngt'   => true, // boat length to (max)
                'ybf'    => true, // year built from (min)
                'ybt'    => true, // year built to (max)
                'powf'   => true, // engine power from (min)
                'powt'   => true, // engine power to (max)
                'cabf'   => true, // number of cabins from (min)
                'cabt'   => true, // number of cabins to (max)
                'fly'    => true, // flybridge
            ];
        }

        // charter boat
        if ($itemType == 'cboat') {
            $output['rgww']   = true; // charter region + waterway
            $output['tn']     = true; // charter port/town
            $output['wkprcf'] = true; // price boat/week from (min)
            $output['wkprct'] = true; // price boat/week to (max)
            $output['daprcf'] = true; // price boat/day from (min)
            $output['daprct'] = true; // price boat/day to (max)
            $output['hrprcf'] = true; // price boat/hour from (min)
            $output['hrprct'] = true; // price boat/hour to (max)
            $output['beprcf'] = true; // price berth/week from (min)
            $output['beprct'] = true; // price berth/week to (max)

        // boat for sale
        } elseif ($itemType == 'sboat') {
            $output['newused'] = true; // new or used boat for sale
            $output['sprcf']   = true; // sale price from (min)
            $output['sprct']   = true; // sale price to (max)

        // boat engine for sale
        } elseif ($itemType == 'engine') {
            $output = [
                'oclass' => true, // new / used
                'ct'     => true, // country
                'provid' => true, // US state, Canadian province...
                'manfe'  => true, // manufacturer
                'etype'  => true, // inborder / outborder
                'fuel'   => true, // engine fuel
                'powf'   => true, // engine power from (min)
                'powt'   => true, // engine power to (max)
                'ybf'    => true, // year built from (min)
                'ybt'    => true, // year built to (max)
                'sprcf'  => true, // sale price from (min)
                'sprct'  => true, // sale price to (max)
            ];

        // boat trailer for sale
        } elseif ($itemType == 'trailer') {
            $output = [
                'oclass' => true, // new / used
                'ct'     => true, // country
                'provid' => true, // US state, Canadian province...
                'manft'  => true, // manufacturer
                'trmid'  => true, // trailer material
                'lngf'   => true, // trailer length from (min)
                'lngt'   => true, // trailer length to (max)
                'ybf'    => true, // year built from (min)
                'ybt'    => true, // year built to (max)
                'sprcf'  => true, // sale price from (min)
                'sprct'  => true, // sale price to (max)
            ];
        }

        $output['q']       = true; // quick search (free text)
        $output['orderby'] = true; // sorting
        $output['pg']      = true; // page number

        return $output;
    }

}
