<?php
/**
 * Transient Manager
 * 
 * Unified transient storage with automatic cleanup and optimization.
 * Provides centralized management for all plugin transients.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Transient_Manager {

    private static $instance = null;
    private $transient_prefix = 'rwp_creator_suite_';
    private $debug_mode = false;
    private $stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
    );

    /**
     * Get single instance of the transient manager.
     *
     * @return RWP_Creator_Suite_Transient_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Cleanup expired transients on a schedule
        add_action( 'rwp_creator_suite_cleanup_transients', array( $this, 'cleanup_expired_transients' ) );
        
        // Schedule cleanup if not already scheduled
        if ( ! wp_next_scheduled( 'rwp_creator_suite_cleanup_transients' ) ) {
            wp_schedule_event( time(), 'daily', 'rwp_creator_suite_cleanup_transients' );
        }

        // Admin hooks for debugging
        if ( is_admin() && $this->debug_mode ) {
            add_action( 'admin_bar_menu', array( $this, 'add_debug_menu' ), 999 );
        }
    }

    /**
     * Get a transient with enhanced features.
     *
     * @param string $key Transient key (without prefix).
     * @param string $group Optional group for organization.
     * @return mixed|false Transient value or false if not found.
     */
    public function get( $key, $group = '' ) {
        $full_key = $this->build_key( $key, $group );
        $value = get_transient( $full_key );

        if ( false !== $value ) {
            $this->stats['hits']++;
            $this->log_debug( "Transient HIT: {$full_key}" );
        } else {
            $this->stats['misses']++;
            $this->log_debug( "Transient MISS: {$full_key}" );
        }

        return $value;
    }

    /**
     * Set a transient with enhanced features.
     *
     * @param string $key Transient key (without prefix).
     * @param mixed  $value Value to store.
     * @param int    $expiration Expiration time in seconds.
     * @param string $group Optional group for organization.
     * @param array  $options Additional options.
     * @return bool True on success, false on failure.
     */
    public function set( $key, $value, $expiration = HOUR_IN_SECONDS, $group = '', $options = array() ) {
        $full_key = $this->build_key( $key, $group );

        // Validate expiration time
        $expiration = $this->validate_expiration( $expiration );

        // Compress large data if enabled
        if ( isset( $options['compress'] ) && $options['compress'] && function_exists( 'gzcompress' ) ) {
            $serialized = maybe_serialize( $value );
            if ( strlen( $serialized ) > 1024 ) { // Only compress if > 1KB
                $value = array(
                    '_compressed' => true,
                    '_data' => base64_encode( gzcompress( $serialized ) ),
                );
            }
        }

        // Set the transient
        $result = set_transient( $full_key, $value, $expiration );

        if ( $result ) {
            $this->stats['sets']++;
            $this->log_debug( "Transient SET: {$full_key} (expires in {$expiration}s)" );

            // Track transient metadata
            $this->track_transient_metadata( $full_key, $group, $expiration, $options );
        }

        return $result;
    }

    /**
     * Delete a transient.
     *
     * @param string $key Transient key (without prefix).
     * @param string $group Optional group for organization.
     * @return bool True on success, false on failure.
     */
    public function delete( $key, $group = '' ) {
        $full_key = $this->build_key( $key, $group );
        $result = delete_transient( $full_key );

        if ( $result ) {
            $this->stats['deletes']++;
            $this->log_debug( "Transient DELETE: {$full_key}" );
            $this->remove_transient_metadata( $full_key );
        }

        return $result;
    }

    /**
     * Get or set a transient (cache-aside pattern).
     *
     * @param string   $key Transient key.
     * @param callable $callback Function to generate value if cache miss.
     * @param int      $expiration Expiration time in seconds.
     * @param string   $group Optional group for organization.
     * @param array    $options Additional options.
     * @return mixed Cached or generated value.
     */
    public function remember( $key, $callback, $expiration = HOUR_IN_SECONDS, $group = '', $options = array() ) {
        $value = $this->get( $key, $group );

        if ( false === $value ) {
            if ( ! is_callable( $callback ) ) {
                return false;
            }

            $value = call_user_func( $callback );
            
            if ( false !== $value ) {
                $this->set( $key, $value, $expiration, $group, $options );
            }
        }

        return $value;
    }

    /**
     * Delete multiple transients by pattern.
     *
     * @param string $pattern Pattern to match (supports wildcards).
     * @param string $group Optional group to limit deletion.
     * @return int Number of transients deleted.
     */
    public function delete_by_pattern( $pattern, $group = '' ) {
        global $wpdb;

        $full_pattern = $this->build_key( $pattern, $group );
        $escaped_pattern = $wpdb->esc_like( $full_pattern );
        $escaped_pattern = str_replace( '*', '%', $escaped_pattern );

        $query = $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_name LIKE '_transient_%'",
            '_transient_' . $escaped_pattern
        );

        $transients = $wpdb->get_col( $query );
        $deleted_count = 0;

        foreach ( $transients as $transient_name ) {
            $key = str_replace( '_transient_', '', $transient_name );
            if ( delete_transient( $key ) ) {
                $deleted_count++;
                $this->remove_transient_metadata( $key );
            }
        }

        $this->log_debug( "Deleted {$deleted_count} transients matching pattern: {$full_pattern}" );
        return $deleted_count;
    }

    /**
     * Get all transients for a specific group.
     *
     * @param string $group Group name.
     * @return array Array of transient keys and values.
     */
    public function get_group( $group ) {
        $group_prefix = $this->build_key( '', $group );
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_name LIKE '_transient_%'",
            '_transient_' . $wpdb->esc_like( $group_prefix ) . '%'
        );

        $results = $wpdb->get_results( $query );
        $transients = array();

        foreach ( $results as $result ) {
            $key = str_replace( '_transient_', '', $result->option_name );
            $transients[ $key ] = maybe_unserialize( $result->option_value );
        }

        return $transients;
    }

    /**
     * Delete all transients for a specific group.
     *
     * @param string $group Group name.
     * @return int Number of transients deleted.
     */
    public function delete_group( $group ) {
        return $this->delete_by_pattern( '*', $group );
    }

    /**
     * Cleanup expired transients.
     */
    public function cleanup_expired_transients() {
        global $wpdb;

        // Delete expired transients
        $current_time = time();
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_%' 
            AND option_value < %d",
            $current_time
        ) );

        // Delete orphaned transient values
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_%'
            AND option_name NOT LIKE '_transient_timeout_%'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->options} t2 
                WHERE t2.option_name = CONCAT('_transient_timeout_', SUBSTRING(o.option_name, 12))
            )"
        );

        // Clean up our metadata
        $this->cleanup_transient_metadata();

        $this->log_debug( "Cleanup: {$deleted} expired transients removed" );

        do_action( 'rwp_creator_suite_transients_cleaned', $deleted );
    }

    /**
     * Get memory usage statistics.
     *
     * @return array Memory usage stats.
     */
    public function get_memory_stats() {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_transients,
                SUM(LENGTH(option_value)) as total_size
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_{$this->transient_prefix}%'"
        );

        return array(
            'total_transients' => (int) $stats->total_transients,
            'total_size_bytes' => (int) $stats->total_size,
            'total_size_human' => size_format( (int) $stats->total_size ),
        );
    }

    /**
     * Get performance statistics.
     *
     * @return array Performance stats.
     */
    public function get_stats() {
        $hit_rate = $this->stats['hits'] + $this->stats['misses'] > 0 
            ? round( ( $this->stats['hits'] / ( $this->stats['hits'] + $this->stats['misses'] ) ) * 100, 2 )
            : 0;

        return array_merge( $this->stats, array(
            'hit_rate_percent' => $hit_rate,
        ) );
    }

    /**
     * Build full transient key.
     *
     * @param string $key Base key.
     * @param string $group Optional group.
     * @return string Full transient key.
     */
    private function build_key( $key, $group = '' ) {
        $parts = array( $this->transient_prefix );
        
        if ( ! empty( $group ) ) {
            $parts[] = sanitize_key( $group );
        }
        
        if ( ! empty( $key ) ) {
            $parts[] = sanitize_key( $key );
        }

        $full_key = implode( '_', array_filter( $parts ) );

        // Ensure key length doesn't exceed WordPress limits
        if ( strlen( $full_key ) > 172 ) { // WordPress limit is 172 chars
            $hash = hash( 'sha256', $full_key );
            $prefix = substr( $full_key, 0, 100 );
            $full_key = $prefix . '_' . substr( $hash, 0, 32 );
        }

        return $full_key;
    }

    /**
     * Validate expiration time.
     *
     * @param int $expiration Expiration in seconds.
     * @return int Validated expiration.
     */
    private function validate_expiration( $expiration ) {
        $expiration = (int) $expiration;

        // Minimum 1 minute
        if ( $expiration < MINUTE_IN_SECONDS ) {
            $expiration = MINUTE_IN_SECONDS;
        }

        // Maximum 1 week
        if ( $expiration > WEEK_IN_SECONDS ) {
            $expiration = WEEK_IN_SECONDS;
        }

        return $expiration;
    }

    /**
     * Track transient metadata for management.
     *
     * @param string $key Transient key.
     * @param string $group Group name.
     * @param int    $expiration Expiration time.
     * @param array  $options Options.
     */
    private function track_transient_metadata( $key, $group, $expiration, $options ) {
        $metadata = get_option( 'rwp_creator_suite_transient_metadata', array() );
        
        $metadata[ $key ] = array(
            'group' => $group,
            'created' => time(),
            'expires' => time() + $expiration,
            'options' => $options,
        );

        // Limit metadata to 1000 entries to prevent bloat
        if ( count( $metadata ) > 1000 ) {
            $metadata = array_slice( $metadata, -800, null, true );
        }

        update_option( 'rwp_creator_suite_transient_metadata', $metadata, false );
    }

    /**
     * Remove transient metadata.
     *
     * @param string $key Transient key.
     */
    private function remove_transient_metadata( $key ) {
        $metadata = get_option( 'rwp_creator_suite_transient_metadata', array() );
        
        if ( isset( $metadata[ $key ] ) ) {
            unset( $metadata[ $key ] );
            update_option( 'rwp_creator_suite_transient_metadata', $metadata, false );
        }
    }

    /**
     * Cleanup transient metadata.
     */
    private function cleanup_transient_metadata() {
        $metadata = get_option( 'rwp_creator_suite_transient_metadata', array() );
        $current_time = time();
        $cleaned = 0;

        foreach ( $metadata as $key => $data ) {
            if ( isset( $data['expires'] ) && $data['expires'] < $current_time ) {
                unset( $metadata[ $key ] );
                $cleaned++;
            }
        }

        if ( $cleaned > 0 ) {
            update_option( 'rwp_creator_suite_transient_metadata', $metadata, false );
        }
    }

    /**
     * Log debug information.
     *
     * @param string $message Debug message.
     */
    private function log_debug( $message ) {
        if ( $this->debug_mode && class_exists( 'RWP_Creator_Suite_Error_Logger' ) ) {
            RWP_Creator_Suite_Error_Logger::log(
                $message,
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_DEBUG
            );
        }
    }

    /**
     * Add debug menu to admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
     */
    public function add_debug_menu( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $stats = $this->get_stats();
        $memory_stats = $this->get_memory_stats();

        $wp_admin_bar->add_node( array(
            'id' => 'rwp-transient-debug',
            'title' => sprintf(
                'Transients: %d/%d (%.1f%%)',
                $stats['hits'],
                $stats['hits'] + $stats['misses'],
                $stats['hit_rate_percent']
            ),
            'meta' => array(
                'title' => sprintf(
                    'Hits: %d, Misses: %d, Sets: %d, Deletes: %d, Memory: %s',
                    $stats['hits'],
                    $stats['misses'],
                    $stats['sets'],
                    $stats['deletes'],
                    $memory_stats['total_size_human']
                ),
            ),
        ) );
    }

    /**
     * Reset statistics.
     */
    public function reset_stats() {
        $this->stats = array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
        );
    }

    /**
     * Flush all plugin transients.
     */
    public function flush_all() {
        return $this->delete_by_pattern( '*' );
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    public function __wakeup() {
        throw new Exception( 'Cannot unserialize singleton' );
    }
}