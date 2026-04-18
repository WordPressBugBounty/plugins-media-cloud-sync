<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class FilterContent {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;


    protected $post_meta_key;
    protected $query_cache = [];

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url   = WPMCS_ASSETS_URL;
        $this->version      = WPMCS_VERSION;
        $this->token        = WPMCS_TOKEN;

        $this->post_meta_key    = Schema::getConstant('CONTENT_META_KEY');

        // Initialize setup
        $this->registerActions();
    }

    /**
     * Register integration access
     */
    public function registerActions() {
        // Posts
		add_action( 'the_post',[ $this, 'filter_post_data' ] );
		add_filter( 'content_pagination',[ $this, 'filter_content_pagination' ] );
		add_filter( 'the_content',[ $this, 'filter_post' ], 100 );
		add_filter( 'the_excerpt',[ $this, 'filter_post' ], 100 );
		add_filter( 'rss_enclosure',[ $this, 'filter_post' ], 100 );
		add_filter( 'content_edit_pre',[ $this, 'filter_post' ] );
		add_filter( 'excerpt_edit_pre',[ $this, 'filter_post' ] );
		add_filter( 'wpmcs_filter_post',[ $this, 'filter_post' ] ); 

		// Post edit screen use Backward compatibility
		add_filter( 'content_save_pre', [ $this, 'filter_post_backward' ] );
		add_filter( 'excerpt_save_pre', [ $this, 'filter_post_backward' ] );
    }


    /**
	 * Filter post data.
	 *
	 * @param WP_Post $post
	 */
	public function filter_post_data( $post ) {
		global $pages;

		$cache    = Utils::get_meta($post->ID, 'items', [], $this->post_meta_key);

		$to_cache = [];

		if ( is_array( $pages ) && 1 === count( $pages ) && ! empty( $pages[0] ) ) {
			// Post already filtered and available on global $page array, continue
			$post->post_content = $pages[0];
		} else {
			$post->post_content = $this->process_content( $post->post_content, $cache, $to_cache );
		}

		$post->post_excerpt = $this->process_content( $post->post_excerpt, $cache, $to_cache );

		$this->maybe_update_post_cache( $to_cache );
	}

	/**
	 * filter post for backward compatibility
	 */
	public function filter_post_backward( $content ) {

		// Enable backword compatibility
		add_filter( 'wpmcs_enable_backward_url_replacement', [ $this, 'enable_backward_url_check' ] );

		$content = $this->filter_post( $content );

		// remove backword compatibility after process
		remove_filter( 'wpmcs_enable_backward_url_replacement', [ $this, 'enable_backward_url_check' ] );

		return $content;
	}


	/**
	 * Filter post.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function filter_post( $content ) {
		if ( empty( $content ) ) {
			// Nothing to filter, continue
			return $content;
		}

		$cache    = $this->get_post_cache();
		$to_cache = array();

		$content  = $this->process_content( $content, $cache, $to_cache );

		$this->maybe_update_post_cache( $to_cache );

		return $content;
	}

	/**
	 * Filter content pagination.
	 *
	 * @param array $pages
	 *
	 * @return array
	 */
	public function filter_content_pagination( $pages ) {
		$cache    = $this->get_post_cache();
		
		$to_cache = array();

		foreach ( $pages as $key => $page ) {
			$pages[ $key ] = $this->process_content( $page, $cache, $to_cache );
		}

		$this->maybe_update_post_cache( $to_cache );

		return $pages;
	}


	/**
	 * may be update the post cache
	 */
	public function maybe_update_post_cache( $to_cache ) {
		$post_id = (int) get_post_field( 'ID', null );

		if ( ! $post_id ) {
			return array();
		}

		$cache    = $this->get_post_cache();

		// Merge the arrays and keep unique key-value pairs
		$to_cache = $to_cache + $cache;

		if($to_cache !== $cache) {
            Utils::update_meta($post_id, 'items', $to_cache, $this->post_meta_key);
        }
	}


	/**
	 * Get post cache
	 *
	 * @param null|int|WP_Post $post           Optional. Post ID or post object. Defaults to current post.
	 * @return array|int
	 */
	public function get_post_cache( $post = null, $transform_ints = true ) {
		$post_id = (int) get_post_field( 'ID', $post );

		if ( ! $post_id ) {
			return array();
		}

		$cache    = Utils::get_meta($post_id, 'items', [], $this->post_meta_key);

		if ( Utils::is_empty( $cache ) ) {
			$cache = array();
		}

		return $cache;
	}


    /**
	 * Process content.
	 *
	 * @param string $content
	 * @param array  $cache
	 * @param array  $to_cache
	 *
	 * @return mixed
	 */
	protected function process_content( $content, $cache, &$to_cache ) {
		if ( empty( $content ) || !Utils::is_ok_to_serve(false, false) ) {
			return $content;
		}

		$content = $this->pre_filter_content( $content );

		// Find URLs from img src
		$url_pairs = $this->get_urls_from_img_src( $content, $to_cache );
		$content   = $this->replace_urls( $content, $url_pairs );

		// Find leftover URLs
		$content = $this->find_urls_and_replace( $content, $cache, $to_cache );

		// Perform post processing if required
		$content = apply_filters('wpmcs_post_process_content', $content);

		return $content;
	}

    /**
	 * Pre replace content.
	 * @since 1.0.0
	 * @param string $content
	 * @return string
	 */
	protected function pre_filter_content( $content ) {
		$uploads  = wp_upload_dir();
		$base_url = Utils::remove_scheme( $uploads['baseurl'] );

		return Utils::remove_query_strings( $content, $base_url );
	}

	/**
	 * Get URLs from img src.
	 *
	 * @param string $content
	 * @param array  $to_cache
	 *
	 * @return array
	 */
	protected function get_urls_from_img_src( $content, &$to_cache ) {
		$url_pairs = array();

		if ( ! is_string( $content ) ) {
			return $url_pairs;
		}

		if ( ! preg_match_all( '/<img [^>]+>/', $content, $matches ) || ! isset( $matches[0] ) ) { // No img tags found
			return $url_pairs;
		}

		$matches      = array_unique( $matches[0] );
		$item_sources = array();

		foreach ( $matches as $image ) {
			if ( ! preg_match( '/wp-image-([0-9]+)/i', $image, $class_id ) || ! isset( $class_id[1] ) ) {
				// Can't determine ID from class, skip
				continue;
			}

			if ( ! preg_match( '/src=\\\?["\']+([^"\'\\\]+)/', $image, $src ) || ! isset( $src[1] ) ) {
				// Can't determine URL, skip
				continue;
			}

			$url = $src[1];

			if ( ! Utils::is_file_url( $url ) ) {
				continue;
			}

			if ( ! self::url_needs_replacing( $url ) ) {
				// URL already correct, skip
				continue;
			}

			$bare_url = Utils::reduce_url( $url );

			$item_sources[ $bare_url ] = [
				'id' 			=> absint( $class_id[1] ),
				'source_type' 	=> 'media_library'
			];
		}

		if ( count( $item_sources ) > 1 ) {
			/*
			 * Warm object cache for use with 'get_post_meta()'.
			 *
			 * To avoid making a database call for each image, a single query
			 * warms the object cache with the meta information for all images.
			 */
			update_meta_cache( 'post', array_unique(array_column($item_sources, 'id')));
		}

		foreach ( $item_sources as $url => $item_source ) {
			if ( ! $this->item_matches_src( $item_source, $url ) ) {
				// Path doesn't match attachment, skip
				continue;
			}

			$this->push_to_url_pairs( $url_pairs, $item_source, $url, $to_cache );
		}

		return $url_pairs;
	}

	/**
	 * Does URL need replacing?
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	public static function url_needs_replacing( $url ) {
		$reverse_compatibility = apply_filters('wpmcs_enable_backward_url_replacement', false);

		if( $reverse_compatibility ) {
			if ( str_replace( Filter::get_bare_upload_base_urls(), '', $url ) !== $url ) {
				// Local URL, no replacement needed.
				return false;
			}

			if ( str_replace( Filter::get_remote_domains(), '', $url ) === $url ) {
				// Not a known remote URL, no replacement needed.
				return false;
			}
		} else {
			if ( str_replace( Filter::get_bare_upload_base_urls(), '', $url ) === $url ) {
				// Remote URL, no replacement needed
				return false;
			}
		}

		// Server URL, perform replacement
		return true;
	}

	/**
	 * Does attachment ID match src?
	 *
	 * @param array  $item_source
	 * @param string $url
	 *
	 * @return bool
	 */
	public function item_matches_src( $item_source, $url ): bool {
		$upload_dir = wp_get_upload_dir();
		$wpmcsItem  = Item::instance();

		$backward_replacement = apply_filters('wpmcs_enable_backward_url_replacement', false);

		if (
			$this->is_empty_item_source($item_source) ||
			'media_library' !== $item_source['source_type'] ||
			get_post_type( $item_source['id'] ) !== 'attachment'
		) {
			return false;
		}

		$item     = $wpmcsItem->get( $item_source['id'], $item_source['source_type'] );

		if(!$item) return false;

		$bare_url = [];
	
		$bare_url[] = $backward_replacement 
							? Utils::reduce_url($wpmcsItem->get_url( $item_source['id'], 'full', $item_source['source_type'] )) 
							: Utils::reduce_url(trailingslashit($upload_dir['baseurl']).$item['source_path']);	
		
	

		if(isset($item['original_source_path']) && !Utils::is_empty($item['original_source_path'])){
			$bare_url[] = $backward_replacement 
							? Utils::reduce_url($wpmcsItem->get_url( $item_source['id'], 'original', $item_source['source_type'] ))
							: Utils::reduce_url(trailingslashit($upload_dir['baseurl']).$item['original_source_path']);
		}

		$extras = $wpmcsItem->get_extras( $item_source['id'], false, $item_source['source_type'] );

		if(isset($extras['sizes']) && !Utils::is_empty($extras['sizes'])) {
			foreach($extras['sizes'] as $size => $sub_image) {
				if(!Utils::is_empty($sub_image)){
					$bare_url[] = $backward_replacement 
									? Utils::reduce_url($wpmcsItem->get_url( $item_source['id'], $size, $item_source['source_type'] ))
									: Utils::reduce_url(trailingslashit($upload_dir['baseurl']).$sub_image['source_path']);
				}
			}
		}

		if ( in_array( $url, $bare_url ) ) {
			// Match found, return true
			return true;
		}

		return false;
	}


	/**
	 * Push to URL pairs.
	 *
	 * @param array  $url_pairs
	 * @param array  $item_source
	 * @param string $find
	 * @param array  $to_cache
	 */
	protected function push_to_url_pairs( &$url_pairs, $item_source, $find, &$to_cache ) {
		$upload_dir = wp_get_upload_dir();
		$wpmcsItem  = Item::instance();
		$item 		= $wpmcsItem->get( $item_source['id'], $item_source['source_type'] );

		if (
			Utils::is_empty($item) ||
			get_post_type( $item_source['id'] ) !== 'attachment'
		) {
			return false;
		}

		$base 					= trailingslashit($upload_dir['baseurl']);
		$backward_replacement 	= apply_filters('wpmcs_enable_backward_url_replacement', false);

		$source_server_file				= Utils::remove_scheme($base.$item['source_path']);
		$source_cloud_file				= Utils::remove_scheme($wpmcsItem->get_url($item_source['id'], 'full', $item_source['source_type']));

		$url_pairs[$source_server_file] = $source_cloud_file;
		if($backward_replacement) {
			$url_pairs[$source_cloud_file] = $source_server_file; // Backward compatibility
		}


		if(
			isset($item['original_source_path']) && 
			!Utils::is_empty($item['original_source_path'])
		) {
			$original_server_file 				= Utils::remove_scheme($base.$item['original_source_path']);
			$original_cloud_file 				= Utils::remove_scheme($wpmcsItem->get_url($item_source['id'], 'original', $item_source['source_type']));
			$url_pairs[$original_server_file] 	= $original_cloud_file;
			if($backward_replacement) {
				$url_pairs[$original_cloud_file] = $original_server_file; // Backward compatibility
			}
		}

		$extras = $wpmcsItem->get_extras($item_source['id'], false, $item_source['source_type']);

		if(isset($extras['sizes'])){
			foreach ($extras['sizes'] as $size => $sub_image) {
				if(Utils::is_empty($sub_image['source_path'])) continue;
				
				$sub_server_file 				= Utils::remove_scheme($base.$sub_image['source_path']);
				$sub_cloud_file 				= Utils::remove_scheme($wpmcsItem->get_url( $item_source['id'], $size, $item_source['source_type'] ));
				$url_pairs[$sub_server_file] 	= $sub_cloud_file;
				if($backward_replacement) {
					$url_pairs[$sub_cloud_file ] = $sub_server_file; // Backward compatibility
				}
			}
		}

		$to_cache[ $find ] = $item_source;
	}


	/**
	 * Replace URLs.
	 *
	 * @param string $content
	 * @param array  $url_pairs
	 *
	 * @return string
	 */
	protected function replace_urls( $content, $url_pairs ) {
		if ( empty( $url_pairs ) ) {
			// No URLs to replace return
			return $content;
		}

		foreach ( $url_pairs as $find => $replace ) {
			$content = apply_filters('wpmcs_before_url_replace', $content, $find, $replace );
			$content = str_replace( $find, $replace, $content );
			$content = apply_filters('wpmcs_after_url_replace', $content, $find, $replace );
		}

		return $content;
	}

	/**
	 * Find URLs and replace.
	 *
	 * @param string $value
	 * @param array  $cache
	 * @param array  $to_cache
	 *
	 * @return string
	 */
	protected function find_urls_and_replace( $value, $cache, &$to_cache ) {
		if ( !Utils::is_ok_to_serve(false, false) ) {
			return $value;
		}

		$url_pairs = $this->get_urls_from_content( $value, $cache, $to_cache );
		$value     = $this->replace_urls( $value, $url_pairs );

		return $value;
	}

	/**
	 * Get URLs from content.
	 *
	 * @param string $content
	 * @param array  $cache
	 * @param array  $to_cache
	 *
	 * @return array
	 */
	protected function get_urls_from_content( $content, $cache, &$to_cache ) {
		$url_pairs = array();

		if ( ! is_string( $content ) ) {
			return $url_pairs;
		}

		if ( ! preg_match_all( '/(http|https)?:?\/\/[^"\'\s<>()\\\]*/', $content, $matches ) || ! isset( $matches[0] ) ) {
			// No URLs found, return
			return $url_pairs;
		}

		$matches = array_unique( $matches[0] );
		$urls    = array();

		foreach ( $matches as $url ) {
			$url = preg_replace( '/[^a-zA-Z0-9]$/', '', $url );

			if ( ! Utils::is_file_url( $url ) ) {
				continue;
			}

			if ( ! self::url_needs_replacing( $url ) ) {
				// URL already correct, skip
				continue;
			}

			$item_source = null;
			$bare_url    = Utils::reduce_url( $url );

			// If attachment ID recently or previously cached, skip full search.
			if ( isset( $to_cache[ $bare_url ] ) ) {
				$item_source = $to_cache[ $bare_url ];
			} elseif ( isset( $cache[ $bare_url ] ) && is_array($cache[ $bare_url ]) ) {
				$item_source = $cache[ $bare_url ];
			}

			if ( is_null( $item_source ) ) {
				// Attachment ID not cached, need to search by URL.
				$urls[] = $bare_url;
			} else {
				$this->push_to_url_pairs( $url_pairs, $item_source, $bare_url, $to_cache );
			}
		}

		if ( ! empty( $urls ) ) {
			$item_sources = $this->get_item_sources_from_urls( $urls );

			foreach ( $item_sources as $url => $item_source ) {
				if ( ! $item_source ) {
					continue;
				}

				$this->push_to_url_pairs( $url_pairs, $item_source, $url, $to_cache );
			}
		}

		return $url_pairs;
	}

	/**
	 * Get item source descriptors from URLs.
	 *
	 * @param array $urls
	 *
	 * @return array url => item source descriptor array (or false)
	 */
	protected function get_item_sources_from_urls( $urls ) {
		$results = array();

		if ( empty( $urls ) ) {
			return $results;
		}
		// Check if backward compatibility is enabled
		$reverse_compatibility = apply_filters('wpmcs_enable_backward_url_replacement', false);
		
		if ( ! is_array( $urls ) ) {
			$urls = array( $urls );
		}
		
		$wpmcsItem = Item::instance();

		$query_set = array();
		$paths     = array();
		$full_urls = array();

		// Quickly parse given URLs to add versions without size as we should lookup with size info first as that could be the "full" size.
		foreach ( $urls as $url ) {
			$query_set[]  = $url;
			$size_removed = Utils::remove_size_from_filename( $url );

			if ( $url !== $size_removed ) {
				$query_set[] = $size_removed;
			}
		}

		foreach ( $query_set as $url ) {
			// Path to search for in query set should be based on bare URL.
			$bare_url = Utils::remove_scheme( $url );
			// There can be multiple URLs in the query set that belong to the same full URL for the Media Library item.
			$full_url = Utils::remove_size_from_filename( $bare_url );

			if ( isset( $this->query_cache[ $full_url ] ) ) {
				// ID already cached, use it.
				$results[ $url ] = $this->query_cache[ $full_url ];

				continue;
			}


			$path = Utils::get_attachment_source_path( $full_url, $reverse_compatibility ? 'key' : 'source' );

			$paths[ $path ]           = $full_url;
			$full_urls[ $full_url ][] = $url;
		}

		if ( ! empty( $paths ) ) {
			if($reverse_compatibility) {
				// For backward compatibility, search inside 'key' and 'original_key' columns.
				$wpmcsItems = $wpmcsItem->get_items_by_paths( array_keys( $paths ), true, false, 'key' );
			} else {
				$wpmcsItems = $wpmcsItem->get_items_by_paths( array_keys( $paths ) );
			}

			if ( ! empty( $wpmcsItems ) ) {
				foreach ( $wpmcsItems as $item ) {
					// Each returned item may have matched on either the source_path or original_source_path.
					// Because the base image file name of a thumbnail might match the primary rather scaled or rotated full image
					// it's possible that both source paths are used by separate URLs.
					$source_id		= (int)$item['source_id'];
					$source_type	= $item['source_type'];
					$source_paths 	= [ $item['source_path'] ];

					// If original_source_path is set, add it to source_paths.
					if(isset($item['original_source_path']) && !Utils::is_empty($item['original_source_path'])) {
						$source_paths[] = $item['original_source_path'];
					}

					foreach ( $source_paths as $source_path ) {
						if ( ! empty( $paths[ $source_path ] ) ) {
							$matched_full_url = $paths[ $source_path ];

							if ( ! empty( $full_urls[ $matched_full_url ] ) ) {
								$item_source = [
									'id' 			=> $source_id,
									'source_type' 	=> $source_type
								];

								$this->query_cache[ $matched_full_url ] = $item_source;

								foreach ( $full_urls[ $matched_full_url ] as $url ) {
									$results[ $url ] = $item_source;
								}
								unset( $full_urls[ $matched_full_url ] );
							}
						}
					}
				}
			}

			// No more item IDs found, set remaining results as false.
			if ( count( $query_set ) !== count( $results ) ) {
				foreach ( $full_urls as $full_url => $schema_urls ) {
					foreach ( $schema_urls as $url ) {
						if ( ! array_key_exists( $url, $results ) ) {
							$this->query_cache[ $full_url ] = false;
							$results[ $url ]                = false;
						}
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Is the supplied item_source considered to be empty?
	 *
	 * @param array $item_source
	 *
	 * @return bool
	 */
	public function is_empty_item_source( $item_source ) {
		if (
			empty( $item_source['source_type'] ) ||
			! isset( $item_source['id'] ) ||
			! is_numeric( $item_source['id'] ) ||
			$item_source['id'] < 0
		) {
			return true;
		}

		return false;
	}



	/**
	 *  Define the method for the backword compatibility filter
	 *  */
    public function enable_backward_url_check( $enable ) {
        return true;
    }

    
    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}