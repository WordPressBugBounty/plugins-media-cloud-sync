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

		// resize_and_return_image is a WooCommerce function that resizes an image and returns its URL.
		// it's used in the `wpmcs_get_attached_file` filter.
		// If it is called from WooCommerce's `resize_and_return_image` function, it will return the original file path without modification.
		// Also restore file from cloud if the file is missing locally, to prevent woocommerce skipping regeneration of missing images.
		add_filter( 'wpmcs_get_attached_file', array( $this, 'get_attached_file' ), 10, 4 );
		// Note: There has still been an issue removing the images from local need a look in 
		// future to see if we can prevent that or handle it better, but this should at least prevent the issue of images 
		// not being regenerated due to missing local files when using cloud storage.
		/******* End Fix Size updation in WooCommerce Customizer */


	}


	/**
	 * Resize images for WooCommerce.
	 * Used to add a filter to return the file path instead of the URL for WooCommerce image resizing, 
	 * to prevent issues with image regeneration when using cloud storage.
	 * 
	 * @param bool $resize Whether to resize images or not.
	 * @return bool The modified value of $resize.
	 * @since  1.3.7
	 *
	 * @return bool The modified value of $resize.
	 */
	public function resize_images( $resize ) {
		add_filter( 'wpmcs_get_attached_file',  function ($url, $file, $attachment_id, $item) {
			return $file;
		}, 10, 4 );
		
		return $resize;
	}

	/**
	 * Get attached file URL.
	 * This method is hooked to the 'wpmcs_get_attached_file' filter and is used to modify the URL of an attached file.
	 * 
	 * @param string $url The original file URL.
	 * @param string $file The file path.
	 * @param int $attachment_id The attachment ID.
	 * @param array $item The item data associated with the attachment.
	 * @return string The modified file URL.
	 * @since  1.3.7
	 *
	 * @return string The modified file URL.
	 */
	public function get_attached_file( $url, $file, $attachment_id, $item ) {
		// Check if the function 'resize_and_return_image' exists and if the current call is from that function.
		if ( Utils::is_called_from( 'WC_Regenerate_Images', 'resize_and_return_image' ) ) {
			// If the call is from 'resize_and_return_image', return the original file path without modification.
			if ( ! file_exists( $file ) ) {
				// If the file does not exist locally, attempt to restore it from the cloud.
				Item::instance()->moveToServerByItem( $item, 'full', true );
			}

			return $file;
		}
		return $url;
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
