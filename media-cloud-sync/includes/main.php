<?php

namespace Dudlewebs\WPMCS;


defined('ABSPATH') || exit;

/**
 * Media Cloud Sync Plugin by Dudlewebs
 *
 * The main plugin handler class is responsible for initializing Plugin.
 *
 * @since 1.0.0
 */
class Main {
    /**
     * Instance.
     *
     * Holds the plugin instance.
     *
     * @since 1.0.0
     * @access public
     * @static
     *
     * @var Plugin
     */
    public static $instance = null;

    /**
     * Plugin constructor.+
     *
     * Initializing  plugin.
     *
     * @since 1.0.0
     * @access private
     */
    private function __construct(){
        $this->register_autoloader();
        add_action('init', [$this, 'init'], 0);
        add_action('rest_api_init', [$this, 'on_rest_api_init'], 9);

        $adminInstance = Admin::instance();
        // reg activation hook.
        register_activation_hook(WPMCS_FILE, [$adminInstance, 'install']);
        // reg deactivation hook.
        register_deactivation_hook(WPMCS_FILE, [$adminInstance, 'deactivation']);
    }
    
    /**
     * Register autoloader.
     *
     * Elementor autoloader loads all the classes needed to run the plugin.
     *
     * @since 1.6.0
     * @access private
     */
    private function register_autoloader(){
        require_once WPMCS_INCLUDES_PATH.'autoloader.php';

        Autoloader::run();
    }


    /**
     * Instance.
     *
     * Ensures only one instance of the plugin class is loaded or can be loaded.
     *
     * @return Plugin An instance of the class.
     * @since 1.0.0
     * @access public
     * @static
     *
     */
    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Init.
     *
     * Initialize  Plugin. Register  support for all the
     * supported post types and initialize  components.
     *
     * @since 1.0.0
     * @access public
     */
    public function init(){
        $this->init_components();
        $this->init_services();
    }

    /**
     * Init components.
     *
     * @since 1.0.0
     * @access private
     */
    private function init_components(){
        /**
         * All backend API has to initiallize outside is_admin(), as REST URL is not part of wp_admin
         */
        Front::instance();
       
        if (is_admin()) {
            Admin::instance();
            Upgrade::instance();
        }
        
        Api::instance();
    }

    /**
     * Init Services.
     *
     * @since 1.0.0
     * @access private
     */
    public function init_services(){
        Cdn::instance();
        Cache::instance();
        Compatibility::instance();
        
        // Integrations
        Integration::instance();
        FilterContent::instance();

        // Background Processing
        BGRunner::instance();
        Sync::instance();
    }


    /**
     * @since 1.0.0
     * @access public
     */
    public function on_rest_api_init(){
    }

    /**
     * Clone.
     *
     * Disable class cloning and throw an error on object clone.
     *
     * The whole idea of the singleton design pattern is that there is a single
     * object. Therefore, we don't want the object to be cloned.
     *
     * @access public
     * @since 1.0.0
     */
    public function __clone(){
        // Cloning instances of the class is forbidden.
        _doing_it_wrong(__FUNCTION__, esc_html__('Something went wrong.', 'media-cloud-sync'), '1.0.0');
    }

    /**
     * Wakeup.
     *
     * Disable unserializing of the class.
     *
     * @access public
     * @since 1.0.0
     */
    public function __wakeup(){
        // Unserializing instances of the class is forbidden.
        _doing_it_wrong(__FUNCTION__, esc_html__('Something went wrong.', 'media-cloud-sync'), '1.0.0');
    }
}

Main::instance();