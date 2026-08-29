<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * WooCommerce Compatibility.
 *
 * Loaded by the autoloader; activation is gated via `is_installed()` in Integration::init().
 *
 * @since  1.3.7
 */
class WooCommerce {
	/**
	 * The singleton instance.
	 *
	 * @since  1.3.7
	 * @var    WooCommerce|null
	 */
	protected static $instance = null;

	/**
	 * Whether WooCommerce image resize is active for this request.
	 *
	 * Set when `woocommerce_resize_images` runs so `get_attached_file()` returns
	 * a local path instead of a cloud URL without stacking filters.
	 *
	 * @since 1.3.12
	 * @var bool
	 */
	private static $return_local_path = false;

	/**
	 * Absolute paths restored from cloud for WooCommerce regeneration this request,
	 * re-removed on shutdown so "Remove from server" stays honored even if the
	 * normal sync pipeline bails early (e.g. waiting on subsizes) and never
	 * re-triggers removal itself.
	 *
	 * @since 1.4.0
	 * @var string[]
	 */
	private $restored_files = array();

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

		// Return the local file path (and restore from cloud when missing) during WooCommerce image regeneration.
		add_filter( 'wpmcs_get_attached_file', array( $this, 'get_attached_file' ), 10, 4 );

		// Force a reupload during regeneration — WooCommerce rewrites the local file in place at
		// the same dimensions/filename, which the normal "unchanged, skip reupload" check can't
		// tell apart from a real no-op, leaving stale content on the cloud otherwise.
		add_filter( 'wpmcs_do_reupload_media', array( $this, 'force_reupload_during_regeneration' ), 10, 1 );
		/******* End Fix Size updation in WooCommerce Customizer */
	}


	/**
	 * Flag the request so attached files are returned as local paths during WooCommerce resize.
	 *
	 * @param bool $resize Whether to resize images or not.
	 * @return bool The modified value of $resize.
	 * @since  1.3.7
	 */
	public function resize_images( $resize ) {
		if ( $resize ) {
			self::$return_local_path = true;
		}

		return $resize;
	}

	/**
	 * Return a local file path during WooCommerce image regeneration.
	 *
	 * @param string $url The cloud URL.
	 * @param string $file The local file path.
	 * @param int    $attachment_id The attachment ID.
	 * @param array  $item The item data associated with the attachment.
	 * @return string The local file path or original cloud URL.
	 * @since  1.3.7
	 */
	public function get_attached_file( $url, $file, $attachment_id, $item ) {
		if ( ! $this->should_return_local_path_for_woocommerce() ) {
			return $url;
		}

		if ( ! file_exists( $file ) && ! Utils::is_empty( $item ) ) {
			Item::instance()->moveToServerByItem( $item, 'full', true );

			// Only track this for later removal on admin-side requests (the System Status
			// tool, the background regen queue, and the single media-row action all run
			// over admin-ajax). WooCommerce also restores files here for its realtime,
			// on-the-fly resize on any storefront page that displays a mismatched-ratio
			// image — re-deleting the restore there would just trigger another cloud
			// download next time a different size needs it from the same full image.
			if ( is_admin() && file_exists( $file ) ) {
				$this->track_restored_file( $file );
			}
		}

		return file_exists( $file ) ? $file : $url;
	}

	/**
	 * Force the normal sync pipeline to treat this attachment as needing a
	 * reupload while WooCommerce is regenerating it, same as Imagify does
	 * for its own optimize/restore cycle.
	 *
	 * Restricted to admin-side requests (System Status tool, the background
	 * regen queue, and the single "Regenerate thumbnail" media-row action all
	 * run over admin-ajax) — WooCommerce also calls the same regeneration
	 * class from `wp_get_attachment_image_src` to resize a mismatched-ratio
	 * image on the fly on any page that displays it, storefront included, and
	 * forcing a synchronous cloud reupload mid-render for a site visitor
	 * there would be a real performance regression, not a fix.
	 *
	 * @param bool $do_reupload
	 * @return bool
	 * @since 1.4.0
	 */
	public function force_reupload_during_regeneration( $do_reupload ) {
		if ( $do_reupload || ! is_admin() ) {
			return $do_reupload;
		}

		return $this->should_return_local_path_for_woocommerce();
	}

	/**
	 * Remember a file restored for WooCommerce so it can be removed again on
	 * shutdown, and arm that cleanup the first time this request restores anything.
	 *
	 * @param string $file Absolute path of the restored file.
	 * @return void
	 * @since 1.4.0
	 */
	private function track_restored_file( $file ) {
		if ( in_array( $file, $this->restored_files, true ) ) {
			return;
		}

		if ( empty( $this->restored_files ) ) {
			add_action( 'shutdown', array( $this, 'remove_restored_files' ) );
		}

		$this->restored_files[] = $file;
	}

	/**
	 * Re-removes files restored for WooCommerce regeneration, honoring the
	 * "Remove from server" setting the same way the normal sync pipeline would.
	 *
	 * @return void
	 * @since 1.4.0
	 */
	public function remove_restored_files() {
		Item::instance()->may_be_delete_server_files_by_source_paths( $this->restored_files );
	}

	/**
	 * Whether the current request needs a local attached file for WooCommerce image handling.
	 *
	 * @return bool
	 * @since 1.3.12
	 */
	private function should_return_local_path_for_woocommerce(): bool {
		if ( self::$return_local_path ) {
			return true;
		}

		if ( Utils::is_called_from( 'WC_Regenerate_Images' ) ) {
			return true;
		}

		return Utils::is_called_from( 'WC_Regenerate_Images_Request' );
	}

    /**
     * Is installed?
     *
     * @return bool
     */
    public static function is_installed(): bool {
        return defined( 'WC_ABSPATH' ) || defined( 'WC_VERSION' ) || class_exists( 'WooCommerce', false );
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
