<?php
namespace Dudlewebs\WPMCS;

use WP_CLI;
use WP_CLI\Utils as CLIUtils;

defined('ABSPATH') || exit;

/**
 * Manage Media Cloud Sync bulk jobs from the command line.
 *
 * Every registered job works here, including those an add-on registers.
 *
 * @since 1.5.0
 */
class CLI {
    private $interrupted = false;

    /**
     * Shows media counts and the status of every job.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mcs status
     */
    public function status($args, $assoc_args) {
        $counts  = Counter::get_count('all');
        $total   = (int) ($counts['total'] ?? 0);
        $synced  = (int) ($counts['uploaded'] ?? 0);
        $format  = $assoc_args['format'] ?? 'table';

        $jobs  = [];
        $files = [];
        foreach ($this->registered_jobs() as $action => $title) {
            $s      = Sync::instance()->get_class_by_action($action)->get_status();
            $jobs[] = [
                'job'       => $action,
                'title'     => $title,
                'status'    => $s['status'] ?? 'stopped',
                'processed' => (int) ($s['processed'] ?? 0),
                'total'     => (int) ($s['total'] ?? 0),
                'failed'    => (int) ($s['failed'] ?? 0),
            ];
            if (!empty($s['download']['path'])) {
                $files[$action] = $s['download']['path'];
            }
        }

        if ($format === 'json') {
            WP_CLI::line(wp_json_encode(['media' => ['total' => $total, 'synced' => $synced, 'pending' => max(0, $total - $synced)], 'jobs' => $jobs, 'files' => (object) $files]));
            return;
        }

        /* translators: %1$d: total media, %2$d: synced media, %3$d: pending media */
        WP_CLI::line(sprintf(__('Media: %1$d total, %2$d synced, %3$d pending', 'media-cloud-sync'), $total, $synced, max(0, $total - $synced)));
        CLIUtils\format_items('table', $jobs, ['job', 'title', 'status', 'processed', 'total', 'failed']);
        foreach ($files as $action => $path) {
            /* translators: 1: job name, 2: file path on the server */
            WP_CLI::line(sprintf(__('%1$s file: %2$s', 'media-cloud-sync'), $action, $path));
        }
    }

    /**
     * Lists the jobs available on this site.
     *
     * ## EXAMPLES
     *
     *     wp mcs jobs
     */
    public function jobs() {
        $rows = [];
        foreach ($this->registered_jobs() as $action => $title) {
            $rows[] = ['job' => $action, 'title' => $title];
        }
        CLIUtils\format_items('table', $rows, ['job', 'title']);
    }

    /**
     * Starts a job and processes it in this terminal until it finishes.
     *
     * Runs without a browser tab or WP-Cron. Ctrl+C pauses the job (when PHP's pcntl extension is
     * available); run the same command again to resume.
     *
     * ## OPTIONS
     *
     * <job>
     * : Job to run, e.g. sync_to_cloud. See `wp mcs jobs`.
     *
     * [--batch=<number>]
     * : Items processed per step.
     * ---
     * default: 10
     * ---
     *
     * [--yes]
     * : Skip the confirmation for jobs that delete files.
     *
     * [--<field>=<value>]
     * : Job options from its confirmation dialog.
     *
     * ## EXAMPLES
     *
     *     wp mcs run sync_to_cloud
     */
    public function run($args, $assoc_args) {
        $action = $this->require_job($args[0] ?? '');
        $batch  = max(1, (int) ($assoc_args['batch'] ?? 10));
        $status = Sync::instance()->get_class_by_action($action)->get_status();

        if (in_array($status['status'] ?? '', ['running', 'paused'], true)) {
            Sync::instance()->resume($action);
            WP_CLI::log(__('Resuming the job.', 'media-cloud-sync'));
        } else {
            $this->start_job($action, $assoc_args);
        }

        $this->listen_for_interrupt($action);

        $status   = Sync::instance()->get_class_by_action($action)->get_status();
        $total    = max(1, (int) ($status['total'] ?? 0));
        $progress = CLIUtils\make_progress_bar($this->registered_jobs()[$action], $total);
        $shown    = 0;
        $idle     = 0;

        while (true) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if ($this->interrupted) {
                $progress->finish();
                WP_CLI::warning(__('Paused. Run the same command again to resume.', 'media-cloud-sync'));
                return;
            }

            BGRunner::instance()->run_all($action, $batch);
            $status = Sync::instance()->get_class_by_action($action)->get_status();

            $processed = (int) ($status['processed'] ?? 0);
            if ($processed > $shown) {
                $progress->tick($processed - $shown);
                $shown = $processed;
                $idle  = 0;
            } else {
                // Another runner (e.g. WP-Cron) may hold the job's lock; wait instead of spinning.
                $idle++;
                if ($idle > 120) {
                    $progress->finish();
                    WP_CLI::error(__('No progress for 2 minutes. Another process may hold the job lock; if the job is stuck, run: wp mcs reset-lock', 'media-cloud-sync'));
                }
                sleep(1);
            }

            $state = $status['status'] ?? 'stopped';
            if ($state === 'completed' || ($state === 'stopped' && $idle > 0) || $state === 'paused') {
                break;
            }
        }

        $progress->finish();
        $failed = (int) ($status['failed'] ?? 0);
        if (($status['status'] ?? '') === 'paused') {
            WP_CLI::warning(__('The job was paused.', 'media-cloud-sync'));
        } elseif ($failed > 0) {
            /* translators: %d: number of failed items, %s: job name */
           WP_CLI::warning(sprintf(__('Finished with %1$d failed item(s). See: wp mcs errors %2$s', 'media-cloud-sync'), $failed, $action));
        } else {
            WP_CLI::success(__('Finished.', 'media-cloud-sync'));
        }
        // Jobs that produce a file (e.g. an export) report where it is.
        if (!empty($status['download']['path'])) {
            /* translators: %s: file path on the server */
            WP_CLI::log(sprintf(__('File: %s', 'media-cloud-sync'), $status['download']['path']));
        }
    }

    /**
     * Starts a job in the background (WP-Cron), like the START button.
     *
     * ## OPTIONS
     *
     * <job>
     * : Job to start. See `wp mcs jobs`.
     *
     * [--yes]
     * : Skip the confirmation for jobs that delete files.
     *
     * [--<field>=<value>]
     * : Job options from its confirmation dialog.
     *
     * ## EXAMPLES
     *
     *     wp mcs start sync_to_cloud
     */
    public function start($args, $assoc_args) {
        $action = $this->require_job($args[0] ?? '');
        // Lets an add-on switch the job to a mode that runs without a browser (cron).
        do_action('wpmcs_cli_before_start', $action);
        $this->start_job($action, $assoc_args);
        WP_CLI::success(__('Started in the background. Check progress with: wp mcs status', 'media-cloud-sync'));
    }

    /**
     * Pauses a running job.
     *
     * ## OPTIONS
     *
     * <job>
     * : Job to pause.
     */
    public function pause($args) {
        Sync::instance()->pause($this->require_job($args[0] ?? ''));
        WP_CLI::success(__('Pause requested.', 'media-cloud-sync'));
    }

    /**
     * Resumes a paused job in the background.
     *
     * ## OPTIONS
     *
     * <job>
     * : Job to resume.
     */
    public function resume($args) {
        Sync::instance()->resume($this->require_job($args[0] ?? ''));
        WP_CLI::success(__('Resumed.', 'media-cloud-sync'));
    }

    /**
     * Stops a job.
     *
     * ## OPTIONS
     *
     * <job>
     * : Job to stop.
     */
    public function stop($args) {
        Sync::instance()->stop($this->require_job($args[0] ?? ''));
        WP_CLI::success(__('Stop requested.', 'media-cloud-sync'));
    }

    /**
     * Lists a job's failed items, optionally retrying them.
     *
     * ## OPTIONS
     *
     * <job>
     * : Job whose errors to list.
     *
     * [--retry]
     * : Retry every failed item. For jobs that delete files (e.g. clean_unused_files) this deletes every listed file, after a confirmation.
     *
     * [--clear]
     * : Clear the job's error log without retrying.
     *
     * [--yes]
     * : Skip the confirmation for jobs that delete files.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mcs errors sync_to_cloud
     *     wp mcs errors sync_to_cloud --retry
     */
    public function errors($args, $assoc_args) {
        $action = $this->require_job($args[0] ?? '');
        $logs   = Logger::instance()->get_logs_by_type($action)['logs'] ?? [];

        if (empty($logs)) {
            WP_CLI::success(__('No failed items.', 'media-cloud-sync'));
            return;
        }

        if (!empty($assoc_args['clear'])) {
            Logger::instance()->remove_logs_by_type($action);
            /* translators: %d: number of failed items */
            WP_CLI::success(sprintf(__('Cleared %d failed item(s).', 'media-cloud-sync'), count($logs)));
            return;
        }

        if (!empty($assoc_args['retry'])) {
            $handler = Sync::instance()->get_class_by_action($action);
            if (!method_exists($handler, 'retry_single')) {
                WP_CLI::error(__('Retry is not available for this job.', 'media-cloud-sync'));
            }
            $confirm = UISchema::instance()->get_all(false)['syncScreen'][$action]['confirm'] ?? [];
            if (!empty($confirm['danger'])) {
                /* translators: %d: number of listed items */
                WP_CLI::confirm(sprintf(__('This job deletes files: retrying acts on all %d listed item(s). Continue?', 'media-cloud-sync'), count($logs)), $assoc_args);
            }
            $fixed = 0;
            foreach ($logs as $log) {
                $handler->retry_single($log['media_id'], $log['source_type']);
                if (Utils::is_empty(Logger::instance()->get_log($action, $log['media_id'], $log['source_type']))) {
                    $fixed++;
                }
            }
            /* translators: %1$d: items retried, %2$d: items fixed, %3$d: items still failing */
            WP_CLI::success(sprintf(__('Retried %1$d item(s): %2$d fixed, %3$d still failing.', 'media-cloud-sync'), count($logs), $fixed, count($logs) - $fixed));
            return;
        }

        $rows = array_map(function ($log) {
            $last = is_array($log['logs'] ?? null) ? end($log['logs']) : [];
            return [
                'media_id'    => $log['media_id'] ?? '',
                'source_type' => $log['source_type'] ?? '',
                'time'        => $log['time'] ?? '',
                'message'     => $last['message'] ?? '',
                'file'        => $last['file'] ?? '',
            ];
        }, $logs);
        CLIUtils\format_items($assoc_args['format'] ?? 'table', $rows, ['media_id', 'source_type', 'time', 'message', 'file']);
    }

    /**
     * Syncs specific attachments to the cloud now.
     *
     * ## OPTIONS
     *
     * <id>...
     * : Attachment IDs.
     *
     * [--force]
     * : Re-upload attachments that are already synced.
     *
     * ## EXAMPLES
     *
     *     wp mcs sync 123 456
     *     wp mcs sync 123 --force
     */
    public function sync($args, $assoc_args) {
        if (!Utils::is_service_enabled()) {
            WP_CLI::error(Utils::get_service_configuration_error());
        }

        $force = !empty($assoc_args['force']) ? function () { return true; } : null;
        if ($force) {
            add_filter('wpmcs_do_reupload_media', $force, 10, 3);
        }

        $failed = 0;
        foreach (array_map('intval', $args) as $id) {
            if (get_post_type($id) !== 'attachment') {
                /* translators: %d: media ID */
                WP_CLI::warning(sprintf(__('%d: not a media item.', 'media-cloud-sync'), $id));
                $failed++;
                continue;
            }
            MediaLibrary::instance()->upload_single_media($id);
            $log = Logger::instance()->get_log('sync_to_cloud', $id, 'media_library');
            if (!empty($log['logs'])) {
                $last = end($log['logs']);
                WP_CLI::warning(sprintf('%d: %s', $id, $last['message'] ?? __('Sync failed.', 'media-cloud-sync')));
                $failed++;
            } else {
                /* translators: %d: media ID */
                WP_CLI::log(sprintf(__('%d: synced.', 'media-cloud-sync'), $id));
            }
        }

        if ($force) {
            remove_filter('wpmcs_do_reupload_media', $force, 10);
        }
        $this->finish_items(count($args), $failed);
    }

    /**
     * Shows an attachment's cloud record: provider, keys and URLs for each file, and which files are still on the server.
     *
     * ## OPTIONS
     *
     * <id>
     * : Attachment ID.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mcs info 123
     */
    public function info($args, $assoc_args) {
        $id   = (int) ($args[0] ?? 0);
        $item = Item::instance()->get($id, 'media_library');
        if (Utils::is_empty($item)) {
            /* translators: %d: media ID */
            WP_CLI::error(sprintf(__('%d is not synced to the cloud.', 'media-cloud-sync'), $id));
        }

        $basedir = trailingslashit(wp_get_upload_dir()['basedir']);
        $rows    = [];
        $add     = function ($label, $file) use (&$rows, $basedir) {
            if (empty($file['source_path']) && empty($file['key'])) {
                return;
            }
            $rows[] = [
                'file'  => $label,
                'local' => !empty($file['source_path']) && file_exists($basedir . $file['source_path']) ? 'yes' : 'no',
                'key'   => $file['key'] ?? '',
                'url'   => $file['url'] ?? '',
            ];
        };

        $add('full', $item);
        $add('original', ['source_path' => $item['original_source_path'] ?? '', 'key' => $item['original_key'] ?? '']);
        $extras = !empty($item['extra']) ? Utils::maybe_unserialize($item['extra']) : [];
        foreach ((array) ($extras['sizes'] ?? []) as $size => $file) {
            $add($size, (array) $file);
        }
        foreach ((array) ($extras['additional_files'] ?? []) as $name => $file) {
            $add($name, (array) $file);
        }
        if (!empty($extras['backup'])) {
            $add('backup', (array) Utils::maybe_unserialize($extras['backup']));
        }

        $summary = [
            'id'        => $id,
            'provider'  => $item['provider'] ?? '',
            'bucket'    => $item['storage'] ?? '',
            'region'    => $item['region'] ?? '',
            'private'   => !empty($item['is_private']),
            'served_as' => wp_get_attachment_url($id),
        ];

        if (($assoc_args['format'] ?? 'table') === 'json') {
            WP_CLI::line(wp_json_encode(['item' => $summary, 'files' => $rows]));
            return;
        }
        foreach ($summary as $label => $value) {
            WP_CLI::line(sprintf('%-10s %s', $label . ':', is_bool($value) ? ($value ? 'yes' : 'no') : $value));
        }
        CLIUtils\format_items('table', $rows, ['file', 'local', 'key', 'url']);
    }

    /**
     * Runs the storage and delivery status checks, like the Settings refresh buttons.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mcs check
     */
    public function check($args, $assoc_args) {
        $result = Service::instance()->verifyStatus('all');
        $labels = ['storageCredentials' => __('Storage', 'media-cloud-sync'), 'cdnRead' => __('Delivery', 'media-cloud-sync')];
        $rows   = [];
        $failed = false;
        foreach ((array) ($result['checks'] ?? []) as $key => $check) {
            // No delivery provider is a setup state, not a fault.
            $not_set_up = $key === 'cdnRead' && !Utils::has_delivery_provider();
            $state      = !empty($check['success']) ? 'ok' : ($not_set_up ? 'not set up' : 'failed');
            $failed     = $failed || $state === 'failed';
            $rows[]     = [
                'check'   => $labels[$key] ?? $key,
                'result'  => $state,
                'message' => $check['message'] ?? '',
            ];
        }
        CLIUtils\format_items($assoc_args['format'] ?? 'table', $rows, ['check', 'result', 'message']);
        if ($failed) {
            WP_CLI::halt(1);
        }
    }

    /**
     * Shows media counts, optionally recounting them first.
     *
     * ## OPTIONS
     *
     * [--refresh]
     * : Recount media before showing the numbers.
     *
     * ## EXAMPLES
     *
     *     wp mcs counts --refresh
     */
    public function counts($args, $assoc_args) {
        if (!empty($assoc_args['refresh'])) {
            Counter::fetch_and_update('all', 'all');
        }
        $counts = Counter::get_count('all');
        $total  = (int) ($counts['total'] ?? 0);
        $synced = (int) ($counts['uploaded'] ?? 0);
        WP_CLI::line(sprintf(__('Media: %1$d total, %2$d synced, %3$d pending', 'media-cloud-sync'), $total, $synced, max(0, $total - $synced)));
    }

    /**
     * Clears a stuck job lock so jobs can run again.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation.
     *
     * @subcommand reset-lock
     */
    public function reset_lock($args, $assoc_args) {
        WP_CLI::confirm(__('Only do this when a job is stuck. Clear the job lock?', 'media-cloud-sync'), $assoc_args);
        BGRunner::instance()->force_remove_lock();
        WP_CLI::success(__('Job lock cleared.', 'media-cloud-sync'));
    }

    /**
     * Shows the connection: storage, bucket, region and delivery. Secrets are never shown.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     */
    public function config($args, $assoc_args) {
        $credentials = Utils::get_credentials();
        $config      = [
            'storage'         => $credentials['service'] ?? '',
            'credentials'     => ($credentials['configSource'] ?? 'database') === 'config' ? 'wp-config.php (WPMCS_CONFIG)' : 'database',
            'bucket'          => $credentials['bucketConfig']['bucket_name'] ?? '',
            'region'          => $credentials['config']['region'] ?? ($credentials['bucketConfig']['region'] ?? ''),
            'delivery'        => ($credentials['cdn']['service'] ?? '') ?: __('(none chosen)', 'media-cloud-sync'),
            'deliver_media'   => Utils::get_settings('rewrite_url') ? 'on' : 'off',
            'custom_domain'   => Utils::get_settings('enable_cdn') ? (string) Utils::get_settings('cdn_url') : '',
            'serving_from'    => Utils::is_ok_to_serve(false, false) ? 'cloud' : 'server',
        ];

        if (($assoc_args['format'] ?? 'table') === 'json') {
            WP_CLI::line(wp_json_encode($config));
            return;
        }
        foreach ($config as $label => $value) {
            WP_CLI::line(sprintf('%-15s %s', $label . ':', $value));
        }
    }

    /**
     * Reads or changes plugin settings. Changes go through the same validation as the admin.
     *
     * ## OPTIONS
     *
     * <action>
     * : get or set.
     * ---
     * options:
     *   - get
     *   - set
     * ---
     *
     * [<key>]
     * : Setting name. Omit with `get` to list all.
     *
     * [<value>]
     * : New value for `set`: on/off, true/false, a number, or text.
     *
     * [--format=<format>]
     * : Output format for `get`.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp mcs settings get
     *     wp mcs settings get rewrite_url
     *     wp mcs settings set rewrite_url on
     */
    public function settings($args, $assoc_args) {
        $settings = (array) Utils::get_settings();
        $key      = $args[1] ?? '';

        if (($args[0] ?? '') === 'get') {
            if ($key !== '') {
                if (!array_key_exists($key, $settings)) {
                    /* translators: %s: setting name */
                    WP_CLI::error(sprintf(__('Unknown setting "%s".', 'media-cloud-sync'), $key));
                }
                WP_CLI::line(is_scalar($settings[$key]) ? var_export($settings[$key], true) : wp_json_encode($settings[$key]));
                return;
            }
            if (($assoc_args['format'] ?? 'table') === 'json') {
                WP_CLI::line(wp_json_encode($settings));
                return;
            }
            $rows = [];
            foreach ($settings as $name => $value) {
                $rows[] = ['setting' => $name, 'value' => is_scalar($value) ? var_export($value, true) : wp_json_encode($value)];
            }
            CLIUtils\format_items('table', $rows, ['setting', 'value']);
            return;
        }

        if ($key === '' || !isset($args[2])) {
            WP_CLI::error(__('Usage: wp mcs settings set <key> <value>', 'media-cloud-sync'));
        }
        $defaults = (array) (UISchema::instance()->get_all(false)['settingsDefaultValues'][Utils::get_service()] ?? []);
        if (!array_key_exists($key, $settings) && !array_key_exists($key, $defaults)) {
            /* translators: %s: setting name */
            WP_CLI::error(sprintf(__('Unknown setting "%s". See: wp mcs settings get', 'media-cloud-sync'), $key));
        }

        $settings[$key] = $this->parse_value($args[2], $settings[$key] ?? $defaults[$key]);
        $request = new \WP_REST_Request('POST');
        $request->set_body_params(['action' => 'settings', 'settings' => $settings]);
        $response = Api::instance()->saveConfig($request)->get_data();

        if (empty($response['success'])) {
            WP_CLI::error(wp_strip_all_tags($response['message'] ?? __('Settings could not be saved.', 'media-cloud-sync')));
        }
        /* translators: %1$s: setting name, %2$s: new value */
        WP_CLI::success(sprintf(__('%1$s set to %2$s.', 'media-cloud-sync'), $key, var_export($settings[$key], true)));
    }

    /** Converts a CLI value to the type of the setting's current value. */
    private function parse_value($value, $current) {
        if (is_bool($current) || in_array(strtolower($value), ['on', 'off', 'true', 'false', 'yes', 'no'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        if (is_int($current) || (is_numeric($value) && (string) (int) $value === $value)) {
            return (int) $value;
        }
        return $value;
    }

    /** Summary line for commands that act on a list of items. */
    private function finish_items($total, $failed) {
        if ($failed > 0) {
            /* translators: %1$d: failed items, %2$d: total items */
            WP_CLI::error(sprintf(__('%1$d of %2$d item(s) failed.', 'media-cloud-sync'), $failed, $total));
        }
        /* translators: %d: number of items */
        WP_CLI::success(sprintf(__('%d item(s) done.', 'media-cloud-sync'), $total));
    }

    /** Registered jobs as action => title. */
    private function registered_jobs() {
        $screens = UISchema::instance()->get_all(false)['syncScreen'] ?? [];
        $jobs    = [];
        foreach (array_keys(Sync::instance()->get_status()) as $action) {
            $jobs[$action] = $screens[$action]['title'] ?? $action;
        }
        return $jobs;
    }

    private function require_job($action) {
        if (!isset($this->registered_jobs()[$action])) {
            /* translators: %1$s: job name, %2$s: available job names */
            WP_CLI::error(sprintf(__('Unknown job "%1$s". Available: %2$s', 'media-cloud-sync'), $action, implode(', ', array_keys($this->registered_jobs()))));
        }
        return $action;
    }

    /** Confirms destructive jobs, maps confirm options to flags, and starts the job. */
    private function start_job($action, $assoc_args) {
        $confirm = UISchema::instance()->get_all(false)['syncScreen'][$action]['confirm'] ?? [];
        if (!empty($confirm['danger'])) {
            WP_CLI::confirm(wp_strip_all_tags($confirm['message'] ?? __('This job deletes files. Continue?', 'media-cloud-sync')), $assoc_args);
        }

        $options = [];
        foreach ((array) ($confirm['options'] ?? []) as $option) {
            $name = $option['name'] ?? '';
            if ($name === '') {
                continue;
            }
            if (in_array($option['type'] ?? '', ['text', 'select', 'number'], true)) {
                $value = $assoc_args[$name] ?? ($option['default'] ?? '');
                // A bare --flag arrives as true.
                $options[$name] = $value === true ? '' : (string) $value;
                continue;
            }
            $options[$name] = array_key_exists($name, $assoc_args)
                ? filter_var($assoc_args[$name], FILTER_VALIDATE_BOOLEAN)
                : (bool) ($option['default'] ?? false);
        }

        try {
            $result = Sync::instance()->start($action, $options);
        } catch (\Exception $e) {
            WP_CLI::error($e->getMessage());
        }
        if (is_array($result) && isset($result['success']) && $result['success'] === false) {
            WP_CLI::error(wp_strip_all_tags($result['message'] ?? __('The job could not be started.', 'media-cloud-sync')));
        }
        if (!empty($result['notice'])) {
            WP_CLI::log(wp_strip_all_tags($result['notice']));
        }
    }

    /** Ctrl+C pauses the job instead of leaving it half-run without a driver. */
    private function listen_for_interrupt($action) {
        if (!function_exists('pcntl_signal')) {
            WP_CLI::log(__('Note: without PHP\'s pcntl extension, Ctrl+C leaves the job running in the background (WP-Cron).', 'media-cloud-sync'));
            return;
        }
        pcntl_signal(SIGINT, function () use ($action) {
            Sync::instance()->pause($action);
            $this->interrupted = true;
        });
    }
}
