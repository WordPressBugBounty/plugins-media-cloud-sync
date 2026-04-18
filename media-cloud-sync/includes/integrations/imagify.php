<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Imagify Compatibility.
 *
 * @since  1.3.6
 */
if ( ! class_exists( 'Imagify' ) ) {
    return;
}

class Imagify {
	/**
	 * The singleton instance.
	 *
	 * @since  1.6.6
	 * @var    Imagify|null
	 */
	protected static $instance = null;

	/**
	 * The class constructor.
	 *
	 * @since  1.6.6
	 */
	protected function __construct() {
        $this->init();
	}

	/**
	 * Launch the hooks.
	 *
	 * @since  1.3.6
	 */
	public function init() {
		/**
		 * WebP images to display with a <picture> tag.
		 */
		add_filter( 'imagify_webp_picture_process_image', [ $this, 'picture_tag_webp_image' ] );

		/**
		 * Register CDN.
		 */
		add_filter( 'imagify_cdn', [ $this, 'register_cdn' ], 8, 3 );

		/**
		 * Optimization process.
		 */

		// Return Local URL even if the file is on the cloud
		add_action( 'imagify_before_optimize', [ $this, 'prepare_local_url' ], 8, 4 );
		
		// Copy file from CDN to server before optimization if not exist on server
		add_filter( 'imagify_before_optimize_size', [ $this, 'maybe_copy_file_from_cdn_before_optimization' ], 8, 6 );

		// Add webp files to the CDN after optimization if webp is exist and remove them from server if enabled
		add_filter( 'wpmcs_pre_update_item_additional_files_to_remove_from_server', [ $this, 'send_additinal_files_to_cdn' ], 8, 5 );

		// Send media to CDN after optimization
		add_action( 'imagify_after_optimize', [ $this, 'maybe_send_media_to_cdn_after_optimization' ], 8, 2 );

		// Revert Local URL file after optimization
		add_action( 'imagify_after_optimize', [ $this, 'revert_local_url' ], 8, 2 );


		/**
		 * Restoration process.
		 */
		add_action( 'imagify_after_restore_media', [ $this, 'maybe_send_media_to_cdn_after_restore' ], 8, 4 );

		/**
		 * WebP support.
		 */
		add_filter( 'wpmcs_get_item', [ $this, 'add_webp_images_to_attachment' ], 8, 3 );
		add_filter( 'mime_types', [ $this, 'add_webp_support' ] );
	}

	/**
	 * This function is called during the optimization process and is responsible for preparing the local URL.
	 *
	 * @param array           $items The array of items.
	 * @param \WP_Error|bool $wp_error The WP_Error object if an error occurs, otherwise false.
	 * @param string           $process The current process.
	 * @param array           $item The item being processed.
	 *
	 * @return array The array of items.
	 */
	public function prepare_local_url( $items, $wp_error, $process, $item ) {
		add_filter('wpmcs_get_attached_file', [ $this, 'return_local_url'], 10, 4);
		return $items;
	}

	/**
	 * Return the local file path for optimization.
	 *
	 * @param string           $url The URL of the file.
	 * @param string           $file The local file path.
	 * @param int              $attachment_id The attachment ID.
	 * @param array            $item The item being processed.
	 *
	 * @return string The local file path.
	 */
	public function return_local_url( $url, $file, $attachment_id, $item ) {
		return $file;
	}


	/**
	 * Revert the local URL back to the provider URL after optimization.
	 *
	 * This function is called after the optimization process and is responsible for reverting the local URL back to the provider URL.
	 *
	 * @param string           $process The current process.
	 * @param array            $item The item being processed.
	 *
	 * @return array The array of items.
	 */
	public function revert_local_url( $process, $item ) {
		remove_filter( 'wpmcs_get_attached_file', [ $this, 'return_local_url'], 10, 4 );
	}


	/**
	 * Maybe copy the file from CDN before optimization.
	 *
	 * If the file is on the CDN but not on the server, this function will copy the file from the CDN to the server.
	 *
	 * @param array           $response The response array.
	 * @param string           $process The current process.
	 * @param string           $file The file to copy.
	 * @param string           $thumb_size The thumbnail size.
	 * @param int              $optimization_level The optimization level.
	 * @param bool             $webp Whether to use WebP images.
	 *
	 * @return array The response array.
	 */
	public function maybe_copy_file_from_cdn_before_optimization( $response, $process, $file, $thumb_size, $optimization_level, $webp ) {
		if ( is_wp_error( $response ) || 'wp' !== $process->get_media()->get_context() ) {
			return $response;
		}

        $attachment_id = $process->get_media()->get_id();

        if ( ! Item::instance()->is_available_from_provider( $attachment_id ) ) {
            return $response;
        }

        Item::instance()->moveToServerBySourcePath(
            $attachment_id,
            $file->get_path()
        );

		return $response;
	}



	/**
	 * After performing a media optimization:
	 * - Save some data,
	 * - Upload the files to the CDN,
	 * - Maybe delete them from the server.
	 *
	 * @since  1.9
	 * @access public
	 *
	 * @param ProcessInterface $process The optimization process.
	 * @param array            $item    The item being processed.
	 */
	public function maybe_send_media_to_cdn_after_optimization( $process, $item ) {
		if ( 'wp' !== $process->get_media()->get_context() ) {
			return;
		}

		$attachment_id = $process->get_media()->get_id();

        if ( ! Item::instance()->is_available_from_provider( $attachment_id ) ) {
            return;
        }

		$this->send_to_cdn( $attachment_id );
	}


	/**
	 * Send additinal files to CDN
	 *
	 * @since  1.9
	 * @access public
	 *
	 * @param array  $files_to_remove
	 * @param int    $source_id
	 * @param array  $new_item
	 * @param array  $old_item
	 * @param string $source_type
	 */
	public function send_additinal_files_to_cdn( $files_to_remove, $source_id, $new_item, $old_item = [], $source_type = 'media_library' ) {
		$upload_dir = wp_get_upload_dir();
		$extras = isset( $new_item['extra'] ) ? Utils::maybe_unserialize( $new_item['extra'] ) : [];

		if ( empty( $extras['sizes'] ) || ! is_array( $extras['sizes'] ) ) {
			return;
		}

		$prefix = isset($extras['prefix']) ? $extras['prefix'] : '';
		$files_to_upload = [];

		// Add all sizes.
		if( isset( $extras['sizes'] ) && !empty( $extras['sizes'] ) && is_array( $extras['sizes'] ) ) {
			foreach ( $extras['sizes'] as $size_name => $size ) {
				if( !isset( $size['source_path'] ) || empty( $size['source_path'] ) ) {
					continue;
				}

				$webp_size_source_path = $this->path_to_webp( $size['source_path'] );
				$webp_size_path = trailingslashit( $upload_dir['basedir'] ) . ltrim( $webp_size_source_path, '/' );
				if ( file_exists( $webp_size_path ) ) {
					$files_to_upload[$webp_size_source_path] = $webp_size_path;
				}
			}
		}

		if ( ! empty( $new_item['original_source_path'] ) ) {
			if ( isset( $new_item['original_source_path'] ) && !empty( $new_item['original_source_path'] ) ) {
				$webp_original_source_path = $this->path_to_webp( $new_item['original_source_path'] );
				$webp_original_path = trailingslashit( $upload_dir['basedir'] ) . ltrim( $webp_original_source_path, '/' );
				if ( file_exists( $webp_original_path ) ) {
					$files_to_upload[$webp_original_source_path] = $webp_original_path;
				}
			}
		}

		if ( ! empty( $new_item['source_path'] ) ) {
			if ( isset( $new_item['source_path'] ) && !empty( $new_item['source_path'] ) ) {
				$webp_full_source_path = $this->path_to_webp( $new_item['source_path'] );
				$webp_full_path = trailingslashit( $upload_dir['basedir'] ) . ltrim( $webp_full_source_path, '/' );
				if ( file_exists( $webp_full_path ) ) {
					$files_to_upload[$webp_full_source_path] = $webp_full_path;
				}
			}
		}

		if ( $files_to_upload ) {
			foreach ( $files_to_upload as $relative_source_path => $path ) {	
				$uploaded = Service::instance()->uploadSingle( $path, $relative_source_path, $prefix );
				if ( $uploaded['success'] ) {
					$files_to_remove[] = $path;
				}
			}
		}

		return $files_to_remove;
	}



	/**
	 * Add the WebP files to the list of files that the CDN must handle.
	 * @since  1.3.6
	 */
	public function add_webp_images_to_attachment( $item, $attachment_id, $source_type = 'media_library' ) {
		if ( ! $item ) {
			return $item;
		}

		$process = imagify_get_optimization_process( $attachment_id, 'wp' );

		if ( ! $process->is_valid() ) {
			return $item;
		}

		// Make sure it's an image.
		if ( ! $this->is_attachment_image( $attachment_id ) ) {
			return $item;
		}

		// Use the optimization data (the files may not be on the server).
		$data = $process->get_data()->get_optimization_data();

		if ( empty( $data['sizes'] ) ) {
			return $item;
		}

        $extras = isset( $item['extra'] ) ? Utils::maybe_unserialize( $item['extra'] ) : [];

        $sizes = isset( $extras['sizes'] ) && ! empty( $extras['sizes'] ) ? $extras['sizes'] : [];

		$new_sizes = [];

		foreach ( $sizes as $size_name => $size ) {
			if ( $process->is_size_next_gen( $size_name ) ) {
				continue;
			}

			$webp_size_name = $size_name . $process::WEBP_SUFFIX;

			if ( empty( $data['sizes'][ $webp_size_name ]['success'] ) ) {
				// This size has no WebP version.
				continue;
			}

			if ( ! $this->is_webp($size['source_path']) ) {
				$new_sizes[ $webp_size_name ] = [
					'source_path' => $this->path_to_webp( $size['source_path'] ),
					'url'         => $this->path_to_webp( $size['url'] ),
					'key'         => $this->path_to_webp( $size['key'] ),
					'width'       => $size['width'],
					'height'      => $size['height'],
				];
			}
		}

        if( $item['source_path'] ) {
            if( !$process->is_size_next_gen( 'full' ) ) {
				$webp_size_name = 'full' . $process::WEBP_SUFFIX;

				if ( ! empty( $data['sizes'][ $webp_size_name ]['success'] ) ) {
					if ( ! $this->is_webp($item['source_path']) ) {
						$new_sizes[ $webp_size_name ] = [
							'source_path' => $this->path_to_webp( $item['source_path'] ),
							'url'         => $this->path_to_webp( $item['url'] ),
							'key'         => $this->path_to_webp( $item['key'] ),
							'width'       => $extras['width'],
							'height'      => $extras['height'],
						];
					}
				}
            }
        }
		
		if ( $new_sizes ) {
			$extras['sizes'] = array_merge( $extras['sizes'], $new_sizes );
			$item['extra']   = Utils::maybe_serialize( $extras );
		}


		return $item;
	}


	/**
	 * After restoring a media:
	 * - Save some data,
	 * - Upload the files to the CDN,
	 * - Maybe delete WebP files from the CDN.
	 */
	public function maybe_send_media_to_cdn_after_restore( $process, $response, $files, $data ) {
		if ( 'wp' !== $process->get_media()->get_context() ) {
			return;
		}
		$attachment_id = $process->get_media()->get_id();

		if ( ! Item::instance()->is_available_from_provider( $attachment_id ) ) {
            return;
        }

		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();

			if ( 'copy_failed' === $error_code ) {
				// No files have been restored.
				return;
			}

			// No thumbnails left?
		}

		$extras = Item::instance()->get_extras( $attachment_id );
		$sizes = isset( $extras['sizes'] ) && is_array( $extras['sizes'] ) ? $extras['sizes'] : [];

        // Upload the files to the CDN.
        $this->send_to_cdn( $attachment_id );

		// Remove WebP files from CDN.
		$webp_files = [];

		if ( $files ) {
			// Get the paths to the WebP files.
			foreach ( $files as $size_name => $file ) {
				$webp_size_name = $size_name . $process::WEBP_SUFFIX;

				if ( empty( $data['sizes'][ $webp_size_name ]['success'] ) ) {
					// This size has no WebP version.
					continue;
				}

				if ( 0 === strpos( $file['mime-type'], 'image/' ) ) {
					if ( ! $this->is_webp( $file['path'] ) ) {
						if($sizes && isset( $sizes[ $webp_size_name ] ) ) {
							$webp_files[] = $sizes[ $webp_size_name ]['key'];
						}
					}
				}
			}
		}

		if ( $webp_files ) {
			Item::instance()->delete_cloud_files_by_keys( $webp_files );
		}
	}


	/**
     * Send media to cloud after Imagify optimization or restore.
     *
     *
     * @param int  $attachment_id
     * @param bool $is_new_upload
     * @return bool|\WP_Error
     */
    public function send_to_cdn( $attachment_id ) {

        $attachment_id = (int) $attachment_id;

        if ( ! $attachment_id ) {
            return new \WP_Error(
                'invalid_attachment',
                __( 'Invalid attachment ID.', 'media-cloud-sync' )
            );
        }

		// Tell Media Cloud Sync to reupload all media files.
		// Media may be optimized and Imagify may have created new optimized files.
		add_filter( 'wpmcs_do_reupload_media', '__return_true', 10, 3 );
        do_action( 'wpmcs_do_update_attachment_metadata', false, $attachment_id );
		remove_filter( 'wpmcs_do_reupload_media', '__return_true', 10 );
        
        return true;
    }



	/**
	 * WebP images to display with a <picture> tag.
	 *
	 * @since  1.3.6
	 *
	 * @param  array $data An array of data for this image.
	 * @return array
	 */
	public function picture_tag_webp_image( $data ) {
		global $wpdb;

		if ( ! empty( $data['src']['webp_path'] ) ) {
			// The file is local.
			return $data;
		}

		if(!$this->is_provider_url( $data['src']['url'] )) {
			// Not on Provider.
			return $data;
		}

		$full_url = Utils::remove_size_from_filename( $data['src']['url'] );
		$path = Utils::get_attachment_source_path( $full_url, 'key' );

		$item = Item::instance()->get_items_by_paths( $path, true, true, 'key' );

		if ( ! $item ) {
			// Not in the database.
			return $data;
		}

		$post_id = (int)$item['source_id'];
		$imagify_data = get_post_meta( $post_id, '_imagify_data', true );

		if ( ! $imagify_data ) {
			// Not optimized.
			return $data;
		}

		if( !function_exists( 'imagify_get_optimization_process_class_name' ) ) {
			// Imagify is not fully loaded.
			return $data;
		}

		$webp_size_suffix = constant( imagify_get_optimization_process_class_name( 'wp' ) . '::WEBP_SUFFIX' );
		$webp_size_name   = 'full' . $webp_size_suffix;

		if ( ! empty( $imagify_data['sizes'][ $webp_size_name ]['success'] ) ) {
			// We have a WebP image.
			$data['src']['webp_exists'] = true;
		}

		if ( empty( $data['srcset'] ) ) {
			return $data;
		}

		$meta_data = get_post_meta( $post_id, '_wp_attachment_metadata', true );

		if ( empty( $meta_data['sizes'] ) ) {
			return $data;
		}

		// Ease the search for corresponding file name.
		$size_files = [];

		foreach ( $meta_data['sizes'] as $size_name => $size_data ) {
			$size_files[ $size_data['file'] ] = $size_name;
		}

		// Look for a corresponding size name.
		foreach ( $data['srcset'] as $i => $srcset_data ) {
			if ( empty( $srcset_data['webp_url'] ) ) {
				// Not a supported image format.
				continue;
			}
			if ( ! empty( $srcset_data['webp_path'] ) ) {
				// The file is local.
				continue;
			}

			$match = $this->is_provider_url( $srcset_data['url'] );

			if ( ! $match ) {
				continue;
			}

			// Try with no subdirs.
			$filename = basename( $srcset_data['url'] );

			if ( isset( $size_files[ $filename ] ) ) {
				$size_name = $size_files[ $filename ];
			} else {
				continue;
			}

			$webp_size_name = $size_name . $webp_size_suffix;

			if ( ! empty( $imagify_data['sizes'][ $webp_size_name ]['success'] ) ) {
				// We have a WebP image.
				$data['srcset'][ $i ]['webp_exists'] = true;
			}
		}

		return $data;
	}

	

	/**
	 * Add WebP format to the list of allowed mime types.
	 *
	 * @since  1.9
	 * @access public
	 * @see    get_allowed_mime_types()
	 *
	 * @param  array $mime_types A list of mime types.
	 * @return array
	 */
	public function add_webp_support( $mime_types ) {
		$mime_types['webp'] = 'image/webp';
		return $mime_types;
	}


	/**
	 * The CDN to use for this media.
	 *
     * @since  1.3.6
     * @access public
	 */
	public function register_cdn( $cdn, $media_id, $context ) {
		if ( 'wp' !== $context->get_name() ) {
			return $cdn;
		}
		if ( Item::instance()->is_available_from_provider( $media_id ) ) {
            // Any truthy value tells Imagify a CDN exists
            return true;
        }

		return $cdn;
	}


	/**
	 * Tell if the file is a WebP image.
	 * Rejects "path/to/.webp" files.
	 *
	 * @return bool
	 */
	public function is_webp($path) {
		return preg_match( '@(?!^|/|\\\)\.webp$@i', $path );
	}

	/**
	 * Get the path to a WebP version of an image.
	 *
	 * @param string $path The path to the original image.
	 * @return string The path to the WebP version of the image.
	 */
    public function path_to_webp( $path ) {
        return $path . '.webp';
    }

	/**
	 * Checks if the given attachment ID is an image.
	 *
	 * @param int $attachment_id The attachment ID to check.
	 * @return bool True if the attachment is an image, false otherwise.
	 */
	private function is_attachment_image($attachment_id) {
		$post = get_post($attachment_id);
		if (!$post) return false;

		// Check the MIME type prefix directly from the database
		return str_starts_with($post->post_mime_type, 'image/');
	}


	/**
	 * Tell if an URL is a Provider one.
	 */
	public function is_provider_url( $url ) {
        /**
         * Allow short-circuiting via filter.
         *
         * @param null|array|bool $is
         * @param string         $url
         */
        $is = apply_filters( 'wpmcs_imagify_is_provider_url', null, $url );

        if ( null !== $is ) {
            return $is;
        }

		return CDN::is_cdn_url( $url ) || Service::instance()->is_provider_url( $url );
    }


    /**
     * Is installed?
     *
     * @return bool
     */
    public static function is_installed(): bool {
        if ( defined( 'IMAGIFY_VERSION' ) ) {
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
