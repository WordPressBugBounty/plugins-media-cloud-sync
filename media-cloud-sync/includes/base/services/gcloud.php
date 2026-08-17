<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

// Libraries
use Dudlewebs\WPMCS\GCP\Google\Cloud\Storage\StorageClient;
use Dudlewebs\WPMCS\GCP\Google\Cloud\Core\Exception\ServiceException;

use Exception;

class GCloud {
    private $assets_url;
    private $version;
    private $token;

    protected $config;
    protected $bucketConfig;
    protected $settings;
    protected $credentials;
    protected $bucket_name;
    protected $bucket; // Object
    protected $cdnConfig;

    public $service         = 'gcloud';
    public $gcloudClient    = false;

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct($credentials = null) {
        $this->assets_url = WPMCS_ASSETS_URL;
        $this->version    = WPMCS_VERSION;
        $this->token      = WPMCS_TOKEN;

        // Initialize setup
        $this->init($credentials);
    }

    /**
     * Initialise Client
     *
     * @param array|null $credentials Optional explicit credentials; falls back to
     *                                Utils::get_credentials() when omitted.
     */
    public function init($credentials = null) {
        $this->settings     = Utils::get_settings();
        $this->credentials  = $credentials !== null ? $credentials : Utils::get_credentials();
        $this->config       = isset($this->credentials['config']) && !empty($this->credentials['config']) 
                                ? $this->credentials['config']
                                : [];
        $this->bucketConfig =  isset($this->credentials['bucketConfig']) && !empty($this->credentials['bucketConfig']) 
                                ? $this->credentials['bucketConfig']
                                : [];
        $this->bucket_name  =  isset($this->bucketConfig['bucket_name']) && !empty($this->bucketConfig['bucket_name']) 
                                ? $this->bucketConfig['bucket_name']
                                : '';
        $this->cdnConfig    = isset($this->credentials['cdn']) && !empty($this->credentials['cdn']) 
                                ? $this->credentials['cdn']
                                : [];

        if (
            isset($this->config['config_json']) && !empty($this->config['config_json']) &&
            isset($this->bucket_name) && !empty($this->bucket_name)
        ) {
            if(Utils::is_json($this->config['config_json'])){
                // Set google client
               $keyArray = json_decode($this->config['config_json'], true);
    
                if (is_array($keyArray)) {
                    $this->gcloudClient = new StorageClient([
                        'keyFile' => $keyArray,
                    ]);
                    $this->bucket = $this->gcloudClient->bucket($this->bucket_name);
                } else {
                    // Handle JSON decode failure
                    throw new \Exception('Invalid JSON provided for GCloud credentials.');
                }
            } else {
                add_action('admin_notices', function (){
                    echo wp_kses_post(sprintf( "<div class='error'><p><strong>%s: </strong><br>Google Cloud Storage configuration is invalid.
                        It may break the media url's as well as media uploads.<br>
                        <a href='%s'>Re-configure</a> plugin to fix the issue.
                        </p></div>", 
                        esc_html__('Media Cloud Sync', 'media-cloud-sync'),
                        admin_url('admin.php?page='.$this->token . '-admin-ui#/configure')
                    ));
                });

            }       
        }
    }

    /**
     * Verify Credentials
     * @since 1.0.0
     * @return boolean
     */
    public function verifyCredentials( $config = [] ){
        $config_json = isset($config['config_json']) ? $config['config_json'] : '';
        if (!Service::has_missing_fields([$config_json])) {
            if(!Utils::is_json($config_json)){
                return [
                    'success' => false,
                    'code'    => 200,
                    'message' => esc_html__('Invalid JSON configuration, please try again', 'media-cloud-sync'),
                ];
            }

            try {
                $config_array = json_decode($config_json, true);
                if (is_array($config_array)) {
                    $googleClient = new StorageClient([
                        'keyFile' => $config_array
                    ]);
                } else {
                    return [
                        'success' => false,
                        'code'    => 200,
                        'message' => esc_html__('JSON Configuration is invalid', 'media-cloud-sync'),
                    ];
                }

                $result = [
                    'success' => false,
                    'code'    => 200,
                    'message' => esc_html__('Please check the authorization details', 'media-cloud-sync'),
                ];

                try {
                    $bucket = $googleClient->bucket($this->token . '_dummy-bucket-for-auth-check');
                    $exists = $bucket->exists(); // Triggers the API call

                    // If we reach here, the credentials are valid
                    $result = [
                        'success' => true,
                        'code'    => 200,
                        'message' => esc_html__('Credentials are valid', 'media-cloud-sync'),
                    ];
                } catch (ServiceException $e) {
                    $statusCode = $e->getCode();
            
                    $validErrors = [200, 403, 404];
            
                    if (in_array($statusCode, $validErrors)) {
                        // If we reach here, the credentials are valid
                        $result = [
                            'success' => true,
                            'code'    => 200,
                            'message' => esc_html__('Credentials are valid', 'media-cloud-sync'),
                        ];
                    }
                }

                if($result['success'] == false) {
                    return $result;
                }


                try {
                    $buckets = $googleClient->buckets();
                    $newBucketFormat = [];
                    if(isset($buckets) && !empty($buckets)){
                        foreach($buckets as $bucket) {
                            $name = $bucket->name();
                            if(!empty($name)) {
                                // Fetch the bucket's metadata
                                $bucketInfo = $bucket->info();
                                $newBucketFormat[] = ['Name' => $name, 'CreationDate' => $bucketInfo['timeCreated']];
                            }
                        }
                    }
                    $result['buckets_data']['buckets'] = $newBucketFormat;
                    $result['buckets_data']['message'] = esc_html__('Buckets listed successfully', 'media-cloud-sync');
                    $result['buckets_data']['status'] = true;
                } catch (Exception $e) {
                    $result ['buckets_data']['buckets'] = [];
                    $result ['buckets_data']['message'] = esc_html__('Unable to list buckets, Please check the bucket listing permission', 'media-cloud-sync');
                    $result ['buckets_data']['status'] = false;
                }
                return $result;
            } catch (Exception $ex) {
                return array('message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            }
        }
        return array('message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false);
    }


    /**
     * Verify Bucket Exists
     * @since 1.0.0
     * @return boolean
     */
    public function verifyBucketExist( $config = [], $bucketConfig = [] ){
        $config_json = isset($config['config_json']) ? $config['config_json'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        if ( Service::has_missing_fields([$config_json, $bucket_name]) ) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        if ( !Utils::is_json( $config_json ) ) {
            return ['message' => esc_html__('JSON Configuration is invalid', 'media-cloud-sync'), 'code' => 200, 'success' => false];   
        }

        try {
            $config_array = json_decode($config_json, true);
            if (is_array($config_array)) {
                $googleClient = new StorageClient([
                    'keyFile' => $config_array
                ]);
            } else {
                return [
                    'success' => false,
                    'code'    => 200,
                    'message' => esc_html__('JSON Configuration is invalid', 'media-cloud-sync'),
                ];
            }

            try {
                $bucket = $googleClient->bucket($bucket_name);

                if ($bucket->exists()) {
                    return [
                        'message' => esc_html__('Bucket exists', 'media-cloud-sync'),
                        'code'    => 200,
                        'success' => true,
                    ];
                } else {
                    return [
                        'message' => esc_html__('Bucket does not exist', 'media-cloud-sync'),
                        'code'    => 200,
                        'success' => false,
                    ];
                }
            } catch (ServiceException $e) {
                return [
                    'message' => esc_html__('Bucket does not exist or credentials are invalid: ', 'media-cloud-sync') . $e->getMessage(),
                    'code'    => 200,
                    'success' => false,
                ];
            }
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }


    /**
     * Create Bucket
     * @since 1.0.0
     * @return boolean
     */
    public function createBucket( $config = [], $bucketConfig = [] ){
        $config_json = isset($config['config_json']) ? $config['config_json'] : '';
        $region = isset($bucketConfig['region']) ? $bucketConfig['region'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        if ( Service::has_missing_fields([$config_json, $region, $bucket_name]) ) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        if ( !Utils::is_json( $config_json ) ) {
            return ['message' => esc_html__('JSON Configuration is invalid', 'media-cloud-sync'), 'code' => 200, 'success' => false];   
        }

        try {
            $config_array =  json_decode($config_json, true);
            if (is_array($config_array)) {
                $googleClient = new StorageClient([
                    'keyFile' => $config_array
                ]);
            } else {
                return [
                    'success' => false,
                    'code'    => 200,
                    'message' => esc_html__('JSON Configuration is invalid', 'media-cloud-sync'),
                ];
            }

            // Create Bucket
            $bucket = $googleClient->createBucket($bucket_name, [
                'location' => $region,
                'iamConfiguration' => [
                    'uniformBucketLevelAccess' => [
                        'enabled' => true
                    ]
                ]
            ]);

            // Fetch the bucket's IAM
            try {
               $iam = $bucket->iam();

               $policy = $iam->policy();

                // Add allUsers as a Storage Object Viewer
                $policy['bindings'][] = [
                    'role' => 'roles/storage.objectViewer',
                    'members' => ['allUsers'],
                ];

                // Set the updated policy
                $iam->setPolicy($policy);

            } catch (ServiceException $e) {
                return [
                    'message' => esc_html__('Bucket created successfully. But failed to set IAM policy.', 'media-cloud-sync'),
                    'data'    => [
                        'Name'         => $bucket_name,
                        'CreationDate' => date('Y-m-d\TH:i:s\Z'),
                    ],
                    'code'    => 200,
                    'success' => true,
                ];
            } catch (Exception $e) {
                return [
                    'message' => esc_html__('Bucket created successfully. But failed to set IAM policy.', 'media-cloud-sync'),
                    'data'    => [
                        'Name'         => $bucket_name,
                        'CreationDate' => date('Y-m-d\TH:i:s\Z'),
                    ],
                    'code'    => 200,
                    'success' => true,
                ];
            }
            
            return [
                'message' => esc_html__('Bucket created successfully.', 'media-cloud-sync'),
                'data'    => [
                    'Name'         => $bucket_name,
                    'CreationDate' => date('Y-m-d\TH:i:s\Z'),
                ],
                'code'    => 200,
                'success' => true,
            ];
               
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }

    /**
     * Check Bucket Write Permission
     * @since 1.0.0
     */ 
    public function verifyObjectWritePermission( $config = [], $bucketConfig = [] ) {
        $config_json = isset($config['config_json']) ? $config['config_json'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        if ( Service::has_missing_fields([$config_json, $bucket_name]) ) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        if ( !Utils::is_json( $config_json ) ) {
            return ['message' => esc_html__('JSON Configuration is invalid', 'media-cloud-sync'), 'code' => 200, 'success' => false];   
        }

        try {
            $config_array =  json_decode($config_json, true);
            if (is_array($config_array)) {
                $googleClient = new StorageClient([
                    'keyFile' => $config_array
                ]);
            } else {
                return [
                    'success' => false,
                    'code'    => 200,
                    'message' => esc_html__('JSON Configuration is invalid', 'media-cloud-sync'),
                ];
            }
            $bucket = $googleClient->bucket($bucket_name);
            if ($bucket->exists()) {
                $bucket_found = true;
            } else {
                return ['message' => esc_html__('No Buckets found', 'media-cloud-sync'), 'code' => 200, 'success' => false];
            }
            if ($bucket_found) {
                $object_key = Utils::get_permission_check_object_key();

                // Prepare a temporary file with content to check write permission
                $stream = fopen('php://temp', 'r+');
                fwrite($stream, 'This is a test object to check write permission.');
                rewind($stream);

                // Upload the object to the bucket
                $object = $bucket->upload(
                    $stream,
                    [
                        'name' => $object_key,
                    ]
                );

                if ($object->exists()) {
                    return ['message' => esc_html__('Bucket write permission verified successfully', 'media-cloud-sync'), 'code' => 200, 'success' => true];
                } else {
                    return ['message' => esc_html__('Bucket write permission not verified', 'media-cloud-sync'), 'code' => 200, 'success' => false];
                }
            }
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        } finally {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Check Bucket Delete Permission
     * @since 1.0.0
     */ 
    public function verifyObjectDeletePermission( $config = [], $bucketConfig = [] ) {
        $config_json = isset($config['config_json']) ? $config['config_json'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';

        if ( Service::has_missing_fields([$config_json, $bucket_name]) ) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
        if( !Utils::is_json( $config_json ) ) {
            return ['message' => esc_html__('JSON Configuration is invalid', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $config_array =  json_decode($config_json, true);
            if (is_array($config_array)) {
                $googleClient = new StorageClient([
                    'keyFile' => $config_array
                ]);
            } else {
                return [
                    'success' => false,
                    'code'    => 200,
                    'message' => esc_html__('JSON Configuration is invalid', 'media-cloud-sync'),
                ];
            }

            $bucket = $googleClient->bucket($bucket_name);
            try {
                $object_key = Utils::get_permission_check_object_key();
                
                // Try fetching a dummy object to test access
                $object = $bucket->object($object_key);
                if ($object->exists()) {
                    $object->delete();  
                    if (!$object->exists()) {
                        return [
                            'message' => esc_html__('Bucket exists', 'media-cloud-sync'),
                            'code'    => 200,
                            'success' => true,
                        ];
                    } else {
                        return [
                            'message' => esc_html__('You do not have permission to delete object', 'media-cloud-sync'),
                            'code'    => 200,
                            'success' => false,
                        ];
                    }
                } else {
                    return [
                        'message' => esc_html__('Object does not exist', 'media-cloud-sync'),
                        'code'    => 200,
                        'success' => false,
                    ];
                }
            } catch (Exception $ex) {
                return [
                    'message' => esc_html__('Object does not exist or credentials are invalid: ', 'media-cloud-sync') . $ex->getMessage(),
                    'code'    => 200,
                    'success' => false,
                ];
            }
           
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }

    /**
     * Check Bucket Read Permission
     * @since 1.2.4
     */
    public function verifyObjectReadPermission() {
        $result = [
            'status' => false,
            'message' => '',
            'lastChecked' => time(),
        ];
        if (Service::has_missing_fields([$this->gcloudClient, $this->bucket_name])) {
            $result['message'] = esc_html__('Please check the authorization details', 'media-cloud-sync');
            return [
                'message' => $result['message'],
                'code'    => 200,
                'success' => false,
                'lastChecked' => $result['lastChecked'],
            ];
        }

        try {   
            $object_key = Utils::get_permission_check_object_key();

            // Check if the object was created successfully
            if (!$this->exists($object_key)) {
                // Create a dummy object to check write permission
                $stream = fopen('php://temp', 'r+');
                fwrite($stream, 'This is a test object to check read permission.');
                rewind($stream);
                $this->bucket->upload(
                    $stream,
                    [
                        'name' => $object_key,
                        'metadata' => ['cacheControl' => 'no-cache, no-store, must-revalidate'],
                    ]
                );
                // Re-check if the object was created successfully
                if (!$this->exists($object_key)) {
                    $result['status'] = false;
                    $result['message'] = esc_html__('Failed to create an object for read permission check, please check service configuration', 'media-cloud-sync');
                    return [
                        'message' => $result['message'],
                        'code'    => 200,
                        'success' => false,
                        'lastChecked' => $result['lastChecked'],
                    ];
                }
            } 

            $url = $this->generate_file_url($object_key);
            $cdn_url = Cdn::may_generate_cdn_url($url, $object_key);

            // Never trust a cached response for this fixed, predictable URL — a stale cached
            // error would otherwise keep failing the check long after real access is fine.
            $no_cache_context = stream_context_create(['http' => ['header' => "Cache-Control: no-cache\r\nPragma: no-cache\r\n"]]);
            $headers = @get_headers($cdn_url, false, $no_cache_context);
            $status_code = (is_array($headers) && !empty($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $matches))
                ? (int) $matches[1]
                : 0;

            if ($status_code === 200) {
                $result['status'] = true;
                $result['message'] = esc_html__('Objects are accessible to Read', 'media-cloud-sync');
            } else if ($status_code === 403) {
                $result['status'] = false;
                if(isset($this->cdnConfig['service']) && $this->cdnConfig['service'] == $this->service) {
                    $result['message'] = esc_html__('Access Denied. Please check your bucket policy. Public Read Access is required.', 'media-cloud-sync');
                } else {
                    $result['message'] = esc_html__('Access Denied. Please check your bucket policy', 'media-cloud-sync');
                }
            } else if ($status_code === 404) {
                $result['status'] = false;
                $result['message'] = esc_html__('Object not found. Please check your bucket policy', 'media-cloud-sync');
            } else if ($status_code === 500) {
                $result['status'] = false;
                $result['message'] = esc_html__('Internal Server error. Please check your bucket policy', 'media-cloud-sync');
            } else {
                $result['status'] = false;
                $result['message'] = esc_html__('Objects are not accessible to read', 'media-cloud-sync');
            }
            $this->deleteSingle($object_key);
            return [
                'message' => $result['message'],
                'code'    => 200,
                'success' => $result['status'],
                'lastChecked' => $result['lastChecked'],
            ];
        } catch (ServiceException $ex) {
            $result['message'] = $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        } catch (Exception $ex) {
            $result['message'] = $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        } finally {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * isConfigured Function To Identify the congfigurations are correct
     * @since 1.0.0
     */
    public function isConfigured(){
        if ($this->gcloudClient) {
            try {
                $bucket = $this->gcloudClient->bucket($this->token . '_dummy-bucket-for-auth-check');
                $exists = $bucket->exists(); // Triggers the API call
                return true;
            } catch (ServiceException $e) {
                $statusCode = $e->getCode();
        
                $validErrors = [200, 403, 404];
        
                if (in_array($statusCode, $validErrors)) {
                    // If we reach here, the credentials are valid
                    return true;
                } else {
                    // If we reach here, the credentials are not valid
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * Make Object Private
     * @since 1.0.0
     * 
     */
    public function toPrivate($key) {
        if(!$key) return false;
        if(!$this->bucket) return false;

        try {
            $object = $this->bucket->object($key);
            if ($object->exists()) {
                $object->update(['acl' => []], ['predefinedAcl' => 'private']);
                return true;
            }
        } catch (ServiceException $e) {
            // Handle exception if needed
            return false;
        } catch (Exception $e) {
            // Handle other exceptions if needed
            return false;
        }
        return false;
    }


    /**
     * Make Object Public
     * @since 1.0.0
     * 
     */
    public function toPublic($key) {
        if(!$key) return false;
        if(!$this->bucket) return false;

        try {
            $object = $this->bucket->object($key);
            if ($object->exists()) {
                $object->update(['acl' => []], ['predefinedAcl' => 'publicRead']);
                return true;
            }
            return false;
        } catch (ServiceException $e) {
            // Handle exception if needed
            return false;
        } catch (Exception $e) {
            // Handle other exceptions if needed
            return false;
        }
    }


    /**
     * Check the object exist 
     * @since 1.1.8
     */
    public function exists($key, $bucket = null) {
        if(!$key) return false;
        
        try {
            $bucket = $bucket ?? $this->bucket;
            $object = $bucket->object($key);
            if ($object->exists()) {
                return true;
            } else {
                return false;
            }
        } catch (ServiceException $e) {
            // Handle exception if needed
            return false;
        } catch (Exception $e) {
            // Handle other exceptions if needed
            return false;
        }
        
        return false;
    }


    /**
     * List Objects — $delimiter = null gives a flat/recursive listing instead of one folder level.
     * resultLimit=$maxKeys caps the iterator to this page only (Bucket::objects() would otherwise auto-paginate the whole bucket).
     * @since 1.3.13
     */
    public function listObjects($prefix = '', $continuationToken = null, $maxKeys = 1000, $delimiter = '/') {
        if (!$this->bucket) {
            return ['success' => false, 'code' => 200, 'message' => esc_html__('Client not configured', 'media-cloud-sync'), 'folders' => [], 'objects' => [], 'next_token' => null];
        }
        try {
            $options = [
                'maxResults'  => $maxKeys,
                'resultLimit' => $maxKeys,
            ];
            if (!empty($delimiter)) {
                $options['delimiter'] = $delimiter;
            }
            if (!empty($prefix)) {
                $options['prefix'] = $prefix;
            }
            if (!empty($continuationToken)) {
                $options['pageToken'] = $continuationToken;
            }

            $iterator = $this->bucket->objects($options);

            $objects = [];
            foreach ($iterator as $object) {
                $key = $object->name();
                if ($key === $prefix) {
                    continue; // the folder placeholder object itself, not a file
                }
                $info      = $object->info();
                $objects[] = [
                    'key'           => $key,
                    'size'          => isset($info['size']) ? (int) $info['size'] : 0,
                    'last_modified' => isset($info['updated']) ? $info['updated'] : '',
                ];
            }

            return [
                'success'    => true,
                'code'       => 200,
                'message'    => '',
                'folders'    => $iterator->prefixes(),
                'objects'    => $objects,
                'next_token' => $iterator->nextResultToken(),
            ];
        } catch (ServiceException $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage(), 'folders' => [], 'objects' => [], 'next_token' => null];
        } catch (Exception $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage(), 'folders' => [], 'objects' => [], 'next_token' => null];
        }
    }

    /**
     * Upload Single
     * @since 1.0.0
     * @return boolean
     */
    public function uploadSingle($absolute_source_path, $relative_source_path, $prefix=''){
        if (
            isset($absolute_source_path) && !empty($absolute_source_path) &&
            isset($relative_source_path) && !empty($relative_source_path)
        ) {
            $file_name = wp_basename( $relative_source_path );
            if ($file_name) {
                $upload_path = Utils::generate_object_key($relative_source_path, $prefix);
                return $this->execute_upload($absolute_source_path, $upload_path);
            }
            return [
                'success'   => false,
                'code'      => 200,
                'message'   => esc_html__('Check the file you are trying to upload. Please try again', 'media-cloud-sync'),
            ];
        }
        return [
            'success'   => false,
            'code'      => 200,
            'message'   => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'),
        ];
    }

    /**
     * Upload a local file to an exact destination key (no Utils::generate_object_key() derivation).
     * @since 1.4.0
     */
    public function uploadObjectAtKey($absolute_source_path, $key) {
        return $this->execute_upload($absolute_source_path, $key);
    }

    // Chunked upload above GCLOUD_MULTIPART_MIN_FILE_SIZE, single request below it — same
    // threshold uploadSingle() always used, now shared with uploadObjectAtKey().
    private function execute_upload($absolute_source_path, $key) {
        $options = ['name' => $key];
        if (filesize($absolute_source_path) > Schema::getConstant('GCLOUD_MULTIPART_MIN_FILE_SIZE')) {
            $options['chunkSize'] = 262144 * 2;
        }
        $cache_control = Utils::get_cache_control_header();
        if ($cache_control) {
            $options['cacheControl'] = $cache_control;
        }

        try {
            $handle = fopen($absolute_source_path, 'rb');
            $upload = $this->bucket->upload($handle, $options);

            if ($upload->exists()) {
                return [
                    'success'  => true,
                    'code'     => 200,
                    'file_url' => $this->generate_file_url($key),
                    'key'      => $key,
                    'message'  => esc_html__('File Uploaded Successfully', 'media-cloud-sync'),
                ];
            }
            return [
                'success' => false,
                'code'    => 200,
                'message' => esc_html__('Object not found at server.', 'media-cloud-sync'),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'code'    => 200,
                'message' => $e->getMessage(),
            ];
        } finally {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * Save object to server
     * @since 1.0.0
     */
    public function object_to_server($key, $save_path){
        if(!$this->bucket) return false;
        try {
            $object = $this->bucket->object($key);
            if ($object->exists()) {
                $object->downloadToFile($save_path);
                if (file_exists($save_path)) {
                    return true;
                }
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Object bytes in memory, no local file — for callers (e.g. zip download) that need
     * the content itself rather than a copy on the server's filesystem.
     * @since 1.3.13
     */
    public function get_object_content($key) {
        if(!$this->bucket) return false;
        try {
            $object = $this->bucket->object($key);
            if ($object->exists()) {
                return $object->downloadAsString();
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Deletes the live generation, then best-effort purges every prior generation too — a
     * bucket with Object Versioning enabled otherwise keeps old generations (and the storage
     * they use) around at the old key. The live delete happens unconditionally first, in its
     * own try/catch, so the object still ends up gone even if the generation-listing call
     * below fails for any reason.
     * @since 1.3.14
     */
    public function purge_all_versions($key) {
        if (!$this->bucket) {
            return ['success' => false, 'code' => 200, 'message' => esc_html__('Client not configured', 'media-cloud-sync')];
        }

        try {
            $this->bucket->object($key)->delete();
        } catch (ServiceException $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage()];
        }

        // Best-effort only from here — the live copy above is already gone regardless of
        // whether this bucket has Object Versioning enabled or this call succeeds.
        try {
            foreach ($this->bucket->objects(['prefix' => $key, 'versions' => true]) as $object) {
                if ($object->name() === $key) {
                    $object->delete();
                }
            }
        } catch (ServiceException $e) {
            // Generation history cleanup failed — not fatal, live object is gone.
        } catch (\Exception $e) {
            // Generation history cleanup failed — not fatal, live object is gone.
        }

        return ['success' => true, 'code' => 200, 'message' => esc_html__('Purged Successfully', 'media-cloud-sync')];
    }


    /**
     * Copy an object to a new path in Google Cloud Storage
     *
     * @param string $key       Original object key (path in bucket)
     * @param string $new_path  Destination object key
     * @return bool             True if object was copied successfully, false otherwise
     * @since 1.3.4
     */
    // Trusts copy()'s own success/failure rather than pre/post-verifying with extra
    // exists() calls — each one is a full network round-trip, and with move/copy processing
    // keys sequentially, extra round-trips per file add up fast on a folder with many files.
    // copy() itself throws (caught below) if the source is missing or the copy otherwise
    // fails, so nothing is lost by not checking first.
    public function copy_to_new_path($key, $new_path) {
        if (!$this->bucket) {
            return [
                'message' => esc_html__('Client not configured', 'media-cloud-sync'),
                'code'    => 200,
                'success' => false
            ];
        }
        try {
            $sourceObject = $this->bucket->object($key);
            $sourceObject->copy($this->bucket, ['name' => $new_path]);
            return [
                'success' => true,
                'code' => 200,
                'message' => esc_html__('File copied successfully', 'media-cloud-sync')
            ];
        } catch (ServiceException $e) {
            return [
                'success' => false,
                'code' => 200,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'code' => 200,
                'message' => $e->getMessage()
            ];
        }
    }

    // Like copy_to_new_path() but into an explicit (possibly different) bucket — needs write
    // access there too, so callers should fall back to download+upload on failure.
    public function copy_to_bucket($key, $new_key, $dest_bucket) {
        if (!$this->bucket || !$this->gcloudClient) {
            return [
                'message' => esc_html__('Client not configured', 'media-cloud-sync'),
                'code'    => 200,
                'success' => false
            ];
        }
        try {
            $sourceObject = $this->bucket->object($key);
            $sourceObject->copy($this->gcloudClient->bucket($dest_bucket), ['name' => $new_key]);
            return [
                'success' => true,
                'code' => 200,
                'message' => esc_html__('File copied successfully', 'media-cloud-sync')
            ];
        } catch (ServiceException $e) {
            return [
                'success' => false,
                'code' => 200,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'code' => 200,
                'message' => $e->getMessage()
            ];
        }
    }


     /**
     * Delete Single
     * @since 1.0.0
     * @return boolean
     */
    public function deleteSingle($key){
        $result = array();
        if (!$this->bucket) {
            return array(
                'success' => false,
                'code'    => 200,
                'message' => esc_html__('Client not configured', 'media-cloud-sync')
            );
        }
        if (isset($key) && !empty($key)) {
            try {
                $object = $this->bucket->object($key);
                $object->delete();

                if (!$object->exists()) {
                    $result = array(
                        'success'   => true,
                        'code'      => 200,
                        'message'   => esc_html__('Deleted Successfully', 'media-cloud-sync'),
                    );
                } else {
                    $result = array(
                        'success'   => false,
                        'code'      => 200,
                        'message'   => esc_html__('File not deleted', 'media-cloud-sync'),
                    );
                }
            } catch (Exception $e) {
                $result = array(
                    'success'   => false,
                    'code'      => 200,
                    'message'   => $e->getMessage(),
                );
            }
        } else {
            $result = array(
                'success'   => false,
                'code'      => 200,
                'message'   => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'),
            );
        }
        return $result;
    }


    /**
     * get private URL
     * @since 1.0.0
     * @return boolean
     */
    public function get_private_url($key) {
        $result = array();
        if (!$this->bucket) {
            return array(
                'success' => false,
                'code'    => 200,
                'message' => esc_html__('Client not configured', 'media-cloud-sync')
            );
        }
        if (isset($key) && !empty($key)) {
            try {
                $object = $this->bucket->object($key);

                $expires = isset($this->settings['private_url_expire']) ? $this->settings['private_url_expire'] : 20;

                $privateUrl =  $object->signedUrl(new \DateTime(sprintf('+%s  minutes', $expires)));

                if ($privateUrl) {
                    $result = array(
                        'success'   => true,
                        'code'      => 200,
                        'file_url'  => $privateUrl,
                        'message'   => esc_html__('Got Private URL Successfully', 'media-cloud-sync'),
                    );
                } else {
                    $result = array(
                        'success'   => false,
                        'code'      => 200,
                        'message'   => esc_html__('Error getting private URL', 'media-cloud-sync'),
                    );
                }
            } catch (Exception $e) {
                $result = array(
                    'success'   => false,
                    'code'      => 200,
                    'message'   => $e->getMessage(),
                );
            }
        } else {
            $result = array(
                'success'   => false,
                'code'      => 200,
                'message'   => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'),
            );
        }
        return $result;
    }


    /**
     * Generate file URL
     */
    public function generate_file_url($key){
        $domain = $this->get_domain();

        return apply_filters('wpmcs_generate_google_file_url',
            $domain . '/' . $this->bucket_name . '/' . $key,
            $domain, $key,
            $this->bucket_name
        );
    }

    /**
     * Is provider URL
     * @since 1.3.6
     */
    public function is_provider_url($url) {
        $domain = $this->get_domain();
        return (strpos($url, $domain . '/' . $this->bucket_name . '/') !== false);
    }

    /**
     * Get domain URL
     */
    public function get_domain() {
        $url_base = 'https://storage.googleapis.com';
        
        return $url_base;
    }
}