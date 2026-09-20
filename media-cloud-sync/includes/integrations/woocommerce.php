<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * WooCommerce Compatibility.
 *
 * @since  1.3.7
 */
if ( ! class_exists( 'WooCommerce' ) ) {
    return;
}

class WooCommerce {
	/**
	 * The singleton instance.
	 *
	 * @since  1.3.7
	 * @var    WooCommerce|null
	 */
	protected static $instance = null;

	/**
	 * The image size WooCommerce is currently regenerating, captured via
	 * `intermediate_image_sizes` so the reupload can be scoped to just that size.
	 *
	 * @since 1.4.0
	 * @var string|null
	 */
	protected $regenerating_size = null;

	/**
	 * Whether the `intermediate_image_sizes` capture hook has been armed.
	 *
	 * @since 1.4.0
	 * @var bool
	 */
	protected $size_capture_hooked = false;

	/**
	 * The class constructor.
	 *
	 * @since  1.3.7
	 */
	protected function __construct() {
        $this->init();
	}

	/**
	 * Launch the hooks.
	 *
	 * @since  1.3.7
	 */
	public function init() {
		/******** FIX Size updation in WooCommerce Customizer *****/
		// Return File path for WooCommerce image resizing to prevent url being used and causing issues with image regeneration.
        add_filter( 'woocommerce_resize_images', [ $this, 'resize_images' ] );

		// Restore the file from cloud (if missing) and return the local path during
		// WooCommerce's on-the-fly image regeneration.
		add_filter( 'wpmcs_get_attached_file', array( $this, 'get_attached_file' ), 10, 4 );

		// Force a reupload of just the size WooCommerce is regenerating — same name/
		// dimensions as an existing size, but different crop, so the normal
		// "unchanged, skip reupload" check can't tell it apart from a real no-op.
		add_filter( 'wpmcs_do_reupload_media', array( $this, 'force_reupload_during_regeneration' ), 10, 3 );
		/******* End Fix Size updation in WooCommerce Customizer */
	}


	/**
	 * Resize images for WooCommerce.
	 * Used to add a filter to return the file path instead of the URL for WooCommerce image resizing,
	 * to prevent issues with image regeneration when using cloud storage.
	 *
	 * WooCommerce fires 'woocommerce_resize_images' unconditionally from
	 * maybe_resize_image(), which itself runs on every wp_get_attachment_image_src()
	 * call for any size — not just when an actual WooCommerce resize is about to
	 * happen. resize_and_return_image() (the method that genuinely needs the local
	 * path) hasn't been called yet at this point, so is_called_from() would always
	 * be false if checked here. The added filter must instead check at the time it's
	 * actually invoked — otherwise it permanently returns the local path for every
	 * wpmcs_get_attached_file call for the rest of the request, once any image has
	 * rendered anywhere (confirmed live: this broke the unrelated BuddyBoss
	 * regenerate-thumbnails restore window entirely).
	 *
	 * @param bool $resize Whether to resize images or not.
	 * @return bool The modified value of $resize.
	 * @since  1.3.7
	 */
	public function resize_images( $resize ) {
		add_filter( 'wpmcs_get_attached_file',  function ($url, $file, $attachment_id, $item) {
			if ( ! Utils::is_called_from( 'WC_Regenerate_Images', 'resize_and_return_image' ) ) {
				return $url;
			}
			return $file;
		}, 10, 4 );

		return $resize;
	}

	/**
	 * Restore the file from cloud (all registered sizes, not just this one — the
	 * source may be needed at other sizes too) and return the local path during
	 * WooCommerce's on-the-fly image regeneration.
	 *
	 * @param string $url The original file URL.
	 * @param string $file The file path.
	 * @param int $attachment_id The attachment ID.
	 * @param array $item The item data associated with the attachment.
	 * @return string The modified file URL.
	 * @since  1.3.7
	 */
	public function get_attached_file( $url, $file, $attachment_id, $item ) {
		if ( ! Utils::is_called_from( 'WC_Regenerate_Images', 'resize_and_return_image' ) ) {
			return $url;
		}

		// Always attempted, not gated on $file specifically being missing —
		// move_to_server_by_key_and_path() already no-ops per-file on file_exists(),
		// and gating here would leave OTHER missing sizes unrestored, which then get
		// silently dropped (not just skipped) if a reupload is later forced without
		// a local file present.
		$results = Item::instance()->moveToServerByItem( $item, 'full', true );

		if ( is_array( $results ) && ! empty( $results ) ) {
			Item::instance()->track_restored_for_cleanup( array_values( $results ) );
		}

		$this->capture_regenerating_size();

		return $file;
	}

	/**
	 * Arms a one-time `intermediate_image_sizes` capture so the exact size
	 * WooCommerce is regenerating is known when `wpmcs_do_reupload_media` fires.
	 * Priority 20 — after WooCommerce's own `adjust_intermediate_image_sizes`
	 * (default priority 10) has already narrowed the array to that one size.
	 *
	 * @return void
	 * @since 1.4.0
	 */
	protected function capture_regenerating_size() {
		if ( $this->size_capture_hooked ) {
			return;
		}
		$this->size_capture_hooked = true;

		add_filter( 'intermediate_image_sizes', function ( $sizes ) {
			if ( is_array( $sizes ) && 1 === count( $sizes ) ) {
				$this->regenerating_size = reset( $sizes );
			}
			return $sizes;
		}, 20 );
	}

	/**
	 * Force a reupload of just the size WooCommerce is regenerating — an existing
	 * registered size with a different crop, which the normal same-name/dimensions
	 * check can't otherwise tell apart from an unchanged file.
	 *
	 * @param bool|array $do_reupload
	 * @param int $attachment_id
	 * @param string $source_type
	 * @return bool|array
	 * @since 1.4.0
	 */
	public function force_reupload_during_regeneration( $do_reupload, $attachment_id, $source_type ) {
		if ( true === $do_reupload || empty( $this->regenerating_size ) ) {
			return $do_reupload;
		}

		$size = $this->regenerating_size;
		$this->regenerating_size = null;

		return array( $size );
	}

    /**
     * Is installed?
     *
     * @return bool
     */
    public static function is_installed(): bool {
        // Check if WooCommerce plugin defines its main constant.
		if ( defined( 'WC_ABSPATH' ) ) {
			return true;
		}

		// Alternative: check for WooCommerce version constant.
		if ( defined( 'WC_VERSION' ) ) {
			return true;
		}

		return false;
    }

    /**
     * Singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
			self::$instance = new self();
        }

        return self::$instance;
    }
}
