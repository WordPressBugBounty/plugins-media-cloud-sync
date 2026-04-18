<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Schema {
	private static $constants;
	private static $service_labels;

	// Static initializer to handle dynamic operations
	static function init() {
		self::$constants = [
			'S3_MULTIPART_MIN_FILE_SIZE' 		=> 5242880,
			'GCLOUD_MULTIPART_MIN_FILE_SIZE' 	=> 5242880,
			'DOCEAN_MULTIPART_MIN_FILE_SIZE' 	=> 5242880,
			'CLOUDFLARE_R2_MULTIPART_MIN_FILE_SIZE' => 5242880,
			'S3COMPATIBLE_MULTIPART_MIN_FILE_SIZE' 	=> 5242880,
			'CACHE_GROUP'						=> WPMCS_TOKEN.'_cache_group',
			'CACHE_EXPIRE'						=> 24*60*60,
			'META_KEY'							=> WPMCS_TOKEN.'_meta_data',
			'CONTENT_META_KEY'					=> WPMCS_TOKEN.'_post_meta_data',
			'GLOBAL_SETTINGS_KEY'				=> WPMCS_TOKEN.'_global_settings',
			'STATUS_KEY'						=> WPMCS_TOKEN.'_status_data',
			'COUNTER_KEY'						=> WPMCS_TOKEN.'_counter_data',
			'UPLOADS'							=> WPMCS_TOKEN.'_uploads'
		];

		self::$service_labels = [
			's3' 			=> esc_html__('AWS/S3', 'media-cloud-sync'),
			'gcloud'		=> esc_html__('Google Cloud Storage', 'media-cloud-sync'),
			'docean' 		=> esc_html__('Digital Ocean Spaces', 'media-cloud-sync'),
			'cloudflareR2'  => esc_html__('Cloudflare R2', 'media-cloud-sync'),
			's3compatible' 	=> esc_html__('S3 Compatible', 'media-cloud-sync')
		];
	}

	/**
	 * Get schema constant by key.
	 */
	public static function getConstant($key, $default = false) {
		return isset(self::$constants[$key]) ? self::$constants[$key] : $default;
	}

	/**
	 * Get service label by key.
	 */
	public static function getServiceLabels($key, $default = false) {
		return isset(self::$service_labels[$key]) ? self::$service_labels[$key] : $default;
	}
}
// Initialize on load time to handle missing constant redeclaration
Schema::init();