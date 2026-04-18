<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;


class Front {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;

    /**
     * Constructor
     * @since 1.0.0
     */
    public function __construct(){
        $this->assets_url = WPMCS_ASSETS_URL;
        $this->version    = WPMCS_VERSION;
        $this->token      = WPMCS_TOKEN;

        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles'], 10);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 10);

        add_action('init', [$this, 'init'], 10);
    }
   

    /**
     * Register frontend styles
     * @since 1.0.0
     */
    public function enqueue_styles(){
        // wp_register_style($this->token.'-frontend', esc_url($this->assets_url).'css/front-default.css', array(), $this->version);
        // wp_enqueue_style($this->token.'-frontend');
    }

    /**
     * Register frontend scripts
     * @since 1.0.0
     */
    public function enqueue_scripts(){
        // $wpmcs_global_vars = array(
        //     'api_nonce'           => wp_create_nonce('wp_rest'),
        //     'root'                => rest_url($this->token.'/v1/'),
        //     'assets_url'          => $this->assets_url,
        // )
       
        // wp_localize_script($this->token.'-front', $this->token.'_front', $wpmcs_global_vars);
    }

    /**
     * Init function
     */
    public function init(){
    }

     /**
     * Ensures only one instance of Class is loaded or can be loaded.
     *
     * @return Front Class instance
     * @since 1.0.0
     * @static
     */
    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}