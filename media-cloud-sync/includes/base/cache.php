<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

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
     * Get Object Cache
     */
    public static function get_object_cache($key = '', $post_id = false, $meta_name = false, $cache_expire = false, $is_user_meta = false) {
        $meta_name      = $meta_name == false ? Schema::getConstant('META_KEY') : $meta_name;
        $cache_expire   = $cache_expire == false ? Schema::getConstant('CACHE_EXPIRE') : $cache_expire;

        $cache_key      = ($is_user_meta ? 'user_' : '').($post_id == false ? '' : $post_id) . $meta_name . (!empty($key) ? '_'.$key : '');

        $data = wp_cache_get($cache_key, Schema::getConstant('CACHE_GROUP'));
        if ($data !== false) return $data;

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

        // Set the data in object cache
        wp_cache_set($cache_key, $data, Schema::getConstant('CACHE_GROUP'), $cache_expire);

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
        } elseif (is_array($meta_data) && array_key_exists($key, $meta_data)) {
            if($meta_data[$key] == $data) {
                return true;
            }
            // If key exists, update the value
            $meta_data[$key] = $data;
        } else { 
            $meta_data[$key] = $data;
        }

        // Update the meta data
        if ($is_user_meta) {
            $update_result = update_user_meta($post_id, $meta_name, $meta_data);
        } else {
            $update_result = $post_id == false ? update_option($meta_name, $meta_data) : update_post_meta($post_id, $meta_name, $meta_data);
        }
        // If update failed, return false
        if (!$update_result) {
            return false;
        }

        // Update the object cache if caching is enabled
        $cache_key = ($is_user_meta ? 'user_' : '') . ($post_id == false ? '' : $post_id) . $meta_name . '_' . $key;
        wp_cache_delete($cache_key, Schema::getConstant('CACHE_GROUP'));
        wp_cache_set($cache_key, $data, Schema::getConstant('CACHE_GROUP'), $cache_expire);


        // Return success if data update was successful
        return $update_result;
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

        // Delete from object cache
        if ($update_result && $key) {
            $cache_key = ($is_user_meta ? 'user_' : '') . ($post_id == false ? '' : $post_id) . $meta_name . '_' . $key;
            wp_cache_delete($cache_key, Schema::getConstant('CACHE_GROUP'));
        }

        // Return success if data delete was successful
        return $update_result;
    }


    /**
     * Flush Cache
     * @since 1.3.2
     */
    public static function flush_object_cache() {
        if(wp_cache_supports('flush_group')) {
            wp_cache_flush_group(Schema::getConstant('CACHE_GROUP'));
        } else {
            wp_cache_flush();
        }
        self::$cached_data = [];
        return true;
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