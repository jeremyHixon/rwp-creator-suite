<?php
/**
 * Caption Writer Cache Manager
 * 
 * Handles caching for AI-generated captions and templates to improve performance.
 * 
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Caption_Cache {

    private $cache_group = 'rwp_caption_writer';
    private $cache_expiry = HOUR_IN_SECONDS * 2; // 2 hours default
    
    /**
     * Initialize cache manager.
     */
    public function init() {
        // Set up cache group
        wp_cache_add_global_groups( array( $this->cache_group ) );
    }
    
    /**
     * Get cached caption generation result.
     * 
     * @param string $description
     * @param string $tone
     * @param string $platform
     * @return array|false False if not cached, array of captions if cached
     */
    public function get_cached_captions( $description, $tone, $platform ) {
        $cache_key = $this->generate_caption_cache_key( $description, $tone, $platform );
        return wp_cache_get( $cache_key, $this->cache_group );
    }
    
    /**
     * Cache caption generation result.
     * 
     * @param string $description
     * @param string $tone
     * @param string $platform
     * @param array $captions
     * @param int $expiry Cache expiry in seconds
     */
    public function cache_captions( $description, $tone, $platform, $captions, $expiry = null ) {
        if ( $expiry === null ) {
            $expiry = $this->cache_expiry;
        }
        
        $cache_key = $this->generate_caption_cache_key( $description, $tone, $platform );
        
        $cache_data = array(
            'captions' => $captions,
            'generated_at' => current_time( 'timestamp' ),
            'description' => $description,
            'tone' => $tone,
            'platform' => $platform,
        );
        
        wp_cache_set( $cache_key, $cache_data, $this->cache_group, $expiry );
        
        // Also store in transients as fallback for non-persistent caching setups
        set_transient( $cache_key, $cache_data, $expiry );
    }
    
    /**
     * Generate cache key for caption generation.
     * 
     * @param string $description
     * @param string $tone
     * @param string $platform
     * @return string
     */
    private function generate_caption_cache_key( $description, $tone, $platform ) {
        // Create a hash to ensure consistent key length and handle special characters
        $key_data = array(
            'desc' => trim( strtolower( $description ) ),
            'tone' => $tone,
            'platform' => $platform,
            'version' => '1.0' // Increment this to invalidate cache when logic changes
        );
        
        return 'captions_' . md5( serialize( $key_data ) );
    }
    
    /**
     * Cache user templates.
     * 
     * @param int $user_id
     * @param array $templates
     */
    public function cache_user_templates( $user_id, $templates ) {
        $cache_key = "user_templates_{$user_id}";
        
        wp_cache_set( $cache_key, $templates, $this->cache_group, HOUR_IN_SECONDS );
        
        // Update user meta cache timestamp
        update_user_meta( $user_id, 'rwp_templates_cache_time', current_time( 'timestamp' ) );
    }
    
    /**
     * Get cached user templates.
     * 
     * @param int $user_id
     * @return array|false
     */
    public function get_cached_user_templates( $user_id ) {
        $cache_key = "user_templates_{$user_id}";
        $cached = wp_cache_get( $cache_key, $this->cache_group );
        
        if ( $cached === false ) {
            // Check if templates were updated since last cache
            $cache_time = get_user_meta( $user_id, 'rwp_templates_cache_time', true );
            $update_time = get_user_meta( $user_id, 'rwp_templates_updated', true );
            
            if ( $cache_time && $update_time && $cache_time > $update_time ) {
                // Cache is still valid, but not in memory - try transient
                $cached = get_transient( $cache_key );
            }
        }
        
        return $cached;
    }
    
    /**
     * Invalidate user templates cache.
     * 
     * @param int $user_id
     */
    public function invalidate_user_templates( $user_id ) {
        $cache_key = "user_templates_{$user_id}";
        
        wp_cache_delete( $cache_key, $this->cache_group );
        delete_transient( $cache_key );
        
        // Update timestamp
        update_user_meta( $user_id, 'rwp_templates_updated', current_time( 'timestamp' ) );
    }
    
    /**
     * Cache built-in templates.
     * 
     * @param array $templates
     */
    public function cache_builtin_templates( $templates ) {
        $cache_key = 'builtin_templates';
        wp_cache_set( $cache_key, $templates, $this->cache_group, DAY_IN_SECONDS );
    }
    
    /**
     * Get cached built-in templates.
     * 
     * @return array|false
     */
    public function get_cached_builtin_templates() {
        return wp_cache_get( 'builtin_templates', $this->cache_group );
    }
    
    /**
     * Cache user favorites.
     * 
     * @param int $user_id
     * @param array $favorites
     */
    public function cache_user_favorites( $user_id, $favorites ) {
        $cache_key = "user_favorites_{$user_id}";
        wp_cache_set( $cache_key, $favorites, $this->cache_group, HOUR_IN_SECONDS );
    }
    
    /**
     * Get cached user favorites.
     * 
     * @param int $user_id
     * @return array|false
     */
    public function get_cached_user_favorites( $user_id ) {
        $cache_key = "user_favorites_{$user_id}";
        return wp_cache_get( $cache_key, $this->cache_group );
    }
    
    /**
     * Invalidate user favorites cache.
     * 
     * @param int $user_id
     */
    public function invalidate_user_favorites( $user_id ) {
        $cache_key = "user_favorites_{$user_id}";
        wp_cache_delete( $cache_key, $this->cache_group );
    }
    
    /**
     * Cache API quota information.
     * 
     * @param string $identifier User ID or IP
     * @param array $quota_data
     */
    public function cache_quota_info( $identifier, $quota_data ) {
        $cache_key = "quota_info_" . md5( $identifier );
        wp_cache_set( $cache_key, $quota_data, $this->cache_group, 300 ); // 5 minutes
    }
    
    /**
     * Get cached API quota information.
     * 
     * @param string $identifier User ID or IP
     * @return array|false
     */
    public function get_cached_quota_info( $identifier ) {
        $cache_key = "quota_info_" . md5( $identifier );
        return wp_cache_get( $cache_key, $this->cache_group );
    }
    
    /**
     * Clear all caption writer caches.
     */
    public function clear_all_caches() {
        // Clear object cache group (if supported)
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( $this->cache_group );
        }
        
        // Clear known transients
        global $wpdb;
        
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_captions_%',
                '_transient_user_templates_%'
            )
        );
        
        foreach ( $transients as $transient ) {
            $key = str_replace( '_transient_', '', $transient );
            delete_transient( $key );
        }
    }
    
    /**
     * Get cache statistics.
     * 
     * @return array
     */
    public function get_cache_stats() {
        global $wpdb;
        
        // Count cached items (transients as fallback)
        $caption_transients = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                '_transient_captions_%'
            )
        );
        
        $template_transients = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                '_transient_user_templates_%'
            )
        );
        
        return array(
            'cached_captions' => intval( $caption_transients ),
            'cached_templates' => intval( $template_transients ),
            'cache_group' => $this->cache_group,
            'default_expiry' => $this->cache_expiry,
        );
    }
    
    /**
     * Clean expired cache entries.
     */
    public function cleanup_expired_cache() {
        global $wpdb;
        
        // Clean expired transients
        $expired_transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT t1.option_name 
                FROM {$wpdb->options} t1 
                JOIN {$wpdb->options} t2 ON t2.option_name = CONCAT('_transient_timeout_', SUBSTRING(t1.option_name, 12))
                WHERE t1.option_name LIKE %s 
                AND t2.option_value < %d",
                '_transient_captions_%',
                current_time( 'timestamp' )
            )
        );
        
        foreach ( $expired_transients as $transient ) {
            $key = str_replace( '_transient_', '', $transient );
            delete_transient( $key );
        }
        
        return count( $expired_transients );
    }
    
    /**
     * Schedule cache cleanup.
     */
    public function schedule_cleanup() {
        if ( ! wp_next_scheduled( 'rwp_caption_cache_cleanup' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'rwp_caption_cache_cleanup' );
        }
    }
}