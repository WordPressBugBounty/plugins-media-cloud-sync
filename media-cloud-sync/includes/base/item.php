<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Item {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;

    protected $config;
    protected $bucketConfig;
    protected $settings;
    protected $credentials;
    protected $service;

    protected $bucket_name;
    protected $region = '';

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url   = WPMCS_ASSETS_URL;
        $this->version      = WPMCS_VERSION;
        $this->token        = WPMCS_TOKEN;

        // Initialize setup
        $this->init();
    }

    /**
     * Initialize datas
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
        $this->service      =  isset($this->credentials['service']) && !empty($this->credentials['service']) 
                                ? $this->credentials['service']
                                : '';

        if (isset($this->bucketConfig['bucket_name'])) {
            $this->bucket_name = $this->bucketConfig['bucket_name'];
        }
        if (isset($this->config['region'])) {
            $this->region = $this->config['region'];
        }
    }

    /**
     * Add Item In database
     * @since 1.0.0
     * @param
     */
    public function add(
        $source_id,
        $url,
        $key,
        $source_path,
        $original_source_path = '',
        $original_key = '',
        $meta = array(),
        $source_type = 'media_library',
        $is_private = 0
    ) {
        global $wpdb;
        $item_id = false;
        $data = array(
            'provider'      => $this->service,
            'region'        => $this->region,
            'storage'       => $this->bucket_name,
            'source_id'     => $source_id,
            'source_path'   => $source_path,
            'source_type'   => $source_type,
            'url'           => $url,
            'key'           => $key,
            'original_source_path' => $original_source_path,
            'original_key'  => $original_key,
            'is_private'    => $is_private,
            'extra'         => Utils::maybe_serialize($meta),
        );

        // Do some pre-update actions
        $this->pre_update_item($source_id, $data, [], $source_type);

        if ($wpdb->insert(Db::get_table_name(), $data)) {
            $item_id = $wpdb->insert_id;
            $data['id'] = $item_id;
            Integration::update_meta($source_id, 'item', $data, false, false, $source_type);
            Cache::update_item_cache($source_id.'_item_'.$source_type, $data);
            Counter::add( 'uploaded', $source_type );
        }

        // Do some post-update actions
        $this->post_update_item($source_id, $data, $source_type);

        return $item_id;
    }


    /**
     * Get Item From Db
     */
    public function get($source_id, $source_type = 'media_library') {
        global $wpdb;
        $item = Cache::get_item_cache($source_id.'_item_'.$source_type, false); 
        if($item === false) {
            $item = Integration::get_meta($source_id, 'item', false, false, false, $source_type);
            if($item == false){
                $item_table = Db::get_table_name();
                $query = "SELECT * FROM {$item_table} 
                    WHERE source_id = %d 
                    AND source_type = %s
                    AND provider = %s
                    AND storage = %s";
                
                if(!empty($this->region)) {
                    $query .= " AND region = %s";
                    $query = $wpdb->prepare($query, $source_id, $source_type, $this->service, $this->bucket_name, $this->region);
                } else {
                    $query = $wpdb->prepare($query, $source_id, $source_type, $this->service, $this->bucket_name);
                }
                
                $item = $wpdb->get_row( $query, ARRAY_A);

                if($wpdb->last_error || null === $item || !(isset($item) && !empty($item))) {
                    Cache::update_item_cache($source_id.'_item_'.$source_type, '');
                    return false;
                }

                Integration::update_meta($source_id, 'item', $item, false, false, $source_type);
            } 
            // Update cache
            Cache::update_item_cache($source_id.'_item_'.$source_type, $item == false ? '' : $item);
        }
        
        /**
         * Filter to modify item data when retrieved
         * @param array|false $item
         * @param int $source_id
         * @param string $source_type
         * @since 1.3.5
         */
        return apply_filters( 'wpmcs_get_item' , !empty($item) ? $item : false, $source_id, $source_type );
    }

    /**
     * Function to delete item from data base
     * @since 1.0.0
     */
    public function delete($source_id, $source_type = 'media_library'){
        global $wpdb;
        if (isset($source_id) && !Utils::is_empty($source_id)) {
            // Delete attachments by item from cloud
            $item = $this->get($source_id, $source_type);
            $this->delete_attachments_by_item($item);

            $where = array(
                'source_id' => $source_id, 
                'source_type' => $source_type, 
                'provider' => $this->service,
                'storage' => $this->bucket_name,
            );
            if(!empty($this->region)) {
                $where['region'] = $this->region;
            }
            $rows = $wpdb->delete( Db::get_table_name(), $where );
            if ($wpdb->last_error || false === $rows) {
                return false;
            }
            Integration::delete_meta($source_id, 'item', false, $source_type);
            Cache::delete_item_cache($source_id.'_item_'.$source_type);
            Counter::remove( 'uploaded', $source_type );

            // Remove logs by media ID & source type 
            Logger::instance()->remove_log_by_media_id($source_id, $source_type);

            return true;
        }
        return false;
    }


     /**
     * Function to update item in data base
     * @since 1.0.0
     */
    public function update($source_id, $data, $source_type = 'media_library') {
        global $wpdb;
        if (isset($source_id) && !Utils::is_empty($source_id)) {
            $data['provider'] = $this->service;
            $data['storage'] = $this->bucket_name;
            $data['region'] = $this->region;

            $old_item = $this->get($source_id, $source_type);

            // update data from $old_item if not exists in $data
            if (isset($old_item) && !empty($old_item)) {
                foreach ($old_item as $key => $value) {
                    if (!isset($data[$key])) {
                        $data[$key] = $value;
                    }
                }
            }

            // Do some pre-update actions
            $this->pre_update_item($source_id, $data, $old_item, $source_type);

            $where = array(
                'source_id'     => $source_id,
                'source_type'   => $source_type,
                'provider'      => $this->service,
                'storage'       => $this->bucket_name,
            );
            if(!empty($this->region)) {
                $where['region'] = $this->region;
            }
            $rows = $wpdb->update(Db::get_table_name(), $data, $where);
            if ($wpdb->last_error || false === $rows) {
                return false;
            }

            Integration::delete_meta($source_id, 'item', false, $source_type);
            Cache::delete_item_cache($source_id.'_item_'.$source_type);

            // Reset cache
            $this->get($source_id, $source_type);
            // Do some post-update actions
            $this->post_update_item($source_id, $data, $source_type);

            return true;
        }
        return false;
    }

    /**
     * Get extra values of item from database
     * @since 1.0.0
     * @param
     */
    public function get_extras($source_id, $field = false, $source_type = 'media_library'){
        $data = $this->get($source_id, $source_type); 
        if ($data) {
            if (isset($data['extra']) && !empty($data['extra'])) {

                if(!Utils::is_empty($field)) {
                    $extras = Utils::maybe_unserialize($data['extra']);
                    return isset($extras[$field]) && !empty($extras[$field]) ? $extras[$field] : false;
                }

                return Utils::maybe_unserialize($data['extra']);
            }
        }
        return false;
    }


    /**
     * Get column of item from database
     * @since 1.0.0
     * @param
     */
    public function get_field($source_id, $field, $source_type = 'media_library'){
        $data = $this->get($source_id, $source_type); 
        if ($data) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
        }
        return false;
    }


    /**
     * Get backup values of item from database
     * @since 1.0.0
     * @param
     */
    public function get_backup($source_id, $source_type = 'media_library'){
        $data = $this->get_extras($source_id, false, $source_type);
        if ($data) {
            if (isset($data['backup']) && !empty($data['backup'])) {
                return Utils::maybe_unserialize($data['backup']);
            }
        }
        return false;
    }


    /**
     * Checking Item that is served by provider And Is rewrite URL is enabled
     * @since 1.0.0
     * @param
     */
    public function is_available_from_provider($attachment_id, $check_rewrite = true, $source_type = 'media_library') {
        $item = $this->get($attachment_id, $source_type);
        if(Utils::is_empty($item)) {
            return false;
        }

        if (
            $item['provider'] == $this->service && 
            $item['storage'] == $this->bucket_name && 
            $item['source_type'] == $source_type 
        ) {
            if(
                ($check_rewrite && (isset($this->settings['rewrite_url']) && $this->settings['rewrite_url'])) ||
                !$check_rewrite
            ) {
                return true;
            } 
        }
        return false;
    }

    /**
     * Get items by source paths
     *
     * @param array|string $paths
     * @param bool         $exact_match Use exact paths or greedy match
     * @param bool         $first_only  Return only the first matched item
     *
     * @return array
     */
    public function get_items_by_paths( $paths, $exact_match = true, $first_only = false, $type = 'source' ) {
        global $wpdb;

        if ( ! is_array( $paths ) && is_string( $paths ) && ! empty( $paths ) ) {
            $paths = [ $paths ];
        }

        if ( Utils::is_empty( $paths ) ) {
            return [];
        }

        /**
         * Field => Index mapping
         * field_name => index_name
         */
        switch ( $type ) {
            case 'key':
                $fields = [
                    'key'          => 'uidx_key',
                    'original_key' => 'uidx_original_key',
                ];
                break;

            default:
                $fields = [
                    'source_path'          => 'uidx_source_path',
                    'original_source_path' => 'uidx_original_source_path',
                ];
                break;
        }

        if ( empty( $fields ) ) {
            return [];
        }

        // Normalize & deduplicate
        $paths = array_unique( array_map( 'esc_sql', $paths ) );

        $table = Db::get_table_name();

        // Build USE INDEX clause from field map
        $index_list = implode( ', ', array_values( $fields ) );

        $sql = "
            SELECT DISTINCT source_id, source_type
            FROM {$table} USE INDEX ({$index_list})
            WHERE provider = %s
            AND storage  = %s
        ";

        $params = [
            $this->service,
            $this->bucket_name,
        ];

        // Optional region
        if ( ! empty( $this->region ) ) {
            $sql     .= " AND region = %s";
            $params[] = $this->region;
        }

        /**
         * Path conditions
         */
        $conditions = [];

        if ( $exact_match ) {
            $in = "'" . implode( "','", $paths ) . "'";

            foreach ( array_keys( $fields ) as $column ) {
                $conditions[] = "`{$column}` IN ({$in})";
            }
        } else {
            foreach ( $paths as $path ) {
                $ext  = pathinfo( $path, PATHINFO_EXTENSION );
                $base = $ext
                    ? substr_replace( $path, '%', -strlen( $ext ) - 1 )
                    : $path . '%';

                foreach ( array_keys( $fields ) as $column ) {
                    $conditions[] = "`{$column}` LIKE '{$base}'";
                }
            }
        }

        if ( ! empty( $conditions ) ) {
            $sql .= " AND ( " . implode( ' OR ', $conditions ) . " )";
        }

        // First only
        if ( $first_only ) {
            $sql .= " ORDER BY source_id ASC LIMIT 1";
        }

        $prepared = $wpdb->prepare( $sql, $params );
        $results  = $wpdb->get_results( $prepared, ARRAY_A );

        if ( $wpdb->last_error || empty( $results ) ) {
            return [];
        }

        // Hydration
        if ( $first_only ) {
            return $this->get(
                (int) $results[0]['source_id'],
                $results[0]['source_type']
            );
        }

        $items = [];

        foreach ( $results as $row ) {
            $item = $this->get(
                (int) $row['source_id'],
                $row['source_type']
            );

            if ( ! Utils::is_empty( $item ) ) {
                $items[] = $item;
            }
        }

        return $items;
    }


    /**
     * Get similar existing files by source path prefix
     * Searches in source_path and original_source_path
     *
     * @since 1.0.0
     */
    public function get_similar_files_by_path( $path ) {
        global $wpdb;

        if ( Utils::is_empty( $path ) ) {
            return false;
        }

        $table = Db::get_table_name();
        $like  = $wpdb->esc_like( $path ) . '%';

        $base_where = "
            provider = %s
            AND storage = %s
            " . ( ! empty( $this->region ) ? "AND region = %s" : '' );

        $params = [ $this->service, $this->bucket_name ];
        if ( ! empty( $this->region ) ) {
            $params[] = $this->region;
        }

        $sql = "
            (
                SELECT source_path
                FROM {$table} USE INDEX (idx_source_path_provider)
                WHERE {$base_where}
                AND source_path LIKE %s
            )
            UNION DISTINCT
            (
                SELECT original_source_path AS source_path
                FROM {$table} USE INDEX (uidx_original_source_path)
                WHERE {$base_where}
                AND original_source_path LIKE %s
            )
        ";

        $params = array_merge( $params, [ $like ], $params, [ $like ] );

        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, $params ),
            ARRAY_A
        );

        return ( $wpdb->last_error || empty( $results ) )
            ? false
            : array_column( $results, 'source_path' );
    }


     /**
     * Get service url of item from database
     * @since 1.0.0
     * @param
     */
    public function get_url($source_id, $size = 'full', $source_type = 'media_library'){
        if ($data = $this->get($source_id, $source_type)) {
            $extras = $this->get_extras($source_id, false, $source_type) ?: [];
            $key    = '';
            $url    = '';
            switch($size) {
                case 'full':
                    $key = $data['key'];
                    break;
                case 'original':
                    if( isset($data['original_key']) && !empty($data['original_key']) ) {
                        $key = $data['original_key'];
                    }
                    break;
                default:
                    if(
                        isset($extras) && !empty($extras) &&
                        isset($extras['sizes']) && !empty($extras['sizes']) &&
                        isset($extras['sizes'][$size]) && !empty($extras['sizes'][$size])
                    ) {
                        $key = $extras['sizes'][$size]['key'];
                    }
            }

            if(!empty($key)){
                if (isset($data['is_private']) && $data['is_private']) {
                    $privateUrl = Integration::get_meta( $source_id, 'private_url_'.$size, false, false, false, $source_type );
                    if ($privateUrl === false) {
                        $new_url = Service::instance()->get_private_url($key);

                        if (!Utils::is_empty($new_url)) {
                            $privateUrl   = Cdn::may_generate_cdn_url($new_url, $key);
                            $expireMinutes  = (int)(isset($this->settings['private_url_expire']) && !empty($this->settings['private_url_expire']))
                                            ? $this->settings['private_url_expire']
                                            : 20;
                            $expireSeconds  = $expireMinutes * 60;

                            Integration::update_meta($source_id, 'private_url_'.$size, $privateUrl, false, $expireSeconds, $source_type);
                        }
                    }
                    return $privateUrl;
                } else {
                    $url = Service::instance()->get_url($key);
                    if(!Utils::is_empty($url)) {
                        return Cdn::may_generate_cdn_url($url, $key);
                    }
                }
            }
        }
        return false;
    }


   
    /**
     * Move file to server, given item id and size
     * If $all is true, it will move all files to server
     * If $backup is true, it will move backup file to server
     * @param int $source_id source id of item
     * @param string $size size of the file, default is full
     * @param string $source_type source type of item, default is media_library
     * @param bool $all if true, it will move all files to server
     * @param bool $backup if true, it will move backup file to server
     * @return array an array of server file paths
     */
    public function moveToServer($source_id, $size = 'full', $source_type = 'media_library', $all = false, $backup = false){
        $server_files   = [];
        $server_file    = false;
        $source_id      = (int)$source_id;
        $item           = $this->get($source_id, $source_type);

        // Remove log if exists before move to server
        Logger::instance()->remove_log('restore_to_server', $source_id, $source_type);

        if ( isset($item) && !empty($item) ) {
            $files = $this->moveToServerByItem($item, $size, $all);
            if (isset($files) && !empty($files)) {
                $server_files = $all ? array_merge($server_files, $files) : $files;
            }
        }
        if( $all && $backup ) {
            $backupItem = $this->get_backup($source_id, $source_type);
            if (isset($backupItem) && !empty($backupItem)) {
                $files = $this->moveToServerByItem($backupItem, $size, $all);
                if (isset($files) && !empty($files)) {
                    $server_files['backup'] = $files;
                }
            }
        }
        return $server_files;
    }

  
    /**
     * Copy back an item from the service to the server
     *
     * @since 1.0.0
     * @param array $item
     * @param string $size
     * @param bool $all
     * @return array|string
     */
    public function moveToServerByItem( $item = [], $size = 'full', $all = false ) {
        $source_id   = (int) ( $item['source_id'] ?? 0 );
        // Validate source ID
        if( $source_id <= 0 ) {
            return false;
        }

        $source_type = $item['source_type'] ?? 'media_library';
        $extras      = ! empty( $item['extra'] ) ? Utils::maybe_unserialize( $item['extra'] ) : [];

        // Build file map once
        $files = [
            'full'     => [
                'key'  => $item['key'] ?? null,
                'path' => $item['source_path'] ?? null,
            ],
            'original' => [
                'key'  => $item['original_key'] ?? null,
                'path' => $item['original_source_path'] ?? null,
            ],
        ];

        if ( ! empty( $extras['sizes'] ) ) {
            foreach ( $extras['sizes'] as $name => $data ) {
                $files[ $name ] = [
                    'key'  => $data['key'] ?? null,
                    'path' => $data['source_path'] ?? null,
                ];
            }
        }

        // ALL files
        if ( $all ) {
            $results = [];

            foreach ( $files as $label => $data ) {
                if ( $file = $this->move_to_server_by_key_and_path(
                    $data['key'],
                    $data['path'],
                    $source_id,
                    $source_type
                ) ) {
                    $results[ $label ] = $file;
                }
            }

            return $results;
        }

        // SINGLE file
        if ( isset( $files[ $size ] ) ) {
            return $this->move_to_server_by_key_and_path(
                $files[ $size ]['key'],
                $files[ $size ]['path'],
                $source_id,
                $source_type
            );
        }

        return false;
    }



    /**
     * Get service path of item from database by source url
     * @since 1.0.0
     * @param int $source_id
     * @param string $file
     * @param string $source_type
     * @return bool
     */
    public function moveToServerBySourcePath( $source_id, $file, $source_type = 'media_library' ) {
        $source_id = (int) $source_id;

        $item = $this->get( $source_id, $source_type );
        if ( Utils::is_empty( $item ) ) {
            return false;
        }

        $source_path = Utils::get_attachment_source_path( $file );
        if ( empty( $source_path ) ) {
            return false;
        }

        // 1. Check main file
        if (
            isset( $item['source_path'] ) &&
            ! empty( $item['source_path'] ) &&
            $item['source_path'] === $source_path &&
            $this->move_to_server_by_key_and_path(
                $item['key'] ?? null,
                $item['source_path'],
                $source_id,
                $source_type
            )
        ) {
            return true;
        }


        // 2. Check original
        if (
            isset( $item['original_source_path'] ) && ! empty( $item['original_source_path'] ) &&
            $item['original_source_path'] === $source_path &&
            $this->move_to_server_by_key_and_path(
                $item['original_key'] ?? null,
                $item['original_source_path'],
                $source_id,
                $source_type
            )
        ) {
            return true;
        }

        $extras = $this->get_extras( $source_id, false, $source_type ) ?: [];
        
        // 3. Check sizes
        if ( ! empty( $extras['sizes'] ) ) {
            foreach ( $extras['sizes'] as $size ) {
                if (
                    isset( $size['source_path'] ) &&
                    ! empty( $size['source_path'] ) &&
                    $size['source_path'] === $source_path &&
                    $this->move_to_server_by_key_and_path(
                        $size['key'] ?? null,
                        $size['source_path'],
                        $source_id,
                        $source_type
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Copy back a file from the service to the server
     *
     * @param string $key
     * @param string $relative_path
     * @param int    $source_id
     * @param string $source_type
     *
     * @return string|false
     */
    protected function move_to_server_by_key_and_path( $key, $relative_path, $source_id = 0, $source_type = 'media_library' ) {
        if ( empty( $key ) || empty( $relative_path ) ) {
            return false;
        }

        $upload_dir = wp_get_upload_dir();
        $file       = trailingslashit( $upload_dir['basedir'] ) . $relative_path;

        if ( file_exists( $file ) ) {
            return $file;
        }

        if ( Service::instance()->object_to_server( $key, $file ) ) {
            return $file;
        }

        Logger::instance()->add_log( 'restore_to_server', $source_id, $source_type, [
            'message' => __( 'The file could not be copied to the server. Please try again.', 'media-cloud-sync' ),
            'file'    => $key,
            'code'    => 404,
        ] );

        return false;
    }


    /**
     * Move original file to server
     *
     * @param int $source_id
     * @param string $source_type
     * @return bool
     */
    public function moveOriginalToServer($source_id, $source_type = 'media_library') {
        $data = $this->get($source_id, $source_type);
        if ($data) {
            $size = 'full';
            if (
                isset($data['original_source_path']) && !empty($data['original_source_path']) && 
                isset($data['original_key']) && !empty($data['original_key'])
            ) {
                $size = 'original';   
            }
            return $this->moveToServer($source_id, $size, $source_type);
        }
        return false;
    }


    /**
     * Delete media item
     */
    public function delete_attachments_by_item($item, $delete_backup = true) {
        $upload_dir = wp_get_upload_dir();

        if (isset($item['extra']) && !empty($item['extra'])) {
            $extras = Utils::maybe_unserialize($item['extra']);
            if (
                isset($extras) && !empty($extras) &&
                isset($extras['sizes']) && !empty($extras['sizes'])
            ) {
                foreach ($extras['sizes'] as $sub_image) {
                    if (isset($sub_image['key']) && !empty($sub_image['key'])) {
                        Service::instance()->deleteSingle($sub_image['key']);
                    }
                }
            }

            if (
                isset($extras) && !empty($extras) &&
                isset($extras['backup']) && !empty($extras['backup']) &&
                $delete_backup
            ) {
                $backup = Utils::maybe_unserialize($extras['backup']);
                if (isset($backup) && !empty($backup)) {
                    $this->delete_attachments_by_item($backup, false);
                }
            }
        }

        if (
            isset($item['original_key']) && !empty($item['original_key'])
        ) {
            Service::instance()->deleteSingle($item['original_key']);
        }

        if (isset($item['key']) && !empty($item['key'])) {
            Service::instance()->deleteSingle($item['key']);
        }
    }


    /**
     * Delete Cloud Files by Keys
     * @since 1.3.6
     */
    public function delete_cloud_files_by_keys( $keys = [] ) {
        if (Utils::is_empty($keys) || !is_array($keys)) {
            return false;
        }

        foreach ($keys as $key) {
            Service::instance()->deleteSingle( $key );
        }
        return true;
    }
    


    /**
     * Pre-update item actions
     * @since 1.2.13
     * @param int $source_id
     * @param array $data
     * @param string $source_type
     */
    public function pre_update_item($source_id, $new_item, $old_item = [], $source_type = 'media_library') {
        // Hook for pre-update actions
        do_action('wpmcs_pre_update_item', $source_id, $new_item, $old_item, $source_type);

        // Additional filter to modify files to be removed from server if needed
        $files_to_remove = apply_filters('wpmcs_pre_update_item_additional_files_to_remove_from_server', [], $source_id, $new_item, $old_item, $source_type);

        // Delete files if any
        if (!Utils::is_empty($files_to_remove)) {
            $this->may_be_delete_server_files_by_source_paths($files_to_remove);
        }
    }


    /**
     * Post-update item actions
     * @since 1.2.13
     * @param int $source_id
     * @param array $data
     * @param string $source_type
     * This function is called after an item has been updated in the database.
     * It triggers a WordPress action hook 'wpmcs_post_update_item' to allow other functions to hook into this event.
     * After that, it calls may_be_delete_server_files_by_id to potentially delete server files associated with the item.
     * 
     * @example
     * $item = Item::instance();
     * $item->post_update_item(123, $data, 'media_library');
     * 
     * This example will trigger the post-update actions for the item with ID 123.
     * It will execute any functions hooked to 'wpmcs_post_update_item' and may delete server files if the settings allow it.
     */
    public function post_update_item($source_id, $data, $source_type = 'media_library') {
        // Hook for post-update actions
        do_action('wpmcs_post_update_item', $source_id, $data, $source_type);

        // May be delete server files
        $this->may_be_delete_server_files_by_id($source_id, $source_type, true, true);
    }


    public function may_be_delete_server_files_by_source_paths($source_paths) {
        if (Utils::is_empty($source_paths) || !is_array($source_paths)) {
            return false;
        }

        if( !( isset($this->settings['remove_from_server']) && $this->settings['remove_from_server'] ) ) {
            return false;
        }

        foreach ($source_paths as $path) {
            if(file_exists($path)) {
                wp_delete_file($path, true);
            }
        }
        return true;
    }

    /**
     * Function to remove media from server by id
     * @param int $attachment_id
     * @param string $source_type
     * @param bool $delete_main_file
     * @param bool $delete_backup
     * 
     * This function checks if the item exists and if the setting to remove from server is enabled.
     * If so, it deletes the main file and any backup files associated with the item.
     * It also checks if the item has any extra data, and if so, it attempts to delete the backup files if specified.
     * Finally, it deletes the main file associated with the item.
     * 
     * @since 1.2.13
     * @return bool Returns true if the deletion process was initiated, false otherwise.
     * 
     * @throws \Exception If the item does not exist or if the removal from server setting is not enabled.
     * 
     * @example
     * $item = Item::instance();
     * $item->may_be_delete_server_files_by_id(123, 'media_library', true, true);
     * 
     * This example will attempt to delete the server files for the attachment with ID 123,
     * including the main file and any backup files, if the settings allow it.
     * 
     * @see Item::may_be_delete_server_files_by_item() for the function that actually performs the deletion.
     */
    public function may_be_delete_server_files_by_id($attachment_id, $source_type = 'media_library', $delete_main_file=false, $delete_backup = false) {
        $item = $this->get($attachment_id, $source_type);
        if(Utils::is_empty($item)) {
            return false;
        }

        if( !( isset($this->settings['remove_from_server']) && $this->settings['remove_from_server'] ) ) {
            return false;
        }

        if (isset($item['extra']) && !empty($item['extra'])) {
            $extras = Utils::maybe_unserialize($item['extra']);
            if (
                isset($extras) && !empty($extras) &&
                isset($extras['backup']) && !empty($extras['backup']) &&
                $delete_backup
            ) {
                $backup = Utils::maybe_unserialize($extras['backup']);
                if (isset($backup) && !empty($backup)) {
                    $this->may_be_delete_server_files_by_item($backup, $delete_main_file);
                }
            }
        }

        $this->may_be_delete_server_files_by_item($item, $delete_main_file, $delete_backup);

        return true;
    }

    /**
     * Function to remove media from server by item
     */
    public function may_be_delete_server_files_by_item( $item, $delete_main_file=false ) {
        if(Utils::is_empty($item)) {
            return false;
        }

        if( !( isset($this->settings['remove_from_server']) && $this->settings['remove_from_server'] ) ) {
            return false;
        }

        return $this->delete_server_files_by_item( $item, $delete_main_file );
    }


    /**
     * Function to remove media from server by item
     */
    public function delete_server_files_by_item( $item, $delete_main_file=false ) {
        $upload_dir     = wp_get_upload_dir();
        $has_original   = false;
        $files_to_remove  = array();

        $file_path = trailingslashit($upload_dir['basedir']) . $item['source_path'];

        if (isset($item['extra']) && !empty($item['extra'])) {
            $extras = Utils::maybe_unserialize($item['extra']);
            if (
                isset($extras) && !empty($extras) &&
                isset($extras['sizes']) && !empty($extras['sizes'])
            ) {
                foreach ($extras['sizes'] as $sub_image) {
                    if (isset($sub_image['source_path']) && !empty($sub_image['source_path'])) {
                        $file = trailingslashit($upload_dir['basedir']) . $sub_image['source_path'];
                        if(file_exists($file)) {
                            $files_to_remove[] = $file;
                        }
                    }
                }
            }
        }
        if (
            isset($item['original_source_path']) && !empty($item['original_source_path'])
        ) {
            $has_original = true;
            $file = trailingslashit($upload_dir['basedir']).$item['original_source_path'];
            if(file_exists($file) && $delete_main_file) {
                $files_to_remove[] = $file;
            }
        }
        if(file_exists($file_path)) {
            if ($has_original || (!$has_original && $delete_main_file)) {
                $files_to_remove[] = $file_path;
            }
        }


        $files_to_remove = apply_filters('wpmcs_files_to_remove_from_server', array_unique($files_to_remove), $item['source_id'], $item);

        if (!Utils::is_empty($files_to_remove)) {
            foreach ($files_to_remove as $file) {
                wp_delete_file($file, true);
            }
        }

        return true;
    }

    /**
     * Ensures only one instance of Class is loaded or can be loaded.
     *
     * @return Item Class instance
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