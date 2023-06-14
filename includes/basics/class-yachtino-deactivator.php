<?php

declare(strict_types=1);

/**
 * Fired during plugin deactivation
 *
 * Yachtino boat listing WordPress Plugin
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

if (!defined('ABSPATH')) {
    exit(); // Don't access directly
};

class Yachtino_Deactivator {

    /**
     * Deactivates the plugin
     *
     * @since    1.0.0
     */
    public static function deactivate(): void
    {

    }

}
