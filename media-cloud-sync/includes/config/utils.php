<?php

namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Utils {
    /**
     * Check a variable is empty
     * @since 1.0.0
     * @param string|integer|array|float 
     * @return boolean
     */
    public static function is_empty($var){
        if (is_array($var)) {
            return empty($var);
        } else {
            return ($var === null || $var === false || $var === '');
        }
    }

    /**
     * Function To get Plugin Specific Wordpress Option
     * @since 1.0.0
     * @return array|boolean|string|integer|float|double
     */
    public static function get_option($key, $default = false, $meta_name = false, $expire = false){
        $data = Cache::get_object_cache( $key, false, $meta_name, $expire );
        return $data == false ? $default : $data;
    }

    /**
     * Function To update Plugin Specific Wordpress Option
     * @since 1.0.0
     * @return boolean
     */
    public static function update_option($key, $options, $meta_name = false, $expire = false){
        return Cache::set_object_cache( $key, $options, false, $meta_name, $expire );
    }

    /**
     * Function To delete Plugin Specific Wordpress Option
     * @since 1.0.0
     * @return boolean
     */
    public static function delete_option($key, $meta_name = false){
        return Cache::delete_object_cache( $key, false, $meta_name );
    }

    /**
     * Function To get Plugin Specific Wordpress post meta
     * @since 1.0.0
     * @return array|boolean|string|integer|float|double
     */
    public static function get_meta($post_id, $key, $default = false, $meta_name = false, $expire = false){
        $data = Cache::get_object_cache( $key, $post_id, $meta_name, $expire );
        return $data == false ? $default : $data;
    }

    /**
     * Get Post Meta Data By Query
     * @since 1.0.0
     * @return boolean
     */
    public static function get_post_meta($post_id, $key, $single=false, $db_query=false){
        global $wpdb;
        if(!(!empty($key) || $post_id)) return false;

        if($db_query) {
            $meta_data = $wpdb->get_row( "SELECT meta_value FROM $wpdb->postmeta WHERE post_id=$post_id AND meta_key='$key'" );
            if ($wpdb->last_error || null === $meta_data || !isset($meta_data)) {
                return false;
            }
            return $meta_data->meta_value;
        } else {
            return get_post_meta( $post_id, $key, $single );
        }
    }

    /**
     * Get Option Data By Query
     * @since 1.0.0
     * @return boolean
     */
    public static function get_option_meta($key, $single=false, $db_query=false){
        global $wpdb;
        if(!(!empty($key))) return false;

        if($db_query) {
            $meta_data = $wpdb->get_row( "SELECT option_value FROM $wpdb->options WHERE option_name='$key'" );
            if ($wpdb->last_error || null === $meta_data || !isset($meta_data)) {
                return false;
            }
            return $meta_data->option_value;
        } else {
            return get_option( $key, $single );
        }
    }


    /**
     * Function To update Plugin Specific Wordpress post meta
     * @since 1.0.0
     * @return boolean
     */
    public static function update_meta($post_id, $key, $options, $meta_name = false, $expire = false){
        return Cache::set_object_cache( $key, $options, $post_id, $meta_name, $expire );
    }

    /**
     * Function To delete Plugin Specific Wordpress post meta
     * @since 1.0.0
     * @return boolean
     */
    public static function delete_meta($post_id, $key, $meta_name = false){
        return Cache::delete_object_cache( $key, $post_id, $meta_name );
    }

    /**
     * Function To get Plugin Specific Wordpress user meta
     * @since 1.0.0
     * @return array|boolean|string|integer|float|double
     */
    public static function get_user_meta($post_id, $key, $default = false, $meta_name = false, $expire = false){
        $data = Cache::get_object_cache( $key, $post_id, $meta_name, $expire, true );
        return $data == false ? $default : $data;
    }

    /**
     * Function To update Plugin Specific Wordpress user meta
     * @since 1.0.0
     * @return boolean
     */
    public static function update_user_meta($post_id, $key, $options, $meta_name = false, $expire = false){
        return Cache::set_object_cache( $key, $options, $post_id, $meta_name, $expire, true );
    }

    /**
     * Function To delete Plugin Specific Wordpress user meta
     * @since 1.0.0
     * @return boolean
     */
    public static function delete_user_meta($post_id, $key, $meta_name = false){
        return Cache::delete_object_cache( $key, $post_id, $meta_name, true );
    }


    /**
     * Clear meta from database
     */
    public static function clear_all_meta($meta_name = false, $meta_table = 'all') {
        global $wpdb;

		$meta_name = $meta_name == false || empty($meta_name) ? Schema::getConstant('META_KEY') : $meta_name;
	
		if ( empty( $meta_name ) ) {
			return false; // Avoid accidental deletions if the meta_key is empty
		}

        $meta_tables = $meta_table == 'all' ? ['postmeta', 'usermeta', 'options'] : [$meta_table];

        if( in_array('postmeta', $meta_tables) ) {
            // Clear post meta
            $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $meta_name ) );
        }

        if( in_array('usermeta', $meta_tables) ) {
            // Clear user meta
            $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE meta_key = %s", $meta_name ) );
        }
       
        if( in_array('options', $meta_tables) ) {
            // Clear options
            $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name = %s", $meta_name ) );
        }

        // Clear object cache
        Cache::flush_object_cache();
    }

    /**
     * Clears all content meta from the database
     *
     * @param string $meta_name Optional. The meta key to clear. Defaults to the constant CONTENT_META_KEY.
     */
    public static function clear_all_content_meta($meta_name = false) {
        global $wpdb;
        $meta_name = $meta_name == false || empty($meta_name) ? Schema::getConstant('CONTENT_META_KEY') : $meta_name;
        
        $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $meta_name ) );
        Cache::flush_object_cache();
    }



    /**
     * Function To get Current credentials
     * @since 1.0.0
     * @return array|boolean|string|integer|float|double
     */
    public static function get_credentials($option='', $default=false, $masked_config = false){
        $current_setttings = self::get_option('credentials',[], Schema::getConstant('GLOBAL_SETTINGS_KEY'));
        if(isset($current_setttings) && !empty($current_setttings)){
            if(isset($option) && !empty($option)){
                if(isset($current_setttings[$option])) {
                    if($masked_config && $option == 'config'){
                        $current_setttings[$option] = self::mask_config($current_setttings[$option]);
                    }
                    return $current_setttings[$option];
                } else {
                    return $default;
                }
            } else {
                if($masked_config && isset($current_setttings['config'])){
                    $current_setttings['config'] = self::mask_config($current_setttings['config']);
                }
                return $current_setttings;
            }
        } else {
            return $default;
        }
    }

    /**
     * Mask Config
     * @since 1.2.13
     * @return array|boolean|string|integer|float|double
     */
    public static function mask_config($config){
        foreach ($config as $key => $value) {
            if (in_array($key, ['config_json', 'secret_key'])) {
                $config[$key] = substr($value, 0, 4) . self::mask_string(substr($value, 4));
            }
        }
        return $config;
    }

    /**
     * Mask String
     * @since 1.2.13
     * @return array|boolean|string|integer|float|double
     */
    public static function mask_string($string){
        return str_repeat('*', strlen($string));
    }

    /**
     * Function To get Current settings
     * @since 1.0.0
     * @return array|boolean|string|integer|float|double
     */
    public static function get_settings($option='', $default=false){
        $current_setttings = self::get_option('settings',[], Schema::getConstant('GLOBAL_SETTINGS_KEY'));
        if(isset($current_setttings) && !empty($current_setttings)){
            if(isset($option) && !empty($option)){
                if(isset($current_setttings[$option])) {
                    return $current_setttings[$option];
                } else {
                    return $default;
                }
            } else {
                return $current_setttings;
            }
        } else {
            return $default;
        }
    }

    /**
     * Function To get Current statuses
     * @since 1.0.0
     * @return array|boolean|string|integer|float|double
     */
    public static function get_status($option='', $default=false){
        $current_setttings = self::get_option('status',[], Schema::getConstant('STATUS_KEY'));
        if(isset($current_setttings) && !empty($current_setttings)){
            if(isset($option) && !empty($option)){
                if(isset($current_setttings[$option])) {
                    return $current_setttings[$option];
                } else {
                    return $default;
                }
            } else {
                return $current_setttings;
            }
        } else {
            return $default;
        }
    }

    /**
     * Function To set statuses
     * @since 1.0.0
     * 
     */
    public static function set_status($option='', $data=[]){
        $current_setttings = self::get_option('status',[], Schema::getConstant('STATUS_KEY'));
        if(isset($option) && !empty($option)){
            $current_setttings[$option] = $data;
            return self::update_option('status', $current_setttings, Schema::getConstant('STATUS_KEY'));
        } else {
            return false;
        }
    }

    /**
     * Function To get Current Service
     * @since 1.0.0
     * @return array|boolean|string|integer|float|double
     */
    public static function get_service(){
        $current_service = self::get_credentials('service', '');
        if(isset($current_service) && !empty($current_service)){
            return $current_service;
        } else {
            return false;
        }
    }

    /**
     * Function To get Current config
     * @since 1.0.0
     * @return array|boolean|string|integer|float|double
     */
    public static function get_config($option='', $default=false){
        $current_setttings = self::get_credentials('config',[]);
        if(isset($current_setttings) && !empty($current_setttings)){
            if(isset($option) && !empty($option)){
                if(isset($current_setttings[$option])) {
                    return $current_setttings[$option];
                } else {
                    return $default;
                }
            } else {
                return $current_setttings;
            }
        } else {
            return $default;
        }
    }

    /**
     * Function to check serving media environment is ok
     * @since 1.0.0
     * @return boolean
     */
    public static function is_ok_to_serve($attachment_id = false, $check_id = true){
        return (
            self::get_service() &&
            self::get_settings('rewrite_url') &&
            ( $check_id ? isset($attachment_id) && !empty($attachment_id) : true )    
        );
    }

    /**
     * Function to check uploading media environment is ok
     * @since 1.0.0
     * @return boolean
     */
    public static function is_ok_to_upload($attachment_id = false){
        return (
            self::get_service() &&
            self::get_settings('copy_to_bucket') &&
            isset($attachment_id) && !empty($attachment_id)
        );
    }

    /**
     * Function To check service is enabled
     * @since 1.0.0
     * @return array|boolean|string|integer|float|double
     */
    public static function is_service_enabled(){
        return !!self::get_service();
    }

    /**
     * Check whether a file exist in a list of files
     * @since 1.0.1
     * @return boolean
     */
    public static function check_existing_file_names( $filename, $files ) {
        $fname = pathinfo( $filename, PATHINFO_FILENAME );
        $ext   = pathinfo( $filename, PATHINFO_EXTENSION );
    
        // Edge case, file names like `.ext`.
        if ( empty( $fname ) ) {
            return false;
        }
    
        if ( $ext ) {
            $ext = ".$ext";
        }
    
        $regex = '/^' . preg_quote( $fname ) . '-(?:\d+x\d+|scaled|rotated)' . preg_quote( $ext ) . '$/i';
    
        foreach ( $files as $file ) {
            if ( 
                preg_match( $regex, wp_basename($file) ) || 
                $filename == $file
            ) {
                return true;
            }
        }
    
        return false;
    }


    /**
     * Get relative attachment path for local source or remote object key.
     *
     * @param string $file File path, URL, or object key
     * @param string $type 'source' (local WP) or 'key' (cloud / CDN)
     *
     * @return string|false
     */
    public static function get_attachment_source_path( $file, $type = 'source' ) {
        if ( empty( $file ) || ! is_string( $file ) ) {
            return false;
        }

        // Normalize slashes early
        $file = str_replace( '\\', '/', $file );

        /**
         * -------------------------------------------------
         * TYPE: SOURCE (WordPress local paths / URLs)
         * -------------------------------------------------
         */
        if ( $type === 'source' ) {

            $uploads = wp_get_upload_dir();
            if ( empty( $uploads ) || ! empty( $uploads['error'] ) ) {
                return false;
            }

            $basedir = str_replace( '\\', '/', $uploads['basedir'] );
            $baseurl = str_replace( '\\', '/', $uploads['baseurl'] );

            // If URL → extract path
            if ( filter_var( $file, FILTER_VALIDATE_URL ) ) {
                $parsed = wp_parse_url( $file );
                $file   = $parsed['path'] ?? '';
            }

            // Strip WordPress upload root
            if ( 0 === strpos( $file, $basedir ) ) {
                $file = substr( $file, strlen( $basedir ) );
            } elseif ( 0 === strpos( $file, $baseurl ) ) {
                $file = substr( $file, strlen( $baseurl ) );
            }
        }

        /**
         * -------------------------------------------------
         * TYPE: KEY (Cloud / CDN paths or URLs)
         * -------------------------------------------------
         */
        elseif ( $type === 'key' ) {

            // URL → extract path only
            if ( filter_var( $file, FILTER_VALIDATE_URL ) ) {
                $parsed = wp_parse_url( $file );
                $file   = $parsed['path'] ?? '';
            }

            $file = ltrim( $file, '/' );

            $enable_base_path = self::get_settings( 'enable_base_path', true );
            $base_path        = trim( (string) self::get_settings( 'base_path', '' ), '/' );

            /**
             * If base_path is enabled and exists as a FULL segment,
             * strip everything before it.
             */
            if ( $enable_base_path && $base_path !== '' ) {
                $pattern = '#(^|/)' . preg_quote( $base_path, '#' ) . '(/|$)#';

                if ( preg_match( $pattern, $file, $m, PREG_OFFSET_CAPTURE ) ) {
                    $file = substr( $file, $m[0][1] );
                }
            }
        }

        // Final cleanup
        $file = trim( $file, '/' );

        /**
         * Reject directory-only paths
         */
        if ( $file === '' || substr( $file, -1 ) === '/' ) {
            return false;
        }

        return apply_filters(
            'wpmcs_get_relative_file_path_from_upload_directory',
            $file,
            $type
        );
    }


    /**
     * Whether the file may be synced based on plugin extension settings only.
     *
     * Uses `extensions_exclude` to block listed extensions and optional `extensions_include` as an allow-list.
     * When `extensions_include` is empty, no extension is blocked by the allow-list (only exclude applies).
     * WordPress MIME / `wp_check_filetype` is not used here.
     *
     * @since 1.0.0
     * @param string $path Absolute or relative file path.
     * @return bool
     */
    public static function is_extension_available( $path ) {
        $settings   = self::get_settings();
        $path_parts = pathinfo( $path );

        if ( ! isset( $path_parts['basename'] ) || $path_parts['basename'] === '' ) {
            return false;
        }

        $ext = isset( $path_parts['extension'] ) ? strtolower( $path_parts['extension'] ) : '';

        $allowed = [];
        if ( isset( $settings['extensions_include'] ) && is_array( $settings['extensions_include'] ) ) {
            $allowed = array_map( 'strtolower', array_filter( $settings['extensions_include'], 'strlen' ) );
        }

        $not_allowed = [];
        if ( isset( $settings['extensions_exclude'] ) && is_array( $settings['extensions_exclude'] ) ) {
            $not_allowed = array_map( 'strtolower', array_filter( $settings['extensions_exclude'], 'strlen' ) );
        }

        if ( in_array( $ext, $not_allowed, true ) ) {
            return false;
        }

        if ( ! empty( $allowed ) && ! in_array( $ext, $allowed, true ) ) {
            return false;
        }

        return true;
    }

    /**
     * Generate prefix for object versioning
     * @since 1.0.0
     * @return string
     */
    public static function generate_object_versioning_prefix(){
        $year_month     = self::get_settings('year_month');
        $date_format    = $year_month ? 'dHis' : 'YmdHis';

        // Use current time so that object version is unique
        $time = current_time('timestamp');

        $object_version = date($date_format, $time) . '/';
        $object_version = apply_filters('wpmcs_object_version_prefix', $object_version);

        return $object_version;
    }

    
    /**
     * Generate Key for Objects
     * @since 1.0.0
     */
    public static function generate_object_key($relative_source_path, $prefix) {
        $upload_path            = '';
        $enable_base_path       = self::get_settings('enable_base_path', true);
        $base_path              = self::get_settings('base_path', 'wp-content/uploads');
        $year_month             = self::get_settings('year_month', true);
        $relative_source_path   = ltrim( $relative_source_path, '/' );
        $file_name              = wp_basename( $relative_source_path );

        if(!$enable_base_path) { // If base path is not enabled
            $base_path = '';
        }

        $keep_original_folder_structure = apply_filters( 'wpmcs_keep_original_folder_structure', false );

        if(isset($base_path) && !empty($base_path)) {
            $upload_path.= preg_replace('~/+~', '/', 
                                    str_replace('\\', '/', 
                                        trim($base_path," \n\r\t\v\x00\/ ")
                                    )
                                );
        }

        if($keep_original_folder_structure) {
            $object_key = ltrim($upload_path . '/' . dirname( $relative_source_path ) . '/' . $prefix . $file_name, '/');
        } else {
            if(isset($year_month) && $year_month) {
                $year_month_prefix = self::get_year_month_from_file_path($relative_source_path);
                if($year_month_prefix) {
                    $upload_path.= '/'.$year_month_prefix;
                } else {
                    $upload_path.= '/'.date("Y/m");
                }
            }

            $object_key = ltrim($upload_path.'/'.$prefix.$file_name, '/');
        }
        
        return apply_filters( 'wpmcs_object_key', $object_key, $relative_source_path, $prefix );
    }


    /**
     * Check if a file path or URL follows the year/month structure and return the year/month as a string.
     *
     * @param string $path_or_url The relative path, absolute path, or URL to check.
     * @return string|false The year/month string if valid, false otherwise.
     */
    public static function get_year_month_from_file_path( $path_or_url ) {
        // Regex pattern to match paths and URLs containing 'YYYY/MM/' at any depth, allowing subdirectories afterward
        $pattern = '#(?:^|/)(\d{4})/(0[1-9]|1[0-2])/[^/]+(?:/[^/]+)*$#';

        // Check if the input matches the pattern
        if ( preg_match( $pattern, $path_or_url, $matches ) ) {
            // Return the year/month as a string in the format 'YYYY/MM'
            return $matches[1] . '/' . $matches[2];
        }

        return false; // Invalid format
    }


    /**
     * Maybe convert size to string
     *
     * @param int   $attachment_id
     * @param mixed $size
     *
     * @return null|string
     */
    public static function maybe_convert_size_to_string( $attachment_id, $size ) {
        if ( is_array( $size ) ) {
            $width  = ( isset( $size[0] ) && $size[0] > 0 ) ? $size[0] : 1;
			$height = ( isset( $size[1] ) && $size[1] > 0 ) ? $size[1] : 1;
			$original_aspect_ratio = $width / $height;
			$meta   = wp_get_attachment_metadata( $attachment_id );

			if ( ! isset( $meta['sizes'] ) || empty( $meta['sizes'] ) ) {
				return false;
			}

			$sizes = $meta['sizes'];
			uasort( $sizes, function ( $a, $b ) {
				// Order by image area
				return ( $a['width'] * $a['height'] ) - ( $b['width'] * $b['height'] );
			} );

			$near_matches = array();

			foreach ( $sizes as $size => $value ) {
				if ( $width > $value['width'] || $height > $value['height'] ) {
					continue;
				}
				$aspect_ratio = $value['width'] / $value['height'];
				if ( $aspect_ratio === $original_aspect_ratio ) {
					return $size;
				}
				$near_matches[] = $size;
			}
			// Return nearest match
			if ( ! empty( $near_matches ) ) {
				return $near_matches[0];
			}
        }

        return $size;
    }

    /**
     * Reduce the given URL down to the simplest version of itself.
     *
     * Useful for matching against the full version of the URL in a full-text search
     * or saving as a key for dictionary type lookup.
     *
     * @param string $url
     *
     * @return string
     */
    public static function reduce_url( $url ) {
        $parts = static::parse_url( $url );
        $host  = isset( $parts['host'] ) ? $parts['host'] : '';
        $port  = isset( $parts['port'] ) ? ":{$parts['port']}" : '';
        $path  = isset( $parts['path'] ) ? $parts['path'] : '';

        return '//' . $host . $port . $path;
    }

    /**
     * Remove scheme from URL.
     * 
     * @param string $url
     * @return string
     */
    public static function remove_scheme( $url ) {
        return preg_replace( '/^(?:http|https):/', '', $url );
    }

    /**
     * Remove size from filename (image[-100x100].jpeg).
     *
     * @param string $url
     * @param bool   $remove_extension
     *
     * @return string
     */
    public static function remove_size_from_filename( $url, $remove_extension = false ) {
        $url = preg_replace( '/^(\S+)-[0-9]{1,4}x[0-9]{1,4}(\.[a-zA-Z0-9\.]{2,})?/', '$1$2', $url );

        $url = apply_filters( 'wpmcs_remove_size_from_filename', $url );

        if ( $remove_extension ) {
            $ext = pathinfo( $url, PATHINFO_EXTENSION );
            $url = str_replace( ".$ext", '', $url );
        }

        return $url;
    }

    /**
     * Is the string a URL?
     *
     * @param mixed $string
     *
     * @return bool
     */
    public static function is_url( $string ): bool {
        if ( empty( $string ) || ! is_string( $string ) ) {
            return false;
        }

        if ( preg_match( '@^(?:https?:)?//[a-zA-Z0-9\-]+@', $string ) ) {
            return true;
        }

        return false;
    }

    /**
     * Parses a URL into its components. Compatible with PHP < 5.4.7.
     *
     * @param string $url       The URL to parse.
     *
     * @param int    $component PHP_URL_ constant for URL component to return.
     *
     * @return mixed An array of the parsed components, mixed for a requested component, or false on error.
     */
    public static function parse_url( $url, $component = -1 ) {
        $url       = trim( $url );
        $no_scheme = 0 === strpos( $url, '//' );

        if ( $no_scheme ) {
            $url = 'http:' . $url;
        }

        $parts = parse_url( $url, $component );

        if ( 0 < $component ) {
            return $parts;
        }

        if ( $no_scheme && is_array( $parts ) ) {
            unset( $parts['scheme'] );
        }

        return $parts;
    }

    /**
     * Is the given string a usable URL?
     *
     * We need URLs that include at least a domain and filename with extension
     * for URL rewriting in either direction.
     *
     * @param mixed $url
     *
     * @return bool
     */
    public static function is_file_url( $url ): bool {
        if ( ! static::is_url( $url ) ) {
            return false;
        }

        $parts = static::parse_url( $url );

        if (
            empty( $parts['host'] ) ||
            empty( $parts['path'] ) ||
            ! pathinfo( $parts['path'], PATHINFO_EXTENSION )
        ) {
            return false;
        }

        return true;
    }


    /**
	 * Remove query strings of services.
	 *
	 * @param string $content
	 * @param string $base_url Optional base URL that must exist within URL for Amazon query strings to be removed.
	 *
	 * @return string
	 */
	public static function remove_query_strings( $content, $base_url = '' ) {
		$pattern    = '\?[^\s"<\?]*(?:X-Amz-Algorithm|AWSAccessKeyId|Key-Pair-Id|GoogleAccessId)=[^\s"<\?]+';
		$group      = 0;

		if ( ! is_string( $content ) ) {
			return $content;
		}

		if ( ! empty( $base_url ) ) {
			$pattern = preg_quote( $base_url, '/' ) . '[^\s"<\?]+(' . $pattern . ')';
			$group   = 1;
		}
		if ( ! preg_match_all( '/' . $pattern . '/', $content, $matches ) || ! isset( $matches[ $group ] ) ) {
			// No query strings found, return
			return $content;
		}

		$matches = array_unique( $matches[ $group ] );

		foreach ( $matches as $match ) {
			$content = str_replace( $match, '', $content );
		}
		return $content;
	}

    /**
     * Maybe unserialize data, but not if an object.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function maybe_unserialize( $data ) {
        if ( is_serialized( $data ) ) {
            return @unserialize( $data, array( 'allowed_classes' => false ) ); // @phpcs:ignore
        }

        return $data;
    }


    /**
     * Serialize data if needed.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function maybe_serialize( $data ) {
        if ( is_array( $data ) || is_object( $data ) ) {
            return serialize( $data );
        }

        // If it's not an array or object, don't serialize. If it is already serialized, return as is.
        if ( is_serialized( $data ) ) {
            return $data;
        }

        return $data;
    }


    /**
     * Validate JSON
     */
    public static function is_json( $string ) {
        json_decode( $string );
        return ( json_last_error() == JSON_ERROR_NONE );
    }

    /**
     * Check whether a specific class::method exists in the current call stack.
     *
     * Useful for detecting callers like WooCommerce image regeneration
     * without hard dependencies.
     *
     * @since 1.3.7
     * @param string      $class    Fully qualified class name.
     * @param string|null $function Method name (optional).
     * @param int         $depth    Backtrace depth limit.
     *
     * @return bool
     */
    public static function is_called_from(
        string $class,
        ?string $function = null,
        int $depth = 15
    ) : bool {

        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $depth );

        foreach ( $trace as $frame ) {

            if ( empty( $frame['class'] ) ) {
                continue;
            }

            if ( $frame['class'] !== $class ) {
                continue;
            }

            // If function not specified, class match is enough
            if ( $function === null ) {
                return true;
            }

            if ( isset( $frame['function'] ) && $frame['function'] === $function ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Is this an AJAX process?
     *
     * @return bool
     */
    public static function is_ajax() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return true;
        }

        return false;
    }

    /**
	 * Helper function for filtering super globals. Easily testable.
	 *
	 * @param string $variable
	 * @param int    $type
	 * @param int    $filter
	 * @param mixed  $options
	 *
	 * @return mixed
	 */
	public static function filter_input( $variable, $type = INPUT_GET, $filter = FILTER_DEFAULT, $options = array() ) {
		return filter_input( $type, $variable, $filter, $options );
	}

	/**
	 * Get license data safe for frontend exposure (no raw key).
	 *
	 * @return array
	 */
	public static function get_safe_license_data() {
		$data = get_option('wpmcs_pro_license', []);
		if (empty($data)) {
			return [];
		}
		$key = $data['license_key'] ?? '';
		$masked = '';
		if (!empty($key)) {
			$parts = explode('-', $key);
			if (count($parts) <= 2) {
				$masked = str_repeat('*', strlen($key));
			} else {
				$first = $parts[0];
				$last = end($parts);
				$middle_count = count($parts) - 2;
				$masked_middle = array_fill(0, $middle_count, '****');
				$masked = $first . '-' . implode('-', $masked_middle) . '-' . $last;
			}
		}
		return [
			'masked_key'          => $masked,
			'status'              => $data['status'] ?? '',
			'expiry'              => $data['expiry'] ?? '',
			'is_expired'          => $data['is_expired'] ?? false,
			'is_domain_activated' => $data['is_domain_activated'] ?? false,
			'can_activate'        => $data['can_activate'] ?? false,
			'message'             => $data['message'] ?? '',
			'last_checked'        => $data['last_checked'] ?? 0,
		];
	}

}
