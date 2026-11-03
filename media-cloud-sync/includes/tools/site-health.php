<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Tools > Site Health: status tests and an Info section. Reports saved check results; never calls the bucket.
 * @since 1.5.0
 */
class SiteHealth {
    private static $instance = null;

    // A running job with no progress for this long is reported as stalled.
    const STALLED_AFTER = 15 * MINUTE_IN_SECONDS;
    // The every-minute runner event this far overdue means WP-Cron isn't running.
    const CRON_OVERDUE_AFTER = 10 * MINUTE_IN_SECONDS;
    const MIN_MEMORY = 256 * MB_IN_BYTES;

    public function __construct() {
        add_filter('site_status_tests', [$this, 'register_tests']);
        add_filter('debug_information', [$this, 'debug_information']);
    }

    public function register_tests($tests) {
        $labels = [
            'storage'  => __('Media Cloud Sync storage connection', 'media-cloud-sync'),
            'delivery' => __('Media Cloud Sync media delivery', 'media-cloud-sync'),
            'jobs'     => __('Media Cloud Sync bulk jobs', 'media-cloud-sync'),
            'cron'     => __('Media Cloud Sync background processing', 'media-cloud-sync'),
            'memory'   => __('Media Cloud Sync PHP memory', 'media-cloud-sync'),
        ];
        foreach ($labels as $test => $label) {
            $tests['direct']['wpmcs_' . $test] = [
                'label' => $label,
                'test'  => [$this, 'test_' . $test],
            ];
        }
        return $tests;
    }

    public function test_storage() {
        if (!Utils::get_service()) {
            return $this->result('storage', 'recommended', __('Media Cloud Sync isn\'t connected to cloud storage yet', 'media-cloud-sync'),
                __('Your media stays on this server until you connect a storage provider.', 'media-cloud-sync'), '/configure', __('Connect storage', 'media-cloud-sync'));
        }
        $error = Utils::get_service_configuration_error();
        if ($error) {
            return $this->result('storage', 'critical', __('Media Cloud Sync can\'t use your cloud storage', 'media-cloud-sync'), $error, '/configure', __('Check storage', 'media-cloud-sync'));
        }
        $check = Utils::get_status('storageCredentials', []);
        if (!empty($check['lastChecked']) && empty($check['status'])) {
            return $this->result('storage', 'critical', __('The last Media Cloud Sync storage check failed', 'media-cloud-sync'),
                $this->checked_message($check), '/settings', __('Re-run the check', 'media-cloud-sync'));
        }
        return $this->result('storage', 'good', __('Media Cloud Sync is connected to cloud storage', 'media-cloud-sync'),
            $this->checked_message($check));
    }

    public function test_delivery() {
        if (!Utils::is_service_enabled()) {
            return $this->result('delivery', 'good', __('Media Cloud Sync delivery isn\'t in use yet', 'media-cloud-sync'),
                __('Media delivery is checked once storage is connected.', 'media-cloud-sync'));
        }
        if (!Utils::has_delivery_provider()) {
            return $this->result('delivery', 'recommended', __('No Media Cloud Sync delivery provider is chosen', 'media-cloud-sync'),
                __('Synced media is still served from this server. Choose a delivery provider to serve it from the cloud or your CDN.', 'media-cloud-sync'), '/choose_cdn', __('Choose a provider', 'media-cloud-sync'));
        }
        if (!Utils::get_settings('rewrite_url')) {
            return $this->result('delivery', 'recommended', __('Media Cloud Sync delivery is turned off', 'media-cloud-sync'),
                __('"Deliver Synced Media" is off, so synced media is served from this server.', 'media-cloud-sync'), '/settings', __('Open Settings', 'media-cloud-sync'));
        }
        $check = Utils::get_status('cdnRead', []);
        if (!empty($check['lastChecked']) && empty($check['status'])) {
            return $this->result('delivery', 'recommended', __('The last Media Cloud Sync delivery check failed', 'media-cloud-sync'),
                $this->checked_message($check), '/settings', __('Re-run the check', 'media-cloud-sync'));
        }
        return $this->result('delivery', 'good', __('Media Cloud Sync is delivering media from the cloud', 'media-cloud-sync'),
            $this->checked_message($check));
    }

    public function test_jobs() {
        $stalled = [];
        // Runner state only: a job's own get_status() may estimate totals with bucket API calls.
        foreach (BGRunner::instance()->all_statuses() as $action => $status) {
            $last_run = (int) ($status['last_run'] ?? 0);
            if (($status['status'] ?? '') === 'running' && $last_run > 0 && (time() - $last_run) > self::STALLED_AFTER) {
                /* translators: %1$s: job name, %2$s: time since the last progress */
                $stalled[] = sprintf(__('%1$s (no progress for %2$s)', 'media-cloud-sync'), $action, human_time_diff($last_run));
            }
        }
        if ($stalled) {
            return $this->result('jobs', 'critical', __('A Media Cloud Sync bulk job has stalled', 'media-cloud-sync'),
                implode(', ', $stalled) . '. ' . __('Check that WP-Cron runs, or resume the job from the dashboard. If it stays stuck, clear the job lock with "wp mcs reset-lock".', 'media-cloud-sync'), '/', __('Open the dashboard', 'media-cloud-sync'));
        }
        return $this->result('jobs', 'good', __('Media Cloud Sync bulk jobs are running normally', 'media-cloud-sync'),
            __('No bulk job is stuck.', 'media-cloud-sync'));
    }

    public function test_cron() {
        if (!$this->cron_on_schedule()) {
            $description = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON
                ? __('WP-Cron is disabled (DISABLE_WP_CRON) and no server cron seems to be calling wp-cron.php, so bulk jobs in Cron mode won\'t advance. Add a server cron job that runs wp-cron.php every minute, or use the Ajax sync method.', 'media-cloud-sync')
                : __('WP-Cron isn\'t running on schedule (it needs site visits, or a server cron), so bulk jobs in Cron mode may stall. Add a server cron job that runs wp-cron.php every minute, or use the Ajax sync method.', 'media-cloud-sync');
            return $this->result('cron', 'recommended', __('Media Cloud Sync background processing is delayed', 'media-cloud-sync'), $description);
        }
        return $this->result('cron', 'good', __('Media Cloud Sync background processing is running', 'media-cloud-sync'),
            __('WP-Cron is running on schedule, so bulk jobs in Cron mode can advance.', 'media-cloud-sync'));
    }

    public function test_memory() {
        $limit = $this->memory_limit();
        if ($limit > 0 && $limit < self::MIN_MEMORY) {
            return $this->result('memory', 'recommended', __('PHP memory may be too low for Media Cloud Sync', 'media-cloud-sync'),
                /* translators: %1$s: current memory limit, %2$s: recommended memory limit */
                sprintf(__('The PHP memory limit is %1$s. Uploading and syncing large images can need %2$s or more; raise memory_limit (or WP_MEMORY_LIMIT) to avoid failed uploads.', 'media-cloud-sync'), size_format($limit), size_format(self::MIN_MEMORY)));
        }
        return $this->result('memory', 'good', __('PHP memory is enough for Media Cloud Sync', 'media-cloud-sync'),
            /* translators: %s: memory limit */
            $limit > 0 ? sprintf(__('The PHP memory limit is %s.', 'media-cloud-sync'), size_format($limit)) : __('The PHP memory limit is unlimited.', 'media-cloud-sync'));
    }

    /** Info tab section; never includes keys or secrets. */
    public function debug_information($info) {
        global $wpdb;
        $credentials = (array) Utils::get_credentials('', [], true);
        $counts      = Counter::get_count('all');
        $total_media = (int) ($counts['total'] ?? 0);
        $synced      = min($total_media, (int) ($counts['uploaded'] ?? 0));
        $yes_no      = function ($value) {
            return $value ? __('Yes', 'media-cloud-sync') : __('No', 'media-cloud-sync');
        };
        $setting     = function ($key) {
            return Utils::get_settings($key);
        };
        $check       = function ($key) {
            $status = Utils::get_status($key, []);
            if (empty($status['lastChecked'])) {
                return __('Not run yet', 'media-cloud-sync');
            }
            return sprintf('%s: %s (%s)', !empty($status['status']) ? __('OK', 'media-cloud-sync') : __('Failed', 'media-cloud-sync'), (string) ($status['message'] ?? ''), wp_date('Y-m-d H:i', (int) $status['lastChecked']));
        };
        $policy      = Utils::get_status('privateMediaPolicy', []);
        $logs        = Logger::instance()->get_all_logs();
        $table       = Db::get_table_name();

        $fields = [
            'version'        => [__('Version', 'media-cloud-sync'), WPMCS_VERSION . (defined('WPMCS_PRO_VERSION') ? ' (PRO ' . WPMCS_PRO_VERSION . ')' : '')],
            /* translators: %s: expected database version */
            'db_version'     => [__('Database version', 'media-cloud-sync'), get_option(WPMCS_TOKEN . '_db_version', '') . ' (' . sprintf(__('expected %s', 'media-cloud-sync'), WPMCS_DB_VERSION) . ')'],
            'storage'        => [__('Storage provider', 'media-cloud-sync'), $credentials['service'] ?? ''],
            'credentials'    => [__('Credentials source', 'media-cloud-sync'), Utils::get_credentials_source()],
            'bucket'         => [__('Bucket', 'media-cloud-sync'), $credentials['bucketConfig']['bucket_name'] ?? ''],
            'region'         => [__('Region', 'media-cloud-sync'), $credentials['bucketConfig']['region'] ?? ($credentials['config']['region'] ?? '')],
            'storage_check'  => [__('Last storage check', 'media-cloud-sync'), $check('storageCredentials')],
            'upload_new'     => [__('Upload new media to the bucket', 'media-cloud-sync'), $yes_no($setting('copy_to_bucket'))],
            'base_path'      => [__('Bucket base path', 'media-cloud-sync'), $setting('enable_base_path') ? (string) $setting('base_path') : __('Off', 'media-cloud-sync')],
            'year_month'     => [__('Year/month folders', 'media-cloud-sync'), $yes_no($setting('year_month'))],
            'versioning'     => [__('Object versioning', 'media-cloud-sync'), $yes_no($setting('object_versioning'))],
            'remove_local'   => [__('Remove files from server', 'media-cloud-sync'), $yes_no($setting('remove_from_server'))],
            'delivery'       => [__('Delivery provider', 'media-cloud-sync'), ($credentials['cdn']['service'] ?? '') ?: __('None chosen', 'media-cloud-sync')],
            'deliver_media'  => [__('Deliver synced media', 'media-cloud-sync'), $yes_no($setting('rewrite_url'))],
            'custom_domain'  => [__('Custom domain', 'media-cloud-sync'), $setting('enable_cdn') ? (string) $setting('cdn_url') : ''],
            'serving_from'   => [__('Serving media from', 'media-cloud-sync'), Utils::is_ok_to_serve(false, false) ? __('Cloud', 'media-cloud-sync') : __('Server', 'media-cloud-sync')],
            'delivery_check' => [__('Last delivery check', 'media-cloud-sync'), $check('cdnRead')],
            'private_media'  => [__('Private media', 'media-cloud-sync'), $setting('enable_private_media')
                /* translators: %1$s: "applied" or "not applied", %2$s: private path */
                ? sprintf(__('On, policy %1$s (path: %2$s)', 'media-cloud-sync'), !empty($policy['status']) ? __('applied', 'media-cloud-sync') : __('not applied', 'media-cloud-sync'), (string) ($policy['prefix'] ?? $setting('private_path')))
                : __('Off', 'media-cloud-sync')],
            'cache_control'  => [__('Cache-Control', 'media-cloud-sync'), $setting('cache_control_enabled') ? $setting('cache_control_duration') . ' ' . $setting('cache_control_unit') : __('Off', 'media-cloud-sync')],
            /* translators: %1$s: included extensions or "off", %2$s: excluded extensions or "off" */
            'extensions'     => [__('Extension filters', 'media-cloud-sync'), sprintf(__('Include: %1$s; Exclude: %2$s', 'media-cloud-sync'),
                $setting('extensions_include_enabled') ? implode(', ', (array) $setting('extensions_include')) : __('off', 'media-cloud-sync'),
                $setting('extensions_exclude_enabled') ? implode(', ', (array) $setting('extensions_exclude')) : __('off', 'media-cloud-sync'))],
            /* translators: %1$d: synced media, %2$d: media not synced, %3$d: total media */
            'media'          => [__('Media', 'media-cloud-sync'), sprintf(__('%1$d synced, %2$d not synced, %3$d total', 'media-cloud-sync'),
                $synced, max(0, $total_media - $synced), $total_media)],
            'items_table'    => [__('Synced items table rows', 'media-cloud-sync'), $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : __('Table missing', 'media-cloud-sync')],
            'integrations'   => [__('Active integrations', 'media-cloud-sync'), implode(', ', array_values((array) Integration::$source_labels))],
            'failed_items'   => [__('Failed items in the error log', 'media-cloud-sync'), (int) ($logs['total'] ?? 0)],
        ];

        foreach (BGRunner::instance()->all_statuses() as $action => $status) {
            $last = (int) ($status['last_run'] ?? 0);
            // Same split as the job screens: succeeded + failed + remaining = in this run.
            $run_total = max(0, (int) ($status['total'] ?? 0));
            $remaining = min($run_total, max(0, (int) ($status['remaining'] ?? 0)));
            $failed    = min($run_total - $remaining, max(0, (int) ($status['failed'] ?? 0)));
            $fields['job_' . $action] = [
                /* translators: %s: job name */
                sprintf(__('Job: %s', 'media-cloud-sync'), $action),
                /* translators: %1$s: job status, %2$d: items in this run, %3$d: succeeded, %4$d: failed, %5$d: remaining, %6$s: sync method, %7$s: last activity time */
                sprintf(__('%1$s, %2$d in this run: %3$d succeeded, %4$d failed, %5$d remaining, %6$s method, last activity %7$s', 'media-cloud-sync'),
                    $status['status'] ?? 'stopped', $run_total, $run_total - $remaining - $failed, $failed, $remaining,
                    $status['sync_method'] ?? 'cron', $last ? wp_date('Y-m-d H:i', $last) : __('never', 'media-cloud-sync')),
            ];
        }

        if (defined('WPMCS_PRO_VERSION')) {
            $license = Utils::get_safe_license_data();
            $fields['license'] = [__('PRO license', 'media-cloud-sync'), empty($license)
                ? __('Not activated', 'media-cloud-sync')
                /* translators: %1$s: license status, %2$s: expiry date */
                : sprintf(__('%1$s, expires %2$s', 'media-cloud-sync'), Utils::is_pro_licensed() ? __('valid', 'media-cloud-sync') : (string) ($license['status'] ?? __('invalid', 'media-cloud-sync')), (string) (($license['expiry'] ?? '') ?: __('never', 'media-cloud-sync')))];
        }

        $fields += [
            'object_cache'   => [__('Persistent object cache', 'media-cloud-sync'), $yes_no(wp_using_ext_object_cache())],
            'cron_disabled'  => [__('WP-Cron disabled', 'media-cloud-sync'), $yes_no(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON)],
            'cron_runner'    => [__('Background processing', 'media-cloud-sync'), $this->cron_state()],
            'next_cron'      => [__('Next background run', 'media-cloud-sync'), ($next = wp_next_scheduled(WPMCS_TOKEN . '_bg_runner_cron')) ? wp_date('Y-m-d H:i:s', $next) : __('Not scheduled', 'media-cloud-sync')],
            'memory_limit'   => [__('PHP memory limit', 'media-cloud-sync'), $this->memory_limit() > 0 ? size_format($this->memory_limit()) : __('Unlimited', 'media-cloud-sync')],
            'max_execution'  => [__('PHP max execution time', 'media-cloud-sync'), (int) get_cfg_var('max_execution_time') > 0 ? (int) get_cfg_var('max_execution_time') . 's' : __('Unlimited', 'media-cloud-sync')],
        ];

        $info['media-cloud-sync'] = [
            'label'  => __('Media Cloud Sync', 'media-cloud-sync'),
            'fields' => array_map(function ($field) {
                return ['label' => $field[0], 'value' => $field[1] === '' ? '—' : $field[1]];
            }, $fields),
        ];
        return $info;
    }

    /** Whether the every-minute runner event is running on time (not overdue). */
    private function cron_on_schedule() {
        $next = wp_next_scheduled(WPMCS_TOKEN . '_bg_runner_cron');
        return !$next || (time() - $next) <= self::CRON_OVERDUE_AFTER;
    }

    /** Server cron vs WP-Cron (site visits), and whether it is running on schedule. */
    private function cron_state() {
        $server = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        if ($server) {
            return $this->cron_on_schedule() ? __('Server cron: active', 'media-cloud-sync') : __('Server cron: not running (jobs in Cron mode won\'t advance)', 'media-cloud-sync');
        }
        return $this->cron_on_schedule() ? __('WP-Cron via site visits: running', 'media-cloud-sync') : __('WP-Cron via site visits: not running on schedule', 'media-cloud-sync');
    }

    /** Memory available to cron and front-end requests (not the admin's raised limit); 0 = unlimited. */
    private function memory_limit() {
        $ini = (string) get_cfg_var('memory_limit');
        if ($ini === '-1') {
            return 0;
        }
        $limit = wp_convert_hr_to_bytes($ini ?: '128M');
        if (defined('WP_MEMORY_LIMIT')) {
            $limit = max($limit, wp_convert_hr_to_bytes(WP_MEMORY_LIMIT));
        }
        return $limit;
    }

    private function checked_message($check) {
        $message = (string) ($check['message'] ?? '');
        if (!empty($check['lastChecked'])) {
            /* translators: %s: time since the last check */
            $message .= ($message !== '' ? ' ' : '') . sprintf(__('(checked %s ago)', 'media-cloud-sync'), human_time_diff((int) $check['lastChecked']));
        }
        return $message !== '' ? $message : __('Run the status check from Settings to confirm.', 'media-cloud-sync');
    }

    private function result($test, $status, $label, $description, $route = '', $action = '') {
        $actions = '';
        if ($route !== '' && $action !== '') {
            $actions = sprintf('<p><a href="%s">%s</a></p>', esc_url(admin_url('admin.php?page=' . WPMCS_TOKEN . '-admin-ui#' . $route)), esc_html($action));
        }
        return [
            'label'       => $label,
            'status'      => $status,
            'badge'       => ['label' => __('Media Cloud Sync', 'media-cloud-sync'), 'color' => $status === 'critical' ? 'red' : 'blue'],
            'description' => '<p>' . esc_html($description) . '</p>',
            'actions'     => $actions,
            'test'        => 'wpmcs_' . $test,
        ];
    }

    /**
     * Get instance
     * @return SiteHealth
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
