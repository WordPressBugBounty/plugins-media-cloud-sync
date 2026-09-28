<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Removes write/enumeration capability from a vendor stream wrapper class —
 * `use`d by ReadOnlyS3StreamWrapper and ReadOnlyGcsStreamWrapper, each still
 * extending its own real vendor parent for read support.
 *
 * @since 1.4.3
 */
trait ReadOnlyStreamWrapperTrait {
    public function stream_open($path, $mode, $options, &$opened_path) {
        if (!in_array($mode, ['r', 'rb'], true)) {
            return false;
        }
        // parent:: resolves to whichever vendor class the using class actually extends.
        return parent::stream_open($path, $mode, $options, $opened_path);
    }

    public function unlink($path) {
        return false;
    }

    public function rename($path_from, $path_to) {
        return false;
    }

    public function mkdir($path, $mode, $options) {
        return false;
    }

    public function rmdir($path, $options) {
        return false;
    }

    public function stream_metadata($path, $option, $value) {
        return false;
    }

    /** dir_readdir()/dir_closedir()/dir_rewinddir() need no override — PHP never reaches them once opendir() itself fails. */
    public function dir_opendir($path, $options) {
        return false;
    }

    /** Defense in depth — stream_open()'s mode check should already make this unreachable. */
    public function stream_write($data) {
        return 0;
    }
}
