<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

use WP_CLI;

class Compatibility {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;
    /**
     * Whether to wait for the attachment metadata to be regenerated
     * before re-offloading the file.
     *
     * @var bool
     */
    public $wait_for_generate_attachment_metadata = false;

    /**
	 * Files to remove that were restored
	 * @var array
	 */
	private $restored_files = array();

    /**
     * Compatibility constructor.
     * @since 1.0.0
     */
    private function __construct() {
        $this->assets_url = WPMCS_ASSETS_URL;
        $this->version = WPMCS_VERSION;
        $this->token = WPMCS_TOKEN;

        // Initialize setup
        $this->init();

    }

    public function init() {
        /*
         * Image Editor Handler
         * /wp-admin/includes/image-edit.php
         */
        add_filter( 'wpmcs_get_attached_file_noop', array( $this, 'image_editor_download_file' ), 10, 4 );
		add_filter( 'wpmcs_get_attached_file', array( $this, 'image_editor_download_file' ), 10, 4 );
        add_filter( 'wpmcs_get_attached_file', array( $this, 'customizer_crop_download_file' ), 10, 4 );
        add_filter( 'wpmcs_pre_update_item_additional_files_to_remove_from_server', [ $this, 'customizer_crop_remove_restored_files' ], 10, 5 );

        /*
         * WP_Customize_Control
         * /wp-includes/class-wp-customize_control.php
         */
        add_filter('attachment_url_to_postid', [$this, 'customizer_background_image'], 10, 2);

        /*
         * Legacy filter
         * 'wpmcs_get_attached_file_copy_back_to_server'
         */
        add_filter('wpmcs_get_attached_file', [$this, 'legacy_copy_back_to_server'], 10, 4);

        /*
         * Regenerate Thumbnails (before v3)
         * https://wordpress.org/plugins/regenerate-thumbnails/
         */
        add_filter('wpmcs_get_attached_file', [$this, 'regenerate_thumbnails_download_file'], 10, 4);

        /**
         * Regenerate Thumbnails v3+ and other REST-API using plugins that need a server file.
         */
        add_filter('rest_dispatch_request', [$this, 'rest_dispatch_request_copy_back_to_server'], 10, 4);
        add_filter('rest_request_after_callbacks', [$this, 'rest_request_after_callbacks_remove_from_server'], 10, 3);
		add_filter('wpmcs_wait_for_generate_attachment_metadata', array( $this, 'wait_for_generate_attachment_metadata' ) );

        /*
         * WP-CLI Compatibility
         */
        if (defined('WP_CLI') && class_exists('WP_CLI')) {
            WP_CLI::add_hook('before_invoke:media regenerate', [$this, 'enable_copy_back_and_wait_for_generate_metadata']);
        }
    }


    /**
	 * Allow the WordPress Customizer to crop images that have been copied to bucket
	 * but removed from the local server, by copying them back temporarily.
	 *
	 * @param string             $url
	 * @param string             $file
	 * @param int                $attachment_id
	 * @param array              $item
	 *
	 * @return string
	 */
	public function customizer_crop_download_file( $url, $file, $attachment_id, $item ) {
		if ( false === $this->is_customizer_crop_action() ) {
			return $url;
		}

        // Check if file was restored already and return the URL
        // Avoid removed files being copied back multiple times
        if(in_array($file, $this->restored_files)) {
            return $url;
        }
		
		if ( ( $file = $this->copy_provider_file_to_server( $attachment_id, $file ) ) ) {
			// Return the file if successfully downloaded from bucket.
			return $file;
		}

		return $url;
	}

    /**
	 * Generic check for Customizer crop actions
	 *
	 * @return bool
	 */
	public function is_customizer_crop_action() {
		$header_crop = $this->maybe_process_on_action( 'custom-header-crop', true );

		$context    = array( 'site-icon', 'custom_logo' );
		$image_crop = $this->maybe_process_on_action( 'crop-image', true, $context );

		if ( ! $header_crop && ! $image_crop ) {
			// Not doing a Customizer action
			return false;
		}

		return true;
	}

    /**
     * Additional filter to remove any restored files from the server
     * during a Customizer crop action.
     * @param array  $files_to_remove
     * @param int    $source_id
     * @param array  $data
     * @param string $source_type
     * @return array
     */
    public function customizer_crop_remove_restored_files($files_to_remove, $source_id, $new_item, $old_item=[], $source_type = 'media_library') {
        $upload_dir = wp_get_upload_dir();
        
        if (false === $this->is_customizer_crop_action()) {
            return $files_to_remove;
        }

        if (isset($old_item['source_path']) && $old_item['source_path'] !== $new_item['source_path']) {
            // The file has changed, so we need to remove the old file from the server
            $files_to_remove[] = trailingslashit( $upload_dir['basedir'] ) . $old_item['source_path'];
        } 
        
        if( isset($old_item['original_source_path']) && !empty($old_item['original_source_path']) ) {
            $files_to_remove[] = trailingslashit( $upload_dir['basedir'] ) . $old_item['original_source_path'];
        }

        return array_merge($files_to_remove, $this->restored_files);
    }


    /**
	 * Allow the WordPress Image Editor to edit files that have been copied to provider
	 * but removed from the local server, by copying them back temporarily
	 *
	 * @param string             $url
	 * @param string             $file
	 * @param int                $attachment_id
	 * @param array              $item    
	 *
	 * @return string
	 */
	public function image_editor_download_file($url, $file, $attachment_id, $item) {
        // If this filter expects a file path, $url is a URL or a file path
        if (!Utils::is_ajax()) {
            return $url;
        }

        $action = Utils::filter_input('action', INPUT_GET) ?: Utils::filter_input('action', INPUT_POST);
        $do     = Utils::filter_input('do', INPUT_POST);

        // Avoid multiple rewrites when restoring/saving.
        if ($action === 'image-editor' && in_array($do, ['restore', 'save'], true)) {
            return $file; // Return the file if image editor is doing a restore or save
        }

        // Copy back only once during a save triggered by the image editor.
        if ($do === 'save' && in_array($action, ['image-editor', 'imgedit-preview'], true)) {
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15) as $caller) {
                if (!empty($caller['function']) && $caller['function'] === '_load_image_to_edit_path') {
                    $provider_file = $this->copy_provider_file_to_server($attachment_id, $file);
                    return $provider_file ?: $url;
                }
            }
        }

        return $url;
}




    /**
     * Called after REST API callback, and removes server files from the server for routes that need it.
     *
     * @param WP_HTTP_Response $response The response object.
     * @param WP_REST_Server   $handler  The response handler.
     * @param WP_REST_Request  $request  The current request.
     *
     * @return WP_HTTP_Response The filtered response object.
     */
    public function rest_request_after_callbacks_remove_from_server($response, $handler, $request) {
        $routes = [
            '/regenerate-thumbnails/v\d+/regenerate/',
        ];

        // Get the current REST route
        $route = $request->get_route();

        // Apply filter so devs can modify routes
        $routes = apply_filters('wpmcs_rest_api_enable_get_attached_file_remove_from_server', $routes);
        $routes = is_array($routes) ? $routes : (array) $routes;

        if (!empty($routes)) {
            foreach ($routes as $match_route) {
                if (preg_match('@' . $match_route . '@i', $route)) {
                    if ($request->get_param('id')) {
                        $attachment_id = absint($request->get_param('id'));
                        // Remove the file from the server
                        Item::instance()->may_be_delete_server_files_by_id($attachment_id, 'media_library', true, true);
                    }
                    break;
                }
            }
        }

        return $response;
    }

    /**
     * Filters the REST dispatch request to determine whether route needs compatibility actions.
     *
     * @param bool            $dispatch_result Dispatch result, will be used if not empty.
     * @param WP_REST_Request $request         Request used to generate the response.
     * @param string          $route           Route matched for the request.
     * @param array           $handler         Route handler used for the request.
     *
     * @return bool
     */
    public function rest_dispatch_request_copy_back_to_server($dispatch_result, $request, $route, $handler) {
        $routes = [
            '/regenerate-thumbnails/v\d+/regenerate/',
        ];

        $routes = apply_filters('wpmcs_rest_api_enable_get_attached_file_copy_back_to_server', $routes);
        $routes = is_array($routes) ? $routes : (array)$routes;

        if (!empty($routes)) {
            foreach ($routes as $match_route) {
                if (preg_match('@' . $match_route . '@i', $route)) {
                    $this->enable_copy_back_and_wait_for_generate_metadata();
                    break;
                }
            }
        }

        return $dispatch_result;
    }

    /**
     * Enable copying back attachments from provider
     * and waiting for their metadata to be regenerated
     * before re-offloading.
     *
     * @handles WP_CLI:before_invoke:media regenerate
     */
    public function enable_copy_back_and_wait_for_generate_metadata() {
        add_filter('wpmcs_get_attached_file_copy_back_to_server', '__return_true');
        $this->enable_get_attached_file_copy_back_to_server();
        $this->wait_for_generate_attachment_metadata = true;
    }

    /**
	 * Enables copying missing local files back to the server when `get_attached_file` filter is called.
	 */
	public function enable_get_attached_file_copy_back_to_server() {
        add_filter('wpmcs_get_attached_file_copy_back_to_server', '__return_true');

		add_filter( 'wp_generate_attachment_metadata', array( $this, 'wp_generate_attachment_metadata' ) );
	}

    /**
	 * Handler for wp_generate_attachment_metadata. Updates class
	 * member variable when the filter has fired.
	 *
	 * @handles wp_generate_attachment_metadata
	 *
	 * @param mixed $metadata
	 *
	 * @return mixed
	 */
	public function wp_generate_attachment_metadata( $metadata ) {
		$this->wait_for_generate_attachment_metadata = false;

		return $metadata;
	}

    /**
	 * Are we waiting for the wp_generate_attachment_metadata filter and
	 * if so, has it run yet?
	 *
	 * @handles wpmcs_wait_for_generate_attachment_metadata
	 *
	 * @param bool $wait
	 *
	 * @return bool
	 */
	public function wait_for_generate_attachment_metadata( $wait ) {
		if ( $this->wait_for_generate_attachment_metadata ) {
			return true;
		}

		return $wait;
	}

    /**
     * Allow the Regenerate Thumbnails plugin to copy the bucket file back to the server
     * server when the file is missing on the server via get_attached_file.
     *
     * @param string $url
     * @param string $file
     * @param int    $attachment_id
     *
     * @return string
     */
    public function regenerate_thumbnails_download_file($url, $file, $attachment_id, $item) {
        return $this->copy_image_to_server_on_action('regeneratethumbnail', true, $url, $file, $attachment_id);
    }

    /**
     * Allow any process to trigger the copy back to server with
     * the filter 'wpmcs_get_attached_file_copy_back_to_server'
     *
     * @param string $url
     * @param string $file
     * @param int    $attachment_id
     *
     * @return string
     */
    public function legacy_copy_back_to_server($url, $file, $attachment_id, $wpmcs_item) {
        $copy_back_to_server = apply_filters('wpmcs_get_attached_file_copy_back_to_server', false, $file, $attachment_id, $wpmcs_item);
        if (false === $copy_back_to_server) {
            // Not copying back file
            return $url;
        }

        if (($file = $this->copy_provider_file_to_server($attachment_id, $file))) {
            // Return the file if successfully downloaded from S3
            return $file;
        }

        // Return S3 URL as a fallback
        return $url;
    }

    /**
     * Show the correct background image in the customizer
     *
     * @param int|null $post_id
     * @param string   $url
     *
     * @return int|null
     */
    public function customizer_background_image($post_id, $url) {
        if (!is_null($post_id)) {
            return $post_id;
        }

        // There seems to be a bug in the WP Customizer whereby sometimes it puts the attachment ID on the URL.
        if (is_numeric($url)) {
            $item = Item::instance()->get($url);

            // If we found an offloaded Media Library item for that ID, job's a good'n'.
            if (!Utils::is_empty($item)) {
                $post_id = $url;
            }
        } else {
            $path = Utils::get_attachment_source_path($url);
            if (!Utils::is_empty($path)) {
                $item = Item::instance()->get_items_by_paths($path);
                if (!Utils::is_empty($item)) {
                    // If we found an offloaded Media Library item for that path, job's a good'n'.
                    $post_id = $item['source_id'];
                }
            }
        }

        // Must return null if not found.
        return empty($post_id) ? null : $post_id;
    }

    /**
     * Check the current request is a specific one based on action and
     * optional context
     *
     * @param string            $action_key
     * @param bool              $ajax
     * @param null|string|array $context_key
     *
     * @return bool
     */
    public function maybe_process_on_action($action_key, $ajax, $context_key = null) {
        if ($ajax !== Utils::is_ajax()) {
            return false;
        }

        $var_type = 'GET';

        if (isset($_GET['action'])) {
            $action = Utils::filter_input('action');
        } elseif (isset($_POST['action'])) {
            $var_type = 'POST';
            $action = Utils::filter_input('action', INPUT_POST);
        } else {
            return false;
        }

        $context_check = true;
        if (!is_null($context_key)) {
            $global = constant('INPUT_' . $var_type);
            $context = Utils::filter_input('context', $global);

            if (is_array($context_key)) {
                $context_check = in_array($context, $context_key);
            } else {
                $context_check = ($context_key === $context);
            }
        }

        return ($action_key === sanitize_key($action) && $context_check);
    }

    /**
     * Generic method for copying back an S3 file to the server on a specific AJAX action
     *
     * @return string
     */
    public function copy_image_to_server_on_action($action_key, $ajax, $url, $file, $attachment_id) {
        if (false === $this->maybe_process_on_action($action_key, $ajax)) {
            return $url;
        }

        if (($file = $this->copy_provider_file_to_server($attachment_id, $file))) {
            // Return the file if successfully downloaded from S3
            return $file;
        }

        return $url;
    }

    /**
     * Download a file from bucket if the file does not exist serverly and places it where
     * the attachment's file should be.
     *
     * @return string|bool File if downloaded, false on failure
     */
    private function copy_provider_file_to_server($attachment_id, $file) {
        // Download files
        if (!Item::instance()->moveToServerBySourcePath($attachment_id, $file, 'media_library')) {
            return false;
        } 

        $this->restored_files[] = $file;

        return $file;
    }

    /**
     * Get the singleton instance of the Compatibility class.
     *
     * @return Compatibility
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton'); }
}
