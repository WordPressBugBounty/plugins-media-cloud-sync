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

    // Temporary (1.5.1 hard cut): remove PRO_MIN_VERSION, PRO_FILE, guard_outdated_pro() and outdated_pro_notice() once PRO 1.1.0 is no longer in use.
    // Oldest PRO that works with this version (older ones call code that moved into PRO).
    const PRO_MIN_VERSION = '1.1.1';
    const PRO_FILE = 'media-cloud-sync-pro/media-cloud-sync-pro.php';

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
        // Before PRO's own boot (plugins_loaded, priority 0). Temporary; see PRO_MIN_VERSION.
        add_action('plugins_loaded', [$this, 'guard_outdated_pro'], -10);
    }

    /**
     * Keeps an outdated PRO from running against this version, but leaves its updater working.
     * Never updates it: the notice links to WordPress's own update screen.
     * Temporary: remove with PRO_MIN_VERSION in a later version.
     * @since 1.5.1
     */
    public function guard_outdated_pro() {
        if (!defined('WPMCS_PRO_VERSION') || version_compare(WPMCS_PRO_VERSION, self::PRO_MIN_VERSION, '>=')) {
            return;
        }

        if (class_exists(__NAMESPACE__ . '\\ProMain')) {
            remove_action('plugins_loaded', [ProMain::instance(), 'boot'], 0);
        }
        if (class_exists(__NAMESPACE__ . '\\ProLicense')) {
            ProLicense::instance();
        }

        // Once per outdated version, so the update offer shows now rather than at the next check.
        $flag = WPMCS_TOKEN . '_pro_update_check';
        if (get_site_option($flag) !== WPMCS_PRO_VERSION) {
            delete_site_transient('update_plugins');
            update_site_option($flag, WPMCS_PRO_VERSION);
        }

        add_action('admin_notices', [$this, 'outdated_pro_notice']);
        add_action('network_admin_notices', [$this, 'outdated_pro_notice']);
    }

    /**
     * Outdated PRO notice; goes away once PRO is updated.
     * Temporary: remove with PRO_MIN_VERSION in a later version.
     * @since 1.5.1
     */
    public function outdated_pro_notice() {
        if (!current_user_can('update_plugins')) {
            return;
        }

        $url = wp_nonce_url(
            self_admin_url('update.php?action=upgrade-plugin&plugin=' . rawurlencode(self::PRO_FILE)),
            'upgrade-plugin_' . self::PRO_FILE
        );

        /* translators: 1: PRO version required, 2: installed PRO version. */
        $message = esc_html(sprintf(__('Media Cloud Sync PRO is paused: this version of Media Cloud Sync needs PRO %1$s or later (installed: %2$s). Your settings and license are kept.', 'media-cloud-sync'), self::PRO_MIN_VERSION, WPMCS_PRO_VERSION));

        printf(
            '<div class="notice notice-error"><p>%1$s</p><p><a class="button button-primary" href="%2$s">%3$s</a></p></div>',
            $message,
            esc_url($url),
            esc_html__('Update PRO now', 'media-cloud-sync')
        );
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
