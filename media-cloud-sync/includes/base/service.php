<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Service {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;
    private $service    = false;
    private $providers  = [
        's3'            => ['class' => 'S3',            'sdk' => 's3'],
        'gcloud'        => ['class' => 'GCloud',        'sdk' => 'google'],
        'docean'        => ['class' => 'DOcean',        'sdk' => 's3'],
        'cloudflareR2'  => ['class' => 'CloudflareR2',  'sdk' => 's3'],
        's3compatible'  => ['class' => 'S3Compatible',  'sdk' => 's3'],
    ];

    protected $settings;


    /**
     * Service constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url = WPMCS_ASSETS_URL;
        $this->version    = WPMCS_VERSION;
        $this->token      = WPMCS_TOKEN;

        $this->settings = Utils::get_settings();

        $current_service = Utils::get_service();

        if($current_service) {
            $this->service = $this->get_handler_class($current_service);
        }
    }

    /**
     * Verify Service Credentials
     * @since 1.0.0
     */
    public function verifyCredentials($data) {
        $result = [
            'success' => false,
            'message' => esc_html__('Something went wrong', 'media-cloud-sync')
        ];
        
        $service = isset($data['service'])? $data['service'] : false;
        $handler_class = $this->get_handler_class($service);

        if($service == false || $handler_class == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('No service selected', 'media-cloud-sync')
            ];
            return $result;
        }

        $configSource = isset($data['configSource']) ? $data['configSource'] : 'database';

        if($configSource === 'config') {
            // Credentials are expected from the WPMCS_CONFIG constant in wp-config.php.
            if(!Utils::is_wp_config_credentials_defined()) {
                return [
                    'success' => false,
                    'message' => esc_html__('WPMCS_CONFIG is not defined in wp-config.php', 'media-cloud-sync')
                ];
            }

            $config  = Utils::get_wp_config_credentials();
            $missing = [];
            foreach(self::get_required_config_keys($service) as $key) {
                if(!isset($config[$key]) || $config[$key] === '') {
                    $missing[] = $key;
                }
            }
            if(!empty($missing)) {
                return [
                    'success' => false,
                    /* translators: %s: comma separated list of missing configuration keys */
                    'message' => sprintf(esc_html__('WPMCS_CONFIG is missing key(s): %s', 'media-cloud-sync'), implode(', ', $missing))
                ];
            }
        } else {
            $config = $this->resolve_config($data);
        }

        return $handler_class->verifyCredentials($config);
    }

    /**
     * Resolve the credential config for a wizard request.
     * When the request indicates the 'config' source, credentials are pulled
     * from the WPMCS_CONFIG constant (wp-config.php) instead of the payload.
     * @since 1.3.11
     * @param array $data
     * @return array
     */
    private function resolve_config($data) {
        $configSource = isset($data['configSource']) ? $data['configSource'] : 'database';
        if($configSource === 'config') {
            return Utils::get_wp_config_credentials();
        }
        return isset($data['config']) ? $data['config'] : [];
    }

    /**
     * Required configuration keys per provider, used to validate the
     * WPMCS_CONFIG constant before attempting credential verification.
     * @since 1.3.11
     * @param string $service
     * @return array
     */
    public static function get_required_config_keys($service) {
        $required = [
            's3'           => ['access_key', 'secret_key', 'region'],
            'gcloud'       => ['config_json'],
            'docean'       => ['access_key', 'secret_key', 'region'],
            'cloudflareR2' => ['account_id', 'access_key', 'secret_key'],
            's3compatible' => ['endpoint', 'access_key', 'secret_key'],
        ];
        return isset($required[$service]) ? $required[$service] : [];
    }

    /**
     * Persist a connection status result and normalize the response payload.
     * @since 1.3.11
     * @param string $status_key
     * @param array  $result
     * @return array
     */
    private function persist_connection_status($status_key, $result) {
        $lastChecked = isset($result['lastChecked']) ? $result['lastChecked'] : time();
        $success     = !empty($result['success']);
        $message     = isset($result['message']) ? $result['message'] : '';

        Utils::set_status($status_key, [
            'status'      => $success,
            'message'     => $message,
            'lastChecked' => $lastChecked,
        ]);

        return [
            'success'     => $success,
            'message'     => $message,
            'lastChecked' => $lastChecked,
        ];
    }

    /**
     * Verify saved storage credentials and bucket write access.
     * @since 1.3.11
     * @return array
     */
    private function run_storage_status_check() {
        if(!Utils::is_service_enabled()) {
            return $this->persist_connection_status('storageCredentials', [
                'success'     => false,
                'message'     => Utils::get_service_configuration_error(),
                'lastChecked' => time(),
            ]);
        }

        $credentials = Utils::get_credentials();
        $data = [
            'service'      => isset($credentials['service']) ? $credentials['service'] : Utils::get_service(),
            'configSource' => Utils::get_credentials_source(),
            'config'       => isset($credentials['config']) ? $credentials['config'] : [],
            'bucketData'   => [
                'config' => isset($credentials['bucketConfig']) ? $credentials['bucketConfig'] : [],
            ],
        ];

        $result = $this->verifyObjectWritePermission($data);

        return $this->persist_connection_status('storageCredentials', $result);
    }

    /**
     * Verify CDN / delivery read access using saved credentials.
     * @since 1.3.11
     * @return array
     */
    private function run_cdn_status_check() {
        if(!Utils::is_service_enabled()) {
            return $this->persist_connection_status('cdnRead', [
                'success'     => false,
                'message'     => Utils::get_service_configuration_error(),
                'lastChecked' => time(),
            ]);
        }

        if(!$this->service) {
            return $this->persist_connection_status('cdnRead', [
                'success'     => false,
                'message'     => esc_html__('No service selected', 'media-cloud-sync'),
                'lastChecked' => time(),
            ]);
        }

        return $this->persist_connection_status('cdnRead', $this->service->verifyObjectReadPermission());
    }

    /**
     * Run one or more saved-connection status checks.
     * @since 1.3.11
     * @param string $check storage|cdn|all
     * @return array
     */
    public function verifyStatus($check = 'storage') {
        $check = is_string($check) ? strtolower($check) : 'storage';

        if($check === 'write') {
            $check = 'storage';
        } elseif($check === 'read') {
            $check = 'cdn';
        }

        if($check === 'all') {
            $storage = $this->run_storage_status_check();
            $cdn     = $this->run_cdn_status_check();

            return [
                'success' => !empty($storage['success']) && !empty($cdn['success']),
                'checks'  => [
                    'storageCredentials' => $storage,
                    'cdnRead'              => $cdn,
                ],
            ];
        }

        if($check === 'cdn') {
            return $this->run_cdn_status_check();
        }

        if($check !== 'storage') {
            return [
                'success' => false,
                'message' => esc_html__('Invalid status check type', 'media-cloud-sync'),
            ];
        }

        return $this->run_storage_status_check();
    }

    /**
     * @deprecated 1.3.11 Use verifyStatus( 'storage' ).
     */
    public function verifyWrite() {
        return $this->verifyStatus('storage');
    }

    /**
     * @deprecated 1.3.11 Use verifyStatus( 'cdn' ).
     */
    public function verifyRead() {
        return $this->verifyStatus('cdn');
    }

    /**
     * Verify Bucket Exist
     * @since 1.0.0
     */
    public function verifyBucketExist($data) {
        $result = [
            'success' => false,
            'message' => esc_html__('Something went wrong', 'media-cloud-sync')
        ];
        
        $service = isset($data['service'])? $data['service'] : false;
        $handler_class = $this->get_handler_class($service);

        if($service == false || $handler_class == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('No service selected', 'media-cloud-sync')
            ];
            return $result;
        }

        $config                 = $this->resolve_config($data);
        $bucketData             = isset($data['bucketData']) ? $data['bucketData'] : [];
        $bucketConfig           = isset($bucketData['config']) ? $bucketData['config'] : [];

        return $handler_class->verifyBucketExist($config, $bucketConfig);
    }

    /**
     * Verify Bucket Credentials
     * @since 1.0.0
     */
    public function createBucket($data) {
        $result = [
            'success' => false,
            'message' => esc_html__('Something went wrong', 'media-cloud-sync')
        ];
        
        $service = isset($data['service'])? $data['service'] : false;
        $handler_class = $this->get_handler_class($service);

        if($service == false || $handler_class == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('No service selected', 'media-cloud-sync')
            ];
            return $result;
        }
        
        $config                 = $this->resolve_config($data);
        $bucketData             = isset($data['bucketData']) ? $data['bucketData'] : [];
        $bucketAddNewConfig     = isset($bucketData['addNewConfig']) ? $bucketData['addNewConfig'] : [];

        return $handler_class->createBucket( $config, $bucketAddNewConfig );
    }

    /**
     * Verify Object write permission
     * @since 1.0.0
     */
    public function verifyObjectWritePermission($data) {
        $result = [
            'success' => false,
            'message' => esc_html__('Something went wrong', 'media-cloud-sync')
        ];
        
        $service = isset($data['service'])? $data['service'] : false;
        $handler_class = $this->get_handler_class($service);

        if($service == false || $handler_class == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('No service selected', 'media-cloud-sync')
            ];
            return $result;
        }

        $config             = $this->resolve_config($data);
        $bucketData         = isset($data['bucketData']) ? $data['bucketData'] : [];
        if(isset($bucketData['addNew']) && $bucketData['addNew']) {
            $bucketConfig   = isset($bucketData['addNewConfig']) ? $bucketData['addNewConfig'] : [];
        } else {
            $bucketConfig   = isset($bucketData['config']) ? $bucketData['config'] : [];
        }

        return $handler_class->verifyObjectWritePermission($config, $bucketConfig);
    }


    /**
     * Verify Object delete permission
     * @since 1.0.0
     */
    public function verifyObjectDeletePermission($data) {
        $result = [
            'success' => false,
            'message' => esc_html__('Something went wrong', 'media-cloud-sync')
        ];
        
        $service = isset($data['service'])? $data['service'] : false;
        $handler_class = $this->get_handler_class($service);

        if($service == false || $handler_class == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('No service selected', 'media-cloud-sync')
            ];
            return $result;
        }

        $config     = $this->resolve_config($data);
        $bucketData = isset($data['bucketData']) ? $data['bucketData'] : [];
        if(isset($bucketData['addNew']) && $bucketData['addNew']) {
            $bucketConfig   = isset($bucketData['addNewConfig']) ? $bucketData['addNewConfig'] : [];
        } else {
            $bucketConfig   = isset($bucketData['config']) ? $bucketData['config'] : [];
        }

        return $handler_class->verifyObjectDeletePermission( $config, $bucketConfig );
    }


    /**
     * Get Bucket Security Settings
     */
    public function getBucketSecuritySettings($data) {
        $result = [
            'success' => false,
            'message' => esc_html__('Something went wrong', 'media-cloud-sync')
        ];
        
        $service = isset($data['service'])? $data['service'] : false;
        $handler_class = $this->get_handler_class($service);

        if($service == false || $handler_class == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('No service selected', 'media-cloud-sync')
            ];
            return $result;
        }

        if(!method_exists($handler_class, 'getBucketSecuritySettings')) {
            $result = [
                'success' => false,
                'message' => esc_html__('Service does not have getBucketSecuritySettings method', 'media-cloud-sync')
            ];
            return $result;
        }

        $config                 = $this->resolve_config($data);
        $bucketData             = isset($data['bucketData']) ? $data['bucketData'] : [];
        if(isset($bucketData['addNew']) && $bucketData['addNew']) {
            $bucketConfig           = isset($bucketData['addNewConfig']) ? $bucketData['addNewConfig'] : [];
        } else {
            $bucketConfig           = isset($bucketData['config']) ? $bucketData['config'] : [];
        }

        return $handler_class->getBucketSecuritySettings( $config, $bucketConfig );
    }


     /**
     * Change Bucket Public Access
     * @since 1.0.0
     * @param array $data
     */
    public function changePublicAccess($data) {
        $result = [
            'success' => false,
            'message' => esc_html__('Something went wrong', 'media-cloud-sync')
        ];
        
        $service = isset($data['service'])? $data['service'] : false;
        $handler_class = $this->get_handler_class($service);

        if($service == false || $handler_class == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('No service selected', 'media-cloud-sync')
            ];
            return $result;
        }

        if(method_exists($handler_class, 'changePublicAccess') == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('Method not supported for this service', 'media-cloud-sync')
            ];
            return $result;
        }

        $config         = $this->resolve_config($data);
        $bucketData     = isset($data['bucketData']) ? $data['bucketData'] : [];
        if(isset($bucketData['addNew']) && $bucketData['addNew']) {
            $bucketConfig   = isset($bucketData['addNewConfig']) ? $bucketData['addNewConfig'] : [];
        } else {
            $bucketConfig   = isset($bucketData['config']) ? $bucketData['config'] : [];
        }
        $value  = isset($data['value']) ? $data['value'] : false;

        return $handler_class->changePublicAccess( $config, $bucketConfig, $value );
    }
    
    /**
     * Change bucket ownership
     */
    public function changeObjectOwnership($data) {
        $result = [
            'success' => false,
            'message' => esc_html__('Something went wrong', 'media-cloud-sync')
        ];
        
        $service = isset($data['service'])? $data['service'] : false;
        $handler_class = $this->get_handler_class($service);

        if($service == false || $handler_class == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('No service selected', 'media-cloud-sync')
            ];
            return $result;
        }

        if(method_exists($handler_class, 'changeObjectOwnership') == false) {
            $result = [
                'success' => false,
                'message' => esc_html__('Method not supported for this service', 'media-cloud-sync')
            ];
            return $result;
        }

        $config                 = $this->resolve_config($data);
        $bucketData             = isset($data['bucketData']) ? $data['bucketData'] : [];
        if(isset($bucketData['addNew']) && $bucketData['addNew']) {
            $bucketConfig           = isset($bucketData['addNewConfig']) ? $bucketData['addNewConfig'] : [];
        } else {
            $bucketConfig           = isset($bucketData['config']) ? $bucketData['config'] : [];
        }
        $value  = isset($data['value']) ? $data['value'] : false;

        return $handler_class->changeObjectOwnership( $config, $bucketConfig, $value );
    }

    
    /**
     * Generates a URL for a given key in the cloud storage.
     * 
     * @param string $key The key of the object in the cloud storage.
     * 
     * @return string The URL of the object.
     */
    public function get_url($key) {
        return $this->service->generate_file_url($key);
    }


    /**
     * Checks if a given URL is from a provider.
     * 
     * @param string $url The URL to be checked.
     * 
     * @return bool True if the URL is from a provider, false otherwise.
     */
    public function is_provider_url($url) {
        return $this->service->is_provider_url($url);
    }


    /**
     * Get private URL
     * @since 1.0.0
     */
    public function get_private_url($path) {
        $url_result = $this->service->get_private_url($path);
        if(isset($url_result['success']) && $url_result['success']) {
            return isset($url_result['file_url']) ? $url_result['file_url'] : false;
        }
        return false;
    }


    /**
     * Upload a single file to the cloud storage.
     *
     * @param string $file_path The absolute path to the file on the local server.
     * @param string $relative_source_path The relative path to the file on the local server.
     * @param string $prefix An optional prefix to add to the cloud storage path.
     * @return array The result of the upload operation, including success status and any relevant messages.
     */
    public function uploadSingle($file_path, $relative_source_path, $prefix = '') {
        return $this->service->uploadSingle($file_path, $relative_source_path, $prefix);
    }


    public function deleteSingle($key) {
        return $this->service->deleteSingle($key);
    }


    /**
     * Move object to server from cloud
     */
    public function object_to_server($key, $save_path) {
        $path_parts = pathinfo($save_path);
        if (!file_exists($path_parts['dirname'])) {
            mkdir($path_parts['dirname'], 0755, true);
        }
        return $this->service->object_to_server($key, $save_path);
    }

    /**
     * Copy an object to a new path in the cloud storage
     *
     * @param string $key The key of the object to be copied
     * @param string $new_path The new path to move the object to
     * @return array The result of the copy operation
     */
    public function copy_to_new_path($key, $new_path) {
        return $this->service->copy_to_new_path($key, $new_path);
    }

    /**
     * Get Service Handler
     */
    public function get_handler_class($service) {
        if(isset($this->providers[$service])) {
            $provider = $this->providers[$service];
            if(!empty($provider['sdk'])) {
                self::load_sdk($provider['sdk']);
            }
            $class = __NAMESPACE__ . '\\' . $provider['class'];
            if(class_exists($class)) {
                return new $class();
            }
        }
        return false;
    }

    /**
     * Lazy load the bundled SDK autoloader for the given service.
     * Each SDK is required at most once per request.
     *
     * @since 1.3.10
     */
    private static function load_sdk($sdk) {
        static $loaded = [];
        if (isset($loaded[$sdk])) {
            return;
        }
        if ($sdk === 's3') {
            require_once WPMCS_SDK_PATH . 's3/aws-autoloader.php';
        } elseif ($sdk === 'google') {
            require_once WPMCS_SDK_PATH . 'google/autoload.php';
        } else {
            return;
        }
        $loaded[$sdk] = true;
    }

    /**
     * Get the service domain
     * 
     */
    public function get_domain() {
        return $this->service->get_domain();
    }

    /**
     * Ensures only one instance of Class is loaded or can be loaded.
     *
     * @return Service Class instance
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