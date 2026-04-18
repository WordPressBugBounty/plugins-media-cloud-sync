<?php
namespace Dudlewebs\WPMCS;

defined('ABSPATH') || exit;

class Counter {
    private static $instance = null;
    private $assets_url;
    private $version;
    private $token;

    /**
     * Admin constructor.
     * @since 1.0.0
     */
    public function __construct() {
        $this->assets_url   = WPMCS_ASSETS_URL;
        $this->version      = WPMCS_VERSION;
        $this->token        = WPMCS_TOKEN;

        // Initialize setup
        // $this->init();
    }
    /**
     * 
     * Add Count
     * @since 1.0.0
     *
     */
    public static function add(  $property = 'uploaded', $source_type = 'media_library' ){
        $settings_key       = Schema::getConstant('COUNTER_KEY');
        $data               = Utils::get_option( $source_type, [], $settings_key );
        $data[$property]    = isset($data[$property]) ? (int)$data[$property] + 1 : 1;
        return Utils::update_option( $source_type, $data, $settings_key );
    }

    /**
     * 
     * Remove Count
     * @since 1.0.0
     *
     */
    public static function remove(  $property = 'uploaded', $source_type = 'media_library' ){
        $settings_key       = Schema::getConstant('COUNTER_KEY');
        $data               = Utils::maybe_unserialize( Utils::get_option_meta( $settings_key, true, true ) ) ?: [];

        $source_type_data               = isset($data[$source_type]) ? $data[$source_type] : [];
        $source_type_data[$property]    = isset($source_type_data[$property]) ? (int)$source_type_data[$property] - 1 : -1;

        return Utils::update_option( $source_type, $source_type_data, $settings_key );
    }

    /**
     * Get Count
     * @since 1.0.0
     */
    public static function get( $property = 'uploaded', $source_type = 'media_library' ) {
        // Get the count by source type
        $data = Utils::get_option( $source_type, [], Schema::getConstant('COUNTER_KEY') );
        return isset($data[$property]) ? (int)$data[$property] : 0;
    }

    /**
     * Update Count
     * @since 1.0.0
     */
    public static function update( $property, $value, $source_type = 'media_library' ) {
        $key = Schema::getConstant('COUNTER_KEY');
        // Update the count by source type
        $data = Utils::get_option( $source_type, [], $key );
        $data[$property] = $value;
        return Utils::update_option( $source_type, $data, $key );
    }

     /**
     * Get total count from all source types.
     *
     * @param string $property The property to get ('uploaded', 'total', or 'all').
     * @param bool   $combined Whether to combine counts from all source types.
     * @return mixed Combined total count (int) or an associative array grouped by source types.
     */
    public static function get_count($property = 'all', $combined = true) {
        $settings_key = Schema::getConstant('COUNTER_KEY');
        $source_types = Integration::$source_types;

        // Determine properties to process
        $properties = ($property === 'all') ? ['uploaded', 'total'] : [$property];
        $result = $combined ? array_fill_keys($properties, 0) : [];

        if (!empty($source_types)) {
            foreach ($source_types as $source_type => $args) {
                $data = Utils::get_option($source_type, [], $settings_key);

                foreach ($properties as $prop) {
                    $count = isset($data[$prop]) ? (int)$data[$prop] : 0;

                    if ($combined) {
                        // Add to combined total
                        $result[$prop] += $count;
                    } else {
                        // Group by source type
                        if (!isset($result[$source_type])) {
                            $result[$source_type] = array_fill_keys($properties, 0);
                        }
                        $result[$source_type][$prop] += $count;
                    }
                }
            }
        }

        return $combined ? ($property === 'all' ? $result : array_sum($result)) : $result;
    }

    /**
     * Fetch and update media counts for uploaded or total items.
     *
     * @param string $source_type The type of source to fetch (default: 'all').
     * @param string $property    The property to update ('uploaded', 'total', or 'all').
     */
    public static function fetch_and_update($property = 'all', $source_type = 'all') {
        global $wpdb;

        $credentials    = Utils::get_credentials();
        $config         = isset($credentials['config']) && !empty($credentials['config']) 
                                ? $credentials['config']
                                : [];
        $bucketConfig   =  isset($credentials['bucketConfig']) && !empty($credentials['bucketConfig']) 
                                ? $credentials['bucketConfig']
                                : [];
        $service        =  isset($credentials['service']) && !empty($credentials['service']) 
                                ? $credentials['service']
                                : '';
        $bucket_name    = isset($bucketConfig['bucket_name']) ? $bucketConfig['bucket_name'] : '';
        $region         = isset($config['region']) ? $config['region'] : '';

        $source_types = Integration::$source_types;
        $fetch_all = ($source_type === 'all');
        $properties = ($property === 'all') ? ['uploaded', 'total'] : [$property];

        foreach ($properties as $prop) {
            if ($prop === 'uploaded') {
                // Reset all
                if(!empty($source_types)) {
                    foreach($source_types as $source_t => $source_type_data) {
                        self::update('uploaded', 0, $source_t);
                    }
                }
                // Fetch uploaded counts grouped by source type
                $table_name = Db::get_table_name();
                $query = "SELECT source_type, COUNT(*) AS count 
                    FROM {$table_name} 
                    WHERE provider=%s 
                    AND storage=%s";

                if(!empty(($region))) {
                    $query .= " AND region=%s GROUP BY source_type";
                    $query = $wpdb->prepare($query, $service, $bucket_name, $region);
                } else {
                    $query .= " GROUP BY source_type";
                    $query = $wpdb->prepare($query, $service, $bucket_name);
                }

                $results = $wpdb->get_results($query, ARRAY_A);

                foreach ($results as $row) {
                    $source_key = $row['source_type'];
                    $count = (int) $row['count'];

                    if ($fetch_all || $source_key === $source_type) {
                        $data = $source_types[$source_key] ?? false;
                        if ($data) {
                            self::update('uploaded', $count, $source_key);
                        }
                    }
                }
            }

            if ($prop === 'total') {
                // Reset all
                if(!empty($source_types)) {
                    foreach($source_types as $source_t => $source_type_data) {
                        self::update('total', 0, $source_t);
                    }
                }
                // Fetch total counts for all or specific source types
                foreach ($source_types as $key => $data) {
                    if ($fetch_all || $key === $source_type) {
                        if ($data && $class = Integration::get_handler_class($key)) {
                            $total = $class::get_total_media($key);
                            self::update('total', $total, $key);
                        }
                    }
                }
            }
        }
    }

    
    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}