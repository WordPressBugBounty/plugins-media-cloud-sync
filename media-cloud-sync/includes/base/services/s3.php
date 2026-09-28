<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

// Libraries
use Dudlewebs\WPMCS\s3\Aws\S3\S3Client;
use Dudlewebs\WPMCS\s3\Aws\Exception\AwsException;
use Dudlewebs\WPMCS\s3\Aws\S3\Exception\S3Exception;
use Dudlewebs\WPMCS\s3\Aws\S3\MultipartUploader;
use Dudlewebs\WPMCS\s3\Aws\Exception\MultipartUploadException;
use Dudlewebs\WPMCS\s3\Aws\S3\ObjectUploader;
use Dudlewebs\WPMCS\s3\Aws\Command;
use Exception;

class S3 {
    private $assets_url;
    private $version;
    private $token;

    protected $config;
    protected $bucketConfig;
    protected $settings;
    protected $credentials;
    protected $bucket_name;
    protected $cdnConfig;

    public $service     = 's3';
    public $s3Client    = false;

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
            isset($this->config['region']) && !empty($this->config['region']) &&
            isset($this->config['access_key']) && !empty($this->config['access_key']) &&
            isset($this->config['secret_key']) && !empty($this->config['secret_key'])
        ) {
            $this->s3Client = new S3Client([
                'version'     => '2006-03-01',
                'region'      => $this->config['region'],
                'use_accelerate_endpoint' => isset($this->bucketConfig['transfer_acceleration'])
                                                ? $this->bucketConfig['transfer_acceleration'] : false,
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
    public function verifyCredentials($config = []) {
        $region     = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';

        if (!Service::has_missing_fields([$region, $access_key, $secret_key])) {
            try {
                $s3Client = new S3Client([
                    'version'                       => '2006-03-01',
                    'region'                        => $region,
                    'use_aws_shared_config_files'   => false,
                    'credentials'                   => [
                        'key'    => $access_key,
                        'secret' => $secret_key,
                    ],
                ]);

                $result = [
                    'success' => false,
                    'code'    => 200,
                    'message' => esc_html__('Please check the authorization details', 'media-cloud-sync'),
                ];

                try {
                    $s3Client->listObjectsV2([
                        'Bucket' => $this->token . '_dummy-bucket-for-auth-check'
                    ]);
                
                    // If we reach here, the credentials are valid
                    $result = [
                        'success' => true,
                        'code'    => 200,
                        'message' => esc_html__('Credentials are valid', 'media-cloud-sync'),
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
                            'message' => esc_html__('Credentials are valid', 'media-cloud-sync'),
                        ];
                    }
                }

                if($result['success'] == false) {
                    return $result;
                }

                try {
                    $buckets = $s3Client->listBuckets();
                    $newBucketFormat = [];
                    if(isset($buckets['Buckets']) && !empty($buckets['Buckets'])){
                        foreach($buckets['Buckets'] as $bucket) {
                            if(isset($bucket['Name'])) {
                                $newBucketFormat[] = ['Name' => $bucket['Name'], 'CreationDate' => $bucket['CreationDate']];
                            }
                        }
                    }
                    $result['buckets_data']['buckets'] = $newBucketFormat;
                    $result['buckets_data']['message'] = esc_html__('Buckets listed successfully', 'media-cloud-sync');
                    $result['buckets_data']['status'] = true;
                } catch (S3Exception $e) {
                    $result ['buckets_data']['buckets'] = [];
                    $result ['buckets_data']['message'] = esc_html__('Unable to list buckets, Please check the bucket listing permission', 'media-cloud-sync');
                    $result ['buckets_data']['status'] = false;
                } catch (Exception $e) {
                    $result ['buckets_data']['buckets'] = [];
                    $result ['buckets_data']['message'] = esc_html__('Unable to list buckets, Please check the bucket listing permission', 'media-cloud-sync');
                    $result ['buckets_data']['status'] = false;
                }
                return $result;
            } 
            catch (S3Exception $ex) {
                return array('message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            } catch (Exception $ex) {
                return array('message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            }
        }
        return array('message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false);
    }

    /**
     * Verify Bucket Exist
     * @since 1.0.0
     * @return boolean
     */
    public function verifyBucketExist( $config = [], $bucketConfig = [] ) {
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $transfer_acceleration = isset($bucketConfig['transfer_acceleration']) ? $bucketConfig['transfer_acceleration'] : false;

        if (!Service::has_missing_fields([$region, $access_key, $secret_key, $bucket_name])) {
            try {
                $s3Client = new S3Client([
                    'version'                       => '2006-03-01',
                    'region'                        => $region,
                    'use_accelerate_endpoint'       => $transfer_acceleration,
                    'use_aws_shared_config_files'   => false,
                    'credentials'                   => [
                                                            'key'    => $access_key,
                                                            'secret' => $secret_key,
                                                        ],
                ]);

                //get S3 object
                $bucket_found = false;
                try {
                    $s3Client->getObject([
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
                    return array('message' => esc_html__('Bucket exist', 'media-cloud-sync'), 'code' => 200, 'success' => true);
                } else {
                    return array('message' => esc_html__("Bucket choosen does not exist / does not have read permission", 'media-cloud-sync'), 'code' => 200, 'success' => false);
                }
            } 
            catch (S3Exception $ex) {
                return array('message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            } catch (Exception $ex) {
                return array('message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            }
        }
        return array('message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false);
    }

    /**
     * Create Bucket
     * @since 1.0.0
     * @return boolean
     */
    public function createBucket( $config = [], $bucketConfig = [] ) {
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $transfer_acceleration = isset($bucketConfig['transfer_acceleration']) ? $bucketConfig['transfer_acceleration'] : false;

        if (Service::has_missing_fields([$region, $access_key, $secret_key, $bucket_name])) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $s3ClientConfig = [
                'version'                     => '2006-03-01',
                'region'                      => $region,
                'use_aws_shared_config_files' => false,
                'credentials'                 => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ];

            $s3Client = new S3Client($s3ClientConfig);

            // Create Bucket
            if ($region === 'us-east-1') {
                $s3Client->createBucket([
                    'Bucket' => $bucket_name,
                ]);
            } else {
                $s3Client->createBucket([
                    'Bucket' => $bucket_name,
                    'CreateBucketConfiguration' => [
                        'LocationConstraint' => $region,
                    ],
                ]);
            }
            
            try {
                $this->blockPublicAccess($bucket_name, false, $s3Client);
                $this->putBucketPolicy($bucket_name, $s3Client);
                $this->changeBucketOwnership($bucket_name, $s3Client);
                try {

                    $this->changeTransferAccilaration($bucket_name, $s3Client, $transfer_acceleration);

                    return [
                        'message' => esc_html__('Bucket created successfully. Choose bucket from list to select the bucket.', 'media-cloud-sync'),
                        'data'    => [
                            'Name'         => $bucket_name,
                            'CreationDate' => date('Y-m-d\TH:i:s\Z'),
                        ],
                        'code'    => 200,
                        'success' => true,
                    ];
                }  catch (AwsException $ex) {
                    return ['message' => esc_html__('Bucket created and made public. But the following error happened while setting the transfer accilaration,', 'media-cloud-sync') . ' ' . $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
                }
            } catch (AwsException $ex) {
                return ['message' => esc_html__('Bucket created. But the following error happened while setting the public access,', 'media-cloud-sync') . ' ' . $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
            }
        } catch (AwsException $ex) {
            return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
        } catch (S3Exception $ex) {
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }

    /**
     * Check Bucket Write Permission
     * @since 1.0.0
     */ 
    public function verifyObjectWritePermission( $config = [], $bucketConfig = [] ) {
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $transfer_acceleration = isset($bucketConfig['transfer_acceleration']) ? $bucketConfig['transfer_acceleration'] : false;

        if (Service::has_missing_fields([$region, $access_key, $secret_key, $bucket_name])) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $s3ClientConfig = [
                'version'                     => '2006-03-01',
                'region'                      => $region,
                'use_accelerate_endpoint'     => $transfer_acceleration,
                'use_aws_shared_config_files' => false,
                'credentials'                 => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ];

            $s3Client = new S3Client($s3ClientConfig);

            $object_key = Utils::get_permission_check_object_key();

            
            // Create a dummy object to check write permission
            $s3Client->putObject([
                'Bucket' => $bucket_name,
                'Key'    => $object_key,
                'Body'   => 'This is a test object to check write permission.',
            ]);
            // Check if the object was created successfully
            if ($this->exists($object_key, $bucket_name, $s3Client)) {
                return ['message' => esc_html__('Bucket write permission verified successfully', 'media-cloud-sync'), 'code' => 200, 'success' => true];
            } else {
                return ['message' => esc_html__('Bucket write permission not verified', 'media-cloud-sync'), 'code' => 200, 'success' => false];
            }
           
        } catch (AwsException $ex) {
            return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
        } catch (S3Exception $ex) {
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }



    /**
     * Check Bucket Delete Permission
     * @since 1.0.0
     */ 
    public function verifyObjectDeletePermission( $config = [], $bucketConfig = [] ) {
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $transfer_acceleration = isset($bucketConfig['transfer_acceleration']) ? $bucketConfig['transfer_acceleration'] : false;

        if (Service::has_missing_fields([$region, $access_key, $secret_key, $bucket_name])) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $s3ClientConfig = [
                'version'                     => '2006-03-01',
                'region'                      => $region,
                'use_accelerate_endpoint'     => $transfer_acceleration,
                'use_aws_shared_config_files' => false,
                'credentials'                 => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ];

            $s3Client = new S3Client($s3ClientConfig);

            $object_key = Utils::get_permission_check_object_key();

            // Create a dummy object to check dlete permission
            $s3Client->deleteObject([
                'Bucket' => $bucket_name,
                'Key'    => $object_key,
            ]);
          
            // Check if the object was created successfully
            if (!$this->exists($object_key, $bucket_name, $s3Client)) {
                return ['message' => esc_html__('Bucket delete permission verified successfully', 'media-cloud-sync'), 'code' => 200, 'success' => true];
            } else {
                return ['message' => esc_html__('Bucket delete permission not verified', 'media-cloud-sync'), 'code' => 200, 'success' => false];
            }
           
        } catch (AwsException $ex) {
            return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
        } catch (S3Exception $ex) {
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
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
        
        if (Service::has_missing_fields([$this->s3Client, $this->bucket_name])) {
            $result['message'] = esc_html__('Invalid Request', 'media-cloud-sync');
            return ['message' => esc_html__('Invalid Request', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        }

        try {   
            $object_key = Utils::get_permission_check_object_key();

            // Check if the object was created successfully
            if (!$this->exists($object_key)) {
                // Create a dummy object to check write permission
                $this->s3Client->putObject([
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
        } catch (AwsException $ex) {
            $result['message'] = $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        } catch (S3Exception $ex) {
            $result['message'] = $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        } catch (Exception $ex) {
            $result['message'] = $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        }
    }


    /**
     * get Bucket Security Settings
     */
    public function getBucketSecuritySettings( $config = [], $bucketConfig = [] ) {
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $transfer_acceleration = isset($bucketConfig['transfer_acceleration']) ? $bucketConfig['transfer_acceleration'] : false;

        if (empty($region) || empty($access_key) || empty($secret_key) || empty($bucket_name)) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        $errors = [];
        try {
            $s3ClientConfig = [
                'version'                     => '2006-03-01',
                'region'                      => $region,
                'use_accelerate_endpoint'     => $transfer_acceleration,
                'use_aws_shared_config_files' => false,
                'credentials'                 => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ];

            $s3Client = new S3Client($s3ClientConfig);

            // Check public access block configuration
            $security['block_public_access'] = false;
            try {
                $publicAccessBlock = $s3Client->getPublicAccessBlock([
                    'Bucket' => $bucket_name,
                ]);
            
                $publicAccessBlockConfig = $publicAccessBlock['PublicAccessBlockConfiguration'];
                if (
                    $publicAccessBlockConfig['BlockPublicAcls'] &&
                    $publicAccessBlockConfig['IgnorePublicAcls'] &&
                    $publicAccessBlockConfig['BlockPublicPolicy'] &&
                    $publicAccessBlockConfig['RestrictPublicBuckets']
                ) {
                    $security['block_public_access'] = true;
                } 
            } catch (S3Exception $ex) {
                // If the bucket does not have public access block configuration, we assume it is not blocked
                $errors['block_public_access'] = $ex->getMessage();
            } catch (Exception $ex) {
                $errors['block_public_access'] = $ex->getMessage();
            }


            $security['object_ownership_enforced'] = false;
            try {
                $ownershipControls = $s3Client->getBucketOwnershipControls([
                    'Bucket' => $bucket_name,
                ]);
            
                $ownershipRule = $ownershipControls['OwnershipControls']['Rules'][0]['ObjectOwnership'];
            
                if ($ownershipRule === 'BucketOwnerEnforced') {
                    $security['object_ownership_enforced'] = true;
                }
            } catch (S3Exception $ex) {
                $errors['object_ownership_enforced'] = $ex->getMessage();
            } catch (Exception $ex) {
                $errors['object_ownership_enforced'] = $ex->getMessage();
            }
        
            return ['message' => '', 'code' => 200, 'success' => empty($errors), 'security' => $security, 'errors' => $errors];
        } catch (AwsException $ex) {
            return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
        } catch (S3Exception $ex) {
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }



    /**
     * Change Bucket Public Access
     */

    public function changePublicAccess( $config = [], $bucketConfig = [], $value = false ) {
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $transfer_acceleration = isset($bucketConfig['transfer_acceleration']) ? $bucketConfig['transfer_acceleration'] : false;    

        if (empty($region) || empty($access_key) || empty($secret_key) || empty($bucket_name)) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $s3ClientConfig = [
                'version'                     => '2006-03-01',
                'region'                      => $region,
                'use_accelerate_endpoint'     => $transfer_acceleration,
                'use_aws_shared_config_files' => false,
                'credentials'                 => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ];

            $s3Client = new S3Client($s3ClientConfig);

            try {
                $result = $this->blockPublicAccess($bucket_name, $value, $s3Client);
                return ['message' => '', 'code' => 200, 'success' => true, 'result' => $result];
            } catch (AwsException $ex) {
                return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
            } catch (S3Exception $ex) {
                return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
            } catch (Exception $ex) {
                return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
            }
        } catch (AwsException $ex) {
            return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
        } catch (S3Exception $ex) {
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }



    /**
     * Change Bucket Ownership
     */

    public function changeObjectOwnership( $config = [], $bucketConfig = [], $value = false ) {
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $transfer_acceleration = isset($bucketConfig['transfer_acceleration']) ? $bucketConfig['transfer_acceleration'] : false;
        
        if (empty($region) || empty($access_key) || empty($secret_key) || empty($bucket_name)) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $s3ClientConfig = [
                'version'                     => '2006-03-01',
                'region'                      => $region,
                'use_accelerate_endpoint'     => $transfer_acceleration,
                'use_aws_shared_config_files' => false,
                'credentials'                 => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ];

            $s3Client = new S3Client($s3ClientConfig);

            $ownership = $value ? 'BucketOwnerEnforced' : 'BucketOwnerPreferred';

            try {
                $result = $this->changeBucketOwnership( $bucket_name, $s3Client, $ownership );
                return ['message' => '', 'code' => 200, 'success' => true, 'result' => $result];
            } catch (AwsException $ex) {
                return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
            } catch (S3Exception $ex) {
                return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
            } catch (Exception $ex) {
                return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
            }
        
        }
        catch (AwsException $ex) {
            return ['message' => $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
        } catch (S3Exception $ex) {
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        } catch (Exception $ex) {
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }
    }
    



    /**
     * Block Public Access
     * @since 1.0.0
     */
    private function blockPublicAccess($bucket, $block = true, $s3Client = false) {
        if($s3Client == false) {
            $s3Client = $this->s3Client;
        }

        if(empty($bucket)) return false;
        
        try {
            $s3Client->putPublicAccessBlock([
                'Bucket'                            => $bucket,
                'PublicAccessBlockConfiguration'    => [
                    'BlockPublicPolicy'     => $block,
                    'BlockPublicAcls'       => $block,
                    'IgnorePublicAcls'      => $block,
                    'RestrictPublicBuckets' => $block,
                ]
            ]);

            return true;
        } catch (AwsException $ex) {
            return false;
        }
    }

    /**
     * Add Bucket Policy
     *
     * $private_prefix, when non-empty, carves that path out of the public
     * grant entirely — every action in the list, not just reads, so an
     * anonymous caller can't read, write, or delete anything under it. Kept
     * as one statement with NotResource rather than split into "reads
     * excluded, everything else still public" — that split would still let
     * anonymous PutObject/DeleteObject reach a "private" file.
     * @since 1.4.1 $private_prefix param added.
     */
    private function putBucketPolicy($bucket, $s3Client = false, $private_prefix = '') {
        if($s3Client == false) {
            $s3Client = $this->s3Client;
        }

        if(empty($bucket)) return false;

        $actions = [
            "s3:DeleteObjectTagging",
            "s3:ListBucketMultipartUploads",
            "s3:DeleteObjectVersion",
            "s3:ListBucket",
            "s3:DeleteObjectVersionTagging",
            "s3:GetBucketAcl",
            "s3:ListMultipartUploadParts",
            "s3:PutObject",
            "s3:GetObjectAcl",
            "s3:GetObject",
            "s3:AbortMultipartUpload",
            "s3:DeleteObject",
            "s3:GetBucketLocation",
            "s3:PutObjectAcl",
            "s3:putBucketOwnershipControls",
            "s3:putBucketPolicy"
        ];

        $statement = [
            "Effect"    => "Allow",
            "Principal" => "*",
            "Action"    => $actions,
        ];

        if (!empty($private_prefix)) {
            $statement["NotResource"] = ["arn:aws:s3:::$bucket/$private_prefix/*"];
        } else {
            $statement["Resource"] = [
                "arn:aws:s3:::$bucket/*",
                "arn:aws:s3:::$bucket"
            ];
        }

        $policy = json_encode([
            "Version"   => "2012-10-17",
            "Statement" => [$statement]
        ]);

        try {
            // Add bucket policy
            $s3Client->putBucketPolicy(['Bucket' => $bucket, 'Policy' => $policy]);

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
     * Apply (or, with an empty $private_prefix, un-apply) the private-path
     * bucket policy carve-out.
     * @since 1.4.1
     */
    public function applyPrivatePathPolicy($private_prefix) {
        if (!$this->s3Client || empty($this->bucket_name)) {
            return ['success' => false, 'code' => 200, 'message' => esc_html__('Client not configured', 'media-cloud-sync')];
        }

        $ok = $this->putBucketPolicy($this->bucket_name, $this->s3Client, $private_prefix);

        return $ok
            ? ['success' => true, 'code' => 200, 'message' => esc_html__('Policy applied successfully', 'media-cloud-sync')]
            : ['success' => false, 'code' => 200, 'message' => esc_html__('Failed to apply bucket policy', 'media-cloud-sync')];
    }

    /**
     * Add Bucket Ownership
     */
    private function changeBucketOwnership($bucket, $s3Client = false, $ownership = 'BucketOwnerPreferred') {
        if($s3Client == false) {
            $s3Client = $this->s3Client;
        }

        if(empty($bucket)) return false;

        try {
            // Change object ownership ACL enabled
            $s3Client->putBucketOwnershipControls([
                'Bucket'           => $bucket,
                'OwnershipControls' => [
                    'Rules' => [['ObjectOwnership' => $ownership]],
                ],
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
     * Change transfer accilaration
     */
    private function changeTransferAccilaration($bucket, $s3Client = false, $enable=false, $force = false) {
        if($s3Client == false) {
            $s3Client = $this->s3Client;
        }

        if(empty($bucket)) return false;
        if(!$force && !$enable) return true;

        // Check if the bucket already has transfer acceleration enabled
        try {
            $s3Client->putBucketAccelerateConfiguration([ 
                'Bucket'                    => $bucket,
                'AccelerateConfiguration'   => [
                    'Status' => $enable ? 'Enabled' : 'Suspended'
                ] 
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
     * isConfigured Function To Identify the congfigurations are correct
     * @since 1.0.0
     */
    public function isConfigured(){
        if ($this->s3Client) {
            try {
                $this->s3Client->listObjectsV2([
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
     * Make Object Private
     * @since 1.0.0
     * 
     */
    public function toPrivate($key) {
        if(!$key) return false;
        if(!$this->s3Client) return false;
        try {
            $this->s3Client->putObjectAcl([
                'Bucket'    => $this->bucket_name,
                'Key'       => $key,
                'ACL'       => 'private'
            ]); 
            return true;
        } catch (AwsException $ex) {
            return false;
        }
    }



    /**
     * Make Object Public
     * @since 1.0.0
     *
     */
    public function toPublic($key) {
        if(!$key) return false;
        if(!$this->s3Client) return false;
        try {
            $this->s3Client->putObjectAcl([
                'Bucket'    => $this->bucket_name,
                'Key'       => $key,
                'ACL'       => 'public-read'
            ]);
            return true;
        } catch (AwsException $ex) {
            return false;
        }
    }



    /**
     * Check the object exist 
     * @since 1.1.8
     */
    public function exists($key, $bucket_name = '', $client = null) {
        if(!$key) return false;
        try {
            $bucket_name = $bucket_name ? $bucket_name : $this->bucket_name;
            $client = $client ?? $this->s3Client;
            if($client->doesObjectExistV2( $bucket_name, $key)) {
                return true;
            }
            return false;
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
        if (!$this->s3Client) {
            return ['success' => false, 'code' => 200, 'message' => esc_html__('Client not configured', 'media-cloud-sync'), 'folders' => [], 'objects' => [], 'next_token' => null];
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

            $result  = $this->s3Client->listObjectsV2($params);
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
        } catch (Exception $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage(), 'folders' => [], 'objects' => [], 'next_token' => null];
        }
    }

    /**
     * Upload Single
     * @since 1.0.0
     * @return boolean
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
                    // Only happens for a private reupload with no private-path provider
                    // available (Pro inactive/unlicensed) — refuse rather than upload
                    // an already-private file to an unprotected path.
                    return [
                        'success' => false,
                        'code'    => 200,
                        'message' => esc_html__('This file is marked private, but the private-media add-on is not currently active — reupload skipped to avoid exposing it.', 'media-cloud-sync')
                    ];
                }
                return $this->execute_upload($absolute_source_path, $upload_path);
            }
            return [
                'success' => false,
                'code'    => 200,
                'message' => esc_html__('Check the file you are trying to upload. Please try again', 'media-cloud-sync')
            ];
        }
        return [
            'success' => false,
            'code'    => 200,
            'message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync')
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
     * Build an unexecuted ObjectUploader (single PUT or multipart, decided internally by the
     * SDK, using this plugin's own multipart threshold rather than the SDK's 16MB default).
     * ACL is stripped via before_* hooks — this plugin's model is bucket-level, not per-object,
     * and an explicit `ACL: null` still serializes to an empty x-amz-acl header otherwise.
     * $options is threaded straight into the SDK (e.g. 'state' => UploadState to resume a
     * previously-failed multipart attempt).
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
            'mup_threshold'    => Schema::getConstant('S3_MULTIPART_MIN_FILE_SIZE'),
            'params'           => $params,
            'before_initiate'  => function ($params) { return $this->strip_acl($params); },
            'before_upload'    => function ($params) { return $this->strip_acl($params); },
            'before_complete'  => function ($params) { return $this->strip_acl($params); },
        ];
        return new ObjectUploader($this->s3Client, $this->bucket_name, $key, $handle, null, $options);
    }

    // Mutate in place, not a clone — the SDK's before_* hooks call this and discard the
    // return value, relying on the same Command object being modified.
    private function strip_acl($params) {
        if ($params instanceof Command && $params->hasParam('ACL')) {
            unset($params['ACL']);
        } elseif (is_array($params) && isset($params['ACL'])) {
            unset($params['ACL']);
        }
        return $params;
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
                    'message'  => esc_html__('File Uploaded Successfully', 'media-cloud-sync')
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
     * Save object to server
     * @since 1.0.0
     */
    public function object_to_server($key, $save_path) {
        if(!$this->s3Client) return false;
        try {
            $getObject = $this->s3Client->GetObject([
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
        if(!$this->s3Client) return false;
        try {
            $result = $this->s3Client->GetObject([
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
     * unconditionally first: not every S3-compatible endpoint supports ListObjectVersions
     * (confirmed missing on Cloudflare R2, a live 501 "NotImplemented"), and the object must
     * still end up gone either way.
     * @since 1.3.14
     */
    public function purge_all_versions($key) {
        if (!$this->s3Client) {
            return ['success' => false, 'code' => 200, 'message' => esc_html__('Client not configured', 'media-cloud-sync')];
        }

        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket_name,
                'Key'    => $key,
            ]);
        } catch (AwsException $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage()];
        }

        // Best-effort only from here — providers that don't support version listing simply
        // skip this part; the live object above is already gone regardless.
        try {
            $objects = [];
            $marker  = null;
            do {
                $args = ['Bucket' => $this->bucket_name, 'Prefix' => $key];
                if ($marker) {
                    $args['KeyMarker']       = $marker['key'];
                    $args['VersionIdMarker'] = $marker['version'];
                }
                $result = $this->s3Client->listObjectVersions($args);
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
                $this->s3Client->deleteObjects([
                    'Bucket' => $this->bucket_name,
                    'Delete' => ['Objects' => $chunk],
                ]);
            }
        } catch (AwsException $e) {
            // Version history cleanup unsupported/failed — not fatal, live object is gone.
        }

        return ['success' => true, 'code' => 200, 'message' => esc_html__('Purged Successfully', 'media-cloud-sync')];
    }


    /**
     * Copy object to new path
     * @since 1.3.4
     */
    // Trusts copyObject()'s own success/failure rather than pre/post-verifying with extra
    // exists() HEAD requests — each one is a full network round-trip, and with move/copy
    // processing keys sequentially, three extra round-trips per file adds up fast on a
    // folder with many files. copyObject() itself throws (caught below) if the source is
    // missing or the copy otherwise fails, so nothing is lost by not checking first.
    public function copy_to_new_path($key, $new_path) {
        if (!$this->s3Client) {
            return [
                'message' => esc_html__('Client not configured', 'media-cloud-sync'),
                'code'    => 200,
                'success' => false
            ];
        }
        try {
            $this->s3Client->copyObject([
                'Bucket'            => $this->bucket_name,
                'CopySource'        => "{$this->bucket_name}/{$key}",
                'Key'               => $new_path,
                'MetadataDirective' => 'COPY',
            ]);
            return [
                'success' => true,
                'code'    => 200,
                'message' => esc_html__('File copied successfully', 'media-cloud-sync')
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
        if (!$this->s3Client) {
            return [
                'message' => esc_html__('Client not configured', 'media-cloud-sync'),
                'code'    => 200,
                'success' => false
            ];
        }
        try {
            $this->s3Client->copyObject([
                'Bucket'            => $dest_bucket,
                'CopySource'        => "{$this->bucket_name}/{$key}",
                'Key'               => $new_key,
                'MetadataDirective' => 'COPY',
            ]);
            return [
                'success' => true,
                'code'    => 200,
                'message' => esc_html__('File copied successfully', 'media-cloud-sync')
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
     * @return boolean
     */
    public function deleteSingle($key) {
        $result = array();
        if (!$this->s3Client) {
            return array(
                'success' => false,
                'code'    => 200,
                'message' => esc_html__('Client not configured', 'media-cloud-sync')
            );
        }
        if (isset($key) && !empty($key)) {
            try {
                $this->s3Client->deleteObject([
                    'Bucket' => $this->bucket_name,
                    'Key'    => $key
                ]);

                if (!$this->exists($key)) {  
                    $result = array(
                        'success' => true,
                        'code'    => 200,
                        'message' => esc_html__('Deleted Successfully', 'media-cloud-sync')
                    );
                } else {
                    $result = array(
                        'success' => false,
                        'code'    => 200,
                        'message' => esc_html__('File not deleted', 'media-cloud-sync')
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
                'message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync')
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
        if (!$this->s3Client) {
            return array(
                'success' => false,
                'code'    => 200,
                'message' => esc_html__('Client not configured', 'media-cloud-sync')
            );
        }
        if (isset($key) && !empty($key)) {
            try {
                $cmd = $this->s3Client->getCommand('GetObject', [
                    'Bucket' => $this->bucket_name,
                    'Key'    => $key
                ]);

                $expires = isset($this->settings['private_url_expire']) ? $this->settings['private_url_expire'] : 20;

                $request = $this->s3Client->createPresignedRequest($cmd, sprintf('+%s  minutes', $expires));

                if ($privateUrl = (string)$request->getUri()) {
                    $result = array(
                        'success'   => true,
                        'code'      => 200,
                        'file_url'  => $privateUrl,
                        'message'   => esc_html__('Got Private URL Successfully', 'media-cloud-sync')
                    );
                } else {
                    $result = array(
                        'success' => false,
                        'code'    => 200,
                        'message' => esc_html__('Error getting private URL', 'media-cloud-sync')
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
                'message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync')
            );
        }
        return $result;
    }

    /**
     * Generate file URL
     */
    public function generate_file_url($key){
        $domain = $this->get_domain();

        return apply_filters('wpmcs_generate_s3_file_url',
            $domain . '/' . $key,
            $domain, $key
        );
    }

    /**
     * Is Provider URL
     * @since 1.3.6
     */
    public function is_provider_url($url) {
        $domain = $this->get_domain();
        return (strpos($url, $domain . '/') !== false);
    }

    /**
     * Get domain URL
     */
    public function get_domain() {
        $region = isset($this->config['region']) ? $this->config['region'] : '';
        return "https://{$this->bucket_name}.s3.{$region}.amazonaws.com";
    }

    /** Exposes the already-constructed SDK client for StreamWrapper's registration — avoids reconstructing one from credentials. */
    public function get_client() {
        return $this->s3Client;
    }

}