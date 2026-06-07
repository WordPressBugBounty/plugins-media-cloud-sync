<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Plugin object cache wrapper.
 *
 * Supports all common WordPress environments on PHP 8.1+ and WordPress 5.9+:
 * 1. Default WordPress object cache (in-memory, per request — no Redis/Memcached plugin).
 * 2. Persistent object cache drop-ins (Redis Object Cache, Memcached, etc.).
 *
 * Reads and writes always go through wp_cache_* so behaviour is consistent;
 * flush strategy differs based on whether a persistent drop-in is active.
 */
class Cache {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;


    private static $cached_data=[];

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url   = WPMCS_ASSETS_URL;
        $this->version      = WPMCS_VERSION;
        $this->token        = WPMCS_TOKEN;

        // Initialize setup
        $this->init();
    }

    /**
     * Initialize datas
     */
    public function init() {
    }


    /**
     * Update Static Cache
     */
    public static function update_item_cache( $item_id, $data ){
        self::$cached_data[$item_id] = $data;
        return true;
    }

    /**
     * Update Static Cache
     */
    public static function get_item_cache( $item_id, $default = false ){
        return isset(self::$cached_data[$item_id]) ? self::$cached_data[$item_id] : $default;
    }

    /**
     * Delete Static Cache
     */
    public static function delete_item_cache( $item_id = false ){
        if($item_id === false) {
            self::$cached_data = [];
        } else {
            unset(self::$cached_data[$item_id]);
        }
        return true;
    }

    /**
     * Whether a persistent object cache drop-in is active (Redis, Memcached, etc.).
     */
    private static function uses_persistent_object_cache() {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    /**
     * Build a consistent object cache key for get/set/delete.
     */
    private static function build_cache_key($key, $post_id, $meta_name, $is_user_meta = false) {
        return ($is_user_meta ? 'user_' : '')
            . ($post_id == false ? '' : $post_id)
            . $meta_name
            . (!Utils::is_empty($key) ? '_' . $key : '');
    }

    /**
     * Whether the active object cache supports a feature (WP 6.1+ or drop-in).
     */
    private static function cache_supports($feature) {
        return function_exists('wp_cache_supports') && wp_cache_supports($feature);
    }

    /**
     * Read from object cache with WP 5.5+ $found support.
     */
    private static function get_cached_value($cache_key, &$found) {
        $group = Schema::getConstant('CACHE_GROUP');
        $found = false;

        if (!function_exists('wp_cache_get')) {
            return false;
        }

        // wp_cache_get( ..., &$found ) is available from WordPress 5.5 (plugin requires 5.9).
        return wp_cache_get($cache_key, $group, false, $found);
    }

    /**
     * Write a value to the plugin object cache group.
     */
    private static function sync_object_cache($key, $data, $post_id, $meta_name, $cache_expire, $is_user_meta = false) {
        if (!function_exists('wp_cache_set') || !function_exists('wp_cache_delete')) {
            return;
        }

        $group     = Schema::getConstant('CACHE_GROUP');
        $cache_key = self::build_cache_key($key, $post_id, $meta_name, $is_user_meta);

        wp_cache_delete($cache_key, $group);
        wp_cache_set($cache_key, $data, $group, $cache_expire);
    }

    /**
     * Get Object Cache
     */
    public static function get_object_cache($key = '', $post_id = false, $meta_name = false, $cache_expire = false, $is_user_meta = false) {
        $meta_name      = $meta_name == false ? Schema::getConstant('META_KEY') : $meta_name;
        $cache_expire   = $cache_expire == false ? Schema::getConstant('CACHE_EXPIRE') : $cache_expire;
        $cache_key      = self::build_cache_key($key, $post_id, $meta_name, $is_user_meta);
        $found          = false;

        $data = self::get_cached_value($cache_key, $found);
        if ($found) {
            return $data;
        }

        if ($is_user_meta) {
            $meta_data = get_user_meta($post_id, $meta_name, true);
        } else {
            $meta_data = $post_id == false ? get_option($meta_name, []) : get_post_meta($post_id, $meta_name, true);
        }
        
        if( !Utils::is_empty($key) && is_array($meta_data) && array_key_exists($key, $meta_data) ) {
            // If key exists in meta data, return it
            $data = $meta_data[$key];
        } elseif (is_array($meta_data) && Utils::is_empty($key)) {
            // If no key is provided, return the entire meta data
            $data = $meta_data;
        } else {
            // If key does not exist, return false
            $data = false;
        }

        self::sync_object_cache($key, $data, $post_id, $meta_name, $cache_expire, $is_user_meta);

        return $data;
    }

    /**
     * Set Object Cache
     */
    public static function set_object_cache($key, $data, $post_id = false, $meta_name = false, $cache_expire = false, $is_user_meta = false) {
        $meta_name      = $meta_name == false ? Schema::getConstant('META_KEY') : $meta_name;
        $cache_expire   = $cache_expire == false ? Schema::getConstant('CACHE_EXPIRE') : $cache_expire;

        if( $is_user_meta) {
            $meta_data = get_user_meta($post_id, $meta_name, true);
        } else {
            $meta_data = $post_id == false ? get_option($meta_name, []) : get_post_meta($post_id, $meta_name, true);
        }

        // If data is not an array, convert it to an array
        if (!is_array($meta_data)) {
            $meta_data = [];
        }

        if(Utils::is_empty($key)) {
            // If key is empty, set the entire meta data
            $meta_data = $data;
            $cache_value = $data;
        } else {
            $meta_data[$key] = $data;
            $cache_value = $data;
        }

        // Update the meta data
        if ($is_user_meta) {
            $update_result = update_user_meta($post_id, $meta_name, $meta_data);
        } else {
            $update_result = $post_id == false ? update_option($meta_name, $meta_data) : update_post_meta($post_id, $meta_name, $meta_data);
        }

        // Always sync object cache so Redis and default WP cache stay current.
        self::sync_object_cache($key, $cache_value, $post_id, $meta_name, $cache_expire, $is_user_meta);

        if ($update_result) {
            return true;
        }

        // update_option/update_meta return false when the value is unchanged — treat as success once cache is synced.
        if ($is_user_meta) {
            $stored = get_user_meta($post_id, $meta_name, true);
        } else {
            $stored = $post_id == false ? get_option($meta_name, []) : get_post_meta($post_id, $meta_name, true);
        }

        return maybe_serialize($stored) === maybe_serialize($meta_data);
    }

    

    /**
     * Delete object cache
     */
    public static function delete_object_cache($key = false, $post_id = false, $meta_name = false, $is_user_meta = false) {
        $meta_name = $meta_name == false ? Schema::getConstant('META_KEY') : $meta_name;
        $update_result = false;
        if ($is_user_meta) {
            $meta_data = get_user_meta($post_id, $meta_name, true);
            if (is_array($meta_data) && array_key_exists($key, $meta_data)) {
                unset($meta_data[$key]);
                if (empty($meta_data)) {
                    $update_result = delete_user_meta($post_id, $meta_name);
                } else {
                    $update_result = update_user_meta($post_id, $meta_name, $meta_data);
                }
            }
        } else {
            $meta_data = $post_id == false ? get_option($meta_name, []) : get_post_meta($post_id, $meta_name, true);
            if (is_array($meta_data) && array_key_exists($key, $meta_data)) {
                unset($meta_data[$key]);
                if (empty($meta_data)) {
                    $update_result = $post_id == false ? delete_option($meta_name) : delete_post_meta($post_id, $meta_name);
                } else {
                    $update_result = $post_id == false ? update_option($meta_name, $meta_data) : update_post_meta($post_id, $meta_name, $meta_data);
                }
            }
        }

        if ($update_result && !Utils::is_empty($key) && function_exists('wp_cache_delete')) {
            $cache_key = self::build_cache_key($key, $post_id, $meta_name, $is_user_meta);
            wp_cache_delete($cache_key, Schema::getConstant('CACHE_GROUP'));
        }

        return $update_result;
    }


    /**
     * Flush Cache
     * @since 1.3.2
     */
    public static function flush_object_cache() {
        $group = Schema::getConstant('CACHE_GROUP');

        if (self::uses_persistent_object_cache()) {
            // Redis / Memcached drop-in: flush only this plugin's cache group.
            self::flush_cache_group($group);
        } else {
            // Default WordPress in-memory cache: safe to flush the runtime cache.
            self::flush_runtime_cache();
        }

        self::$cached_data = [];
        return true;
    }

    /**
     * Flush the in-memory runtime cache (default WordPress, no drop-in plugin).
     */
    private static function flush_runtime_cache() {
        if (self::cache_supports('flush_runtime') && function_exists('wp_cache_flush_runtime')) {
            wp_cache_flush_runtime();
            return;
        }

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Flush all keys in the plugin cache group when the drop-in supports it.
     */
    private static function flush_cache_group($group) {
        if (!function_exists('wp_cache_flush_group')) {
            return false;
        }

        // Prefer the supported API so core never falls back to a full cache flush.
        if (self::cache_supports('flush_group')) {
            return (bool) wp_cache_flush_group($group);
        }

        // Persistent drop-ins (e.g. Redis Object Cache on WP 5.9) may provide flush_group
        // without wp_cache_supports() being available in core.
        if (self::uses_persistent_object_cache()) {
            return (bool) wp_cache_flush_group($group);
        }

        return false;
    }

    
    /**
     * Gets the instance of this class.
     *
     * @return Cache
     */
    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
