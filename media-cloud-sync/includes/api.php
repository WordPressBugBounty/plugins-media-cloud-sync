<?php
namespace Dudlewebs\WPMCS;

use WP_REST_Response;

defined('ABSPATH') || exit;

class Api {
	private static $instance = null;
	private $token;
	private $version;
	private $assets_url;

	/**
	 * Constructor
     * @since 1.0.0
	 */

	public function __construct() {
		$this->assets_url = WPMCS_ASSETS_URL;
		$this->version    = WPMCS_VERSION;
		$this->token      = WPMCS_TOKEN;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}


	/**
	 * Register API routes
	 */

	public function register_routes() {
		$this->add_route( '/verifyCredentials', 'verifyCredentials', 'POST' );
		$this->add_route( '/permissions/checkExist', 'verifyBucketExist', 'POST' );
		$this->add_route( '/permissions/addNewBucket', 'createBucket', 'POST' );
		$this->add_route( '/permissions/write', 'verifyObjectWrite', 'POST' );
		$this->add_route( '/permissions/delete', 'verifyObjectDelete', 'POST' );
		$this->add_route( '/permissions/read', 'verifyObjectRead', 'POST' );

		$this->add_route( '/security/get_security', 'getBucketSecuritySettings', 'POST' );
		$this->add_route( '/security/block_public_access', 'changePublicAccess', 'POST' );
		$this->add_route( '/security/object_ownership_enforce', 'changeObjectOwnership', 'POST' );

		$this->add_route( '/getSettings', 'getSettings' );
		$this->add_route( '/saveConfig', 'saveConfig', 'POST' );
		$this->add_route( '/refreshCounts', 'refreshCounts', 'POST' );
		$this->add_route( '/getErrorLogs', 'getErrorLogs', 'POST' );

		// Upgrade Database Routes
		$this->add_route( '/upgrade/status', 'getUpgradeStatus' );
		$this->add_route( '/upgrade/start', 'startUpgrade', 'POST' );

		// Sync Routes
		$this->add_route( '/sync/status', 'getStatus' );
		$this->add_route( '/sync/start', 'startSync', 'POST' );
		$this->add_route( '/sync/pause', 'pauseSync', 'POST' );
		$this->add_route( '/sync/resume', 'resumeSync', 'POST' );
		$this->add_route( '/sync/stop', 'stopSync', 'POST' );

		$this->add_route( '/sync/retrySingle', 'retrySingle', 'POST' );
	}

	/**
	 * Verify the Service Credentials
	 */
	public function verifyCredentials( $data ) {
		return new WP_REST_Response( Service::instance()->verifyCredentials($data->get_params()), 200 );
	}

	/**
	 * Verify the Bucket exist
	 */
	public function verifyBucketExist( $data ) {
		return new WP_REST_Response( Service::instance()->verifyBucketExist($data->get_params()), 200 );
	}

	/**
	 * Create the bucket
	 */
	public function createBucket( $data ) {
		return new WP_REST_Response( Service::instance()->createBucket($data->get_params()), 200 );
	}

	
	/**
	 * Verify the Bucket Write Permission
	 */
	public function verifyObjectWrite( $data ) {
		return new WP_REST_Response( Service::instance()->verifyObjectWritePermission($data->get_params()), 200 );
	}

	/**
	 * Verify the Bucket delete Permission
	 */
	public function verifyObjectDelete( $data ) {
		return new WP_REST_Response( Service::instance()->verifyObjectDeletePermission($data->get_params()), 200 );
	}

	/**
	 * Verify the Bucket Read Permission
	 */
	public function verifyObjectRead( $data ) {
		return new WP_REST_Response( Service::instance()->verifyObjectReadPermission(), 200 );
	}

	/**
	 * Get the Security Settings
	 */
	public function getBucketSecuritySettings( $data ) {
		return new WP_REST_Response( Service::instance()->getBucketSecuritySettings($data->get_params()), 200 );
	}


	/**
	 * Change the Public Access
	 */
	public function changePublicAccess( $data ) {
		return new WP_REST_Response( Service::instance()->changePublicAccess($data->get_params()), 200 );
	}


	/**
	 * Change the Object Ownership
	 */
	public function changeObjectOwnership( $data ) {
		return new WP_REST_Response( Service::instance()->changeObjectOwnership($data->get_params()), 200 );
	}

	/**
	 * Get Settings
	 */
	public function getSettings( ) {
		// Fetch and update media counts for make sure they are up to date
        Counter::fetch_and_update();

		$data = [
			'credentials'	=> Utils::get_credentials( '', [], true ),
			'common'		=> [
				'version'	=> defined('WPMCS_PRO_VERSION') ? WPMCS_PRO_VERSION : WPMCS_VERSION,
				'counts'	=> [
					'all'		=> Counter::get_count(),
					'categorized' => $this->getSortedMediaCounts()
				],
				'status'	=> Utils::get_status('', false),
				'settings'	=> Utils::get_settings(),
			],
			'sync'			=> Sync::instance()->get_status(),
		];

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Save the Configurations
	 */
	public function saveConfig( $data ) {
		$saveData 		= $data->get_params();
		$result			= [
			'success'	=> false,
			'message'	=> esc_html__('Something went wrong. Invalid data recieved', 'media-cloud-sync')
		];

		$action 		= isset($saveData['action']) ? $saveData['action'] : false;
		if($action === false) return new WP_REST_Response( $result, 200 );

		$service 		= isset($saveData['service']) ? $saveData['service'] : false;
		$serviceLabel 	= isset($saveData['serviceLabel']) ? $saveData['serviceLabel'] : '';
		$cdn			= isset($saveData['cdn']) ? $saveData['cdn'] : [];
		$config 		= isset($saveData['config']) ? $saveData['config'] : false;
		$bucketConfig 	= isset($saveData['bucketConfig']) ? $saveData['bucketConfig'] : [];
		$security 		= isset($saveData['security']) ? $saveData['security'] : [];
		$settings 		= isset($saveData['settings']) ? $saveData['settings'] : [];

		$serviceOk 		= true;
		$settingsOk 	= true;
		
		if( $action === 'cdn' ){
			$existing = Utils::get_credentials();
			$existing['cdn'] = $cdn;
			
			$updated = Utils::update_option('credentials', $existing, Schema::getConstant('GLOBAL_SETTINGS_KEY'));
			$updatedSettings = Utils::update_option('settings', $settings, Schema::getConstant('GLOBAL_SETTINGS_KEY'));

			if(!$updated) $serviceOk = false;
		}


		if($action === 'all' || $action === 'service'){
			if ( $service === false || empty($config) || empty($bucketConfig)) return new WP_REST_Response( $result, 200 );

			$updated = Utils::update_option(
				'credentials', 
				[
					'service' 		=> $service,
					'serviceLabel' 	=> $serviceLabel,
					'cdn'			=> $cdn,
					'config'		=> $config,
					'bucketConfig'  => $bucketConfig,
					'security'		=> $security
				], 
				Schema::getConstant('GLOBAL_SETTINGS_KEY')
			);
			if(!$updated) $serviceOk = false;

			if($serviceOk) {
				Utils::set_status('cdnRead', [
					'status'		=> false,
					'message'		=> '',
					'lastChecked'	=> null
				]);
			}
		} 
		
		if(($action === 'all' || $action === 'settings')) {
			$updatedSettings = Utils::update_option('settings', $settings, Schema::getConstant('GLOBAL_SETTINGS_KEY'));
			
			// Clear content meta cache if settings updated
			Utils::clear_all_content_meta();
			if(!$updatedSettings) $settingsOk = false;
		}

		// If action is all, service or settings, clear caches and update counts
		if($action === 'all' || $action === 'service' || $action === 'settings') {
			//clear object cache
			Cache::flush_object_cache();
			// clear all meta of attachments
			Integration::instance()->clear_all_meta();
			// clear all content meta
			Utils::clear_all_content_meta();
			// update counts
			Counter::instance()->fetch_and_update();
		}


		if($serviceOk && $settingsOk) {
			$result = [
				'success'	=> true,
				'message'	=> esc_html__('Configuration saved successfully', 'media-cloud-sync')
			];
		}

		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Refresh the Media Counts
	 * 
	 */
	public function refreshCounts( ) {
		Cache::flush_object_cache();
		Integration::instance()->clear_all_meta();
		Counter::instance()->fetch_and_update();

		$result = [
			'success'	=> true,
			'counts'	=> [
				'all'		=> Counter::get_count(),
				'categorized' => $this->getSortedMediaCounts()
			],
			'message'	=> esc_html__('Media counts refreshed successfully', 'media-cloud-sync')
		];
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Start Upgrade
	 * @since 1.3.6
	 */
	public function startUpgrade( $request ) {
		$result = Upgrade::instance()->start_upgrade();
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Get Upgrade Status
	 * @since 1.3.6
	 */	public function getUpgradeStatus( $request ) {
		$result = Upgrade::instance()->get_progress();
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Get Media Error Logs
	 * @since 1.2.13
	 */
	public function getErrorLogs( $request ) {
		$params 	= $request->get_params();
		$type 		= isset($params['type']) ? $params['type'] : 'all';
		$page 		= isset($params['page']) ? $params['page'] : null;
		$per_page 	= isset($params['per_page']) ? $params['per_page'] : null;

		if($type !== 'all') {
			$logs = Logger::instance()->get_logs_by_type($type, $page, $per_page);
		} else {
			$logs = Logger::instance()->get_all_logs($page, $per_page);
		}
		
		$result = [
			'success'	=> true,
			'logs'		=> $logs,
			'message'	=> esc_html__('Error logs fetched successfully', 'media-cloud-sync')
		];
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Get Status
	 * @since 1.2.13
	 */
	public function getStatus( $request ) {
		// Fetch and update media counts for make sure they are up to date
		$action = isset($request->get_params()['action']) ? $request->get_params()['action'] : 'all';
		$status = Sync::instance()->get_status();
		
		if($action !== 'all') {
			$status = isset($status[$action]) ? $status[$action] : [];
		}

		$result = [
			'success'	=> true,
			'status'	=> $status,
			'counts'	=> [
				'all'		=> Counter::get_count(),
				'categorized' => $this->getSortedMediaCounts()
			],
			'message'	=> esc_html__('Status fetched successfully', 'media-cloud-sync')
		];
		
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Start Sync
	 * @since 1.2.13
	 */
	public function startSync( $data ) {
		$action = isset($data->get_params()['action']) ? $data->get_params()['action'] : '';
		if(empty($action)) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Invalid action', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}

		$status = Sync::instance()->start($action);

		$result = [
			'success'	=> true,
			'status'	=> $status,
			'message'	=> esc_html__('Sync started successfully', 'media-cloud-sync')
		];
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Pause Sync
	 * @since 1.2.13
	 */
	public function pauseSync( $data ) {
		$action = isset($data->get_params()['action']) ? $data->get_params()['action'] : '';
		if(empty($action)) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Invalid action', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}

		$status = Sync::instance()->pause($action);

		$result = [
			'success'	=> true,
			'status'	=> $status,
			'message'	=> esc_html__('Sync paused successfully', 'media-cloud-sync')
		];
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Resume Sync
	 * @since 1.2.13
	 */
	public function resumeSync( $data ) {
		$action = isset($data->get_params()['action']) ? $data->get_params()['action'] : '';
		if(empty($action)) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Invalid action', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}

		$status = Sync::instance()->resume($action);

		$result = [
			'success'	=> true,
			'status'	=> $status,
			'message'	=> esc_html__('Sync resumed successfully', 'media-cloud-sync')
		];
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Stop Sync
	 * @since 1.2.13
	 */
	public function stopSync( $data ) {
		$action = isset($data->get_params()['action']) ? $data->get_params()['action'] : '';
		if(empty($action)) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Invalid action', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}

		$status = Sync::instance()->stop($action);

		$result = [
			'success'	=> true,
			'status'	=> $status,
			'counts'	=> [
				'all'		=> Counter::get_count(),
				'categorized' => $this->getSortedMediaCounts()
			],
			'message'	=> esc_html__('Sync stopped successfully', 'media-cloud-sync')
		];
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Retry a Media Sync Error
	 */
	public function retrySingle( $data ) {
		$params 		= $data->get_params();
		$id 			= isset($params['id']) ? $params['id'] : '';
		$source_type 	= isset($params['source_type']) ? $params['source_type'] : 'media_library';
		$type 			= isset($params['type']) ? $params['type'] : '';
		$status			= false;
		if(empty($id) || empty($source_type) || empty($type)) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Invalid parameters', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}

		// Retry Sync
		$handler = Sync::instance()->get_class_by_action($type);
		if($handler) {
			$handler->retry_single($id, $source_type);
		}

		$status = Utils::is_empty(Logger::instance()->get_log($type, $id, $source_type)) ? true : false;

		$result = [
			'success'	=> $status,
			'message'	=> $status ? esc_html__('Media synced successfully', 'media-cloud-sync') : esc_html__('Media sync failed', 'media-cloud-sync')
		];
		return new WP_REST_Response( $result, 200 );
	}


	/**
	 * Get Sorted Media Counts
	 * Sorts media counts by source type
	 * @since 1.2.13
	 */
	private function getSortedMediaCounts() {
		$sorted_counts  = [];
        $detailed_counts = Counter::get_count( 'all', false );
        if(!empty($detailed_counts)) {
            $source_labels = Integration::$source_labels;
            if(!empty($source_labels)) {
                $prefixes = array_keys($source_labels);
                foreach($detailed_counts as $source_type => $counts) {
                    $found_prefix = false;
                    foreach($prefixes as $prefix) {
                        $found_prefix = strpos($source_type, $prefix) === 0 ? $prefix : false;;
                        if($found_prefix !== false) break;
                    }

                    if($found_prefix !== false) {
                        $sorted_counts[$found_prefix] = [
							'label'	=> $source_labels[$found_prefix],
							'uploaded' => isset($sorted_counts[$found_prefix]['data']['uploaded']) 
                                ? $sorted_counts[$found_prefix]['data']['uploaded'] + $counts['uploaded'] 
                                : $counts['uploaded'],
                            'total' => isset($sorted_counts[$found_prefix]['data']['total']) 
                                ? $sorted_counts[$found_prefix]['data']['total'] + $counts['total'] 
                                : $counts['total']
                        ];
                    }
                }
            }
        }

		return $sorted_counts;
	}


    /**
     * Add Custom Mime Type JSON
     * @since 1.0.0
     */
    public function add_custom_mime_type_json($mimes) {
        $mimes['json'] = 'application/json';
        // Return the array back to the function with our added mime type.
        return $mimes;
    }


	/**
	 * Helper function to create Adding Route
	 * @since 1.0.0
	 */
	private function add_route( $slug, $callBack, $method = 'GET' ) {
		register_rest_route(
			$this->token . '/v1',
			$slug,
			array(
				'methods'             => $method,
				'callback'            => array( $this, $callBack ),
				'permission_callback' => array( $this, 'getPermission' ),
			) );
	}

	/**
	 * Permission Callback
	 **/
	public function getPermission() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
     * Ensures only one instance of Class is loaded or can be loaded.
     *
     * @return Api Class instance
     * @since 1.0.0
     * @static
     */
    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
