<?php

namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Handles plugin bootstrap admin notices.
 *
 * @since 1.3.11
 */
class Notices {
    /**
     * Instance.
     *
     * @var Notices|null
     */
    private static $instance = null;

    /**
     * Retrieves the singleton instance of the class.
     *
     * @return self
     * @since 1.3.11
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Check minimum PHP/WP requirements and queue an admin notice if missing.
     *
     * @return bool
     * @since 1.3.11
     */
    public function is_environment_compatible() {
        if (!version_compare(PHP_VERSION, '8.1', '>=')) {
            add_action('admin_notices', [$this, 'php_version_check_fail']);
            return false;
        }

        if (!version_compare(get_bloginfo('version'), '5.9', '>=')) {
            add_action('admin_notices', [$this, 'wp_version_check_fail']);
            return false;
        }

        return true;
    }

    /**
     * Plugin admin notice for minimum PHP version.
     *
     * @return void
     * @since 1.3.11
     */
    public function php_version_check_fail() {
        /* translators: 1: Plugin name, 2: PHP version. */
        $message = sprintf(esc_html__('%1$s requires PHP version %2$s+, plugin is currently not running.', 'media-cloud-sync'), WPMCS_PLUGIN_NAME, '8.1');
        echo wp_kses_post(sprintf('<div class="error">%s</div>', wpautop($message)));
    }

    /**
     * Plugin admin notice for minimum WordPress version.
     *
     * @return void
     * @since 1.3.11
     */
    public function wp_version_check_fail() {
        /* translators: 1: Plugin name, 2: WordPress version. */
        $message = sprintf(esc_html__('%1$s requires WordPress version %2$s+. Because you are using an earlier version, the plugin is currently not running.', 'media-cloud-sync'), WPMCS_PLUGIN_NAME, '5.9');
        echo wp_kses_post(sprintf('<div class="error">%s</div>', wpautop($message)));
    }
}
