<?php
/**
 * Account Manager API
 *
 * Handles REST API endpoints for account management functionality
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Account_API {

    /**
     * The API namespace.
     *
     * @var string
     */
    private $namespace = 'rwp-creator-suite/v1';

    /**
     * Initialize the API.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Get user consent status
        register_rest_route( 
            $this->namespace, 
            '/consent/status', 
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_consent_status' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
            )
        );

        // Update user consent
        register_rest_route( 
            $this->namespace, 
            '/consent/update', 
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'update_consent' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
                'args'                => array(
                    'consent' => array(
                        'required'          => true,
                        'type'              => 'boolean',
                        'sanitize_callback' => 'rest_sanitize_boolean',
                        'validate_callback' => 'rest_validate_request_arg',
                        'description'       => 'Consent value (true/false)',
                    ),
                ),
            )
        );

        // Get account dashboard data
        register_rest_route( 
            $this->namespace, 
            '/account/dashboard', 
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_dashboard_data' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
            )
        );

        // Get consent statistics (admin only)
        register_rest_route( 
            $this->namespace, 
            '/consent/statistics', 
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_consent_statistics' ),
                'permission_callback' => array( $this, 'check_admin_permissions' ),
            )
        );
    }

    /**
     * Get user consent status.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_consent_status( $request ) {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            return $this->error_response( 'unauthorized', 'User not logged in', 401 );
        }

        $consent_handler = new RWP_Creator_Suite_Registration_Consent_Handler();
        $consent_status = $consent_handler->get_user_consent( $user_id );

        return $this->success_response( array(
            'consent' => $consent_status,
            'user_id' => $user_id,
            'meta_key' => RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key(),
        ) );
    }

    /**
     * Update user consent.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_consent( $request ) {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            return $this->error_response( 'unauthorized', 'User not logged in', 401 );
        }

        $consent = $request->get_param( 'consent' );
        
        // Validate consent parameter
        if ( ! is_bool( $consent ) ) {
            return $this->error_response( 'invalid_consent', 'Consent must be a boolean value', 400 );
        }

        $consent_handler = new RWP_Creator_Suite_Registration_Consent_Handler();
        $result = $consent_handler->update_user_consent( $user_id, $consent );

        if ( false === $result ) {
            return $this->error_response( 'update_failed', 'Failed to update consent preference', 500 );
        }

        // Log the consent change
        do_action( 'rwp_creator_suite_consent_updated_via_api', $user_id, $consent, current_time( 'timestamp' ) );

        return $this->success_response( 
            array(
                'consent' => $consent,
                'user_id' => $user_id,
                'updated_at' => current_time( 'c' ),
            ),
            'Consent preference updated successfully'
        );
    }

    /**
     * Get dashboard data for current user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_dashboard_data( $request ) {
        $user_id = get_current_user_id();
        
        if ( ! $user_id ) {
            return $this->error_response( 'unauthorized', 'User not logged in', 401 );
        }

        $user = get_userdata( $user_id );
        $consent_handler = new RWP_Creator_Suite_Registration_Consent_Handler();
        
        $dashboard_data = array(
            'user' => array(
                'id' => $user_id,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'registered' => $user->user_registered,
            ),
            'consent' => array(
                'status' => $consent_handler->get_user_consent( $user_id ),
                'meta_key' => RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key(),
            ),
            'account' => array(
                'login_url' => wp_login_url(),
                'profile_url' => admin_url( 'profile.php' ),
                'logout_url' => wp_logout_url(),
            ),
        );

        return $this->success_response( $dashboard_data );
    }

    /**
     * Get consent statistics (admin only).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_consent_statistics( $request ) {
        $consent_handler = new RWP_Creator_Suite_Registration_Consent_Handler();
        $statistics = $consent_handler->get_consent_statistics();

        return $this->success_response( $statistics );
    }

    /**
     * Check if user is logged in.
     *
     * @return bool
     */
    public function check_user_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Check if user has admin permissions.
     *
     * @return bool
     */
    public function check_admin_permissions() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Return a successful response.
     *
     * @param mixed  $data Response data.
     * @param string $message Optional message.
     * @param array  $meta Optional metadata.
     * @return WP_REST_Response
     */
    private function success_response( $data, $message = '', $meta = array() ) {
        $response = array(
            'success' => true,
            'data'    => $data,
        );
        
        if ( $message ) {
            $response['message'] = $message;
        }
        
        if ( ! empty( $meta ) ) {
            $response['meta'] = $meta;
        }
        
        return rest_ensure_response( $response );
    }

    /**
     * Return an error response.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     * @param int    $status HTTP status code.
     * @param mixed  $data Additional error data.
     * @return WP_Error
     */
    private function error_response( $code, $message, $status = 400, $data = null ) {
        $error_data = array( 'status' => $status );
        
        if ( $data ) {
            $error_data['data'] = $data;
        }
        
        return new WP_Error( $code, $message, $error_data );
    }
}