<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

// Libraries
use Dudlewebs\WPMCS\s3\Aws\S3\S3Client;
use Dudlewebs\WPMCS\s3\GuzzleHttp\Psr7\MimeType;
use Dudlewebs\WPMCS\s3\Aws\Exception\AwsException;
use Dudlewebs\WPMCS\s3\Aws\S3\Exception\S3Exception;
use Dudlewebs\WPMCS\s3\Aws\S3\MultipartUploader;
use Dudlewebs\WPMCS\s3\Aws\Exception\MultipartUploadException;
use Dudlewebs\WPMCS\s3\Aws\Command;
use Dudlewebs\WPMCS\s3\Aws\S3\ObjectUploader;
use Dudlewebs\WPMCS\s3\GuzzleHttp\Promise\Each;
use Exception;

class CloudflareR2 {
    private $assets_url;
    private $version;
    private $token;

    protected $config;
    protected $bucketConfig;
    protected $settings;
    protected $credentials;
    protected $bucket_name;
    protected $cdnConfig;

    public $service             = 'cloudflareR2';
    public $cloudflareClient    = false;

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
        $this->bucketConfig = isset($this->credentials['bucketConfig']) && !empty($this->credentials['bucketConfig'])
                                ? $this->credentials['bucketConfig']
                                : [];
        $this->bucket_name  = isset($this->bucketConfig['bucket_name']) && !empty($this->bucketConfig['bucket_name'])
                                ? $this->bucketConfig['bucket_name']
                                : '';
        $this->cdnConfig    = isset($this->credentials['cdn']) && !empty($this->credentials['cdn'])
                                ? $this->credentials['cdn']
                                : [];

        // Cloudflare R2 needs Account ID + access/secret
        if (
            isset($this->config['account_id']) && !empty($this->config['account_id']) &&
            isset($this->config['access_key']) && !empty($this->config['access_key']) &&
            isset($this->config['secret_key']) && !empty($this->config['secret_key'])
        ) {
            $this->cloudflareClient = new S3Client([
                'version'                   => '2006-03-01',
                'region'                    => 'auto', // Cloudflare R2 uses 'auto' region
                'endpoint'                  => $this->get_domain(),
                'signature_version'         => 'v4',
                'use_accelerate_endpoint'   => false,
                'use_path_style_endpoint'   => true,
                'use_aws_shared_config_files' => false,
                'credentials' => [
                    'key'    => $this->config['access_key'],
                    'secret' => $this->config['secret_key'],
                ],
            ]);
        }

    }


    /**
     * Verify Credentials
     * @since 1.0.0
     * @return boolean
     */
    public function verifyCredentials( $config = [] ) {
        $account_id = isset($config['account_id']) ? $config['account_id'] : false;
        $access_key = isset($config['access_key']) ? $config['access_key'] : false;
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : false;
        $region     = isset($config['region']) ? $config['region'] : '';

        if (!Service::has_missing_fields([$account_id, $access_key, $secret_key])) {
            try {
                $cloudflareClient = new S3Client([
                    'version'                       => '2006-03-01',
                    'region'                        => 'auto',
                    'endpoint'                      => $this->get_domain($account_id, $region),
                    'signature_version'             => 'v4',
                    'use_path_style_endpoint'       => true,
                    'use_accelerate_endpoint'       => false,
                    'use_aws_shared_config_files'   => false,
                    'credentials'                   => [
                        'key'    => $access_key,
                        'secret' => $secret_key,
                    ],
                ]);
                
                $result = [
                    'success' => false,
                    'code'    => 200,
                    'message' => __('Please check the authorization details', 'media-cloud-sync'),
                ];

                try {
                    $cloudflareClient->listObjectsV2([
                        'Bucket' => $this->token . '_dummy-bucket-for-auth-check'
                    ]);
                
                    // If we reach here, the credentials are valid
                    $result = [
                        'success' => true,
                        'code'    => 200,
                        'message' => __('Credentials are valid', 'media-cloud-sync'),
                    ];
                } catch (AwsException $e) {
                    $code = $e->getAwsErrorCode();
            
                    $validErrors = [
                        'AccessDenied',
                        'NoSuchBucket',
                        'AllAccessDisabled',
                        'AuthorizationHeaderMalformed',
                        'PermanentRedirect',
                        'InvalidBucketName',
                    ];
            
                    if (in_array($code, $validErrors)) {
                        // If we reach here, the credentials are valid
                        $result = [
                            'success' => true,
                            'code'    => 200,
                            'message' => __('Credentials are valid', 'media-cloud-sync'),
                        ];
                    }
                }

                if($result['success'] == false) {
                    return $result;
                }

                try {
                    $buckets = $cloudflareClient->listBuckets();
                    $newBucketFormat = [];
                    if(isset($buckets['Buckets']) && !empty($buckets['Buckets'])){
                        foreach($buckets['Buckets'] as $bucket) {
                            if(isset($bucket['Name'])) {
                                $newBucketFormat[] = ['Name' => $bucket['Name'], 'CreationDate' => $bucket['CreationDate'] ?? ''];
                            }
                        }
                    }
                    $result['buckets_data']['buckets'] = $newBucketFormat;
                    $result['buckets_data']['message'] = __('Buckets listed successfully', 'media-cloud-sync');
                    $result['buckets_data']['status'] = true;
                } catch (S3Exception $e) {
                    $result ['buckets_data']['buckets'] = [];
                    $result ['buckets_data']['message'] = __('Unable to list buckets, Please check the bucket listing permission', 'media-cloud-sync');
                    $result ['buckets_data']['status'] = false;
                } catch (Exception $e) {
                    $result ['buckets_data']['buckets'] = [];
                    $result ['buckets_data']['message'] = __('Unable to list buckets, Please check the bucket listing permission', 'media-cloud-sync');
                    $result ['buckets_data']['status'] = false;
                }
                return $result;
            } catch (S3Exception $ex) {
                return array('message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            } catch (Exception $ex) {
                return array('message' => $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            }
        }
        return array('message' => __('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false);
    }

    /**
     * Verify Bucket
     * @since 1.0.0
     * @return boolean
     */
    public function verifyBucketExist( $config = [], $bucketConfig = [] ) {
        $account_id = isset($config['account_id']) ? $config['account_id'] : false;
        $access_key = isset($config['access_key']) ? $config['access_key'] : false;
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : false;
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : false;
        $region     = isset($config['region']) ? $config['region'] : '';

        if ( Service::has_missing_fields([$account_id, $access_key, $secret_key, $bucket_name]) ) {
            return array('message' => __('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false);
        }

        try {
            $cloudflareClient = new S3Client([
                'version'                       => '2006-03-01',
                'region'                        => 'auto',
                'endpoint'                      => $this->get_domain($account_id, $region),
                'signature_version'             => 'v4',
                'use_path_style_endpoint'       => true,
                'use_accelerate_endpoint'       => false,
                'use_aws_shared_config_files'   => false,
                'credentials'                   => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            //get S3 object
            $bucket_found = false;
            try {
                $cloudflareClient->getObject([
                    'Bucket' => $bucket_name,
                    'Key'    => $this->token . '_dummy-object-for-bucket-exist-check'
                ]);
                $bucket_found = true;
            }  catch (AwsException $e) {
                $code = $e->getAwsErrorCode();
                if ($code === 'NoSuchKey') {
                    $bucket_found = true;
                } 
            }
            
            if($bucket_found) {
                return array('message' => __('Bucket exist', 'media-cloud-sync'), 'code' => 200, 'success' => true);
            } else {
                return array('message' => __("Bucket chosen does not exist or you don't have permission", 'media-cloud-sync'), 'code' => 200, 'success' => false);
            }
        } catch (S3Exception $ex) {
            return array('message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
        } catch (Exception $ex) {
            return array('message' => $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
        }
    }

    /**
     * Create Bucket
     * @since 1.0.0
     */
    public function createBucket( $config = [], $bucketConfig = [] ) {
        $account_id = isset($config['account_id']) ? $config['account_id'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $region     = isset($config['region']) ? $config['region'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';

        if ( Service::has_missing_fields([$account_id, $access_key, $secret_key, $bucket_name]) ) {
            return ['message' => __('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $cloudflareClient = new S3Client([
                'version'                       => '2006-03-01',
                'region'                        => 'auto',
                'endpoint'                      => $this->get_domain($account_id, $region),
                'signature_version'             => 'v4',
                'use_path_style_endpoint'       => true,
                'use_accelerate_endpoint'       => false,
                'use_aws_shared_config_files'   => false,
                'credentials'                   => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            // Create bucket - R2 supports CreateBucket via the S3 API
            $cloudflareClient->createBucket(['Bucket' => $bucket_name]);
            
            return [
                'message' => __('Bucket created successfully.', 'media-cloud-sync'),
                'data'    => [
                    'Name'         => $bucket_name,
                    'CreationDate' => date('Y-m-d\TH:i:s\Z'),
                ],
                'code'    => 200,
                'success' => true,
            ];
        } catch (AwsException $ex) {
            return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
        } catch (S3Exception $ex) {
            return ['message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }

    /**
     * Check Bucket Write Permission
     * @since 1.0.0
     */
    public function verifyObjectWritePermission( $config = [], $bucketConfig = [] ) {
        $account_id = isset($config['account_id']) ? $config['account_id'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $region     = isset($config['region']) ? $config['region'] : '';

        if (Service::has_missing_fields([$access_key, $secret_key, $bucket_name, $account_id])) {
            return ['message' => __('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $cloudflareClient = new S3Client([
                'version'                       => '2006-03-01',
                'region'                        => 'auto',
                'endpoint'                      => $this->get_domain($account_id, $region),
                'signature_version'             => 'v4',
                'use_path_style_endpoint'       => true,
                'use_accelerate_endpoint'       => false,
                'use_aws_shared_config_files'   => false,
                'credentials'                   => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            $object_key = Utils::get_permission_check_object_key();

            // create an empty object to test write permission
            $cloudflareClient->putObject([
                'Bucket' => $bucket_name,
                'Key'    => $object_key,
                'Body'   => 'This is a test object to check write permission.',
            ]);

            // Check if the object was created successfully
            if ($this->exists( $object_key, $bucket_name, $cloudflareClient )) {
                return ['message' => __('Bucket write permission verified successfully', 'media-cloud-sync'), 'code' => 200, 'success' => true];
            } else {
                return ['message' => __('Bucket write permission not verified', 'media-cloud-sync'), 'code' => 200, 'success' => false];
            }
           
        } catch (AwsException $ex) {
            return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
        } catch (S3Exception $ex) {
            return ['message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }

    /**
     * Check Bucket Delete Permission
     * @since 1.0.0
     */
    public function verifyObjectDeletePermission( $config = [], $bucketConfig = [] ) {
        $account_id = isset($config['account_id']) ? $config['account_id'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $region     = isset($config['region']) ? $config['region'] : '';

        if (Service::has_missing_fields([$access_key, $secret_key, $bucket_name, $account_id])) {
            return ['message' => __('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $cloudflareClient = new S3Client([
                'version'                       => '2006-03-01',
                'region'                        => 'auto',
                'endpoint'                      => $this->get_domain($account_id, $region),
                'signature_version'             => 'v4',
                'use_path_style_endpoint'       => true,
                'use_accelerate_endpoint'       => false,
                'use_aws_shared_config_files'   => false,
                'credentials'                   => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            $object_key = Utils::get_permission_check_object_key();

            // Create a dummy object to check delete permission
            $cloudflareClient->deleteObject([
                'Bucket' => $bucket_name,
                'Key'    => $object_key,
            ]);
          
            // Check if the object was created successfully
            if (!$this->exists($object_key, $bucket_name, $cloudflareClient)) {
                return ['message' => __('Bucket delete permission verified successfully', 'media-cloud-sync'), 'code' => 200, 'success' => true];
            } else {
                return ['message' => __('Bucket delete permission not verified', 'media-cloud-sync'), 'code' => 200, 'success' => false];
            }
           
        } catch (AwsException $ex) {
            return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
        } catch (S3Exception $ex) {
            return ['message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
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
        
        if (Service::has_missing_fields([$this->cloudflareClient, $this->bucket_name])) {
            $result['message'] = __('Invalid Request', 'media-cloud-sync');
            return ['message' => __('Invalid Request', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        }

        if (Utils::delivery_needs_domain(Utils::get_settings())) {
            return ['message' => Utils::delivery_domain_message(), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        }

        try {
            $object_key = Utils::get_read_check_object_key();

            // Check if the object was created successfully
            if (!$this->exists($object_key)) {
                // Create a dummy object to check write permission
                $this->cloudflareClient->putObject([
                    'Bucket' => $this->bucket_name,
                    'Key'    => $object_key,
                    'Body'   => 'This is a test object to check permission.',
                    'ContentType' => 'text/plain',
                    'CacheControl' => 'no-cache, no-store, must-revalidate',
                ]);
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
                $result['message'] = __('Objects are accessible to Read', 'media-cloud-sync');
            } else if ($status_code === 403) {
                $result['status'] = false;
                if(isset($this->cdnConfig['service']) && $this->cdnConfig['service'] == $this->service) {
                    $result['message'] = __('Access Denied. Please check your bucket policy. Public Read Access is required.', 'media-cloud-sync');
                } else {
                    $result['message'] = __('Access Denied. Please check your bucket policy', 'media-cloud-sync');
                }
            } else if ($status_code === 404) {
                $result['status'] = false;
                $result['message'] = __('Object not found. Please check your bucket policy', 'media-cloud-sync');
            } else if ($status_code === 500) {
                $result['status'] = false;
                $result['message'] = __('Internal Server error. Please check your bucket policy', 'media-cloud-sync');
            } else {
                $result['status'] = false;
                $result['message'] = __('Objects are not accessible to read', 'media-cloud-sync');
            }

            $this->deleteSingle($object_key);
            return [
                'message' => $result['message'],
                'code'    => 200,
                'success' => $result['status'],
                'lastChecked' => $result['lastChecked'],
            ];
        } catch (AwsException $ex) {
            $result['message'] = $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false , 'lastChecked' => time()];
        } catch (S3Exception $ex) {
            $result['message'] = $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false , 'lastChecked' => time()];
        } catch (Exception $ex) {
            $result['message'] = $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false , 'lastChecked' => time()];
        }
    }

    /**
     * isConfigured Function To Identify the configurations are correct
     * @since 1.0.0
     */
    public function isConfigured(){
        if ($this->cloudflareClient) {
            try {
                $this->cloudflareClient->listObjectsV2([
                    'Bucket' => $this->token . '_dummy-bucket-for-auth-check'
                ]);
            
                // If we reach here, the credentials are valid
               return true;
            } catch (AwsException $ex) {
                $code = $ex->getAwsErrorCode();
            
                $validErrors = [
                    'AccessDenied',
                    'NoSuchBucket',
                    'AllAccessDisabled',
                    'AuthorizationHeaderMalformed',
                    'PermanentRedirect',
                    'InvalidBucketName'
                ];
        
                if (in_array($code, $validErrors)) {
                    // If we reach here, the credentials are valid
                    return true;
                } else {
                    return false;
                }
            } catch (S3Exception $ex) {
                return false;
            } catch (Exception $ex) {
                return false;
            }
        }
        return false;
    }

    /**
     * Check the object exists
     * @since 1.1.8
     */
    public function exists($key, $bucket_name = '', $client = null) {
        if (!$key) return false;

        try {
            $client = $client ?? $this->cloudflareClient;
            $client->headObject([
                'Bucket' => $bucket_name ? $bucket_name : $this->bucket_name,
                'Key'    => $key,
            ]);
            return true;
        } catch (AwsException $ex) {
            return false;
        } catch (S3Exception $ex) {
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * List Objects — $delimiter = null gives a flat/recursive listing instead of one folder level.
     * @since 1.3.13
     */
    public function listObjects($prefix = '', $continuationToken = null, $maxKeys = 1000, $delimiter = '/') {
        if (!$this->cloudflareClient) {
            return ['success' => false, 'code' => 200, 'message' => __('Client not configured', 'media-cloud-sync'), 'folders' => [], 'objects' => [], 'next_token' => null];
        }
        try {
            $params = ['Bucket' => $this->bucket_name, 'MaxKeys' => $maxKeys];
            if (!empty($delimiter)) {
                $params['Delimiter'] = $delimiter;
            }
            if (!empty($prefix)) {
                $params['Prefix'] = $prefix;
            }
            if (!empty($continuationToken)) {
                $params['ContinuationToken'] = $continuationToken;
            }

            $result  = $this->cloudflareClient->listObjectsV2($params);
            $folders = [];
            foreach (($result['CommonPrefixes'] ?? []) as $common) {
                $folders[] = $common['Prefix'];
            }
            $objects = [];
            foreach (($result['Contents'] ?? []) as $object) {
                if ($object['Key'] === $prefix) {
                    continue; // the folder placeholder object itself, not a file
                }
                $objects[] = [
                    'key'           => $object['Key'],
                    'size'          => (int) $object['Size'],
                    'last_modified' => $object['LastModified'] ? $object['LastModified']->format(DATE_ATOM) : '',
                ];
            }

            return [
                'success'    => true,
                'code'       => 200,
                'message'    => '',
                'folders'    => $folders,
                'objects'    => $objects,
                'next_token' => !empty($result['IsTruncated']) ? ($result['NextContinuationToken'] ?? null) : null,
            ];
        } catch (AwsException $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage(), 'folders' => [], 'objects' => [], 'next_token' => null];
        } catch (S3Exception $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage(), 'folders' => [], 'objects' => [], 'next_token' => null];
        } catch (Exception $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage(), 'folders' => [], 'objects' => [], 'next_token' => null];
        }
    }

    /**
     * Upload Single
     * @since 1.0.0
     */
    public function uploadSingle($absolute_source_path, $relative_source_path, $prefix='', $is_private = false) {
        if (
            isset($absolute_source_path) && !empty($absolute_source_path) &&
            isset($relative_source_path) && !empty($relative_source_path)
        ) {
            $file_name = wp_basename( $relative_source_path );
            if ($file_name) {
                $upload_path = Utils::generate_object_key($relative_source_path, $prefix, $is_private);
                if ($upload_path === false) {
                    return [
                        'success' => false,
                        'code'    => 200,
                        'message' => __('This file is marked private, but the private-media add-on is not currently active — reupload skipped to avoid exposing it.', 'media-cloud-sync')
                    ];
                }
                return $this->execute_upload($absolute_source_path, $upload_path);
            }
        }

        return [
            'success' => false,
            'code'    => 200,
            'message' => __('Insufficient Data. Please try again', 'media-cloud-sync')
        ];
    }

    /**
     * Upload a local file to an exact destination key (no Utils::generate_object_key() derivation).
     * @since 1.4.0
     */
    public function uploadObjectAtKey($absolute_source_path, $key) {
        return $this->execute_upload($absolute_source_path, $key);
    }

    /**
     * Build an unexecuted ObjectUploader for the given local file and destination key, using
     * this plugin's own multipart threshold rather than the SDK's 16MB default. $acl is
     * explicitly null — R2 doesn't support S3-style ACLs — and the before_initiate/
     * before_upload/before_complete hooks strip it via strip_acl(), since ObjectUploader's
     * multipart delegation still threads $options through to MultipartUploader.
     * @since 1.4.0
     */
    private function build_object_uploader($absolute_source_path, $key, $options = []) {
        $handle = fopen($absolute_source_path, 'rb');
        $params = [];
        $cache_control = Utils::get_cache_control_header();
        if ($cache_control) {
            $params['CacheControl'] = $cache_control;
        }
        $options += [
            'mup_threshold'   => Schema::getConstant('CLOUDFLARE_R2_MULTIPART_MIN_FILE_SIZE'),
            'params'          => $params,
            'before_initiate' => function ($params) {
                return $this->strip_acl($params);
            },
            'before_upload' => function ($params) {
                return $this->strip_acl($params);
            },
            'before_complete' => function ($params) {
                return $this->strip_acl($params);
            },
        ];
        return new ObjectUploader($this->cloudflareClient, $this->bucket_name, $key, $handle, null, $options);
    }

    /**
     * Run an ObjectUploader synchronously and normalize the result shape. Retries up to
     * 3 attempts on MultipartUploadException, resuming from the failed attempt's saved
     * state rather than restarting the whole upload — same retry contract uploadSingle()
     * had before the ObjectUploader swap.
     * @since 1.4.0
     */
    private function execute_upload($absolute_source_path, $key) {
        $max_attempts = 3;
        $attempt = 0;
        $options = [];

        while (true) {
            $attempt++;
            try {
                $this->build_object_uploader($absolute_source_path, $key, $options)->upload();
                return [
                    'success'  => true,
                    'code'     => 200,
                    'file_url' => $this->generate_file_url($key),
                    'key'      => $key,
                    'message'  => __('File Uploaded Successfully', 'media-cloud-sync')
                ];
            } catch (MultipartUploadException $e) {
                if ($attempt >= $max_attempts) {
                    return [
                        'success' => false,
                        'code'    => 200,
                        'message' => $e->getMessage()
                    ];
                }
                $options = ['state' => $e->getState()];
            } catch (AwsException $e) {
                return [
                    'success' => false,
                    'code'    => 200,
                    'message' => $e->getMessage()
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'code'    => 200,
                    'message' => $e->getMessage()
                ];
            }
        }
    }

    /**
     * Exclude 'ACL' parameter from given params.
     *
     * @param Command|array $params Command or array of parameters.
     *
     * @return Command|array Modified params.
     */
    private function strip_acl($params) {
        if ($params instanceof Command) {
            // Mutate in place, not a clone — the SDK's before_* hooks (both here and in
            // MultipartUploader) call this and discard the return value, relying on the
            // same Command object being modified, not a modified copy being returned.
            if ($params->hasParam('ACL')) {
                unset($params['ACL']);
            }
        } else if (is_array($params)) {
            if (isset($params['ACL'])) {
                unset($params['ACL']);
            }
        }
        return $params;
    }

    /**
     * Save object to server
     * @since 1.0.0
     */
    public function object_to_server($key, $save_path) {
        if(!$this->cloudflareClient) return false;
        try {
            $getObject = $this->cloudflareClient->getObject([
                'Bucket' => $this->bucket_name,
                'Key'    => $key,
                'SaveAs' => $save_path
            ]);
            if (file_exists($save_path)) {
                return true;
            }
        } catch (AwsException $e) {
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
        if(!$this->cloudflareClient) return false;
        try {
            $result = $this->cloudflareClient->getObject([
                'Bucket' => $this->bucket_name,
                'Key'    => $key,
            ]);
            return (string) $result['Body'];
        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * Deletes the live object, then best-effort purges every historical version too — a
     * plain deleteSingle() on a versioned bucket only adds a delete marker, leaving prior
     * versions (and the storage they use) behind at the old key. The live delete happens
     * unconditionally first: some providers (Cloudflare R2 itself doesn't support
     * ListObjectVersions at all — confirmed via a live 501 "NotImplemented" response) can't
     * do the version-history part, and the object must still end up gone either way.
     * @since 1.3.14
     */
    public function purge_all_versions($key) {
        if (!$this->cloudflareClient) {
            return ['success' => false, 'code' => 200, 'message' => __('Client not configured', 'media-cloud-sync')];
        }

        try {
            $this->cloudflareClient->deleteObject([
                'Bucket' => $this->bucket_name,
                'Key'    => $key,
            ]);
        } catch (AwsException $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage()];
        }

        // Best-effort only from here — providers that don't support version listing (R2)
        // simply skip this part; the live object above is already gone regardless.
        try {
            $objects = [];
            $marker  = null;
            do {
                $args = ['Bucket' => $this->bucket_name, 'Prefix' => $key];
                if ($marker) {
                    $args['KeyMarker']       = $marker['key'];
                    $args['VersionIdMarker'] = $marker['version'];
                }
                $result = $this->cloudflareClient->listObjectVersions($args);
                foreach (array_merge($result['Versions'] ?? [], $result['DeleteMarkers'] ?? []) as $version) {
                    if (($version['Key'] ?? null) === $key) {
                        $objects[] = ['Key' => $key, 'VersionId' => $version['VersionId']];
                    }
                }
                $marker = !empty($result['IsTruncated'])
                    ? ['key' => $result['NextKeyMarker'], 'version' => $result['NextVersionIdMarker']]
                    : null;
            } while ($marker);

            foreach (array_chunk($objects, 1000) as $chunk) {
                $this->cloudflareClient->deleteObjects([
                    'Bucket' => $this->bucket_name,
                    'Delete' => ['Objects' => $chunk],
                ]);
            }
        } catch (AwsException $e) {
            // Version history cleanup unsupported/failed — not fatal, live object is gone.
        }

        return ['success' => true, 'code' => 200, 'message' => __('Purged Successfully', 'media-cloud-sync')];
    }

    /**
     * Copy to new path
     * @since 1.3.4
     */
    // Trusts copyObject()'s own success/failure rather than pre/post-verifying with extra
    // exists() HEAD requests — each one is a full network round-trip, and with move/copy
    // processing keys sequentially, three extra round-trips per file adds up fast on a
    // folder with many files. copyObject() itself throws (caught below) if the source is
    // missing or the copy otherwise fails, so nothing is lost by not checking first.
    public function copy_to_new_path($key, $new_path) {
        if (!$this->cloudflareClient) {
            return [
                'message' => __('Client not configured', 'media-cloud-sync'),
                'code'    => 200,
                'success' => false
            ];
        }
        try {
            $this->cloudflareClient->copyObject([
                'Bucket'            => $this->bucket_name,
                'CopySource'        => "{$this->bucket_name}/{$key}",
                'Key'               => $new_path,
                'MetadataDirective' => 'COPY',
            ]);
            return [
                'success' => true,
                'code'    => 200,
                'message' => __('File copied successfully', 'media-cloud-sync')
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'code'    => 200,
                'message' => $e->getMessage()
            ];
        }
    }

    // Like copy_to_new_path() but into an explicit (possibly different) bucket — needs write
    // access there too, so callers should fall back to download+upload on failure.
    public function copy_to_bucket($key, $new_key, $dest_bucket) {
        if (!$this->cloudflareClient) {
            return [
                'message' => __('Client not configured', 'media-cloud-sync'),
                'code'    => 200,
                'success' => false
            ];
        }
        try {
            $this->cloudflareClient->copyObject([
                'Bucket'            => $dest_bucket,
                'CopySource'        => "{$this->bucket_name}/{$key}",
                'Key'               => $new_key,
                'MetadataDirective' => 'COPY',
            ]);
            return [
                'success' => true,
                'code'    => 200,
                'message' => __('File copied successfully', 'media-cloud-sync')
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'code'    => 200,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete Single
     * @since 1.0.0
     */
    public function deleteSingle($key) {
        $result = array();
        if (!$this->cloudflareClient) {
            return array(
                'success' => false,
                'code'    => 200,
                'message' => __('Client not configured', 'media-cloud-sync')
            );
        }
        if (isset($key) && !empty($key)) {
            try {
                $this->cloudflareClient->deleteObject([
                    'Bucket' => $this->bucket_name,
                    'Key'    => $key
                ]);

                if (!$this->exists($key)) {
                    $result = array(
                        'success' => true,
                        'code'    => 200,
                        'message' => __('Deleted Successfully', 'media-cloud-sync')
                    );
                } else {
                    $result = array(
                        'success' => false,
                        'code'    => 200,
                        'message' => __('File not deleted', 'media-cloud-sync')
                    );
                }
            } catch (AwsException $e) {
                $result = array(
                    'success' => false,
                    'code'    => 200,
                    'message' => $e->getMessage()
                );
            }
        } else {
            $result = array(
                'success' => false,
                'code'    => 200,
                'message' => __('Insufficient Data. Please try again', 'media-cloud-sync')
            );
        }
        return $result;
    }

    /**
     * get private URL (presigned)
     * @since 1.0.0
     */
    public function get_private_url($key) {
        $result = array();
        if (!$this->cloudflareClient) {
            return array(
                'success' => false,
                'code'    => 200,
                'message' => __('Client not configured', 'media-cloud-sync')
            );
        }
        if (isset($key) && !empty($key)) {
            try {
                $cmd = $this->cloudflareClient->getCommand('GetObject', [
                    'Bucket' => $this->bucket_name,
                    'Key'    => $key
                ]);

                $expires = isset($this->settings['private_url_expire']) ? $this->settings['private_url_expire'] : 20;

                $request = $this->cloudflareClient->createPresignedRequest($cmd, sprintf('+%s minutes', $expires));

                if ($privateUrl = (string)$request->getUri()) {
                    $result = array(
                        'success'   => true,
                        'code'      => 200,
                        'file_url'  => $privateUrl,
                        'message'   => __('Got Private URL Successfully', 'media-cloud-sync')
                    );
                } else {
                    $result = array(
                        'success' => false,
                        'code'    => 200,
                        'message' => __('Error getting Private URL', 'media-cloud-sync')
                    );
                }
            } catch (AwsException $e) {
                $result = array(
                    'success' => false,
                    'code'    => 200,
                    'message' => $e->getMessage()
                );
            } catch (S3Exception $e) {
                $result = array(
                    'success' => false,
                    'code'    => 200,
                    'message' => $e->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync')
                );
            } catch (Exception $e) {
                $result = array(
                    'success' => false,
                    'code'    => 200,
                    'message' => $e->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync')
                );
            }
        } else {
            $result = array(
                'success' => false,
                'code'    => 200,
                'message' => __('Insufficient Data. Please try again', 'media-cloud-sync')
            );
        }
        return $result;
    }

    /**
     * Generate file URL
     */
    public function generate_file_url($key){
        $domain = $this->get_domain();

        return apply_filters('wpmcs_generate_do_file_url',
            $domain . '/' . $this->bucket_name . '/' . $key,
            $domain, 
            $this->bucket_name, 
            $key
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
     * @since 1.0.0
     * @return string
     */
    public function get_domain($account_id = '', $region = 'default') {
        if (empty($account_id)) {
            $account_id = isset($this->config['account_id']) ? $this->config['account_id'] : '';
        }
        if (!empty($account_id)) {
            $region = ($region === 'default') && isset($this->config['region']) ? $this->config['region'] : $region;
            if (!empty($region)) {
                return "https://{$account_id}.{$region}.r2.cloudflarestorage.com";
            }
            return "https://{$account_id}.r2.cloudflarestorage.com";
        }
        // fallback
        return "https://r2.cloudflarestorage.com";
    }

    /**
     * Sets Cache-Control and the extension's Content-Type on an existing object, by copying it onto itself.
     * @return array {success, code, message, changed}
     * @since 1.5.0
     */
    public function update_object_headers($key, $cache_control) {
        $client = $this->get_client();
        if (!$client || !$key) {
            return ['success' => false, 'code' => 200, 'changed' => false, 'message' => __('Client not configured', 'media-cloud-sync')];
        }
        try {
            $head    = $client->headObject(['Bucket' => $this->bucket_name, 'Key' => $key]);
            $current = [(string) $head['CacheControl'], (string) $head['ContentType']];
            $type    = Utils::content_type_for($current[1], MimeType::fromFilename($key));
            if ($current === [(string) $cache_control, $type]) {
                return ['success' => true, 'code' => 200, 'changed' => false, 'message' => __('Headers already up to date', 'media-cloud-sync')];
            }
            if ((int) $head['ContentLength'] > 5 * GB_IN_BYTES) {
                return ['success' => false, 'code' => 200, 'changed' => false, 'message' => __('Files over 5 GB can\'t be updated in place.', 'media-cloud-sync')];
            }
            // REPLACE drops whatever isn't sent again, so carry over the rest of the object's settings.
            $params = [
                'Bucket'            => $this->bucket_name,
                'Key'               => $key,
                'CopySource'        => $this->bucket_name . '/' . S3Client::encodeKey($key),
                'MetadataDirective' => 'REPLACE',
                'Metadata'          => $head['Metadata'] ?: [],
                'ContentType'       => $type,
            ];
            if ($cache_control) {
                $params['CacheControl'] = $cache_control;
            }
            if ($type === '') {
                unset($params['ContentType']);
            }
            foreach (['ContentDisposition', 'ContentEncoding', 'ContentLanguage', 'StorageClass', 'ServerSideEncryption', 'SSEKMSKeyId'] as $field) {
                if (!empty($head[$field])) {
                    $params[$field] = $head[$field];
                }
            }
            $client->copyObject($params);
            return ['success' => true, 'code' => 200, 'changed' => true, 'message' => __('Headers updated', 'media-cloud-sync')];
        } catch (AwsException $e) {
            $message = $e->getStatusCode() == 404 ? __('Not found in the bucket', 'media-cloud-sync') : ($e->getAwsErrorMessage() ?: $e->getMessage());
            return ['success' => false, 'code' => 200, 'changed' => false, 'message' => $message];
        } catch (Exception $e) {
            return ['success' => false, 'code' => 200, 'changed' => false, 'message' => $e->getMessage()];
        }
    }

    // Storage classes files can be moved to, value => label.
    public function get_storage_classes() {
        return [
            'STANDARD'    => __('Standard', 'media-cloud-sync'),
            'STANDARD_IA' => __('Infrequent Access', 'media-cloud-sync'),
        ];
    }

    // Moves an existing object to another storage class in place; 'changed' is false when it already had it.
    public function change_storage_class($key, $class) {
        if (!$this->get_client() || !$key) {
            return ['success' => false, 'code' => 200, 'changed' => false, 'message' => __('Client not configured', 'media-cloud-sync')];
        }
        return $this->change_storage_classes([$key], $class)[$key];
    }

    // change_storage_class() for several objects, a few requests at a time; results keyed by object key.
    public function change_storage_classes($keys, $class) {
        $client = $this->get_client();
        $keys   = array_values(array_unique(array_filter((array) $keys)));
        if (!$client) {
            return array_fill_keys($keys, ['success' => false, 'code' => 200, 'changed' => false, 'message' => __('Client not configured', 'media-cloud-sync')]);
        }
        $results  = [];
        $promises = function () use ($client, $keys, $class, &$results) {
            foreach ($keys as $key) {
                yield $client->headObjectAsync(['Bucket' => $this->bucket_name, 'Key' => $key])
                    ->then(function ($head) use ($client, $key, $class, &$results) {
                        $skip = $this->storage_class_skip($head, $class);
                        if ($skip) {
                            $results[$key] = $skip;
                            return null;
                        }
                        return $client->copyObjectAsync($this->storage_class_copy_params($key, $class, $head))->then(function () use ($key, &$results) {
                            $results[$key] = ['success' => true, 'code' => 200, 'changed' => true, 'message' => __('Storage class changed', 'media-cloud-sync')];
                        });
                    })
                    ->otherwise(function ($e) use ($key, &$results) {
                        $results[$key] = $this->storage_class_error($e);
                    });
            }
        };
        Each::ofLimit($promises(), 5)->wait();
        return $results;
    }

    // The result when an object can't or needn't change class, else null.
    private function storage_class_skip($head, $class) {
        $current = (string) ($head['StorageClass'] ?: 'STANDARD');
        if ($current === $class) {
            return ['success' => true, 'code' => 200, 'changed' => false, 'message' => __('Already in this storage class', 'media-cloud-sync')];
        }
        if (in_array($current, ['GLACIER', 'DEEP_ARCHIVE'], true)) {
            return ['success' => false, 'code' => 200, 'changed' => false, 'message' => __('Archived in Glacier: restore it in your storage console first.', 'media-cloud-sync')];
        }
        if ((int) $head['ContentLength'] > 5 * GB_IN_BYTES) {
            return ['success' => false, 'code' => 200, 'changed' => false, 'message' => __('Files over 5 GB can\'t be changed in place.', 'media-cloud-sync')];
        }
        return null;
    }

    private function storage_class_copy_params($key, $class, $head) {
        $params = [
            'Bucket'            => $this->bucket_name,
            'Key'               => $key,
            'CopySource'        => $this->bucket_name . '/' . S3Client::encodeKey($key),
            'MetadataDirective' => 'COPY',
            'StorageClass'      => $class,
        ];
        foreach (['ServerSideEncryption', 'SSEKMSKeyId'] as $field) {
            if (!empty($head[$field])) {
                $params[$field] = $head[$field];
            }
        }
        return $params;
    }

    private function storage_class_error($e) {
        if ($e instanceof AwsException) {
            $message = $e->getStatusCode() == 404 ? __('Not found in the bucket', 'media-cloud-sync') : ($e->getAwsErrorMessage() ?: $e->getMessage());
        } else {
            $message = $e instanceof Exception ? $e->getMessage() : (string) $e;
        }
        return ['success' => false, 'code' => 200, 'changed' => false, 'message' => $message];
    }

    // The plugin's lifecycle rule as ['class' => ..., 'days' => ...], or null when there is none.
    public function get_storage_class_rule() {
        $client = $this->get_client();
        if (!$client) {
            return ['success' => false, 'code' => 200, 'message' => __('Client not configured', 'media-cloud-sync')];
        }
        try {
            $rules = (array) ($client->getBucketLifecycleConfiguration(['Bucket' => $this->bucket_name])['Rules'] ?? []);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NoSuchLifecycleConfiguration') {
                return ['success' => true, 'code' => 200, 'rule' => null];
            }
            if ($e->getAwsErrorCode() === 'AccessDenied') {
                return ['success' => false, 'code' => 200, 'message' => $this->lifecycle_denied_message()];
            }
            return ['success' => false, 'code' => 200, 'message' => $e->getAwsErrorMessage() ?: $e->getMessage()];
        }
        foreach ($rules as $rule) {
            if (strpos((string) ($rule['ID'] ?? ''), 'media-cloud-sync-storage-class') === 0 && !empty($rule['Transitions'][0]['StorageClass'])) {
                return ['success' => true, 'code' => 200, 'rule' => ['class' => $rule['Transitions'][0]['StorageClass'], 'days' => (int) ($rule['Transitions'][0]['Days'] ?? 0)]];
            }
        }
        return ['success' => true, 'code' => 200, 'rule' => null];
    }

    // Sets the plugin's lifecycle rules moving files under $prefixes to $class after $days (an empty $class removes them); other rules are kept.
    public function set_storage_class_rule($prefixes, $class = '', $days = 0) {
        $client = $this->get_client();
        if (!$client) {
            return ['success' => false, 'code' => 200, 'message' => __('Client not configured', 'media-cloud-sync')];
        }
        $rule_id = 'media-cloud-sync-storage-class';
        try {
            $rules = [];
            try {
                $rules = (array) ($client->getBucketLifecycleConfiguration(['Bucket' => $this->bucket_name])['Rules'] ?? []);
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() !== 'NoSuchLifecycleConfiguration') {
                    throw $e;
                }
            }
            $rules = array_values(array_filter($rules, function ($rule) use ($rule_id) {
                return strpos((string) ($rule['ID'] ?? ''), $rule_id) !== 0;
            }));
            if ($class !== '') {
                foreach (array_values($prefixes) as $i => $prefix) {
                    $rules[] = [
                        'ID'          => $rule_id . '-' . ($i + 1),
                        'Status'      => 'Enabled',
                        'Filter'      => ['Prefix' => $prefix],
                        'Transitions' => [['Days' => (int) $days, 'StorageClass' => $class]],
                    ];
                }
            }
            if (empty($rules)) {
                $client->deleteBucketLifecycle(['Bucket' => $this->bucket_name]);
            } else {
                $client->putBucketLifecycleConfiguration(['Bucket' => $this->bucket_name, 'LifecycleConfiguration' => ['Rules' => $rules]]);
            }
            return ['success' => true, 'code' => 200, 'message' => $class !== '' ? __('Automatic storage class rule saved', 'media-cloud-sync') : __('Automatic storage class rule removed', 'media-cloud-sync')];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'AccessDenied') {
                return ['success' => false, 'code' => 200, 'message' => $this->lifecycle_denied_message()];
            }
            return ['success' => false, 'code' => 200, 'message' => $e->getAwsErrorMessage() ?: $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage()];
        }
    }

    private function lifecycle_denied_message() {
        return sprintf(
            /* translators: %s: bucket name */
            __('Your storage keys are not allowed to read or change the bucket\'s lifecycle rules. Use an API token with Admin Read & Write permission, or add the rule in the Cloudflare dashboard under R2 › %s › Settings › Object lifecycle rules.', 'media-cloud-sync'),
            $this->bucket_name
        );
    }

    // Allowed origins of the plugin's CORS rule, or null when the bucket has none.
    public function get_cors_rule() {
        $client = $this->get_client();
        if (!$client) {
            return ['success' => false, 'code' => 200, 'message' => __('Client not configured', 'media-cloud-sync')];
        }
        try {
            $rules = (array) ($client->getBucketCors(['Bucket' => $this->bucket_name])['CORSRules'] ?? []);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NoSuchCORSConfiguration') {
                return ['success' => true, 'code' => 200, 'rule' => null];
            }
            if ($e->getAwsErrorCode() === 'AccessDenied') {
                return ['success' => false, 'code' => 200, 'message' => $this->cors_denied_message()];
            }
            return ['success' => false, 'code' => 200, 'message' => $e->getAwsErrorMessage() ?: $e->getMessage()];
        }
        foreach ($rules as $rule) {
            if ($this->is_plugin_cors_rule($rule)) {
                return ['success' => true, 'code' => 200, 'rule' => ['origins' => array_values((array) ($rule['AllowedOrigins'] ?? []))]];
            }
        }
        return ['success' => true, 'code' => 200, 'rule' => null];
    }

    // Sets the plugin's CORS rule (GET/HEAD from $origins; none removes it); other rules are kept.
    public function set_cors_rule($origins) {
        $client = $this->get_client();
        if (!$client) {
            return ['success' => false, 'code' => 200, 'message' => __('Client not configured', 'media-cloud-sync')];
        }
        try {
            $rules = [];
            try {
                $rules = (array) ($client->getBucketCors(['Bucket' => $this->bucket_name])['CORSRules'] ?? []);
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() !== 'NoSuchCORSConfiguration') {
                    throw $e;
                }
            }
            $rules = array_values(array_filter($rules, function ($rule) {
                return !$this->is_plugin_cors_rule($rule);
            }));
            if (!empty($origins)) {
                $rules[] = [
                    'ID'             => 'media-cloud-sync',
                    'AllowedOrigins' => array_values($origins),
                    'AllowedMethods' => ['GET', 'HEAD'],
                    'AllowedHeaders' => ['*'],
                    'MaxAgeSeconds'  => 86400,
                ];
            }
            if (empty($rules)) {
                $client->deleteBucketCors(['Bucket' => $this->bucket_name]);
            } else {
                $client->putBucketCors(['Bucket' => $this->bucket_name, 'CORSConfiguration' => ['CORSRules' => $rules]]);
            }
            return ['success' => true, 'code' => 200, 'message' => !empty($origins) ? __('CORS rule saved', 'media-cloud-sync') : __('CORS rule removed', 'media-cloud-sync')];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'AccessDenied') {
                return ['success' => false, 'code' => 200, 'message' => $this->cors_denied_message()];
            }
            return ['success' => false, 'code' => 200, 'message' => $e->getAwsErrorMessage() ?: $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage()];
        }
    }

    // Ours by ID; without IDs, by its exact read-only shape (a hand-made rule of that shape counts as ours).
    private function is_plugin_cors_rule($rule) {
        if (isset($rule['ID'])) {
            return $rule['ID'] === 'media-cloud-sync';
        }
        $methods = (array) ($rule['AllowedMethods'] ?? []);
        sort($methods);
        return $methods === ['GET', 'HEAD'] && (int) ($rule['MaxAgeSeconds'] ?? 0) === 86400 && (array) ($rule['AllowedHeaders'] ?? []) === ['*'];
    }

    private function cors_denied_message() {
        return sprintf(
            /* translators: %s: bucket name */
            __('Your storage keys are not allowed to read or change the bucket\'s CORS policy. Use an API token with Admin Read & Write permission, or add the policy in the Cloudflare dashboard under R2 › %s › Settings › CORS Policy.', 'media-cloud-sync'),
            $this->bucket_name
        );
    }

    /** Exposes the already-constructed SDK client for StreamWrapper's registration — avoids reconstructing one from credentials. */
    public function get_client() {
        return $this->cloudflareClient;
    }
}
