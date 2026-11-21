<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Logger {
    private static $instance = null;
    private $log_dir;
    private $log_file;
    private $handle = null;

    private function __construct() {
        $upload_dir   = wp_upload_dir();
        $this->log_dir = trailingslashit($upload_dir['basedir']) . Schema::getConstant('UPLOADS');

        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }

        $this->log_file = $this->log_dir . '/sync-log.json';
    }

    public function __destruct() {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * Singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reuse a single open file handle for the process instead of
     * reopening/closing on every call (heavy when many logs happen at once)
     */
    private function get_handle($create = true) {
        if (is_resource($this->handle)) {
            return $this->handle;
        }

        if (!$create && !file_exists($this->log_file)) {
            return null;
        }

        $this->handle = fopen($this->log_file, 'c+');
        return $this->handle ?: null;
    }

    /**
     * Read logs under a shared lock
     */
    private function read_logs() {
        $handle = $this->get_handle(false);
        if (!$handle) return [];

        $logs = [];
        try {
            if (flock($handle, LOCK_SH)) {
                rewind($handle);
                $content = stream_get_contents($handle);
                $logs = json_decode($content, true) ?: [];
            }
        } finally {
            flock($handle, LOCK_UN);
        }

        return $logs;
    }

    /**
     * Read-modify-write logs under an exclusive lock held for the whole cycle
     */
    private function update_logs(callable $mutator) {
        $handle = $this->get_handle(true);
        if (!$handle) return;

        try {
            if (flock($handle, LOCK_EX)) {
                rewind($handle);
                $content = stream_get_contents($handle);
                $logs    = json_decode($content, true) ?: [];

                $logs = $mutator($logs);

                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, wp_json_encode($logs));
                fflush($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
        }
    }

    /**
     * Add or update a log entry
     */
    public function add_log($type, $media_id, $source_type, $log = []) {
        $entry = [
            'time'        => current_time('mysql'),
            'type'        => $type,
            'media_id'    => $media_id,
            'source_type' => $source_type,
        ];

        $this->append_log($entry, $log);
    }

    /**
     * Append log using file-based indexing
     */
    private function append_log($entry, $log=[]) {
        $this->update_logs(function($logs) use ($entry, $log) {
            // Unique key per media/type/source
            $key = $entry['media_id'] . '|' . $entry['type'] . '|' . $entry['source_type'];

            if (isset($logs[$key])) {
                if (!isset($logs[$key]['logs']) || !is_array($logs[$key]['logs'])) {
                    $logs[$key]['logs'] = [];
                }
                $logs[$key]['logs'][] = $log;
                $logs[$key]['time'] = $entry['time'] ?? current_time('mysql');
            } else {
                $logs[$key] = $entry;
                $logs[$key]['logs'][] = $log;
            }

            return $logs;
        });
    }

    /**
     * Get log by media ID, type, and source type
     */
    public function get_log($type, $media_id, $source_type) {
        $key = $media_id . '|' . $type . '|' . $source_type;
        return $this->read_logs()[$key] ?? [];
    }

    /**
     * Get all logs (numeric array, supports pagination)
     */
    public function get_all_logs($page = null, $per_page = null) {
        if (!file_exists($this->log_file))  {
            return [
                'logs'        => [],
                'total'       => 0,
                'total_pages' => 0,
                'page'        => 1,
                'per_page'    => $per_page ?:1,
            ];
        }

        $logs = array_values($this->read_logs());

        $total = count($logs);

        if ($page === null || $per_page === null) {
            return [
                'logs'        => $logs,
                'total'       => $total,
                'total_pages' => 1,
                'page'        => 1,
                'per_page'    => $total,
            ];
        }

        $total_pages = (int) ceil($total / $per_page);
        $page = max(1, (int) $page);
        $offset = ($page - 1) * $per_page;

        return [
            'logs'        => array_slice($logs, $offset, $per_page),
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => $page,
            'per_page'    => $per_page,
        ];
    }

    /**
     * Get logs by type (supports pagination)
     */
    public function get_logs_by_type($type, $page = null, $per_page = null) {
        if (!file_exists($this->log_file)) {
            return [
                'logs'        => [],
                'total'       => 0,
                'total_pages' => 0,
                'page'        => 1,
                'per_page'    => $per_page ?:1,
            ];
        };
        
        $all_logs = $this->read_logs();

        $logs = array_values(array_filter($all_logs, function($log) use ($type) {
            return isset($log['type']) && $log['type'] === $type;
        }));

        $total = count($logs);

        if ($page === null || $per_page === null) {
            return [
                'logs'        => $logs,
                'total'       => $total,
                'total_pages' => 1,
                'page'        => 1,
                'per_page'    => $total,
            ];
        }

       
        $total_pages = (int) ceil($total / $per_page);
        $page = max(1, (int) $page);
        $offset = ($page - 1) * $per_page;

        return [
            'logs'        => array_slice($logs, $offset, $per_page),
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => $page,
            'per_page'    => $per_page,
        ];
    }
    

    /**
     * Remove logs by type
     */
    public function remove_logs_by_type($type) {
        if (!file_exists($this->log_file)) return;

        $this->update_logs(function($logs) use ($type) {
            foreach ($logs as $key => $log) {
                if (isset($log['type']) && $log['type'] === $type) {
                    unset($logs[$key]);
                }
            }

            return $logs;
        });
    }

    /**
     * Remove logs by media ID, type, and source type
     */
    public function remove_log($type, $media_id, $source_type) {
        if (!file_exists($this->log_file)) return;

        $this->update_logs(function($logs) use ($type, $media_id, $source_type) {
            $key = $media_id . '|' . $type . '|' . $source_type;

            if (isset($logs[$key])) {
                unset($logs[$key]);
            }

            return $logs;
        });
    }

    /**
     * Remove Log by media ID & source type
     */
    public function remove_log_by_media_id($media_id, $source_type) {
        if (!file_exists($this->log_file)) return;

        $this->update_logs(function($logs) use ($media_id, $source_type) {
            return array_filter($logs, function($log) use ($media_id, $source_type) {
                return $log['media_id'] !== $media_id || $log['source_type'] !== $source_type;
            });
        });
    }

    /**
     * Clear all logs
     */
    public function clear_logs() {
        if (is_resource($this->handle)) {
            fclose($this->handle);
            $this->handle = null;
        }

        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
    }
}