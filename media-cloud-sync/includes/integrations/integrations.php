<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Integration {
    private static $instance = null;

    private $integrations = [];
    public static $source_types = [];
    public static $source_labels = [];

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->integrations = [
            'MediaLibrary',
            'Acf',
            'Imagify',
            'WooCommerce',
        ];

        // Initialize setup
        $this->init();
    }

    /**
     * Initializes all registered integrations.
     *
     * Iterates over the list of registered integrations, checks if they are installed,
     * and if so, initializes their instances. Additionally, it gathers and merges source types
     * and labels from each integration into the static properties $source_types and $source_labels.
     *
     * @since 1.0.0
     */

    public function init() {
        // Init all integrations
        if (!empty($this->integrations)) {
            foreach ($this->integrations as $integration) {
                $this->process_integration($integration);
            }
        }
    }

    /**
     * Processes a single integration: registers its source types/labels and
     * instantiates it if installed and the service is enabled.
     *
     * @param string $integration Unqualified integration class name.
     * @since 1.4.1
     */
    private function process_integration($integration) {
        $integration = __NAMESPACE__ . '\\' . $integration;
        if (class_exists($integration) && $integration::is_installed()) {
            if (property_exists($integration, 'source_types')) {
                self::$source_types = array_merge(self::$source_types, $integration::$source_types);
            }
            // get_label() (not a $label property) because a static property default can't
            // call __() — PHP only allows constant expressions there.
            if (property_exists($integration, 'source_type_prefix') && method_exists($integration, 'get_label')) {
                self::$source_labels[$integration::$source_type_prefix] = $integration::get_label();
            }
            if (Utils::is_service_enabled()) {
                $integration::instance();
            }
        }
    }

    /**
     * Registers additional integration classes after construction, mirroring
     * Sync::add_sync_classes() — lets Pro register a new integration whenever,
     * regardless of load order relative to this class's own construction.
     *
     * @param array $classes Unqualified integration class names to add.
     * @since 1.4.1
     */
    public function add_integrations($classes = []) {
        $new_classes = array_diff($classes, $this->integrations);
        $this->integrations = array_merge($this->integrations, $new_classes);
        foreach ($new_classes as $integration) {
            $this->process_integration($integration);
        }
    }

    /**
     * Gets the handler class associated with a given source type.
     *
     * @param string $source_type The source type to look up.
     *
     * @return string|null The handler class associated with the source type, or null if not found.
     */
    public static function get_handler_class($source_type = 'media_library') {
        return  class_exists(__NAMESPACE__ . '\\' . self::$source_types[$source_type]['class']) 
                    ? __NAMESPACE__ . '\\' . self::$source_types[$source_type]['class'] 
                    : null;
    }


    /**
     * Get Item Meta According to Integration
     * @param int $post_id
     * @param string $key
     * @param mixed $default
     * @param string|false $meta_name
     * @param int|false $expire
     * @param string $source_type
     * @return mixed|null|false
     * @throws \Exception
     * @since 1.0.0
     */
    public static function get_meta($post_id, $key, $default = false, $meta_name = false, $expire = false, $source_type = 'media_library') {
        if(isset(self::$source_types[$source_type])) {
            $source_type_data = self::$source_types[$source_type];
            $meta_key   = (isset($source_type_data['meta_prefix']) ? $source_type_data['meta_prefix'].'_' : '') . $key;
            $table      = isset($source_type_data['table']) ? $source_type_data['table'] : false;

            if (is_array($table)) {
                return Utils::get_custom_meta($post_id, $meta_key, $default, $meta_name, $expire, $table);
            }

            // Update it -  handle in seperate integration by calling common function
            switch($table) {
                case 'posts':
                    return Utils::get_meta($post_id, $meta_key, $default, $meta_name, $expire);
                case 'users':
                    return Utils::get_user_meta($post_id, $meta_key, $default, $meta_name, $expire);
                default: return $default;
            }
        }
    }

    /**
     * Update Meta According to Integration
     * @param int $post_id
     * @param string $key
     * @param mixed $options
     * @param string|false $meta_name
     * @param int|false $expire
     * @param string $source_type
     * @return bool|int
     * @throws \Exception
     * @since 1.0.0
     */
    public static function update_meta($post_id, $key, $options, $meta_name = false, $expire = false, $source_type = 'media_library') {
        if(isset(self::$source_types[$source_type])) {
            $source_type_data = self::$source_types[$source_type];
            $meta_key   = (isset($source_type_data['meta_prefix']) ? $source_type_data['meta_prefix'].'_' : '') . $key;
            $table      = isset($source_type_data['table']) ? $source_type_data['table'] : false;

            if (is_array($table)) {
                return Utils::update_custom_meta($post_id, $meta_key, $options, $meta_name, $expire, $table);
            }

            switch($table) {
                case 'posts':
                    return Utils::update_meta($post_id, $meta_key, $options, $meta_name, $expire);
                case 'users':
                    return Utils::update_user_meta($post_id, $meta_key, $options, $meta_name, $expire);
                default: null;
            }
        }
    }

    /**
     * Delete Meta According to Integration
     * @param int $post_id
     * @param string $key
     * @param string|false $meta_name
     * @param string $source_type
     * @return bool
     * @throws \Exception
     * @since 1.0.0
     */
    public static function delete_meta($post_id, $key, $meta_name = false, $source_type = 'media_library') {
        if(isset(self::$source_types[$source_type])) {
            $source_type_data = self::$source_types[$source_type];
            if($key !== false) {
                $meta_key   = (isset($source_type_data['meta_prefix']) ? $source_type_data['meta_prefix'].'_' : '') . $key;
            } else {
                $meta_key = false;
            }
            $table      = isset($source_type_data['table']) ? $source_type_data['table'] : false;

            if (is_array($table)) {
                return Utils::delete_custom_meta($post_id, $meta_key, $meta_name, $table);
            }

            switch($table) {
                case 'posts':
                    return Utils::delete_meta($post_id, $meta_key, $meta_name);
                case 'users':
                    return Utils::delete_user_meta($post_id, $meta_key, $meta_name);
                default: null;
            }
        }
    }

    /**
     * Clear all meta
     *
     * @param bool $flush_cache Whether to flush the plugin object cache after each table clear.
     */
    public static function clear_all_meta($flush_cache = true) {
        if (!empty(self::$source_types)) {
            foreach (self::$source_types as $source_type => $source_type_data) {
                $table      = isset($source_type_data['table']) ? $source_type_data['table'] : false;

                if (is_array($table)) {
                    // Custom backends (e.g. BuddyBoss groupmeta) aren't bulk-cleared here —
                    // no generic raw table name to target; individual keys are still
                    // cleaned up as their owning items are deleted.
                    continue;
                }

                switch($table) {
                    case 'posts':
                        Utils::clear_all_meta(false, 'postmeta', $flush_cache);
                        break;
                    case 'users':
                        Utils::clear_all_meta(false, 'usermeta', $flush_cache);
                        break;
                    default: null;
                }
            }
        }
    }


    
    /**
     * Return an instance of the class.
     * 
     * @return Integration
     * @since 1.0.0
     */
    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
}