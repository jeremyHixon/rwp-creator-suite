<?php
/**
 * Hashtag Analysis API
 * 
 * Handles REST API endpoints for hashtag analysis functionality.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Hashtag_Analysis_API {

    /**
     * TikTok service instance.
     */
    private $tiktok_service;

    /**
     * Aggregator service instance.
     */
    private $aggregator_service;

    /**
     * Initialize the API.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        
        // Initialize services
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/hashtag-analysis/class-tiktok-service.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/hashtag-analysis/class-aggregator-service.php';
        
        $this->tiktok_service = new RWP_Creator_Suite_TikTok_Service();
        $this->aggregator_service = new RWP_Creator_Suite_Aggregator_Service();
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'hashtag-analysis/v1', '/search', array(
            'methods' => 'POST',
            'callback' => array( $this, 'search_hashtag' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'hashtag' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array( $this, 'validate_hashtag' ),
                ),
                'platforms' => array(
                    'required' => false,
                    'type' => 'array',
                    'default' => array( 'tiktok', 'instagram', 'facebook' ),
                    'items' => array(
                        'type' => 'string',
                        'enum' => array( 'tiktok', 'instagram', 'facebook' ),
                    ),
                ),
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ),
            ),
        ) );

        register_rest_route( 'hashtag-analysis/v1', '/analytics', array(
            'methods' => 'POST',
            'callback' => array( $this, 'get_analytics' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'hashtag' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array( $this, 'validate_hashtag' ),
                ),
                'platforms' => array(
                    'required' => false,
                    'type' => 'array',
                    'default' => array( 'tiktok', 'instagram', 'facebook' ),
                ),
                'timeframe' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => '7d',
                    'enum' => array( '1d', '7d', '30d' ),
                ),
            ),
        ) );

        register_rest_route( 'hashtag-analysis/v1', '/dashboard', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_dashboard_data' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Validate hashtag parameter.
     *
     * @param string $value The hashtag value.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    public function validate_hashtag( $value ) {
        // Remove # if present and validate
        $hashtag = ltrim( $value, '#' );
        
        if ( empty( $hashtag ) ) {
            return new WP_Error( 'invalid_hashtag', 'Hashtag cannot be empty.', array( 'status' => 400 ) );
        }

        if ( strlen( $hashtag ) > 100 ) {
            return new WP_Error( 'invalid_hashtag', 'Hashtag is too long.', array( 'status' => 400 ) );
        }

        // Basic validation for hashtag format
        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $hashtag ) ) {
            return new WP_Error( 'invalid_hashtag', 'Hashtag contains invalid characters.', array( 'status' => 400 ) );
        }

        return true;
    }

    /**
     * Check user rate limits for API calls.
     *
     * @param string $action The action being performed.
     * @return true|WP_Error True if within limits, error if exceeded.
     */
    private function check_user_rate_limits( $action ) {
        $user_id = get_current_user_id();
        $is_logged_in = $user_id > 0;
        
        // Get configured limits
        $guest_limit = get_option( 'rwp_hashtag_analysis_guest_limit', 5 );
        $user_limit = get_option( 'rwp_hashtag_analysis_user_limit', 20 );
        $daily_limit = $is_logged_in ? $user_limit : $guest_limit;
        
        // Check daily usage
        $cache_key = 'rwp_hashtag_rate_limit_' . ( $is_logged_in ? $user_id : $_SERVER['REMOTE_ADDR'] ) . '_' . date( 'Y-m-d' );
        $current_usage = get_transient( $cache_key ) ?: 0;
        
        if ( $current_usage >= $daily_limit ) {
            $message = $is_logged_in 
                ? sprintf( 'Daily limit of %d searches reached. Try again tomorrow.', $daily_limit )
                : sprintf( 'Daily limit of %d searches reached. Login for higher limits.', $daily_limit );
                
            return new WP_Error( 'rate_limit_exceeded', $message, array( 'status' => 429 ) );
        }
        
        // Increment usage counter
        set_transient( $cache_key, $current_usage + 1, DAY_IN_SECONDS );
        
        return true;
    }

    /**
     * Get user-specific search limit.
     *
     * @return int The search limit for current user.
     */
    private function get_user_search_limit() {
        $is_logged_in = get_current_user_id() > 0;
        return $is_logged_in 
            ? get_option( 'rwp_hashtag_analysis_user_limit', 20 )
            : get_option( 'rwp_hashtag_analysis_guest_limit', 5 );
    }

    /**
     * Get cached API response.
     *
     * @param string $cache_key The cache key.
     * @return array|null Cached data or null if not found/expired.
     */
    private function get_cached_response( $cache_key ) {
        $cache_duration = get_option( 'rwp_hashtag_analysis_cache_duration', 3600 );
        return get_transient( $cache_key );
    }

    /**
     * Set cached API response.
     *
     * @param string $cache_key The cache key.
     * @param array  $data      The data to cache.
     */
    private function set_cached_response( $cache_key, $data ) {
        $cache_duration = get_option( 'rwp_hashtag_analysis_cache_duration', 3600 );
        set_transient( $cache_key, $data, $cache_duration );
    }

    /**
     * Search for hashtag data across platforms.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response.
     */
    public function search_hashtag( $request ) {
        // Check rate limits first
        $rate_limit_check = $this->check_user_rate_limits( 'search' );
        if ( is_wp_error( $rate_limit_check ) ) {
            return $rate_limit_check;
        }

        $hashtag = $request->get_param( 'hashtag' );
        $platforms = $request->get_param( 'platforms' );
        $limit = $request->get_param( 'limit' );

        // Clean hashtag
        $hashtag = ltrim( $hashtag, '#' );

        // Apply user limits
        $user_limit = $this->get_user_search_limit();
        $limit = min( $limit, $user_limit );

        // Check cache first
        $cache_key = 'rwp_hashtag_search_' . md5( $hashtag . implode( '_', $platforms ) . $limit );
        $cached_response = $this->get_cached_response( $cache_key );
        
        if ( $cached_response ) {
            $cached_response['cached'] = true;
            $cached_response['cache_timestamp'] = $cached_response['timestamp'] ?? time();
            $cached_response['timestamp'] = current_time( 'timestamp' );
            return new WP_REST_Response( $cached_response, 200 );
        }

        $results = array();
        $errors = array();
        $warnings = array();

        // Search across requested platforms
        foreach ( $platforms as $platform ) {
            try {
                $platform_results = null;
                
                switch ( $platform ) {
                    case 'tiktok':
                        $platform_results = $this->tiktok_service->search_hashtag( $hashtag, $limit );
                        break;
                    case 'instagram':
                    case 'facebook':
                        $platform_results = $this->aggregator_service->search_hashtag( $hashtag, $platform, $limit );
                        break;
                    default:
                        $warnings[] = "Platform '{$platform}' is not supported";
                        continue 2;
                }

                if ( ! is_wp_error( $platform_results ) ) {
                    $results[ $platform ] = $platform_results;
                } else {
                    $error_code = $platform_results->get_error_code();
                    $error_message = $platform_results->get_error_message();
                    $error_data = $platform_results->get_error_data();
                    
                    $errors[ $platform ] = array(
                        'code' => $error_code,
                        'message' => $error_message,
                        'recoverable' => in_array( $error_code, array( 'rate_limit_exceeded', 'temporary_error' ) ),
                    );
                    
                    // Log detailed error for debugging
                    RWP_Creator_Suite_Error_Logger::log_error( 
                        'Hashtag Search API Error', 
                        $error_message, 
                        array( 
                            'platform' => $platform, 
                            'hashtag' => $hashtag,
                            'error_code' => $error_code,
                            'error_data' => $error_data,
                        )
                    );
                }
            } catch ( Exception $e ) {
                $errors[ $platform ] = array(
                    'code' => 'exception',
                    'message' => 'Platform temporarily unavailable',
                    'recoverable' => true,
                );
                
                RWP_Creator_Suite_Error_Logger::log_error( 
                    'Hashtag Search Exception', 
                    $e->getMessage(), 
                    array( 
                        'platform' => $platform, 
                        'hashtag' => $hashtag,
                        'trace' => $e->getTraceAsString(),
                    )
                );
            }
        }

        $response_data = array(
            'success' => ! empty( $results ),
            'data' => $results,
            'errors' => $errors,
            'warnings' => $warnings,
            'hashtag' => $hashtag,
            'platforms_requested' => $platforms,
            'platforms_successful' => array_keys( $results ),
            'limit' => $limit,
            'user_limit' => $user_limit,
            'is_logged_in' => get_current_user_id() > 0,
            'timestamp' => current_time( 'timestamp' ),
        );

        // Cache successful results
        if ( ! empty( $results ) ) {
            $this->set_cached_response( $cache_key, $response_data );
        }

        return new WP_REST_Response( $response_data, 200 );
    }

    /**
     * Get analytics data for a hashtag.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response.
     */
    public function get_analytics( $request ) {
        $hashtag = $request->get_param( 'hashtag' );
        $platforms = $request->get_param( 'platforms' );
        $timeframe = $request->get_param( 'timeframe' );

        // Clean hashtag
        $hashtag = ltrim( $hashtag, '#' );

        $analytics = array();
        $errors = array();

        // Get analytics from each platform
        foreach ( $platforms as $platform ) {
            try {
                switch ( $platform ) {
                    case 'tiktok':
                        $platform_analytics = $this->tiktok_service->get_hashtag_analytics( $hashtag, $timeframe );
                        break;
                    case 'instagram':
                    case 'facebook':
                        $platform_analytics = $this->aggregator_service->get_hashtag_analytics( $hashtag, $platform, $timeframe );
                        break;
                    default:
                        continue 2;
                }

                if ( ! is_wp_error( $platform_analytics ) ) {
                    $analytics[ $platform ] = $platform_analytics;
                } else {
                    $errors[ $platform ] = $platform_analytics->get_error_message();
                }
            } catch ( Exception $e ) {
                $errors[ $platform ] = $e->getMessage();
                RWP_Creator_Suite_Error_Logger::log_error( 
                    'Hashtag Analytics Error', 
                    $e->getMessage(), 
                    array( 'platform' => $platform, 'hashtag' => $hashtag )
                );
            }
        }

        return new WP_REST_Response( array(
            'success' => ! empty( $analytics ),
            'data' => $analytics,
            'errors' => $errors,
            'hashtag' => $hashtag,
            'timeframe' => $timeframe,
            'timestamp' => current_time( 'timestamp' ),
        ), 200 );
    }

    /**
     * Get dashboard data with trending hashtags and overview.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response.
     */
    public function get_dashboard_data( $request ) {
        $dashboard_data = array(
            'trending_hashtags' => array(),
            'platform_status' => array(),
            'recent_searches' => array(),
        );

        // Check platform availability and get trending data
        try {
            // TikTok trending hashtags
            $tiktok_trending = $this->tiktok_service->get_trending_hashtags();
            if ( ! is_wp_error( $tiktok_trending ) ) {
                $dashboard_data['trending_hashtags']['tiktok'] = $tiktok_trending;
                $dashboard_data['platform_status']['tiktok'] = 'active';
            } else {
                $dashboard_data['platform_status']['tiktok'] = 'error';
            }
        } catch ( Exception $e ) {
            $dashboard_data['platform_status']['tiktok'] = 'error';
        }

        try {
            // Instagram/Facebook trending hashtags via aggregator
            $aggregator_trending = $this->aggregator_service->get_trending_hashtags();
            if ( ! is_wp_error( $aggregator_trending ) ) {
                $dashboard_data['trending_hashtags']['instagram'] = $aggregator_trending['instagram'] ?? array();
                $dashboard_data['trending_hashtags']['facebook'] = $aggregator_trending['facebook'] ?? array();
                $dashboard_data['platform_status']['instagram'] = 'active';
                $dashboard_data['platform_status']['facebook'] = 'active';
            } else {
                $dashboard_data['platform_status']['instagram'] = 'error';
                $dashboard_data['platform_status']['facebook'] = 'error';
            }
        } catch ( Exception $e ) {
            $dashboard_data['platform_status']['instagram'] = 'error';
            $dashboard_data['platform_status']['facebook'] = 'error';
        }

        // Get recent searches from localStorage (frontend will populate this)
        $dashboard_data['recent_searches'] = get_transient( 'rwp_hashtag_recent_searches' ) ?: array();

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $dashboard_data,
            'timestamp' => current_time( 'timestamp' ),
        ), 200 );
    }
}