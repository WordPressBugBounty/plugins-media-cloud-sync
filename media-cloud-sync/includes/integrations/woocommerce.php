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
		// Note: There has still been an issue removing the images from local need a look in 
		// future to see if we can prevent that or handle it better, but this should at least prevent the issue of images 
		// not being regenerated due to missing local files when using cloud storage.
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
		}

		return file_exists( $file ) ? $file : $url;
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
