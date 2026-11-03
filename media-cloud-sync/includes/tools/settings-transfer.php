<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Settings export/import (Import / Export page): the Settings options and the delivery provider as a JSON file, without
 * storage keys or other secrets, and an import that previews the changes before applying them.
 * @since 1.5.0
 */
class SettingsTransfer {
    private static $instance = null;

    const FORMAT  = 'media-cloud-sync-settings';
    const VERSION = 1;

    /** The export file's data. */
    public function export() {
        $credentials = Utils::get_credentials();
        $cdn         = (string) ($credentials['cdn']['service'] ?? '');
        return [
            'format'         => self::FORMAT,
            'version'        => self::VERSION,
            'plugin_version' => WPMCS_VERSION,
            'exported_at'    => gmdate('c'),
            'site'           => home_url(),
            // For reference only: an import never changes storage.
            'storage'        => ['service' => (string) Utils::get_service(), 'bucket' => (string) Utils::get_bucket_name()],
            'delivery'       => [
                'service' => $cdn,
                'label'   => $this->delivery_label((string) Utils::get_service(), $cdn, (string) ($credentials['cdn']['label'] ?? '')),
            ],
            'settings'       => array_diff_key((array) Utils::get_settings(), array_flip($this->secret_keys())),
        ];
    }

    /**
     * Checks an export file against this site; with $apply, saves it. Returns the changes (label, from, to),
     * warnings, and on apply whether the delivery provider changed (the page reloads then).
     */
    public function import($data, $apply) {
        if (!is_array($data) || ($data['format'] ?? '') !== self::FORMAT || !is_array($data['settings'] ?? null)) {
            return ['success' => false, 'message' => __('This isn\'t a Media Cloud Sync settings file.', 'media-cloud-sync')];
        }
        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            return ['success' => false, 'message' => __('This settings file comes from a newer Media Cloud Sync. Update the plugin, then import it again.', 'media-cloud-sync')];
        }
        if (!Utils::is_service_enabled()) {
            return ['success' => false, 'message' => __('Connect your storage first: settings are imported for the connected storage.', 'media-cloud-sync')];
        }
        $running = $this->running_job();
        if ($running !== '') {
            /* translators: %s: name of the running job */
            return ['success' => false, 'message' => sprintf(__('A job (%s) is running. Let it finish or stop it, then import.', 'media-cloud-sync'), $running)];
        }

        $schema   = UISchema::instance()->get_all();
        $storage  = (string) Utils::get_service();
        $current  = (array) Utils::get_settings();
        $defaults = (array) ($schema['settingsDefaultValues'][$storage] ?? []);
        $warnings = [];

        $credentials  = Utils::get_credentials();
        $delivery     = (string) ($credentials['cdn']['service'] ?? '');
        $file_cdn     = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($data['delivery']['service'] ?? ''));
        $file_label   = sanitize_text_field((string) ($data['delivery']['label'] ?? ''));
        $servers      = (array) ($schema['cdnServers'][$storage] ?? []);

        // The delivery provider, its domain and keys belong to one bucket, so a file from another bucket keeps this site's.
        $from_storage = (string) ($data['storage']['service'] ?? '');
        $same_bucket  = $from_storage === $storage && (string) ($data['storage']['bucket'] ?? '') === (string) Utils::get_bucket_name();
        $skip         = $this->secret_keys();
        if (!$same_bucket && $from_storage === $storage) {
            $bucket_keys = $this->bucket_keys();
            $skip        = array_merge($skip, $bucket_keys);
            $differs     = $file_cdn !== '' && $file_cdn !== $delivery;
            foreach ($bucket_keys as $key) {
                $differs = $differs || (array_key_exists($key, $data['settings']) && $data['settings'][$key] !== ($current[$key] ?? null));
            }
            if ($differs) {
                $warnings[] = __('The file comes from another bucket, so this site keeps its own delivery provider, domain and CDN keys.', 'media-cloud-sync');
            }
        } elseif (!$same_bucket) {
            $skip = array_merge($skip, $this->bucket_keys());
        }

        // Only options this site knows, cast to the type it uses; secrets keep this site's values.
        $new = $current;
        foreach (array_diff_key($data['settings'], array_flip($skip)) as $key => $value) {
            $key = (string) $key;
            if (!array_key_exists($key, $current) && !array_key_exists($key, $defaults)) {
                continue;
            }
            $new[$key] = $this->cast($value, $current[$key] ?? $defaults[$key]);
        }

        // Delivery providers depend on the storage provider, so another storage keeps this site's delivery.
        $new_delivery = $delivery;
        if ($file_cdn !== '' && $file_cdn !== $delivery) {
            if ($same_bucket && isset($servers[$file_cdn])) {
                $new_delivery = $file_cdn;
            } elseif ($from_storage !== $storage) {
                /* translators: %s: delivery provider name */
                $warnings[] = sprintf(__('The file uses %s for delivery, which isn\'t available with this site\'s storage, so the current delivery is kept.', 'media-cloud-sync'), $this->delivery_label($from_storage, $file_cdn, $file_label));
            }
        }
        // Saved like the Choose Delivery screen: empty for named providers, the custom name for "Other".
        $new_label = $new_delivery === $delivery
            ? (string) ($credentials['cdn']['label'] ?? '')
            : (!empty($servers[$new_delivery]['mainLabel']) ? '' : ($file_label ?: (string) ($servers[$new_delivery]['label'] ?? $new_delivery)));
        if ($from_storage !== '' && $from_storage !== $storage) {
            $warnings[] = __('The file comes from another storage provider. Options this provider doesn\'t have are skipped.', 'media-cloud-sync');
        }

        $changes = $this->changes($current, $new, $schema, $storage, $delivery, $new_delivery, (string) ($credentials['cdn']['label'] ?? ''), $new_label);
        if (!$apply) {
            return ['success' => true, 'changes' => $changes, 'warnings' => $warnings];
        }
        if (empty($changes)) {
            return ['success' => true, 'message' => __('Nothing to import: the settings already match.', 'media-cloud-sync'), 'changes' => []];
        }

        // The same checks as saving Settings.
        if (Utils::delivery_needs_domain($new, $storage, $new_delivery)) {
            return ['success' => false, 'message' => Utils::delivery_domain_message($storage, $new_delivery)];
        }
        $validation = apply_filters('wpmcs_validate_settings_save', ['ok' => true, 'message' => ''], $new, 'settings');
        if (empty($validation['ok'])) {
            return ['success' => false, 'message' => !empty($validation['message']) ? $validation['message'] : __('Settings could not be saved.', 'media-cloud-sync')];
        }
        if ($new_delivery !== $delivery) {
            $credentials['cdn'] = ['service' => $new_delivery, 'label' => $new_label];
            Utils::update_option('credentials', $credentials, Schema::getConstant('GLOBAL_SETTINGS_KEY'));
        }
        Utils::update_option('settings', $new, Schema::getConstant('GLOBAL_SETTINGS_KEY'));
        do_action('wpmcs_after_settings_save', $new, 'settings');

        return [
            'success'  => true,
            /* translators: %d: number of changed settings */
            'message'  => sprintf(_n('%d setting imported.', '%d settings imported.', count($changes), 'media-cloud-sync'), count($changes)),
            'settings' => $new,
            'reload'   => $new_delivery !== $delivery,
        ];
    }

    // Kept out of exports and never overwritten by imports.
    private function secret_keys() {
        return (array) apply_filters('wpmcs_settings_transfer_secret_keys', ['private_cdn_key']);
    }

    // Tied to one bucket (its public domain, CDN distribution and key pair); imported only from the same bucket.
    private function bucket_keys() {
        return (array) apply_filters('wpmcs_settings_transfer_bucket_keys', ['enable_cdn', 'cdn_url', 'private_cdn_distribution', 'private_cdn_domain', 'private_cdn_key_id']);
    }

    private function delivery_label($storage, $cdn, $saved = '') {
        if ($saved !== '') {
            return $saved;
        }
        $servers = (array) (UISchema::instance()->get_all()['cdnServers'][$storage] ?? []);
        return (string) ($servers[$cdn]['label'] ?? $cdn);
    }

    private function cast($value, $like) {
        if (is_bool($like)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        if (is_int($like)) {
            return (int) $value;
        }
        if (is_array($like)) {
            return array_values(array_map(function ($item) {
                return sanitize_text_field((string) $item);
            }, array_filter((array) $value, 'is_scalar')));
        }
        return sanitize_text_field(is_scalar($value) ? (string) $value : '');
    }

    /** Changed options as label / from / to (and a warning), in the order the Settings page shows them. */
    private function changes($current, $new, $schema, $storage, $delivery, $new_delivery, $label = '', $new_label = '') {
        $labels = $warnings = [];
        $this->collect_labels((array) ($schema['services'][$storage]['settings'] ?? []), $labels, $warnings);
        foreach ([$delivery, $new_delivery] as $cdn) {
            $this->collect_labels((array) ($schema['cdnServers'][$storage][$cdn]['fields'] ?? []), $labels, $warnings);
        }
        $changes = [];
        if ($new_delivery !== $delivery) {
            $changes[] = [
                'label' => __('Delivery', 'media-cloud-sync'),
                'from'  => $delivery !== '' ? $this->delivery_label($storage, $delivery, $label) : __('None', 'media-cloud-sync'),
                'to'    => $this->delivery_label($storage, $new_delivery, $new_label),
            ];
        }
        foreach ($new as $key => $value) {
            if (array_key_exists($key, $current) && $current[$key] === $value) {
                continue;
            }
            $change = [
                'label' => $labels[$key] ?? $key,
                'from'  => $this->display(array_key_exists($key, $current) ? $current[$key] : null),
                'to'    => $this->display($value),
            ];
            // Same warning the Settings page shows when the option is turned on.
            if (isset($warnings[$key]) && $value && empty($current[$key])) {
                $change['warning'] = $warnings[$key];
            }
            $changes[] = $change;
        }
        return $changes;
    }

    // Field name => label (and warning text) from the Settings and delivery schema; an unlabelled child field uses its parent's.
    private function collect_labels($node, &$labels, &$warnings, $parent = '') {
        if (!is_array($node)) {
            return;
        }
        if (!empty($node['name']) && is_string($node['name'])) {
            $label = !empty($node['label']) ? wp_strip_all_tags((string) $node['label']) : $parent;
            if ($label !== '' && !isset($labels[$node['name']])) {
                $labels[$node['name']] = $label;
            }
            if (!empty($node['warning']['enable']) && !empty($node['warning']['message']) && !isset($warnings[$node['name']])) {
                $warnings[$node['name']] = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(str_replace('</p>', '</p> ', (string) $node['warning']['message']))));
            }
            $parent = $label;
        }
        foreach ($node as $child) {
            $this->collect_labels($child, $labels, $warnings, $parent);
        }
    }

    private function display($value) {
        if ($value === null) {
            return __('Not set', 'media-cloud-sync');
        }
        if (is_bool($value)) {
            return $value ? __('On', 'media-cloud-sync') : __('Off', 'media-cloud-sync');
        }
        if (is_array($value)) {
            return $value ? implode(', ', $value) : __('None', 'media-cloud-sync');
        }
        return (string) $value === '' ? __('Empty', 'media-cloud-sync') : (string) $value;
    }

    // Settings change what jobs do, so imports wait for running jobs.
    private function running_job() {
        foreach (BGRunner::instance()->all_statuses() as $type => $status) {
            if (in_array($status['status'] ?? '', ['running', 'paused'], true)) {
                return (string) $type;
            }
        }
        return '';
    }

    public static function instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }
}
