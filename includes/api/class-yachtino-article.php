<?php

declare(strict_types=1);

/**
 * Defines data for a single article - boat, trailer, engine, mooring or marine gear.
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

// Don't access directly
if (!defined('ABSPATH')) {
    exit();
};

class Yachtino_Article
{
    private StdClass $output;
    private object $advert;
    private object $mainNode;
    private object $apiTranslate;

    private string $entityType;
    private string $pageType;
    private string $itemType;
    private string $pageLayout;
    private string $singleItemUrl;

    private array $mainDataBlock;
    private array $moduleData;
    private array $priceTypes;
    private array $pricesAsCriteria;

    /**
     * Singleton pattern.
     */
    private static ?self $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function get_instance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Data for all boats/trailers/engines.
     *
     * @param $moduleData {
     *      Control variables.
     *
     *      @type string $itemType Possible: cboat (charter), sboat (for sale), trailer, engine, mooring, gear.
     *      @type string $pageType Possible: list, detail.
     * }
     * @param $apiTranslate Node from JSON response from Yachtino API with translated words.
     */
    public function set_basics(array $moduleData, string $pageType, object $apiTranslate): void
    {
        global $wpdb;
        $this->moduleData   = $moduleData;
        $this->itemType     = $moduleData['itemType'];
        $this->pageType     = $pageType;
        $this->apiTranslate = $apiTranslate;

        // Get link to detail page of boat/engine/trailer from the list.
        $this->singleItemUrl = '';
        if ($pageType == 'list') {
            if ($moduleData['linkedDetailUrl']) {
                if (!empty($moduleData['linkedDetailUrl'][$moduleData['language']])) {
                    $this->singleItemUrl = str_replace('{itemId}', '([a-z0-9]+)', $moduleData['linkedDetailUrl'][$moduleData['language']]);
                } else {
                    $this->singleItemUrl = '';
                }

            } else {
                $sql = 'SELECT r.path FROM ' . $wpdb->prefix . 'yachtino_routes AS r '
                    . 'INNER JOIN ' . $wpdb->prefix . 'yachtino_route_master AS m ON r.fk_master_id = m.master_id '
                    . 'WHERE r.fk_master_id = ' . $moduleData['linkedDetailMaster'] . ' AND r.language = "' . $moduleData['language'] . '"';

                $result = $wpdb->get_results($sql);
                if ($result) {
                    $this->singleItemUrl = stripslashes($result[0]->path);
                }
            }

            $this->pageLayout = $moduleData['settings']['layout'];
        }

        // Price types for charter boats.
        if ($this->itemType == 'cboat') {

            // For list show only the most important prices.
            if ($this->pageType == 'list') {
                $this->priceTypes = ['boatWeek', 'boatDay', 'boatHour', 'berthWeek',];

                $this->pricesAsCriteria = [
                    'wkprc' => 'boatWeek',
                    'daprc' => 'boatDay',
                    'beprc' => 'berthWeek',
                    'hrprc' => 'boatHour',
                ];

            // In detail view show all prices.
            } else {
                $this->priceTypes = ['boatWeek', 'boatDay', 'berthWeek', 'boatWeekend', 'boatShortweek', 'cabinWeek', 'boatHour',];
            }
        }
    }

    public static function get_head_shortcode(string $pageType, object $apiResponse): array
    {
        $output = [
            'title'       => '',
            'description' => '',
            'data'        => [],
        ];

        if (!empty($apiResponse->seo)) {
            $output['title']       = $apiResponse->seo->title;
            $output['description'] = $apiResponse->seo->description;
        }

        if ($pageType == 'detail') {
            if (empty($apiResponse->advert)) {
                return $output;
            }
            $itemType = $apiResponse->advert->managing->itemType;
            $output['data'] = Yachtino_Api::create_placeholders_for_item($itemType, $apiResponse->advert);

        } else {
            if (!empty($apiResponse->seo)) {
                $output['data'] = (array)$apiResponse->seo;
            }
        }

        return $output;
    }

    /**
     * Gets data for a single article.
     */
    public function get_data(object $advert): StdClass
    {
        $this->output = new StdClass();
        $this->advert = $advert;

        if ($this->itemType == 'cboat' || $this->itemType == 'sboat') {
            $this->mainNode   = $advert->boatData;
            $this->entityType = 'boat';

        } else {
            $name = $this->itemType . 'Data';
            $this->mainNode   = $advert->{$name};
            $this->entityType = 'product';
        }

        $this->output->itemId = $advert->managing->itemId;
        $this->output->model  = $this->mainNode->general->model;

        if ($this->pageType == 'detail') {
            $this->mainDataBlock = [];
        }

        if ($this->entityType == 'boat') {
            $this->define_boat_data();

        } elseif ($this->itemType == 'engine') {
            $this->define_engine_data();

        } elseif ($this->itemType == 'trailer') {
            $this->define_trailer_data();
        }

        if ($this->itemType == 'cboat') {
            $this->define_charter_data();
        } else {
            $this->define_sale_data();
        }
        $this->define_media();

        $this->define_texts();

        if ($this->pageType == 'list') {
            if ($this->pageLayout == 'tiles') {
                $this->prepare_output_tiles();
            } else {
                $this->prepare_output_list();
            }
        } else {
            $this->prepare_output_detail();
        }

        $this->define_added_translation();

        return $this->output;
    }

    private function define_boat_data(): void
    {
        // $this->output->name     = $this->mainNode->general->name;
        $this->output->boatType = $this->mainNode->general->boatType;
        $this->output->category = $this->mainNode->general->category;

        if ($this->pageType == 'list') {
            $this->output->yearBuilt    = $this->mainNode->general->yearBuilt;
            $this->output->allCabins    = $this->mainNode->passengers->allCabins;
            $this->output->hullMaterial = $this->mainNode->general->hullMaterial;
            $this->output->engineString = $this->mainNode->drive->engineString;

            if ($this->pageLayout == 'list') {
                $this->help_measure($this->mainNode->measures, 'loa');
                $this->help_measure($this->mainNode->measures, 'beam');
                $this->help_measure($this->mainNode->measures, 'draught');

            } else {
                $this->output->loa = [];
                if (!empty($this->mainNode->measures->loa->m->value)) {
                    $this->output->loa['value'] = $this->mainNode->measures->loa->m->value;
                    $this->output->loa['unit'] = $this->mainNode->measures->loa->m->unit;
                }
                if (!empty($this->mainNode->measures->beam->m->value)) {
                    $this->output->beam['value'] = $this->mainNode->measures->beam->m->value;
                    $this->output->beam['unit'] = $this->mainNode->measures->beam->m->unit;
                }
                if (!empty($this->mainNode->measures->draught->m->value)) {
                    $this->output->draught['value'] = $this->mainNode->measures->draught->m->value;
                    $this->output->draught['unit'] = $this->mainNode->measures->draught->m->unit;
                }
            }
        }

        if ($this->pageType == 'list') {
            return;
        }

        $singleFields = $this->get_single_fields();

        foreach ($singleFields as $nodeName => $subData) {
            foreach($subData as $fieldName) {
                if (!empty($this->mainNode->{$nodeName}->{$fieldName})) {
                    $this->mainDataBlock[$fieldName] = [
                        'name'  => $this->apiTranslate->advert->boatData->{$nodeName}->{$fieldName},
                        'value' => $this->mainNode->{$nodeName}->{$fieldName},
                    ];
                }
            }
        }

        $this->mainDataBlock['itemId'] = [
            'name'  => $this->apiTranslate->advert->managing->boatId,
            'value' => $this->advert->managing->itemId,
        ];

        if ($this->mainNode->general->category) {
            $this->mainDataBlock['category'] = [
                'name'  => $this->apiTranslate->advert->boatData->general->category,
                'value' => $this->mainNode->general->category,
            ];
            if ($this->mainNode->general->secCategories) {
                foreach ($this->mainNode->general->secCategories as $cat) {
                    $this->mainDataBlock['category']['value'] .= ', ' . $cat->name;
                }
            }
        }

        $this->help_measure_detail(
            $this->mainNode->measures, 'loa', $this->apiTranslate->advert->boatData->measures);
        $this->help_measure_detail(
            $this->mainNode->measures, 'beam', $this->apiTranslate->advert->boatData->measures);
        $this->help_measure_detail(
            $this->mainNode->measures, 'draught', $this->apiTranslate->advert->boatData->measures);
        $this->help_measure_detail(
            $this->mainNode->measures, 'clearance', $this->apiTranslate->advert->boatData->measures);
        $this->help_measure_detail(
            $this->mainNode->measures, 'headroom', $this->apiTranslate->advert->boatData->measures);

        $this->help_tank('water');
        $this->help_tank('fuel');
        $this->help_tank('holding');

        $this->help_value_with_unit(
            $this->mainNode->measures, 'displacement', $this->apiTranslate->advert->boatData->measures);
        $this->help_value_with_unit(
            $this->mainNode->measures, 'payload', $this->apiTranslate->advert->boatData->measures);
        $this->help_value_with_unit(
            $this->mainNode->drive, 'cruisingSpeed', $this->apiTranslate->advert->boatData->drive);
        $this->help_value_with_unit(
            $this->mainNode->drive, 'cruisingConsumption', $this->apiTranslate->advert->boatData->drive);
        $this->help_value_with_unit(
            $this->mainNode->drive, 'maxSpeed', $this->apiTranslate->advert->boatData->drive);
        $this->help_value_with_unit(
            $this->mainNode->drive, 'maxConsumption', $this->apiTranslate->advert->boatData->drive);
        $this->help_value_with_unit(
            $this->mainNode->drive, 'engineRange', $this->apiTranslate->advert->boatData->drive);

        $cabinNodes = ['masterCabins', 'crewCabins', 'guestCabins'];
        foreach ($cabinNodes as $fieldName) {
            if ($this->mainNode->passengers->{$fieldName}) {
                $this->mainDataBlock[$fieldName] = [
                    'name'  => '- ' . $this->apiTranslate->advert->words->thereof . ' '
                        . $this->apiTranslate->advert->boatData->passengers->{$fieldName},
                    'value' => $this->mainNode->passengers->{$fieldName},
                ];
            }
        }

        if ($this->itemType == 'cboat') {

        }

        // sailing boat
        if ($this->advert->boatData->general->boatTypeId == 1) {
            $this->help_value_with_unit(
                $this->mainNode->sailing, 'ballast', $this->apiTranslate->advert->boatData->sailing);
            $this->help_measure_detail(
                $this->mainNode->sailing, 'mastHeight', $this->apiTranslate->advert->boatData->sailing);
            $this->help_measure_detail(
                $this->mainNode->sailing, 'sailArea', $this->apiTranslate->advert->boatData->sailing);
            $this->help_measure_detail(
                $this->mainNode->sailing, 'mainsailArea', $this->apiTranslate->advert->boatData->sailing);
            $this->help_measure_detail(
                $this->mainNode->sailing, 'jibArea', $this->apiTranslate->advert->boatData->sailing);
            $this->help_measure_detail(
                $this->mainNode->sailing, 'genoaArea', $this->apiTranslate->advert->boatData->sailing);
            $this->help_measure_detail(
                $this->mainNode->sailing, 'gennakerArea', $this->apiTranslate->advert->boatData->sailing);
            $this->help_measure_detail(
                $this->mainNode->sailing, 'spinnakerArea', $this->apiTranslate->advert->boatData->sailing);
        }

        // engines
        $this->output->engines = [];

        // boat has no engine
        if (!empty($this->mainNode->drive->noEngine)) {
            $this->mainDataBlock['engine'] = [
                'name'  => $this->apiTranslate->advert->engines->words->engine,
                'value' => $this->mainNode->drive->noEngine,
            ];

        // list single engines
        } elseif ($this->advert->engines) {
            foreach ($this->advert->engines as $engineNode) {
                $this->output->engines[] = $this->get_engine_data($engineNode);
            }
        }

        // equipment
        $fields = ['navigation', 'technics', 'deck', 'interior', 'entertainment', 'safety', 'persennings', 'sails', 'layout'];
        $this->output->equipment = [];
        foreach ($fields as $fieldName) {
            if ($this->advert->boatEquipment->{$fieldName}) {
                $this->output->equipment[$fieldName] = [
                    'name'  => $this->apiTranslate->advert->boatEquipment->{$fieldName},
                    'value' => $this->advert->boatEquipment->{$fieldName},
                ];
            }
        }
    }

    /**
     * Data for engines for sale (not for boat).
     */
    private function define_engine_data(): void
    {
        if ($this->pageType == 'list') {
            if ($this->mainNode->power->hp->value) {
                $this->output->power = $this->mainNode->power->hp->value . ' '
                    . $this->mainNode->power->hp->unit . ' ('
                    . $this->mainNode->power->kw->value . ' '
                    . $this->mainNode->power->kw->unit . ')';
            }
            $this->output->yearBuilt    = $this->mainNode->general->yearBuilt;
        }

        if ($this->pageType == 'list') {
            return;
        }

        $this->mainDataBlock['itemId'] = [
            'name'  => $this->apiTranslate->advert->managing->advertId,
            'value' => $this->advert->managing->itemId,
        ];

        $arrTmp = $this->get_engine_data($this->advert->engineData);
        $this->mainDataBlock = array_merge($this->mainDataBlock, $arrTmp);
    }

    /**
     * Cata for boat trailers for sale.
     */
    private function define_trailer_data(): void
    {
        if ($this->pageType == 'list') {
            $this->output->yearBuilt = $this->mainNode->general->yearBuilt;
            $this->output->braked    = $this->mainNode->general->braked;

            if ($this->pageLayout == 'list') {
                $this->help_measure($this->mainNode->metresFeet, 'length');
                $this->help_measure($this->mainNode->metresFeet, 'width');

            } else {
                $this->output->length = [];
                if (!empty($this->mainNode->metresFeet->length->m->value)) {
                    $this->output->length['value'] = $this->mainNode->metresFeet->length->m->value;
                    $this->output->length['unit'] = $this->mainNode->metresFeet->length->m->unit;
                }
                if (!empty($this->mainNode->metresFeet->width->m->value)) {
                    $this->output->width['value'] = $this->mainNode->metresFeet->width->m->value;
                    $this->output->width['unit'] = $this->mainNode->metresFeet->width->m->unit;
                }
            }
        }

        if ($this->pageType == 'list') {
            return;
        }

        $this->mainDataBlock['itemId'] = [
            'name'  => $this->apiTranslate->advert->managing->advertId,
            'value' => $this->advert->managing->itemId,
        ];

        $singleFields = $this->get_single_fields();

        foreach ($singleFields as $nodeName => $subData) {
            foreach($subData as $fieldName) {
                if (!empty($this->mainNode->{$nodeName}->{$fieldName})) {
                    $this->mainDataBlock[$fieldName] = [
                        'name'  => $this->apiTranslate->advert->trailerData->{$nodeName}->{$fieldName},
                        'value' => $this->mainNode->{$nodeName}->{$fieldName},
                    ];
                }
            }
        }

        $this->help_measure_detail(
            $this->mainNode->metresFeet, 'length', $this->apiTranslate->advert->trailerData->metresFeet);
        $this->help_measure_detail(
            $this->mainNode->metresFeet, 'width', $this->apiTranslate->advert->trailerData->metresFeet);
        $this->help_measure_detail(
            $this->mainNode->metresFeet, 'axleWidth', $this->apiTranslate->advert->trailerData->metresFeet);

        $this->help_value_with_unit(
            $this->mainNode->fieldsUnit, 'weightEmpty', $this->apiTranslate->advert->trailerData->fieldsUnit);
        $this->help_value_with_unit(
            $this->mainNode->fieldsUnit, 'maxLoad', $this->apiTranslate->advert->trailerData->fieldsUnit);
        $this->help_value_with_unit(
            $this->mainNode->fieldsUnit, 'weightTotal', $this->apiTranslate->advert->trailerData->fieldsUnit);
        $this->help_value_with_unit(
            $this->mainNode->fieldsUnit, 'maxSpeed', $this->apiTranslate->advert->trailerData->fieldsUnit);

        $this->output->trailerEquipment = $this->mainNode->equipment;
    }

    /*
     * Sale information for all item types exept for charter boats
     */
    private function define_sale_data(): void
    {
        // trim() necessary - articles without price have only amount=price on request, no currency
        $this->output->price = trim($this->advert->saleData->price->currencySign . ' '
            . $this->advert->saleData->price->amount);

        if ($this->advert->saleData->price->amountOld) {
            $this->output->priceOld = $this->advert->saleData->price->currencySign . ' '
                . $this->advert->saleData->price->amountOld;
        } else {
            $this->output->priceOld = '';
        }
        if ($this->advert->saleData->price->currencySign != '€' && $this->advert->saleData->price->amountEuro) {
            $this->output->priceEuro = '€ ' . $this->advert->saleData->price->amountEuro;
        } else {
            $this->output->priceEuro = '';
        }

        $this->output->articleClass = $this->advert->saleData->articleClass;

        // where the advert is lying (country, US state, town)
        $this->output->lying = $this->advert->saleData->lying->country;

        if ($this->pageType == 'detail' || $this->pageLayout == 'list') {
            if ($this->advert->saleData->lying->province) {
                if ($this->output->lying) {
                    $this->output->lying .= ', ';
                }
                $this->output->lying .= $this->advert->saleData->lying->province;
            }
            if ($this->advert->saleData->lying->point) {
                if ($this->output->lying) {
                    $this->output->lying .= ', ';
                }
                $this->output->lying .= $this->advert->saleData->lying->point;
            }
        }

        $this->output->vatPaid = $this->advert->saleData->vatPaid;

        if ($this->itemType == 'sboat') {
            $this->output->auction = $this->advert->saleData->auction;
            $this->output->sharing = $this->advert->saleData->sharing;
        }

        if ($this->pageType == 'list') {
            return;
        }

        // $this->output->countryIso = $this->advert->saleData->lying->countryIso;
        $this->mainDataBlock['lying'] = [
            'name' => $this->apiTranslate->advert->saleData->lying,
            'value' => $this->output->lying,
        ];

        $addToPrice = [];
        if ($this->itemType == 'sboat') {
            if ($this->advert->saleData->auction) {
                $addToPrice[] = '<b class="yachtino-color-red">' . $this->advert->saleData->auction . '</b>';
            }
            if ($this->advert->saleData->sharing) {
                $addToPrice[] = $this->advert->saleData->sharing;
            }
        }
        if ($this->advert->saleData->vatPaid) {
            $addToPrice[] = $this->advert->saleData->vatPaid;
        }
        if ($this->advert->saleData->vatAreas) {
            $addToPrice[] = $this->apiTranslate->advert->saleData->vatAreas . ': '
                . $this->advert->saleData->vatAreas;
        }
        if ($this->advert->saleData->priceNegotiable) {
            $addToPrice[] = $this->advert->saleData->priceNegotiable;
        }
        if ($this->advert->saleData->vatStated) {
            $addToPrice[] = $this->advert->saleData->vatStated;
        }
        if ($addToPrice) {
            $this->output->addToPrice = implode(', ', $addToPrice);
        }

        $singleFields = ['articleClass', 'plusBrokerFee', 'tradeIn', 'underOffer', 'condition', 'previousOwners',
            'wasInCharter', 'expectedDelivery'];

        foreach ($singleFields as $fieldName) {

            // not for all item types therefor check isset/empty
            if (!empty($this->advert->saleData->{$fieldName})) {
                $this->mainDataBlock[$fieldName] = [
                    'name'  => $this->apiTranslate->advert->saleData->{$fieldName},
                    'value' => $this->advert->saleData->{$fieldName},
                ];
            }
        }
    }

    /*
     * Special information for charter boats.
     */
    private function define_charter_data(): void
    {
        // countries
        if ($this->pageType == 'list') {
            if ($this->pageLayout == 'tiles') {
                $maxHits = 1;
            } else {
                $maxHits = 2;
            }
        } else {
            $maxHits = 9999;
        }
        $this->output->countries = '';
        if ($this->advert->charterData->areas->countries) {
            $counter = 0;
            foreach ($this->advert->charterData->areas->countries as $item) {
                $counter++;

                if ($counter <= $maxHits) {
                    $this->output->countries .= ', ' . $item->name;
                } elseif ($maxHits != 1 && $counter == ($maxHits + 1)) {
                    $this->output->countries .= '...';
                    break;
                }
            }
            if ($this->output->countries) {
                $this->output->countries = mb_substr($this->output->countries, 2);
            }
        }

        // waterways
        if ($this->pageType == 'list') {
            if ($this->pageLayout == 'tiles') {
                $maxHits = 1;
            } else {
                $maxHits = 3;
            }
        } else {
            $maxHits = 9999;
        }
        $this->output->waterways = '';
        if ($this->advert->charterData->areas->waterways) {
            $counter = 0;
            foreach ($this->advert->charterData->areas->waterways as $item) {

                // a boat can have eg. "Seychelles" as country and than "Seychelles" as waterway
                // avoid duplicate names
                if ($this->pageType == 'list' && $this->pageLayout == 'tiles') {
                    if ($item->name == $this->output->countries) {
                        continue;

                    } else {
                        $this->output->waterways = ', ' . $item->name;
                        break;
                    }

                } else {
                    $counter++;

                    if ($counter <= $maxHits) {
                        $this->output->waterways .= ', ' . $item->name;
                    } elseif ($maxHits != 1 && $counter == ($maxHits + 1)) {
                        $this->output->waterways .= '...';
                        break;
                    }
                }
            }
            if ($this->output->waterways) {
                $this->output->waterways = mb_substr($this->output->waterways, 2);
            }
        }

        if ($this->pageType == 'detail' || $this->pageLayout == 'list' || !$this->output->waterways) {

            // ports - it can be max. 3 ports
            $this->output->ports = '';
            if ($this->advert->charterData->areas->ports) {
                foreach ($this->advert->charterData->areas->ports as $item) {
                    $this->output->ports .= ', ' . $item->name;
                    if ($this->pageType == 'list' && $this->pageLayout == 'tiles') {
                        break;
                    }
                }
                if ($this->output->ports) {
                    $this->output->ports = mb_substr($this->output->ports, 2);
                }
            }
        }

        // Prices - for detail view see below.
        if ($this->pageType == 'list') {
            $this->output->prices = [];
            foreach ($this->priceTypes as $priceType) {
                if (empty($this->advert->charterData->prices->{$priceType}->price->min)) {
                    continue;
                }
                $this->output->prices[$priceType] = $this->advert->charterData->currencySign
                    . ' ' . $this->advert->charterData->prices->{$priceType}->price->min;

                if ($this->pageLayout == 'list') {
                    if ($this->advert->charterData->prices->{$priceType}->price->min
                    != $this->advert->charterData->prices->{$priceType}->price->max) {
                        $this->output->prices[$priceType] .= ' - ' . $this->advert->charterData->prices->{$priceType}->price->max;
                    }
                }
            }

            // Is a discount possible?
            if ($this->advert->charterData->discounts->hasAny) {
                $this->output->discountPossible = $this->apiTranslate->advert->charterData->discountPossible;
            }

            if ($this->pageLayout == 'list') {
                $this->output->charterType = $this->advert->charterData->charterType;
            }
        }

        if ($this->pageType == 'list') {
            return;
        }

        $this->output->priceTable = [];

        $fields = ['charterType', 'crewMembers', 'checkInDays', 'damageDeposit', 'petsPrice'];
        foreach ($fields as $fieldName) {
            if ($this->advert->charterData->{$fieldName}) {

                // damage deposit can also be "no deposit"
                if (($fieldName == 'damageDeposit' && preg_match('/[0-9]/', $this->advert->charterData->{$fieldName}))
                || $fieldName == 'petsPrice') {
                    $value = $this->advert->charterData->currencySign
                        . ' ' . $this->advert->charterData->{$fieldName};
                } else {
                    $value = $this->advert->charterData->{$fieldName};
                }
                $this->output->priceTable[] = [
                    'name'  => $this->apiTranslate->advert->charterData->{$fieldName},
                    'value' => $value,
                ];
            }
        }

        // prices
        foreach ($this->priceTypes as $priceType) {
            if (!empty($this->advert->charterData->prices->{$priceType}->price->min)) {
                $price = $this->advert->charterData->currencySign
                    . ' ' . $this->advert->charterData->prices->{$priceType}->price->min;
                if ($this->advert->charterData->prices->{$priceType}->price->min
                != $this->advert->charterData->prices->{$priceType}->price->max) {
                    $price .= ' - ' . $this->advert->charterData->prices->{$priceType}->price->max;
                }
                $this->output->priceTable[] = [
                    'name'  => $this->apiTranslate->advert->charterData->prices->{$priceType},
                    'value' => $price,
                ];
            }
        }

        // extra charged
        $this->output->extraCharged = [];
        if ($this->advert->charterData->extraCharged) {
            foreach ($this->advert->charterData->extraCharged as $extra) {
                $this->output->extraCharged[] = [
                    'name'  => $extra->name,
                    'value' => $extra->value,
                ];
            }
            // other ordering than on Happycharter.com (SEO)
            $this->output->extraCharged = array_reverse($this->output->extraCharged);
        }

        // seasonal prices
        $this->output->seasonalPrices = [];
        foreach ($this->priceTypes as $priceType) {
            if (empty($this->advert->charterData->prices->{$priceType}->seasons)) {
                continue;
            }
            $this->output->seasonalPrices[$priceType] = [
                'name' => str_replace('XXX_curr', $this->advert->charterData->currencyIso, $this->apiTranslate->advert->charterData->{'pricesInCurr_' . $priceType}),
                'prices' => [],
            ];
            foreach ($this->advert->charterData->prices->{$priceType}->seasons as $season) {
                $this->output->seasonalPrices[$priceType]['prices'][] = [
                    'from'   => $season->dateFrom,
                    'to'     => $season->dateTo,
                    'amount' => $season->amount,
                ];
            }
        }

        // possibilities
        $this->output->possibilities = $this->advert->charterData->possibilities;

        // additional to charter is possible
        $this->output->additional = $this->advert->charterData->additional;

        // discounts (last minutes, early booking)
        $this->output->discounts = [];
        foreach ($this->priceTypes as $priceType) {
            if (empty($this->advert->charterData->discounts->lastAndEarly->{$priceType})) {
                continue;
            }
            $this->output->discounts[$priceType] = [
                'name' => $this->apiTranslate->advert->charterData->prices->{$priceType},
                'discs' => [],
            ];
            foreach ($this->advert->charterData->discounts->lastAndEarly->{$priceType} as $disc) {
                $discount = $disc->charterFrom . ' - ' . $disc->charterTo;
                if ($disc->bookingTo) {
                    $discount .= ' (' . $this->apiTranslate->advert->charterData->discounts->bookingTo
                        . ' ' . $disc->bookingTo . ')';
                }
                $discount .= ' <s>' . $disc->priceOrigin . '</s> <b>' . $disc->priceNew . '</b> '
                    . $this->advert->charterData->currencySign;
                $this->output->discounts[$priceType]['discs'][] = $discount;
            }
        }
    }

    /**
     * Description / marketing texts for a boat/trailer/engine.
     */
    private function define_texts(): void
    {
        if ($this->pageType == 'list') {
            // if ($this->itemType == 'cboat') {
                // $this->output->textList = nl2br($this->advert->charterData->descriptionList);
            // } else {
                // $this->output->textList = nl2br($this->advert->saleData->descriptionList);
            // }
            return;
        }

        // detail view
        if ($this->itemType == 'cboat') {
            $textDetail = $this->advert->charterData->descriptionView;
        } else {
            $textDetail = $this->advert->saleData->descriptionView;
        }
        if ($textDetail) {
            $arrTmp = Yachtino_Library::splitText($textDetail, 650, 8);
            $this->output->textsDetail = [
                'part1' => nl2br($arrTmp['part1']),
                'part2' => '',
            ];
            if ($arrTmp['part2']) {
                $this->output->textsDetail['part2'] = nl2br($arrTmp['part2']);
            }
        }

        if ($this->entityType != 'boat') {
            return;
        }

        if ($this->itemType == 'cboat') {
            $textDetail = $this->advert->charterData->descriptionEquip;
        } elseif ($this->itemType == 'sboat') {
            $textDetail = $this->advert->saleData->descriptionEquip;
        }
        if ($textDetail) {
            $this->output->textEquip = nl2br($textDetail);
        }
    }

    /*
     * Engine data for boat (charter, for sale) AND for engines for sale.
     */
    private function get_engine_data(object $engineNode): array
    {
        $output = [];

        $singleFields = ['model', 'fuel', 'yearBuilt', 'engineLocation', 'hours', 'cylinders',
            'strokes', 'starterType', 'serialNumber', 'auxiliary',];

        if ($this->itemType == 'engine') {
            $singleFields[] = 'manufacturer';
            $singleFields[] = 'propulsion';
        }

        foreach ($singleFields as $fieldName) {
            if (!empty($engineNode->general->{$fieldName})) {
                $output[$fieldName] = [
                    'name'  => $this->apiTranslate->advert->engines->general->{$fieldName},
                    'value' => $engineNode->general->{$fieldName},
                ];
            }
        }

        if ($engineNode->power->hp->value) {
            $output['power'] = [
                'name'  => $this->apiTranslate->advert->engines->power,
                'value' => $engineNode->power->hp->value . ' '
                    . $engineNode->power->hp->unit . ' (' . $engineNode->power->kw->value . ' '
                    . $engineNode->power->kw->unit . ')',
            ];
        }

        $singleFields = ['capacity'];
        if ($this->itemType == 'engine') {
            $singleFields[] = 'weight';
        }
        foreach ($singleFields as $fieldName) {
            if (!empty($engineNode->fieldsUnit->{$fieldName}->value)) {
                $output[$fieldName] = [
                    'name'  => $this->apiTranslate->advert->engines->fieldsUnit->{$fieldName},
                    'value' => $engineNode->fieldsUnit->{$fieldName}->value . ' '
                        . $engineNode->fieldsUnit->{$fieldName}->unit,
                ];
            }
        }
        return $output;
    }

    private function define_media(): void
    {
        $this->output->pics      = [];
        $this->output->picsThumb = [];
        $this->output->picsMax   = [];
        if ($this->advert->pictures->pics) {
            $counter = 0;
            foreach ($this->advert->pictures->pics as $pic) {
                $this->output->pics[$counter] = [
                    'url' => $pic->url,
                ];
                $this->output->picsThumb[$counter] = [
                    'url' => str_replace('huge-', 'thumb-', $pic->url),
                ];
                $this->output->picsMax[$counter] = [
                    'url' => $pic->url,
                ];
                if (!empty($pic->link)) {
                    $this->output->picsMax[$counter]['link'] = $pic->link;
                }
                $counter++;
            }
        }

        $this->output->videos = [];
        if ($this->advert->videos) {
            $counter = 0;
            foreach ($this->advert->videos as $video) {
                if (preg_match('/youtube(.*)?\/embed\/([^\/]+)\?/u', $video->url, $m)) {
                    $this->output->videos[$counter] = [
                        'image'  => 'https://img.youtube.com/vi/' . $m[2] . '/maxresdefault.jpg',
                        'id'     => $m[2],
                        // 'width'  => $video['width'],
                        // 'height' => $video['height'],
                    ];
                    $counter++;
                }
            }
        }

        $this->output->pdfs = [];
        if ($this->advert->pdfs) {
            $counter = 0;
            foreach ($this->advert->pdfs as $pdf) {
                $this->output->pdfs[$counter] = [
                    'url'    => $pdf->url,
                ];
                $counter++;
            }
        }
    }

    public static function get_search_form(array $searchFormFields, object $apiResponse): StdClass
    {
        $output = new StdClass();
        $output->fields = [];

        foreach ($searchFormFields as $fieldName) {
            $output->fields[$fieldName] = [
                'label' => $apiResponse->translation->search->{$fieldName},
            ];

            if (!empty($apiResponse->selects->{$fieldName})) {
                $output->fields[$fieldName]['options'] = $apiResponse->selects->{$fieldName};
            } else {
                $output->fields[$fieldName]['options'] = [];
            }

            if ($fieldName == 'q') {
                $output->fields[$fieldName]['default'] = $apiResponse->translation->search->qDefault;

            } elseif ($fieldName == 'lng' || $fieldName == 'yb' || $fieldName == 'pow' || $fieldName == 'cab'
            || $fieldName == 'sprc' || $fieldName == 'wkprc' || $fieldName == 'daprc' || $fieldName == 'hrprc') {
                $output->fields[$fieldName]['double'] = true;
                $output->fields[$fieldName]['from'] = $apiResponse->translation->words->from;
                $output->fields[$fieldName]['to'] = $apiResponse->translation->words->to;
            }
        }
        $output->translation['searchHead'] = $apiResponse->translation->search->SearchNoun;
        $output->translation['searchButton'] = $apiResponse->translation->search->Search;
        $output->translation['reset'] = $apiResponse->translation->words->resetButton;
        $output->translation['unit_m'] = $apiResponse->translation->words->m;

        return $output;
    }

    private function define_added_translation(): void
    {
        if (!isset($this->output->translation)) {
            $this->output->translation = [];
        }

        if ($this->itemType != 'cboat') {
            $this->output->translation['Price'] = $this->apiTranslate->advert->saleData->price;
        }

        if ($this->pageType == 'list') {
            return;
        }

        $this->output->translation['Video'] = $this->apiTranslate->advert->words->Video;
        $this->output->translation['PDF'] = $this->apiTranslate->advert->words->PDF;
        $this->output->translation['OtherInformation'] = $this->apiTranslate->advert->words->OtherInformation;
        $this->output->translation['wholeText'] = $this->apiTranslate->advert->words->wholeText;
        $this->output->translation['lessText'] = $this->apiTranslate->advert->words->lessText;

        if ($this->itemType == 'cboat' || $this->itemType == 'sboat') {
            $this->output->translation['TechnicalData'] = $this->apiTranslate->advert->words->TechnicalData;
            $this->output->translation['Equipment'] = $this->apiTranslate->advert->words->Equipment;
            $this->output->translation['Engine'] = $this->apiTranslate->advert->engines->words->engine;
        }

        if ($this->itemType == 'cboat') {
            $this->output->translation['Prices'] = $this->apiTranslate->advert->charterData->pricesWord;
            $this->output->translation['Countries'] = $this->apiTranslate->advert->charterData->areas->countries;
            $this->output->translation['Waterways'] = $this->apiTranslate->advert->charterData->areas->waterways;
            $this->output->translation['Ports'] = $this->apiTranslate->advert->charterData->areas->ports;
            $this->output->translation['Possibilities'] = $this->apiTranslate->advert->charterData->possibilities;
            $this->output->translation['Additional'] = $this->apiTranslate->advert->charterData->additional;
            $this->output->translation['SeasonalPrices'] = $this->apiTranslate->advert->charterData->seasonPrices;
            $this->output->translation['toPrice'] = $this->apiTranslate->words->to;
            $this->output->translation['DiscountsOffers'] = $this->apiTranslate->advert->charterData->DiscountsOffers;
            $this->output->translation['ExtraCharged'] = $this->apiTranslate->advert->charterData->extraCharged;
            $this->output->translation['discountsConstraint'] = $this->apiTranslate->advert->charterData->discounts->discountsConstraint;

        } elseif ($this->itemType == 'trailer') {
            $this->output->translation['Equipment'] = $this->apiTranslate->advert->trailerData->equipment;
        }
    }

    /*
     * All fields are alredy in an array; now we create single lines for the list as tiles (not rows).
     */
    private function prepare_output_tiles(): void
    {
        $this->output->singleItemUrl = str_replace('([a-z0-9]+)', $this->output->itemId, $this->singleItemUrl);

        $this->output->singlePoints = [];

        if ($this->itemType == 'cboat' || $this->itemType == 'sboat') {
            $dataNodeName = 'boatData';
        } else {
            $dataNodeName = $this->itemType . 'Data';
        }

        if ($this->itemType != 'mooring') {

            // $this->output->singlePoints['yearBuilt'] = $this->apiTranslate->advert->{$dataNodeName}->general->yearBuiltShort . ': ';
            if (!empty($this->output->yearBuilt)) {
                $this->output->singlePoints['yearBuilt'] = $this->output->yearBuilt;
            } elseif (!empty($this->output->articleClass)) {
                $this->output->singlePoints['yearBuilt'] = $this->output->articleClass;
            } else {
                $this->output->singlePoints['yearBuilt'] = '-';
            }
        }

        if ($this->itemType == 'cboat' || $this->itemType == 'sboat') {

            if (!empty($this->output->loa)) {
                $this->output->singlePoints['length'] = $this->output->loa['value'];

                if (!empty($this->output->beam)) {
                    $this->output->singlePoints['length'] .= ' x ' . $this->output->beam['value'];
                }
                if (!empty($this->output->draught)) {
                    $this->output->singlePoints['length'] .= ' x ' . $this->output->draught['value'];
                }
                $this->output->singlePoints['length'] .= ' ' . $this->output->loa['unit'];

            } else {
                $this->output->singlePoints['length'] = '-';
            }

        } elseif ($this->itemType == 'trailer' || $this->itemType == 'mooring') {
            if (!empty($this->output->length)) {
                $this->output->singlePoints['length'] = $this->output->length['value'];

                if (!empty($this->output->width)) {
                    $this->output->singlePoints['length'] .= ' x ' . $this->output->width['value'];
                }
                $this->output->singlePoints['length'] .= ' ' . $this->output->length['unit'];

            } else {
                $this->output->singlePoints['length'] = '-';
            }
        }

        if ($this->itemType == 'sboat') {
            $this->output->singlePoints['specLine'] = $this->output->boatType;

            if (!empty($this->output->engineString)) {
                $p = explode(', ', $this->output->engineString);
                $this->output->singlePoints['specLine'] .= ', ' . $p[0];

                // hp number
                if (isset($p[1])) {
                    $eTmp = $this->output->singlePoints['specLine'] . ', ' . $p[1];
                    $lng = mb_strlen($eTmp);
                    if ($lng <= 39) {
                        $this->output->singlePoints['specLine'] = $eTmp;

                    } else {
                        $hp = preg_replace('/\([0-9,\.]+ (kw|кВт)\)/ui', '', $p[1]);
                        $eTmp = $this->output->singlePoints['specLine'] . ', ' . $hp;
                        $lng = mb_strlen($eTmp);
                        if ($lng <= 39) {
                            $this->output->singlePoints['specLine'] = $eTmp;
                        }
                    }
                }
            }

        } elseif ($this->itemType == 'engine') {
            if (!empty($this->output->power)) {
                $this->output->singlePoints['power'] = $this->output->power;
            } else {
                $this->output->singlePoints['power'] = '';
            }
        }

        if ($this->itemType == 'cboat') {

            if ($this->output->allCabins !== '') {
                $this->output->singlePoints['allCabins'] = $this->apiTranslate->advert->boatData->passengers->allCabins
                    . ': ' . $this->output->allCabins;
            } else {
                $this->output->singlePoints['allCabins'] = '';
            }

            $this->output->singlePoints['lying'] = '';

            if ($this->output->countries) {
                $this->output->singlePoints['lying'] = $this->output->countries;
            }

            if ($this->output->waterways) {
                if ($this->output->singlePoints['lying']) {
                    $this->output->singlePoints['lying'] .= ', ';
                }
                $this->output->singlePoints['lying'] .= $this->output->waterways;

            } elseif ($this->output->ports) {
                if ($this->output->singlePoints['lying']) {
                    $this->output->singlePoints['lying'] .= ', ';
                }
                $this->output->singlePoints['lying'] .= $this->output->ports;
            }

            if (!$this->output->singlePoints['lying']) {
                $this->output->singlePoints['lying'] = '-';
            }

            if ($this->moduleData['criteria']) {
                foreach ($this->pricesAsCriteria as $nameCrit => $nameDb) {
                    if ((isset($this->moduleData['criteria'][$nameCrit . 'f'])
                    || isset($this->moduleData['criteria'][$nameCrit . 't'])) && !empty($this->output->prices[$nameDb])) {

                        $this->output->singlePoints['price'] = $this->apiTranslate->words->fromSingle
                            . ' ' . $this->output->prices[$nameDb]
                            . ' ' . $this->apiTranslate->advert->charterData->prices->{$nameDb};
                        break;
                    }
                }
            }
            if (empty($this->output->singlePoints['price'])) {
                foreach ($this->output->prices as $priceType => $price) {
                    $this->output->singlePoints['price'] = $this->apiTranslate->words->fromSingle
                        . ' <b>' . $price
                        . '</b> ' . $this->apiTranslate->advert->charterData->prices->{$priceType};
                    break;
                }
            }

            // discount
            if (!empty($this->output->discountPossible)) {
                $this->output->singlePoints['discount'] = '<span class="yachtino-list-discount">' . $this->output->discountPossible . '</span>';
            } else {
                $this->output->singlePoints['discount'] = '';
            }

            if (empty($this->output->singlePoints['price'])) {
                $this->output->singlePoints['price'] = '-';
            }

        } else {
            if ($this->output->lying) {
                $this->output->singlePoints['lying'] = $this->output->lying;
            } else {
                $this->output->singlePoints['lying'] = '-';
            }
        }
    }

    /*
     * All fields are alredy in an array; now we create single lines for the list.
     */
    private function prepare_output_list(): void
    {
        $this->output->singleItemUrl = str_replace('([a-z0-9]+)', $this->output->itemId, $this->singleItemUrl);

        $this->output->singleLines = [];

        if ($this->itemType == 'sboat') {
            $line = $this->output->boatType;
            if (!empty($this->output->category)) {
                $line .= ' / ' . $this->output->category;
            }
            if (!empty($this->output->articleClass)) {
                $line .= ', ' . $this->output->articleClass;
            }
            if (!empty($this->output->hullMaterial)) {
                $line .= ', ' . $this->output->hullMaterial;
            }
            $this->output->singleLines[] = $line;
        }

        if ($this->entityType == 'boat') {
            $lines = [];

            if (!empty($this->output->loa['m'])) {
                $lines[] = $this->apiTranslate->advert->boatData->measures->loa
                    . ':&nbsp;' . $this->output->loa['m'] . ' (' . str_replace(' ', '&nbsp;', $this->output->loa['ft']) . ')';
            }
            if (!empty($this->output->beam['m'])) {
                $lines[] = $this->apiTranslate->advert->boatData->measures->beam
                    . ':&nbsp;' . str_replace(' ', '&nbsp;', $this->output->beam['m']);
            }
            if (!empty($this->output->draught['m'])) {
                $lines[] = $this->apiTranslate->advert->boatData->measures->draught
                    . ':&nbsp;' . str_replace(' ', '&nbsp;', $this->output->draught['m']); // draught from - to
            }

            if ($this->itemType == 'cboat') {
                if ($this->output->allCabins !== '') {
                    $lines[] = $this->apiTranslate->advert->boatData->passengers->allCabins
                        . ':&nbsp;' . $this->output->allCabins;
                }
                if (!empty($this->output->yearBuilt)) {
                    $lines[] = $this->apiTranslate->advert->boatData->general->yearBuiltShort
                        . ':&nbsp;' . str_replace(' ', '&nbsp;', $this->output->yearBuilt); // before 1985
                }
            } else {
                if (!empty($this->output->yearBuilt)) {
                    $lines[] = $this->apiTranslate->advert->boatData->general->yearBuiltShort
                        . ':&nbsp;' . str_replace(' ', '&nbsp;', $this->output->yearBuilt);
                }
                if ($this->output->allCabins !== '') {
                    $lines[] = $this->apiTranslate->advert->boatData->passengers->allCabins
                        . ':&nbsp;' . $this->output->allCabins;
                }
            }

            if ($lines) {
                $this->output->singleLines[] = implode(', ', $lines);
            }
        }

        if ($this->itemType == 'sboat') {
            if (!empty($this->output->engineString)) {
                $this->output->singleLines[] = $this->apiTranslate->advert->engines->words->engine . ': '
                    . $this->output->engineString;
            }

        } elseif ($this->itemType == 'cboat') {
            $lines = [];
            if ($this->output->ports) {
                $lines[] = $this->apiTranslate->advert->charterData->areas->ports . ': '
                    . $this->output->ports;
            }
            if ($this->output->countries) {
                $lines[] = $this->apiTranslate->advert->charterData->areas->countries . ': '
                    . $this->output->countries;
            }
            if ($lines) {
                $this->output->singleLines[] = implode(', ', $lines);
            }

            if ($this->output->waterways) {
                $this->output->singleLines[] = $this->apiTranslate->advert->charterData->areas->waterways . ': '
                    . $this->output->waterways;
            }

            // boat type + with skipper
            $lines = [];
            $type = $this->output->boatType;
            if (!empty($this->output->category)) {
                $type .= ' / ' . $this->output->category;
            }
            if ($type) {
                $lines[] = $type;
            }
            if ($this->output->charterType) {
                $lines[] = $this->output->charterType;
            }
            if ($lines) {
                $this->output->singleLines[] = implode(', ', $lines);
            }

            // line with prices and discounts
            $lines = [];

            // discount
            if (!empty($this->output->discountPossible)) {
                $lines[] = '<span class="yachtino-list-discount">' . $this->output->discountPossible . '</span>';
            }

            // charter prices
            // $prices = '';
            $counter = 0;

            if ($this->moduleData['criteria']) {
                foreach ($this->pricesAsCriteria as $nameCrit => $nameDb) {
                    if ((isset($this->moduleData['criteria'][$nameCrit . 'f'])
                    || isset($this->moduleData['criteria'][$nameCrit . 't'])) && !empty($this->output->prices[$nameDb])) {

                        $counter++;
                        $lines[] =  '<b>' . $this->apiTranslate->advert->charterData->prices->{$nameDb} . ': </b>'
                            . '<span class="yachtino-nowrap">' . $this->output->prices[$nameDb] . '</span>';

                        unset($this->output->prices[$nameDb]);
                        if ($counter >= 2) {
                            break;
                        }
                    }
                }
            }

            if ($counter < 2) {
                foreach ($this->output->prices as $priceType => $price) {
                    $counter++;
                    $lines[] = '<b>' . $this->apiTranslate->advert->charterData->prices->{$priceType} . ': </b>'
                        . '<span class="yachtino-nowrap">' . $price . '</span>';
                    if ($counter >= 2) {
                        break;
                    }
                }
            }
            if ($lines) {
                $this->output->singleLines[] = implode(', ', $lines);
            }
        }

        if ($this->itemType == 'engine') {
            $lines = [];
            if (!empty($this->output->articleClass)) {
                $lines[] = $this->output->articleClass;
            }
            if (!empty($this->output->power)) {
                $lines[] = $this->apiTranslate->advert->engines->power . ': '
                    . $this->output->power;
            }
            if (!empty($this->output->yearBuilt)) {
                $lines[] = $this->apiTranslate->advert->engines->general->yearBuiltShort . ': '
                    . $this->output->yearBuilt;
            }
            if ($lines) {
                $this->output->singleLines[] = implode(', ', $lines);
            }

        } elseif ($this->itemType == 'trailer') {
            $lines = [];
            if (!empty($this->output->articleClass)) {
                $lines[] = $this->output->articleClass;
            }
            if (!empty($this->output->braked)) {
                $lines[] = $this->output->braked;
            }
            if ($lines) {
                $this->output->singleLines[] = implode(', ', $lines);
            }

            $lines = [];
            if (!empty($this->output->yearBuilt)) {
                $lines[] = $this->apiTranslate->advert->trailerData->general->yearBuiltShort . ': '
                    . $this->output->yearBuilt;
            }
            if (!empty($this->output->length['m'])) {
                $lines[] = $this->apiTranslate->advert->trailerData->metresFeet->length . ': '
                    . $this->output->length['m'];
            }
            if (!empty($this->output->width['m'])) {
                $lines[] = $this->apiTranslate->advert->trailerData->metresFeet->width . ': '
                    . $this->output->width['m'];
            }
            if ($lines) {
                $this->output->singleLines[] = implode(', ', $lines);
            }
        }

        if ($this->itemType != 'cboat') {
            if (!empty($this->output->lying)) {
                $this->output->singleLines[] = $this->apiTranslate->advert->saleData->lying . ': '
                    . $this->output->lying;
            }
        }
    }

    /*
     * All fields are alredy in an array; now we order/sort the fields in a logical content way.
     */
    private function prepare_output_detail(): void
    {
        if ($this->itemType == 'cboat') {
            $orderingFields = [
                'firstBlock' => ['itemId', 'boatType', 'category', 'model', 'name', 'manufacturer', 'designer',
                    'yearBuilt', 'yearRefit', 'yearLaunch', 'flybridge',
                    'hullType', 'hullColour', 'hullMaterial', 'hullId', 'steeringType',
                    'registrationCountry', 'registrationPurpose', 'registrationPort',
                    'keelType', 'mast', 'mastHeight', 'mastMaterial', 'sailArea', 'mainsailArea', 'jibArea', 'genoaArea',
                    'gennakerArea', 'spinnakerArea',
                ],
                'secondBlock' => ['allCabins',
                    'masterCabins', 'guestCabins', 'crewCabins', 'allBerths', 'personsDay', 'insideShowers', 'toilets',
                    'loa', 'beam', 'draught', 'clearance', 'headroom', 'heating',
                    'displacement', 'ballast', 'payload', 'cruisingSpeed', 'cruisingConsumption', 'maxSpeed', 'maxConsumption',
                    'waterTank', 'fuelTank', 'holdingTank', 'propulsion', 'cooling', 'engineRange', 'propellerType',
                    'propellerBrand', 'propellerMaterial', 'propellerBlades', 'engine',
                ],
            ];

        } elseif ($this->itemType == 'sboat') {
            $orderingFields = [
                'firstBlock' => ['underOffer', 'itemId', 'model', 'manufacturer', 'designer', 'name', 'boatType',
                    'category', 'articleClass', 'yearBuilt', 'yearRefit', 'yearLaunch', 'previousOwners',
                    'condition', 'wasInCharter', 'ceCategory', 'flybridge',
                    'hullType', 'hullColour', 'hullMaterial', 'hullId', 'steeringType',
                    'lying', 'registrationCountry', 'registrationPurpose', 'registrationPort',
                    'keelType', 'mast', 'mastHeight', 'mastMaterial', 'sailArea', 'mainsailArea', 'jibArea', 'genoaArea',
                    'gennakerArea', 'spinnakerArea', 'plusBrokerFee', 'tradeIn',
                ],
                'secondBlock' => ['loa', 'beam', 'draught', 'clearance', 'headroom', 'allCabins',
                    'masterCabins', 'guestCabins', 'crewCabins', 'allBerths', 'personsDay', 'insideShowers', 'toilets',
                    'heating',
                    'displacement', 'ballast', 'payload', 'cruisingSpeed', 'cruisingConsumption', 'maxSpeed', 'maxConsumption',
                    'waterTank', 'fuelTank', 'holdingTank', 'propulsion', 'cooling', 'lakeConstance', 'engineRange', 'propellerType',
                    'propellerBrand', 'propellerMaterial', 'propellerBlades', 'engine',
                ],
            ];

        } elseif ($this->itemType == 'engine') {
            $orderingFields = [
                'firstBlock' => ['itemId', 'model', 'manufacturer', 'articleClass', 'engineLocation', 'power', 'fuel', 'propulsion',
                    'yearBuilt', 'condition', 'lying', 'hours', 'capacity', 'cylinders',
                    'strokes', 'starterType', 'serialNumber', 'weight',
                ],
            ];

        } elseif ($this->itemType == 'trailer') {
            $orderingFields = [
                'firstBlock' => ['itemId', 'model', 'manufacturer', 'category', 'articleClass', 'previousOwners', 'braked',
                    'length', 'width', 'axleWidth', 'yearBuilt', 'material',
                    'lying', 'axles', 'wideTrack', 'twinAxle', 'coupling', 'boattypes',
                    'maxLoad', 'weightEmpty', 'weightTotal', 'maxSpeed',
                ],
            ];
        }
        $this->output->firstBlock = [];
        $this->output->secondBlock = [];

        foreach ($orderingFields as $blockName => $subData) {
            $this->output->{$blockName} = [];
            foreach ($subData as $fieldName) {
                if (isset($this->mainDataBlock[$fieldName])) {
                    $this->output->{$blockName}[$fieldName] = $this->mainDataBlock[$fieldName];
                    unset($this->mainDataBlock[$fieldName]);
                }
            }
        }

        // engines for a boat
        if ($this->itemType == 'cboat' || $this->itemType == 'sboat') {
            if ($this->output->engines) {
                $enginesTmp = $this->output->engines;
                $this->output->engines = [];
                $orderingFields = $this->get_ordering_engine_fields_boat();
                foreach ($enginesTmp as $engine) {
                    $dataTmp = [];
                    foreach ($orderingFields as $fieldName) {
                        if (!empty($engine[$fieldName])) {
                            $dataTmp[$fieldName] = $engine[$fieldName];
                        }
                    }
                    $this->output->engines[] = $dataTmp;
                }
                $enginesTmp = [];
            }
        }
    }

// ############ help methods ###################################################

    private function help_measure(object $node, string $fieldName): void
    {
        $this->output->{$fieldName} = [
            'm'  => '',
            'ft' => '',
        ];
        if ($node->{$fieldName}->m->value) {
            $this->output->{$fieldName}['m'] = $node->{$fieldName}->m->value . ' '
                . $node->{$fieldName}->m->unit;
            $this->output->{$fieldName}['ft'] = $node->{$fieldName}->ft->value . ' '
                . $node->{$fieldName}->ft->unit;
        }
    }

    private function help_measure_detail(object $node, string $fieldName, object $translate): void
    {
        if ($node->{$fieldName}->m->value) {
            $this->mainDataBlock[$fieldName] = [
                'name' => $translate->{$fieldName},
                'value' => $node->{$fieldName}->m->value . ' '
                    . $node->{$fieldName}->m->unit . ' (' . $node->{$fieldName}->ft->value . ' '
                    . $node->{$fieldName}->ft->unit . ')',
            ];
        }
    }

    private function help_value_with_unit(object $node, string $fieldName, object $translate): void
    {
        if (!empty($node->{$fieldName}->value)) {
            $this->mainDataBlock[$fieldName] = [
                'name' => $translate->{$fieldName},
                'value' => $node->{$fieldName}->value . ' ' . $node->{$fieldName}->unit,
            ];
        }
    }

    /**
     * @param $tankName Possible: water, fuel, holding.
     */
    private function help_tank(string $tankName): void
    {
        $nodeName = $tankName . 'Tank';
        if (!$this->advert->boatData->general->{$nodeName}->volume) {
            return;
        }

        $value = $this->advert->boatData->general->{$nodeName}->volume . ' '
            . $this->advert->boatData->general->{$nodeName}->unit;
        if ($this->advert->boatData->general->{$nodeName}->number >= 2) {
            $value .= ' (' . $this->advert->boatData->general->{$nodeName}->number . '&nbsp;'
                . $this->apiTranslate->advert->boatData->general->tanks . ')';
        }

        if ($this->advert->boatData->general->{$nodeName}->material) {
            $value .= ', ' . $this->advert->boatData->general->{$nodeName}->material;
        }

        $this->mainDataBlock[$nodeName] = [
            'name'  => $this->apiTranslate->advert->boatData->general->{$nodeName},
            'value' => $value,
        ];
    }

    private function get_ordering_engine_fields_boat(): array
    {
        return ['auxiliary', 'engineLocation', 'model', 'power', 'fuel', 'propulsion',
            'yearBuilt', 'hours', 'capacity', 'cylinders',
            'strokes', 'starterType', 'serialNumber', 'weight',
        ];
    }

    private function get_single_fields(): array
    {
        if ($this->itemType == 'cboat' || $this->itemType == 'sboat') {
            $singleFields = [
                'general' => [
                    'boatType', 'name', 'model', 'yearBuilt', 'yearRefit', 'yearLaunch',
                    'designer', 'manufacturer', 'flybridge',
                    'hullType', 'hullColour', 'hullMaterial', 'steeringType', 'heating',
                    'registrationCountry', 'registrationPurpose', 'registrationPort', 'ceCategory', 'hullId',
                ],
                'drive' => [
                    'propulsion', 'propellerType', 'propellerBrand',
                    'propellerBlades', 'propellerMaterial', 'cooling', 'lakeConstance',
                ],
                'passengers' => [
                    'allCabins', 'allBerths', 'personsDay', 'insideShowers', 'toilets',
                ],
                'sailing' => [
                    'keelType', 'mast', 'mastMaterial',
                ],
            ];

        } elseif ($this->itemType == 'trailer') {
            $singleFields = [
                'general' => [
                    'model', 'yearBuilt', 'braked', 'category',
                    'manufacturer', 'material',
                    'axles', 'twinAxle', 'wideTrack', 'coupling', 'boattypes',
                ],
            ];
        }
        return $singleFields;
    }

}