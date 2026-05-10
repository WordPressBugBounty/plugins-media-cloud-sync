<?php
/**
 * Plugin Name: Media Cloud Sync
 * Version: 1.3.9
 * Description: Media Cloud Sync helps to sync your wordpress media to the cloud based services like Amazon S3, DigitalOcean Spaces, Google Cloud Storage, Cloudflare R2 and S3 Compatible Services.
 * Author: Dudlewebs
 * Author URI: http://dudlewebs.com
 * License: GPLv2 or later
 * Requires at least: 5.2
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * Text Domain: media-cloud-sync
 */
 
defined('ABSPATH') || exit;

if (!defined('WPMCS_FILE')) {
    define('WPMCS_FILE', __FILE__);
}

define('WPMCS_VERSION', '1.3.9');
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

if (!version_compare(PHP_VERSION, '8.1', '>=')) {
    add_action('admin_notices', 'wpmcs_php_version_check_fail');
    return;
} elseif (!version_compare(get_bloginfo('version'), '5.2', '>=')) {
    add_action('admin_notices', 'wpmcs_wp_version_check_fail');
    return;
} else {
    //include services
    require_once WPMCS_SDK_PATH . 's3/aws-autoloader.php';
    require_once WPMCS_SDK_PATH . 'google/autoload.php';

    require WPMCS_INCLUDES_PATH . 'main.php';
}


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

/**
 * Plugin admin notice for minimum PHP version.
 *
 * Warning when the site doesn't have the minimum required PHP version.
 *
 * @return void
 * @since 1.0.0
 *
 */
function wpmcs_php_version_check_fail(){
    /* translators: %s: PHP version. */
    $message = sprintf(esc_html__('%1$s requires PHP version %2$s+, plugin is currently not running.', 'media-cloud-sync'), WPMCS_PLUGIN_NAME, '8.1');
    $html_message = sprintf('<div class="error">%s</div>', wpautop($message));
    echo wp_kses_post($html_message);
}

/**
 * Plugin admin notice for minimum WordPress version.
 *
 * Warning when the site doesn't have the minimum required WordPress version.
 *
 * @return void
 * @since 1.0.0
 *
 */
function wpmcs_wp_version_check_fail(){
    /* translators: %s: WordPress version. */
    $message = sprintf(esc_html__('%1$s requires WordPress version %2$s+. Because you are using an earlier version, the plugin is currently not running.', 'media-cloud-sync'), WPMCS_PLUGIN_NAME, '5.2');
    $html_message = sprintf('<div class="error">%s</div>', wpautop($message));
    echo wp_kses_post($html_message);
}