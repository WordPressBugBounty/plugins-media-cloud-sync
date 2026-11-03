<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * One-time fix for S3 / DigitalOcean buckets that still carry the pre-1.5.0 public-write policy.
 * Remove this file (plus its autoloader and main.php lines) once every site runs a major version newer than 1.5.0.
 * @since 1.5.0
 */
class BucketPolicyPatch {
    private static $instance = null;

    public function __construct() {
        add_action('admin_init', [$this, 'maybe_secure_bucket_policy']);
        add_action('admin_notices', [$this, 'bucket_policy_admin_notice']);
        add_action('admin_post_' . WPMCS_TOKEN . '_secure_bucket_policy', [$this, 'retry_secure_bucket_policy']);
    }

    /**
     * Once per bucket: fix a policy left over from versions that granted public write.
     * @since 1.5.0
     */
    public function maybe_secure_bucket_policy($force = false) {
        if (!current_user_can('manage_options') || !Utils::is_service_enabled() || !in_array(Utils::get_service(), ['s3', 'docean'], true)) {
            return;
        }
        $meta     = Schema::getConstant('GLOBAL_SETTINGS_KEY');
        $state    = (array) Utils::get_option('bucket_policy_security', [], $meta);
        $bucket   = Utils::get_service() . ':' . (Utils::get_credentials()['bucketConfig']['bucket_name'] ?? '');
        $secured  = (array) ($state['secured'] ?? []);
        if (!$force && in_array($bucket, $secured, true)) {
            return;
        }

        $result = $this->secure_bucket_policy();
        // Recorded even on failure so the provider isn't called on every admin page; the notice offers a retry.
        Utils::update_option('bucket_policy_security', [
            'secured'      => array_values(array_unique(array_merge($secured, [$bucket]))),
            'error'        => !empty($result['success']) ? '' : (string) ($result['message'] ?? ''),
            'error_bucket' => !empty($result['success']) ? '' : $bucket,
        ], $meta);
    }

    /**
     * Notice when the one-time bucket policy fix couldn't be applied.
     * @since 1.5.0
     */
    public function bucket_policy_admin_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $state  = (array) Utils::get_option('bucket_policy_security', [], Schema::getConstant('GLOBAL_SETTINGS_KEY'));
        $bucket = Utils::get_service() . ':' . (Utils::get_credentials()['bucketConfig']['bucket_name'] ?? '');
        // Only for the bucket that failed and is still connected.
        if (empty($state['error']) || ($state['error_bucket'] ?? '') !== $bucket) {
            return;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=' . WPMCS_TOKEN . '_secure_bucket_policy'), WPMCS_TOKEN . '_secure_bucket_policy');
        Notices::dismissible('bucket_policy', $bucket . '|' . $state['error'], 'error', sprintf(
            '<p><strong>%1$s</strong> %2$s</p><p>%3$s</p><p><a class="button button-primary" href="%4$s">%5$s</a></p>',
            esc_html__('Media Cloud Sync:', 'media-cloud-sync'),
            esc_html__('Your bucket policy still allows anyone to upload, change or delete files in your bucket. Media Cloud Sync tried to restrict it to public read only but could not:', 'media-cloud-sync'),
            esc_html($state['error']),
            esc_url($url),
            esc_html__('Fix now', 'media-cloud-sync')
        ));
    }

    /**
     * Retry the bucket policy fix from the notice.
     * @since 1.5.0
     */
    public function retry_secure_bucket_policy() {
        check_admin_referer(WPMCS_TOKEN . '_secure_bucket_policy');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'media-cloud-sync'));
        }
        $this->maybe_secure_bucket_policy(true);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /**
     * Rewrites the old public-write plugin statement to read-only, keeping its private-path carve-out.
     * Works through the connected provider's own client (S3 and DigitalOcean Spaces share the S3 policy API).
     */
    private function secure_bucket_policy() {
        $client = Service::instance()->get_active_client();
        $bucket = Utils::get_credentials()['bucketConfig']['bucket_name'] ?? '';
        // No client or policy we can read means we can't tell it's insecure, so skip quietly rather than warn.
        if (!$client || $bucket === '') {
            return ['success' => true];
        }

        try {
            $existing = (string) $client->getBucketPolicy(['Bucket' => $bucket])['Policy'];
        } catch (\Exception $ex) {
            return ['success' => true];
        }

        $provider = Utils::get_service() === 'docean' ? DOcean::class : S3::class;
        $prefix   = self::insecure_policy_prefix($existing, $bucket, $provider);
        if ($prefix === null) {
            return ['success' => true];
        }

        try {
            $client->putBucketPolicy(['Bucket' => $bucket, 'Policy' => $provider::build_bucket_policy($bucket, $prefix, $existing)]);
            return ['success' => true];
        } catch (\Exception $ex) {
            return ['success' => false, 'message' => __('The bucket policy could not be updated. Check that your access keys can read and change the bucket policy.', 'media-cloud-sync')];
        }
    }

    /**
     * Private-path carve-out of an insecure plugin statement (old policies granted public write), or null if there is none.
     * @return string|null '' when the old statement covered the whole bucket.
     */
    public static function insecure_policy_prefix($existing_policy, $bucket, $provider = S3::class) {
        $existing = json_decode((string) $existing_policy, true);
        foreach ($provider::policy_statements($existing) as $statement) {
            if (!$provider::is_plugin_policy_statement($statement, $bucket)) {
                continue;
            }
            $actions = array_map('strtolower', array_map('strval', (array) ($statement['Action'] ?? [])));
            if (empty(array_diff($actions, ['s3:getobject']))) {
                continue;
            }
            foreach ((array) ($statement['NotResource'] ?? []) as $arn) {
                if (preg_match('#^arn:aws:s3:::' . preg_quote($bucket, '#') . '/(.+)/\\*$#', (string) $arn, $m)) {
                    return $m[1];
                }
            }
            return '';
        }
        return null;
    }

    /**
     * Get instance
     * @return BucketPolicyPatch
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
