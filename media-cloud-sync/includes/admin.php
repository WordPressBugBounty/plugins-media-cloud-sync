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

        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_styles'], 10, 1);
        // Load media scripts
        add_action( 'load-upload.php', [ $this, 'load_media_assets' ], 11 );

        // Plugin Deactivation Survey
        add_action('admin_footer', array($this, 'wpmcs_deactivation_form'));

        if (is_multisite()) {
            // Register multisite site creation hooks
            add_action('wp_initialize_site', [$this, 'create_multisite_table'], 10, 1);
            add_action('wpmu_new_blog', [$this, 'create_multisite_table'], 10, 1);
        }
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
    public function install($network_wide = false) {
        Db::handle_tables($network_wide);
        
        // Redirection on activation
        add_option($this->token.'_do_activation_redirect', true);

        //Protect directories
        $this->_protect_upload_dir();
    }

    /**
     * Create plugin specific upload directory
     * @since 1.0.0
     */
    public function create_plugin_dir() {
        //Protect directories
        $this->_protect_upload_dir();
    }

    /**
     * Protect Directory from external access.
     *
     * @access  private
     * @return  void
     * @since   1.3.0
     * @see \Dudlewebs\WPMCS\Admin::_protect_upload_dir
     */
    private function _protect_upload_dir(){
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
    public function deactivation(){
    }


    /**
     * Handle multisite site creation.
     */
    public function create_multisite_table($site) {
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
            wp_enqueue_script($this->token . '-backend', esc_url($this->assets_url) . 'js/backend.js', array('wp-i18n'), $this->version, true);

            // Localize a script.
            wp_localize_script(
                $this->token . '-backend',
                $this->token . '_object',
                array(
                    'api_nonce' => wp_create_nonce('wp_rest'),
                    'root' => rest_url($this->token . '/v1/'),
                    'pro_root' => rest_url($this->token . '/pro/v1/'),
                    'assets_url' => $this->assets_url,
                    'admin_url' => admin_url(),
                    'is_db_upgrade_required' => Upgrade::instance()->is_upgrade_needed(),
                    'is_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
                    'is_pro_active' => defined('WPMCS_PRO_VERSION'),
                )
            );
        }
    }



    /**
     * Load admin CSS.
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function admin_enqueue_styles($hook = '') {
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
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTczIiBoZWlnaHQ9IjEyMiIgdmlld0JveD0iMCAwIDE3MyAxMjIiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0xMzkuNTggNzYuMDA4MkMxNDYuNDQxIDc1LjgyMyAxNTEuOTAyIDc4LjgxNTggMTU2LjE3OSA4My44NzkzQzE2MS42OTUgOTAuNDA5OCAxNjEuMzQ5IDk4LjY5MjEgMTU1LjkyMyAxMDUuMzI0QzE1Mi40ODUgMTA5LjUyNiAxNDYuOTI5IDExMC42OTMgMTQyLjIwNyAxMDguMTZDMTM5LjU4OSAxMDYuNzU1IDEzNy45NDIgMTA0LjUxIDEzNy4wMzEgMTAxLjcwMUMxMzYuNzc0IDEwMC45MDYgMTM3LjA3MiAxMDAuNjE1IDEzNy44NSAxMDAuNjMxQzEzOC41OTggMTAwLjY0NyAxMzkuMzQ3IDEwMC42MzUgMTQwLjQ0NCAxMDAuNjM1QzE0MS43MDkgMTAwLjUyMSAxNDMuMTc4IDEwMC44MTggMTQ0LjY3MiAxMDEuNThDMTQ3Ljc2NyAxMDMuMTU5IDE1Mi4xNTcgMTAxLjY1OCAxNTMuOTY1IDk4LjYwNTlDMTU2LjA3OSA5NS4wMzcgMTU1LjQxMSA5MS4wMDY3IDE1Mi4zMjkgODguNzM2N0MxNDguNTkgODUuOTgyOSAxNDQuNDg0IDg2LjQzNzggMTQxLjM0NyA4OS44OTg4QzEzOS41NzUgOTEuODUzNyAxMzcuMzYzIDkzLjAwNDMgMTM0LjcwMiA5My4yMDk0QzEzMC4zNjUgOTMuNTQzNiAxMjcuMzE1IDg5Ljk3OTIgMTI4LjEzMyA4NS41Mzg1QzEyOS4xMjUgODAuMTU4NSAxMzMuOTE2IDc2LjE2MTEgMTM5LjU4IDc2LjAwODJaIiBmaWxsPSJibGFjayIvPgo8cGF0aCBkPSJNMTQyLjIxNyA5NC4xMzMyQzE0Mi42MjQgOTQuMTMzMiAxNDIuOTU1IDk0LjQ2MzMgMTQyLjk1NSA5NC44NzA1Vjk5LjY3OTFDMTQyLjk1NSAxMDAuMDg2IDE0Mi42MjQgMTAwLjQxNSAxNDIuMjE3IDEwMC40MTVIMTM3LjU1OEMxMzcuMTUxIDEwMC40MTUgMTM2LjgyMSAxMDAuMDg2IDEzNi44MiA5OS42NzkxVjk0Ljg3MDVDMTM2LjgyIDk0LjQ2MzMgMTM3LjE1IDk0LjEzMzIgMTM3LjU1OCA5NC4xMzMySDE0Mi4yMTdaIiBmaWxsPSJibGFjayIvPgo8cGF0aCBkPSJNMTQ5LjE5NyA5NC4xMzQyQzE0OS42MDUgOTQuMTM0MiAxNDkuOTM2IDk0LjQ2NDMgMTQ5LjkzNiA5NC44NzE1Vjk2LjU3NTZDMTQ5LjkzNiA5Ni45ODI4IDE0OS42MDUgOTcuMzEyOSAxNDkuMTk3IDk3LjMxMjlIMTQ3LjU2OUMxNDcuMTYxIDk3LjMxMjkgMTQ2LjgzMSA5Ni45ODI4IDE0Ni44MzEgOTYuNTc1NlY5NC44NzE1QzE0Ni44MzEgOTQuNDY0MyAxNDcuMTYxIDk0LjEzNDIgMTQ3LjU2OSA5NC4xMzQySDE0OS4xOTdaIiBmaWxsPSJibGFjayIvPgo8cGF0aCBkPSJNMTQ1LjMzMiA5NC4xMzMyQzE0NS43MzkgOTQuMTMzNSAxNDYuMDcgOTQuNDYzNSAxNDYuMDcgOTQuODcwNVY5NS44MDUxQzE0Ni4wNyA5Ni4yMTIxIDE0NS43MzkgOTYuNTQyMSAxNDUuMzMyIDk2LjU0MjRIMTQ0LjQ1NEMxNDQuMDQ3IDk2LjU0MjQgMTQzLjcxNyA5Ni4yMTIzIDE0My43MTcgOTUuODA1MVY5NC44NzA1QzE0My43MTcgOTQuNDYzMyAxNDQuMDQ3IDk0LjEzMzIgMTQ0LjQ1NCA5NC4xMzMySDE0NS4zMzJaIiBmaWxsPSJibGFjayIvPgo8cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTE3MyA5Mi41MDAxQzE3MyAxMDguMjQgMTYwLjI0IDEyMSAxNDQuNSAxMjFDMTI4Ljc2IDEyMSAxMTYgMTA4LjI0IDExNiA5Mi41MDAxQzExNiA3Ni43NiAxMjguNzYgNjQuMDAwMSAxNDQuNSA2NC4wMDAxQzE2MC4yNCA2NC4wMDAxIDE3MyA3Ni43NiAxNzMgOTIuNTAwMVpNMTQ0LjUgMTE0QzE1Ni4zNzQgMTE0IDE2NiAxMDQuMzc0IDE2NiA5Mi41MDAxQzE2NiA4Ni43MDIyIDE2My43MDUgODEuNDQwMyAxNTkuOTc0IDc3LjU3MzNDMTU2LjA2NCA3My41MjA3IDE1MC41NzYgNzEuMDAwMSAxNDQuNSA3MS4wMDAxQzEzMi42MjYgNzEuMDAwMSAxMjMgODAuNjI2IDEyMyA5Mi41MDAxQzEyMyAxMDAuNTQzIDEyNy40MTYgMTA3LjU1NCAxMzMuOTU2IDExMS4yNDFDMTM3LjA3MSAxMTIuOTk4IDE0MC42NjkgMTE0IDE0NC41IDExNFoiIGZpbGw9ImJsYWNrIi8+CjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNMzUuMjY1OSA0My40MTY4QzM3LjI3OTMgMzEuMjg3IDQzLjUzMjQgMjAuMjY1NSA1Mi45MTE3IDEyLjMxNDdDNjIuMjkxIDQuMzYzOTEgNzQuMTg3NyAwIDg2LjQ4MzUgMEM5OC43NzkzIDAgMTEwLjY3NiA0LjM2MzkxIDEyMC4wNTUgMTIuMzE0N0MxMjkuNDM1IDIwLjI2NTUgMTM1LjY4OCAzMS4yODcgMTM3LjcwMSA0My40MTY4QzE0Ny42NjIgNDQuMzU5IDE1Ni44NzcgNDkuMDk5NiAxNjMuNDM1IDU2LjY1NTFDMTY5Ljk5NCA2NC4yMTA2IDE3My4zOTIgNzQuMDAwOSAxNzIuOTI0IDgzLjk5NDlDMTcyLjQ1NyA5My45ODg5IDE2OC4xNiAxMDMuNDE5IDE2MC45MjUgMTEwLjMzQzE1My42OSAxMTcuMjQgMTQ0LjA3MiAxMjEuMSAxMzQuMDY3IDEyMS4xMDhIMzguODk5NkMyOC44OTQ3IDEyMS4xIDE5LjI3NyAxMTcuMjQgMTIuMDQyIDExMC4zM0M0LjgwNjk1IDEwMy40MTkgMC41MDk5NzUgOTMuOTg4OSAwLjA0MjU0OTUgODMuOTk0OUMtMC40MjQ4NzYgNzQuMDAwOSAyLjk3MzEzIDY0LjIxMDYgOS41MzE1OSA1Ni42NTUxQzE2LjA5IDQ5LjA5OTYgMjUuMzA1NSA0NC4zNTkgMzUuMjY1OSA0My40MTY4Wk00Mi45NzE1IDQ1LjkyNTJDNDQuNjgyNyAzNS42MDkgNDkuOTk3IDI2LjIzNTQgNTcuOTY4MiAxOS40NzM1QzY1LjkzOTQgMTIuNzExNSA3Ni4wNTAxIDkuMDAwMSA4Ni40OTk5IDkuMDAwMUM5Ni45NDk4IDkuMDAwMSAxMDcuMDYgMTIuNzExNSAxMTUuMDMyIDE5LjQ3MzVDMTIzLjAwMyAyNi4yMzU0IDEyOC4zMTcgMzUuNjA5IDEzMC4wMjggNDUuOTI1MkMxMzguNDkzIDQ2LjcyNjUgMTQ2LjMyNSA1MC43NTgzIDE1MS44OTkgNTcuMTg0QzE1Ni44NDUgNjIuODg2MSAxNTkuNjc2IDcwLjA4NDkgMTU5Ljk3NCA3Ny41NzMzQzE2My43MDUgODEuNDQwMyAxNjYgODYuNzAyMiAxNjYgOTIuNTAwMUMxNjYgMTA0LjM3NCAxNTYuMzc0IDExNCAxNDQuNSAxMTRDMTQwLjY2OSAxMTQgMTM3LjA3MSAxMTIuOTk4IDEzMy45NTYgMTExLjI0MUMxMzEuNjY4IDExMS43NCAxMjkuMzE3IDExMS45OTggMTI2Ljk0IDExMkg0Ni4wNTk3QzM3LjU1NjggMTExLjk5MyAyOS4zODI5IDEwOC43MSAyMy4yMzQxIDEwMi44MzNDMTcuMDg1MiA5Ni45NTYxIDEzLjQzMzMgODguOTM1NyAxMy4wMzYxIDgwLjQzNkMxMi42Mzg4IDcxLjkzNjMgMTUuNTI2NyA2My42MDk4IDIxLjEwMDYgNTcuMTg0QzI2LjY3NDQgNTAuNzU4MyAzNC41MDY0IDQ2LjcyNjUgNDIuOTcxNSA0NS45MjUyWiIgZmlsbD0iYmxhY2siLz4KPHBhdGggZD0iTTkzLjUyMTEgNDEuMTc0OEw3Ny4xNDE3IDE4LjY1MDJDNzYuOTk1MyAxOC40NDk1IDc2LjgwMjUgMTguMjg1OCA3Ni41NzkyIDE4LjE3MjhDNzYuMzU1OSAxOC4wNTk4IDc2LjEwODUgMTguMDAwNiA3NS44NTc0IDE4LjAwMDJINzUuODUzNkM3NS42MDE1IDE4LjAwMDcgNzUuMzUzMiAxOC4wNjA0IDc1LjEyOTMgMTguMTc0M0M3NC45MDU0IDE4LjI4ODIgNzQuNzEyMyAxOC40NTMxIDc0LjU2NjEgMTguNjU1Mkw1OC4yOTI0IDQxLjE3OTdDNTguMTI0IDQxLjQxMjUgNTguMDI0MSA0MS42ODY1IDU4LjAwMzggNDEuOTcxNUM1Ny45ODM1IDQyLjI1NjYgNTguMDQzNSA0Mi41NDE2IDU4LjE3NzMgNDIuNzk1MkM1OC4zMTAzIDQzLjA0OTIgNTguNTExOCA0My4yNjIxIDU4Ljc1OTggNDMuNDEwN0M1OS4wMDc4IDQzLjU1OTIgNTkuMjkyNyA0My42Mzc1IDU5LjU4MyA0My42MzdINjcuMjQxOEw2Ny4yNDEyIDc2LjE0MUM2Ny4yNDExIDc2LjM0NTQgNjcuMjgxOSA3Ni41NDc3IDY3LjM2MTQgNzYuNzM2NUM2Ny40NDA5IDc2LjkyNTMgNjcuNTU3NCA3Ny4wOTY5IDY3LjcwNDMgNzcuMjQxM0M2Ny44NTEyIDc3LjM4NTggNjguMDI1NiA3Ny41MDA0IDY4LjIxNzUgNzcuNTc4NUM2OC40MDk0IDc3LjY1NjYgNjguNjE1MSA3Ny42OTY3IDY4LjgyMjggNzcuNjk2Nkw4Mi45OTMxIDc3LjY5NTlDODMuMjAwOSA3Ny42OTYgODMuNDA2NyA3Ny42NTU4IDgzLjU5ODcgNzcuNTc3NkM4My43OTA2IDc3LjQ5OTQgODMuOTY1MSA3Ny4zODQ3IDg0LjExMiA3Ny4yNDAxQzg0LjI1ODkgNzcuMDk1NSA4NC4zNzUzIDc2LjkyMzkgODQuNDU0OCA3Ni43MzVDODQuNTM0MiA3Ni41NDYxIDg0LjU3NSA3Ni4zNDM2IDg0LjU3NDggNzYuMTM5MlY0My42Mzc2SDkyLjIzNjFDOTIuODI3IDQzLjYzNzYgOTMuMzY5NiA0My4zMDk4IDkzLjY0MjQgNDIuNzkyMUM5My43NzU3IDQyLjUzNzcgOTMuODM0OSA0Mi4yNTIgOTMuODEzNSA0MS45NjY2QzkzLjc5MjEgNDEuNjgxMiA5My42OTA5IDQxLjQwNzEgOTMuNTIxMSA0MS4xNzQ4WiIgZmlsbD0iYmxhY2siLz4KPHBhdGggZD0iTTgyLjE0NDkgODUuNDcxNUw5Ni44MzM4IDEwMi4xMzZDOTYuOTY1IDEwMi4yODUgOTcuMTM3OSAxMDIuNDA2IDk3LjMzODIgMTAyLjQ5Qzk3LjUzODQgMTAyLjU3MyA5Ny43NjAzIDEwMi42MTcgOTcuOTg1NSAxMDIuNjE3Qzk4LjIxMTYgMTAyLjYxNyA5OC40Mzc3IDEwMi41NzMgOTguNjM4NSAxMDIuNDg4Qzk4LjgzOTMgMTAyLjQwNCA5OS4wMTI0IDEwMi4yODIgOTkuMTQzNSAxMDIuMTMzTDExMy43MzggODUuNDY3OUMxMTMuODg5IDg1LjI5NTYgMTEzLjk3OCA4NS4wOTI5IDExMy45OTYgODQuODgyQzExNC4wMTUgODQuNjcxMSAxMTMuOTYxIDg0LjQ2MDMgMTEzLjg0MSA4NC4yNzI2QzExMy43MjIgODQuMDg0NyAxMTMuNTQxIDgzLjkyNzIgMTEzLjMxOCA4My44MTczQzExMy4wOTYgODMuNzA3NCAxMTIuODQxIDgzLjY0OTUgMTEyLjU4IDgzLjY0OThIMTA1LjcxMkwxMDUuNzEyIDU5LjYwMTZDMTA1LjcxMyA1OS40NTA1IDEwNS42NzYgNTkuMzAwNyAxMDUuNjA1IDU5LjE2MTFDMTA1LjUzMyA1OS4wMjE0IDEwNS40MjkgNTguODk0NSAxMDUuMjk3IDU4Ljc4NzZDMTA1LjE2NSA1OC42ODA3IDEwNS4wMDkgNTguNTk1OSAxMDQuODM3IDU4LjUzODFDMTA0LjY2NSA1OC40ODAzIDEwNC40OCA1OC40NTA3IDEwNC4yOTQgNTguNDUwOEw5MS41ODYyIDU4LjQ1MTJDOTEuMzk5OSA1OC40NTEyIDkxLjIxNTQgNTguNDgwOSA5MS4wNDMyIDU4LjUzODhDOTAuODcxMSA1OC41OTY3IDkwLjcxNDYgNTguNjgxNSA5MC41ODI5IDU4Ljc4ODVDOTAuNDUxMiA1OC44OTU1IDkwLjM0NjcgNTkuMDIyNCA5MC4yNzU1IDU5LjE2MjJDOTAuMjA0MiA1OS4zMDIgOTAuMTY3NyA1OS40NTE4IDkwLjE2NzggNTkuNjAzVjgzLjY0OTRIODMuMjk3MkM4Mi43NjczIDgzLjY0OTQgODIuMjgwOCA4My44OTE5IDgyLjAzNjEgODQuMjc0OUM4MS45MTY2IDg0LjQ2MzEgODEuODYzNSA4NC42NzQ1IDgxLjg4MjcgODQuODg1N0M4MS45MDE5IDg1LjA5NjggODEuOTkyNiA4NS4yOTk2IDgyLjE0NDkgODUuNDcxNVoiIGZpbGw9ImJsYWNrIi8+Cjwvc3ZnPgo=',
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

        if(Utils::is_service_enabled()) {
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
                        
                        <p id="dw-wpmcs-error" class="dw-wpmcs-error"></p>
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