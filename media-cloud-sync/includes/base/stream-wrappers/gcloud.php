<?php
namespace Dudlewebs\WPMCS;

use Dudlewebs\WPMCS\GCP\Google\Cloud\Storage\StreamWrapper;

defined('ABSPATH') || exit;

/**
 * Read-only wrapper for Google Cloud Storage.
 *
 * @since 1.4.3
 */
class ReadOnlyGcsStreamWrapper extends StreamWrapper {
    use ReadOnlyStreamWrapperTrait;
}
