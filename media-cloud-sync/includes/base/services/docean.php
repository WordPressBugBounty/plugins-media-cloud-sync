<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

// Libraries
use Dudlewebs\WPMCS\s3\Aws\S3\S3Client;
use Dudlewebs\WPMCS\s3\Aws\Exception\AwsException;
use Dudlewebs\WPMCS\s3\Aws\S3\Exception\S3Exception;
use Dudlewebs\WPMCS\s3\Aws\S3\MultipartUploader;
use Dudlewebs\WPMCS\s3\Aws\Exception\MultipartUploadException;
use Exception;

class DOcean {
    private $assets_url;
    private $version;
    private $token;

    protected $config;
    protected $bucketConfig;
    protected $settings;
    protected $credentials;
    protected $bucket_name;
    protected $cdnConfig;

    public $service     = 'docean';
    public $DOClient    = false;

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url = WPMCS_ASSETS_URL;
        $this->version    = WPMCS_VERSION;
        $this->token      = WPMCS_TOKEN;

        // Initialize setup
        $this->init();
    }

    /**
     * Initialise Client
     */
    public function init() {
        $this->settings     = Utils::get_settings();
        $this->credentials  = Utils::get_credentials();
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
            $endpoint = $this->get_domain();

            $this->DOClient = new S3Client([
                'version'     => '2006-03-01',
                'region'      => $this->config['region'],
                'endpoint'    => $endpoint, // DigitalOcean Spaces requires a custom endpoint
                'use_accelerate_endpoint' => false,
                'use_path_style_endpoint' => true, // DigitalOcean Spaces often requires path-style endpoints
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
        if (!empty($region) && !empty($access_key) && !empty($secret_key)) {
            try {
                $endpoint = $this->get_domain($region);

                $DOClient = new S3Client([
                    'version'                       => '2006-03-01',
                    'region'                        => $region,
                    'endpoint'                      => $endpoint,
                    'use_path_style_endpoint'       => true, // Required for DigitalOcean Spaces
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
                    'message' => esc_html__('Please check the authorization details', 'media-cloud-sync'),
                ];

                try {
                    $DOClient->listObjectsV2([
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
                    $buckets = $DOClient->listBuckets();
                    $newBucketFormat = [];
                    if(isset($buckets['Buckets']) && !empty($buckets['Buckets'])){
                        foreach($buckets['Buckets'] as $bucket) {
                            if(isset($bucket['Name'])) {
                                $newBucketFormat[] = ['Name' => $bucket['Name'], 'CreationDate' => $bucket['CreationDate'] ?? ''];
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
            } catch (S3Exception $ex) {
                return array('message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            } catch (Exception $ex) {
                return array('message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            }
        }
        return array('message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false);
    }

    /**
     * Verify Bucket
     * @since 1.0.0
     * @return boolean
     */
    public function verifyBucketExist( $config = [], $bucketConfig = [] ) {
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';

        if (!empty($region) && !empty($access_key) && !empty($secret_key) && !empty($bucket_name)) {
            try {
                $endpoint = $this->get_domain($region);

                $DOClient = new S3Client([
                    'version'                       => '2006-03-01',
                    'region'                        => $region,
                    'endpoint'                      => $endpoint,
                    'use_path_style_endpoint'       => true, // Required for DigitalOcean Spaces
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
                    $DOClient->getObject([
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

        if (empty($region) || empty($access_key) || empty($secret_key) || empty($bucket_name)) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $endpoint = $this->get_domain($region);

            $DOClient = new S3Client([
                'version'                       => '2006-03-01',
                'region'                        => $region,
                'endpoint'                      => $endpoint,
                'use_path_style_endpoint'       => true, // Required for DigitalOcean Spaces
                'use_accelerate_endpoint'       => false,
                'use_aws_shared_config_files'   => false,
                'credentials'                   => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            // Create Bucket
            $DOClient->createBucket([
                'Bucket' => $bucket_name,
            ]);
        
            // Optionally wait for bucket existence (recommended)
            $DOClient->waitUntil('BucketExists', ['Bucket' => $bucket_name]);
            
            try {
                $this->putBucketPolicy($bucket_name, $DOClient);
                
                return [
                    'message' => esc_html__('Bucket created successfully.', 'media-cloud-sync'),
                    'data'    => [
                        'Name'         => $bucket_name,
                        'CreationDate' => date('Y-m-d\TH:i:s\Z'),
                    ],
                    'code'    => 200,
                    'success' => true,
                ];
            
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
     * Add Bucket Policy
     */
    private function putBucketPolicy($bucket, $DOClient = false) {
        if($DOClient == false) {
            $DOClient = $this->DOClient;
        }

        if(empty($bucket)) return false;

        $policy = json_encode([
            "Version" => "2012-10-17",
            "Statement" => [
                [
                    "Effect" => "Allow",
                    "Principal" => "*",
                    "Action" => [
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
                    ],
                    "Resource" => [
                        "arn:aws:s3:::$bucket/*",
                        "arn:aws:s3:::$bucket"
                    ]
                ]
            ]
        ]);

        try {
            // Add bucket policy
            $DOClient->putBucketPolicy(['Bucket' => $bucket, 'Policy' => $policy]);

            return true;
        } catch (AwsException $ex) {
            return false; // Handle AWS specific exceptions
        } catch (S3Exception $ex) {
            return false; // Handle S3 specific exceptions
        } catch (Exception $ex) {
            return false; // Handle general exceptions
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

        if (empty($region) || empty($access_key) || empty($secret_key) || empty($bucket_name)) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $endpoint = $this->get_domain($region);

            $DOClient = new S3Client([
                'version'                       => '2006-03-01',
                'region'                        => $region,
                'endpoint'                      => $endpoint,
                'use_path_style_endpoint'       => true, // Required for DigitalOcean Spaces
                'use_accelerate_endpoint'       => false,
                'use_aws_shared_config_files'   => false,
                'credentials'                   => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            $object_key = Utils::generate_object_key($this->token . '_dummy-object-for-bucket-permission-check', '');

            
            // Create a dummy object to check write permission
            $DOClient->putObject([
                'Bucket' => $bucket_name,
                'Key'    => $object_key,
                'Body'   => 'This is a test object to check write permission.',
            ]);
            // Check if the object was created successfully
            if ($this->exists($object_key, $bucket_name, $DOClient)) {
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
        
        if (empty($region) || empty($access_key) || empty($secret_key) || empty($bucket_name)) {
            return ['message' => esc_html__('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
        }

        try {
            $endpoint = $this->get_domain($region);

            $DOClient = new S3Client([
                'version'                       => '2006-03-01',
                'region'                        => $region,
                'endpoint'                      => $endpoint,
                'use_path_style_endpoint'       => true, // Required for DigitalOcean Spaces
                'use_accelerate_endpoint'       => false,
                'use_aws_shared_config_files'   => false,
                'credentials'                   => [
                    'key'    => $access_key,
                    'secret' => $secret_key,
                ],
            ]);
            
            $object_key = Utils::generate_object_key($this->token . '_dummy-object-for-bucket-permission-check', '');

            // Create a dummy object to check dlete permission
            $DOClient->deleteObject([
                'Bucket' => $bucket_name,
                'Key'    => $object_key,
            ]);
          
            // Check if the object was created successfully
            if (!$this->exists($object_key, $bucket_name, $DOClient)) {
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
        if (empty($this->DOClient) || empty($this->bucket_name)) {
            $result['message'] = esc_html__('Invalid Request', 'media-cloud-sync');
            Utils::set_status('cdnRead', $result);
            return ['message' => esc_html__('Invalid Request', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        }

        try {   
            $object_key = Utils::generate_object_key($this->token . '_dummy-object-for-bucket-permission-check', '');

            // Check if the object was created successfully
            if (!$this->exists($object_key)) {
                // Create a dummy object to check write permission
                $this->DOClient->putObject([
                    'Bucket' => $this->bucket_name,
                    'Key'    => $object_key,
                    'Body'   => 'This is a test object to check permission.',
                ]);
            } 
            

            $url = $this->generate_file_url($object_key);
            $cdn_url = Cdn::may_generate_cdn_url($url, $object_key);
            $headers = @get_headers($cdn_url);
            
            if (strpos($headers[0], '200') !== false) {
                $result['status'] = true;
                $result['message'] = esc_html__('Objects are accessible to Read', 'media-cloud-sync');
            } else if (strpos($headers[0], '403') !== false) {
                $result['status'] = false;
                if($this->cdnConfig['service'] == $this->service) {
                    $result['message'] = esc_html__('Access Denied. Please check your bucket policy. Public Read Access is required.', 'media-cloud-sync');
                } else {
                    $result['message'] = esc_html__('Access Denied. Please check your bucket policy', 'media-cloud-sync');
                }
                $result['message'] = esc_html__('Access Denied. Please check your bucket policy', 'media-cloud-sync');
            } else if (strpos($headers[0], '404') !== false) {
                $result['status'] = false;
                $result['message'] = esc_html__('Object not found. Please check your bucket policy', 'media-cloud-sync');
            } else if (strpos($headers[0], '500') !== false) {
                $result['status'] = false;
                $result['message'] = esc_html__('Internal Server error. Please check your bucket policy', 'media-cloud-sync');
            } else {
                $result['status'] = false;
                $result['message'] = esc_html__('Objects are not accessible to read', 'media-cloud-sync');
            }
            Utils::set_status('cdnRead', $result);

            $this->deleteSingle($object_key);
            return [
                'message' => $result['message'],
                'code'    => 200,
                'success' => $result['status'],
                'lastChecked' => $result['lastChecked'],
            ];
        } catch (AwsException $ex) {
            $result['message'] = $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync');
            Utils::set_status('cdnRead', $result);
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        } catch (S3Exception $ex) {
            $result['message'] = $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync');
            Utils::set_status('cdnRead', $result);
            return ['message' => $ex->getAwsErrorMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        } catch (Exception $ex) {
            $result['message'] = $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync');
            Utils::set_status('cdnRead', $result);
            return ['message' => $ex->getMessage() ?? esc_html__('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        }
    }

    /**
     * isConfigured Function To Identify the congfigurations are correct
     * @since 1.0.0
     */
    public function isConfigured(){
        if ($this->DOClient) {
            try {
               $DOClient->listObjectsV2([
                    'Bucket' => $this->token . '_dummy-bucket-for-auth-check'
                ]);
            
                // If we reach here, the credentials are valid
                return true;
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
                    return false;
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
        try {
            $this->DOClient->putObjectAcl([
                'Bucket'    => $this->bucket_name,
                'Key'       => $key,
                'ACL'       => 'private'
            ]); 
            return true;
        } catch (AwsException $ex) {
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
        try {
            $this->DOClient->putObjectAcl([
                'Bucket'    => $this->bucket_name,
                'Key'       => $key,
                'ACL'       => 'public-read'
            ]); 
            return true;
        } catch (AwsException $ex) {
            return false;
        }
        return false;
    }



    /**
     * Check the object exist 
     * @since 1.1.8
     */
    public function exists($key, $bucket_name = '', $client = null) {
        if(!$key) return false;

        try {
            $client = $client ?? $this->DOClient;
            $bucket_name = !empty($bucket_name) ? $bucket_name : $this->bucket_name;
            if($client->doesObjectExistV2($bucket_name, $key)) {
                return true;
            }
        } catch (AwsException $ex) {
            return false;
        }
        catch (S3Exception $ex) {
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * Upload Single
     * @since 1.0.0
     * @return boolean
     */
    public function uploadSingle($absolute_source_path, $relative_source_path, $prefix='') {
        $result = array();
        if (
            isset($absolute_source_path) && !empty($absolute_source_path) &&
            isset($relative_source_path) && !empty($relative_source_path)
        ) {
            $file_name = wp_basename( $relative_source_path );
            if ($file_name) {
                $upload_path = Utils::generate_object_key($relative_source_path, $prefix);
               
                // Decide Multipart upload or normal put object
                if (filesize($absolute_source_path) <= Schema::getConstant('DOCEAN_MULTIPART_MIN_FILE_SIZE')) {
                    // Upload a publicly accessible file. The file size and type are determined by the SDK.
                    try {
                        $handle = fopen($absolute_source_path, 'rb');
                        $upload = $this->DOClient->putObject([
                            'Bucket' => $this->bucket_name,
                            'Key'    => $upload_path,
                            'Body'   => $handle,
                        ]);
                        if (is_resource($handle)) {
                            fclose($handle);
                        }

                        $result = array(
                            'success'   => true,
                            'code'      => 200,
                            'file_url'  => $this->generate_file_url($upload_path),
                            'key'       => $upload_path,
                            'message'   => esc_html__('File Uploaded Successfully', 'media-cloud-sync')
                        );
                    } catch (AwsException $e) {
                        $result = array(
                            'success' => false,
                            'code'    => 200,
                            'message' => $e->getMessage()
                        );
                    }
                } else {
                    $multiUploader = new MultipartUploader($this->DOClient, $absolute_source_path, [
                        'bucket'    => $this->bucket_name,
                        'key'       => $upload_path,
                    ]);
                    
                    try {
                        do {
                            try {
                                $uploaded = $multiUploader->upload();
                            } catch (MultipartUploadException $e) {
                                $multiUploader = new MultipartUploader($this->DOClient, $absolute_source_path, [
                                    'state' => $e->getState(),
                                ]);
                            }
                        } while (!isset($uploaded));

                        if (isset($uploaded['ObjectURL']) && !empty($uploaded['ObjectURL'])) {
                            $result = array(
                                'success' => true,
                                'code'    => 200,
                                'file_url' => $this->generate_file_url($upload_path),
                                'key'     => $upload_path,
                                'message' => esc_html__('File Uploaded Successfully', 'media-cloud-sync')
                            );
                        } else {
                            $result = array(
                                'success' => false,
                                'code'    => 200,
                                'message' => esc_html__('Something happened while uploading to server', 'media-cloud-sync')
                            );
                        }
                    } catch (MultipartUploadException $e) {
                        $result = array(
                            'success' => false,
                            'code'    => 200,
                            'message' => $e->getMessage()
                        );
                    }
                }
            } else {
                $result = array(
                    'success' => false,
                    'code'    => 200,
                    'message' => esc_html__('Check the file you are trying to upload. Please try again', 'media-cloud-sync')
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
     * Save object to server
     * @since 1.0.0
     */
    public function object_to_server($key, $save_path) {
        try {
            $getObject = $this->DOClient->GetObject([
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
     * Copy to new path
     * @since 1.3.4
     */
    public function copy_to_new_path($key, $new_path) {
        try {
            // Step 1: Verify object exists at old location
            if (!$this->exists($key)) {
                return [
                    'message' => esc_html__('Original file not found' , 'media-cloud-sync'),
                    'code' => 200,
                    'success' => false
                ];
            }
            // Step 2: Copy object
            if (!$this->exists($new_path)) {
                $this->DOClient->copyObject([
                    'Bucket'            => $this->bucket_name,
                    'CopySource'        => "{$this->bucket_name}/{$key}",
                    'Key'               => $new_path,
                    'MetadataDirective' => 'COPY',
                ]);
            }
            // Step 3: Verify object exists at new location
            if ($this->exists($new_path)) {
                return [
                    'success' => true,
                    'code'    => 200,
                    'message' => esc_html__('File copied successfully', 'media-cloud-sync')
                ];    
            }
        } catch (AwsException $e) {
            return [
                'success' => false,
                'code'    => 200,
                'message' => $e->getMessage()
            ];
        }
        return [
            'success' => false,
            'code'    => 200,
            'message' => esc_html__('File not copied', 'media-cloud-sync')
        ];
    }


    /**
     * Delete Single
     * @since 1.0.0
     * @return boolean
     */
    public function deleteSingle($key) {
        $result = array();
        if (isset($key) && !empty($key)) {
            try {
                $this->DOClient->deleteObject([
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
        if (isset($key) && !empty($key)) {
            try {
                $cmd = $this->DOClient->getCommand('GetObject', [
                    'Bucket' => $this->bucket_name,
                    'Key'    => $key
                ]);

                $expires = isset($this->settings['private_url_expire']) ? $this->settings['private_url_expire'] : 20;

                $request = $this->DOClient->createPresignedRequest($cmd, sprintf('+%s  minutes', $expires));

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
                        'message' => esc_html__('Error getting Private URL', 'media-cloud-sync')
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

        return apply_filters('wpmcs_generate_do_file_url',
            $domain . '/' . $this->bucket_name . '/' . $key,
            $domain, 
            $this->bucket_name, 
            $key
        );
    }

    /**
     * Is Provider URL
     * @since 1.3.6
     */
    public function is_provider_url($url) {
        $domain = $this->get_domain();
        return (strpos($url, $domain . '/' . $this->bucket_name . '/') !== false);
    }

    /**
     * Get domain URL
     */
    public function get_domain($region = '') {
        if(empty($region)) {
            $region = isset($this->config['region']) ? $this->config['region'] : '';
        }
        return "https://{$region}.digitaloceanspaces.com";
    }

}