<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Db {
    private static $instance = null;
    private string $assets_url;
    private string $version;
    private string $token;


    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url = WPMCS_ASSETS_URL;
        $this->version    = WPMCS_VERSION;
        $this->token      = WPMCS_TOKEN;
    }

    /**
     * Get table name with correct DB prefix (multisite-aware)
     *
     * @param int|null $blog_id Optional. Blog ID for multisite.
     * @return string
     */
    public static function get_table_name( $blog_id = null ) {
        global $wpdb;
        $prefix = is_multisite() ? $wpdb->get_blog_prefix( $blog_id ?? get_current_blog_id() ) : $wpdb->prefix;
        return $prefix . WPMCS_ITEM_TABLE;
    }

    /**
     * Create Database Table
     * 
     */
    public function create_table() {
        global $wpdb;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_name = self::get_table_name();
        $queries = array();
        $charset_collate = $wpdb->get_charset_collate();
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $queries[] = "
                CREATE TABLE {$table_name} (
                    id BIGINT(20) NOT NULL AUTO_INCREMENT,
                    provider VARCHAR(18) NOT NULL,
                    region VARCHAR(255) NOT NULL,
                    storage VARCHAR(255) NOT NULL,
                    source_id BIGINT(20) NOT NULL,
                    source_path VARCHAR(1024) NOT NULL,
                    source_type VARCHAR(18) NOT NULL,
                    url VARCHAR(1024) NOT NULL,
                    `key` VARCHAR(1024) NOT NULL,
                    original_source_path VARCHAR(1024) NOT NULL,
                    original_key VARCHAR(1024) NOT NULL,
                    is_private TINYINT(1) NOT NULL DEFAULT 0,
                    extra LONGTEXT DEFAULT NULL,

                    PRIMARY KEY (id),

                    UNIQUE KEY uidx_url (url(190), id),
                    UNIQUE KEY uidx_key (`key`(190), id),
                    UNIQUE KEY uidx_source_path (source_path(190), id),
                    UNIQUE KEY uidx_original_source_path (original_source_path(190), id),
                    UNIQUE KEY uidx_original_key (original_key(190), id),
                    KEY idx_item_lookup (
                        source_id,
                        source_type,
                        provider,
                        storage,
                        region
                    ),
                    KEY idx_source_path_provider (
                        provider,
                        storage,
                        region,
                        source_path(190)
                    )
                ) $charset_collate;";
        } 
        dbDelta( $queries );
    }

    /**
     * Create tables across all sites (on activation)
     */
    public static function handle_tables($network_wide = false) {
        // If network-wide activation, create tables for all sites in multisite
        if (is_multisite() && $network_wide) {
            $sites = get_sites(['number' => 1000, 'fields' => 'ids']); // Adjust number as needed
            // If no sites found, return early
            if (is_wp_error($sites) || !is_array($sites) || empty($sites)) {
                return;
            }
            
            // Create table for each site in multisite
            foreach ($sites as $site_id) {
                switch_to_blog($site_id);
                self::instance()->create_table();
                restore_current_blog();
            }
        } else {
            self::instance()->create_table();
        }
    }

    /**
     * Database Upgrade 
     * @since 1.0.0
     */
    public function do_database_upgrade() {
        global $wpdb;
        $table_name         = self::get_table_name();
        $current_version    = get_option( $this->token.'_db_version' );
        $latest_version     = WPMCS_DB_VERSION;

        if ( $current_version === false ) {
            $current_version = $latest_version;
        }

        /**
         * If DB version below 1.0.1
         * @since 1.2.11
         * 
         * This is to handle the migration of Google Cloud credentials from file to JSON string.
         */
        if (version_compare($current_version, '1.0.1', '<')) {
            $credentials = Utils::get_credentials();
            if (isset($credentials['service']) && $credentials['service'] === 'gcloud') {
                $config = $credentials['config'] ?? null;
                if($config) {
                    $config_json_path = $config['config_json']['path'] ?? null;
                    if (!empty($config_json_path) && file_exists($config_json_path)) {
                        $config_json = file_get_contents($config_json_path);
                        if (!empty($config_json)) {
                            $credentials['config']['config_json'] = $config_json;
                            Utils::update_credentials($credentials);
                            $updated = Utils::update_option('credentials', $credentials, Schema::getConstant('GLOBAL_SETTINGS_KEY'));
                            if ($updated) {
                                unlink($config_json_path);
                            }
                        }
                    }
                }
            }
        }

        /**
         * If DB version below 1.0.2
         * 
         * @since 1.2.12
         * This is to handle the migration of media library counter from the global settings to the specific media library key.
         */
        if (version_compare($current_version, '1.0.2', '<')) {
            $settings_key       = Schema::getConstant('COUNTER_KEY');
            $media_library      = Utils::get_option( '', [], $settings_key );
            if (is_array($media_library) && !empty($media_library)) {
                Utils::update_option('', ['media_library' => $media_library ?? []], $settings_key);
            }
        }


        /**
         * If DB version below 1.0.3
         * 
         * @since 1.2.13
         * This is to handle the migration of media library counter from the global settings to the specific media library key.
         * Re-Create plugin specific upload directory
         */
        if (version_compare($current_version, '1.0.3', '<')) {
            Counter::instance()->fetch_and_update();
        }

        /**
         * If DB version below 1.0.4
         * 
         * @since 1.3.1
         * This is to handle the removal of htaccess from uploads folder and adding htaccess to plugin specific upload directory
         */
        if (version_compare($current_version, '1.0.4', '<')) {
            $upload_dir = wp_upload_dir();
            $upload_base = $upload_dir['basedir'];

            if (is_dir($upload_base)) {
                $files_to_delete = array(
                    $upload_base . '/.htaccess',
                    $upload_base . '/' . Schema::getConstant('UPLOADS') . '/.htaccess',
                    $upload_base . '/' . Schema::getConstant('UPLOADS') .'/index.php',
                );
                foreach ($files_to_delete as $file) {
                    if (file_exists($file)) {
                        @unlink($file); // deletes the file
                    }
                }
            }

            // Re-Create plugin specific upload directory
            do_action('wpmcs_create_plugin_dir');
        }


        /**
         * If DB version below 1.0.5
         *
         * @since 1.3.5
         * Schema adjustments:
         * - Add original_source_path AFTER `key`
         * - Add original_key AFTER original_source_path
         * - Add required indexes
         */
        if ( version_compare( $current_version, '1.0.5', '<' ) ) {
            global $wpdb;

            $table_name = self::get_table_name();

            /**
             * 1. Add original_source_path AFTER `key`
             */
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table_name} LIKE %s",
                    'original_source_path'
                )
            );

            if ( ! $exists ) {
                $wpdb->query(
                    "ALTER TABLE {$table_name}
                    ADD COLUMN original_source_path VARCHAR(1024) NOT NULL
                    AFTER `key`"
                );
            }

            /**
             * 2. Add original_key AFTER original_source_path
             */
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$table_name} LIKE %s",
                    'original_key'
                )
            );

            if ( ! $exists ) {
                $wpdb->query(
                    "ALTER TABLE {$table_name}
                    ADD COLUMN original_key VARCHAR(1024) NOT NULL
                    AFTER original_source_path"
                );
            }

            /**
             * 3. Unique index for original_source_path
             */
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                    'uidx_original_source_path'
                )
            );

            if ( ! $exists ) {
                $wpdb->query(
                    "ALTER TABLE {$table_name}
                    ADD UNIQUE KEY uidx_original_source_path (original_source_path(190), id)"
                );
            }

            /**
             * 4. Unique index for original_key
             */
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                    'uidx_original_key'
                )
            );

            if ( ! $exists ) {
                $wpdb->query(
                    "ALTER TABLE {$table_name}
                    ADD UNIQUE KEY uidx_original_key (original_key(190), id)"
                );
            }

            /**
             * 5. Item lookup index (get by source_id)
             */
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                    'idx_item_lookup'
                )
            );

            if ( ! $exists ) {
                $wpdb->query(
                    "ALTER TABLE {$table_name}
                    ADD KEY idx_item_lookup (
                        source_id,
                        source_type,
                        provider,
                        storage,
                        region
                    )"
                );
            }

            /**
             * 6. Provider-aware source_path prefix index
             */
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                    'idx_source_path_provider'
                )
            );

            if ( ! $exists ) {
                $wpdb->query(
                    "ALTER TABLE {$table_name}
                    ADD KEY idx_source_path_provider (
                        provider,
                        storage,
                        region,
                        source_path(190)
                    )"
                );
            }

            // Update upgrade version option only for who had previous version to trigger initial upgrade tracking
            update_option( $this->token . '_db_upgrade_version', '0.0.0', false );
        }



        update_option( $this->token.'_db_version', $latest_version, true );
    }

    /**
     * Ensures only one instance of Class is loaded or can be loaded.
     *
     * @return Db Class instance
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