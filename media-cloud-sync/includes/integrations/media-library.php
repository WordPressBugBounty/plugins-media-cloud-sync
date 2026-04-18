<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

use Exception;
use WP_Error;

class MediaLibrary {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;

    protected $config;
    protected $bucketConfig;
    protected $settings;
    protected $credentials;
    protected $service;

    protected $bucket_name;
    protected $region = '';

	private $deleting_attachment = false;


    public static $source_type_prefix = "media";
	public static $label = 'Media Library';

    // Map which meta to be updated
	public static $source_types = [
		"media_library" => [
            'table'         => 'posts',
			'class'			=> 'MediaLibrary'
        ]
	];

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url   = WPMCS_ASSETS_URL;
        $this->version      = WPMCS_VERSION;
        $this->token        = WPMCS_TOKEN;
        $this->settings     = Utils::get_settings();

        $this->credentials  = Utils::get_credentials();
        $this->config       = isset($this->credentials['config']) && !empty($this->credentials['config']) 
                                ? $this->credentials['config']
                                : [];
        $this->bucketConfig =  isset($this->credentials['bucketConfig']) && !empty($this->credentials['bucketConfig']) 
                                ? $this->credentials['bucketConfig']
                                : [];
        $this->service      =  isset($this->credentials['service']) && !empty($this->credentials['service']) 
                                ? $this->credentials['service']
                                : '';

        if (isset($this->bucketConfig['bucket_name'])) {
            $this->bucket_name = $this->bucketConfig['bucket_name'];
        }
        if (isset($this->config['region'])) {
            $this->region = $this->config['region'];
        }

        // Initialize setup
        $this->registerActions();
    }

    /**
     * Register integration access
     */
    public function registerActions() {
        /** IMAGE EDITOR COMPATIBILITY */
        add_action( 'attachment_submitbox_misc_actions', array( $this, 'attachment_submitbox_metadata'),99 );
        //Media Modal Ajax
		add_action( 'wp_ajax_wpmcs_get_attachment_details', array( $this, 'ajax_get_attachment_details' ) );

        /** URL RE-WRITING HOOKS */
        add_filter( 'wp_get_attachment_url', [ $this, 'wp_get_attachment_url' ], 99, 2 );
        add_filter( 'wp_get_attachment_image_attributes', [ $this, 'wp_get_attachment_image_attributes' ], 99, 3 );
        add_filter( 'get_image_tag', array( $this, 'get_image_tag' ), 99, 6 );
        add_filter( 'wp_get_attachment_image_src', array( $this, 'wp_get_attachment_image_src' ), 99, 4 );
        add_filter( 'wp_prepare_attachment_for_js', array( $this, 'wp_prepare_attachment_for_js' ), 99, 3 );
        add_filter( 'image_get_intermediate_size', array( $this, 'image_get_intermediate_size' ), 99, 3 );
        add_filter( 'get_attached_file', [ $this, 'get_attached_file' ], 10, 2 );
        add_filter( 'wp_get_original_image_path', [ $this, 'get_attached_file' ], 10, 2 );
        add_filter( 'wp_video_shortcode', [ $this, 'wp_media_shortcode' ], 100, 5  );
        add_filter( 'wp_audio_shortcode', [ $this, 'wp_media_shortcode' ], 100, 5  );
        
        /*
		 * Responsive Images WP 4.4
		 */
		add_filter( 'wp_calculate_image_srcset', array( $this, 'wp_calculate_image_srcset' ), 10, 5 );

        // Srcset handling
		add_filter( 'wp_image_file_matches_image_meta', array( $this, 'wp_image_file_matches_image_meta' ), 10, 4 );

        if ( self::wp_check_filetype_broken() ) {
			add_filter( 'shortcode_atts_audio', array( $this, 'filter_shortcode_atts' ), 10, 4 );
			add_filter( 'shortcode_atts_video', array( $this, 'filter_shortcode_atts' ), 10, 4 );
		}

        /** FILE MANAGEMENT HOOKS */
        add_filter( 'wp_unique_filename', [ $this, 'wp_unique_filename' ], 10, 3 );
        add_filter( 'wp_update_attachment_metadata', [ $this, 'update_attachment_metadata' ], 110, 2 );
		add_filter( 'pre_delete_attachment', [ $this, 'pre_delete_attachment' ], 20  );
        add_filter( 'delete_attachment', [ $this, 'delete_attachment' ], 20 );
		add_action( 'delete_post',  [$this, 'delete_post'] );
        add_filter( 'update_attached_file', [ $this, 'update_attached_file' ], 100, 2 ); 

        add_action( 'wpmcs_do_update_attachment_metadata', [ $this, 'update_attachment_metadata' ], 10, 2 );
    }


    /**
     * Upload a single media item from the local server to the provider.
     * 
     * This function will trigger the `wpmcs_do_update_attachment_metadata` action
     * with the `false` parameter, which will cause the attachment metadata
     * to be updated on the provider.
     *
     * @param int    $id       The media item ID.
     * @param string $source_type The source type of the media item.
     * @since 1.0.0
     */
    public function upload_single_media($id, $source_type = 'media_library') {
        do_action( 'wpmcs_do_update_attachment_metadata', false, $id );
    }


    /**
     * Allow processes to update the file on provider via update_attached_file()
     * @since 1.0.0
     * @param string $file
     * @param int    $attachment_id
     *
     * @return string
     */
    public function update_attached_file($file, $attachment_id) {
        if( !Utils::is_ok_to_upload($attachment_id) ) {
            return $file;
        }

        $item = Item::instance()->get($attachment_id, 'media_library');

        if (Utils::is_empty($item)) {
            return $file;
        }

        $file = apply_filters('wpmcs_update_attached_file', $file, $attachment_id, $item);

        return $file;
    }


    /**
     * Function to remove data from provider by attachment ID
     * @since 1.0.0
     *
     */
    public function delete_attachment($attachment_id){
        if (!Utils::is_service_enabled()) {
            return $attachment_id;
        }

        $wpmcsItem  = Item::instance();
        $item       = $wpmcsItem->get($attachment_id, 'media_library');

        if (Utils::is_empty($item)) {
            // Remove Log if exists
            Logger::instance()->remove_log('sync_to_cloud', $attachment_id, 'media_library');
            return $attachment_id;
        }

        $wpmcsItem->delete($attachment_id, 'media_library');

        return $attachment_id;
    }

    /**
     * Function to execute on wp_update_attachment_metadata
     * @since 1.0.0
     * @param array $attachment_meta
     * @param int   $attachment_id
     * @return array|WP_Error
     */
    public function update_attachment_metadata($attachment_meta, $attachment_id) {
        // Remove Logs
        Logger::instance()->remove_log('sync_to_cloud', $attachment_id, 'media_library');
        try {
            // Ensure attachment is eligible for upload
            $attachment_meta = is_array($attachment_meta) && !empty($attachment_meta) 
                                ? $attachment_meta 
                                : wp_get_attachment_metadata($attachment_id);

            if (!Utils::is_ok_to_upload($attachment_id) || is_wp_error($attachment_meta)) {
                return $attachment_meta;
            }

            $attachment_id = (int) $attachment_id;
            $is_image = wp_attachment_is_image($attachment_id);

            if ($is_image && $this->should_wait_for_subsizes($attachment_meta, $attachment_id)) {
                return $attachment_meta;
            }

            $file = $this->get_attachment_file($attachment_meta, $attachment_id);
            $source_path = Utils::get_attachment_source_path($file);

            if (empty($source_path) || !is_string($source_path)) {
                return $attachment_meta;
            }

            $existing = Item::instance()->get($attachment_id, 'media_library');
            $backup   = Item::instance()->get_backup($attachment_id, 'media_library');

            if (Utils::is_empty($existing)) {
                $this->handle_media_first_upload($attachment_meta, $attachment_id, $source_path);
            } elseif (Utils::is_empty($backup)) {
                $this->handle_media_with_no_backup($attachment_meta, $attachment_id, $source_path, $existing);
            } else {
                $this->handle_media_with_backup($attachment_meta, $attachment_id, $source_path, $existing, $backup);
            }

            return $attachment_meta;
        } catch (Exception $e) {
            Logger::instance()->add_log('sync_to_cloud', $attachment_id, 'media_library', [
                'message' => $e->getMessage() ?? 'Currupted attachment metadata.',
                'file'    => method_exists($e, 'getFile') ? $e->getFile() : 'N/A',
                'code'    => 415
            ]);
            return $attachment_meta;
        }
    }

    /**
     * Checks if we should wait for WordPress to generate subsizes.
     */
    private function should_wait_for_subsizes($attachment_meta, $attachment_id) {
        if (
            empty($attachment_meta) || 
            !function_exists('wp_get_registered_image_subsizes') || 
            !function_exists('wp_get_missing_image_subsizes')
        ) {
            return false;
        }

        // Wait for generate_attachment_metadata if the filter is set to true.
        if (apply_filters('wpmcs_wait_for_generate_attachment_metadata', false)) {
            return true;
        }

        // Check if there are any missing image subsizes.
        $image_sizes = apply_filters(
            'intermediate_image_sizes_advanced',
            wp_get_registered_image_subsizes(),
            $attachment_meta,
            $attachment_id
        );

        // If an image has been rotated, remove original image from metadata so that
        // `wp_get_missing_image_subsizes()` doesn't use non-rotated image for
        // generating missing thumbnail sizes.
        // Also, some images, particularly SVGs, don't create thumbnails but do have
        // metadata for them. At the time `wp_get_missing_image_subsizes()` checks
        // the saved metadata, it isn't there, but we already have it.
        $handle_post_meta = function ($value, $object_id, $meta_key, $single, $meta_type) use ($attachment_id, $attachment_meta) {
           if(is_array($attachment_meta) && isset($attachment_meta['image_meta']) && !empty($attachment_meta['image_meta']['orientation'])) {
                unset($attachment_meta['original_image']);
            }
            if(is_array($value) && isset($value['image_meta']) && !empty($value['image_meta']['orientation'])) {
                unset($value['original_image']);
            }
            
            if (
                is_null($value) && 
                $object_id === $attachment_id && 
                '_wp_attachment_metadata' === $meta_key && 
                $single && $meta_type === 'post'
            ) {
                // For some reason the filter is expected return an array of values
                // as if not doing a single record.
                return [$attachment_meta];
            }
            return $value;
        };

        add_filter('get_post_metadata', $handle_post_meta, 10, 5);
        $missing_sizes = wp_get_missing_image_subsizes($attachment_id);
        remove_filter('get_post_metadata', $handle_post_meta);

        return !empty(array_intersect_key($missing_sizes, $image_sizes));
    }

    /**
     * Gets the attachment file path.
     */
    private function get_attachment_file($attachment_meta, $attachment_id) {
        $file = $attachment_meta['file'] ?? Utils::get_post_meta($attachment_id, '_wp_attached_file', true);
        if ($file === basename($file)) {
            $file = Utils::get_post_meta($attachment_id, '_wp_attached_file', true);
        }
        return $file;
    }

    /**
     * Handles first upload.
     * @since 1.2.13
     * @param array $attachment_meta
     * @param int   $attachment_id
     * @param string $source_path
     */
    private function handle_media_first_upload($attachment_meta, $attachment_id, $source_path) {
        $uploaded_data = $this->uploadMedia($attachment_meta, $attachment_id, $source_path);
        if ( $uploaded_data && isset($uploaded_data['file']) && !empty($uploaded_data['file'])) {
            Item::instance()->add(
                $attachment_id,
                $uploaded_data['file']['url'],
                $uploaded_data['file']['key'],
                $uploaded_data['file']['source_path'],
                $uploaded_data['file']['original_source_path'] ?? '',
                $uploaded_data['file']['original_key'] ?? '',
                $uploaded_data['extra'],
                'media_library'
            );
        }
    }

    /**
     * Handles upload when no backup exists.
     * @since 1.2.13
     * @param array $attachment_meta
     * @param int   $attachment_id
     * @param string $source_path
     * @param array $existing
     */
    private function handle_media_with_no_backup($attachment_meta, $attachment_id, $source_path, $existing) {
        $uploaded_data = $this->uploadMedia($attachment_meta, $attachment_id, $source_path);

        if ($existing['source_path'] != $source_path) {
            $uploaded_data['extra']['backup'] = Utils::maybe_serialize($existing);
        }

        $this->update_item_if_uploaded($attachment_id, $uploaded_data);
    }

    /**
     * Handles upload when backup exists.
     * @since 1.2.13
     * @param array $attachment_meta
     * @param int   $attachment_id
     * @param string $source_path
     * @param array $existing
     * @param array $backup
     */
    private function handle_media_with_backup($attachment_meta, $attachment_id, $source_path, $existing, $backup) {
        $delete_existing = false;

        if ($backup['source_path'] != $source_path) {
            $uploaded_data = $this->uploadMedia($attachment_meta, $attachment_id, $source_path);
            $uploaded_data['extra']['backup'] = Utils::maybe_serialize($backup);

            if ($existing['source_path'] != $source_path) {
                $delete_existing = true;
            }
        } else {
            $delete_existing = true;

            // construct upload data from backup
            $uploaded_data = [
                'file' => [
                    'source_path'           => $backup['source_path'],
                    'url'                   => $backup['url'],
                    'key'                   => $backup['key'],
                    'original_source_path'  => $backup['original_source_path'],
                    'original_key'          => $backup['original_key'],
                ],
                'extra' => Utilsmaybe_unserialize($backup['extra']),
            ];
        }

        if ($delete_existing) {
            Item::instance()->delete_attachments_by_item($existing, false);
            Item::instance()->delete_server_files_by_item($existing, true);
        }

        $this->update_item_if_uploaded($attachment_id, $uploaded_data);
    }

    /**
     * Updates item if upload was successful.
     * @since 1.2.13
     * @param int   $attachment_id
     * @param array $uploaded_data
     * @return void
     */
    private function update_item_if_uploaded($attachment_id, $uploaded_data) {
        if (!empty($uploaded_data['file'])) {
            Item::instance()->update(
                $attachment_id,
                [
                    'source_path'           => $uploaded_data['file']['source_path'],
                    'url'                   => $uploaded_data['file']['url'],
                    'key'                   => $uploaded_data['file']['key'],
                    'original_source_path'  => $uploaded_data['file']['original_source_path'] ?? '',
                    'original_key'          => $uploaded_data['file']['original_key'] ?? '',
                    'extra'                 => Utils::maybe_serialize($uploaded_data['extra']),
                ],
                'media_library'
            );
        }
    }


    /**
     * Create unique names for files effects mainly on delete files from server settings
     * @since 1.0.0
     * @return string
     */
    public function wp_unique_filename($filename, $ext, $dir) {
        // Get Post ID if uploaded in post screen.
        $post_id = Utils::filter_input( 'post_id', INPUT_POST, FILTER_VALIDATE_INT );

        $filename = $this->filter_unique_filename($filename, $ext, $dir, $post_id);

        return $filename;
    }

    /**
     * filter unique file names
     * @since 1.0.0
     * @return string
     */
    private function filter_unique_filename($filename, $ext, $dir, $post_id = null) {
        if (!Utils::is_service_enabled()) {
            return $filename;
        }

        // sanitize the file name before we begin processing
        $filename   = sanitize_file_name($filename);
        $ext        = strtolower($ext);
        $name       = wp_basename($filename, $ext);

        // Edge case: if file is named '.ext', treat as an empty name.
        if ($name === $ext) {
            $name = '';
        }

        // Rebuild filename with lowercase extension as provider will have converted extension on upload.
        $filename = $name . $ext;

        return $this->generate_unique_filename($name, $ext, $dir);
    }


    /**
     * Generate unique filename
     * @since 1.0.0
     * @param string $name
     * @param string $ext
     * @param string $time
     *
     * @return string
     */
    private function generate_unique_filename($name, $ext, $dir) {
        $upload_dir         = wp_get_upload_dir();
        $filename           = $name . $ext;
        $no_ext_path        = $dir . '/' . $name;
        $rel_no_ext_path    = substr($no_ext_path, strlen(trailingslashit($upload_dir['basedir'])),strlen($no_ext_path));
        $path               = $dir . '/' . $name . $ext;
        $source_path        = substr($path, strlen(trailingslashit($upload_dir['basedir'])),strlen($path));


        $uploaded_files = Item::instance()->get_similar_files_by_path($rel_no_ext_path);

        if ($uploaded_files !== false) {
            if (Utils::check_existing_file_names($source_path, $uploaded_files) || file_exists($path)) {
                $count = 1;
                $new_file_name = '';
                $found = true;
                while ($found) {
                    $tmp_path   = $dir . '/' . $name . '-' . $count . $ext;
                    $rel_temp_path   = substr($tmp_path, strlen(trailingslashit($upload_dir['basedir'])),strlen($tmp_path));

                    if (Utils::check_existing_file_names($rel_temp_path, $uploaded_files) || file_exists($tmp_path)) {
                        $count++;
                    } else {
                        $found = false;
                        $new_file_name = $name . '-' . $count . $ext;
                    }
                }
                return $new_file_name;
            }
        } else {
            if (file_exists($path)) {
                $count = 1;
                $new_file_name = '';
                $found = true;

                while ($found) {
                    $tmp_path = $dir . '/' . $name . '-' . $count . $ext;
                    if (file_exists($tmp_path)) {
                        $count++;
                    } else {
                        $found = false;
                        $new_file_name = $name . '-' . $count . $ext;
                    }
                }
                return $new_file_name;
            }
        }

        return $filename;
    }


    /**
	 * Filters the audio & video shortcodes output to remove "&_=NN" params from source.src as it breaks signed URLs.
	 *
	 * @param string $html    Shortcode HTML output.
	 * @param array  $atts    Array of shortcode attributes.
	 * @param string $media   Media file.
	 * @param int    $post_id Post ID.
	 * @param string $library Media library used for the shortcode.
	 *
	 * @return string
	 *
	 * Note: Depends on 30377.4.diff from https://core.trac.wordpress.org/ticket/30377
	 */
	public function wp_media_shortcode( $html, $atts, $media, $post_id, $library ) {
		return preg_replace( '/&#038;_=[0-9]+/', '', $html );
	}

    /**
     * Return the provider URL when the server file is missing
     * unless we know who the calling process is and we are happy
     * to copy the file back to the server to be used.
     *
     * @handles get_attached_file
     * @handles wp_get_original_image_path
     * @since 1.0.0
     * @param string $file
     * @param int    $attachment_id
     *
     * @return string
     */
    public function get_attached_file($file, $attachment_id) {
        $attachment_id = (int)$attachment_id;

        // During the deletion of an attachment, stream wrapper URLs should not be returned.
		if ( $this->deleting_attachment ) {
			return $file;
		}

        if (!Utils::is_ok_to_serve($attachment_id)) {
            return $file;
        }

        $wpmcsItem = Item::instance();

        $item = $wpmcsItem->get($attachment_id, 'media_library');

        if ( file_exists( $file ) || Utils::is_empty($item)) {
            if(!Utils::is_empty($item)) {
                /**
                 * Added filter to allow plugins to copy the pending size to the server
                 * before the file is served.
                 * 
                 * @param string $file
                 * @param string $file
                 * @param int    $attachment_id
                 */
                return apply_filters( 'wpmcs_get_attached_file_noop', $file, $file, $attachment_id, $item );
            } else {
                return $file;
            }
        }
        
        $url = $wpmcsItem->get_url($attachment_id, 'full', 'media_library');
        if(Utils::is_empty($url)) { 
            return $file;
        }
        /**
		 * This filter gives filter implementors a chance to copy back missing item files
		 * from the provider before WordPress returns the file name/path for it. Defaults to
		 * returning the remote URL.
		 *
		 * @param string             $url           Item URL
         * @param string             $file          Server file path
         * @param int                $attachment_id Attachment ID
         * @param array              $item          Item data
		 */
        return apply_filters('wpmcs_get_attached_file', $url, $file, $attachment_id, $item);
    }


    /**
     * Change src attributes
     * @since 1.0.0
     */

    public function wp_calculate_image_srcset($sources, $size_array, $image_src, $attachment_meta, $attachment_id = 0) {
        $attachment_id = (int)$attachment_id;
        if ( ! is_array( $sources ) ) {
			// Sources corrupt
			return $sources;
		}

        // Must need $attachment_id other wise not possible to get data from the table
        if (!Utils::is_ok_to_serve($attachment_id)) {
            return $sources;
        }

        $wpmcsItem = Item::instance();

        if (Utils::is_empty($wpmcsItem->get($attachment_id, 'media_library'))) {
            return $sources;
        }

        foreach ( $sources as $width => $source ) {
			$filename     = wp_basename( $source['url'] );
			$size         = $this->find_image_size_from_width( $attachment_meta, $width, $filename );
			$provider_url = $wpmcsItem->get_url( $attachment_id, $size, 'media_library' );

			if ( false === $provider_url || is_wp_error( $provider_url ) ) {
				// Skip URLs not uploaded to cloud
				continue;
			}

			$sources[ $width ]['url'] = $provider_url;
		}
        return $sources;
    }

    /**
	 * Helper function to find size name from width and filename
	 *
	 * @param array  $sizes
	 * @param string $width
	 * @param string $filename
	 *
	 * @return null|string
	 */
	protected function find_image_size_from_width( $meta, $width, $filename ) {
        $sizes = $meta['sizes'];
		foreach ( $sizes as $name => $size ) {
			if ( $width === absint( $size['width'] ) && $size['file'] === $filename ) {
				return $name;
			}
		}

        // Check for full size
        if($width === $meta['width']) {
            $full_file     = wp_basename( $meta['file'] );
            if($full_file === $filename) {
                return 'full';
            }
        }

		return null;
	}

    /**
     * Filters the list of attachment image attributes.
     *
     * @since 1.0.0
     * @param array        $attr  Attributes for the image markup.
     * @param WP_Post      $attachment Image attachment post.
     * @param string|array $size  Requested size. Image size or array of width and height values (in that order).
     *
     * @return array
     */
    public function wp_get_attachment_image_attributes($attr, $attachment, $size='thumbnail') {
        if (
            !$attachment || 
            !Utils::is_ok_to_serve($attachment->ID)
        ) {
            return $attr;
        }

        $wpmcsItem = Item::instance();

        $item = $wpmcsItem->get($attachment->ID, 'media_library');

        if (Utils::is_empty($item)) {
            return $attr;
        }

        $size = Utils::maybe_convert_size_to_string($attachment->ID, $size);
        if ($size === false) {
            return $attr;
        }

        

        if (
            isset($size) && !empty($size) &&
            isset($attr['src']) && !empty($attr['src'])
        ) {
            $source = $wpmcsItem->get_url($attachment->ID, $size, 'media_library');
            if (isset($source) && !empty($source)) {
                $attr['src'] = $source;
            }
        }

        /**
         * Filtered list of attachment image attributes.
         *
         * @param array              $attr       Attributes for the image markup.
         * @param WP_Post            $attachment Image attachment post.
         * @param string             $size       Requested size.
         */
        return apply_filters('wpmcs_wp_get_attachment_image_attributes', $attr, $attachment, $size, $item);
    }

    /**
     * Get attachment url
     * @since 1.0.0
     * @param string $url
     * @param int    $attachment_id
     *
     * @return bool|mixed|WP_Error
     */
    public function wp_get_attachment_url($url, $attachment_id) {
        if (!Utils::is_ok_to_serve($attachment_id)) {
            return $url;
        }

        $new_url = Item::instance()->get_url($attachment_id, 'full', 'media_library');

        if (Utils::is_empty($new_url)) {
            return $url;
        }

        $new_url = apply_filters('wpmcs_wp_get_attachment_url', $new_url, $url, $attachment_id);

        return $new_url;
    }

    /**
	 * Maybe replace attachment URLs when retrieving the image tag
	 *
	 * @param string $html
	 * @param int    $id
	 * @param string $alt
	 * @param string $title
	 * @param string $align
	 * @param string $size
	 *
	 * @return string
	 */
	public function get_image_tag( $html, $id, $alt, $title, $align, $size ) {
		if ( ! is_string( $html ) ) {
			return $html;
		}

        if (!Utils::is_ok_to_serve($id)) {
            return $html;
        }

        preg_match( '@\ssrc=[\'\"]([^\'\"]*)[\'\"]@', $html, $matches );

		if ( ! isset( $matches[1] ) ) {
			// Can't establish img src
			return $html;
		}

        $img_src = $matches[1];
        $size = Utils::maybe_convert_size_to_string( $id, $size );
        $new_url = Item::instance()->get_url($id, $size, 'media_library');

        if (Utils::is_empty($new_url)) {
            return $html;
        }

		return str_replace( $img_src, $new_url, $html );
	}


    /**
	 * Relace URLs for images that represent an attachment
	 *
	 * @param array|bool   $image
	 * @param int          $attachment_id
	 * @param string|array $size
	 * @param bool         $icon
	 *
	 * @return array
	 */
    public function wp_get_attachment_image_src( $image, $attachment_id, $size, $icon ) {
        if (!Utils::is_ok_to_serve($attachment_id)) {
            return $image;
        }
		
		if ( isset( $image[0] ) ) {
            $size = Utils::maybe_convert_size_to_string( $attachment_id, $size );
            $new_url = Item::instance()->get_url($attachment_id, $size, 'media_library');

            if (Utils::is_empty($new_url)) {
                return $image;
            }

			$image[0] = $new_url;
		}

		return $image;
	}

    /**
	 * Replace URLs when outputting attachments in the media grid
	 *
	 * @param array      $response
	 * @param int|object $attachment
	 * @param array      $meta
	 *
	 * @return array
	 */
	public function wp_prepare_attachment_for_js( $response, $attachment, $meta ) {
		if (!Utils::is_ok_to_serve($attachment->ID)) {
            return $response;
        }

		if ( isset( $response['sizes'] ) && is_array( $response['sizes'] ) ) {
			foreach ( $response['sizes'] as $size => $value ) {
                $url = Item::instance()->get_url($attachment->ID, $size, 'media_library');
                if (Utils::is_empty($url)) {
                    continue;
                }
				$response['sizes'][ $size ]['url'] = $url;
			}
		}

		return $response;
	}


	/**
	 * Replace URLs when retrieving intermediate sizes.
	 *
	 * @param array        $data
	 * @param int          $post_id
	 * @param string|array $size
	 *
	 * @return array
	 */
	public function image_get_intermediate_size( $data, $post_id, $size ) {
        if (!Utils::is_ok_to_serve($post_id)) {
            return $data;
        }
		if ( isset( $data['url'] ) ) {
            $size = Utils::maybe_convert_size_to_string( $post_id, $size );
			$url = Item::instance()->get_url($post_id, $size, 'media_library');

            if (Utils::is_empty($url)) {
                return $data;
            }

			$data['url'] = $url;
		}

		return $data;
	}

    /**
	 * Determines if the image metadata is for the image source file.
	 *
	 * @handles wp_image_file_matches_image_meta
	 *
	 * @param bool   $match
	 * @param string $image_location
	 * @param array  $image_meta
	 * @param int    $source_id
	 *
	 * @return bool
	 */
	public function wp_image_file_matches_image_meta( $match, $image_location, $image_meta, $source_id ) {
		// If already matched or the URL is local, there's nothing for us to do.
		if ( $match || FilterContent::url_needs_replacing( $image_location ) ) {
			return $match;
		}

		$item = array(
			'id'          => $source_id,
			'source_type' => 'media_library',
		);

		return FilterContent::instance()->item_matches_src( $item, $image_location );
	}


    /**
	 * Filters shortcode attributes to temporarily add file extension to end of URL params.
	 *
	 * The temporary extension is removed once wp_check_filetype has been used.
	 *
	 * The function compensates for when query args or fragments are included in the URL,
	 * which makes wp_check_filetype fail to see the extension of the file.
	 *
	 * @see https://core.trac.wordpress.org/ticket/30377
	 * @see https://github.com/aaemnnosttv/fix-wp-media-shortcodes-with-params/blob/master/fix-wp-media-shortcodes-with-params.php
	 *
	 * @param array  $out       The output array of shortcode attributes.
	 * @param array  $pairs     The supported attributes and their defaults.
	 * @param array  $atts      The user defined shortcode attributes.
	 * @param string $shortcode The shortcode name.
	 *
	 * @return array
	 */
	public function filter_shortcode_atts( $out, $pairs, $atts, $shortcode ) {
		$get_media_extensions = "wp_get_{$shortcode}_extensions";

		if ( ! function_exists( $get_media_extensions ) ) {
			return $out;
		}

		$default_types = $get_media_extensions();

		if ( empty( $default_types ) || ! is_array( $default_types ) ) {
			return $out;
		}

		// URLs can be in src or type specific fallback attributes.
		array_unshift( $default_types, 'src' );

		$fixes = array();

		foreach ( $default_types as $type ) {
			if ( empty( $out[ $type ] ) ) {
				continue;
			}

			if ( false !== strpos( $out[ $type ], '&'.$this->token.'-fix-wp-check-file-type-ext=.' ) ) {
				continue;
			}

			if ( Utils::is_url( $out[ $type ] ) ) {
				$url   = $out[ $type ];
				$parts = wp_parse_url( $url );

				if (
					empty( $parts['path'] ) ||
					( empty( $parts['query'] ) && empty( $parts['fragment'] ) )
				) {
					continue;
				}

				$ext = pathinfo( $parts['path'], PATHINFO_EXTENSION );

				if ( empty( $ext ) ) {
					continue;
				}

				$scheme = empty( $parts['scheme'] ) ? '' : $parts['scheme'] . '://';
				$user   = empty( $parts['user'] ) ? '' : $parts['user'];
				$pass   = ! empty( $user ) && ! empty( $parts['pass'] ) ? ':' . $parts['pass'] : '';
				$auth   = ! empty( $user ) ? $user . $pass . '@' : '';
				$host   = empty( $parts['host'] ) ? '' : $parts['host'];
				$port   = ! empty( $host ) && ! empty( $parts['port'] ) ? ':' . $parts['port'] : '';
				$path   = $parts['path'];
				$query  = empty( $parts['query'] ) ? '?'.$this->token.'-fix-wp-check-file-type=true' : '?' . $parts['query'];

				if ( ! empty( $parts['fragment'] ) ) {
					$query .= '&'.$this->token.'-fix-wp-check-file-type-fragment=' . $parts['fragment'];
				}

				$query .= '&'.$this->token.'-fix-wp-check-file-type-ext=.' . $ext;

				$out[ $type ] = $scheme . $auth . $host . $port . $path . $query;
				$fixes[]      = $ext;
			}
		}

		if ( $fixes ) {
			add_filter( "wp_{$shortcode}_shortcode", function ( $html ) use ( $fixes ) {
				$html = str_replace( '?'.$this->token.'-fix-wp-check-file-type=true', '', $html );

				foreach ( $fixes as $ext ) {
					$html = str_replace( '&#038;'.$this->token.'-fix-wp-check-file-type-ext=.' . $ext, '', $html );
				}

				return str_replace( '&#038;'.$this->token.'-fix-wp-check-file-type-fragment=', '#', $html );
			} );
		}

		return $out;
	}


    /**
     * Upload media item
     */
    public function uploadMedia($attachment_meta, $attachment_id, $source_path) {
        $wpmcsItem          = Item::instance();
        $upload_dir         = wp_get_upload_dir();
        $is_image           = wp_attachment_is_image($attachment_id);
        $existing           = $wpmcsItem->get($attachment_id, 'media_library');
        $existing_extras    = $wpmcsItem->get_extras($attachment_id, false, 'media_library');
        $has_existing       = !Utils::is_empty($existing);
        $sizes              = [];
        $uploaded           = [];
        $extras             = [];
        $prefix             = '';
        $file_path          = trailingslashit($upload_dir['basedir']) . $source_path;
        $file_dir           = isset(pathinfo($file_path)['dirname']) ? pathinfo($file_path)['dirname'] : '';
        $original_file      = isset($attachment_meta['original_image']) ? $attachment_meta['original_image'] : '';

        $do_reupload        = apply_filters('wpmcs_do_reupload_media', false, $attachment_id, 'media_library');

        // Force reupload when sizes/dimensions changed
        if (!$do_reupload && $has_existing) {
            if($this->shouldForceReupload($attachment_meta, $attachment_id, $existing_extras)) {
                $do_reupload = true; 
            }
        }

        // Add prefix if object versioning is ON
        if (isset($this->settings['object_versioning']) && $this->settings['object_versioning']) {
            $prefix           = ($has_existing && isset($existing_extras['prefix']) && !empty($existing_extras['prefix']))
                                    ? $existing_extras['prefix']
                                    : Utils::generate_object_versioning_prefix();
            $extras['prefix'] = $prefix;
        }

        // Width/height
        if (isset($attachment_meta) && !empty($attachment_meta)) {
            $extras['width']  = (isset($attachment_meta['width']) && !empty($attachment_meta['width'])) ? $attachment_meta['width'] : 0;
            $extras['height'] = (isset($attachment_meta['height']) && !empty($attachment_meta['height'])) ? $attachment_meta['height'] : 0;
        }

        // Check whether the extension is enabled for uploading
        if (!Utils::is_extension_available($file_path)) {
            Logger::instance()->add_log('sync_to_cloud', $attachment_id, 'media_library', [
                'message' => __('File extension is not supported', 'media-cloud-sync'),
                'file'    => $file_path,
                'code'    => 415
            ]);
            return false;
        }

        // Upload main file first, if that fails no need to attempt the rest
        $uploaded = $this->uploadFullFile($file_path, $source_path, $do_reupload, $has_existing, $existing, $prefix, $attachment_id);

        if ($uploaded && isset($uploaded['success']) && $uploaded['success']) {
            // Attempt to upload original file if present. Not critical if this fails so we don't check the result before proceeding.
            $original = $this->uploadOriginalFile($original_file, $file_dir, $do_reupload, $has_existing, $existing, $prefix, $attachment_id);

            if ($is_image && isset($file_dir) && !empty($file_dir)) {
                $sizes_to_upload = $this->get_attachment_image_sizes_for_upload($attachment_meta, $attachment_id);
                if (!empty($sizes_to_upload)) {
                    // Upload image sizes. Again, not critical if this fails so we don't check the result before proceeding.
                    $sizes = $this->uploadImageSizes($sizes_to_upload, $file_dir, $do_reupload, $existing_extras, $prefix, $attachment_id);
                }
            }

            if (!empty($sizes)) {
                $extras['sizes'] = $sizes;
            }

            return [
                'file' => [
                    'source_path'          => $source_path,
                    'url'                  => $uploaded['file_url'],
                    'key'                  => $uploaded['key'],
                    'original_source_path' => isset($original['source_path']) ? $original['source_path'] : '',
                    'original_key'         => isset($original['key']) ? $original['key'] : ''
                ],
                'extra' => $extras
            ];
        }

        if (isset($uploaded['success']) && !$uploaded['success']) {
            Logger::instance()->add_log('sync_to_cloud', $attachment_id, 'media_library', [
                'message' => __('File upload failed', 'media-cloud-sync'),
                'file'    => $file_path,
                'code'    => 500
            ]);
        }

        return false;
    }

    /**
     * Image subsizes to sync to cloud (after filters). Empty means no subsizes are uploaded.
     *
     * `wpmcs_skip_upload_image_sizes` — (bool) When true, no subsizes are uploaded.
     * `wpmcs_upload_attachment_image_sizes` — (array) Subset of `$attachment_meta['sizes']` to upload; empty skips all.
     *
     * @param array $attachment_meta Full attachment metadata.
     * @param int   $attachment_id   Attachment post ID.
     * @return array Same shape as attachment meta `sizes` entries.
     */
    private function get_attachment_image_sizes_for_upload($attachment_meta, $attachment_id) {
        $sizes = [];
        if (isset($attachment_meta['sizes']) && is_array($attachment_meta['sizes'])) {
            $sizes = $attachment_meta['sizes'];
        }

        if (apply_filters('wpmcs_skip_upload_image_sizes', false, $attachment_id, $attachment_meta)) {
            return [];
        }

        $sizes = apply_filters('wpmcs_upload_attachment_image_sizes', $sizes, $attachment_id, $attachment_meta);

        return is_array($sizes) ? $sizes : [];
    }

    /**
     * Decide if we should force a reupload based on dims/sizes differences.
     * @since 1.3.6
     * @param array $attachment_meta
     * @param int   $attachment_id
     * @param array $existing_extras
     */
    private function shouldForceReupload($attachment_meta, $attachment_id, $existing_extras) {
        $existing_extras = $existing_extras ?? Item::instance()->get_extras($attachment_id, false, 'media_library');

        $current_width  = isset($attachment_meta['width']) ? (int)$attachment_meta['width'] : 0;
        $current_height = isset($attachment_meta['height']) ? (int)$attachment_meta['height'] : 0;

        $existing_width  = isset($existing_extras['width']) ? (int)$existing_extras['width'] : 0;
        $existing_height = isset($existing_extras['height']) ? (int)$existing_extras['height'] : 0;

        if ($current_width !== $existing_width || $current_height !== $existing_height) {
            return true;
        }

        $filtered_sizes = $this->get_attachment_image_sizes_for_upload($attachment_meta, $attachment_id);
        if (empty($filtered_sizes)) {
            return false;
        }

        $existing_sizes = isset($existing_extras['sizes']) && is_array($existing_extras['sizes']) ? $existing_extras['sizes'] : [];
        $current_sizes  = [];
        foreach ($filtered_sizes as $sname => $smeta) {
            $current_sizes[$sname] = [
                'width'  => isset($smeta['width']) ? (int)$smeta['width'] : 0,
                'height' => isset($smeta['height']) ? (int)$smeta['height'] : 0,
            ];
        }

        $existing_relevant = [];
        foreach (array_keys($current_sizes) as $sname) {
            if (isset($existing_sizes[$sname])) {
                $existing_relevant[$sname] = $existing_sizes[$sname];
            }
        }

        if (array_keys($existing_relevant) !== array_keys($current_sizes)) {
            return true;
        }

        foreach ($current_sizes as $sname => $dims) {
            if (!isset($existing_sizes[$sname])) {
                return true;
            }
            $ex_w = isset($existing_sizes[$sname]['width']) ? (int)$existing_sizes[$sname]['width'] : 0;
            $ex_h = isset($existing_sizes[$sname]['height']) ? (int)$existing_sizes[$sname]['height'] : 0;
            if ($ex_w !== $dims['width'] || $ex_h !== $dims['height']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Upload the main/full file.
     * @since 1.3.6
     * @param string $file_path
     * @param string $source_path
     * @param bool   $do_reupload
     * @param bool   $has_existing
     * @param array  $existing
     * @param string $prefix
     * @param int    $attachment_id
     */
    private function uploadFullFile($file_path, $source_path, $do_reupload, $has_existing, $existing, $prefix, $attachment_id) {
        if (!$do_reupload && $has_existing && ($existing['source_path'] == $source_path)) {
            return [
                'success'  => true,
                'file_url' => $existing['url'],
                'key'      => $existing['key']
            ];
        }

        if (file_exists($file_path)) {
            return Service::instance()->uploadSingle($file_path, $source_path, $prefix);
        }

        Logger::instance()->add_log('sync_to_cloud', $attachment_id, 'media_library', [
            'message' => __('File not found', 'media-cloud-sync'),
            'file'    => $file_path,
            'code'    => 404
        ]);

        return false;
    }

    /**
     * Upload original file when present.
     * @since 1.3.6
     * @param string $original_file
     * @param string $file_dir
     * @param bool   $do_reupload
     * @param bool   $has_existing
     * @param array  $existing
     * @param string $prefix
     * @param int    $attachment_id
     */
    private function uploadOriginalFile($original_file, $file_dir, $do_reupload, $has_existing, $existing, $prefix, $attachment_id) {
        $original = [];
        if (empty($original_file)) {
            return $original;
        }

        $original_file_path = trailingslashit($file_dir) . $original_file;
        $original_file_source_path = Utils::get_attachment_source_path($original_file_path);

        if (!$do_reupload && $has_existing && isset($existing['original_source_path']) && !Utils::is_empty($existing['original_source_path']) && $existing['original_source_path'] === $original_file_source_path) {
            return [ 'source_path' => $original_file_source_path, 'key' => $existing['original_key'] ];
        }

        if (file_exists($original_file_path)) {
            $uploaded_original = Service::instance()->uploadSingle($original_file_path, $original_file_source_path, $prefix);
            if (isset($uploaded_original['success']) && $uploaded_original['success']) {
                return [ 'source_path' => $original_file_source_path, 'key' => $uploaded_original['key'] ];
            }

            if (isset($uploaded_original['success']) && !$uploaded_original['success']) {
                Logger::instance()->add_log('sync_to_cloud', $attachment_id, 'media_library', [
                    'message' => __('Original File upload failed', 'media-cloud-sync'),
                    'file'    => $original_file_path,
                    'code'    => 500
                ]);
            }
        } else {
            Logger::instance()->add_log('sync_to_cloud', $attachment_id, 'media_library', [
                'message' => __('Original File not found', 'media-cloud-sync'),
                'file'    => $original_file_path,
                'code'    => 404
            ]);
        }

        return $original;
    }

    /**
     * Upload intermediate image sizes.
     * @since 1.3.6
     * @param array  $attachment_sizes
     * @param string $file_dir
     * @param bool   $do_reupload
     * @param array  $existing_extras
     * @param string $prefix
     * @param int    $attachment_id
     * @return array    
     */
    private function uploadImageSizes($attachment_sizes, $file_dir, $do_reupload, $existing_extras, $prefix, $attachment_id) {
        $sizes = [];

        foreach ($attachment_sizes as $size => $sub_image) {
            $sub_size = [];
            $sub_file = isset($sub_image['file']) ? $sub_image['file'] : false;
            $sub_file_path = '';
            $sub_file_source_path = '';

            if ($sub_file) {
                $sub_file_path = $file_dir . '/' . $sub_file;
                $sub_file_source_path = Utils::get_attachment_source_path($sub_file_path);
            }

            $uploaded_sub_image = [];

            if (
                !$do_reupload &&
                !Utils::is_empty($existing_extras) &&
                isset($existing_extras['sizes']) && !Utils::is_empty($existing_extras['sizes']) &&
                isset($existing_extras['sizes'][$size]) && !Utils::is_empty($existing_extras['sizes'][$size]) &&
                $existing_extras['sizes'][$size]['source_path'] == $sub_file_source_path
            ) {
                $uploaded_sub_image = [
                    'success'  => true,
                    'file_url' => $existing_extras['sizes'][$size]['url'],
                    'key'      => $existing_extras['sizes'][$size]['key']
                ];
            } else if (file_exists($sub_file_path)) {
                $uploaded_sub_image = Service::instance()->uploadSingle($sub_file_path, $sub_file_source_path, $prefix);
            } else {
                Logger::instance()->add_log('sync_to_cloud', $attachment_id, 'media_library', [
                    /* translators: %1$s: Image size name (e.g., thumbnail, medium, large). */
                    'message' => sprintf(__('Image size %1$s not found', 'media-cloud-sync'), $size),
                    'file'    => $sub_file_path,
                    'code'    => 404
                ]);
            }

            if (isset($uploaded_sub_image) && !empty($uploaded_sub_image) && isset($uploaded_sub_image['success']) && $uploaded_sub_image['success']) {
                $sub_size['source_path'] = $sub_file_source_path;
                $sub_size['url'] = $uploaded_sub_image['file_url'];
                $sub_size['key'] = $uploaded_sub_image['key'];
                $sub_size['width'] = isset($sub_image['width']) ? $sub_image['width'] : 0;
                $sub_size['height'] = isset($sub_image['height']) ? $sub_image['height'] : 0;
            } else {
                if (isset($uploaded_sub_image['success']) && !$uploaded_sub_image['success']) {
                    Logger::instance()->add_log('sync_to_cloud', $attachment_id, 'media_library', [
                        /* translators: %1$s: Image size name (e.g., thumbnail, medium, large). */
                        'message' => sprintf(__('Image size %1$s upload failed', 'media-cloud-sync'), $size),
                        'file'    => $sub_file_path,
                        'code'    => 500
                    ]);
                }
            }

            $sizes[$size] = $sub_size;
        }

        return $sizes;
    }


    /**
     * Get Total Media Count (queried)
     * 
     * Param added because need a generic function name, 
     * if the function calls by source_type
     */
    public static function get_total_media($source_type) {
        global $wpdb;
    
        // Fetch the count of attachments with the 'inherit' status
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                'attachment',
                'inherit'
            )
        );
    
        return (int)$count;
    }


    /**
     * Upload pending media files.
     *
     * Processes media attachments that are pending and haven't been synced yet.
     *
     * @param string $source_type Source type for identifying which source..
     * @param int $limit  Number of media to process. Default is 50.
     * @param int $offset Offset for media query. Default is 0.
     * @return array      Status array with success and failed media IDs.
     */
    public function upload_pending_media( $source_type, $limit = 50, $offset = 0 ) {
        global $wpdb;

        $failed = 0;

        $source_type_data = self::$source_types[ $source_type ] ?? false;
        if ( ! $source_type_data ) {
            return [ 'success' => true, 'failed' => $limit ];
        }

        $posts = $wpdb->prefix . $source_type_data['table'];
        $items = Db::get_table_name();

        $join  = "
            items.source_id   = posts.ID
            AND items.source_type = %s
            AND items.provider    = %s
            AND items.storage     = %s
        ";

        $params = [
            $source_type,
            $this->service,
            $this->bucket_name,
        ];

        if ( ! empty( $this->region ) ) {
            $join    .= " AND items.region = %s";
            $params[] = $this->region;
        }

        $sql = "
            SELECT posts.ID
            FROM {$posts} AS posts
            LEFT JOIN {$items} AS items USE INDEX (idx_item_lookup)
                ON {$join}
            WHERE posts.post_type = 'attachment'
            AND items.source_id IS NULL
            ORDER BY posts.ID ASC
            LIMIT %d OFFSET %d
        ";

        $params[] = (int) $limit;
        $params[] = (int) $offset;

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

        if ( empty( $rows ) ) {
            return [ 'success' => true, 'failed' => 0 ];
        }

        foreach ( $rows as $row ) {
            try {
                $this->upload_single_media( (int) $row->ID, $source_type );
            } catch ( \Throwable $e ) {
                $failed++;
                continue;
            }

            if ( ! Item::instance()->get( (int) $row->ID, $source_type ) ) {
                $failed++;
            }
        }

        return [
            'success' => true,
            'failed'  => $failed,
        ];
    }


    /**
	 * Edit Image Meta In View Image
	 * @since 1.0.0
	 */
	function attachment_submitbox_metadata( ) {
        $post          = get_post();
	    $attachment_id = $post->ID;

        if (!Utils::is_ok_to_serve($attachment_id)) {
            return;
        }

        $wpmcsItem = Item::instance();

        $item = $wpmcsItem->get($attachment_id, 'media_library');

        if (Utils::is_empty($item)) {
            return;
        }
        
        $provider = $wpmcsItem->get_field($attachment_id, 'provider', 'media_library');
        if($provider) { 
            $label = Schema::getServiceLabels($provider); ?>
            <div class="misc-pub-section misc-pub-provider">
                <?php esc_html_e( 'Provider :', 'media-cloud-sync' ); ?> <strong><?php echo esc_textarea(!empty($label) ? $label : $provider); ?></strong></a>
            </div>
        <?php
        }

        $region = $wpmcsItem->get_field($attachment_id, 'region', 'media_library');
        if($region) { ?>
            <div class="misc-pub-section misc-pub-provider">
                <?php esc_html_e( 'Region :', 'media-cloud-sync' ); ?> <strong><?php echo esc_textarea($region); ?></strong></a>
            </div>
        <?php
        }
        $private = (int)$wpmcsItem->get_field($attachment_id, 'is_private', 'media_library');
        ?>
            <div class="misc-pub-section misc-pub-provider">
                <?php esc_html_e( 'Access :', 'media-cloud-sync' ); ?> <strong><?php $private ? esc_html_e( 'Private', 'media-cloud-sync' ) : esc_html_e( 'Public', 'media-cloud-sync' ); ?></strong></a>
            </div>
        <?php
	}


    /**
     * Function to get attachment details by ID
     */
    public function ajax_get_attachment_details() {
        $result = array(
            'status'    => false,
            'data'      => array(),
            'exclude'   => false
        );

        if ( ! isset( $_POST['id'] ) ) {
            wp_send_json_success( $result );
		}

		check_ajax_referer( 'get_media_provider_details', '_nonce' );

		$id= intval( sanitize_text_field( $_POST['id'] ) );
        
        // Return if extension not allowed
        $path = get_attached_file( $id );
        if(!Utils::is_extension_available($path)) {
            $result['exclude'] = true;
            wp_send_json_success( $result );
        }

        if (!Utils::is_ok_to_serve($id)) {
            wp_send_json_success( $result );
        }

        $wpmcsItem = Item::instance();

        $item = $wpmcsItem->get($id, 'media_library');

        if (Utils::is_empty($item)) {
            wp_send_json_success( $result );
        }

        $provider = $wpmcsItem->get_field($id, 'provider', 'media_library');
        if($provider){
            $label = Schema::getServiceLabels($provider);
            $item['provider'] = !empty($label) ? $label : $provider;
        }
        $region = $wpmcsItem->get_field($id, 'region', 'media_library');
        if($region){
            $item['region'] = $region;
        }
        $item['private'] = $wpmcsItem->get_field($id, 'private', 'media_library');
        if($item) {
            $result= array(
                'status' => true,
                'data' => $item
            );
        }
        
        wp_send_json_success( $result );
    }

    /**
	 * Takes notice that an attachment is about to be deleted and prepares for it.
	 *
	 * @handles pre_delete_attachment
	 *
	 * @param bool|null $delete Whether to go forward with deletion.
	 *
	 * @return bool|null
	 */
	public function pre_delete_attachment( $delete ) {
		if ( is_null( $delete ) ) {
			$this->deleting_attachment = true;
		}

		return $delete;
	}

	/**
	 * Takes notice that an attachment has been deleted and undoes previous preparations for the event.
	 *
	 * @handles delete_post
	 *
	 * Note: delete_post is used as there is a potential that deleted_post is not reached.
	 */
	public function delete_post() {
		$this->deleting_attachment = false;
	}

    /**
	 * Has WP Core fixed wp_check_filetype when URL has params yet?
	 *
	 * @see https://core.trac.wordpress.org/ticket/30377
	 * @see https://github.com/aaemnnosttv/fix-wp-media-shortcodes-with-params/blob/master/fix-wp-media-shortcodes-with-params.php
	 *
	 * @return bool
	 */
	public static function wp_check_filetype_broken() {
		$normal_file = wp_check_filetype( 'file.mp4', array( 'mp4' => 'video/mp4' ) );
		$querys_file = wp_check_filetype( 'file.mp4?param=1', array( 'mp4' => 'video/mp4' ) );

		return $normal_file !== $querys_file;
	}

    /**
     * Is installed?
     *
     * @return bool
     */
    public static function is_installed(): bool {
        // It is inbuilt in worpress
        return true;
    }


    
    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}