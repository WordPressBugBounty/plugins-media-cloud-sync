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
        's3'            => 'S3',
        'gcloud'        => 'GCloud',
        'docean'        => 'DOcean',
        'cloudflareR2'  => 'CloudflareR2',
        's3compatible'  => 'S3Compatible',
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
        $config     = isset($data['config']) ? $data['config'] : [];

        return $handler_class->verifyCredentials($config);
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

        $config                 = isset($data['config']) ? $data['config'] : [];
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
        
        $config                 = isset($data['config']) ? $data['config'] : [];
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

        $config             = isset($data['config']) ? $data['config'] : [];
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

        $config     = isset($data['config']) ? $data['config'] : [];
        $bucketData = isset($data['bucketData']) ? $data['bucketData'] : [];
        if(isset($bucketData['addNew']) && $bucketData['addNew']) {
            $bucketConfig   = isset($bucketData['addNewConfig']) ? $bucketData['addNewConfig'] : [];
        } else {
            $bucketConfig   = isset($bucketData['config']) ? $bucketData['config'] : [];
        }

        return $handler_class->verifyObjectDeletePermission( $config, $bucketConfig );
    }


    /**
     * Verify Object read permission
     * @since 1.0.0
     */
    public function verifyObjectReadPermission() {
        return $this->service->verifyObjectReadPermission();
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

        $config                 = isset($data['config']) ? $data['config'] : [];
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

        $config         = isset($data['config']) ? $data['config'] : [];
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

        $config                 = isset($data['config']) ? $data['config'] : [];
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
            $class = __NAMESPACE__ . '\\' . $this->providers[$service];
            if(class_exists($class)) {
                return new $class();
            }
        }
        return false;
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