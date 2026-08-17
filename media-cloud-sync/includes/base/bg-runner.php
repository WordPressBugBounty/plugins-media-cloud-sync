<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class BGRunner {
    private static $instance = null;
    private $meta_key = 'dw_bg_runner_meta';
    private $transient_key = 'dw_bg_runner_state';
    private $control_transient_key = 'dw_bg_runner_control';
    // Unlike $meta_key's state rows, never cleared on idle — see get_type_settings().
    private $settings_meta_key = 'dw_bg_runner_settings';
    private $action_hook = 'dw_bg_runner_cron';
    private $callback_map = []; // type => callback
    private $tick_supported = []; // type => bool, whether ajax-driven ticking is wired up for this type
    private $state_cache = []; // type => cached state array for that type
    private static $lock_duration = 5 * 60; // lock duration (seconds)
    // How long a tick-supported type can go without a successful pass before cron treats
    // it as abandoned (e.g. laptop lid closed mid-sync) and steps in as a safety net.
    private static $ajax_abandoned_threshold = 120;

    private function __construct() {
        $this->meta_key    = WPMCS_TOKEN . '_bg_runner_meta';
        $this->transient_key = WPMCS_TOKEN . '_bg_runner_state';
        $this->control_transient_key = WPMCS_TOKEN . '_bg_runner_control';
        $this->settings_meta_key = Schema::getConstant('BG_RUNNER_SETTINGS_KEY');
        $this->action_hook = WPMCS_TOKEN . '_bg_runner_cron';

        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action($this->action_hook, [$this, 'run_all']);

        // Ensure cron always exists
        if (!wp_next_scheduled($this->action_hook)) {
            wp_schedule_event(time(), 'every_minute', $this->action_hook);
        }

        // Force remove lock
        add_action('init', [$this, 'force_remove_lock']);
    }

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /**
     * Register a job type's per-iteration callback.
     *
     * @param string   $type          Job type key.
     * @param callable $callback      Called with ($offset, $total_iterations) once per iteration.
     * @param bool     $supports_tick Whether this type also supports being driven synchronously
     *                                via run_all($type, ...) from a REST request (ajax/mixed sync
     *                                mode), in addition to the WP-Cron tick.
     */
    public function set_callback(string $type, callable $callback, bool $supports_tick = false) {
        // Empty type would collide with any other empty-type registration on the same rows.
        if ($type === '') {
            return;
        }

        $this->callback_map[$type] = $callback;
        $this->tick_supported[$type] = $supports_tick;
    }

    public function start(string $type, int $iterations) {
        $s = $this->get_type_state($type);
        $s['status'] = 'running';
        $s['iterations_total'] = $iterations;
        $s['iterations_done'] = 0;
        $s['failed_count'] = 0;
        $s['last_run'] = time();
        $this->save_type_state($type, $s);

        // reset control flags
        $this->save_type_control($type, $this->default_control_state());
    }

    public function pause(string $type) {
        $c = $this->get_type_control($type);
        $c['pause_requested'] = true;
        $c['time'] = time();
        $this->save_type_control($type, $c);
    }

    public function stop(string $type) {
        $c = $this->get_type_control($type);
        $c['stop_requested'] = true;
        $c['time'] = time();
        $this->save_type_control($type, $c);
    }

    public function resume(string $type) {
        $c = $this->get_type_control($type);
        $c['pause_requested'] = false;
        $c['stop_requested']  = false;
        $c['time'] = time();
        $this->save_type_control($type, $c);

        $s = $this->get_type_state($type);
        if (in_array($s['status'] ?? 'stopped', ['paused','stopped'], true)) {
            $s['status'] = 'running';
            $s['last_run'] = time();
            $this->save_type_state($type, $s);
        }
    }

    public function status(string $type) {
        $s = $this->get_type_state($type);
        $c = $this->get_type_control($type);

        // if running but pause/stop requested and lock expired, update state
        if(
            (( $s['status'] ?? 'stopped') === 'running' ) &&
            ( isset($c['time']) && ( $c['time'] > 0 ) && ( ( time() - $c['time'] ) > self::$lock_duration ) ) &&
            ($c['pause_requested'] === true || $c['stop_requested'] === true)
        ) {
            if($c['stop_requested'] === true) {
                $s = $this->default_type_state();
            } else {
                $s['status'] = 'paused';
            }
            $this->save_type_state($type, $s);

            // Consume the request this self-heal just acted on.
            $c['pause_requested'] = false;
            $c['stop_requested'] = false;
            $c['time'] = time();
            $this->save_type_control($type, $c);
        }

        return $this->format_status($type, $s);
    }

    public function all_statuses() {
        $statuses = [];
        foreach (array_keys($this->callback_map) as $type) {
            $statuses[$type] = $this->status($type);
        }
        return $statuses;
    }

    private function format_status(string $type, array $s) {
        $c = $this->get_type_control($type);

        $total = $s['iterations_total'] ?? 0;
        $done = $s['iterations_done'] ?? 0;
        $failed_count = $s['failed_count'] ?? 0;
        $remaining = max(0, $total - $done);
        $percentage = $total > 0 ? round(($done / $total) * 100, 2) : 0;

        $status = [
            'total' => $total,
            'processed' => $done,
            'failed' => $failed_count,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'status' => $s['status'],
            'last_run' => $s['last_run'] ?? 0,
            'pause_requested' => $c['pause_requested'] ?? false,
            'stop_requested' => $c['stop_requested'] ?? false,
            'sync_method' => $this->get_sync_method($type),
        ];

        // Report completed if marked so in state
        if (!empty($s['completed'])) {
            $status['percentage'] = 100;
            $status['remaining'] = 0;
            $status['status'] = 'completed';
        }

        // Once idle, delete state/control rows instead of resetting in place — a missing
        // row and a freshly-defaulted one read back identically.
        if(
            !empty($s['completed']) ||
            (
                $status['status'] === 'stopped' &&
                $s['iterations_done'] < $s['iterations_total']
            )
        ) {
            $this->delete_type_state($type);
            $this->delete_type_control($type);
        }

        return $status;
    }

    /**
     * Process registered job types.
     *
     * Called by WP-Cron with no args (processes every running type, up to 50
     * iterations each). Also called synchronously from a REST request with a
     * specific $type and a small $max_per_run to drive ajax/mixed sync mode.
     *
     * @param string|null $only_type   If set, only this type is processed (all others are
     *                                 left for the next cron tick to own).
     * @param int         $max_per_run Max iterations to process per type in this call.
     */
    public function run_all(?string $only_type = null, int $max_per_run = 50) {
        $max_per_run = max(1, $max_per_run);

        $max_exec  = (int) ini_get('max_execution_time');
        $max_exec  = $max_exec !== 0 ? $max_exec : 55;
        $time_safe = $max_exec * 0.80;
        $run_start_time = microtime(true);

        // Registered types are the source of truth now that state/control/lock live per type.
        foreach (array_keys($this->callback_map) as $type) {
            if ($only_type !== null && $type !== $only_type) continue;

            // shared budget for this whole tick, not per type
            if ((microtime(true) - $run_start_time) > $time_safe) {
                break;
            }

            // refresh state for each type to pick up changes made during processing of other types
            $s = $this->get_type_state($type, true);

            if (($s['status'] ?? 'stopped') !== 'running') {
                $c = $this->get_type_control($type);

                if ($c['stop_requested'] ?? false) {
                    $s['status'] = 'stopped';
                    $this->save_type_state($type, $s);
                    $c['stop_requested'] = false;
                    $this->save_type_control($type, $c);
                }
                continue;
            }

            // Ajax mode: the browser drives this type via its own run_all($type, ...) calls,
            // so cron's untargeted pass yields to it while last_run is still recent. Only
            // applies to the cron-driven invocation; a type that never opted into ticking is
            // never skipped here. If last_run goes stale (browser gone, e.g. laptop lid
            // closed without firing pagehide), cron steps back in as a safety net.
            if ($only_type === null && !empty($this->tick_supported[$type]) && $this->get_effective_sync_method($type) === 'ajax') {
                $last_run = $s['last_run'] ?? 0;
                if ((time() - $last_run) < self::$ajax_abandoned_threshold) {
                    continue;
                }
            }

            if ($this->is_locked($s)) continue;

            // Real cross-process lock — is_locked() above is a cheap, non-atomic first
            // filter; acquire_process_lock() is what actually prevents two processes from
            // working the same type at once.
            if (!$this->acquire_process_lock($type)) continue;

            try {
                // lock, save and process
                $s = $this->lock($s);
                $this->save_type_state($type, $s);

                $this->process_iterations($type, $this->callback_map[$type], $run_start_time, $time_safe, $max_per_run);
            } finally {
                // Fetch state again to ensure we have the latest and then unlock
                $latest_s = $this->get_type_state($type, true);
                $latest_s = $this->unlock($latest_s);
                $this->save_type_state($type, $latest_s);
                $this->release_process_lock($type);
            }
        }
    }

    /**
     * Whether Pro is installed, licensed, and set to ajax/mixed sync mode for this type.
     * Falls back to 'cron' whenever Pro isn't installed and currently licensed.
     *
     * @return string 'ajax'|'cron'|'mixed'
     */
    private function get_effective_sync_method(string $type) {
        return Utils::is_pro_licensed() ? $this->get_sync_method($type) : 'cron';
    }

    /**
     * Force the next get_transient() for this key to be a real database read instead of a
     * possibly-stale value from WordPress's per-request object cache, which otherwise keeps
     * returning the same cached copy for the life of a request no matter how many times
     * it's read again.
     */
    private function bust_transient_cache(string $key) {
        wp_cache_delete($key, 'transient');
        wp_cache_delete('_transient_' . $key, 'options');
    }

    /**
     * Cross-process mutual exclusion for a type, via add_option()'s atomic INSERT (backed
     * by the UNIQUE index on wp_options.option_name) rather than a MySQL-specific locking
     * function. One row per type so unrelated sync types never block each other. Any
     * process can reclaim a stale lock by age — see renew_process_lock() for why a
     * heartbeat is needed rather than just the acquisition timestamp.
     *
     * @return bool True if the lock was acquired (fresh or reclaimed from a stale holder).
     */
    private function acquire_process_lock(string $type) {
        $lock_option = $this->meta_key . '_proc_lock_' . $type;

        if (add_option($lock_option, time(), '', false)) {
            return true;
        }

        $acquired_at = (int) get_option($lock_option, 0);
        if ($acquired_at > 0 && (time() - $acquired_at) > self::$lock_duration) {
            delete_option($lock_option);
            return add_option($lock_option, time(), '', false);
        }

        return false;
    }

    private function release_process_lock(string $type) {
        delete_option($this->meta_key . '_proc_lock_' . $type);
    }

    /**
     * Refresh the lock's timestamp so a batch that's simply slow doesn't look identical to
     * a dead one to acquire_process_lock()'s staleness check.
     */
    private function renew_process_lock(string $type) {
        update_option($this->meta_key . '_proc_lock_' . $type, time(), false);
    }

    private function process_iterations(string $type, callable $callback, float $run_start_time, float $time_safe, int $max_per_run = 50) {
        $s = $this->get_type_state_counts($type, true);

        $max_mem = ini_get('memory_limit') ? $this->return_bytes(ini_get('memory_limit')) : 128 * 1024 * 1024;
        $memory_safe = $max_mem * 0.80;
        $iterations_count = 0;

        while ($s['iterations_done'] < $s['iterations_total'] && $iterations_count < $max_per_run) {
            // limit iterations per run to avoid long blocking
            $iterations_count++;

            // force refresh so pause/stop requests are seen immediately
            $latest_c = $this->get_type_control($type);

            // stop if paused or stopped
            if (!empty($latest_c['pause_requested']) || !empty($latest_c['stop_requested'])) {
                break;
            }

            // check memory and time using more precise calls (time budget shared across all types in this tick)
            if ((memory_get_usage(false) > $memory_safe) || ((microtime(true) - $run_start_time) > $time_safe)) {
                break;
            }

            try {
                $already_done   = $s['iterations_done'];
                $result         = call_user_func($callback, $already_done, $s['iterations_total']);
                if ($result !== true) {
                    $s['failed_count']++;
                }
            } catch (\Exception $e) {
                $s['failed_count']++;
            }

            $s['iterations_done']++;

            // Write progress every item, not just at the end of the batch, so a slow item
            // can't silently lose the batch's progress if it gets killed mid-way.
            $this->update_type_state_counts($type, $s, true, false);
            $this->renew_process_lock($type);

            if($iterations_count % 10 === 0) {
                gc_collect_cycles();
            }
        }

        // Get latest state again
        $latest_s       = $this->get_type_state($type, true);
        $latest_c       = $this->get_type_control($type);
        $is_completed   = ($latest_s['iterations_done'] ?? 0) >= ($latest_s['iterations_total'] ?? 0);

        // Update state if completed, paused or stopped
        if ($is_completed || ($latest_c['stop_requested'] ?? false) || ($latest_c['pause_requested'] ?? false)) {
            $latest_s['status'] = $is_completed || ($latest_c['stop_requested'] ?? false) ? 'stopped' : 'paused';
            $latest_s['completed'] = $is_completed;
            $this->save_type_state($type, $latest_s);

            $latest_c['pause_requested'] = false;
            $latest_c['stop_requested'] = false;
            $latest_c['time'] = time();
            $this->save_type_control($type, $latest_c);
        }

        gc_collect_cycles();
    }

    private function lock(array $s) {
        $s['lock_until'] = time() + self::$lock_duration;
        return $s;
    }

    private function unlock(array $s) {
        $s['lock_until'] = 0;
        $s['last_run'] = time();
        return $s;
    }

    private function is_locked(array $s) {
        return ($s['lock_until'] ?? 0) && time() < $s['lock_until'];
    }

    private function return_bytes($val) {
        $val = trim($val);
        // "-1"/blank mean no limit; treating it as a numeric byte count made the
        // memory-budget check in process_iterations() permanently true.
        if ($val === '' || $val === '-1') {
            return PHP_INT_MAX;
        }
        $last = strtolower($val[strlen($val)-1] ?? '');
        $num = (int) $val;
        switch ($last) {
            case 'g': $num *= 1024 * 1024 * 1024; break;
            case 'm': $num *= 1024 * 1024; break;
            case 'k': $num *= 1024; break;
        }
        return $num;
    }

    public function add_cron_schedules($schedules) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => 'Every Minute'
        ];
        return $schedules;
    }


    /**
     * Get only the count-related fields of the state for a given type.
     *
     * @param string $type The job type.
     * @param bool   $force Reload state from transient/option instead of cache.
     *
     * @return array {
     *     @type int $iterations_total
     *     @type int $iterations_done
     *     @type int $failed_count
     * }
     */
    private function get_type_state_counts(string $type, bool $force = false) {
        $s = $this->get_type_state($type, $force);

        return [
            'iterations_total' => (int) ($s['iterations_total'] ?? 0),
            'iterations_done'  => (int) ($s['iterations_done'] ?? 0),
            'failed_count'     => (int) ($s['failed_count'] ?? 0),
        ];
    }


    /**
     * Update only the count-related fields for a given type.
     *
     * @param string $type The job type.
     * @param array  $counts {
     *     @type int $iterations_total
     *     @type int $iterations_done
     *     @type int $failed_count
     * }
     * @param bool $update_transient Whether to update transient.
     * @param bool $update_options   Whether to update options.
     */
    private function update_type_state_counts(string $type, array $counts, bool $update_transient = true, bool $update_options = true) {
        $s = $this->get_type_state($type, true);

        // update only the count fields
        if (isset($counts['iterations_total'])) {
            $s['iterations_total'] = (int) $counts['iterations_total'];
        }
        if (isset($counts['iterations_done'])) {
            $s['iterations_done'] = (int) $counts['iterations_done'];
        }
        if (isset($counts['failed_count'])) {
            $s['failed_count'] = (int) $counts['failed_count'];
        }

        $this->save_type_state($type, $s, $update_transient, $update_options);
    }


    /**
     * Get the state for one job type. Each type has its own transient/option row rather
     * than one row holding every type's state, so concurrent processing of two different
     * types never races on the same shared row.
     *
     * @param string $type  The job type.
     * @param bool   $force Reload the state from the transient or option.
     *
     * @return array The state for this type.
     */
    private function get_type_state(string $type, bool $force = false) {
        // if not forcing and cache already exists, return cached
        if (!$force && isset($this->state_cache[$type])) {
            return $this->state_cache[$type];
        }

        $transient_key = $this->transient_key . '_' . $type;

        if ($force) {
            $this->bust_transient_cache($transient_key);
        }

        // try transient first
        $s = get_transient($transient_key);

        if ($s !== false) {
            if ($force) {
                // update cache with the fresh transient
                $this->state_cache[$type] = $s;
            }
            return $s;
        }

        // fallback to option if transient missing (e.g. evicted from a persistent object cache)
        $option_key = $this->meta_key . '_' . $type;
        $s = get_option($option_key, null);
        if ($s === null) {
            // Genuinely idle/never started — return the default without persisting a row,
            // since run_all() now touches every registered type on every cron tick.
            $s = $this->default_type_state();
            $this->state_cache[$type] = $s;
            return $s;
        }
        $this->state_cache[$type] = $s;
        set_transient($transient_key, $s, WEEK_IN_SECONDS);

        return $s;
    }

    private function save_type_state(string $type, array $s, bool $update_transient = true, bool $update_options = true) {
        // Only write if changed to reduce option churn
        if (!isset($this->state_cache[$type]) || $this->state_cache[$type] !== $s) {
            $this->state_cache[$type] = $s;
            if ($update_transient) set_transient($this->transient_key . '_' . $type, $s, WEEK_IN_SECONDS);
            if ($update_options) update_option($this->meta_key . '_' . $type, $s, false);
        }
    }

    private function delete_type_state(string $type) {
        delete_transient($this->transient_key . '_' . $type);
        delete_option($this->meta_key . '_' . $type);
        unset($this->state_cache[$type]);
    }

    private function default_type_state() {
        return [
            'lock_until' => 0,
            'status' => 'stopped',
            'iterations_total' => 0,
            'iterations_done' => 0,
            'failed_count' => 0,
            'completed' => false,
            'last_run' => 0,
        ];
    }

    /**
     * Control (pause/stop) always busts the cache before reading — every caller needs it
     * live, unlike state there's no non-forced fast path here.
     *
     * @return array
     */
    private function get_type_control(string $type) {
        $key = $this->control_transient_key . '_' . $type;
        $this->bust_transient_cache($key);

        $c = get_transient($key);
        return $c !== false ? $c : $this->default_control_state();
    }

    private function save_type_control(string $type, array $c) {
        set_transient($this->control_transient_key . '_' . $type, $c, WEEK_IN_SECONDS);
    }

    private function delete_type_control(string $type) {
        delete_transient($this->control_transient_key . '_' . $type);
    }

    private function default_control_state() {
        return [
            'pause_requested' => false,
            'stop_requested'  => false,
            'time'            => time(),
        ];
    }

    // One option row for every type, keyed by $type — not one option per type.
    private function get_type_settings(string $type) {
        return Utils::get_option($type, [], $this->settings_meta_key);
    }

    private function save_type_settings(string $type, array $settings) {
        Utils::update_option($type, $settings, $this->settings_meta_key);
    }

    // Raw stored preference, not license-gated — see get_effective_sync_method() for that.
    public function get_sync_method(string $type) {
        $settings = $this->get_type_settings($type);
        return $settings['sync_method'] ?? 'cron';
    }

    public function set_sync_method(string $type, string $method) {
        if (!in_array($method, ['ajax', 'cron', 'mixed'], true)) {
            return false;
        }
        $settings = $this->get_type_settings($type);
        $settings['sync_method'] = $method;
        $this->save_type_settings($type, $settings);
        return true;
    }

    /**
     * Forces removal of the bg runner lock. This is a debug utility and should not be used in production.
     * The lock is removed when the query string parameter 'force_reset_sync' is set to '1'.
     * The purpose of this function is to allow for easy reset of the bg runner lock in debug environments.
     * It is not intended for use in production and can potentially cause issues with the bg runner's operation.
     */
    public function force_remove_lock() {
        if (isset($_GET['force_reset_sync']) && $_GET['force_reset_sync'] == '1') {
            if (! current_user_can('manage_options')) {
                return;
            }

            // Legacy pre-per-type keys, in case any still linger.
            delete_transient($this->transient_key);
            delete_option($this->meta_key);
            delete_transient($this->control_transient_key);

            foreach (array_keys($this->callback_map) as $type) {
                $this->delete_type_state($type);
                $this->delete_type_control($type);
                delete_option($this->meta_key . '_proc_lock_' . $type);
            }

            if (! defined('DOING_AJAX')) {
                wp_die('Locks removed successfully.');
            }
        }
    }
}
