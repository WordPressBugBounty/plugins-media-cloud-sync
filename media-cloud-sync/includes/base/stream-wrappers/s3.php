<?php
namespace Dudlewebs\WPMCS;

use Dudlewebs\WPMCS\s3\Aws\S3\StreamWrapper;

defined('ABSPATH') || exit;

/**
 * Read-only wrapper for the four S3-family providers (S3, DigitalOcean
 * Spaces, Cloudflare R2, S3-compatible) — they all construct the identical
 * vendored Aws\S3\S3Client, so one subclass covers all four.
 *
 * @since 1.4.3
 */
class ReadOnlyS3StreamWrapper extends StreamWrapper {
    use ReadOnlyStreamWrapperTrait;

    /** The one method that isn't shared — only the S3 vendor class implements it. */
    public function stream_truncate($new_size) {
        return false;
    }
}
