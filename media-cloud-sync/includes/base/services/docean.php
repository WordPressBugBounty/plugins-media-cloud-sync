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
use Dudlewebs\WPMCS\s3\Aws\S3\ObjectUploader;
use Dudlewebs\WPMCS\s3\Aws\Command;
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
        if (!Service::has_missing_fields([$region, $access_key, $secret_key])) {
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
                    'message' => __('Please check the authorization details', 'media-cloud-sync'),
                ];

                try {
                    $DOClient->listObjectsV2([
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
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';

        if (!Service::has_missing_fields([$region, $access_key, $secret_key, $bucket_name])) {
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
                    return array('message' => __('Bucket exist', 'media-cloud-sync'), 'code' => 200, 'success' => true);
                } else {
                    return array('message' => __("Bucket chosen does not exist / does not have read permission", 'media-cloud-sync'), 'code' => 200, 'success' => false);
                }
            } 
            catch (S3Exception $ex) {
                return array('message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            } catch (Exception $ex) {
                return array('message' => $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false);
            }
        }
        return array('message' => __('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false);
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

        if (Service::has_missing_fields([$region, $access_key, $secret_key, $bucket_name])) {
            return ['message' => __('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
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
                    'message' => __('Bucket created successfully.', 'media-cloud-sync'),
                    'data'    => [
                        'Name'         => $bucket_name,
                        'CreationDate' => date('Y-m-d\TH:i:s\Z'),
                    ],
                    'code'    => 200,
                    'success' => true,
                ];
            
            } catch (AwsException $ex) {
                return ['message' => __('Bucket created. But the following error happened while setting the public access,', 'media-cloud-sync') . ' ' . $ex->getAwsErrorMessage(), 'code' => 200, 'success' => false];
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
     * Add Bucket Policy
     *
     * Public read-only; $private_prefix, when non-empty, is carved out of it.
     * @since 1.0.0
     */
    private function putBucketPolicy($bucket, $DOClient = false, $private_prefix = '') {
        if($DOClient == false) {
            $DOClient = $this->DOClient;
        }

        if(empty($bucket)) return false;

        // Keep statements the plugin didn't write (e.g. CDN access); the bucket may have no policy yet.
        $existing = '';
        try {
            $existing = (string) $DOClient->getBucketPolicy(['Bucket' => $bucket])['Policy'];
        } catch (Exception $ex) {
            $existing = '';
        }
        $policy = self::build_bucket_policy($bucket, $private_prefix, $existing);

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
     * Bucket policy JSON: public read-only (never write), minus the private path.
     * Replaces only the plugin's own statement in $existing_policy; anything else (e.g. CDN access) is kept.
     * @since 1.5.0
     * @return string
     */
    public static function build_bucket_policy($bucket, $private_prefix = '', $existing_policy = '') {
        $object_arn = "arn:aws:s3:::$bucket/*";
        $public     = [
            'Sid'       => 'WpmcsPublicRead',
            'Effect'    => 'Allow',
            'Principal' => '*',
            'Action'    => ['s3:GetObject'],
        ];
        if (!empty($private_prefix)) {
            $public['NotResource'] = ["arn:aws:s3:::$bucket/$private_prefix/*"];
        } else {
            $public['Resource'] = [$object_arn];
        }

        $existing   = json_decode((string) $existing_policy, true);
        $statements = [];
        foreach (self::policy_statements($existing) as $statement) {
            if (!self::is_plugin_policy_statement($statement, $bucket)) {
                $statements[] = $statement;
            }
        }
        $statements[] = $public;

        $statements = apply_filters('wpmcs_bucket_policy_statements', $statements, $bucket, $private_prefix);

        return wp_json_encode(['Version' => '2012-10-17', 'Statement' => array_values($statements)]);
    }

    /**
     * A policy's statements as a list; AWS also allows a single statement object.
     * @since 1.5.0
     */
    public static function policy_statements($policy) {
        $statements = $policy['Statement'] ?? [];
        return isset($statements['Effect']) ? [$statements] : array_values(array_filter((array) $statements, 'is_array'));
    }

    /**
     * Whether a policy statement is the plugin's public grant: tagged, or the legacy untagged one (anonymous Allow incl. GetObject on this bucket).
     * @since 1.5.0
     */
    public static function is_plugin_policy_statement($statement, $bucket) {
        if (!is_array($statement)) {
            return false;
        }
        if (($statement['Sid'] ?? '') === 'WpmcsPublicRead') {
            return true;
        }
        $principal = $statement['Principal'] ?? '';
        $anonymous = $principal === '*' || (is_array($principal) && ($principal['AWS'] ?? '') === '*');
        $resources = array_merge((array) ($statement['Resource'] ?? []), (array) ($statement['NotResource'] ?? []));
        // Exact bucket match, so a similarly named bucket (e.g. "media-backup" for "media") isn't mistaken for this one.
        $on_bucket = !empty($resources) && empty(array_filter($resources, function ($arn) use ($bucket) {
            $arn = (string) $arn;
            return $arn !== "arn:aws:s3:::$bucket" && strpos($arn, "arn:aws:s3:::$bucket/") !== 0;
        }));

        return ($statement['Effect'] ?? '') === 'Allow' && $anonymous && empty($statement['Condition']) && $on_bucket
            && in_array('s3:GetObject', array_map('strval', (array) ($statement['Action'] ?? [])), true);
    }

    /**
     * Apply (or, with an empty $private_prefix, un-apply) the private-path
     * bucket policy carve-out.
     * @since 1.0.0
     */
    public function applyPrivatePathPolicy($private_prefix) {
        if (!$this->DOClient || empty($this->bucket_name)) {
            return ['success' => false, 'code' => 200, 'message' => __('Client not configured', 'media-cloud-sync')];
        }

        $ok = $this->putBucketPolicy($this->bucket_name, $this->DOClient, $private_prefix);

        return $ok
            ? ['success' => true, 'code' => 200, 'message' => __('Policy applied successfully', 'media-cloud-sync')]
            : ['success' => false, 'code' => 200, 'message' => __('Failed to apply bucket policy', 'media-cloud-sync')];
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

        if (Service::has_missing_fields([$region, $access_key, $secret_key, $bucket_name])) {
            return ['message' => __('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
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

            $object_key = Utils::get_permission_check_object_key();

            
            // Create a dummy object to check write permission
            $DOClient->putObject([
                'Bucket' => $bucket_name,
                'Key'    => $object_key,
                'Body'   => 'This is a test object to check write permission.',
            ]);
            // Check if the object was created successfully
            if ($this->exists($object_key, $bucket_name, $DOClient)) {
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
        $region = isset($config['region']) ? $config['region'] : '';
        $access_key = isset($config['access_key']) ? $config['access_key'] : '';
        $secret_key = isset($config['secret_key']) ? $config['secret_key'] : '';
        $bucket_name = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';

        if (Service::has_missing_fields([$region, $access_key, $secret_key, $bucket_name])) {
            return ['message' => __('Insufficient Data. Please try again', 'media-cloud-sync'), 'code' => 200, 'success' => false];
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
            
            $object_key = Utils::get_permission_check_object_key();

            // Create a dummy object to check dlete permission
            $DOClient->deleteObject([
                'Bucket' => $bucket_name,
                'Key'    => $object_key,
            ]);
          
            // Check if the object was created successfully
            if (!$this->exists($object_key, $bucket_name, $DOClient)) {
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
        if (Service::has_missing_fields([$this->DOClient, $this->bucket_name])) {
            $result['message'] = __('Invalid Request', 'media-cloud-sync');
            return ['message' => __('Invalid Request', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        }

        try {   
            $object_key = Utils::get_read_check_object_key();

            // Check if the object was created successfully
            if (!$this->exists($object_key)) {
                // Create a dummy object to check write permission
                $this->DOClient->putObject([
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
            return ['message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        } catch (S3Exception $ex) {
            $result['message'] = $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getAwsErrorMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        } catch (Exception $ex) {
            $result['message'] = $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync');
            return ['message' => $ex->getMessage() ?? __('Please check the authorization details', 'media-cloud-sync'), 'code' => 200, 'success' => false, 'lastChecked' => time()];
        }
    }

    /**
     * isConfigured Function To Identify the congfigurations are correct
     * @since 1.0.0
     */
    public function isConfigured(){
        if ($this->DOClient) {
            try {
               $this->DOClient->listObjectsV2([
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
        if(!$this->DOClient) return false;
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
    }



    /**
     * Make Object Public
     * @since 1.0.0
     *
     */
    public function toPublic($key) {
        if(!$key) return false;
        if(!$this->DOClient) return false;
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
            return false;
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
     * List Objects — $delimiter = null gives a flat/recursive listing instead of one folder level.
     * @since 1.3.13
     */
    public function listObjects($prefix = '', $continuationToken = null, $maxKeys = 1000, $delimiter = '/') {
        if (!$this->DOClient) {
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

            $result  = $this->DOClient->listObjectsV2($params);
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
                    return [
                        'success' => false,
                        'code'    => 200,
                        'message' => __('This file is marked private, but the private-media add-on is not currently active — reupload skipped to avoid exposing it.', 'media-cloud-sync')
                    ];
                }
                return $this->execute_upload($absolute_source_path, $upload_path);
            }
            return [
                'success' => false,
                'code'    => 200,
                'message' => __('Check the file you are trying to upload. Please try again', 'media-cloud-sync')
            ];
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
            'mup_threshold'    => Schema::getConstant('DOCEAN_MULTIPART_MIN_FILE_SIZE'),
            'params'           => $params,
            'before_initiate'  => function ($params) { return $this->strip_acl($params); },
            'before_upload'    => function ($params) { return $this->strip_acl($params); },
            'before_complete'  => function ($params) { return $this->strip_acl($params); },
        ];
        return new ObjectUploader($this->DOClient, $this->bucket_name, $key, $handle, null, $options);
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
     * Save object to server
     * @since 1.0.0
     */
    public function object_to_server($key, $save_path) {
        if(!$this->DOClient) return false;
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
     * Object bytes in memory, no local file — for callers (e.g. zip download) that need
     * the content itself rather than a copy on the server's filesystem.
     * @since 1.3.13
     */
    public function get_object_content($key) {
        if(!$this->DOClient) return false;
        try {
            $result = $this->DOClient->GetObject([
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
     * unconditionally first: DigitalOcean Spaces doesn't support object versioning at all,
     * so the version-listing part below simply fails there (caught, non-fatal) — the object
     * must still end up gone either way, which is why it can't be the only delete call.
     * @since 1.3.14
     */
    public function purge_all_versions($key) {
        if (!$this->DOClient) {
            return ['success' => false, 'code' => 200, 'message' => __('Client not configured', 'media-cloud-sync')];
        }

        try {
            $this->DOClient->deleteObject([
                'Bucket' => $this->bucket_name,
                'Key'    => $key,
            ]);
        } catch (AwsException $e) {
            return ['success' => false, 'code' => 200, 'message' => $e->getMessage()];
        }

        // Best-effort only from here — Spaces doesn't support version listing at all, so
        // this always no-ops there; the live object above is already gone regardless.
        try {
            $objects = [];
            $marker  = null;
            do {
                $args = ['Bucket' => $this->bucket_name, 'Prefix' => $key];
                if ($marker) {
                    $args['KeyMarker']       = $marker['key'];
                    $args['VersionIdMarker'] = $marker['version'];
                }
                $result = $this->DOClient->listObjectVersions($args);
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
                $this->DOClient->deleteObjects([
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
        if (!$this->DOClient) {
            return [
                'message' => __('Client not configured', 'media-cloud-sync'),
                'code'    => 200,
                'success' => false
            ];
        }
        try {
            $this->copy_object_keeping_public_read([
                'Bucket'            => $this->bucket_name,
                'CopySource'        => "{$this->bucket_name}/{$key}",
                'Key'               => $new_path,
                'MetadataDirective' => 'COPY',
            ], $key);
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
        if (!$this->DOClient) {
            return [
                'message' => __('Client not configured', 'media-cloud-sync'),
                'code'    => 200,
                'success' => false
            ];
        }
        try {
            $this->DOClient->copyObject([
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
     * @return boolean
     */
    public function deleteSingle($key) {
        $result = array();
        if (!$this->DOClient) {
            return array(
                'success' => false,
                'code'    => 200,
                'message' => __('Client not configured', 'media-cloud-sync')
            );
        }
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
     * get private URL
     * @since 1.0.0
     * @return boolean
     */
    public function get_private_url($key) {
        $result = array();
        if (!$this->DOClient) {
            return array(
                'success' => false,
                'code'    => 200,
                'message' => __('Client not configured', 'media-cloud-sync')
            );
        }
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
            $this->copy_object_keeping_public_read($params, $key);
            return ['success' => true, 'code' => 200, 'changed' => true, 'message' => __('Headers updated', 'media-cloud-sync')];
        } catch (AwsException $e) {
            $message = $e->getStatusCode() == 404 ? __('Not found in the bucket', 'media-cloud-sync') : ($e->getAwsErrorMessage() ?: $e->getMessage());
            return ['success' => false, 'code' => 200, 'changed' => false, 'message' => $message];
        } catch (Exception $e) {
            return ['success' => false, 'code' => 200, 'changed' => false, 'message' => $e->getMessage()];
        }
    }

    // Copies an object keeping its own public-read; without it when the bucket blocks public ACLs.
    private function copy_object_keeping_public_read($params, $source_key) {
        if (!$this->object_is_public_read($source_key)) {
            return $this->get_client()->copyObject($params);
        }
        try {
            return $this->get_client()->copyObject($params + ['ACL' => 'public-read']);
        } catch (AwsException $e) {
            return $this->get_client()->copyObject($params);
        }
    }

    // Whether the object itself grants public read (older versions uploaded with it); a copy doesn't keep it.
    private function object_is_public_read($key) {
        try {
            $acl = $this->get_client()->getObjectAcl(['Bucket' => $this->bucket_name, 'Key' => $key]);
            foreach ((array) $acl['Grants'] as $grant) {
                if (($grant['Grantee']['URI'] ?? '') === 'http://acs.amazonaws.com/groups/global/AllUsers' && in_array($grant['Permission'] ?? '', ['READ', 'FULL_CONTROL'], true)) {
                    return true;
                }
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
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
            __('Your storage keys are not allowed to read or change the bucket\'s CORS configuration. Use a Spaces key with full access, or add the rule in the DigitalOcean control panel under Spaces › %s › Settings › CORS Configurations.', 'media-cloud-sync'),
            $this->bucket_name
        );
    }

    /** Exposes the already-constructed SDK client for StreamWrapper's registration — avoids reconstructing one from credentials. */
    public function get_client() {
        return $this->DOClient;
    }

}