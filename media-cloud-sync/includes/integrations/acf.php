<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

use DirectoryIterator;
use Exception;

class Acf {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;

    protected $settings;
    protected $content_filter;
    protected $compatibility;

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    private function __construct() {
        $this->assets_url = WPMCS_ASSETS_URL;
        $this->version = WPMCS_VERSION;
        $this->token = WPMCS_TOKEN;
        $this->settings = Utils::get_settings();
        $this->content_filter = FilterContent::instance();
        $this->compatibility = Compatibility::instance();

		if (self::is_installed()) {
			// Register actions
			$this->registerActions();
		}
    }

    /**
     * Register integration access
     * @since 1.0.0
     * @access public
     *
     * @return void
     */
    public function registerActions() {
        /*
		 * Content Filtering
		 */
        
		add_filter( 'acf/load_value/type=text', array( $this->content_filter, 'filter_post' ) );
		add_filter( 'acf/load_value/type=textarea', array( $this->content_filter, 'filter_post' ) );
		add_filter( 'acf/load_value/type=wysiwyg', array( $this->content_filter, 'filter_post' ) );
		add_filter( 'acf/load_value/type=url', array( $this->content_filter, 'filter_post' ) );
		add_filter( 'acf/load_value/type=link', array( $this, 'filter_link_server' ) );
		add_filter( 'acf/update_value/type=text', array( $this->content_filter, 'filter_post_backward' ) );
		add_filter( 'acf/update_value/type=textarea', array( $this->content_filter, 'filter_post_backward' ) );
		add_filter( 'acf/update_value/type=wysiwyg', array( $this->content_filter, 'filter_post_backward' ) );
		add_filter( 'acf/update_value/type=url', array( $this->content_filter, 'filter_post_backward' ) );
		add_filter( 'acf/update_value/type=link', array( $this, 'filter_link_provider' ) );

		/*
		 * Image Crop Add-on
		 * https://en-gb.wordpress.org/plugins/acf-image-crop-add-on/
		 */
		if ( class_exists( 'acf_field_image_crop' ) ) {
			add_filter( 'wp_get_attachment_metadata', array( $this, 'download_image' ), 10, 2 );
			add_filter( 'sanitize_file_name', array( $this, 'remove_original_after_download' ) );
		}

		/*
		 * Rewrite URLs in field and field group config.
		 */
		add_filter( 'acf/load_fields', array( $this, 'acf_load_config' ) );
		add_filter( 'acf/load_field_group', array( $this, 'acf_load_config' ) );
    }

    /**
	 * Rewrites URLs from server to remote inside ACF field and field group config. If the
	 * rewriting process fails, it will return the original config.
	 *
	 * @handles acf/load_fields
	 * @handles acf/load_field_group
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	public function acf_load_config( array $config ): array {
		try {
			$filtered_config = Utils::maybe_unserialize( $this->content_filter->filter_post( Utils::maybe_serialize( $config ) ) );
		} catch ( Exception $e ) {
			return $config;
		}

		return is_array( $filtered_config ) ? $filtered_config : $config;
	}

    /**
	 * Remove the original image downloaded for the cropping after it has been processed
	 *
	 * @param string $filename
	 *
	 * @return mixed
	 */
	public function remove_original_after_download( $filename ) {
		$this->maybe_remove_original_after_download();

		return $filename;
	}

    /**
	 * Remove the original image from the server
	 *
	 * @return bool|WP_Error
	 */
	public function maybe_remove_original_after_download() {
		if ( false === $this->compatibility->maybe_process_on_action( 'acf_image_crop_perform_crop', true ) ) {
			return false;// Not ACF crop process
		}

		$original_attachment_id = Utils::filter_input( 'id', INPUT_POST, FILTER_VALIDATE_INT );

		if ( ! isset( $original_attachment_id ) ) {
			// Can't find the original attachment id
			return false; // Attachment ID not available
		}

		$item = Item::instance()->get( $original_attachment_id );

		if ( ! $item ) {
			return false; //Attachment not offloaded
		}
        
		if ( ! Utils::get_meta($original_attachment_id, 'acf_cropped_to_remove', false) ) {
			// Original attachment should exist serverly, no need to delete
			return false; //Attachment not to be removed from server
		}

		// Remove the original file from the server
		Item::instance()->may_be_delete_server_files_by_id($original_attachment_id, true);

		// Remove marker
        Utils::delete_meta($original_attachment_id, 'acf_cropped_to_remove');

		return true;
	}
    


    /**
	 * Copy back the S3 image for cropping
	 *
	 * @param array $data
	 * @param int   $post_id
	 *
	 * @return array
	 */
	public function download_image( $data, $post_id ) {
		$this->maybe_download_image( $post_id );

		return $data;
	}


    /**
	 * Copy back the S3 image
	 *
	 * @param int $post_id
	 *
	 * @return bool|WP_Error
	 */
	public function maybe_download_image( $post_id ) {
		if ( false === $this->compatibility->maybe_process_on_action( 'acf_image_crop_perform_crop', true ) ) {
			return false; // Skip not a proper action
		}

		$file = get_attached_file( $post_id, true );

		if ( file_exists( $file ) ) {
			return false; // skip file already exist
		}

		$item = Item::instance()->get( $post_id );

		if ( ! $item ) {
			return false; // Failed not offloaded
		}

		$callers = debug_backtrace(); // phpcs:ignore
		foreach ( $callers as $caller ) {
			if ( isset( $caller['function'] ) && 'image_downsize' === $caller['function'] ) {
				// Don't copy when downsizing the image, which would result in bringing back
				// the newly cropped image from S3.
				return false; // 'Skip when Copying back cropped file'
			}
		}

		// Copy back the original file for cropping
		$result = $this->compatibility->copy_provider_file_to_server( $post_id, $file );

		if ( false === $result ) {
			return false; //Copy back failed
		}

		// Mark the attachment so we know to remove it later after the crop
        Utils::update_meta($post_id, 'acf_cropped_to_remove', true);

		return true;
	}


    /**
	 * Filter a link field's URL from server to provider.
	 *
	 * @param array $link
	 *
	 * @return array
	 */
	public function filter_link_server( $link ) {
		if ( is_array( $link ) && ! empty( $link['url'] ) ) {
			$url = $this->content_filter->filter_post( $link['url'] );

			if ( ! empty( $url ) ) {
				$link['url'] = $url;
			}
		}

		return $link;
	}


    /**
	 * Filter a link field's URL from provider to server.
	 *
	 * @param array $link
	 *
	 * @return array
	 */
	public function filter_link_provider( $link ) {
		if ( is_array( $link ) && ! empty( $link['url'] ) ) {
			$url = $this->content_filter->filter_post_backward( $link['url'] );

			if ( ! empty( $url ) ) {
				$link['url'] = $url;
			}
		}

		return $link;
	}


    /**
     * Is installed?
     *
     * @return bool
     */
    public static function is_installed(): bool {
        if ( class_exists( 'acf', false ) ) {
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
