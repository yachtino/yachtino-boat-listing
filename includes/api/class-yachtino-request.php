<?php

declare(strict_types=1);

/**
 * Defines data for sending a request for a boat or trailer; also sends request via Yachtino API
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

if (!defined('ABSPATH')) {
    exit(); // Don't access directly
};

class Yachtino_Request
{
    private StdClass $output;

    /**
     * singleton pattern
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
     * Gets data for contact/request form
     * $itemType - cboat (charter), sboat (for sale), trailer, engine, mooring, gear
     * $apiResults - node from JSON response from Yachtino API
     */
    public function get_form_data(string $lg, string $itemType, StdClass $itemData, object $apiResults): StdClass
    {
        $this->output = new StdClass();

        $this->output->itemId   = $itemData->itemId;
        $this->output->itemType = $itemType;
        $this->output->language = $lg;

        // texts to be displayed
        $this->output->translation = [
            'headline'  => $apiResults->translation->advert->words->ContactData,
            'cname'     => $apiResults->translation->request->cname,
            'cemail'    => $apiResults->translation->request->cemail,
            'cphone'    => $apiResults->translation->request->cphone,
            'caddress'  => $apiResults->translation->request->caddress,
            'cpostcode' => $apiResults->translation->request->cpostcode,
            'ccity'     => $apiResults->translation->request->ccity,
            'ccountry'  => $apiResults->translation->request->ccountry,
            'ctext'     => $apiResults->translation->request->ctext,
            'emailSent' => $apiResults->translation->request->emailSent,
            'sendRequest'    => $apiResults->translation->request->sendRequest,
            'fieldsRequired' => $apiResults->translation->words->fieldsRequired,
        ];
        if ($itemType == 'cboat') {
            $this->output->translation['chTime'] = $apiResults->translation->request->chTime;
            $this->output->translation['datef'] = $apiResults->translation->request->datef;
            $this->output->translation['datet'] = $apiResults->translation->request->datet;
            $this->output->jsDateFormat = Yachtino_Library::javascriptDateFormat($lg);
        }

        // content for selectboxes
        $this->output->editOptions = [];
        $this->output->editOptions['ccountry'] = $apiResults->selects->ccountry;

        // user data - address, logo...
        $this->output->userData = [
            'data' => [],
        ];

        $row = '<b>' . $apiResults->offices->office->userName . '</b>';

        if ($apiResults->offices->office->userName != $apiResults->offices->office->officeName) {
            $row .= '<br /><b>' . $apiResults->offices->office->officeName . '</b>';
        }

        if ($apiResults->offices->office->personSurname) {
            $person = '';
            if ($apiResults->offices->office->personTitle) {
                $person .= $apiResults->offices->office->personTitle . ' ';
            }
            if ($apiResults->offices->office->personFirstname) {
                $person .= $apiResults->offices->office->personFirstname . ' ';
            }
            $person .= $apiResults->offices->office->personSurname;
            $row .= '<br />' . $person;
        }

        if ($apiResults->offices->office->addressLine1) {
            $row .= '<br />' . $apiResults->offices->office->addressLine1;
        }
        if ($apiResults->offices->office->addressLine2) {
            $row .= '<br />' . $apiResults->offices->office->addressLine2;
        }

        $addressLine = '';
        if ($apiResults->offices->office->postcode) {
            $addressLine = $apiResults->offices->office->postcode . ' ';
        }
        if ($apiResults->offices->office->town) {
            $addressLine .= $apiResults->offices->office->town;
        }
        if ($addressLine) {
            $row .= '<br />' . $addressLine;
        }

        $addressLine = '';
        if ($apiResults->offices->office->country) {
            $addressLine = $apiResults->offices->office->country;
        }
        if ($apiResults->offices->office->province) {
            $addressLine .= $apiResults->offices->office->province;
        }
        if ($addressLine) {
            $row .= '<br />' . $addressLine;
        }

        $this->output->userData['data'][] = [
            'name'  => $apiResults->translation->offices->contact,
            'value' => $row,
        ];

        $singleFields = ['phone1', 'phone2', 'mobile', 'fax', 'skype', 'languages'];
        foreach ($singleFields as $fieldName) {
            if ($apiResults->offices->office->{$fieldName}) {
                $this->output->userData['data'][] = [
                    'name'  => $apiResults->translation->offices->{$fieldName},
                    'value' => $apiResults->offices->office->{$fieldName},
                ];
            }
        }

        if ($apiResults->offices->office->web) {
            $this->output->userData['web'] = $apiResults->offices->office->web;
        }

        if ($apiResults->offices->office->logo) {
            $this->output->userData['logo'] = $apiResults->offices->office->logo;
        }

        return $this->output;
    }

    // return type: never, possible as of PHP 8.1
    public function ajax_send_request()
    {
        $arrayResult = [
            'ok' => true,
        ];

        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-library.php';

        $req = filter_input(INPUT_POST, 'req');
        if ($req) {
            $formData = json_decode($req, true);
            $formData = Yachtino_Library::adjustArray($formData, ['stripslashes', 'strip_tags', 'trim',]);

            if (!empty($formData['lg'])) {

                // language variable does not contain language but spam values
                if (!preg_match('/^[a-z]{2}$/', $formData['lg'])) {
                    wp_die('Forbidden', 403);
                }

            } else {
                $locale = determine_locale();
                $formData['lg'] = substr($locale, 0, 2);
            }
            $formData['formSent'] = 1;
            $formData['kind']     = 'request';

        } else {
            $formData = [];
        }

        if (empty($formData['itemtype']) || empty($formData['itid'])) {
            wp_send_json($arrayResult);
            wp_die();
        }
        if (!preg_match('/^[csetmg][0-9]+$/', $formData['itid'])) {
            wp_die('Forbidden', 403);
        }
        if (!in_array($formData['itemtype'], ['cboat', 'sboat', 'mooring', 'trailer', 'engine', 'gear'])) {
            wp_die('Forbidden', 403);
        }

        // send to API
        $urlParts = [
            'language' => $formData['lg'],
            'part1'    => 'request',
            'part2'    => 'send-request',
        ];

        $additionalVars = [
            'itemtype' => $formData['itemtype'],
        ];
        unset($formData['itemtype']);

        $classApi = Yachtino_Api::get_instance();
        $apiResults = $classApi->send_request_to_api($urlParts, $additionalVars, $formData);

        if (!empty($apiResults->ok)) {
            $arrayResult = [
                'ok' => true,
            ];

        } elseif (!empty($apiResults->errors)) {
            $arrayResult = [
                'errors' => [
                    'errMsg'   => [],
                    'errField' => [],
                ],
            ];
            if (!empty($apiResults->errors->errMsg)) {
                foreach ($apiResults->errors->errMsg as $msg) {
                    $arrayResult['errors']['errMsg'][] = $msg;
                }
            }
            if (!empty($apiResults->errors->errField)) {
                foreach ($apiResults->errors->errField as $fieldKey => $fieldTrue) {
                    $arrayResult['errors']['errField'][$fieldKey] = true;
                }
            }
        }

        // Make your array as json
        wp_send_json($arrayResult);

        // Don't forget to stop execution afterward.
        wp_die();
    }

    // return type: never, possible as of PHP 8.1
    // shows form for special request (insurance, financing, transport) by javascript
    public function ajax_get_specrequest()
    {
        $arrayResult = [
            'html' => 'The boat does not exist',
            'isError' => 1,
        ];

        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-library.php';

        $req = filter_input(INPUT_POST, 'req');
        if ($req) {
            $formData = json_decode($req, true);
            $formData = Yachtino_Library::adjustArray($formData, ['stripslashes', 'strip_tags', 'trim',]);

            if (!empty($formData['lg'])) {

                // language variable does not contain language but spam values
                if (!preg_match('/^[a-z]{2}$/', $formData['lg'])) {
                    wp_die('Forbidden', 403);
                }

            } else {
                $locale = determine_locale();
                $formData['lg'] = substr($locale, 0, 2);
            }
            $formData['formSent'] = 1;

        } else {
            $formData = [];
        }
        $formData['itemtype'] = 'sboat';

        if (empty($formData['rtype']) || empty($formData['itid'])) {
            wp_send_json($arrayResult);
            wp_die();
        }
        if (!preg_match('/^[csetmg][0-9]+$/', $formData['itid'])) {
            wp_die('Forbidden', 403);
        }
        if ($formData['rtype'] != 'financing' && $formData['rtype'] != 'insurance'
        && $formData['rtype'] != 'transport') {
            wp_die('Forbidden', 403);
        }

        // send to API
        $urlParts = [
            'language' => $formData['lg'],
            'part1'    => 'request',
            'part2'    => 'partner-forms',
        ];

        $additionalVars = [
            'itemtype' => $formData['itemtype'],
            'itid'     => $formData['itid'],
            'kind'     => $formData['rtype'],
            'boatdata' => 1,
            'trans'    => 1,
            'mergeccountryspec' => 1,
        ];

        $classApi = Yachtino_Api::get_instance();
        $apiResults = $classApi->send_request_to_api($urlParts, $additionalVars);

        if (empty($apiResults->advert)) {
            wp_send_json($arrayResult);
            wp_die();
        }

        // prepare output
        $requestData = new StdClass();
        $requestData->itemId      = $formData['itid'];
        $requestData->itemType    = $formData['itemtype'];
        $requestData->requestType = $formData['rtype'];
        $requestData->language    = $formData['lg'];

        $requestData->data        = self::get_boat_data($formData['rtype'], $apiResults->advert);
        $requestData->translation = self::get_translation($formData['rtype'], $apiResults->translation);
        $requestData->editOptions = self::get_edit_options($formData['rtype'], $apiResults->selects);

        ob_start();
        include YACHTINO_DIR_PATH . '/templates/public/incl-request-spec.html';
        $arrayResult['html'] = ob_get_clean();
        $arrayResult['isError'] = 0;

        // Make your array as json
        wp_send_json($arrayResult);

        // Don't forget to stop execution afterward.
        wp_die();
    }

    public static function get_translation(string $requestType, object $transl): array
    {
        $output = [
            'YourData'  => $transl->request->YourData,
            'cname'     => $transl->request->cname,
            'cemail'    => $transl->request->cemail,
            'cphone'    => $transl->request->cphone,
            'caddress'  => $transl->request->caddress,
            'cpostcode' => $transl->request->cpostcode,
            'ccity'     => $transl->request->ccity,
            'ccountry'  => $transl->request->ccountry,
            'ctext'     => $transl->request->ctext,
            'Boat'      => $transl->request->Boat,
            'btid'      => $transl->request->btid,
            'model'     => $transl->request->model,
            'yb'        => $transl->request->yb,
            'loa'       => $transl->request->loa,
            'price'     => $transl->request->price,
            'sendRequest'    => $transl->request->sendRequest,
            'fieldsRequired' => $transl->words->fieldsRequired,
            'emailSent'      => $transl->request->emailSent,
        ];
        if ($requestType == 'insurance') {
            $output['h1'] = $transl->request->InsuranceRequest;
        } elseif ($requestType == 'financing') {
            $output['h1'] = $transl->request->FinancingRequest;
        } elseif ($requestType == 'transport') {
            $output['h1'] = $transl->request->TransportRequest;
        }

        if ($requestType == 'insurance' || $requestType == 'transport') {
            $output['material'] = $transl->request->material;
        }

        if ($requestType == 'insurance') {
            $output['power']   = $transl->request->power;
            $output['mooring'] = $transl->request->mooring;
            $output['area']    = $transl->request->area;
            $output['use']     = $transl->request->use;
            $output['areaPlaceholder'] = $transl->request->areaEg;

        } elseif ($requestType == 'financing') {
            $output['manuf']   = $transl->request->manuf;
            $output['use']     = $transl->request->use;
            $output['financ']  = $transl->request->financ;
            $output['dur']     = $transl->request->dur;
            $output['advPay']  = $transl->request->advPay;
            $output['OR']      = $transl->request->OR;

        } elseif ($requestType == 'transport') {
            $output['beam']         = $transl->request->beam;
            $output['height']       = $transl->request->height;
            $output['weight']       = $transl->request->weight;
            $output['fromPlace']    = $transl->request->fromPlace;
            $output['toPlace']      = $transl->request->toPlace;
            $output['toPlaceEg']    = $transl->request->toPlaceEg;
            $output['prefDateFrom'] = $transl->request->prefDateFrom;

        } elseif ($requestType == 'financing') {
            $output['manuf'] = $transl->request->manuf;
        }

        return $output;
    }

    public static function get_boat_data(string $requestType, object $advert): array
    {
        $output = [
            'itid'  => $advert->managing->itemId,
            'btid'  => $advert->boatData->general->boatTypeId,
            'model' => $advert->boatData->general->model,
            'yb'    => $advert->boatData->general->yearBuilt,
            'loa'   => trim($advert->boatData->measures->loa->m->value . ' ' . $advert->boatData->measures->loa->m->unit),
        ];

        // not "price on request"
        if (preg_match('/[0-9]/u', $advert->saleData->price->amount)) {
            $output['price'] = trim($advert->saleData->price->amount . ' ' . $advert->saleData->price->currencyIso);
        } else {
            $output['price'] = '';
        }

        if ($requestType == 'insurance' || $requestType == 'transport') {
            $output['material'] = $advert->boatData->general->hullMaterialId;
        }

        if ($requestType == 'insurance') {
            $output['power'] = '';
            $nrEngines = count($advert->engines);
            if ($nrEngines) {
                if ($advert->engines[0]->power->hp->value) {
                    $power = $advert->engines[0]->power->hp->value . ' '
                        . $advert->engines[0]->power->hp->unit;

                    if ($nrEngines > 1) {
                        $output['power'] = (string)$nrEngines . ' x ' . $power;
                    } else {
                        $output['power'] = $power;
                    }
                }
            }

            $output['mooring'] = '';
            $moorings = [];
            if ($advert->saleData->lying->point) {
                $moorings[] = $advert->saleData->lying->point;
            }
            if ($advert->saleData->lying->province) {
                $moorings[] = $advert->saleData->lying->province;
            }
            if ($advert->saleData->lying->country) {
                $moorings[] = $advert->saleData->lying->country;
            }
            if ($moorings) {
                $output['mooring'] = implode(', ', $moorings);
            }

        } elseif ($requestType == 'financing') {
            $output['manuf'] = $advert->boatData->general->manufacturer;

        } elseif ($requestType == 'transport') {
            $output['beam'] = trim($advert->boatData->measures->beam->m->value . ' ' . $advert->boatData->measures->beam->m->unit);
            $output['weight'] = trim($advert->boatData->measures->displacement->value . ' ' . $advert->boatData->measures->displacement->unit);

            // // height
            // $output['height'] = '';
            // if ($advert->boatData->general->boattypeId == 1) {
                // $height = 0;
                // if ($advert->boatData->sailing->mastHeight->m->value) {
                    // $mast = (float)str_replace(',', '.', $advert->boatData->sailing->mastHeight->m->value);
                    // if ()
                // }
            // }

            if ($advert->saleData->lying->countryIso) {
                $output['fromCt'] = $advert->saleData->lying->countryIso;
            }

            $output['fromPlace'] = '';
            if ($advert->saleData->lying->point) {
                $output['fromPlace'] = $advert->saleData->lying->point;
            }
            if ($advert->saleData->lying->province) {
                if ($output['fromPlace']) {
                    $output['fromPlace'] .= ', ';
                }
                $output['fromPlace'] .= $advert->saleData->lying->province;
            }
        }

        return $output;
    }

    public static function get_edit_options(string $requestType, object $selects): array
    {
        $output = [
            'ccountry' => $selects->ccountry,
            'btid'     => $selects->btid,
        ];

        if ($requestType == 'insurance' || $requestType == 'transport') {
            $output['material'] = $selects->material;
        }

        if ($requestType == 'insurance' || $requestType == 'financing') {
            $output['use'] = $selects->use;
        }

        if ($requestType == 'financing') {
            $output['financ'] = $selects->financ;
            $output['dur'] = $selects->dur;

        } elseif ($requestType == 'transport') {
            $output['fromCt'] = $selects->fromCt;
        }

        return $output;
    }

    // return type: never, possible as of PHP 8.1
    public function ajax_send_specrequest()
    {
        $arrayResult = [
            'ok' => true,
        ];

        require_once YACHTINO_DIR_PATH . '/includes/api/class-yachtino-library.php';

        $req = filter_input(INPUT_POST, 'req');
        if ($req) {
            $formData = json_decode($req, true);
            $formData = Yachtino_Library::adjustArray($formData, ['stripslashes', 'strip_tags', 'trim',]);

            if (!empty($formData['lg'])) {

                // language variable does not contain language but spam values
                if (!preg_match('/^[a-z]{2}$/', $formData['lg'])) {
                    wp_die('Forbidden', 403);
                }

            } else {
                $locale = determine_locale();
                $formData['lg'] = substr($locale, 0, 2);
            }
            $formData['formSent'] = 1;

        } else {
            $formData = [];
        }

        if (empty($formData['itemtype']) || empty($formData['itid']) || empty($formData['kind'])) {
            wp_send_json($arrayResult);
            wp_die();
        }
        if (!preg_match('/^[csetmg][0-9]+$/', $formData['itid'])) {
            wp_die('Forbidden', 403);
        }
        if ($formData['rtype'] != 'financing' && $formData['rtype'] != 'insurance'
        && $formData['rtype'] != 'transport') {
            wp_die('Forbidden', 403);
        }

        // send to API
        $urlParts = [
            'language' => $formData['lg'],
            'part1'    => 'request',
            'part2'    => 'send-request',
        ];

        $additionalVars = [
            'itemtype' => $formData['itemtype'],
        ];
        unset($formData['itemtype']);

        $classApi = Yachtino_Api::get_instance();
        $apiResults = $classApi->send_request_to_api($urlParts, $additionalVars, $formData);

        if (!empty($apiResults->ok)) {
            $arrayResult = [
                'ok' => true,
            ];

        } elseif (!empty($apiResults->errors)) {
            $arrayResult = [
                'errors' => [
                    'errMsg'   => [],
                    'errField' => [],
                ],
            ];
            if (!empty($apiResults->errors->errMsg)) {
                foreach ($apiResults->errors->errMsg as $msg) {
                    $arrayResult['errors']['errMsg'][] = $msg;
                }
            }
            if (!empty($apiResults->errors->errField)) {
                foreach ($apiResults->errors->errField as $fieldKey => $fieldTrue) {
                    $arrayResult['errors']['errField'][$fieldKey] = true;
                }
            }
        }

        // Make your array as json
        wp_send_json($arrayResult);

        // Don't forget to stop execution afterward.
        wp_die();
    }

}