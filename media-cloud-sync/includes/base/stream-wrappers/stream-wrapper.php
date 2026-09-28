<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Last-resort, read-only fallback for wpmcs_get_attached_file — every
 * specific integration (Compatibility, EWWW, Meta Slider, all priority 10)
 * runs first and wins if it matches; this only engages when nothing else
 * has already resolved a real local path.
 *
 * @since 1.4.3
 */
class StreamWrapper {
    private static $instance = null;
    const PROTOCOL = 'wpmcssw';
    private $registered = false;
    private $bucket_name = null;
    private $failed_bucket = null;

    public function __construct() {
        add_filter('wpmcs_get_attached_file', [$this, 'stream_wrapper_fallback'], 999, 4);
    }

    public function stream_wrapper_fallback($url, $file, $attachment_id, $item) {
        // Skip the filtered wp_get_upload_dir() call when $url is clearly still a cloud URL, not a local path.
        $looks_like_url = stripos((string) $url, 'http') === 0;
        if (!$looks_like_url) {
            $upload_basedir = trailingslashit(wp_get_upload_dir()['basedir']);
            if (strpos((string) $url, $upload_basedir) === 0 && file_exists($url)) {
                return $url;
            }
        }
        if (empty($item) || empty($item['key'])) {
            return $url;
        }
        if ($this->register()) {
            // $url always resolves to the main file's URL regardless of which WP core hook fired; $file doesn't, so use it to detect an original-image request.
            $key = $item['key'];
            if (!empty($item['original_source_path']) && !empty($item['original_key'])
                && basename((string) $file) === basename($item['original_source_path'])) {
                $key = $item['original_key'];
            }
            return self::PROTOCOL . '://' . $this->bucket_name . '/' . $key;
        }
        // No disk-restore fallback here on purpose — that's what a dedicated integration is for.
        return $url;
    }

    private function register() {
        $bucket_name = Utils::get_credentials('bucketConfig')['bucket_name'] ?? null;
        $service = Utils::get_credentials('service');
        if ($this->registered && $this->bucket_name === $bucket_name) {
            return true;
        }
        // Bucket-keyed failure memoization avoids re-attempting/re-logging on every call of a persistently broken setup.
        if ($this->failed_bucket === $bucket_name) {
            return false;
        }
        if (empty($bucket_name)) {
            return false;
        }
        $client = Service::instance()->get_active_client();
        if (empty($client)) {
            $this->failed_bucket = $bucket_name;
            return false;
        }

        try {
            // Unregister explicitly first — the two vendor register() methods aren't consistently idempotent.
            if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
                stream_wrapper_unregister(self::PROTOCOL);
            }
            // Branches on the credential value, not instanceof, to avoid force-loading the GCS SDK on non-GCS sites.
            if ($service === 'gcloud') {
                ReadOnlyGcsStreamWrapper::register($client, self::PROTOCOL);
            } else {
                ReadOnlyS3StreamWrapper::register($client, self::PROTOCOL);
            }
        } catch (\Throwable $e) {
            // Must never propagate — this runs inside a filter WP core fires broadly.
            Logger::instance()->add_log('stream_wrapper_register_failed', 0, 'media_library', ['message' => $e->getMessage()]);
            $this->registered = false;
            $this->failed_bucket = $bucket_name;
            return false;
        }

        // Default context ensures unaware callers get seekable reads (needed by e.g. getimagesize()).
        stream_context_set_default([self::PROTOCOL => ['seekable' => true]]);

        $this->registered = true;
        $this->bucket_name = $bucket_name;
        $this->failed_bucket = null;
        return true;
    }

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
