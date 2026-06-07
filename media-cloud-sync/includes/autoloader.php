<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

/**
 * Plugin autoloader.
 *
 * Plugin autoloader handler class is responsible for loading the different
 * classes needed to run the plugin.
 *
 * @since 1.0.0
 */
class Autoloader {

	/**
	 * Classes map.
	 *
	 * Maps Plugin classes to file names.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @var array Classes used by *.
	 */
	private static $classes_map;

	/**
	 * Classes aliases.
	 *
	 * Maps * classes to aliases.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @var array Classes aliases.
	 */
	private static $classes_aliases;

	/**
	 * Default path for autoloader.
	 *
	 * @var string
	 */
	private static $default_path;

	/**
	 * Default namespace for autoloader.
	 *
	 * @var string
	 */
	private static $default_namespace;

	/**
	 * Run autoloader.
	 *
	 * Register a function as `__autoload()` implementation.
	 *
	 * @param string $default_path
	 * @param string $default_namespace
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 */
	public static function run( $default_path = '', $default_namespace = '' ) {
		if ( '' === $default_path ) {
			$default_path = WPMCS_PATH;
		}

		if ( '' === $default_namespace ) {
			$default_namespace = __NAMESPACE__;
		}

		self::$default_path = $default_path;
		self::$default_namespace = $default_namespace;

		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Get classes aliases.
	 *
	 * Retrieve the classes aliases names.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @return array Classes aliases.
	 */
	public static function get_classes_aliases() {
		if ( ! self::$classes_aliases ) {
			self::init_classes_aliases();
		}

		return self::$classes_aliases;
	}

	public static function get_classes_map() {
		if ( ! self::$classes_map ) {
			self::init_classes_map();
		}

		return self::$classes_map;
	}

	private static function init_classes_map() {
		self::$classes_map = [
			// Config Files
            'Utils' 		=> 'includes/config/utils.php',
            'Schema' 		=> 'includes/config/schema.php',

			'Api'   		=> 'includes/api.php',
			'Main'  		=> 'includes/main.php',
			'Notices'		=> 'includes/notices.php',
			'Front' 		=> 'includes/front.php',
            'Admin' 		=> 'includes/admin.php',
			
			// Upgrade
			'Upgrade' 		=> 'includes/upgrade/upgrade.php',

			// Base Files
			'Service'		=> 'includes/base/service.php',
			'Item'			=> 'includes/base/item.php',
            'Db' 			=> 'includes/base/db.php',
            'Cdn' 			=> 'includes/base/cdn.php',
            'Cache' 		=> 'includes/base/cache.php',
            'Counter' 		=> 'includes/base/counter.php',
			'BGRunner' 		=> 'includes/base/bg-runner.php',
			'Logger' 		=> 'includes/base/logger.php',

			// Integrations
			'Integration' 	=> 'includes/integrations/integrations.php',
			'MediaLibrary' 	=> 'includes/integrations/media-library.php',
			'Acf' 			=> 'includes/integrations/acf.php',
			'Imagify' 		=> 'includes/integrations/imagify.php',
			'WooCommerce' 	=> 'includes/integrations/woocommerce.php',

			// Compatibility
			'Compatibility' => 'includes/compatbility/compatibility.php',

			// Filters
            'Filter' 		=> 'includes/filters/filters.php',
			'FilterContent'	=> 'includes/filters/content.php',

			// Services
			'S3'			=> 'includes/base/services/s3.php',
			'GCloud'		=> 'includes/base/services/gcloud.php',
			'DOcean'		=> 'includes/base/services/docean.php',
			'CloudflareR2'	=> 'includes/base/services/cloudflare-r2.php',
			'S3Compatible'	=> 'includes/base/services/s3compatible.php',

			// Sync
			'SyncToCloud'	=> 'includes/bulk/sync-to-cloud.php',
			'Sync'			=> 'includes/bulk/sync.php',
		];
	}



	private static function init_classes_aliases() {
		self::$classes_aliases = [];
	}

	/**
	 * Load class.
	 *
	 * For a given class name, require the class file.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @param string $relative_class_name Class name.
	 */
	private static function load_class( $relative_class_name ) {
		$classes_map = self::get_classes_map();

		if ( isset( $classes_map[ $relative_class_name ] ) ) {
			$filename = self::$default_path . '/' . $classes_map[ $relative_class_name ];
		} else {
			$filename = strtolower(
				preg_replace(
					[ '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
					[ '$1-$2', '-', DIRECTORY_SEPARATOR ],
					$relative_class_name
				)
			);

			$filename = self::$default_path . $filename . '.php';
		}

		if ( is_readable( $filename ) ) {
			require $filename;
		}
	}

	/**
	 * Autoload.
	 *
	 * For a given class, check if it exist and load it.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 *
	 * @param string $class Class name.
	 */
	private static function autoload( $class ) {
		if ( 0 !== strpos( $class, self::$default_namespace . '\\' ) ) {
			return;
		}

		$relative_class_name = str_replace( self::$default_namespace.'\\', '', $class );

		$classes_aliases = self::get_classes_aliases();

		$has_class_alias = isset( $classes_aliases[ $relative_class_name ] );

		$final_class_name = self::$default_namespace . '\\' . $relative_class_name;

		if ( ! class_exists( $final_class_name ) ) {
			self::load_class( $relative_class_name );
		}

		if ( $has_class_alias ) {
			class_alias( $final_class_name, $class );

		}
	}
}

