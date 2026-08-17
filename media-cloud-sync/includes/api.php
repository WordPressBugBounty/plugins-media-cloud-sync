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
		$this->add_route( '/bucket/checkExist', 'verifyBucketExist', 'POST' );
		$this->add_route( '/bucket/addNew', 'createBucket', 'POST' );
		$this->add_route( '/permissions/status', 'verifyStatus', 'POST' );
		$this->add_route( '/permissions/write', 'verifyWrite', 'POST' );
		$this->add_route( '/permissions/delete', 'verifyObjectDelete', 'POST' );
		$this->add_route( '/permissions/read', 'verifyRead', 'POST' );

		$this->add_route( '/security/get_security', 'getBucketSecuritySettings', 'POST' );
		$this->add_route( '/security/block_public_access', 'changePublicAccess', 'POST' );
		$this->add_route( '/security/object_ownership_enforce', 'changeObjectOwnership', 'POST' );

		$this->add_route( '/getSettings', 'getSettings' );
		$this->add_route( '/saveConfig', 'saveConfig', 'POST' );
		$this->add_route( '/refreshCounts', 'refreshCounts', 'POST' );
		$this->add_route( '/getErrorLogs', 'getErrorLogs', 'POST' );
		$this->add_route( '/clearErrorLogs', 'clearErrorLogs', 'POST' );

		// Upgrade Database Routes
		$this->add_route( '/upgrade/status', 'getUpgradeStatus' );
		$this->add_route( '/upgrade/start', 'startUpgrade', 'POST' );

		// Sync Routes
		$this->add_route( '/sync/status', 'getStatus' );
		$this->add_route( '/sync/start', 'startSync', 'POST' );
		$this->add_route( '/sync/pause', 'pauseSync', 'POST' );
		$this->add_route( '/sync/resume', 'resumeSync', 'POST' );
		$this->add_route( '/sync/stop', 'stopSync', 'POST' );
		$this->add_route( '/sync/tick', 'tickSync', 'POST' );
		$this->add_route( '/sync/setMethod', 'setSyncMethod', 'POST' );

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
	 * Saved connection status checks (storage, CDN).
	 * @since 1.3.11
	 */
	public function verifyStatus( $data ) {
		$params = $data->get_params();
		$check  = isset( $params['check'] ) ? sanitize_text_field( $params['check'] ) : 'storage';
		return new WP_REST_Response( Service::instance()->verifyStatus( $check ), 200 );
	}

	/**
	 * Verify bucket write permission (configure wizard) or saved storage status (legacy route).
	 * @since 1.3.11
	 */
	public function verifyWrite( $data ) {
		$params = $data->get_params();
		if ( !empty( $params['service'] ) ) {
			return new WP_REST_Response( Service::instance()->verifyObjectWritePermission( $params ), 200 );
		}
		return new WP_REST_Response( Service::instance()->verifyStatus( 'storage' ), 200 );
	}

	/**
	 * Verify the Bucket delete Permission
	 */
	public function verifyObjectDelete( $data ) {
		return new WP_REST_Response( Service::instance()->verifyObjectDeletePermission($data->get_params()), 200 );
	}

	/**
	 * Verify delivery read access (legacy route).
	 * @since 1.3.11
	 */
	public function verifyRead( $data ) {
		return new WP_REST_Response( Service::instance()->verifyStatus( 'cdn' ), 200 );
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
			'credentials'		=> Utils::get_credentials( '', [], true ),
			'serviceEnabled'	=> Utils::is_service_enabled(),
			'serviceError'		=> Utils::get_service_configuration_error(),
			'common'			=> [
				'version'	=> defined('WPMCS_PRO_VERSION') ? WPMCS_PRO_VERSION : WPMCS_VERSION,
				'counts'	=> [
					'all'		=> Counter::get_count(),
					'categorized' => $this->getSortedMediaCounts()
				],
				'status'	=> Utils::get_status('', false),
				'settings'	=> Utils::get_settings(),
			],
			'sync'			=> Sync::instance()->get_status(),
			'license'		=> defined('WPMCS_PRO_VERSION') ? Utils::get_safe_license_data() : [],
			'file_manager'	=> defined('WPMCS_PRO_VERSION') ? [
				'delete_enabled' => (bool) Utils::get_option('delete_enabled', false, Schema::getConstant('FILE_MANAGER_SETTINGS_KEY')),
				'root_folder'    => Utils::get_option('root_folder', '', Schema::getConstant('FILE_MANAGER_SETTINGS_KEY')),
			] : [],
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
		$configSource 	= isset($saveData['configSource']) ? $saveData['configSource'] : 'database';
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
			if(!$updatedSettings) $settingsOk = false;
		}


		if($action === 'all' || $action === 'service'){
			// In 'config' source mode the credentials live in the WPMCS_CONFIG constant,
			// so an empty config payload is expected and must not block the save.
			if ( $service === false || ($configSource !== 'config' && empty($config)) || empty($bucketConfig)) return new WP_REST_Response( $result, 200 );

			$updated = Utils::update_option(
				'credentials', 
				[
					'service' 		=> $service,
					'serviceLabel' 	=> $serviceLabel,
					'cdn'			=> $cdn,
					'config'		=> $configSource === 'config' ? [] : $config,
					'configSource'	=> $configSource,
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
				Utils::set_status('storageCredentials', [
					'status'		=> false,
					'message'		=> '',
					'lastChecked'	=> null
				]);
			}
		} 
		
		if(($action === 'all' || $action === 'settings')) {
			$updatedSettings = Utils::update_option('settings', $settings, Schema::getConstant('GLOBAL_SETTINGS_KEY'));
			if(!$updatedSettings) $settingsOk = false;
		}

		// Clear plugin caches and refresh counts after any configuration save.
		if(in_array($action, ['all', 'service', 'settings', 'cdn'], true)) {
			if($action === 'all' || $action === 'service' || $action === 'settings') {
				Integration::instance()->clear_all_meta(false);
			}

			if($action === 'all' || $action === 'settings') {
				Utils::clear_all_content_meta(false, false);
			}

			Cache::flush_object_cache();
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
	 * Clear all error logs for a sync type (e.g. stale entries from earlier failed attempts).
	 * @since 1.3.13
	 */
	public function clearErrorLogs( $request ) {
		$type = isset($request->get_params()['type']) ? $request->get_params()['type'] : '';

		if (empty($type) || !Sync::instance()->get_class_by_action($type)) {
			return new WP_REST_Response( [
				'success'	=> false,
				'message'	=> esc_html__('Invalid action', 'media-cloud-sync')
			], 200 );
		}

		Logger::instance()->remove_logs_by_type($type);

		$result = [
			'success'	=> true,
			'message'	=> esc_html__('Error logs cleared', 'media-cloud-sync')
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
		if ( ! Sync::instance()->get_class_by_action( $action ) ) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Invalid action', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}
		if(!Utils::is_service_enabled()) {
			$result = [
				'success'	=> false,
				'message'	=> Utils::get_service_configuration_error() ?: esc_html__('Storage is not configured correctly.', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}
		$status = Sync::instance()->start($action);

		// Sync::start() passes a job's own precondition-failure array straight through
		// (e.g. no destination configured) instead of masking it as a started status.
		if (is_array($status) && isset($status['success']) && $status['success'] === false) {
			return new WP_REST_Response( $status, 200 );
		}

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
		if ( ! Sync::instance()->get_class_by_action( $action ) ) {
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
	 * Save the sync method ('ajax'|'cron'|'mixed') preference for a sync type.
	 * @since 1.3.13
	 */
	public function setSyncMethod( $data ) {
		$params = $data->get_params();
		$action = isset($params['action']) ? sanitize_text_field($params['action']) : '';
		$method = isset($params['sync_method']) ? sanitize_text_field($params['sync_method']) : '';

		if ( ! Sync::instance()->get_class_by_action( $action ) ) {
			return new WP_REST_Response( [
				'success'	=> false,
				'message'	=> esc_html__('Invalid action', 'media-cloud-sync')
			], 200 );
		}

		$saved = Sync::instance()->set_sync_method($action, $method);
		if(!$saved) {
			return new WP_REST_Response( [
				'success'	=> false,
				'message'	=> esc_html__('Invalid sync method', 'media-cloud-sync')
			], 200 );
		}

		$result = [
			'success'		=> true,
			'sync_method'	=> $method,
			'status'		=> Sync::instance()->get_class_by_action($action)->get_status(),
		];
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Resume Sync
	 * @since 1.2.13
	 */
	public function resumeSync( $data ) {
		$action = isset($data->get_params()['action']) ? $data->get_params()['action'] : '';
		if ( ! Sync::instance()->get_class_by_action( $action ) ) {
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
		if ( ! Sync::instance()->get_class_by_action( $action ) ) {
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
	 * Tick Sync — process a small batch synchronously, driving Ajax/Mixed sync mode.
	 * @since 1.3.13
	 */
	public function tickSync( $data ) {
		$params = $data->get_params();
		$action = isset($params['action']) ? $params['action'] : '';

		if ( ! Sync::instance()->get_class_by_action( $action ) ) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Invalid action', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}

		// Defense in depth — the frontend should never call this in free/unlicensed mode, but
		// the endpoint must not trust that, and must not silently no-op: an unlicensed call
		// needs a clear rejection so the frontend's tick loop stops instead of retrying forever.
		if ( ! Utils::is_pro_licensed() ) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Ajax sync requires an active Pro license', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}

		// Clamp regardless of what the frontend sends — the endpoint itself must enforce the
		// ceiling, since a batch of slow uploads processed sequentially in one request risks
		// exceeding reverse-proxy/webserver timeouts that process_iterations() can't see.
		$batch = isset($params['batch']) ? (int) $params['batch'] : 5;
		$batch = min(10, max(1, $batch));

		$status = Sync::instance()->tick($action, $batch);

		$result = [
			'success'	=> true,
			'status'	=> $status,
			'message'	=> esc_html__('Sync ticked successfully', 'media-cloud-sync')
		];

		// Deliberately omitted on every tick (this endpoint may be hit ~once/second while a
		// sync runs, and Counter::get_count() is a real query) — but the Dashboard's own
		// Statistics widget reads a *different* redux slice (common.counts) than the Sync
		// screen's progress (sync[action]), only refreshed via the slower ~2-3s useSyncPolling
		// cadence. That's an acceptable few-seconds lag while a sync is actively running, but
		// right when it finishes it becomes a visibly wrong, stuck-looking number on the
		// Dashboard until the next poll happens to catch up. Only pay for the query at that
		// one transition, not on every tick.
		if ( isset( $status['status'] ) && $status['status'] !== 'running' ) {
			$result['counts'] = [
				'all'			=> Counter::get_count(),
				'categorized'	=> $this->getSortedMediaCounts()
			];
		}
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
		if(!$handler) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Invalid action', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}
		if(!method_exists($handler, 'retry_single')) {
			$result = [
				'success'	=> false,
				'message'	=> esc_html__('Retry is not available for this sync type.', 'media-cloud-sync')
			];
			return new WP_REST_Response( $result, 200 );
		}
		$handler->retry_single($id, $source_type);

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
                        $found_prefix = strpos($source_type, $prefix) === 0 ? $prefix : false;
                        if($found_prefix !== false) break;
                    }

                    if($found_prefix !== false) {
                        $sorted_counts[$found_prefix] = [
							'label'	=> $source_labels[$found_prefix],
							'uploaded' => isset( $sorted_counts[ $found_prefix ]['uploaded'] )
                                ? $sorted_counts[ $found_prefix ]['uploaded'] + $counts['uploaded']
                                : $counts['uploaded'],
                            'total' => isset( $sorted_counts[ $found_prefix ]['total'] )
                                ? $sorted_counts[ $found_prefix ]['total'] + $counts['total']
                                : $counts['total']
                        ];
                    }
                }
            }
        }

		return $sorted_counts;
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
