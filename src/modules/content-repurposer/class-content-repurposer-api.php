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
                'is_guest' => array(
                    'required'          => false,
                    'type'              => 'boolean',
                    'default'           => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
                'nonce' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array( $this, 'validate_nonce' ),
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
        $is_guest = $request->get_param( 'is_guest' ) === true;
        
        // Check rate limiting - for guests, use client-side attempt tracking
        if ( $is_guest && ! is_user_logged_in() ) {
            // For guests, we rely on client-side enforcement (3 attempts)
            // Server-side check is more lenient to avoid conflicts
            $guest_limit_result = $this->check_guest_attempts( $request );
            if ( is_wp_error( $guest_limit_result ) ) {
                return $guest_limit_result;
            }
        } else {
            // For logged-in users, use the shared rate limiting system
            $rate_limit_result = $this->ai_service->check_rate_limit( 'content_repurposing' );
            if ( is_wp_error( $rate_limit_result ) ) {
                return $rate_limit_result;
            }
        }
        
        // Generate repurposed content
        $result = $this->ai_service->repurpose_content( $content, $platforms, $tone );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Apply guest limitations (full Twitter + previews for other platforms)
        if ( $is_guest && ! is_user_logged_in() ) {
            $result = $this->apply_guest_limitations( $result );
        }
        
        // Track usage in shared system
        $this->ai_service->track_usage( count( $platforms ), 'content_repurposing' );
        
        $response_data = array(
            'success' => true,
            'data' => $result,
            'usage' => $this->ai_service->get_usage_stats(),
        );
        
        $response = rest_ensure_response( $response_data );
        
        // Add security headers based on user type
        if ( $is_guest && ! is_user_logged_in() ) {
            // Prevent caching of guest preview data
            $response->header( 'Cache-Control', 'private, no-cache, no-store, must-revalidate' );
            $response->header( 'X-Guest-Preview', '1' );
            $response->header( 'X-Content-Type-Options', 'nosniff' );
        } else {
            // For authenticated users, allow some caching but mark as private
            $response->header( 'Cache-Control', 'private, max-age=300' );
            $response->header( 'X-Content-Type-Options', 'nosniff' );
        }
        
        return $response;
    }
    
    /**
     * Apply guest limitations to repurposed content.
     * 
     * @param array $result The full repurposed content result
     * @return array Modified result with guest limitations
     */
    private function apply_guest_limitations( $result ) {
        if ( ! is_array( $result ) ) {
            return $result;
        }
        
        foreach ( $result as $platform => &$platform_data ) {
            // Skip Twitter - guests get full Twitter content
            if ( $platform === 'twitter' ) {
                continue;
            }
            
            // For other platforms, create preview versions
            if ( isset( $platform_data['success'] ) && $platform_data['success'] && isset( $platform_data['versions'] ) ) {
                foreach ( $platform_data['versions'] as &$version ) {
                    if ( isset( $version['text'] ) ) {
                        $full_text = $version['text'];
                        $preview_length = $this->get_preview_length( $platform );
                        
                        // Create preview text (first X characters + ellipsis)
                        $preview_text = mb_substr( $full_text, 0, $preview_length ) . '...';
                        
                        // Modify version data for guest preview
                        $version['is_preview'] = true;
                        $version['preview_text'] = $preview_text;
                        $version['estimated_length'] = $version['character_count'] ?? mb_strlen( $full_text );
                        
                        // Remove full text for security
                        unset( $version['text'] );
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Get preview length for different platforms.
     * 
     * @param string $platform Platform name
     * @return int Preview character length
     */
    private function get_preview_length( $platform ) {
        $preview_lengths = array(
            'linkedin'  => 100,
            'facebook'  => 80,
            'instagram' => 75,
        );
        
        return $preview_lengths[ $platform ] ?? 50;
    }
    
    /**
     * Get usage statistics for current user.
     */
    public function get_usage_stats( $request ) {
        $stats = $this->ai_service->get_usage_stats();
        
        return rest_ensure_response( array(
            'success' => true,
            'data' => $stats,
        ) );
    }
    
    /**
     * Check if user has permission to use the API.
     */
    public function check_permissions( $request ) {
        // For logged-in users, verify nonce for security
        if ( is_user_logged_in() ) {
            $nonce = $request->get_param( 'nonce' );
            if ( ! empty( $nonce ) && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return new WP_Error( 
                    'rest_forbidden', 
                    __( 'Invalid security token.', 'rwp-creator-suite' ), 
                    array( 'status' => 403 ) 
                );
            }
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
     * Validate nonce parameter.
     */
    public function validate_nonce( $nonce, $request, $param ) {
        // Only validate nonce if user is logged in
        if ( ! is_user_logged_in() ) {
            return true; // Skip nonce validation for guests
        }
        
        // If user is logged in but no nonce provided, it's invalid
        if ( empty( $nonce ) ) {
            return new WP_Error(
                'missing_nonce',
                __( 'Security token is required for authenticated requests.', 'rwp-creator-suite' )
            );
        }
        
        return true; // Actual verification happens in check_permissions
    }
    
    /**
     * Check guest attempts from client-side tracking.
     * This provides a lightweight server-side validation that aligns with client-side limits.
     */
    private function check_guest_attempts( $request ) {
        // For guests, we mainly rely on client-side enforcement
        // This is just a basic server-side safety net with a higher limit
        $ip = $this->get_client_ip();
        $transient_key = 'rwp_guest_repurposer_' . hash( 'sha256', $ip . wp_salt( 'secure_auth' ) );
        $attempts = get_transient( $transient_key );
        
        if ( false === $attempts ) {
            $attempts = 0;
        }
        
        // Use a higher limit (10) to avoid conflicts with client-side 3-attempt system
        // This catches only extreme abuse cases
        if ( $attempts >= 10 ) {
            return new WP_Error(
                'guest_limit_exceeded',
                __( 'Too many requests. Please try again later or create a free account.', 'rwp-creator-suite' ),
                array( 'status' => 429 )
            );
        }
        
        // Increment attempts counter (expires after 1 hour)
        set_transient( $transient_key, $attempts + 1, HOUR_IN_SECONDS );
        
        return true;
    }
    
    /**
     * Get client IP address for guest rate limiting.
     */
    private function get_client_ip() {
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
                $ips = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ips[0] );
                
                // Validate IP
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}