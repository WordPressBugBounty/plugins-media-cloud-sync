<?php
namespace Dudlewebs\WPMCS;
defined('ABSPATH') || exit;

class Sync {
    protected static $instance = null;

    private $syncClasses = [
        'SyncToCloud'
    ];
    
    /**
     * Constructor method.
     */
    public function __construct() {
        // Initialize sync classes
        foreach ($this->syncClasses as $class) {
            $class_with_namespace = __NAMESPACE__.'\\'. trim($class);
            if(class_exists($class_with_namespace)) {
                $class_with_namespace::instance();
            }
        }
    }


    /**
     * Add new sync classes to the list of sync classes.
     *
     * @param array $classes List of new sync classes to add.
     *
     * @return void
     */
    public function add_sync_classes($classes = []) {
        // Only add classes that are not already in syncClasses
        $new_classes = array_diff($classes, $this->syncClasses);

        // Merge new classes
        $this->syncClasses = array_merge($this->syncClasses, $new_classes);

        // Initialize new classes
        foreach ($new_classes as $class) {
            $class_with_namespace = __NAMESPACE__ . '\\' . trim($class);
            if (class_exists($class_with_namespace)) {
                $class_with_namespace::instance();
            }
        }
    }


    /**
     * Gets the status of the sync.
     *
     * @return array An associative array with the action as key and the status
     *               as value.
     */
    public function get_status() {
        $status = [];
        foreach ($this->syncClasses as $class) {
            $class_with_namespace = __NAMESPACE__.'\\'. trim($class);
            if(!class_exists($class_with_namespace)) continue;
            $instance = $class_with_namespace::instance();
            $action = $instance->get_action();
            $status[$action] = $instance->get_status();
        }
        return $status;
    }


    /**
     * Starts the sync process.
     * 
     */
    public function start( $action, $options = [] ) {
        $action_class = $this->get_class_by_action($action);

        if($action_class) {
            // BGRunner's lock is per-type — nothing else stops two different actions from running at once.
            $blocking_action = $this->find_running_action($action);
            if ($blocking_action) {
                return [
                    'success' => false,
                    /* translators: %s: the action that is currently running */
                    'message' => sprintf(__('Another sync (%s) is currently running. Stop or finish it before starting a new one.', 'media-cloud-sync'), $blocking_action),
                ];
            }

            $start_result = $action_class->start( $options );
            // A job's start() can signal a precondition failure (e.g. no destination
            // configured) by returning ['success' => false, ...] instead of the usual
            // void/true — short-circuit and pass that straight through instead of masking
            // it with a status object that always reads as started.
            if (is_array($start_result) && isset($start_result['success']) && $start_result['success'] === false) {
                return $start_result;
            }
            $status = $action_class->get_status();
            // A job can report what it did besides (or instead of) starting a run.
            if (is_array($start_result) && !empty($start_result['notice'])) {
                $status['notice'] = $start_result['notice'];
            }
            return $status;
        } else {
            /* translators: %s: action name */
            throw new \Exception(sprintf(__('Invalid action: %s', 'media-cloud-sync'), $action));
        }
    }

    // Action string of another registered action currently running/paused, or null.
    private function find_running_action( $except_action ) {
        $runner = BGRunner::instance();
        foreach ($this->syncClasses as $class) {
            $class_with_namespace = __NAMESPACE__.'\\'. trim($class);
            if(!class_exists($class_with_namespace)) continue;

            $instance = $class_with_namespace::instance();
            $other_action = $instance->get_action();
            if ($other_action === $except_action) continue;

            $status = $runner->status($other_action);
            if (in_array($status['status'] ?? 'stopped', ['running', 'paused'], true)) {
                return $other_action;
            }
        }
        return null;
    }

    /**
     * Pauses the sync process.
     * 
     */
    public function pause( $action ) {
        $action_class = $this->get_class_by_action($action);

        if($action_class) {
            $action_class->pause();
            return $action_class->get_status();
        } else {
            throw new \Exception(sprintf(__('Invalid action: %s', 'media-cloud-sync'), $action));
        }
    }


    /**
     * Resumes the sync process.
     * 
     */
    public function resume( $action ) {
        $action_class = $this->get_class_by_action($action);

        if($action_class) {
            $action_class->resume();
            return $action_class->get_status();
        } else {
            throw new \Exception(sprintf(__('Invalid action: %s', 'media-cloud-sync'), $action));
        }
    }


    /**
     * Stops the sync process.
     *
     */
    public function stop( $action ) {
        $action_class = $this->get_class_by_action($action);

        if($action_class) {
            $action_class->stop();
            return $action_class->get_status();
        } else {
            throw new \Exception(sprintf(__('Invalid action: %s', 'media-cloud-sync'), $action));
        }
    }


    /**
     * Returns the class associated with the given action.
     * 
     * @param string $action The action to search for.
     * 
     * @return string|null The class associated with the action, or null if not found.
     */
    public function get_class_by_action( $action ) {
        foreach ($this->syncClasses as $class) {
            $class_with_namespace = __NAMESPACE__.'\\'. trim($class);
            if(!class_exists($class_with_namespace)) continue;

            if($class_with_namespace::instance()->get_action() === $action) {
                return $class_with_namespace::instance();
            }
        }

        return false;
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
