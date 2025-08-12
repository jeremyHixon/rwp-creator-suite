<?php
/**
 * Caption Writer API
 * 
 * Handles REST API endpoints for caption generation, templates, and user data.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Caption_API {

    private $namespace = 'rwp-creator-suite/v1';
    private $cache_manager;
    
    /**
     * Initialize the Caption API.
     */
    public function init() {
        $this->cache_manager = new RWP_Creator_Suite_Caption_Cache();
        $this->cache_manager->init();
        
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }
    
    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Generate AI captions
        register_rest_route( $this->namespace, '/captions/generate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'generate_captions' ),
            'permission_callback' => array( $this, 'verify_nonce_permission' ),
            'args'                => array(
                'description' => array(
                    'required' => true,
                    'type'     => 'string',
                    'sanitize_callback' => array( $this, 'sanitize_description' ),
                    'validate_callback' => array( $this, 'validate_description' ),
                ),
                'tone' => array(
                    'type'     => 'string',
                    'enum'     => array( 'witty', 'inspirational', 'question', 'professional', 'casual' ),
                    'default'  => 'casual',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'platform' => array(
                    'type'     => 'string',
                    'enum'     => array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' ),
                    'default'  => 'instagram',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'platforms' => array(
                    'type'     => 'array',
                    'items'    => array(
                        'type' => 'string',
                        'enum' => array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' ),
                    ),
                    'sanitize_callback' => array( $this, 'sanitize_platforms' ),
                    'validate_callback' => array( $this, 'validate_platforms' ),
                ),
            ),
        ) );
        
        // Save/get user templates
        register_rest_route( $this->namespace, '/templates', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_user_templates' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'save_user_template' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
                'args'                => $this->get_template_schema(),
            ),
        ) );
        
        // Delete user template
        register_rest_route( $this->namespace, '/templates/(?P<id>[a-zA-Z0-9\-]+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'delete_user_template' ),
            'permission_callback' => array( $this, 'check_user_logged_in' ),
            'args'                => array(
                'id' => array(
                    'required' => true,
                    'type'     => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
        
        // Save/get user favorites
        register_rest_route( $this->namespace, '/favorites', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_user_favorites' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'save_user_favorite' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
                'args'                => array(
                    'caption' => array(
                        'required' => true,
                        'type'     => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ),
                ),
            ),
        ) );
        
        // Delete user favorite
        register_rest_route( $this->namespace, '/favorites/(?P<id>[a-zA-Z0-9\-]+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'delete_user_favorite' ),
            'permission_callback' => array( $this, 'check_user_logged_in' ),
            'args'                => array(
                'id' => array(
                    'required' => true,
                    'type'     => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
        
        // Get quota status
        register_rest_route( $this->namespace, '/quota', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_quota_status' ),
            'permission_callback' => array( $this, 'check_user_logged_in' ),
        ) );
    }
    
    /**
     * Generate AI captions.
     */
    public function generate_captions( $request ) {
        $description = $request->get_param( 'description' );
        $tone = $request->get_param( 'tone' );
        $platforms = $request->get_param( 'platforms' ) ?: array( $request->get_param( 'platform' ) ?: 'instagram' );
        
        // Ensure platforms is an array and has valid values
        if ( ! is_array( $platforms ) ) {
            $platforms = array( $platforms );
        }
        $platforms = array_filter( $platforms, function( $platform ) {
            return in_array( $platform, array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' ), true );
        } );
        if ( empty( $platforms ) ) {
            $platforms = array( 'instagram' );
        }
        
        // For caching, use the primary platform (first one)
        $primary_platform = $platforms[0];
        
        // Check cache first for non-logged-in users or if caching is enabled
        $cached_captions = $this->cache_manager->get_cached_captions( $description, $tone, $primary_platform );
        if ( $cached_captions && isset( $cached_captions['captions'] ) ) {
            return rest_ensure_response( array(
                'success' => true,
                'data'    => $cached_captions['captions'],
                'meta'    => array(
                    'platform_limits' => $this->get_platform_limits( $platforms ),
                    'platforms' => $platforms,
                    'generated_at'   => $cached_captions['generated_at'],
                    'cached'         => true,
                    'remaining_quota' => get_current_user_id() ? $this->get_remaining_quota( get_current_user_id() ) : null,
                ),
            ) );
        }
        
        // Check rate limiting (use IP for non-logged-in users)
        $user_id = get_current_user_id();
        $identifier = $user_id ?: $this->get_client_ip();
        $rate_limit_result = $this->check_rate_limit( $identifier );
        if ( is_wp_error( $rate_limit_result ) ) {
            return $rate_limit_result;
        }
        
        // Initialize AI service
        $ai_service = new RWP_Creator_Suite_AI_Caption_Service();
        $captions = $ai_service->generate_captions( $description, $tone, $primary_platform );
        
        if ( is_wp_error( $captions ) ) {
            return $captions;
        }
        
        // Cache the results
        $this->cache_manager->cache_captions( $description, $tone, $primary_platform, $captions );
        
        // Track usage for logged-in users only
        if ( $user_id ) {
            $this->track_usage( $user_id, 'ai_generation' );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'data'    => $captions,
            'meta'    => array(
                'platform_limits' => $this->get_platform_limits( $platforms ),
                'platforms' => $platforms,
                'generated_at'   => current_time( 'mysql' ),
                'cached'         => false,
                'remaining_quota' => $user_id ? $this->get_remaining_quota( $user_id ) : null,
            ),
        ) );
    }
    
    /**
     * Get user templates.
     */
    public function get_user_templates( $request ) {
        $template_manager = new RWP_Creator_Suite_Template_Manager();
        $templates = $template_manager->get_user_templates( get_current_user_id() );
        
        return rest_ensure_response( array(
            'success' => true,
            'data'    => $templates,
        ) );
    }
    
    /**
     * Save user template.
     */
    public function save_user_template( $request ) {
        $template_data = array(
            'name'      => $request->get_param( 'name' ),
            'category'  => $request->get_param( 'category' ),
            'template'  => $request->get_param( 'template' ),
            'variables' => $request->get_param( 'variables' ),
            'platforms' => $request->get_param( 'platforms' ),
        );
        
        $template_manager = new RWP_Creator_Suite_Template_Manager();
        $result = $template_manager->save_user_template( get_current_user_id(), $template_data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'message' => __( 'Template saved successfully', 'rwp-creator-suite' ),
            'template_id' => $result,
        ) );
    }
    
    /**
     * Delete user template.
     */
    public function delete_user_template( $request ) {
        $template_id = $request->get_param( 'id' );
        
        $template_manager = new RWP_Creator_Suite_Template_Manager();
        $result = $template_manager->delete_user_template( get_current_user_id(), $template_id );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'message' => __( 'Template deleted successfully', 'rwp-creator-suite' ),
        ) );
    }
    
    /**
     * Get user favorites.
     */
    public function get_user_favorites( $request ) {
        $favorites = get_user_meta( get_current_user_id(), 'rwp_caption_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = array();
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'data'    => $favorites,
        ) );
    }
    
    /**
     * Save user favorite.
     */
    public function save_user_favorite( $request ) {
        $caption = $request->get_param( 'caption' );
        $user_id = get_current_user_id();
        
        $favorites = get_user_meta( $user_id, 'rwp_caption_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = array();
        }
        
        $favorite = array(
            'id'         => wp_generate_uuid4(),
            'caption'    => $caption,
            'created_at' => current_time( 'mysql' ),
        );
        
        $favorites[] = $favorite;
        
        // Limit to 100 favorites per user
        if ( count( $favorites ) > 100 ) {
            $favorites = array_slice( $favorites, -100 );
        }
        
        update_user_meta( $user_id, 'rwp_caption_favorites', $favorites );
        
        return rest_ensure_response( array(
            'success' => true,
            'message' => __( 'Caption saved to favorites', 'rwp-creator-suite' ),
            'favorite_id' => $favorite['id'],
        ) );
    }
    
    /**
     * Delete user favorite.
     */
    public function delete_user_favorite( $request ) {
        $favorite_id = $request->get_param( 'id' );
        $user_id = get_current_user_id();
        
        $favorites = get_user_meta( $user_id, 'rwp_caption_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = array();
        }
        
        $favorites = array_filter( $favorites, function( $favorite ) use ( $favorite_id ) {
            return $favorite['id'] !== $favorite_id;
        } );
        
        update_user_meta( $user_id, 'rwp_caption_favorites', array_values( $favorites ) );
        
        return rest_ensure_response( array(
            'success' => true,
            'message' => __( 'Favorite deleted successfully', 'rwp-creator-suite' ),
        ) );
    }
    
    /**
     * Get quota status for current user.
     */
    public function get_quota_status( $request ) {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            return new WP_Error(
                'not_logged_in',
                __( 'User must be logged in', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }
        
        $remaining_quota = $this->get_remaining_quota( $user_id );
        
        return rest_ensure_response( array(
            'success' => true,
            'data' => array(
                'remaining_quota' => $remaining_quota,
                'user_id' => $user_id,
            ),
        ) );
    }
    
    /**
     * Check if user is logged in.
     */
    public function check_user_logged_in( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'not_logged_in',
                __( 'You must be logged in to access this feature', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }
        
        return $this->verify_nonce_permission( $request );
    }
    
    /**
     * Verify nonce for security.
     */
    public function verify_nonce_permission( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        
        if ( empty( $nonce ) ) {
            return new WP_Error(
                'missing_nonce',
                __( 'Security token is missing', 'rwp-creator-suite' ),
                array( 'status' => 403 )
            );
        }
        
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'invalid_nonce',
                __( 'Security token is invalid', 'rwp-creator-suite' ),
                array( 'status' => 403 )
            );
        }
        
        return true;
    }
    
    /**
     * Sanitize description parameter.
     */
    public function sanitize_description( $param ) {
        // Remove dangerous characters and normalize whitespace
        $param = sanitize_textarea_field( $param );
        $param = wp_strip_all_tags( $param );
        $param = preg_replace( '/\s+/', ' ', $param );
        return trim( $param );
    }
    
    /**
     * Validate description parameter.
     */
    public function validate_description( $param, $request, $key ) {
        $param = trim( $param );
        
        if ( empty( $param ) ) {
            return new WP_Error(
                'empty_description',
                __( 'Description cannot be empty', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }
        
        if ( strlen( $param ) < 10 ) {
            return new WP_Error(
                'description_too_short',
                __( 'Description must be at least 10 characters long', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }
        
        if ( strlen( $param ) > 500 ) {
            return new WP_Error(
                'description_too_long',
                __( 'Description must be 500 characters or less', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }
        
        // Check for suspicious patterns
        $suspicious_patterns = array(
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/data:text\/html/i',
            '/vbscript:/i',
            '/on\w+\s*=/i',
        );
        
        foreach ( $suspicious_patterns as $pattern ) {
            if ( preg_match( $pattern, $param ) ) {
                return new WP_Error(
                    'invalid_content',
                    __( 'Description contains invalid content', 'rwp-creator-suite' ),
                    array( 'status' => 400 )
                );
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize platforms parameter.
     */
    public function sanitize_platforms( $platforms ) {
        if ( ! is_array( $platforms ) ) {
            return array( 'instagram' );
        }
        
        $valid_platforms = array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' );
        $sanitized = array();
        
        foreach ( $platforms as $platform ) {
            $sanitized_platform = sanitize_text_field( $platform );
            if ( in_array( $sanitized_platform, $valid_platforms, true ) ) {
                $sanitized[] = $sanitized_platform;
            }
        }
        
        return empty( $sanitized ) ? array( 'instagram' ) : array_unique( $sanitized );
    }
    
    /**
     * Validate platforms parameter.
     */
    public function validate_platforms( $platforms, $request, $key ) {
        if ( ! is_array( $platforms ) ) {
            return new WP_Error(
                'invalid_platforms',
                __( 'Platforms must be an array', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }
        
        if ( empty( $platforms ) ) {
            return new WP_Error(
                'empty_platforms',
                __( 'At least one platform must be specified', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }
        
        if ( count( $platforms ) > 5 ) {
            return new WP_Error(
                'too_many_platforms',
                __( 'Maximum 5 platforms allowed', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }
        
        $valid_platforms = array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' );
        foreach ( $platforms as $platform ) {
            if ( ! in_array( $platform, $valid_platforms, true ) ) {
                return new WP_Error(
                    'invalid_platform',
                    sprintf( __( 'Invalid platform: %s', 'rwp-creator-suite' ), $platform ),
                    array( 'status' => 400 )
                );
            }
        }
        
        return true;
    }
    
    /**
     * Get template schema for validation.
     */
    private function get_template_schema() {
        return array(
            'name' => array(
                'required' => true,
                'type'     => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'category' => array(
                'required' => true,
                'type'     => 'string',
                'enum'     => array( 'business', 'personal', 'engagement', 'other' ),
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'template' => array(
                'required' => true,
                'type'     => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'variables' => array(
                'type'     => 'array',
                'items'    => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'default'  => array(),
            ),
            'platforms' => array(
                'type'     => 'array',
                'items'    => array(
                    'type' => 'string',
                    'enum' => array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' ),
                ),
                'default'  => array( 'instagram' ),
            ),
        );
    }
    
    /**
     * Check rate limiting for AI generation.
     */
    private function check_rate_limit( $identifier ) {
        // Determine if this is a user ID or IP address
        $is_user = is_numeric( $identifier ) && $identifier > 0;
        
        // Get limit based on user status
        if ( $is_user ) {
            $is_premium = apply_filters( 'rwp_caption_writer_is_premium_user', false, $identifier );
            if ( $is_premium ) {
                $limit = get_option( 'rwp_creator_suite_rate_limit_premium', 50 );
            } else {
                $limit = get_option( 'rwp_creator_suite_rate_limit_free', 10 );
            }
        } else {
            // IP-based rate limiting (more restrictive)
            $limit = 5; // 5 generations per hour for anonymous users
        }
        
        $limit = apply_filters( 'rwp_caption_writer_rate_limit', $limit, $identifier );
        
        $cache_key = 'rwp_caption_rate_limit_' . md5( $identifier );
        $current_count = get_transient( $cache_key );
        
        if ( false === $current_count ) {
            $current_count = 0;
        }
        
        if ( $current_count >= $limit ) {
            return new WP_Error( 
                'rate_limit_exceeded', 
                sprintf( 
                    __( 'Rate limit exceeded. You can generate %d captions per hour.', 'rwp-creator-suite' ), 
                    $limit 
                ),
                array( 'status' => 429 )
            );
        }
        
        set_transient( $cache_key, $current_count + 1, HOUR_IN_SECONDS );
        
        return true;
    }
    
    /**
     * Get remaining quota for user.
     */
    private function get_remaining_quota( $user_id ) {
        // Get user-specific limit based on premium status
        $is_premium = apply_filters( 'rwp_caption_writer_is_premium_user', false, $user_id );
        
        if ( $is_premium ) {
            $limit = get_option( 'rwp_creator_suite_rate_limit_premium', 50 );
        } else {
            $limit = get_option( 'rwp_creator_suite_rate_limit_free', 10 );
        }
        
        $limit = apply_filters( 'rwp_caption_writer_rate_limit', $limit, $user_id );
        
        // Use the same cache key format as check_rate_limit()
        $cache_key = 'rwp_caption_rate_limit_' . md5( $user_id );
        $current_count = get_transient( $cache_key );
        
        if ( false === $current_count ) {
            $current_count = 0;
        }
        
        return max( 0, $limit - $current_count );
    }
    
    /**
     * Track usage for analytics.
     */
    private function track_usage( $user_id, $action ) {
        $usage_key = 'rwp_caption_usage_' . $user_id;
        $usage_data = get_user_meta( $user_id, $usage_key, true );
        
        if ( ! is_array( $usage_data ) ) {
            $usage_data = array();
        }
        
        $date = current_time( 'Y-m-d' );
        
        if ( ! isset( $usage_data[ $date ] ) ) {
            $usage_data[ $date ] = array();
        }
        
        if ( ! isset( $usage_data[ $date ][ $action ] ) ) {
            $usage_data[ $date ][ $action ] = 0;
        }
        
        $usage_data[ $date ][ $action ]++;
        
        // Keep only last 30 days of data
        $cutoff_date = date( 'Y-m-d', strtotime( '-30 days' ) );
        $usage_data = array_filter( $usage_data, function( $key ) use ( $cutoff_date ) {
            return $key >= $cutoff_date;
        }, ARRAY_FILTER_USE_KEY );
        
        update_user_meta( $user_id, $usage_key, $usage_data );
    }
    
    /**
     * Get character limit for platform.
     */
    private function get_character_limit( $platform ) {
        $limits = array(
            'instagram' => 2200,
            'tiktok'    => 2200,
            'twitter'   => 280,
            'linkedin'  => 3000,
            'facebook'  => 63206,
        );
        
        return isset( $limits[ $platform ] ) ? $limits[ $platform ] : 2200;
    }
    
    /**
     * Get character limits for multiple platforms.
     */
    private function get_platform_limits( $platforms ) {
        $limits = array(
            'instagram' => 2200,
            'tiktok'    => 2200,
            'twitter'   => 280,
            'linkedin'  => 3000,
            'facebook'  => 63206,
        );
        
        $platform_limits = array();
        foreach ( $platforms as $platform ) {
            $platform_limits[ $platform ] = isset( $limits[ $platform ] ) ? $limits[ $platform ] : 2200;
        }
        
        return $platform_limits;
    }
    
    /**
     * Get client IP address securely.
     */
    private function get_client_ip() {
        // Check for various headers in order of trustworthiness
        $headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ips = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ips[0] );
                
                // Validate IP address
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR if available, even if private
        $fallback_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return filter_var( $fallback_ip, FILTER_VALIDATE_IP ) ? $fallback_ip : '127.0.0.1';
    }
}