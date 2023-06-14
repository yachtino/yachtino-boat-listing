<?php

/**
 * Register all actions and filters for the plugin
 *
 * Yachtino boat listing WordPress Plugin
 * @author   Yachtino GmbH
 * @package  yachtino
 * @since    1.0.0
 */

if (!defined('ABSPATH')) {
    exit(); // Don't access directly
};

class Yachtino_Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
     */
    protected array $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
     */
    protected array $filters;

    /**
     * The array of shortcode registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $shortcodes    The shortcodes registered with WordPress to fire when the plugin loads.
     */
    protected array $shortcodes;

    /**
     * Initialize the collections used to maintain the actions and filters.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->actions = [];
        $this->filters = [];
        $this->shortcodes = [];
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string               $hook             The name of the WordPress action that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the action is defined.
     * @param    string               $callback         The name of the function definition on the $component.
     * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int                  $acceptedArgs    Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_action(
        string $hook,
        object|string $component,
        string $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {

        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $acceptedArgs);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string               $hook             The name of the WordPress filter that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the filter is defined.
     * @param    string               $callback         The name of the function definition on the $component.
     * @param    int                  $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int                  $acceptedArgs    Optional. The number of arguments that should be passed to the $callback. Default is 1
     */
    public function add_filter(
        string $hook,
        object|string $component,
        string $callback,
        int $priority = 10,
        int $acceptedArgs = 1,
    ): void {

        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $acceptedArgs);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string               $hook             The name of the WordPress filter that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the filter is defined.
     * @param    string               $callback         The name of the function definition on the $component.
     */
    public function add_shortcode(
        string $hook,
        object|string $component,
        string $callback,
    ): void {

        $this->shortcodes = $this->add($this->shortcodes, $hook, $component, $callback, 0, 0);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     *
     * @since    1.0.0
     * @access   private
     * @param    array                $hooks            The collection of hooks that is being registered (that is, actions or filters).
     * @param    string               $hook             The name of the WordPress filter that is being registered.
     * @param    object               $component        A reference to the instance of the object on which the filter is defined.
     * @param    string               $callback         The name of the function definition on the $component.
     * @param    int                  $priority         The priority at which the function should be fired.
     * @param    int                  $acceptedArgs    The number of arguments that should be passed to the $callback.
     * @return   array                                  The collection of actions and filters registered with WordPress.
     */
    private function add(
        array $hooks,
        string $hook,
        object|string $component,
        string $callback,
        int $priority,
        int $acceptedArgs,
    ): array {

        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'acceptedArgs' => $acceptedArgs
        ];

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @since    1.0.0
     */
    public function run(): void
    {
        foreach ($this->filters as $hook ) {

            // static method
            if (is_string($hook['component'])) {
                $callable = $hook['component'] . '::' . $hook['callback'];

            // class created
            } else {
                $callable = [$hook['component'], $hook['callback']];
            }
            add_filter($hook['hook'], $callable, $hook['priority'], $hook['acceptedArgs']);
        }

        foreach ($this->actions as $hook ) {

            // static method
            if (is_string($hook['component'])) {
                $callable = $hook['component'] . '::' . $hook['callback'];

            // class created
            } else {
                $callable = [$hook['component'], $hook['callback']];
            }
            add_action($hook['hook'], $callable, $hook['priority'], $hook['acceptedArgs']);
        }

        foreach ($this->shortcodes as $hook ) {

            // static method
            if (is_string($hook['component'])) {
                $callable = $hook['component'] . '::' . $hook['callback'];

            // class created
            } else {
                $callable = [$hook['component'], $hook['callback']];
            }
            add_shortcode($hook['hook'], $callable);
        }
    }

}
