<?php
/**
 * Comprehensive Cache Manager
 *
 * Multi-tier caching system with intelligent invalidation and performance metrics.
 * Supports memory cache, transients, and object cache for optimal performance.
 *
 * @package    RWP_Creator_Suite
 * @subpackage RWP_Creator_Suite/common
 * @since      1.0.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Cache_Manager {

    /**
     * Singleton instance.
     *
     * @var RWP_Creator_Suite_Cache_Manager
     */
    private static $instance = null;

    /**
     * Memory cache for current request.
     *
     * @var array
     */
    private $memory_cache = array();

    /**
     * Cache configuration.
     *
     * @var array
     */
    private $config = array();

    /**
     * Performance metrics.
     *
     * @var array
     */
    private $metrics = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'invalidations' => 0,
    );

    /**
     * Cache groups and their configurations.
     *
     * @var array
     */
    private $cache_groups = array();

    /**
     * Warming strategies.
     *
     * @var array
     */
    private $warming_strategies = array();

    /**
     * Get singleton instance.
     *
     * @return RWP_Creator_Suite_Cache_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->init_config();
        $this->init_cache_groups();
        $this->init_hooks();
    }

    /**
     * Initialize cache configuration.
     */
    private function init_config() {
        $this->config = apply_filters( 'rwp_creator_suite_cache_config', array(
            'memory_limit' => 50, // MB
            'default_ttl' => HOUR_IN_SECONDS,
            'max_key_length' => 250,
            'enable_compression' => true,
            'enable_metrics' => true,
            'auto_cleanup' => true,
            'warming_enabled' => true,
        ) );
    }

    /**
     * Initialize cache groups.
     */
    private function init_cache_groups() {
        $this->cache_groups = array(
            'ai_responses' => array(
                'ttl' => 6 * HOUR_IN_SECONDS,
                'tier' => 'all', // memory, transient, object, all
                'compress' => true,
                'warm' => false,
                'tags' => array( 'ai', 'content' ),
            ),
            'platform_configs' => array(
                'ttl' => DAY_IN_SECONDS,
                'tier' => 'all',
                'compress' => false,
                'warm' => true,
                'tags' => array( 'config', 'platforms' ),
            ),
            'user_quotas' => array(
                'ttl' => HOUR_IN_SECONDS,
                'tier' => 'memory',
                'compress' => false,
                'warm' => false,
                'tags' => array( 'users', 'quotas' ),
            ),
            'settings' => array(
                'ttl' => 12 * HOUR_IN_SECONDS,
                'tier' => 'all',
                'compress' => false,
                'warm' => true,
                'tags' => array( 'settings', 'config' ),
            ),
            'templates' => array(
                'ttl' => 2 * HOUR_IN_SECONDS,
                'tier' => 'transient',
                'compress' => true,
                'warm' => false,
                'tags' => array( 'templates', 'content' ),
            ),
            'analytics' => array(
                'ttl' => 30 * MINUTE_IN_SECONDS,
                'tier' => 'memory',
                'compress' => false,
                'warm' => false,
                'tags' => array( 'analytics', 'stats' ),
            ),
        );

        $this->cache_groups = apply_filters( 'rwp_creator_suite_cache_groups', $this->cache_groups );
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        // Auto cleanup on WordPress cron
        if ( $this->config['auto_cleanup'] ) {
            add_action( 'wp_scheduled_delete', array( $this, 'cleanup_expired_cache' ) );
        }

        // Cache warming
        if ( $this->config['warming_enabled'] ) {
            add_action( 'init', array( $this, 'schedule_cache_warming' ) );
            add_action( 'rwp_cache_warming', array( $this, 'warm_cache' ) );
        }

        // Cache invalidation hooks
        add_action( 'rwp_creator_suite_settings_updated', array( $this, 'invalidate_settings_cache' ) );
        add_action( 'rwp_creator_suite_user_quota_reset', array( $this, 'invalidate_user_quota_cache' ) );
    }

    /**
     * Get cached data with multi-tier fallback.
     *
     * @param string $key Cache key.
     * @param string $group Cache group.
     * @return mixed|false Cached data or false if not found.
     */
    public function get( $key, $group = 'default' ) {
        $normalized_key = $this->normalize_key( $key, $group );
        $group_config = $this->get_group_config( $group );

        // Try memory cache first
        if ( $this->is_tier_enabled( 'memory', $group_config ) ) {
            if ( isset( $this->memory_cache[ $normalized_key ] ) ) {
                $cache_data = $this->memory_cache[ $normalized_key ];
                if ( $this->is_cache_valid( $cache_data ) ) {
                    $this->record_hit();
                    return $this->decompress_if_needed( $cache_data['data'], $group_config );
                } else {
                    unset( $this->memory_cache[ $normalized_key ] );
                }
            }
        }

        // Try WordPress transients
        if ( $this->is_tier_enabled( 'transient', $group_config ) ) {
            $transient_data = get_transient( $normalized_key );
            if ( false !== $transient_data ) {
                // Store in memory cache for faster access
                if ( $this->is_tier_enabled( 'memory', $group_config ) ) {
                    $this->set_memory_cache( $normalized_key, $transient_data, $group_config );
                }
                $this->record_hit();
                return $this->decompress_if_needed( $transient_data, $group_config );
            }
        }

        // Try WordPress object cache
        if ( $this->is_tier_enabled( 'object', $group_config ) ) {
            $object_data = wp_cache_get( $key, $group );
            if ( false !== $object_data ) {
                // Store in memory and transient cache
                if ( $this->is_tier_enabled( 'memory', $group_config ) ) {
                    $this->set_memory_cache( $normalized_key, $object_data, $group_config );
                }
                if ( $this->is_tier_enabled( 'transient', $group_config ) ) {
                    set_transient( $normalized_key, $object_data, $group_config['ttl'] );
                }
                $this->record_hit();
                return $this->decompress_if_needed( $object_data, $group_config );
            }
        }

        $this->record_miss();
        return false;
    }

    /**
     * Set cached data across enabled tiers.
     *
     * @param string $key Cache key.
     * @param mixed  $data Data to cache.
     * @param string $group Cache group.
     * @param int    $ttl Time to live in seconds.
     * @return bool Success status.
     */
    public function set( $key, $data, $group = 'default', $ttl = null ) {
        $normalized_key = $this->normalize_key( $key, $group );
        $group_config = $this->get_group_config( $group );
        $ttl = $ttl ?? $group_config['ttl'];

        $compressed_data = $this->compress_if_needed( $data, $group_config );
        $success = true;

        // Set in memory cache
        if ( $this->is_tier_enabled( 'memory', $group_config ) ) {
            $success &= $this->set_memory_cache( $normalized_key, $compressed_data, $group_config, $ttl );
        }

        // Set in transient cache
        if ( $this->is_tier_enabled( 'transient', $group_config ) ) {
            $success &= set_transient( $normalized_key, $compressed_data, $ttl );
        }

        // Set in object cache
        if ( $this->is_tier_enabled( 'object', $group_config ) ) {
            $success &= wp_cache_set( $key, $compressed_data, $group, $ttl );
        }

        if ( $success ) {
            $this->record_set();
            
            // Fire action for cache warming
            do_action( 'rwp_creator_suite_cache_set', $key, $group, $data );
        }

        return $success;
    }

    /**
     * Delete cached data from all tiers.
     *
     * @param string $key Cache key.
     * @param string $group Cache group.
     * @return bool Success status.
     */
    public function delete( $key, $group = 'default' ) {
        $normalized_key = $this->normalize_key( $key, $group );
        $group_config = $this->get_group_config( $group );
        $success = true;

        // Delete from memory cache
        if ( isset( $this->memory_cache[ $normalized_key ] ) ) {
            unset( $this->memory_cache[ $normalized_key ] );
        }

        // Delete from transient cache
        if ( $this->is_tier_enabled( 'transient', $group_config ) ) {
            $success &= delete_transient( $normalized_key );
        }

        // Delete from object cache
        if ( $this->is_tier_enabled( 'object', $group_config ) ) {
            $success &= wp_cache_delete( $key, $group );
        }

        if ( $success ) {
            $this->record_delete();
        }

        return $success;
    }

    /**
     * Remember pattern - get from cache or execute callback and cache result.
     *
     * @param string   $key Cache key.
     * @param callable $callback Callback to execute if cache miss.
     * @param string   $group Cache group.
     * @param int      $ttl Time to live in seconds.
     * @return mixed Cached or computed data.
     */
    public function remember( $key, $callback, $group = 'default', $ttl = null ) {
        $cached_data = $this->get( $key, $group );
        
        if ( false !== $cached_data ) {
            return $cached_data;
        }

        if ( ! is_callable( $callback ) ) {
            return false;
        }

        $data = call_user_func( $callback );
        
        if ( false !== $data ) {
            $this->set( $key, $data, $group, $ttl );
        }

        return $data;
    }

    /**
     * Invalidate cache by group or tags.
     *
     * @param array|string $groups_or_tags Groups or tags to invalidate.
     * @param string       $type Type of invalidation ('group' or 'tag').
     * @return bool Success status.
     */
    public function invalidate( $groups_or_tags, $type = 'group' ) {
        $invalidated = 0;

        if ( 'group' === $type ) {
            $groups = (array) $groups_or_tags;
            foreach ( $groups as $group ) {
                $invalidated += $this->invalidate_group( $group );
            }
        } elseif ( 'tag' === $type ) {
            $tags = (array) $groups_or_tags;
            $invalidated += $this->invalidate_by_tags( $tags );
        }

        if ( $invalidated > 0 ) {
            $this->record_invalidation( $invalidated );
            do_action( 'rwp_creator_suite_cache_invalidated', $groups_or_tags, $type, $invalidated );
        }

        return $invalidated > 0;
    }

    /**
     * Invalidate entire cache group.
     *
     * @param string $group Cache group to invalidate.
     * @return int Number of invalidated items.
     */
    private function invalidate_group( $group ) {
        $invalidated = 0;
        $prefix = $this->get_key_prefix( $group );

        // Clear memory cache
        foreach ( $this->memory_cache as $key => $data ) {
            if ( str_starts_with( $key, $prefix ) ) {
                unset( $this->memory_cache[ $key ] );
                $invalidated++;
            }
        }

        // Clear transients (limited capability)
        // WordPress doesn't provide a way to delete all transients by prefix
        // We rely on expiration or custom tracking

        // Clear object cache if supported
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( $group );
        }

        return $invalidated;
    }

    /**
     * Invalidate cache by tags.
     *
     * @param array $tags Tags to invalidate.
     * @return int Number of invalidated items.
     */
    private function invalidate_by_tags( $tags ) {
        $invalidated = 0;

        foreach ( $this->cache_groups as $group => $config ) {
            if ( ! empty( array_intersect( $tags, $config['tags'] ) ) ) {
                $invalidated += $this->invalidate_group( $group );
            }
        }

        return $invalidated;
    }

    /**
     * Warm cache for configured groups.
     */
    public function warm_cache() {
        foreach ( $this->cache_groups as $group => $config ) {
            if ( $config['warm'] && isset( $this->warming_strategies[ $group ] ) ) {
                $strategy = $this->warming_strategies[ $group ];
                if ( is_callable( $strategy ) ) {
                    try {
                        call_user_func( $strategy, $this );
                    } catch ( Exception $e ) {
                        error_log( "RWP Cache Manager: Error warming cache for group '$group': " . $e->getMessage() );
                    }
                }
            }
        }
    }

    /**
     * Add cache warming strategy for a group.
     *
     * @param string   $group Cache group.
     * @param callable $strategy Warming strategy callback.
     */
    public function add_warming_strategy( $group, $strategy ) {
        if ( is_callable( $strategy ) ) {
            $this->warming_strategies[ $group ] = $strategy;
        }
    }

    /**
     * Schedule cache warming.
     */
    public function schedule_cache_warming() {
        if ( ! wp_next_scheduled( 'rwp_cache_warming' ) ) {
            wp_schedule_event( time(), 'hourly', 'rwp_cache_warming' );
        }
    }

    /**
     * Cleanup expired cache entries.
     */
    public function cleanup_expired_cache() {
        $cleaned = 0;

        // Clean memory cache
        foreach ( $this->memory_cache as $key => $data ) {
            if ( ! $this->is_cache_valid( $data ) ) {
                unset( $this->memory_cache[ $key ] );
                $cleaned++;
            }
        }

        // Fire action for additional cleanup
        do_action( 'rwp_creator_suite_cache_cleanup', $cleaned );
    }

    /**
     * Get cache statistics.
     *
     * @return array Cache statistics.
     */
    public function get_stats() {
        $total_requests = $this->metrics['hits'] + $this->metrics['misses'];
        $hit_rate = $total_requests > 0 ? ( $this->metrics['hits'] / $total_requests ) * 100 : 0;

        return array(
            'metrics' => $this->metrics,
            'hit_rate' => round( $hit_rate, 2 ),
            'memory_usage' => $this->get_memory_usage(),
            'cache_groups' => array_keys( $this->cache_groups ),
            'total_requests' => $total_requests,
        );
    }

    /**
     * Get cache group configuration.
     *
     * @param string $group Cache group.
     * @return array Group configuration.
     */
    private function get_group_config( $group ) {
        return isset( $this->cache_groups[ $group ] ) 
            ? $this->cache_groups[ $group ] 
            : array(
                'ttl' => $this->config['default_ttl'],
                'tier' => 'all',
                'compress' => false,
                'warm' => false,
                'tags' => array(),
            );
    }

    /**
     * Check if cache tier is enabled for group.
     *
     * @param string $tier Cache tier.
     * @param array  $config Group configuration.
     * @return bool
     */
    private function is_tier_enabled( $tier, $config ) {
        return 'all' === $config['tier'] || $tier === $config['tier'];
    }

    /**
     * Normalize cache key.
     *
     * @param string $key Cache key.
     * @param string $group Cache group.
     * @return string Normalized key.
     */
    private function normalize_key( $key, $group ) {
        $prefix = $this->get_key_prefix( $group );
        $normalized = $prefix . hash( 'sha256', $key );
        
        // Ensure key length is within limits
        if ( strlen( $normalized ) > $this->config['max_key_length'] ) {
            $normalized = substr( $normalized, 0, $this->config['max_key_length'] );
        }

        return $normalized;
    }

    /**
     * Get key prefix for group.
     *
     * @param string $group Cache group.
     * @return string Key prefix.
     */
    private function get_key_prefix( $group ) {
        return 'rwp_' . $group . '_';
    }

    /**
     * Set memory cache with size limits.
     *
     * @param string $key Cache key.
     * @param mixed  $data Cache data.
     * @param array  $config Group configuration.
     * @param int    $ttl Time to live.
     * @return bool Success status.
     */
    private function set_memory_cache( $key, $data, $config, $ttl = null ) {
        $ttl = $ttl ?? $config['ttl'];
        $expires = time() + $ttl;

        $this->memory_cache[ $key ] = array(
            'data' => $data,
            'expires' => $expires,
            'size' => $this->get_data_size( $data ),
        );

        $this->check_memory_limits();
        return true;
    }

    /**
     * Check if cached data is still valid.
     *
     * @param array $cache_data Cache data structure.
     * @return bool
     */
    private function is_cache_valid( $cache_data ) {
        return isset( $cache_data['expires'] ) && time() < $cache_data['expires'];
    }

    /**
     * Compress data if compression is enabled.
     *
     * @param mixed $data Data to compress.
     * @param array $config Group configuration.
     * @return mixed Compressed or original data.
     */
    private function compress_if_needed( $data, $config ) {
        if ( $config['compress'] && $this->config['enable_compression'] && function_exists( 'gzcompress' ) ) {
            return array(
                'compressed' => true,
                'data' => gzcompress( serialize( $data ) ),
            );
        }

        return $data;
    }

    /**
     * Decompress data if needed.
     *
     * @param mixed $data Data to decompress.
     * @param array $config Group configuration.
     * @return mixed Decompressed or original data.
     */
    private function decompress_if_needed( $data, $config ) {
        if ( is_array( $data ) && isset( $data['compressed'] ) && $data['compressed'] ) {
            if ( function_exists( 'gzuncompress' ) ) {
                return unserialize( gzuncompress( $data['data'] ) );
            }
        }

        return $data;
    }

    /**
     * Get data size in bytes.
     *
     * @param mixed $data Data to measure.
     * @return int Size in bytes.
     */
    private function get_data_size( $data ) {
        return strlen( serialize( $data ) );
    }

    /**
     * Check memory limits and clean if needed.
     */
    private function check_memory_limits() {
        $current_size = $this->get_memory_usage();
        $limit_bytes = $this->config['memory_limit'] * 1024 * 1024;

        if ( $current_size > $limit_bytes ) {
            $this->cleanup_memory_cache();
        }
    }

    /**
     * Get current memory usage.
     *
     * @return int Memory usage in bytes.
     */
    private function get_memory_usage() {
        $total_size = 0;
        foreach ( $this->memory_cache as $cache_data ) {
            $total_size += isset( $cache_data['size'] ) ? $cache_data['size'] : 0;
        }
        return $total_size;
    }

    /**
     * Cleanup memory cache by removing oldest entries.
     */
    private function cleanup_memory_cache() {
        // Sort by expiration time
        uasort( $this->memory_cache, function( $a, $b ) {
            return $a['expires'] - $b['expires'];
        } );

        // Remove oldest 25% of entries
        $count = count( $this->memory_cache );
        $to_remove = max( 1, floor( $count * 0.25 ) );
        
        for ( $i = 0; $i < $to_remove; $i++ ) {
            array_shift( $this->memory_cache );
        }
    }

    // Metric recording methods
    private function record_hit() {
        if ( $this->config['enable_metrics'] ) {
            $this->metrics['hits']++;
        }
    }

    private function record_miss() {
        if ( $this->config['enable_metrics'] ) {
            $this->metrics['misses']++;
        }
    }

    private function record_set() {
        if ( $this->config['enable_metrics'] ) {
            $this->metrics['sets']++;
        }
    }

    private function record_delete() {
        if ( $this->config['enable_metrics'] ) {
            $this->metrics['deletes']++;
        }
    }

    private function record_invalidation( $count ) {
        if ( $this->config['enable_metrics'] ) {
            $this->metrics['invalidations'] += $count;
        }
    }

    // Invalidation hook handlers
    public function invalidate_settings_cache() {
        $this->invalidate( 'settings', 'group' );
    }

    public function invalidate_user_quota_cache( $user_id = null ) {
        if ( $user_id ) {
            $this->delete( "user_quota_{$user_id}", 'user_quotas' );
        } else {
            $this->invalidate( 'user_quotas', 'group' );
        }
    }
}