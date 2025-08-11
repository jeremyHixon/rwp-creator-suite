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
     * Search for hashtag data across platforms.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response.
     */
    public function search_hashtag( $request ) {
        $hashtag = $request->get_param( 'hashtag' );
        $platforms = $request->get_param( 'platforms' );
        $limit = $request->get_param( 'limit' );

        // Clean hashtag
        $hashtag = ltrim( $hashtag, '#' );

        $results = array();
        $errors = array();

        // Search across requested platforms
        foreach ( $platforms as $platform ) {
            try {
                switch ( $platform ) {
                    case 'tiktok':
                        $platform_results = $this->tiktok_service->search_hashtag( $hashtag, $limit );
                        break;
                    case 'instagram':
                    case 'facebook':
                        $platform_results = $this->aggregator_service->search_hashtag( $hashtag, $platform, $limit );
                        break;
                    default:
                        continue 2; // Skip unknown platform
                }

                if ( ! is_wp_error( $platform_results ) ) {
                    $results[ $platform ] = $platform_results;
                } else {
                    $errors[ $platform ] = $platform_results->get_error_message();
                }
            } catch ( Exception $e ) {
                $errors[ $platform ] = $e->getMessage();
                RWP_Creator_Suite_Error_Logger::log_error( 
                    'Hashtag Search Error', 
                    $e->getMessage(), 
                    array( 'platform' => $platform, 'hashtag' => $hashtag )
                );
            }
        }

        // Return results even if some platforms failed
        return new WP_REST_Response( array(
            'success' => ! empty( $results ),
            'data' => $results,
            'errors' => $errors,
            'hashtag' => $hashtag,
            'timestamp' => current_time( 'timestamp' ),
        ), 200 );
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