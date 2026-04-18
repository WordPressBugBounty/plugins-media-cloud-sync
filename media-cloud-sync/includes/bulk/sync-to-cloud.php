<?php
namespace Dudlewebs\WPMCS;
defined('ABSPATH') || exit;


class SyncToCloud {
    private static $instance = null;
    private $state_key;

    private $action = 'sync_to_cloud';

    private $runner;

    /**
     * Constructor method.
     */
    public function __construct() {
        $this->state_key = WPMCS_TOKEN . '_' . $this->action . '_state';
        $this->runner = BGRunner::instance();
        $this->runner->set_callback($this->action, [$this, 'sync_to_cloud']);
    }

    /**
     * Get Action
     */
    public function get_action() {
        return $this->action;
    }

    /**
     * Syncs to Cloud.
     */
    public function sync_to_cloud($offset = 0, $total_iterations = 0) {
        if($offset == 0) {
            // Clear previous state
            $this->clear_state();
        }
        $state          = $this->get_state();
        $failed_count   = isset($state['failed']) ? $state['failed'] : [];
        
        $all_media_count = Counter::get_count( 'all', false );

        $is_failed = false;

        if(!empty($all_media_count)) {
            foreach($all_media_count as $source_type => $counts) {
                $total      = (int)$counts['total'];
                $uploaded   = (int)$counts['uploaded'];
                $failed     = isset($failed_count[$source_type]) ? $failed_count[$source_type] : 0;

                $pending = $total - ($uploaded + $failed);
                if($pending > 0) {
                    $handler = Integration::instance()->get_handler_class($source_type);
                    if ($handler) {
                        $result = $handler::instance()->upload_pending_media($source_type, 1, $failed);

                        if(isset($result['failed']) && !empty($result['failed']) && $result['failed'] > 0) {
                            $failed_count[$source_type] = $failed + $result['failed'];
                            $is_failed = true;
                        }
                        break;
                    }
                }
            }
        }

        if(($offset+1) == $total_iterations) {
            $this->clear_state();
        } else {
            $state = [
                'failed' => $failed_count
            ];
            $this->set_state($state);
        }

        return !$is_failed;
    }

    /**
     * Start the action.
     */
    public function start() {
        // Fetch and update media counts for make sure they are up to date
        Counter::fetch_and_update();

        // Start the action
        $this->runner->start($this->action, $this->get_pending_media_count());
    }

    /**
     * Stop the action.
     */
    public function stop() {
        $this->runner->stop($this->action);
    }

    /**
     * Pause the action.
     */
    public function pause() {
        $this->runner->pause($this->action);
    }

    /**
     * Resume the action.
     */
    public function resume() {
        $this->runner->resume($this->action);
    }

    /**
     * Get Status
     */
    public function get_status() {
        $status = $this->runner->status($this->action);
        // Clear state if stopped or completed
        if(isset($status['status'])) {
            if($status['status'] == 'stopped' || $status['status'] == 'completed') {
                $this->clear_state();
            }
        }

        if($status['total'] <= 0) {
            $status['total'] = $this->get_pending_media_count();
        }
        return $status;
    }


    /**
     * Retry Single Sync
     * @since 1.3.3
     */
    public function retry_single($id, $source_type = 'media_library') {
        $handler = Integration::instance()->get_handler_class($source_type);    
        if ($handler) {
            $handler::instance()->upload_single_media($id, $source_type);
        }
    }


    /**
     * Set State
     */
    private function set_state($state) {
        set_transient( $this->state_key, $state, WEEK_IN_SECONDS );
    }

    /**
     * Get State
     */
    private function get_state() {
        $state = get_transient( $this->state_key );
        if ( ! is_array( $state ) ) {
            $state = [];
        }
        
        return $state;
    }


    /**
     * Get pending Media count
     */
    private function get_pending_media_count() {
        $counts = Counter::get_count();

        $total = isset($counts['total']) ? (int)$counts['total'] : 0;
        $uploaded = isset($counts['uploaded']) ? (int)$counts['uploaded'] : 0;
        $pending = $total - $uploaded;

        return $pending;
    }
    

    /**
     * Clear State
     */
    private function clear_state() {
        delete_transient( $this->state_key );
    }


    /**
     * Retrieves the singleton instance of the class.
     *
     * @return self
     */
    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }
}