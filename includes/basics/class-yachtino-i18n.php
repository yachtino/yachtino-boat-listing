<?php

declare(strict_types=1);

/**
 * Yachtino boat listing WordPress Plugin
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

if (!defined('ABSPATH')) {
    exit(); // Don't access directly
};

class Yachtino_i18n
{
    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public static function load_plugin_textdomain(): void
    {
        // if we do not have language file for given admin language -> copy english file
        $locale = determine_locale(); // !!! NOT get_bloginfo('language')
        $mofile = self::get_mofile($locale);

        $path = 'yachtino-boat-listing/languages';
        load_plugin_textdomain(
            'yachtino-boat-listing',
            false,
            $path
        );
    }

    public static function load_my_own_textdomain(string $mofile, string $domain): string
    {
        if ('yachtino-boat-listing' === $domain && false !== strpos($mofile, WP_LANG_DIR . '/plugins/')) {
            $locale = apply_filters('plugin_locale', determine_locale(), $domain);
            $mofile = self::get_mofile($locale);
        }
        return $mofile;
    }

    // if we do not have files for given language -> copy English to this locale
    public static function get_mofile(string $locale): string
    {
        $wholePath = YACHTINO_DIR_PATH . '/languages';
        $targetMofile = $wholePath . '/yachtino-boat-listing-' . $locale . '.mo';

        if (is_file($targetMofile)) {
            return $targetMofile;
        }

        $sourceLocale = '';
        $allLangs = Yachtino_Api::allowed_languages();
        $lg = substr($locale, 0, 2);
        if (isset($allLangs[$lg])) {
            $sourceLocale = $allLangs[$lg];
            if (!is_file($wholePath . '/yachtino-boat-listing-' . $sourceLocale . '.mo')) {
                $sourceLocale = '';
            }
        }
        if (!$sourceLocale) {
            $sourceLocale = $allLangs['en'];
        }

        copy(
            $wholePath . '/yachtino-boat-listing-' . $sourceLocale . '.mo',
            $targetMofile,
        );

        return $targetMofile;
    }

}