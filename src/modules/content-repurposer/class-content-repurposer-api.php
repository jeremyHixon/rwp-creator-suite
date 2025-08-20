<?php
/**
 * Content Repurposer API
 * 
 * Handles REST API endpoints for content repurposing functionality.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Content_Repurposer_API {
    
    private $ai_service;
    private $advanced_cache_manager;
    
    /**
     * Initialize API endpoints.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        
        // Initialize AI service
        $this->ai_service = new RWP_Creator_Suite_AI_Service();
        
        // Phase 1 Optimization: Enhanced cache manager integration
        $this->advanced_cache_manager = RWP_Creator_Suite_Cache_Manager::get_instance();
        
        // Phase 1 Optimization: Add caching headers for API responses
        add_action( 'rest_pre_serve_request', array( $this, 'add_api_cache_headers' ), 10, 4 );
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
        
        register_rest_route( 'rwp-creator-suite/v1', '/recover-guest-content', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'recover_guest_content' ),
            'permission_callback' => array( $this, 'check_logged_in_permissions' ),
            'args'                => array(
                'content_key' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'nonce' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array( $this, 'validate_nonce' ),
                ),
            ),
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
        
        // Handle case where platforms might be a JSON string
        if ( is_string( $platforms ) ) {
            $decoded = json_decode( $platforms, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $platforms = $decoded;
            }
        }
        
        // Phase 1 Optimization: Enhanced caching for content repurposing
        $cache_key = 'repurpose_' . md5( serialize( array(
            'content' => trim( strtolower( $content ) ),
            'platforms' => $platforms,
            'tone' => $tone,
            'version' => '1.1' // Increment to invalidate old cache entries
        ) ) );
        
        // For guest users, use shorter cache duration and include IP in key for security
        if ( $is_guest ) {
            $ip = RWP_Creator_Suite_Network_Utils::get_client_ip();
            $cache_key = 'guest_' . $cache_key . '_' . md5( $ip );
            $cache_ttl = 15 * MINUTE_IN_SECONDS; // 15 minutes for guest users
        } else {
            $cache_ttl = 2 * HOUR_IN_SECONDS; // 2 hours for authenticated users
        }
        
        $cached_result = $this->advanced_cache_manager->remember(
            $cache_key,
            function() use ( $content, $platforms, $tone, $is_guest, $request ) {
                // Check rate limiting based on request type
                if ( $is_guest ) {
                    // For guest requests, use dedicated guest rate limiting
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
                
                // Track usage in shared system (single API call regardless of platform count)
                $this->ai_service->track_usage( 1, 'content_repurposing' );
                
                return array(
                    'result' => $result,
                    'generated_at' => current_time( 'mysql' ),
                    'cached' => false,
                );
            },
            'ai_responses',
            $cache_ttl
        );
        
        // Handle errors from cached callback
        if ( is_wp_error( $cached_result ) ) {
            return $cached_result;
        }
        
        // If we don't have cached results, something went wrong
        if ( ! $cached_result || ! isset( $cached_result['result'] ) ) {
            return new WP_Error(
                'cache_failure',
                __( 'Failed to generate or retrieve repurposed content. Please try again.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        $result = $cached_result['result'];
        
        // Store full content for guest users before applying limitations
        if ( $is_guest ) {
            $this->store_guest_full_content( $result, $request );
            $result = $this->apply_guest_limitations( $result );
        }
        
        $response_data = array(
            'success' => true,
            'data' => $result,
            'usage' => $this->ai_service->get_usage_stats(),
            'meta' => array(
                'generated_at' => $cached_result['generated_at'],
                'cached' => $cached_result['cached'],
                'cache_key_hash' => substr( $cache_key, -8 ), // For debugging
            ),
        );
        
        $response = rest_ensure_response( $response_data );
        
        // Add security headers based on request type
        if ( $is_guest ) {
            // Prevent caching of guest preview data
            $response->header( 'Cache-Control', 'private, no-cache, no-store, must-revalidate' );
            $response->header( 'X-Guest-Preview', '1' );
            $response->header( 'X-Content-Type-Options', 'nosniff' );
        } else {
            // For authenticated users, allow some caching but mark as private
            if ( isset( $cached_result['cached'] ) && $cached_result['cached'] ) {
                $response->header( 'Cache-Control', 'private, max-age=600' ); // 10 minutes for cached responses
                $response->header( 'X-Cache-Status', 'HIT' );
            } else {
                $response->header( 'Cache-Control', 'private, max-age=300' ); // 5 minutes for fresh responses
                $response->header( 'X-Cache-Status', 'MISS' );
            }
            $response->header( 'X-Content-Type-Options', 'nosniff' );
        }
        
        return $response;
    }
    
    /**
     * Store full AI response for guest users before applying limitations.
     * This allows recovery of full content when the user registers or logs in.
     * 
     * @param array $result The full repurposed content result
     * @param WP_REST_Request $request The original request
     */
    private function store_guest_full_content( $result, $request ) {
        if ( ! is_array( $result ) ) {
            return;
        }
        
        $ip = RWP_Creator_Suite_Network_Utils::get_client_ip();
        $content_hash = hash( 'sha256', $request->get_param( 'content' ) );
        $storage_key = 'rwp_guest_full_content_' . hash( 'sha256', $ip . $content_hash . wp_salt( 'secure_auth' ) );
        
        // Store the full content with metadata
        $storage_data = array(
            'full_content' => $result,
            'original_content' => $request->get_param( 'content' ),
            'platforms' => $request->get_param( 'platforms' ),
            'tone' => $request->get_param( 'tone' ),
            'timestamp' => time(),
            'ip_hash' => hash( 'sha256', $ip . wp_salt( 'secure_auth' ) )
        );
        
        // Store for 30 minutes - long enough for user registration/login but not too long for privacy
        set_transient( $storage_key, $storage_data, 30 * MINUTE_IN_SECONDS );
        
        // Also store the key in user session/cookie for easy retrieval
        $this->set_guest_content_key( $storage_key );
    }
    
    /**
     * Set guest content key for later retrieval.
     * 
     * @param string $storage_key The storage key
     */
    private function set_guest_content_key( $storage_key ) {
        // Use a secure cookie that expires in 30 minutes
        $cookie_name = 'rwp_guest_content_key';
        $cookie_value = base64_encode( $storage_key );
        $expire_time = time() + ( 30 * MINUTE_IN_SECONDS );
        
        setcookie( 
            $cookie_name, 
            $cookie_value, 
            $expire_time, 
            COOKIEPATH, 
            COOKIE_DOMAIN, 
            is_ssl(), 
            true // httponly
        );
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
     * Recover full content for newly logged-in users who previously used guest mode.
     */
    public function recover_guest_content( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'not_logged_in',
                __( 'You must be logged in to recover guest content.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }
        
        // Try to get content key from request parameter or cookie
        $content_key = $request->get_param( 'content_key' );
        
        if ( empty( $content_key ) ) {
            $content_key = $this->get_guest_content_key_from_cookie();
        }
        
        if ( empty( $content_key ) ) {
            return new WP_Error(
                'no_content_key',
                __( 'No guest content found to recover.', 'rwp-creator-suite' ),
                array( 'status' => 404 )
            );
        }
        
        // Decode the content key if it's base64 encoded
        if ( base64_encode( base64_decode( $content_key, true ) ) === $content_key ) {
            $content_key = base64_decode( $content_key );
        }
        
        // Retrieve stored content
        $stored_data = get_transient( $content_key );
        
        if ( false === $stored_data || ! is_array( $stored_data ) ) {
            return new WP_Error(
                'content_expired',
                __( 'Guest content has expired or is not available.', 'rwp-creator-suite' ),
                array( 'status' => 404 )
            );
        }
        
        // Validate the stored data structure
        if ( ! isset( $stored_data['full_content'] ) || ! isset( $stored_data['timestamp'] ) ) {
            return new WP_Error(
                'invalid_content',
                __( 'Stored content is invalid.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        // Additional security check - ensure content isn't too old (30 minutes max)
        if ( ( time() - $stored_data['timestamp'] ) > ( 30 * MINUTE_IN_SECONDS ) ) {
            delete_transient( $content_key );
            return new WP_Error(
                'content_expired',
                __( 'Guest content has expired.', 'rwp-creator-suite' ),
                array( 'status' => 410 )
            );
        }
        
        // Clean up the stored content after successful retrieval
        delete_transient( $content_key );
        $this->clear_guest_content_cookie();
        
        return rest_ensure_response( array(
            'success' => true,
            'data' => array(
                'content' => $stored_data['original_content'],
                'platforms' => $stored_data['platforms'],
                'tone' => $stored_data['tone'],
                'repurposed_content' => $stored_data['full_content'],
                'recovered_at' => time()
            ),
            'message' => __( 'Guest content successfully recovered!', 'rwp-creator-suite' )
        ) );
    }
    
    /**
     * Get guest content key from cookie.
     */
    private function get_guest_content_key_from_cookie() {
        $cookie_name = 'rwp_guest_content_key';
        
        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            return sanitize_text_field( $_COOKIE[ $cookie_name ] );
        }
        
        return null;
    }
    
    /**
     * Clear guest content cookie.
     */
    private function clear_guest_content_cookie() {
        $cookie_name = 'rwp_guest_content_key';
        
        setcookie( 
            $cookie_name, 
            '', 
            time() - 3600, 
            COOKIEPATH, 
            COOKIE_DOMAIN, 
            is_ssl(), 
            true 
        );
    }
    
    /**
     * Check permissions for logged-in users only.
     */
    public function check_logged_in_permissions( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to use this endpoint.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }
        
        // Verify nonce for security - MANDATORY for CSRF protection
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce ) {
            return new WP_Error( 
                'missing_nonce', 
                __( 'Security token is required.', 'rwp-creator-suite' ), 
                array( 'status' => 403 ) 
            );
        }
        
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 
                'invalid_nonce', 
                __( 'Security token is invalid.', 'rwp-creator-suite' ), 
                array( 'status' => 403 ) 
            );
        }
        
        return true;
    }
    
    /**
     * Check if user has permission to use the API.
     */
    public function check_permissions( $request ) {
        // Verify nonce for security - MANDATORY for all users for CSRF protection
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce ) {
            return new WP_Error( 
                'missing_nonce', 
                __( 'Security token is required.', 'rwp-creator-suite' ), 
                array( 'status' => 403 ) 
            );
        }
        
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 
                'invalid_nonce', 
                __( 'Security token is invalid.', 'rwp-creator-suite' ), 
                array( 'status' => 403 ) 
            );
        }
        
        return true;
        
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
        // Get allowed platforms from configuration (same as Caption Writer)
        $platforms_config = RWP_Creator_Suite_Caption_Admin_Settings::get_platforms_config();
        $allowed_platforms = array();
        foreach ( $platforms_config as $platform ) {
            if ( isset( $platform['key'] ) ) {
                $allowed_platforms[] = $platform['key'];
            }
        }
        
        // Fallback to default platforms if config is empty
        if ( empty( $allowed_platforms ) ) {
            $allowed_platforms = array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' );
        }
        
        // Handle case where platforms might be a JSON string
        if ( is_string( $platforms ) ) {
            $decoded = json_decode( $platforms, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $platforms = $decoded;
            }
        }
        
        // Ensure platforms is an array
        if ( ! is_array( $platforms ) ) {
            return new WP_Error(
                'invalid_platforms',
                __( 'Platforms must be an array.', 'rwp-creator-suite' )
            );
        }
        
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
        // Get allowed tones from roles configuration
        $allowed_tones = array( 'professional', 'casual', 'engaging', 'informative' ); // fallback
        
        if ( class_exists( 'RWP_Creator_Suite_Caption_Admin_Settings' ) ) {
            $roles_config = RWP_Creator_Suite_Caption_Admin_Settings::get_roles_config();
            if ( is_array( $roles_config ) && ! empty( $roles_config ) ) {
                $allowed_tones = array();
                foreach ( $roles_config as $role ) {
                    if ( isset( $role['value'] ) ) {
                        $allowed_tones[] = $role['value'];
                    }
                }
                // If no valid tones found, fall back to default
                if ( empty( $allowed_tones ) ) {
                    $allowed_tones = array( 'professional', 'casual', 'engaging', 'informative' );
                }
            }
        }
        
        if ( ! in_array( $tone, $allowed_tones, true ) ) {
            return new WP_Error(
                'invalid_tone',
                sprintf( __( 'Invalid tone: %s. Allowed tones: %s', 'rwp-creator-suite' ), $tone, implode( ', ', $allowed_tones ) )
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
        $ip = RWP_Creator_Suite_Network_Utils::get_client_ip();
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
     * Phase 1 Optimization: Add caching headers for API responses.
     */
    public function add_api_cache_headers( $served, $result, $request, $server ) {
        // Only apply to our plugin's API endpoints
        if ( ! str_starts_with( $request->get_route() ?? '', 'rwp-creator-suite/v1' ) ) {
            return $served;
        }
        
        $route = $request->get_route();
        $method = $request->get_method();
        
        // Content repurposing endpoint
        if ( $method === 'POST' && str_contains( $route ?? '', '/repurpose-content' ) ) {
            // Check if this is a guest request or cached response
            $is_guest = $request->get_param( 'is_guest' ) === true;
            
            if ( $is_guest ) {
                // Guest requests - no caching for security
                header( 'Cache-Control: private, no-cache, no-store, must-revalidate' );
                header( 'X-Guest-Request: 1' );
            } else {
                // Authenticated user requests
                if ( isset( $result->data['meta']['cached'] ) && $result->data['meta']['cached'] ) {
                    // Cached response - longer cache
                    header( 'Cache-Control: private, max-age=600' ); // 10 minutes
                    header( 'X-Cache-Status: HIT' );
                } else {
                    // Fresh response - shorter cache
                    header( 'Cache-Control: private, max-age=300' ); // 5 minutes
                    header( 'X-Cache-Status: MISS' );
                }
                header( 'Vary: Authorization' );
            }
        } else {
            // Other endpoints - no cache
            header( 'Cache-Control: no-cache, no-store, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
        }
        
        // Add performance headers
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        
        return $served;
    }
    
}