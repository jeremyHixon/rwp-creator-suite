<?php
/**
 * Instagram Analyzer REST API
 * 
 * Provides REST API endpoints for Instagram Analyzer functionality.
 * Replaces AJAX-based endpoints with standardized REST API.
 */

defined( 'ABSPATH' ) || exit;

require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/common/traits/trait-api-response.php';
require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/common/traits/trait-api-permissions.php';
require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/common/traits/trait-api-validation.php';

class RWP_Creator_Suite_Instagram_Analyzer_REST_API {
    use RWP_Creator_Suite_API_Response_Trait;
    use RWP_Creator_Suite_API_Permissions_Trait;
    use RWP_Creator_Suite_API_Validation_Trait;

    /**
     * API namespace
     */
    const NAMESPACE = 'rwp-creator-suite/v1';

    /**
     * Initialize the REST API endpoints.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Get user whitelist
        register_rest_route(
            self::NAMESPACE,
            '/instagram/whitelist',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_whitelist' ),
                'permission_callback' => array( $this, 'check_logged_in_with_nonce' ),
                'args' => array(),
            )
        );

        // Update user whitelist
        register_rest_route(
            self::NAMESPACE,
            '/instagram/whitelist',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'sync_whitelist' ),
                'permission_callback' => array( $this, 'check_logged_in_with_nonce' ),
                'args' => array(
                    'whitelist' => array(
                        'required' => true,
                        'type' => 'array',
                        'description' => 'Array of Instagram usernames for whitelist',
                        'items' => array(
                            'type' => 'string',
                        ),
                        'sanitize_callback' => array( $this, 'sanitize_whitelist_array' ),
                        'validate_callback' => array( $this, 'validate_whitelist_array' ),
                    ),
                ),
            )
        );
    }

    /**
     * Get user whitelist.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_whitelist( $request ) {
        $user_id = get_current_user_id();
        $whitelist = get_user_meta( $user_id, 'instagram_analyzer_whitelist', true );
        
        if ( ! is_array( $whitelist ) ) {
            $whitelist = array();
        }

        return $this->success_response( 
            $whitelist, 
            __( 'Whitelist retrieved successfully', 'rwp-creator-suite' )
        );
    }

    /**
     * Sync whitelist with server.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function sync_whitelist( $request ) {
        try {
            $user_id = get_current_user_id();
            $whitelist = $request->get_param( 'whitelist' );

            // Additional validation has already been done by sanitize/validate callbacks
            $result = update_user_meta( $user_id, 'instagram_analyzer_whitelist', $whitelist );
            
            if ( $result !== false ) {
                return $this->success_response( 
                    $whitelist, 
                    __( 'Whitelist synchronized successfully', 'rwp-creator-suite' )
                );
            } else {
                error_log( 'RWP Creator Suite: Failed to save whitelist for user ' . $user_id );
                return $this->error_response(
                    'save_failed',
                    __( 'Failed to save whitelist', 'rwp-creator-suite' ),
                    500
                );
            }
        } catch ( Exception $e ) {
            error_log( 'RWP Creator Suite Whitelist Sync Exception: ' . $e->getMessage() );
            return $this->error_response(
                'unexpected_error',
                __( 'An unexpected error occurred', 'rwp-creator-suite' ),
                500
            );
        }
    }

    /**
     * Sanitize whitelist array.
     *
     * @param array $whitelist Array of usernames.
     * @return array Sanitized whitelist.
     */
    public function sanitize_whitelist_array( $whitelist ) {
        if ( ! is_array( $whitelist ) ) {
            return array();
        }

        $sanitized_whitelist = array();
        foreach ( $whitelist as $username ) {
            $clean_username = $this->sanitize_instagram_username( $username );
            if ( $clean_username ) {
                $sanitized_whitelist[] = $clean_username;
            }
        }

        return array_unique( $sanitized_whitelist );
    }

    /**
     * Validate whitelist array.
     *
     * @param array $whitelist Array of usernames.
     * @return bool|WP_Error
     */
    public function validate_whitelist_array( $whitelist ) {
        if ( ! is_array( $whitelist ) ) {
            return new WP_Error(
                'invalid_whitelist',
                __( 'Whitelist must be an array.', 'rwp-creator-suite' ),
                array( 'status' => 400 )
            );
        }

        $max_usernames = apply_filters( 'rwp_creator_suite_max_whitelist_usernames', 100 );
        if ( count( $whitelist ) > $max_usernames ) {
            return new WP_Error(
                'whitelist_too_large',
                sprintf(
                    __( 'Whitelist cannot contain more than %d usernames.', 'rwp-creator-suite' ),
                    $max_usernames
                ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Sanitize Instagram username.
     *
     * @param string $username The username to sanitize.
     * @return string|false The sanitized username or false if invalid.
     */
    private function sanitize_instagram_username( $username ) {
        if ( ! is_string( $username ) ) {
            return false;
        }

        // Remove @ if present
        $username = ltrim( $username, '@' );
        
        // Instagram usernames can only contain letters, numbers, periods, and underscores
        $username = preg_replace( '/[^a-zA-Z0-9._]/', '', $username );
        
        // Limit length (Instagram usernames are max 30 characters)
        $username = substr( $username, 0, 30 );
        
        // Must be at least 1 character
        if ( strlen( $username ) < 1 ) {
            return false;
        }
        
        return $username;
    }
}