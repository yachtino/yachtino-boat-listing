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

class Yachtino_Library
{
    public static function truncateDirectory(string $absolutePath, bool $removeThisDir): bool
    {
        if (!is_dir($absolutePath)) {
            return false;
        }
        if (substr($absolutePath, -1, 1) == '/') {
            $absolutePath = substr($absolutePath, 0, -1);
        }
        self::truncateOneDir($absolutePath, $removeThisDir);
        return true;
    }

    /**
     * Deletes all files in one directory
     */
    public static function truncateOneDir(string $absolutePath, bool $removeThisDir): void
    {
        $files = scandir($absolutePath);
        foreach ($files as $file) {
            if ($file != '..' && $file != '.') {
                if (is_file($absolutePath . '/' . $file)) {
                    unlink($absolutePath . '/' . $file);
                } else {
                    self::truncateOneDir($absolutePath . '/' . $file, true);
                }
            }
        }
        if ($removeThisDir) {
            rmdir($absolutePath);
        }
    }

    public static function adjustVar(mixed $var, array $options = []): mixed
    {
        if ($var === null || is_int($var) || is_float($var) || is_bool($var)) {
            return $var;
        }

        if (empty($options)) {
            $options = ['trim'];
        }

        foreach ($options as $fct) {
            switch($fct) {
                case 'trim':
                    $var = trim($var);
                    break;
                case 'stripslashes':
                    $var = stripslashes($var);
                    break;
                case 'addslashes':
                    $var = addslashes($var);
                    break;
                case 'strip_tags':
                    $var = strip_tags($var);
                    break;
                case 'htmlentities':
                    $var = htmlentities($var, ENT_QUOTES, 'UTF-8');
                    break;
                case 'htmlspecialchars':
                    $var = htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
                    break;
            }
        }

        return $var;
    }

    /**
     * Trims all variables given in multidimensional array
     * usefull for $_POST variables that are array
     */
    public static function adjustArray(array $array, array $options = []): array
    {
        if (empty($options)) {
            $options = ['trim'];
        }

        foreach ($array as $key => $value) {
            self::_adjustOneArray($key, $value, $options, $array);
        }
        return $array;
    }

    /**
     * Also for deep arrays - number of levels is unlimited
     */
    private static function _adjustOneArray(string|float $key, mixed $value, array $options, array &$basicVar): void
    {
        if (!is_array($value)) {
            $basicVar[$key] = self::adjustVar($value, $options);
        } else {
            foreach ($value as $key1 => $value1) {
                self::_adjustOneArray($key1, $value1, $options, $basicVar[$key]);
            }
        }
    }

    /**
     * Similar function to http_build_query but this uses rawurlencode() and NOT urlencode()
     */
    public static function makePostToUrl(array $postData): string
    {
        if (!$postData) {
            return '';
        }

        $url = self::_convertOneVar('', $postData, '');
        if ($url) {
            $url = mb_substr($url, 1);
        }
        return $url;
    }

    public static function _convertOneVar(string $url, array $arrayData, string $keysBefore): string
    {
        foreach ($arrayData as $key => $value) {
            if (!is_array($value)) {
                if ($keysBefore) {
                    $url .= '&' . $keysBefore . '[' . $key . ']=' . self::urlencodeVar($value);
                } else {
                    $url .= '&' . $key . '=' . self::urlencodeVar($value);
                }

            } else {
                if ($keysBefore) {
                    $keysBeforeNew = $keysBefore . '[' . $key . ']';
                } else {
                    $keysBeforeNew = $key;
                }
                $url = self::_convertOneVar($url, $value, $keysBeforeNew);
            }
        }
        return $url;
    }

    public static function urlencodeVar(string|float|null $value): string
    {
        if ($value === null) {
            return '';
        } elseif (!is_numeric($value)) {
            return rawurlencode($value);
        } else {
            return (string)$value;
        }
    }

    /**
     * Splits text into two parts if the text is too long
     * $text - text with \n as break (not <br />)
     */
    public static function splitText(string $text, int $maxLength, int $maxLines): array
    {
        $text = trim($text);

        // too many lines
        $numberLines = substr_count($text, "\n");
        if ($numberLines > $maxLines) {
            $firstPart = '';
            $p = explode("\n", $text);
            foreach ($p as $key => $part) {
                if ($key == $maxLines) {
                    break;
                }
                $firstPart .= $part . "\n";
            }
        } else {
            $firstPart = $text;
        }

        // too many signs
        if (mb_strlen($firstPart) > $maxLength) {
            $newText = '';
            $p = explode(' ', $firstPart);
            $count = 0;
            foreach ($p as $part) {
                if (mb_strlen($newText . ' ' . $part) <= $maxLength) {
                    if ($count != 0) {
                        $newText .= ' ';
                    }
                    $newText .= $part;
                } else {
                    break;
                }
                $count++;
            }
            $firstPart = $newText;
        }

        $secondPart = preg_replace('/^' . preg_quote($firstPart, '/') . '/u', '', $text);

        // the text is NOT too long
        if (!$secondPart) {
            return [
                'part1' => $firstPart,
                'part2' => $secondPart,
            ];
        }

        // HTML tag is broken (first part has <spa and second part has n>)
        preg_match('/(<[^>]*)$/u', $firstPart, $m1);
        preg_match('/^([^<]*>)/u', $secondPart, $m2);

        if (!empty($m1[1]) && !empty($m2[1])) {
            $brokenTag = $m1[1] . $m2[1];

            // closing tag OR <br /> => close the tag in first part
            if (mb_substr($brokenTag, 0, 2) == '</'
            || mb_substr($brokenTag, 0, 3) == '<br') {
                $firstPart .= $m2[1];
                $secondPart = preg_replace('/^' . preg_quote($m2[1], '/') . '/u', '', $secondPart);

            // opening tag => open the tag in second part
            } else {
                $secondPart = $m1[1] . $secondPart;
                $firstPart = preg_replace('/' . preg_quote($m1[1], '/') . '$/u', '', $firstPart);
            }

            $arrTmp = self::checkBrokenTags($firstPart, $secondPart);
            return [
                'part1' => $arrTmp['part1'],
                'part2' => $arrTmp['part2'],
            ];
        }

        $arrTmp = self::checkBrokenTags($firstPart, $secondPart);
        return [
            'part1' => $arrTmp['part1'],
            'part2' => $arrTmp['part2'],
        ];
    }

    public static function checkBrokenTags(string $firstPart, string $secondPart): array
    {
        // opening HTML tag in first part, closing tag in second part
        preg_match_all('/(<[^\/>]+>)/u', $firstPart, $m1); // opening tags
        preg_match_all('/(<\/[^>]+>)/u', $firstPart, $m2); // closing tags

        // no HTML tags
        if (empty($m1[1])
        || count($m1[1]) == count($m2[1])) {
            return [
                'changed' => false,
                'part1' => $firstPart,
                'part2' => $secondPart,
            ];
        }

        $openTag = end($m1[1]);
        preg_match('/<([^ ]+)/u', $openTag, $m);
        $tagName = $m[1];
        $firstPart .= '</' . $tagName . '>';
        $secondPart = $openTag . $secondPart;

        $arrTmp = self::checkBrokenTags($firstPart, $secondPart);

        return [
            'changed' => true,
            'part1' => $arrTmp['part1'],
            'part2' => $arrTmp['part2'],
        ];
    }

    /**
     * $options - int $ignorePathLevels - shown path - cut up first directories
     *            int $stackFramesLimit - for debug_backtrace
     */
    public static function backtraceAsString(bool $asHtml = true, array $options = []): string
    {
        if ($asHtml) {
            $endOfLine = '<br />' . "\r\n";
            $iBegin = '<i>';
            $iEnd = '</i>';
        } else {
            $endOfLine = "\r\n";
            $iBegin = '';
            $iEnd = '';
        }
        if (empty($options['stackFramesLimit'])) {
            $options['stackFramesLimit'] = 0;
        }

        $output = $endOfLine;

        $counter = 0;
        $debugResult = debug_backtrace(0, $options['stackFramesLimit']);

        foreach ($debugResult as $row) {
            if (empty($row['file'])) {
                continue;
            }
            if (!empty($options['ignorePathLevels'])) {
                $p = explode(DIRECTORY_SEPARATOR, $row['file']);
                $newFile = '';
                foreach ($p as $key => $part) {
                    if ($key < $options['ignorePathLevels']) {
                        continue;
                    }
                    $newFile .= $part . '/';
                }
                if ($newFile) {
                    $newFile = mb_substr($newFile, 0, -1);
                }
            } else {
                $newFile = $row['file'];
            }

            $output .= '#' . (string)$counter . ' ' . $newFile . ' (' . (string)$row['line'] . '): ';

            if (!empty($row['class'])) {
                $output .= $iBegin . $row['class'] . $row['type'] . $row['function'] . '(';
                $args = '';
                if (!empty($row['args'])) {
                    foreach ($row['args'] as $arg) {
                        if (is_object($arg) || is_array($arg) || is_resource($arg)) {
                            $x = get_debug_type($arg);
                            $args .= $x . ', ';

                        } elseif (is_bool($arg)) {
                            if (!$arg) {
                                $args .= 'false, ';
                            } else {
                                $args .= 'true, ';
                            }

                        } elseif ($arg === null) {
                            $args .= 'null, ';

                        } elseif (is_int($arg) || is_float($arg)) {
                            $args .= (string)$arg . ', ';

                        } else {
                            $args .= '"' . $arg . '", ';
                        }
                    }
                }
                if ($args) {
                    $output .= mb_substr($args, 0, -2);
                }
                $output .= ')' . $iEnd;

            } elseif ($row['function'] == 'include' && !empty($row['args'][0])) {
                if (!empty($options['ignorePathLevels'])) {
                    $p = explode(DIRECTORY_SEPARATOR, $row['args'][0]);
                    $newFile = '';
                    foreach ($p as $key => $part) {
                        if ($key < $options['ignorePathLevels']) {
                            continue;
                        }
                        $newFile .= $part . '/';
                    }
                    if ($newFile) {
                        $newFile = mb_substr($newFile, 0, -1);
                    }
                } else {
                    $newFile = $row['args'][0];
                }
                $output .= $iBegin . 'include (' . $newFile . ')' . $iEnd;
            }
            $output .= $endOfLine;
            $counter++;
        }

        return $output;
    }

    public static function javascriptDateFormat(string $lg): string
    {
        if ($lg == 'en' || $lg == 'it' || $lg == 'fr' || $lg == 'el' || $lg == 'es') {
            return 'dd/mm/yy';

        } elseif ($lg == 'nl') { // 03-05-2021
            return 'dd-mm-yy';

        // cs, de, hr, hu, pl, ru
        } else {                 // 03.05.2021
            return 'dd.mm.yy';
        }
    }

    /**
     * Makes an array from search string ("btid=1&manfb=25" => ['btid' => 1, 'manfb' => 25]).
     */
    public static function explodeSearchString(string $searchString): array
    {
        $searchArray = [];

        if (mb_substr($searchString, 0, 1) === '?') {
            $searchString = mb_substr($searchString, 1);
        }

        if (!$searchString) {
            return $searchArray;
        }

        // Correct square brackets for array (may be url encoded).
        $searchString = str_ireplace('%5b', '[', $searchString);
        $searchString = str_ireplace('%5d', ']', $searchString);

        $parts = explode('&', $searchString);
        foreach ($parts as $p) {
            if (!$p) {
                continue;
            }
            $tmp = explode('=', $p);
            if (!isset($tmp[1])) {
                continue;
            }

            $key   = $tmp[0];
            $value = $tmp[1];
            if (is_numeric($value)) {
                if ($key != 'md' && $key != 'q' && preg_match('/^[0-9]+$/', $value)) {
                    $value = (int)$value;
                }

            } else {
                $valueX = rawurldecode($value);
                if ($valueX != $value) {
                    $value = $valueX;
                }
            }

            // it is double array (eg. key[foo][]=bar)
            if (preg_match('/^([-a-z0-9_]+)\[([-a-z0-9_]*?)\]\[([-a-z0-9_]*?)\]$/ui', $key, $m)) {
                if ($m[2]) {
                    if ($m[3]) {
                        $searchArray[$m[1]][$m[2]][$m[3]] = $value;
                    } else {
                        $searchArray[$m[1]][$m[2]][] = $value;
                    }
                } else {
                    if ($m[3]) {
                        $searchArray[$m[1]][] = [
                            $m[3] => $value,
                        ];
                    } else {
                        $searchArray[$m[1]][][] = $value;
                    }
                }

            // it is array (eg. btid[]=1 or lastSearch[ct]=de)
            } elseif (preg_match('/^([-a-z0-9_]+)\[([-a-z0-9_]*?)\]$/ui', $key, $m)) {
                if ($m[2]) {
                    $searchArray[$m[1]][$m[2]] = $value;
                } else {
                    $searchArray[$m[1]][] = $value;
                }

            // scalar search variable
            } else {
                $searchArray[$key] = $value;
            }
        }

        return $searchArray;
    }

}