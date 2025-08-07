<?php
/**
 * Registration API Class
 *
 * Handles REST API endpoints for user registration.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Registration_API {

    /**
     * API namespace.
     *
     * @var string
     */
    private $namespace = 'rwp-creator-suite/v1';

    /**
     * User registration instance.
     *
     * @var RWP_Creator_Suite_User_Registration
     */
    private $user_registration;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->user_registration = new RWP_Creator_Suite_User_Registration();
    }

    /**
     * Initialize API endpoints.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Registration endpoint
        register_rest_route(
            $this->namespace,
            '/auth/register',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_registration' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'email' => array(
                        'required'          => true,
                        'validate_callback' => 'is_email',
                        'sanitize_callback' => 'sanitize_email',
                        'description'       => 'User email address',
                    ),
                    'redirect_to' => array(
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                        'description'       => 'URL to redirect to after registration',
                    ),
                    'nonce' => array(
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'Security nonce',
                    ),
                ),
            )
        );

        // Login endpoint
        register_rest_route(
            $this->namespace,
            '/auth/login',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_login' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'email' => array(
                        'required'          => true,
                        'validate_callback' => 'is_email',
                        'sanitize_callback' => 'sanitize_email',
                        'description'       => 'User email address',
                    ),
                    'password' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'User password',
                    ),
                    'redirect_to' => array(
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                        'description'       => 'URL to redirect to after login',
                    ),
                    'nonce' => array(
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'Security nonce',
                    ),
                ),
            )
        );

        // Get redirect URL endpoint
        register_rest_route(
            $this->namespace,
            '/auth/redirect',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_redirect_url' ),
                'permission_callback' => 'is_user_logged_in',
                'args'                => array(
                    'default' => array(
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                        'description'       => 'Default URL if no stored redirect',
                    ),
                ),
            )
        );

        // Check registration status endpoint
        register_rest_route(
            $this->namespace,
            '/auth/status',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_auth_status' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Handle user registration via REST API.
     *
     * @param WP_REST_Request $request Registration request.
     * @return WP_REST_Response|WP_Error Registration response.
     */
    public function handle_registration( $request ) {
        // Rate limiting check
        if ( ! $this->check_rate_limit( $request->get_param( 'email' ) ) ) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many registration attempts. Please try again later.',
                array( 'status' => 429 )
            );
        }

        $result = $this->user_registration->handle_registration_request( array(
            'email'       => $request->get_param( 'email' ),
            'redirect_to' => $request->get_param( 'redirect_to' ),
            'nonce'       => $request->get_param( 'nonce' ),
        ) );

        if ( is_wp_error( $result ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Registration successful. You are now logged in.',
            'data'    => $result,
        ) );
    }

    /**
     * Handle user login via REST API.
     *
     * @param WP_REST_Request $request Login request.
     * @return WP_REST_Response|WP_Error Login response.
     */
    public function handle_login( $request ) {
        $auto_login = new RWP_Creator_Suite_Auto_Login();

        $result = $auto_login->login_user(
            $request->get_param( 'email' ),
            $request->get_param( 'password' ),
            $request->get_param( 'redirect_to' )
        );

        if ( is_wp_error( $result ) ) {
            return rest_ensure_response( array(
                'success' => false,
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Login successful.',
            'data'    => $result,
        ) );
    }

    /**
     * Get redirect URL for authenticated user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Redirect URL response.
     */
    public function get_redirect_url( $request ) {
        $redirect_handler = new RWP_Creator_Suite_Redirect_Handler();
        $default = $request->get_param( 'default' );
        
        $redirect_url = $redirect_handler->get_stored_redirect_url( $default );

        return rest_ensure_response( array(
            'success'      => true,
            'redirect_url' => $redirect_url,
        ) );
    }

    /**
     * Get authentication status.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Auth status response.
     */
    public function get_auth_status( $request ) {
        $is_logged_in = is_user_logged_in();
        $data = array(
            'logged_in'        => $is_logged_in,
            'registration_enabled' => get_option( 'users_can_register', false ),
        );

        if ( $is_logged_in ) {
            $current_user = wp_get_current_user();
            $data['user'] = array(
                'id'       => $current_user->ID,
                'username' => $current_user->user_login,
                'email'    => $current_user->user_email,
                'roles'    => $current_user->roles,
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'data'    => $data,
        ) );
    }

    /**
     * Basic rate limiting check.
     *
     * @param string $email Email address.
     * @return bool Whether request is within rate limits.
     */
    private function check_rate_limit( $email ) {
        if ( empty( $email ) ) {
            return false;
        }

        $transient_key = 'rwp_creator_suite_reg_' . md5( $email );
        $attempts = get_transient( $transient_key );

        if ( $attempts >= 5 ) {
            return false;
        }

        // Increment counter
        set_transient( $transient_key, $attempts + 1, HOUR_IN_SECONDS );

        return true;
    }

    /**
     * Get nonce for API requests.
     *
     * @return string Nonce value.
     */
    public static function get_nonce() {
        return wp_create_nonce( 'rwp_creator_suite_api' );
    }
}