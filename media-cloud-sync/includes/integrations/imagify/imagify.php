<?php
namespace Dudlewebs\WPMCS;

defined( 'ABSPATH' ) || exit;

/**
 * Imagify Compatibility.
 *
 * Integration class is `Dudlewebs\WPMCS\Imagify` — not the Imagify plugin's global `\Imagify` class.
 * Loaded by the autoloader; activation is gated via `is_installed()` in Integration::init().
 *
 * @since  1.3.6
 */
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
		add_filter( 'imagify_before_optimize_size', [ $this, 'maybe_copy_file_from_cdn_before_optimization' ], 8, 6 );

		// Add webp files to the CDN after optimization if webp is exist and remove them from server if enabled
		add_filter( 'wpmcs_pre_update_item_additional_files_to_remove_from_server', [ $this, 'send_additinal_files_to_cdn' ], 8, 5 );

		// Send media to CDN after optimization
		add_action( 'imagify_after_optimize', [ $this, 'maybe_send_media_to_cdn_after_optimization' ], 8, 2 );


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
	 * Copy file from cloud storage before optimization when it is missing locally.
	 *
	 * Mirrors Imagify's official AS3CF integration hook handler.
	 *
	 * @param null|\WP_Error   $response The response.
	 * @param object           $process The optimization process instance.
	 * @param object           $file The Imagify file instance.
	 * @param string           $thumb_size The media size.
	 * @param int              $optimization_level The optimization level.
	 * @param bool             $webp Whether the image will be converted to WebP.
	 *
	 * @return null|\WP_Error
	 */
	public function maybe_copy_file_from_cdn_before_optimization( $response, $process, $file, $thumb_size, $optimization_level, $webp ) {
		if ( is_wp_error( $response ) || 'wp' !== $process->get_media()->get_context() ) {
			return $response;
		}

		if ( \Imagify_Filesystem::get_instance()->exists( $file->get_path() ) ) {
			return $response;
		}

		return $this->copy_files_from_cdn( $process->get_media()->get_id(), [ $file->get_path() ] );
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

		$this->send_attachment_to_cdn(
			$process->get_media()->get_id(),
			! empty( $item['data']['is_new_upload'] )
		);
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
			return $files_to_remove;
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
			$extras['sizes'] = array_merge( $sizes, $new_sizes );
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

		if ( ! $this->media_is_on_provider( $attachment_id ) ) {
			return;
		}

		if ( is_wp_error( $response ) && 'copy_failed' === $response->get_error_code() ) {
			return;
		}

		$this->send_attachment_to_cdn( $attachment_id );

		$extras = Item::instance()->get_extras( $attachment_id );
		$sizes = isset( $extras['sizes'] ) && is_array( $extras['sizes'] ) ? $extras['sizes'] : [];
		$webp_files = [];

		if ( $files ) {
			foreach ( $files as $size_name => $file ) {
				$webp_size_name = $size_name . $process::WEBP_SUFFIX;

				if ( empty( $data['sizes'][ $webp_size_name ]['success'] ) ) {
					continue;
				}

				if ( 0 === strpos( $file['mime-type'], 'image/' ) && ! $this->is_webp( $file['path'] ) ) {
					if ( $sizes && isset( $sizes[ $webp_size_name ] ) ) {
						$webp_files[] = $sizes[ $webp_size_name ]['key'];
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
		remove_filter( 'wpmcs_do_reupload_media', '__return_true', 10, 3 );

		return true;
	}

	/**
	 * Tell if an attachment is stored in cloud storage.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 * @since 1.3.12
	 */
	public function media_is_on_provider( $attachment_id ) {
		return Item::instance()->is_available_from_provider( (int) $attachment_id );
	}

	/**
	 * Copy missing files from cloud storage to the server.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $file_paths Local file paths.
	 * @return bool|\WP_Error
	 * @since 1.3.12
	 */
	public function copy_files_from_cdn( $attachment_id, $file_paths ) {
		$attachment_id = (int) $attachment_id;

		if ( ! Utils::is_service_enabled() ) {
			return new \WP_Error(
				'not_ready',
				__( 'Cloud storage is not set up.', 'media-cloud-sync' )
			);
		}

		if ( ! $this->media_is_on_provider( $attachment_id ) ) {
			return new \WP_Error(
				'not_on_cdn',
				__( 'This media could not be found in cloud storage.', 'media-cloud-sync' )
			);
		}

		$filesystem = \Imagify_Filesystem::get_instance();
		$errors     = [];

		foreach ( (array) $file_paths as $file_path ) {
			if ( $filesystem->exists( $file_path ) ) {
				continue;
			}

			Item::instance()->moveToServerBySourcePath( $attachment_id, $file_path );

			if ( ! $filesystem->exists( $file_path ) ) {
				$errors[] = $file_path;
			}
		}

		if ( ! $errors ) {
			return true;
		}

		$error_count = count( $errors );
		$error_paths = array_map( [ $filesystem, 'make_path_relative' ], $errors );
		$error_paths = wp_sprintf_l( '%l', $error_paths );

		return new \WP_Error(
			'not_retrieved',
			sprintf(
				/* translators: %s is a list of file paths. */
				_n(
					'The following file could not be retrieved from cloud storage: %s.',
					'The following files could not be retrieved from cloud storage: %s.',
					$error_count,
					'media-cloud-sync'
				),
				$error_paths
			),
			$errors
		);
	}

	/**
	 * Upload optimized files back to cloud storage when allowed.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $is_new_upload Whether this is a new upload.
	 * @return bool|\WP_Error
	 * @since 1.3.12
	 */
	public function send_attachment_to_cdn( $attachment_id, $is_new_upload = false ) {
		$attachment_id = (int) $attachment_id;

		if ( ! Utils::is_service_enabled() || ! $this->media_is_on_provider( $attachment_id ) ) {
			return false;
		}

		return $this->send_to_cdn( $attachment_id );
	}

	/**
	 * Remove files from cloud storage.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $file_paths Local file paths or file names.
	 * @return bool|\WP_Error
	 * @since 1.3.12
	 */
	public function remove_files_from_cdn( $attachment_id, $file_paths ) {
		$attachment_id = (int) $attachment_id;

		if ( ! Utils::is_service_enabled() ) {
			return new \WP_Error(
				'not_ready',
				__( 'Cloud storage is not set up.', 'media-cloud-sync' )
			);
		}

		if ( ! $this->media_is_on_provider( $attachment_id ) ) {
			return new \WP_Error(
				'not_on_cdn',
				__( 'This media could not be found in cloud storage.', 'media-cloud-sync' )
			);
		}

		$keys = [];

		foreach ( (array) $file_paths as $file_path ) {
			$key = $this->get_key_for_attachment_path( $attachment_id, $file_path );

			if ( $key ) {
				$keys[] = $key;
			}
		}

		if ( ! $keys ) {
			return true;
		}

		Item::instance()->delete_cloud_files_by_keys( array_unique( $keys ) );

		return true;
	}

	/**
	 * Get a provider URL for an attachment file.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_name File name or empty for full size.
	 * @return string
	 * @since 1.3.12
	 */
	public function get_provider_file_url( $attachment_id, $file_name = '' ) {
		$attachment_id = (int) $attachment_id;

		if ( ! $file_name ) {
			$url = Item::instance()->get_url( $attachment_id, 'full', 'media_library' );

			return $url ? $url : (string) wp_get_attachment_url( $attachment_id );
		}

		$filesystem = \Imagify_Filesystem::get_instance();
		$full_url   = $this->get_provider_file_url( $attachment_id );

		return $filesystem->dir_path( $full_url ) . $file_name;
	}

	/**
	 * Get a local filesystem path for an attachment file.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_name File name, empty for full size, or `original`.
	 * @return string
	 * @since 1.3.12
	 */
	public function get_local_file_path( $attachment_id, $file_name = '' ) {
		$attachment_id = (int) $attachment_id;
		$item          = Item::instance()->get( $attachment_id, 'media_library' );
		$upload_dir    = wp_upload_dir();
		$filesystem    = \Imagify_Filesystem::get_instance();

		if ( Utils::is_empty( $item ) ) {
			return (string) get_attached_file( $attachment_id, true );
		}

		if ( ! $file_name ) {
			return trailingslashit( $upload_dir['basedir'] ) . ltrim( $item['source_path'], '/' );
		}

		if ( 'original' === $file_name ) {
			if ( function_exists( 'wp_get_original_image_path' ) ) {
				$original_path = wp_get_original_image_path( $attachment_id );

				if ( $original_path ) {
					return $original_path;
				}
			}

			if ( ! empty( $item['original_source_path'] ) ) {
				return trailingslashit( $upload_dir['basedir'] ) . ltrim( $item['original_source_path'], '/' );
			}

			return trailingslashit( $upload_dir['basedir'] ) . ltrim( $item['source_path'], '/' );
		}

		$full_path = trailingslashit( $upload_dir['basedir'] ) . ltrim( $item['source_path'], '/' );

		return $filesystem->dir_path( $full_path ) . $file_name;
	}

	/**
	 * Resolve a cloud object key from a local path or file name.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path Local file path or file name.
	 * @return string
	 * @since 1.3.12
	 */
	protected function get_key_for_attachment_path( $attachment_id, $file_path ) {
		$attachment_id = (int) $attachment_id;
		$item          = Item::instance()->get( $attachment_id, 'media_library' );

		if ( Utils::is_empty( $item ) ) {
			return '';
		}

		$filesystem  = \Imagify_Filesystem::get_instance();
		$source_path = Utils::get_attachment_source_path( $file_path );

		if ( empty( $source_path ) ) {
			$full_path   = $this->get_local_file_path( $attachment_id );
			$source_path = Utils::get_attachment_source_path(
				$filesystem->dir_path( $full_path ) . wp_basename( $file_path )
			);
		}

		if ( empty( $source_path ) ) {
			return '';
		}

		if ( ! empty( $item['source_path'] ) && $item['source_path'] === $source_path ) {
			return $item['key'] ?? '';
		}

		if ( ! empty( $item['original_source_path'] ) && $item['original_source_path'] === $source_path ) {
			return $item['original_key'] ?? '';
		}

		$extras = Item::instance()->get_extras( $attachment_id, false, 'media_library' ) ?: [];

		if ( ! empty( $extras['sizes'] ) ) {
			foreach ( $extras['sizes'] as $size ) {
				if (
					! empty( $size['source_path'] ) &&
					$size['source_path'] === $source_path &&
					! empty( $size['key'] )
				) {
					return $size['key'];
				}
			}
		}

		return '';
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

		if ( $cdn instanceof \Imagify\CDN\PushCDNInterface ) {
			return $cdn;
		}

		if ( ! interface_exists( '\Imagify\CDN\PushCDNInterface' ) || ! class_exists( ImagifyPushCDN::class ) ) {
			return $cdn;
		}

		if ( Item::instance()->is_available_from_provider( $media_id ) ) {
			return new ImagifyPushCDN( (int) $media_id );
		}

		return $cdn;
	}


	/**
	 * Tell if the file is a WebP image.
	 * Rejects "path/to/.webp" files.
	 *
	 * @param string $path The path to the file.
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

		return Cdn::is_cdn_url( $url ) || Service::instance()->is_provider_url( $url );
	}


	/**
	 * Is installed?
	 *
	 * @return bool
	 */
	public static function is_installed(): bool {
		return defined( 'IMAGIFY_VERSION' ) || class_exists( '\Imagify', false );
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