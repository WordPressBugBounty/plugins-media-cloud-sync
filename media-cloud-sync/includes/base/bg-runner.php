<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class BGRunner {
    private static $instance = null;
    private $meta_key = 'dw_bg_runner_meta';
    private $transient_key = 'dw_bg_runner_state';
    private $control_transient_key = 'dw_bg_runner_control';
    private $action_hook = 'dw_bg_runner_cron';
    private $callback_map = []; // type => callback
    private $state_cache = null; // cached state array
    private static $lock_duration = 5 * 60; // lock duration (seconds)

    private function __construct() {
        $this->meta_key    = WPMCS_TOKEN . '_bg_runner_meta';
        $this->control_transient_key = WPMCS_TOKEN . '_bg_runner_control';
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

    public function set_callback(string $type, callable $callback) {
        $this->callback_map[$type] = $callback;
    }

    public function start(string $type, int $iterations) {
        $state = $this->get_state();
        $state[$type]['status'] = 'running';
        $state[$type]['iterations_total'] = $iterations;
        $state[$type]['iterations_done'] = 0;
        $state[$type]['failed_count'] = 0;
        $this->save_state($state);

        // reset control flags
        $control = $this->get_control();
        $control[$type] = $this->default_control_state();
        $this->save_control($control);
    }

    public function pause(string $type) {
        $control = $this->get_control();
        $control[$type]['pause_requested'] = true;
        $control[$type]['time']  = time();
        $this->save_control($control);
    }

    public function stop(string $type) {
        $control = $this->get_control();
        $control[$type]['stop_requested'] = true;
        $control[$type]['time']  = time();
        $this->save_control($control);
    }

    public function resume(string $type) {
        $control = $this->get_control();
        $control[$type]['pause_requested'] = false;
        $control[$type]['stop_requested']  = false;
        $control[$type]['time']  = time();
        $this->save_control($control);

        $state = $this->get_state();
        if (in_array($state[$type]['status'] ?? 'stopped', ['paused','stopped'], true)) {
            $state[$type]['status'] = 'running';
            $this->save_state($state);
        }
    }

    public function status(string $type) {
        $state = $this->get_state();
        $s = $state[$type] ?? $this->default_type_state();
        $control = $this->get_control();
        $c = $control[$type] ?? $this->default_control_state();

        // if running but pause/stop requested and lock expired, update state
        if( 
            (( $s['status'] ?? 'stopped') === 'running' ) && 
            ( isset($c['time']) && ( $c['time'] > 0 ) && ( ( time() - $c['time'] ) > self::$lock_duration ) ) &&
            ($c['pause_requested'] === true || $c['stop_requested'] === true)
        ) {
            if($c['stop_requested'] === true) {
                $s = $this->default_type_state();
                $c = $this->default_control_state();
            } else {
                $s['status'] = 'paused';
                $c['pause_requested'] = false;
            }
            $state[$type] = $s;
            $this->save_state($state);
        }
    

        return $this->format_status($type, $state);
    }

    public function all_statuses() {
        $state = $this->get_state();
        $control = $this->get_control();
        $statuses = [];
        foreach ($state as $type => $s) {
            $c = $control[$type] ?? $this->default_control_state();
            // if running but pause/stop requested and lock expired, update state
            if( 
                (( $s['status'] ?? 'stopped') === 'running' ) && 
                ( isset($c['time']) && ( $c['time'] > 0 ) && ( ( time() - $c['time'] ) > self::$lock_duration ) ) &&
                ($c['pause_requested'] === true || $c['stop_requested'] === true)
            ) {
                if($c['stop_requested'] === true) {
                    $s = $this->default_type_state();
                    $c = $this->default_control_state();
                } else {
                    $s['status'] = 'paused';
                    $c['pause_requested'] = false;
                }
                $state[$type] = $s;
                $this->save_state($state);
            }


            $statuses[$type] = $this->format_status($type, $state);
        }
        return $statuses;
    }

    private function format_status(string $type, array &$state) {
        $s = $state[$type] ?? $this->default_type_state();
        $control = $this->get_control();
        $c = $control[$type] ?? $this->default_control_state();

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
        ];

        // Report completed if marked so in state
        if (!empty($s['completed'])) {
            $status['percentage'] = 100;
            $status['remaining'] = 0;
            $status['status'] = 'completed';
        }

        // Reset state and control if completed or stopped before finishing
        if(
            !empty($s['completed']) || 
            (
                $status['status'] === 'stopped' && 
                $s['iterations_done'] < $s['iterations_total']
            ) 
        ) {
            // reset type state after reporting completed
            $state[$type] = $this->default_type_state();
            $this->save_state($state);
            $control[$type] = $this->default_control_state();
            $this->save_control($control);
        }

        return $status;
    }

    public function run_all() {
        $state = $this->get_state();
        foreach ($state as $type => $s) {
            // refresh state for each type to pick up changes made during processing of other types
            $latest_state = $this->get_state(true);
            $s = $latest_state[$type] ?? $s;
            
            if (($s['status'] ?? 'stopped') !== 'running') {
                $latest_control = $this->get_control();
                $c = $latest_control[$type] ?? $this->default_control_state();

                if ($c['stop_requested'] ?? false) {
                    $s['status'] = 'stopped';
                    $latest_state[$type] = $s;
                    $latest_control[$type]['stop_requested'] = false;
                    $this->save_state($latest_state);
                    $this->save_control($latest_control);
                }
                continue;
            }

            if (!isset($this->callback_map[$type])) continue;
            if ($this->is_locked($s)) continue;

            // lock, save and process
            $this->lock($latest_state, $type);
            $this->save_state($latest_state);

            $this->process_iterations($type, $this->callback_map[$type]);

            // Fetch state again to ensure we have the latest and then unlock
            $latest_state = $this->get_state(true);
            $this->unlock($latest_state, $type);
            $this->save_state($latest_state);
        }
    }

    private function process_iterations(string $type, callable $callback) {
        $s = $this->get_state_counts($type, true);

        $max_exec = (int) ini_get('max_execution_time');
        $max_exec = $max_exec !== 0 ? $max_exec : 55;
        $max_mem = ini_get('memory_limit') ? $this->return_bytes(ini_get('memory_limit')) : 128 * 1024 * 1024;
        $memory_safe = $max_mem * 0.80;
        $time_safe = $max_exec * 0.80;
        $start_time = microtime(true);
        $max_per_run = 50;
        $iterations_count = 0;

        while ($s['iterations_done'] < $s['iterations_total'] && $iterations_count < $max_per_run) {
            // limit iterations per run to avoid long blocking
            $iterations_count++;

            // force refresh so pause/stop requests are seen immediately
            $latest_control = get_transient($this->control_transient_key) ?: [];
            $latest_c = $latest_control[$type] ?? $this->default_control_state();

            // stop if paused or stopped
            if (!empty($latest_c['pause_requested']) || !empty($latest_c['stop_requested'])) {
                break;
            }

            // check memory and time using more precise calls
            if ((memory_get_usage(false) > $memory_safe) || ((microtime(true) - $start_time) > $time_safe)) {
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

            // Update state after each iteration to ensure progress is saved
            $this->update_state_counts($type, $s, true, false);

            if($iterations_count % 10 === 0) {
                gc_collect_cycles();
            }
        }
        
        // Get latest state again
        $latest_state   = $this->get_state(true);
        $latest_s       = $latest_state[$type];    
        $latest_control = $this->get_control();
        $latest_c       = $latest_control[$type] ?? $this->default_control_state();
        $is_completed   = ($latest_s['iterations_done'] ?? 0) >= ($latest_s['iterations_total'] ?? 0);

        // Update state if completed, paused or stopped
        if ($is_completed || ($latest_c['stop_requested'] ?? false) || ($latest_c['pause_requested'] ?? false)) {
            $latest_s['status'] = $is_completed || ($latest_c['stop_requested'] ?? false) ? 'stopped' : 'paused';
            $latest_s['completed'] = $is_completed;
            $latest_state[$type] = $latest_s;
            $this->save_state($latest_state);

            $latest_control[$type]['pause_requested'] = false;
            $latest_control[$type]['stop_requested'] = false;
            $latest_control[$type]['time'] = time();
            $this->save_control($latest_control);
        }

        gc_collect_cycles();
    }

    private function lock(array &$state, string $type) {
        $state[$type]['lock_until'] = time() + self::$lock_duration;
    }

    private function unlock(array &$state, string $type) {
        $state[$type]['lock_until'] = 0;
        $state[$type]['last_run'] = time();
    }

    private function is_locked(array $s) {
        return ($s['lock_until'] ?? 0) && time() < $s['lock_until'];
    }

    private function return_bytes($val) {
        $val = trim($val);
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
     * @param string $type The type key in the state array.
     * @param bool   $force Reload state from transient/option instead of cache.
     *
     * @return array {
     *     @type int $iterations_total
     *     @type int $iterations_done
     *     @type int $failed_count
     * }
     */
    private function get_state_counts($type, $force = false) {
        $state = $this->get_state($force);

        if (!isset($state[$type])) {
            return [
                'iterations_total' => 0,
                'iterations_done'  => 0,
                'failed_count'     => 0,
            ];
        }

        return [
            'iterations_total' => (int) ($state[$type]['iterations_total'] ?? 0),
            'iterations_done'  => (int) ($state[$type]['iterations_done'] ?? 0),
            'failed_count'     => (int) ($state[$type]['failed_count'] ?? 0),
        ];
    }


    /**
     * Update only the count-related fields for a given type.
     *
     * @param string $type The type key in the state array.
     * @param array  $counts {
     *     @type int $iterations_total
     *     @type int $iterations_done
     *     @type int $failed_count
     * }
     * @param bool $update_transient Whether to update transient.
     * @param bool $update_options   Whether to update options.
     */
    private function update_state_counts($type, array $counts, $update_transient = true, $update_options = true) {
        $state = $this->get_state( true ); // full state array

        if (!isset($state[$type])) {
            $state[$type] = $this->default_type_state();
        }

        // update only the count fields
        if (isset($counts['iterations_total'])) {
            $state[$type]['iterations_total'] = (int) $counts['iterations_total'];
        }
        if (isset($counts['iterations_done'])) {
            $state[$type]['iterations_done'] = (int) $counts['iterations_done'];
        }
        if (isset($counts['failed_count'])) {
            $state[$type]['failed_count'] = (int) $counts['failed_count'];
        }

        $this->save_state($state, $update_transient, $update_options);
    }


    /**
     * Get the state of the bg runner.
     *
     * If $force is true, the state will be reloaded from the transient or option.
     * If $force is false and the state cache already exists, the cached state will be returned.
     *
     * If the state is not available from the transient, it will be loaded from the option.
     * The state will then be cached and set as a transient for one week.
     *
     * @param bool $force Reload the state from the transient or option.
     *
     * @return array The state of the bg runner.
     */
    private function get_state($force = false) {
        // if not forcing and cache already exists, return cached
        if (!$force && $this->state_cache !== null) {
            return $this->state_cache;
        }

        // try transient first
        $state = get_transient($this->transient_key);

        if ($state !== false) {
            if ($force) {
                // update cache with the fresh transient
                $this->state_cache = $state;
            }
            return $state;
        }

        // fallback to option if transient missing
        $state = get_option($this->meta_key, []);
        $this->state_cache = $state;
        set_transient($this->transient_key, $state, WEEK_IN_SECONDS);

        return $state;
    }

    private function save_state($state, $update_transient = true, $update_options = true) {
        // Only write if changed to reduce option churn
        if ($this->state_cache === null || $this->state_cache !== $state) {
            $this->state_cache = $state;
            if ($update_transient) set_transient($this->transient_key, $state, WEEK_IN_SECONDS);
            if ($update_options) update_option($this->meta_key, $state, false);
        }
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

    private function get_control() {
        return get_transient($this->control_transient_key) ?: [];
    }

    private function save_control($control) {
        set_transient($this->control_transient_key, $control, WEEK_IN_SECONDS);
    }

    private function default_control_state() {
        return [
            'pause_requested' => false,
            'stop_requested'  => false,
            'time'            => time(),
        ];
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

            delete_transient($this->transient_key);
            delete_option($this->meta_key);
            delete_transient($this->control_transient_key);

            if (! defined('DOING_AJAX')) {
                wp_die('Locks removed successfully.');
            }
        }
    }
}