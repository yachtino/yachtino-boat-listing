<?php
/**
 * Helps to create HTML elements
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

class Yachtino_Template_Helper
{
    private StdClass $allData;

    /**
     * singleton pattern
     */
    private static ?self $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setData(StdClass $allData): void
    {
        $this->allData = $allData;
    }

    public function createSearchSelect(string $fieldName, string $label, array $options): string
    {
        $output = '<div class="yachtino-filters-field">
        <label for="' . $fieldName . '" class="yachtino-searchlabel';
        if (!empty($this->allData->criteria[$fieldName])) {
            $output .= ' search-chosen';
        }
        $output .= '">' . $label . '</label>
        <select name="' . $fieldName . '" id="yachtino-srchform-' . $fieldName . '" class="yachtino-searchfield';
        if (!empty($this->allData->criteria[$fieldName])) {
            $output .= ' search-chosen';
        }
        $output .= '">
            <option value="">------------------</option>' . "\n";

        foreach ($options as $row) {
            $output .= '<option value="' . $row->id . '"';
            if (!empty($this->allData->criteria[$fieldName]) && $this->allData->criteria[$fieldName] == $row->id) {
                $output .= ' selected';
            }
            $output .= '>' . $row->name . '</option>' . "\n";
        }
        $output .= '
        </select>
        </div>';

        return $output;
    }

    public function createSearchSelectDouble(string $fieldName, string $label, array $options, array $settings): string
    {
        $output = '<div class="yachtino-filters-field">
        <label for="' . $fieldName . 'f" class="yachtino-searchlabel';
        if (!empty($this->allData->criteria[$fieldName . 'f']) || !empty($this->allData->criteria[$fieldName . 't'])) {
            $output .= ' search-chosen';
        }
        $output .= '">' . $label;
        if ($fieldName == 'lng' || $fieldName == 'lng' || $fieldName == 'wdth') {
            $output .= ' (' . $this->allData->search->translation['unit_m'] . ')';
        } elseif (strpos($fieldName, 'prc') !== false) {
            $output .= ' (€)';
        }

        $output .= '</label>
        <div class="yachtino-searchfield-double">
        <select name="' . $fieldName . 'f" id="yachtino-srchform-' . $fieldName . 'f" class="first';
        if (!empty($this->allData->criteria[$fieldName . 'f'])) {
            $output .= ' search-chosen';
        }
        $output .= '">
            <option value="">' . $settings['from'] . '</option>' . "\n";

        foreach ($options as $row) {
            $output .= '<option value="' . $row->id . '"';
            if (!empty($this->allData->criteria[$fieldName . 'f']) && $this->allData->criteria[$fieldName . 'f'] == $row->id) {
                $output .= ' selected';
            }
            $output .= '>' . $row->name . '</option>' . "\n";
        }
        $output .= '
        </select>
        <select name="' . $fieldName . 't" id="yachtino-srchform-' . $fieldName . 't" class="second';
        if (!empty($this->allData->criteria[$fieldName . 't'])) {
            $output .= ' search-chosen';
        }
        $output .= '">
            <option value="">' . $settings['to'] . '</option>' . "\n";

        foreach ($options as $row) {
            $output .= '<option value="' . $row->id . '"';
            if (!empty($this->allData->criteria[$fieldName . 't']) && $this->allData->criteria[$fieldName . 't'] == $row->id) {
                $output .= ' selected';
            }
            $output .= '>' . $row->name . '</option>' . "\n";
        }
        $output .= '
        </select></div>
        </div>';

        return $output;
    }

    public function specRequestSelect(
        string $fieldName,
        string $label,
        bool $isRequired,
        array $options,
        array $settings = [],
    ): string {

        $output = '<div class="yachtino-specreq-row">' . "\n"
            . '<div class="yachtino-specreq-row-name">';

        if ($label) {
            $output .= '<label for="yachtino-specreqfield-' . $fieldName . '" '
                . 'id="l-yachtino-specreqfield-' . $fieldName . '"';
            if ($isRequired) {
                $output .= ' class="yachtino-bold"';
            }
            $output .= '>' . $label;
            if ($isRequired) {
                $output .= '*';
            }
            $output .= '</label>';
        }
        $output .= '</div>' . "\n"
            . '<div class="yachtino-specreq-row-value"><select '
            . 'id="yachtino-specreqfield-' . $fieldName . '"';

        if (!empty($settings['addAttrField'])) {
            $output .= $settings['addAttrField'];
        }
        if ($isRequired) {
            $output .= ' required';
        }
        $output .= '>' . "\n"
            . '<option value="">------------------</option>' . "\n";

        foreach ($options as $row) {
            $output .= '<option value="' . $row->id . '"';
            if (!empty($this->allData->data[$fieldName]) && $this->allData->data[$fieldName] == $row->id) {
                $output .= ' selected';
            }
            $output .= '>' . str_replace('&', '&amp;', $row->name) . '</option>' . "\n";
        }
        $output .= '
        </select>';
        if (!empty($settings['addText'])) {
            $output .= $settings['addText'];
        }
        $output .= '</div></div>' . "\n";

        return $output;
    }

    public function specRequestInput(
        string $fieldName,
        string $label,
        bool $isRequired,
        array $settings = [],
    ): string {

        $output = '<div class="yachtino-specreq-row">' . "\n"
            . '<div class="yachtino-specreq-row-name';
        if ($fieldName == 'caddress') {
            $output .= ' yachtino-nowrap';
        }
        $output .= '">';

        if ($label) {
            $output .= '<label for="yachtino-specreqfield-' . $fieldName . '" '
                . 'id="l-yachtino-specreqfield-' . $fieldName . '"';
            if ($isRequired) {
                $output .= ' class="yachtino-bold"';
            }
            $output .= '>' . $label;
            if ($isRequired) {
                $output .= '*';
            }
            $output .= '</label>';
        }
        $output .= '</div>' . "\n"
            . '<div class="yachtino-specreq-row-value"><input type="text" '
            . 'id="yachtino-specreqfield-' . $fieldName . '" value="';
        if (!empty($this->allData->data[$fieldName])) {
            $output .= esc_attr($this->allData->data[$fieldName]);
        }
        $output .= '"';

        if (!empty($settings['addAttrField'])) {
            $output .= $settings['addAttrField'];
        }
        if ($isRequired) {
            $output .= ' required';
        }
        $output .= '>' . "\n";
        if (!empty($settings['addText'])) {
            $output .= $settings['addText'];
        }
        $output .= '</div></div>' . "\n";

        return $output;
    }

}