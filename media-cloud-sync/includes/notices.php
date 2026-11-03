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

    public function __construct() {
        add_action('admin_post_' . WPMCS_TOKEN . '_dismiss_notice', [$this, 'dismiss_notice']);
    }

    /**
     * Prints an admin notice with a Dismiss link. Dismissal is saved per user for this $situation,
     * so the notice returns once the situation changes (e.g. a different error or bucket).
     * @since 1.5.0
     * @param string $id        Notice id.
     * @param string $situation What the notice is about right now.
     * @param string $type      error|warning|info|success.
     * @param string $html      Message HTML (post-safe markup).
     */
    public static function dismissible($id, $situation, $type, $html) {
        $hash      = md5((string) $situation);
        $dismissed = (array) get_user_meta(get_current_user_id(), WPMCS_TOKEN . '_dismissed_notices', true);
        if (($dismissed[$id] ?? '') === $hash) {
            return;
        }
        $url = wp_nonce_url(add_query_arg([
            'action' => WPMCS_TOKEN . '_dismiss_notice',
            'notice' => rawurlencode($id),
            'hash'   => $hash,
        ], admin_url('admin-post.php')), WPMCS_TOKEN . '_dismiss_notice_' . $id);

        printf(
            '<div class="notice notice-%1$s">%2$s<p><a href="%3$s">%4$s</a></p></div>',
            esc_attr($type),
            wp_kses_post($html),
            esc_url($url),
            esc_html__('Dismiss', 'media-cloud-sync')
        );
    }

    public function dismiss_notice() {
        $id = sanitize_key(wp_unslash($_GET['notice'] ?? ''));
        check_admin_referer(WPMCS_TOKEN . '_dismiss_notice_' . $id);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'media-cloud-sync'));
        }
        $dismissed      = (array) get_user_meta(get_current_user_id(), WPMCS_TOKEN . '_dismissed_notices', true);
        $dismissed[$id] = sanitize_key(wp_unslash($_GET['hash'] ?? ''));
        update_user_meta(get_current_user_id(), WPMCS_TOKEN . '_dismissed_notices', array_filter($dismissed));
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
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
        echo wp_kses_post(sprintf('<div class="notice notice-error is-dismissible">%s</div>', wpautop($message)));
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
        echo wp_kses_post(sprintf('<div class="notice notice-error is-dismissible">%s</div>', wpautop($message)));
    }
}
