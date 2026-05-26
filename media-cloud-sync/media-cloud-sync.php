<?php
/**
 * Plugin Name: Media Cloud Sync
 * Version: 1.3.10
 * Description: Media Cloud Sync helps to sync your wordpress media to the cloud based services like Amazon S3, DigitalOcean Spaces, Google Cloud Storage, Cloudflare R2 and S3 Compatible Services.
 * Author: Dudlewebs
 * Author URI: http://dudlewebs.com
 * License: GPLv2 or later
 * Requires at least: 5.2
 * Tested up to: 7.0
 * Requires PHP: 8.1
 * Text Domain: media-cloud-sync
 */
 
defined('ABSPATH') || exit;

if (!defined('WPMCS_FILE')) {
    define('WPMCS_FILE', __FILE__);
}

define('WPMCS_VERSION', '1.3.10');
define('WPMCS_PLUGIN_NAME', 'Media Cloud Sync');

define('WPMCS_TOKEN', 'wpmcs');
define('WPMCS_PATH', plugin_dir_path(WPMCS_FILE));
define('WPMCS_URL', plugins_url('/', WPMCS_FILE));

define('WPMCS_ASSETS_PATH', WPMCS_PATH . 'assets/');
define('WPMCS_ASSETS_URL', WPMCS_URL . 'assets/');
define('WPMCS_INCLUDES_PATH', WPMCS_PATH . 'includes/');
define('WPMCS_SDK_PATH', WPMCS_INCLUDES_PATH . 'sdk/');

define('WPMCS_ITEM_TABLE', WPMCS_TOKEN.'_items');
define('WPMCS_DB_VERSION', '1.0.5');
// Force DB upgrade using UI if needed
define('WPMCS_DB_UPGRADE_VERSION', '1.0.0');

add_action('plugins_loaded', 'wpmcs_load_plugin_textdomain');

require WPMCS_INCLUDES_PATH . 'main.php';

/**
 * Load Plugin textdomain.
 *
 * Load gettext translate for Plugin text domain.
 *
 * @return void
 * @since 1.0.0
 *
 */
function wpmcs_load_plugin_textdomain(){
    load_plugin_textdomain('media-cloud-sync');
}