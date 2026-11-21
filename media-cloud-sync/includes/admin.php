<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

use WP_Site;
class Admin {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;
    private $script_suffix;

    protected $hook_suffix = [];
    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url       = WPMCS_ASSETS_URL;
        $this->version          = WPMCS_VERSION;
        $this->token            = WPMCS_TOKEN;
        $this->script_suffix    = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        $plugin = plugin_basename(WPMCS_FILE);

        // Create file directory on action do
        add_action( $this->token.'_create_plugin_dir', [ $this, 'create_plugin_dir' ] );

        // add action links to link to link list display on the plugins page.
        add_filter("plugin_action_links_$plugin", [$this, 'plugin_action_links']);

        // add our custom CSS classes to <body>
		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
        // Admin Init
        add_action('admin_init', [$this, 'adminInit']);

        add_action('admin_menu', [$this, 'add_menu'], 10);
        add_action('admin_notices', [$this, 'storage_credentials_admin_notice']);

        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_styles'], 10, 1);
        // Load media scripts
        add_action( 'load-upload.php', [ $this, 'load_media_assets' ], 11 );

        // Plugin Deactivation Survey
        add_action('admin_footer', array($this, 'wpmcs_deactivation_form'));
    }

    /**
     * Method that is used on plugin initialization time
     * @since 1.0.0
     */
    public function adminInit() {
        $current_db_version = get_option( $this->token . '_db_version', false );
        /**
         * Run DB upgrades only if:
         * - DB version exists
         * - DB version is lower than plugin DB version
         */
        if (
            $current_db_version === false || 
            version_compare( $current_db_version, WPMCS_DB_VERSION, '<' )
        ) {
            Db::instance()->do_database_upgrade();
        }

        /**
         * One-time activation redirect
         */
        $do_redirect = get_option( $this->token . '_do_activation_redirect', false );

        if ( $do_redirect ) {
            delete_option( $this->token . '_do_activation_redirect' );

            if ( ! Utils::get_service() ) {
                wp_redirect(
                    admin_url( 'admin.php?page=' . $this->token . '-admin-ui#/configure' )
                );
                exit;
            }
        }
    }

    /**
     * Installation. Runs on activation.
     *
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public static function install($network_wide = false) {
        Db::handle_tables($network_wide);

        // Redirection on activation
        add_option(WPMCS_TOKEN . '_do_activation_redirect', true);

        //Protect directories
        self::_protect_upload_dir();
    }

    /**
     * Create plugin specific upload directory
     * @since 1.0.0
     */
    public function create_plugin_dir() {
        //Protect directories
        self::_protect_upload_dir();
    }

    /**
     * Protect Directory from external access.
     *
     * @access  private
     * @return  void
     * @since   1.3.0
     * @see \Dudlewebs\WPMCS\Admin::_protect_upload_dir
     */
    private static function _protect_upload_dir(){
        $upload_dir = wp_upload_dir();

        $upload_base = $upload_dir['basedir'] . '/' . Schema::getConstant('UPLOADS');

        $files = array(
            array(
                'base' => $upload_base,
                'file' => '.htaccess',
                'content' => "Options -Indexes\nDeny from all"
            ),
            array(
                'base' => $upload_base,
                'file' => 'index.php',
                'content' => "<?php\n// Silence is golden."
            )
        );

        foreach ($files as $file) {
            if ((wp_mkdir_p($file['base'])) && (!file_exists(trailingslashit($file['base']) . $file['file']))  // If file not exist
            ) {
                if ($file_handle = @fopen(trailingslashit($file['base']) . $file['file'], 'w')) {
                    fwrite($file_handle, $file['content']);
                    fclose($file_handle);
                }
            }
        }
    }

    /**
     * Deactivation hook
     */
    public static function deactivation($network_wide = false){
        if (is_multisite() && $network_wide) {
            foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $site_id) {
                switch_to_blog($site_id);
                wp_clear_scheduled_hook(WPMCS_TOKEN . '_bg_runner_cron');
                restore_current_blog();
            }
            return;
        }
        wp_clear_scheduled_hook(WPMCS_TOKEN . '_bg_runner_cron');
    }


    /**
     * Handle multisite site creation.
     */
    public static function create_multisite_table($site) {
        if (is_numeric($site)) {
            $site_id = (int) $site;
        } elseif ($site instanceof \WP_Site) {
            $site_id = $site->blog_id;
        } else {
            return; // Invalid input
        }

        switch_to_blog($site_id);
        Db::instance()->create_table();
        restore_current_blog();
    }

    /**
     * Load admin Javascript.
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function admin_enqueue_scripts($hook = '') {
        if (!isset($this->hook_suffix) || empty($this->hook_suffix)) {
            return;
        }

        $screen = get_current_screen();

        // deactivate form js
        if ($screen->id == 'plugins') { 
            wp_enqueue_script($this->token . '-deactivate-form', esc_url($this->assets_url) . 'js/deactivate.js', array('jquery'), $this->version, true);
        }


        if (in_array($screen->id, $this->hook_suffix, true)) {
            // Enqueue WordPress media scripts.
            if (!did_action('wp_enqueue_media')) {
                wp_enqueue_media();
            }

            // Enqueue custom backend script.
            // wp-hooks: the admin app's extension API for add-ons.
            wp_enqueue_script($this->token . '-backend', esc_url($this->assets_url) . 'js/backend.js', array('wp-i18n', 'wp-hooks'), $this->version, true);

            // Localize a script.
            wp_localize_script(
                $this->token . '-backend',
                $this->token . '_object',
                array(
                    'api_nonce' => wp_create_nonce('wp_rest'),
                    'root' => rest_url($this->token . '/v1/'),
                    'assets_url' => $this->assets_url,
                    'admin_url' => admin_url(),
                    'is_db_upgrade_required' => Upgrade::instance()->is_upgrade_needed(),
                    'is_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
                    'version' => WPMCS_VERSION,
                )
            );

            // Inline (not localized) so booleans/numbers keep their types.
            wp_add_inline_script($this->token . '-backend', 'window.wpmcs_schema = ' . UISchema::instance()->get_json() . ';', 'before');
        }
    }



    /**
     * Load admin CSS.
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function admin_enqueue_styles($hook = '') {
        // Menu icon as a currentColor mask so it matches the admin scheme without svg-painter's recolour flash.
        wp_add_inline_style('admin-menu', $this->menu_icon_css());

        if ( ! isset($this->hook_suffix) || empty($this->hook_suffix)) {
            return;
        } 
        $screen = get_current_screen();
        if (in_array($screen->id, $this->hook_suffix)) {
            wp_register_style($this->token.'-backend',
                esc_url($this->assets_url).'css/backend.css?nocache='.rand(0, 10000), array(), $this->version);
            wp_enqueue_style($this->token.'-backend');
        }

        if ($screen->id == 'plugins') { 
            wp_enqueue_style($this->token . '-deactivate-form', esc_url($this->assets_url) . 'css/deactivate.css', array(), $this->version);
        }
    }

    /**
     * CSS for the admin menu icon.
     * @since 1.5.0
     */
    private function menu_icon_css() {
        $icon = 'url(data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZGVmcz48Y2xpcFBhdGggaWQ9ImMiPjxwYXRoIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTAgMEgyMFYyMEgwWk0xMC41IDE1LjVhNSA1IDAgMSAwIDEwIDBhNSA1IDAgMSAwIC0xMCAwWiIvPjwvY2xpcFBhdGg+PC9kZWZzPjxwYXRoIGNsaXAtcGF0aD0idXJsKCNjKSIgZmlsbC1ydWxlPSJldmVub2RkIiBmaWxsPSJibGFjayIgZD0iTTUgMS41SDE1QTMuNSAzLjUgMCAwIDEgMTguNSA1VjE1QTMuNSAzLjUgMCAwIDEgMTUgMTguNUg1QTMuNSAzLjUgMCAwIDEgMS41IDE1VjVBMy41IDMuNSAwIDAgMSA1IDEuNVpNNS45IDEyLjlIMTMuMUEyLjYgMi42IDAgMCAwIDEzLjUgNy43M0EzLjkgMy45IDAgMCAwIDYuMSA2LjlBMyAzIDAgMCAwIDUuOSAxMi45WiIvPjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgZmlsbD0iYmxhY2siIGQ9Ik0xMS40IDE1LjVhNC4xIDQuMSAwIDEgMCA4LjIgMGE0LjEgNC4xIDAgMSAwIC04LjIgMFpNMTMuMTA0IDE0LjYyOEEyLjU1IDIuNTUgMCAwIDEgMTcuNzA4IDE0LjIyNUwxOC4zMTUgMTMuODc1TDE3LjUxOSAxNS4xNDRMMTYuMjM2IDE1LjA3NUwxNi44NDIgMTQuNzI1QTEuNTUgMS41NSAwIDAgMCAxNC4wNDMgMTQuOTdaTTE3Ljg5NiAxNi4zNzJBMi41NSAyLjU1IDAgMCAxIDEzLjI5MiAxNi43NzVMMTIuNjg1IDE3LjEyNUwxMy40ODEgMTUuODU2TDE0Ljc2NCAxNS45MjVMMTQuMTU4IDE2LjI3NUExLjU1IDEuNTUgMCAwIDAgMTYuOTU3IDE2LjAzWiIvPjwvc3ZnPgo=)';
        return '#adminmenu .toplevel_page_' . $this->token . '-admin-ui div.wp-menu-image::before{content:"";background-color:currentColor;-webkit-mask:' . $icon . ' center/20px no-repeat;mask:' . $icon . ' center/20px no-repeat;}';
    }

    /**
     * Show action links on the plugin screen.
     *
     * @param mixed $links Plugin Action links.
     *
     * @return array
     */
    public function plugin_action_links($links){
        $action_links = array(
            'getstarted' => '<a href="' . admin_url('admin.php?page=' . $this->token . '-admin-ui#/configure') . '">' . esc_html__('Get Started', 'media-cloud-sync') . '</a>',
            'settings' => '<a href="' . admin_url('admin.php?page=' . $this->token . '-admin-ui/') . '">' . esc_html__('Settings', 'media-cloud-sync') . '</a>',
        );

        // Shown until an add-on reports a valid license.
        if (!apply_filters('wpmcs_is_pro_licensed', false)) {
            $action_links['upgrade'] = '<a href="https://dudlewebs.com/product/media-cloud-sync/" target="_blank" style="color:#d54e21;font-weight:600;">' . esc_html__('Upgrade', 'media-cloud-sync') . '</a>';
        }

        return array_merge($action_links, $links);
    }




    /**
     * Add Admin Menu
     */
    public function add_menu() {
        $this->hook_suffix[] = add_menu_page(
            esc_html__('Media Cloud Sync', 'media-cloud-sync'),
            esc_html__('Media Cloud Sync', 'media-cloud-sync'),
            'manage_options',
            $this->token.'-admin-ui',
            array($this, 'adminUi'),
            'none',
            999
        );
     
        $this->hook_suffix[] = add_submenu_page(
            $this->token . '-admin-ui', 
            esc_html__('Dashboard', 'media-cloud-sync'), 
            esc_html__('Dashboard', 'media-cloud-sync'), 
            'manage_options', 
            $this->token . '-admin-ui' ,
            array($this, 'adminUi')
        );

        if(Utils::get_service()) {
            $this->hook_suffix[] = add_submenu_page(
                $this->token . '-admin-ui', 
                esc_html__('Settings', 'media-cloud-sync'), 
                esc_html__('Settings', 'media-cloud-sync'), 
                'manage_options', 
                $this->token . '-admin-ui#/settings' ,
                array($this, 'adminUi')
            );
        } else {
            $this->hook_suffix[] = add_submenu_page(
                $this->token . '-admin-ui', 
                esc_html__('Configure', 'media-cloud-sync'), 
                esc_html__('Configure', 'media-cloud-sync'), 
                'manage_options', 
                $this->token . '-admin-ui#/configure' ,
                array($this, 'adminUi')
            );
        }
    }

    /**
     * Admin notice when storage credentials are incomplete.
     * @since 1.3.11
     */
    public function storage_credentials_admin_notice() {
        if(!current_user_can('manage_options') || !Utils::get_service() || Utils::is_service_enabled()) {
            return;
        }

        $error = Utils::get_service_configuration_error();
        if(empty($error)) {
            return;
        }

        $settings_url = admin_url('admin.php?page=' . $this->token . '-admin-ui#/settings');
        $message      = esc_html($error) . ' ' . sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url($settings_url),
            esc_html__('Open Media Cloud Sync settings', 'media-cloud-sync')
        );

        Notices::dismissible('storage_credentials', $error, 'error', '<p>' . $message . '</p>');
    }

    /**
     * Calling view function for admin page components
     */
    public function adminUi() {
        echo wp_kses_post(sprintf(
            '<div id="%s_ui_root">
                <div class="%s_loader">
                    <h1>%s</h1>
                    <p>%s</p>
                </div>
            </div>', 
            $this->token,
            $this->token,
            esc_html__('Media Cloud Sync By Dudlewebs','media-cloud-sync'),
            esc_html__('Plugin is loading Please wait for a while..', 'media-cloud-sync')
        ));
       
    }


    /**
	 * Load the media assets
	 */
	public function load_media_assets() {
        if(!Utils::is_service_enabled()) {
            return;
        }

        /** CSS */
        wp_enqueue_style($this->token . '-media', esc_url($this->assets_url) . 'css/media.css', array(), $this->version);
        
        /** JS */
        wp_enqueue_script(
            $this->token . '-media', 
            esc_url($this->assets_url) . 'js/media.js', 
            array(
                'jquery',
                'media-views',
                'media-grid',
                'wp-util'
            ),
            $this->version, 
            true
        );

        // Localize a script.
        wp_localize_script(
            $this->token . '-media',
            $this->token . '_media_object',
            array(
                'file_details_nonce'    => wp_create_nonce('get_media_provider_details'),
                'admin_ajax_url'        => admin_url('admin-ajax.php'),
                'strings'               => array(
                    'provider'          => esc_html__("Provider: ", "media-cloud-sync"),
                    'region'            => esc_html__("Region: ", "media-cloud-sync"),
                    'access'            => esc_html__("Access: ", "media-cloud-sync"),
                    'access_private'    => esc_html__("Private", "media-cloud-sync"),
                    'access_public'     => esc_html__("Public", "media-cloud-sync"),
                )      
            )
        );
    }


    /**
     * Deactivation form
     * @since 1.0.2
     */
    public function wpmcs_deactivation_form() {
        $currentScreen = get_current_screen();
        $screenID = $currentScreen->id;
        if ($screenID == 'plugins') { 
            ?>
            <div id="dw-wpmcs-survey-form-wrap">
                <div id="dw-wpmcs-survey-form">
                    <h2>We Value Your Feedback</h2>
                    <p>We're sorry to see you go! Please let us know why you are deactivating this plugin. Your feedback is anonymous and will help us improve.</p>
                    <form method="POST">
                        <input name="plugin_name" type="hidden" value="<?= esc_attr($this->token); ?>" required>
                        <input name="website" type="hidden" value="<?= esc_url(get_site_url()); ?>" required>
                        <input name="title" type="hidden" value="<?= esc_attr(get_bloginfo('name')); ?>" required>
                        <input name="version" type="hidden" value="<?= esc_attr($this->version); ?>" required>
                        
                        <div class="dw-wpmcs-reason">
                            <label><input type="radio" name="reason" value="I\'m only deactivating temporarily"> I'm only deactivating temporarily</label>
                            <label><input type="radio" name="reason" value="I no longer need the plugin"> I no longer need the plugin</label>
                            <label><input type="radio" name="reason" value="I only needed the plugin for a short period"> I only needed the plugin for a short period</label>
                            <label><input type="radio" name="reason" value="I found a better plugin"> I found a better plugin</label>
                            <label><input type="radio" name="reason" value="Upgrading to PRO version"> Upgrading to PRO version</label>
                            <label><input type="radio" name="reason" value="Plugin doesn\'t meet my requirements"> Plugin doesn't meet my requirements</label>
                            <label><input type="radio" name="reason" value="Plugin broke my site"> Plugin broke my site</label>
                            <label><input type="radio" name="reason" value="Plugin suddenly stopped working"> Plugin suddenly stopped working</label>
                            <label><input type="radio" name="reason" value="I found a bug"> I found a bug</label>
                            <label><input type="radio" name="reason" value="Other"> Other</label>
                        </div>
                        
                        <div class="dw-wpmcs-comments" style="display:none;">
                            <textarea name="comments" placeholder="Please specify" rows="3"></textarea>
                            <p>Need help? <a href="https://dudlewebs.com/" target="_blank">Use live chat support</a></p>
                        </div>
                        
                        <p id="dw-wpmcs-error" class="dw-wpmcs-error">
                            <svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M8 2.2L14.6 13.3H1.4L8 2.2Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
                                <path d="M8 6.7V9.7" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                                <circle cx="8" cy="11.4" r="0.85" fill="currentColor"/>
                            </svg>
                            <span id="dw-wpmcs-error-text"></span>
                        </p>
                        <div class="dw-wpmcs-actions">
                            <button type="button" class="dw-button dw-skip" id="dw-wpmcs-skip">Skip & Deactivate</button>
                            <button type="button" class="dw-button dw-secondary" id="dw-wpmcs-cancel">Cancel</button>
                            <button type="submit" class="dw-button dw-primary" id="dw-wpmcs-deactivate">Submit & Deactivate</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php }
    }



    /**
	 * Add custom classes to the HTML body tag
	 *
	 * @param string $classes
	 *
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( ! $classes ) {
			$classes = array();
		} else {
			$classes = explode( ' ', $classes );
		}
		$classes[] = $this->token.'_page';
		/**
         *  Recommended way to target WP 3.8+
         *  http://make.wordpress.org/ui/2013/11/19/targeting-the-new-dashboard-design-in-a-post-mp6-world/
         * 
         */
		if ( version_compare( $GLOBALS['wp_version'], '3.8-alpha', '>' ) ) {
			if ( ! in_array( 'mp6', $classes ) ) {
				$classes[] = 'mp6';
			}
		}
		return implode( ' ', $classes );
	}
    

    /**
     * Ensures only one instance of Class is loaded or can be loaded.
     *
     * @return Main Class instance
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