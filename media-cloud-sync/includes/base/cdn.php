<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Cdn {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url   = WPMCS_ASSETS_URL;
        $this->version      = WPMCS_VERSION;
        $this->token        = WPMCS_TOKEN;

        // Initialize setup
        // $this->init();
    }
    /**
     * 
     * CDN url generate
     * @since 1.0.0
     *
     */
    public static function may_generate_cdn_url($url, $key) {
        $settings = Utils::get_settings();

        if (
            isset($settings['enable_cdn'], $settings['cdn_url']) &&
            $settings['enable_cdn'] && !empty($settings['cdn_url']) &&
            isset($url, $key) && !Utils::is_empty($url) && !Utils::is_empty($key)
        ) {
            $url_parts = explode($key, $url);
            if (!empty($url_parts[0])) {
                $cdn_url = 'https://' . preg_replace('#^https?://#i', '', trim($settings['cdn_url']));
                $new_url = str_replace(trailingslashit($url_parts[0]), trailingslashit($cdn_url), $url);
                $new_url = apply_filters('wpmcs_cdn_url', $new_url, $url, $cdn_url);
                return preg_replace('/([^:])(\/{2,})/', '$1/', $new_url); // remove double slashes
            }
        }

        return $url;
    }


    /**
     * Is CDN URL ?
     * @since 1.3.6
     */
    public static function is_cdn_url($url) {
        $settings = Utils::get_settings();

        if (
            isset($settings['enable_cdn'], $settings['cdn_url']) &&
            $settings['enable_cdn'] && !empty($settings['cdn_url']) &&
            isset($url) && !Utils::is_empty($url)
        ) {
            $cdn_url = 'https://' . preg_replace('#^https?://#i', '', trim($settings['cdn_url']));
            return (strpos($url, $cdn_url . '/') !== false);
        }

        return false;
    }

   
    /**
     * Get the CDN domain.
     *
     * @since 1.3.6
     * @return string The CDN domain URL if enabled, otherwise an empty string.
     */
    public static function get_domain() {
        $settings = Utils::get_settings();

        if (
            isset($settings['enable_cdn'], $settings['cdn_url']) &&
            $settings['enable_cdn'] && !empty($settings['cdn_url'])
        ) {
            return 'https://' . preg_replace('#^https?://#i', '', trim($settings['cdn_url']));
        }

        return '';
    }

    
    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}