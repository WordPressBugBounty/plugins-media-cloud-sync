<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Upgrade class
 *
 * Stateless batch-wise DB upgrade runner
 *
 * @since 1.3.6
 */
class Upgrade {

    private static $instance = null;

    private $token;
    private $current_version = false;
    private $latest_version  = false;
    private $upgrade_queue   = [];
    private $upgrade_queue_key = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->token          = WPMCS_TOKEN;
        $this->latest_version = WPMCS_DB_UPGRADE_VERSION;
        $this->upgrade_queue_key = $this->token . '_upgrade_queue';

        $this->current_version = get_option( $this->token . '_db_upgrade_version', false );
        $this->upgrade_queue   = get_transient( $this->upgrade_queue_key ) ?: [];

        $this->register_upgrades();
    }


    /**
     * Registers upgrades to be performed on the database.
     *
     * Upgrades are registered in the format of [$version, $callback] where
     * $version is the target version to upgrade to and $callback is a
     * callable that will be executed when the upgrade is performed.
     *
     * The upgrades are registered in the order in which they should be
     * performed. The upgrade process will only be performed if the current
     * version is less than the latest version.
     */
    private function register_upgrades() {
        // Force DB upgrade for fresh installs prior to 1.0.0
        if ( $this->latest_version === '1.0.0' && $this->current_version === '0.0.0' ) { 
            $this->add_upgrade( '1.0.0', [ $this, 'upgrade_to_1_0_0' ] );
        }

        // If no version is set, set to latest version to avoid upgrades
        if( $this->current_version === false ) {
            $this->current_version = $this->latest_version;
            update_option(
                $this->token . '_db_upgrade_version',
                $this->latest_version,
                false
            );
        }

        // Add future upgrades here
        // if ( version_compare( $this->current_version, '1.0.1', '<' ) ) {
        //     $this->add_upgrade( '1.0.1', [ $this, 'upgrade_to_1_0_1' ] );
        // }

    }

    /**
     * Add upgrade
     */
    public function add_upgrade( $version, $callback ) {
        if ( isset( $this->upgrade_queue[ $version ] ) ) {
            return;
        }

        $this->upgrade_queue[ $version ] = $callback;

        // Update queue in DB
        set_transient( $this->token . '_upgrade_queue', $this->upgrade_queue, DAY_IN_SECONDS );
    }

    /** 
     * Start upgrade
     */
    public function start_upgrade() {
        // Reset previous upgrade data
        delete_transient( $this->upgrade_queue_key );
        delete_transient( $this->token . '_upgrade_total' );
        delete_transient( $this->token . '_upgrade_completed' );

        set_transient( $this->token . '_upgrade_started', true, DAY_IN_SECONDS );

        return $this->run();
    }

    /**
     * Get Progress
     */
    public function get_progress() {
        if( get_transient( $this->token . '_upgrade_started' ) === false ) {
            return [
                'status'     => 'not_started',
                'percentage' => 0,
            ];
        }

        return $this->run();
    }

    /**
     * Run the upgrade process.
     *
     * This function will check if all upgrades are finished and return
     * the result of the upgrade process. If not all upgrades are finished,
     * it will run the first task in the upgrade queue and return the progress
     * of the upgrade process.
     *
     * @return array|string The result of the upgrade process or the progress of the upgrade process.
     */
    private function run() {
        $total_key     = $this->token . '_upgrade_total';
        $completed_key = $this->token . '_upgrade_completed';

        // Initialize counters once
        if ( get_transient( $total_key ) === false ) {

            if ( empty( $this->upgrade_queue ) ) {
                return $this->finish_upgrade();
            }

            set_transient( $this->upgrade_queue_key, $this->upgrade_queue, DAY_IN_SECONDS );
            set_transient( $total_key, count( $this->upgrade_queue ), DAY_IN_SECONDS );
            set_transient( $completed_key, 0, DAY_IN_SECONDS );
        }

        // All tasks finished
        if ( empty( $this->upgrade_queue ) ) {
            return $this->finish_upgrade();
        }

        // Get first task (PHP-safe)
        foreach ( $this->upgrade_queue as $version => $callback ) {
            break;
        }

        $total     = max( 1, (int) get_transient( $total_key ) );
        $completed = (int) get_transient( $completed_key );

        $result = is_callable( $callback ) ? call_user_func( $callback ) : true;

        // Task still running
        if ( is_array( $result ) && empty( $result['done'] ) ) {
            return $this->progress_response(
                $completed + ( (int) ( $result['percentage'] ?? 0 ) / 100 ),
                $total
            );
        }

        // Task completed
        unset( $this->upgrade_queue[ $version ] );
        $completed++;

        set_transient( $this->upgrade_queue_key, $this->upgrade_queue, DAY_IN_SECONDS );
        set_transient( $completed_key, $completed, DAY_IN_SECONDS );

        return empty( $this->upgrade_queue )
            ? $this->finish_upgrade()
            : $this->progress_response( $completed, $total );
    }


    /**
     * Finish the upgrade process by updating the database version and deleting the upgrade queue and counters.
     *
     * @return array The result of the upgrade process with status 'completed' and percentage 100.
     */
    private function finish_upgrade() {
        update_option(
            $this->token . '_db_upgrade_version',
            $this->latest_version,
            false
        );

        delete_transient( $this->upgrade_queue_key );
        delete_transient( $this->token . '_upgrade_total' );
        delete_transient( $this->token . '_upgrade_completed' );
        delete_transient( $this->token . '_upgrade_started' );

        $this->current_version = $this->latest_version;

        return [
            'status'     => 'completed',
            'percentage' => 100,
        ];
    }


    /**
     * Return a response for the progress of the upgrade process.
     *
     * @param int $completed_units Number of completed units.
     * @param int $total Total number of units.
     *
     * @return array {
     *     'status' => string Status of the upgrade process (running/completed).
     *     'percentage' => int Percentage of completed units (integer between 0 and 100).
     * }
     */
    private function progress_response( $completed_units, $total ) {

        $percentage = ( $completed_units / max( 1, $total ) ) * 100;

        return [
            'status'     => 'running',
            'percentage' => (int) floor( $percentage ),
        ];
    }



    /* --------------------------------------------------------------------
     * Batch Upgrade
     * ------------------------------------------------------------------ */


    /**
     * Upgrades the database from version 0.0.0 to version 1.0.0.
     *
     * This upgrade does the following:
     * - Populate the `original_source_path` and `original_key` columns with the relevant data from the `extra` column.
     * - Set the `original_source_path` and `original_key` columns to NULL if the `extra` column does not contain the relevant data.
     *
     * This upgrade is done in chunks of 200 rows at a time, and keeps track of its progress in a transient.
     * The progress is returned as a percentage, with a hard stop at 99% to avoid repeated stalls.
     *
     * @return array {
     *     'done' => bool Whether the upgrade is completed.
     *     'percentage' => int Percentage of completed upgrade (integer between 0 and 100).
     * }
     */
    private function upgrade_to_1_0_0() {
        global $wpdb;

        $table = Db::get_table_name();
        $limit = 200;
        $key   = $this->token . '_upgrade_1_0_0_state';

        $state = get_transient( $key ) ?: [
            'last_id' => 0,
            'total'   => null,
            'done'    => 0,
            'stall'   => 0,
        ];

        // Hard stop
        if ( $state['stall'] >= 5 ) {
            delete_transient( $key );
            return [ 'done' => true, 'percentage' => 100 ];
        }

        // Total count (once)
        if ( $state['total'] === null ) {
            $state['total'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table}
                WHERE (original_source_path IS NULL OR original_source_path = '')
                AND extra IS NOT NULL"
            );

            if ( ! $state['total'] ) {
                delete_transient( $key );
                return [ 'done' => true, 'percentage' => 100 ];
            }
        }

        // Cursor-based fetch
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, extra FROM {$table}
                WHERE id > %d
                AND (original_source_path IS NULL OR original_source_path = '')
                AND extra IS NOT NULL
                ORDER BY id ASC
                LIMIT %d",
                $state['last_id'],
                $limit
            ),
            ARRAY_A
        );

        if ( ! $rows ) {
            delete_transient( $key );
            return [ 'done' => true, 'percentage' => 100 ];
        }

        $case_sp   = [];
        $case_k    = [];
        $case_ext  = [];
        $ids       = [];
        $max_id    = $state['last_id'];

        foreach ( $rows as $r ) {
            $max_id = max( $max_id, (int) $r['id'] );
            $e = Utils::maybe_unserialize( $r['extra'] );

            if (
                empty( $e['original']['source_path'] ) ||
                empty( $e['original']['key'] )
            ) {
                continue;
            }

            $id = (int) $r['id'];

            $ids[]      = $id;
            $case_sp[]  = $wpdb->prepare( "WHEN id=%d THEN %s", $id, $r['extra'] ? $e['original']['source_path'] ?? '' : '' );
            $case_k[]   = $wpdb->prepare( "WHEN id=%d THEN %s", $id, $r['extra'] ? $e['original']['key'] ?? '' : '' );

            // Cleanup extra
            unset( $e['original'] );
            $new_extra = Utils::maybe_serialize( $e );

            $case_ext[] = $wpdb->prepare( "WHEN id=%d THEN %s", $id, $new_extra );
        }

        if ( ! $ids ) {
            $state['last_id'] = $max_id;
            $state['stall']++;
            set_transient( $key, $state, DAY_IN_SECONDS );

            return [
                'done'       => false,
                'percentage' => (int) ceil( $state['done'] / $state['total'] * 100 ),
            ];
        }

        // Single UPDATE (migration + cleanup)
        $wpdb->query(
            "UPDATE {$table}
            SET
            original_source_path = CASE " . implode( ' ', $case_sp ) . " ELSE original_source_path END,
            original_key         = CASE " . implode( ' ', $case_k )  . " ELSE original_key END,
            extra                = CASE " . implode( ' ', $case_ext ) . " ELSE extra END
            WHERE id IN (" . implode( ',', $ids ) . ")
            AND (original_source_path IS NULL OR original_source_path = '')"
        );

        $state['done']     += count( $ids );
        $state['last_id']   = $max_id;
        $state['stall']     = 0;

        set_transient( $key, $state, DAY_IN_SECONDS );

        return [
            'done'       => false,
            'percentage' => min( 99, (int) ceil( $state['done'] / $state['total'] * 100 ) ),
        ];
    }




    /**
     * Is upgrade needed
     */
    public function is_upgrade_needed() {
        return version_compare( $this->current_version, $this->latest_version, '<' ) || 
                ($this->current_version === false && $this->latest_version === '1.0.0');
    }

    /**
     * Singleton
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
