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
        
        // Check shared rate limiting
        $rate_limit_result = $this->ai_service->check_rate_limit( 'content_repurposing' );
        if ( is_wp_error( $rate_limit_result ) ) {
            return $rate_limit_result;
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
        
        return rest_ensure_response( array(
            'success' => true,
            'data' => $result,
            'usage' => $this->ai_service->get_usage_stats(),
        ) );
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
                        $preview_text = mb_substr( $full_text, 0, $preview_length );
                        if ( mb_strlen( $full_text ) > $preview_length ) {
                            $preview_text .= '...';
                        }
                        
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
}