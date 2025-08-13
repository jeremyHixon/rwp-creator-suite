<?php
/**
 * Content Repurposer API
 * 
 * Handles REST API endpoints for content repurposing functionality.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Content_Repurposer_API {
    
    private $ai_service;
    
    /**
     * Initialize API endpoints.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        
        // Initialize AI service
        $this->ai_service = new RWP_Creator_Suite_AI_Service();
    }
    
    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'rwp-creator-suite/v1', '/repurpose-content', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'repurpose_content' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args'                => array(
                'content' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => array( $this, 'validate_content' ),
                ),
                'platforms' => array(
                    'required'          => true,
                    'type'              => 'array',
                    'items'             => array( 'type' => 'string' ),
                    'validate_callback' => array( $this, 'validate_platforms' ),
                ),
                'tone' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'professional',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array( $this, 'validate_tone' ),
                ),
            ),
        ) );
        
        register_rest_route( 'rwp-creator-suite/v1', '/repurpose-usage', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_usage_stats' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );
    }
    
    /**
     * Handle content repurposing request.
     */
    public function repurpose_content( $request ) {
        $content = $request->get_param( 'content' );
        $platforms = $request->get_param( 'platforms' );
        $tone = $request->get_param( 'tone' );
        
        // Check rate limiting
        $rate_limit_result = $this->check_rate_limit();
        if ( is_wp_error( $rate_limit_result ) ) {
            return $rate_limit_result;
        }
        
        // Generate repurposed content
        $result = $this->ai_service->repurpose_content( $content, $platforms, $tone );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Track usage
        $this->track_usage( count( $platforms ) );
        
        return rest_ensure_response( array(
            'success' => true,
            'data' => $result,
            'usage' => $this->get_user_usage_stats(),
        ) );
    }
    
    /**
     * Get usage statistics for current user.
     */
    public function get_usage_stats( $request ) {
        $stats = $this->get_user_usage_stats();
        
        return rest_ensure_response( array(
            'success' => true,
            'data' => $stats,
        ) );
    }
    
    /**
     * Check if user has permission to use the API.
     */
    public function check_permissions( $request ) {
        // Allow both logged-in users and guests (with rate limiting)
        if ( is_user_logged_in() ) {
            return true;
        }
        
        // For guests, check if guest access is enabled
        $allow_guest_access = get_option( 'rwp_creator_suite_allow_guest_repurpose', false );
        
        if ( $allow_guest_access ) {
            return true;
        }
        
        return new WP_Error(
            'rest_forbidden',
            __( 'You must be logged in to use this feature.', 'rwp-creator-suite' ),
            array( 'status' => 401 )
        );
    }
    
    /**
     * Validate content parameter.
     */
    public function validate_content( $content, $request, $param ) {
        if ( empty( trim( $content ) ) ) {
            return new WP_Error(
                'invalid_content',
                __( 'Content cannot be empty.', 'rwp-creator-suite' )
            );
        }
        
        if ( mb_strlen( $content ) > 10000 ) {
            return new WP_Error(
                'content_too_long',
                __( 'Content is too long. Maximum 10,000 characters allowed.', 'rwp-creator-suite' )
            );
        }
        
        return true;
    }
    
    /**
     * Validate platforms parameter.
     */
    public function validate_platforms( $platforms, $request, $param ) {
        $allowed_platforms = array( 'twitter', 'linkedin', 'facebook', 'instagram' );
        
        if ( empty( $platforms ) ) {
            return new WP_Error(
                'invalid_platforms',
                __( 'At least one platform must be selected.', 'rwp-creator-suite' )
            );
        }
        
        foreach ( $platforms as $platform ) {
            if ( ! in_array( $platform, $allowed_platforms, true ) ) {
                return new WP_Error(
                    'invalid_platform',
                    sprintf( __( 'Invalid platform: %s', 'rwp-creator-suite' ), $platform )
                );
            }
        }
        
        if ( count( $platforms ) > 4 ) {
            return new WP_Error(
                'too_many_platforms',
                __( 'Maximum 4 platforms allowed per request.', 'rwp-creator-suite' )
            );
        }
        
        return true;
    }
    
    /**
     * Validate tone parameter.
     */
    public function validate_tone( $tone, $request, $param ) {
        $allowed_tones = array( 'professional', 'casual', 'engaging', 'informative' );
        
        if ( ! in_array( $tone, $allowed_tones, true ) ) {
            return new WP_Error(
                'invalid_tone',
                sprintf( __( 'Invalid tone: %s', 'rwp-creator-suite' ), $tone )
            );
        }
        
        return true;
    }
    
    /**
     * Check rate limiting for current user.
     */
    private function check_rate_limit() {
        $user_id = get_current_user_id();
        $is_guest = ! $user_id;
        
        if ( $is_guest ) {
            // Use IP-based rate limiting for guests
            $identifier = $this->get_client_ip();
            $limit = get_option( 'rwp_creator_suite_rate_limit_guest', 5 );
        } else {
            $identifier = $user_id;
            // Check if user is premium
            $is_premium = apply_filters( 'rwp_creator_suite_is_premium_user', false, $user_id );
            $limit = $is_premium 
                ? get_option( 'rwp_creator_suite_rate_limit_premium', 50 )
                : get_option( 'rwp_creator_suite_rate_limit_free', 10 );
        }
        
        $transient_key = "rwp_repurpose_rate_limit_{$identifier}";
        $current_usage = get_transient( $transient_key );
        
        if ( false === $current_usage ) {
            $current_usage = 0;
        }
        
        if ( $current_usage >= $limit ) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __( 'Rate limit exceeded. You can make %d requests per hour.', 'rwp-creator-suite' ),
                    $limit
                ),
                array( 'status' => 429 )
            );
        }
        
        return true;
    }
    
    /**
     * Track usage for rate limiting and statistics.
     */
    private function track_usage( $platform_count = 1 ) {
        $user_id = get_current_user_id();
        $is_guest = ! $user_id;
        
        if ( $is_guest ) {
            $identifier = $this->get_client_ip();
        } else {
            $identifier = $user_id;
        }
        
        // Update rate limiting counter
        $transient_key = "rwp_repurpose_rate_limit_{$identifier}";
        $current_usage = get_transient( $transient_key );
        
        if ( false === $current_usage ) {
            $current_usage = 0;
        }
        
        $new_usage = $current_usage + $platform_count;
        set_transient( $transient_key, $new_usage, HOUR_IN_SECONDS );
        
        // Track usage statistics for logged-in users
        if ( ! $is_guest ) {
            $total_usage = get_user_meta( $user_id, 'rwp_repurpose_total_usage', true );
            if ( ! $total_usage ) {
                $total_usage = 0;
            }
            update_user_meta( $user_id, 'rwp_repurpose_total_usage', $total_usage + $platform_count );
            
            // Track monthly usage
            $current_month = date( 'Y-m' );
            $monthly_usage = get_user_meta( $user_id, "rwp_repurpose_usage_{$current_month}", true );
            if ( ! $monthly_usage ) {
                $monthly_usage = 0;
            }
            update_user_meta( $user_id, "rwp_repurpose_usage_{$current_month}", $monthly_usage + $platform_count );
        }
    }
    
    /**
     * Get usage statistics for current user.
     */
    private function get_user_usage_stats() {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            // For guests, only show rate limit info
            $identifier = $this->get_client_ip();
            $transient_key = "rwp_repurpose_rate_limit_{$identifier}";
            $current_usage = get_transient( $transient_key );
            $limit = get_option( 'rwp_creator_suite_rate_limit_guest', 5 );
            
            return array(
                'current_hour_usage' => $current_usage ? $current_usage : 0,
                'hourly_limit' => $limit,
                'remaining' => max( 0, $limit - ( $current_usage ? $current_usage : 0 ) ),
                'is_guest' => true,
            );
        }
        
        // For logged-in users
        $is_premium = apply_filters( 'rwp_creator_suite_is_premium_user', false, $user_id );
        $limit = $is_premium 
            ? get_option( 'rwp_creator_suite_rate_limit_premium', 50 )
            : get_option( 'rwp_creator_suite_rate_limit_free', 10 );
            
        $transient_key = "rwp_repurpose_rate_limit_{$user_id}";
        $current_usage = get_transient( $transient_key );
        
        $total_usage = get_user_meta( $user_id, 'rwp_repurpose_total_usage', true );
        $current_month = date( 'Y-m' );
        $monthly_usage = get_user_meta( $user_id, "rwp_repurpose_usage_{$current_month}", true );
        
        return array(
            'current_hour_usage' => $current_usage ? $current_usage : 0,
            'hourly_limit' => $limit,
            'remaining' => max( 0, $limit - ( $current_usage ? $current_usage : 0 ) ),
            'total_usage' => $total_usage ? $total_usage : 0,
            'monthly_usage' => $monthly_usage ? $monthly_usage : 0,
            'is_premium' => $is_premium,
            'is_guest' => false,
        );
    }
    
    /**
     * Get client IP address for guest rate limiting.
     */
    private function get_client_ip() {
        // Check for various headers that might contain the real IP
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ( $ip_headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip_list = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ip_list[0] );
                
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}