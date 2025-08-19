<?php
/**
 * Hashtag Tracker
 * 
 * Integrates with existing caption writer and content repurposer to track
 * user-added hashtags (NOT AI-generated ones) for analytics purposes.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.6.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Hashtag_Tracker {

    /**
     * Analytics system instance.
     *
     * @var RWP_Creator_Suite_Anonymous_Analytics
     */
    private $analytics;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
    }

    /**
     * Initialize hashtag tracking.
     */
    public function init() {
        // Hook into REST API endpoints to track hashtag usage
        add_action( 'rest_api_init', array( $this, 'register_tracking_endpoints' ) );
        
        // Hook into frontend JavaScript to track user input
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );
        
        // Hook into content save/generation to track hashtags
        add_action( 'rwp_caption_generated', array( $this, 'track_caption_hashtags' ), 10, 3 );
        add_action( 'rwp_content_repurposed', array( $this, 'track_repurposed_hashtags' ), 10, 3 );
        
        // Track template usage
        add_action( 'rwp_template_used', array( $this, 'track_template_hashtags' ), 10, 4 );
    }

    /**
     * Register REST API endpoints for hashtag tracking.
     */
    public function register_tracking_endpoints() {
        register_rest_route( 'rwp-creator-suite/v1', '/analytics/track-hashtag', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'track_hashtag_endpoint' ),
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => array(
                'hashtag' => array(
                    'required' => true,
                    'type'     => 'string',
                    'sanitize_callback' => array( $this, 'sanitize_hashtag' ),
                    'validate_callback' => array( $this, 'validate_hashtag' ),
                ),
                'platform' => array(
                    'type'     => 'string',
                    'enum'     => array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' ),
                    'default'  => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'tone' => array(
                    'type'     => 'string',
                    'enum'     => array( 'witty', 'inspirational', 'question', 'professional', 'casual' ),
                    'default'  => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'feature' => array(
                    'type'     => 'string',
                    'enum'     => array( 'caption_writer', 'content_repurposer', 'template', 'manual' ),
                    'default'  => 'manual',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'source' => array(
                    'type'     => 'string',
                    'enum'     => array( 'user_input', 'template_customization', 'manual_addition' ),
                    'default'  => 'user_input',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        register_rest_route( 'rwp-creator-suite/v1', '/analytics/track-action', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'track_action_endpoint' ),
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => array(
                'action' => array(
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => array( 'platform_selected', 'tone_selected', 'feature_used' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'value' => array(
                    'required' => true,
                    'type'     => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'context' => array(
                    'type'     => 'object',
                    'default'  => array(),
                ),
            ),
        ) );
    }

    /**
     * Enqueue hashtag tracking JavaScript.
     */
    public function enqueue_tracking_script() {
        // Only enqueue on pages with our blocks or admin pages
        if ( ! $this->should_enqueue_tracking() ) {
            return;
        }

        wp_enqueue_script(
            'rwp-hashtag-tracker',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/hashtag-tracker.js',
            array( 'wp-api-fetch' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        wp_localize_script( 'rwp-hashtag-tracker', 'rwpHashtagTracker', array(
            'apiUrl' => rest_url( 'rwp-creator-suite/v1/' ),
            'nonce'  => wp_create_nonce( 'wp_rest' ),
            'debug'  => WP_DEBUG,
        ) );
    }

    /**
     * Check if we should enqueue tracking script.
     *
     * @return bool
     */
    private function should_enqueue_tracking() {
        // Always enqueue in admin
        if ( is_admin() ) {
            return true;
        }

        // Check if current page has our blocks
        global $post;
        if ( $post && has_blocks( $post->post_content ) ) {
            $blocks = parse_blocks( $post->post_content );
            foreach ( $blocks as $block ) {
                if ( isset( $block['blockName'] ) && str_starts_with( $block['blockName'], 'rwp-creator-suite/' ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * REST endpoint for tracking hashtags.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function track_hashtag_endpoint( $request ) {
        $hashtag = $request->get_param( 'hashtag' );
        $platform = $request->get_param( 'platform' );
        $tone = $request->get_param( 'tone' );
        $feature = $request->get_param( 'feature' );
        $source = $request->get_param( 'source' );

        // Only track if user has consented
        if ( ! $this->analytics->user_has_consented() ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Analytics consent required'
            ), 200 );
        }

        $context = array(
            'platform' => $platform,
            'tone' => $tone,
            'content_type' => $feature,
            'source' => $source,
        );

        $this->analytics->track_user_hashtag( $hashtag, $context );

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Hashtag tracked successfully'
        ), 200 );
    }

    /**
     * REST endpoint for tracking general actions.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function track_action_endpoint( $request ) {
        $action = $request->get_param( 'action' );
        $value = $request->get_param( 'value' );
        $context = $request->get_param( 'context' );

        // Only track if user has consented
        if ( ! $this->analytics->user_has_consented() ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Analytics consent required'
            ), 200 );
        }

        switch ( $action ) {
            case 'platform_selected':
                $this->analytics->track_platform_selection( $value, $context );
                break;
            case 'tone_selected':
                $this->analytics->track_tone_selection( $value, $context );
                break;
            case 'feature_used':
                $this->analytics->track_feature_usage( $value, $context );
                break;
        }

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Action tracked successfully'
        ), 200 );
    }

    /**
     * Track hashtags from generated captions.
     *
     * @param string $caption Generated caption.
     * @param array  $context Generation context.
     * @param array  $user_data User input data.
     */
    public function track_caption_hashtags( $caption, $context, $user_data ) {
        // Only track hashtags that user explicitly added to their input
        if ( isset( $user_data['description'] ) ) {
            $user_hashtags = $this->extract_hashtags_from_text( $user_data['description'] );
            
            foreach ( $user_hashtags as $hashtag ) {
                $tracking_context = array(
                    'platform' => $context['platform'] ?? '',
                    'tone' => $context['tone'] ?? '',
                    'content_type' => 'caption',
                    'source' => 'user_input',
                );
                
                $this->analytics->track_user_hashtag( $hashtag, $tracking_context );
            }
        }

        // Track content generation
        $generation_context = array(
            'feature' => 'caption_writer',
            'platform' => $context['platform'] ?? '',
            'tone' => $context['tone'] ?? '',
            'success' => true,
            'content_length' => strlen( $caption ),
        );

        $this->analytics->track_content_generation( $generation_context );
    }

    /**
     * Track hashtags from repurposed content.
     *
     * @param string $content Repurposed content.
     * @param array  $context Repurposing context.
     * @param array  $user_data User input data.
     */
    public function track_repurposed_hashtags( $content, $context, $user_data ) {
        // Only track hashtags that user explicitly added to their input
        if ( isset( $user_data['content'] ) ) {
            $user_hashtags = $this->extract_hashtags_from_text( $user_data['content'] );
            
            foreach ( $user_hashtags as $hashtag ) {
                $tracking_context = array(
                    'platform' => $context['platform'] ?? '',
                    'tone' => $context['tone'] ?? '',
                    'content_type' => 'repurposed',
                    'source' => 'user_input',
                );
                
                $this->analytics->track_user_hashtag( $hashtag, $tracking_context );
            }
        }

        // Track content generation
        $generation_context = array(
            'feature' => 'content_repurposer',
            'platform' => $context['platform'] ?? '',
            'tone' => $context['tone'] ?? '',
            'success' => true,
            'content_length' => strlen( $content ),
        );

        $this->analytics->track_content_generation( $generation_context );
    }

    /**
     * Track hashtags from template usage.
     *
     * @param string $template_id Template ID.
     * @param array  $variables Template variables.
     * @param array  $context Template context.
     * @param string $final_content Final generated content.
     */
    public function track_template_hashtags( $template_id, $variables, $context, $final_content ) {
        // Track hashtags from template variables that user customized
        foreach ( $variables as $key => $value ) {
            if ( is_string( $value ) && str_contains( $key, 'hashtag' ) ) {
                $hashtags = $this->extract_hashtags_from_text( $value );
                
                foreach ( $hashtags as $hashtag ) {
                    $tracking_context = array(
                        'platform' => $context['platform'] ?? '',
                        'tone' => $context['tone'] ?? '',
                        'content_type' => 'template',
                        'source' => 'template_customization',
                    );
                    
                    $this->analytics->track_user_hashtag( $hashtag, $tracking_context );
                }
            }
        }

        // Track template usage
        $template_context = array(
            'platform' => $context['platform'] ?? '',
            'tone' => $context['tone'] ?? '',
            'completion_status' => 'completed',
            'customizations_made' => count( $variables ),
        );

        $this->analytics->track_template_usage( $template_id, $template_context );
    }

    /**
     * Extract hashtags from text.
     *
     * @param string $text Text to extract hashtags from.
     * @return array Array of hashtags (without #).
     */
    private function extract_hashtags_from_text( $text ) {
        preg_match_all( '/#([a-zA-Z0-9_]+)/', $text, $matches );
        
        // Return unique hashtags, lowercase for consistency
        $hashtags = array_map( 'strtolower', $matches[1] );
        return array_unique( $hashtags );
    }

    /**
     * Sanitize hashtag input.
     *
     * @param string $hashtag Hashtag to sanitize.
     * @return string
     */
    public function sanitize_hashtag( $hashtag ) {
        // Remove # if present and sanitize
        $hashtag = ltrim( sanitize_text_field( $hashtag ), '#' );
        
        // Only allow alphanumeric and underscore
        return preg_replace( '/[^a-zA-Z0-9_]/', '', $hashtag );
    }

    /**
     * Validate hashtag input.
     *
     * @param string $hashtag Hashtag to validate.
     * @return bool|WP_Error
     */
    public function validate_hashtag( $hashtag ) {
        if ( empty( $hashtag ) ) {
            return new WP_Error( 'empty_hashtag', 'Hashtag cannot be empty' );
        }

        if ( strlen( $hashtag ) > 100 ) {
            return new WP_Error( 'hashtag_too_long', 'Hashtag is too long' );
        }

        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', ltrim( $hashtag, '#' ) ) ) {
            return new WP_Error( 'invalid_hashtag', 'Hashtag contains invalid characters' );
        }

        return true;
    }

    /**
     * Check if current request has analytics tracking disabled.
     *
     * @return bool
     */
    private function is_tracking_disabled() {
        // Check for explicit opt-out
        if ( isset( $_REQUEST['rwp_no_tracking'] ) && $_REQUEST['rwp_no_tracking'] === '1' ) {
            return true;
        }

        // Check for Do Not Track header
        if ( isset( $_SERVER['HTTP_DNT'] ) && $_SERVER['HTTP_DNT'] === '1' ) {
            return true;
        }

        return false;
    }

    /**
     * Get hashtag suggestions based on analytics data.
     * This is for future use when we want to provide data-driven suggestions.
     *
     * @param array $context Context for suggestions.
     * @return array
     */
    public function get_hashtag_suggestions( $context = array() ) {
        // This would query anonymized analytics data to suggest popular hashtags
        // Implementation would depend on Phase 2 dashboard requirements
        return array();
    }
}