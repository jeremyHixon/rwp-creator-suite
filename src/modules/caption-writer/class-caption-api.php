<?php
/**
 * Caption Writer API
 * 
 * Handles REST API endpoints for caption generation and user data.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Caption_API {

    private $namespace = 'rwp-creator-suite/v1';
    private $cache_manager;
    private $advanced_cache_manager;
    
    /**
     * Initialize the Caption API.
     */
    public function init() {
        $this->cache_manager = new RWP_Creator_Suite_Caption_Cache();
        $this->cache_manager->init();
        
        // Phase 1 Optimization: Enhanced cache manager integration
        $this->advanced_cache_manager = RWP_Creator_Suite_Cache_Manager::get_instance();
        
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        
        // Phase 1 Optimization: Add caching headers for API responses
        add_action( 'rest_pre_serve_request', array( $this, 'add_api_cache_headers' ), 10, 4 );
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
            'permission_callback' => array( $this, 'verify_nonce_permission' ),
        ) );
        
        // User preferences
        register_rest_route( $this->namespace, '/preferences', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_user_preferences' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'update_user_preferences' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
                'args'                => array(
                    'preferred_tone' => array(
                        'type'     => 'string',
                        'enum'     => array( 'witty', 'inspirational', 'question', 'professional', 'casual' ),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'preferred_platforms' => array(
                        'type'     => 'array',
                        'items'    => array(
                            'type' => 'string',
                            'enum' => array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' ),
                        ),
                        'sanitize_callback' => array( $this, 'sanitize_platforms' ),
                        'validate_callback' => array( $this, 'validate_platforms' ),
                    ),
                ),
            ),
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
        
        // Initialize AI service first for shared functionality
        $ai_service = new RWP_Creator_Suite_AI_Service();
        
        // Phase 1 Optimization: Use advanced cache manager with remember pattern
        $cache_key = 'captions_' . md5( serialize( array(
            'description' => trim( strtolower( $description ) ),
            'tone' => $tone,
            'platform' => $primary_platform,
            'version' => '1.1' // Increment to invalidate old cache entries
        ) ) );
        
        $cached_result = $this->advanced_cache_manager->remember(
            $cache_key,
            function() use ( $description, $tone, $primary_platform, $ai_service ) {
                // Check shared rate limiting
                $rate_limit_result = $ai_service->check_rate_limit( 'caption_generation' );
                if ( is_wp_error( $rate_limit_result ) ) {
                    return $rate_limit_result;
                }
                
                $captions = $ai_service->generate_captions( $description, $tone, $primary_platform );
                
                if ( is_wp_error( $captions ) ) {
                    return $captions;
                }
                
                // Track usage in shared system
                $ai_service->track_usage( 1, 'caption_generation' );
                
                // Also cache in legacy system for backward compatibility
                $this->cache_manager->cache_captions( $description, $tone, $primary_platform, $captions );
                
                return array(
                    'captions' => $captions,
                    'generated_at' => current_time( 'mysql' ),
                    'cached' => false,
                );
            },
            'ai_responses',
            4 * HOUR_IN_SECONDS // 4-hour cache for AI responses
        );
        
        // Handle errors from cached callback
        if ( is_wp_error( $cached_result ) ) {
            return $cached_result;
        }
        
        // Fallback to legacy cache if advanced cache fails
        if ( ! $cached_result ) {
            $cached_captions = $this->cache_manager->get_cached_captions( $description, $tone, $primary_platform );
            if ( $cached_captions && isset( $cached_captions['captions'] ) ) {
                $cached_result = array(
                    'captions' => $cached_captions['captions'],
                    'generated_at' => $cached_captions['generated_at'],
                    'cached' => true,
                );
            }
        }
        
        // If we still don't have results, something went wrong
        if ( ! $cached_result ) {
            return new WP_Error(
                'cache_failure',
                __( 'Failed to generate or retrieve captions. Please try again.', 'rwp-creator-suite' ),
                array( 'status' => 500 )
            );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'data'    => $cached_result['captions'],
            'meta'    => array(
                'platform_limits' => $this->get_platform_limits( $platforms ),
                'platforms' => $platforms,
                'generated_at'   => $cached_result['generated_at'],
                'cached'         => $cached_result['cached'],
                'remaining_quota' => $ai_service->get_usage_stats()['remaining'],
                'cache_key_hash' => substr( $cache_key, -8 ), // For debugging
            ),
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
        $ai_service = new RWP_Creator_Suite_AI_Service();
        $usage_stats = $ai_service->get_usage_stats();
        
        return rest_ensure_response( array(
            'success' => true,
            'data' => $usage_stats,
        ) );
    }
    
    /**
     * Get user preferences.
     */
    public function get_user_preferences( $request ) {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            return new WP_Error(
                'not_logged_in',
                __( 'User must be logged in', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }
        
        $preferences = get_user_meta( $user_id, 'rwp_caption_preferences', true );
        if ( ! is_array( $preferences ) ) {
            $preferences = array(
                'preferred_tone' => 'casual',
                'preferred_platforms' => array( 'instagram' ),
            );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'data'    => $preferences,
        ) );
    }
    
    /**
     * Update user preferences.
     */
    public function update_user_preferences( $request ) {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            return new WP_Error(
                'not_logged_in',
                __( 'User must be logged in', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }
        
        $preferences = $request->get_json_params();
        
        // Get existing preferences
        $existing_preferences = get_user_meta( $user_id, 'rwp_caption_preferences', true );
        if ( ! is_array( $existing_preferences ) ) {
            $existing_preferences = array();
        }
        
        // Sanitize and validate new preferences
        $clean_preferences = array();
        
        if ( isset( $preferences['preferred_tone'] ) ) {
            $tone = sanitize_text_field( $preferences['preferred_tone'] );
            if ( in_array( $tone, array( 'witty', 'inspirational', 'question', 'professional', 'casual' ), true ) ) {
                $clean_preferences['preferred_tone'] = $tone;
            }
        }
        
        if ( isset( $preferences['preferred_platforms'] ) ) {
            $platforms = $this->sanitize_platforms( $preferences['preferred_platforms'] );
            if ( ! empty( $platforms ) ) {
                $clean_preferences['preferred_platforms'] = $platforms;
            }
        }
        
        // Merge with existing preferences
        $updated_preferences = array_merge( $existing_preferences, $clean_preferences );
        
        // Save preferences
        $updated = update_user_meta( $user_id, 'rwp_caption_preferences', $updated_preferences );
        
        if ( $updated !== false ) {
            return rest_ensure_response( array(
                'success' => true,
                'message' => __( 'Preferences updated successfully', 'rwp-creator-suite' ),
                'data'    => $updated_preferences,
            ) );
        }
        
        return new WP_Error( 'update_failed', 'Failed to update preferences', array( 'status' => 500 ) );
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
            RWP_Creator_Suite_Error_Logger::log_security_event(
                'Missing nonce in Caption API request',
                array( 
                    'endpoint' => $request->get_route(),
                    'user_agent' => $request->get_header( 'User-Agent' ),
                    'referer' => $request->get_header( 'Referer' )
                )
            );
            return new WP_Error(
                'missing_nonce',
                __( 'Security token is missing', 'rwp-creator-suite' ),
                array( 'status' => 403 )
            );
        }
        
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            RWP_Creator_Suite_Error_Logger::log_security_event(
                'Invalid nonce in Caption API request',
                array( 
                    'endpoint' => $request->get_route(),
                    'user_agent' => $request->get_header( 'User-Agent' ),
                    'referer' => $request->get_header( 'Referer' ),
                    'user_id' => get_current_user_id()
                )
            );
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
     * Phase 1 Optimization: Add caching headers for API responses.
     */
    public function add_api_cache_headers( $served, $result, $request, $server ) {
        // Only apply to our plugin's API endpoints
        if ( ! str_starts_with( $request->get_route() ?? '', $this->namespace ) ) {
            return $served;
        }
        
        $route = $request->get_route();
        $method = $request->get_method();
        
        // Different cache strategies for different endpoints
        if ( $method === 'GET' ) {
            if ( str_contains( $route ?? '', '/favorites' ) ) {
                // User favorites - short cache, private
                header( 'Cache-Control: private, max-age=300' ); // 5 minutes
                header( 'Vary: Authorization' );
            } elseif ( str_contains( $route ?? '', '/quota' ) ) {
                // Quota status - very short cache
                header( 'Cache-Control: private, max-age=60' ); // 1 minute
                header( 'Vary: Authorization' );
            } elseif ( str_contains( $route ?? '', '/preferences' ) ) {
                // User preferences - short cache, private
                header( 'Cache-Control: private, max-age=600' ); // 10 minutes
                header( 'Vary: Authorization' );
            }
        } elseif ( $method === 'POST' && str_contains( $route ?? '', '/captions/generate' ) ) {
            // Caption generation - cacheable for same requests
            if ( isset( $result->data['meta']['cached'] ) && $result->data['meta']['cached'] ) {
                // Cached response - longer cache
                header( 'Cache-Control: public, max-age=1800' ); // 30 minutes
                header( 'X-Cache-Status: HIT' );
            } else {
                // Fresh response - shorter cache
                header( 'Cache-Control: public, max-age=300' ); // 5 minutes
                header( 'X-Cache-Status: MISS' );
            }
            header( 'Vary: Accept-Encoding, Accept-Language' );
        } else {
            // Other endpoints (POST/PUT/DELETE) - no cache
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