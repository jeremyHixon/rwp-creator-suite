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
                'permission_callback' => array( $this, 'check_registration_enabled' ),
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
                    'advanced_features_consent' => array(
                        'required'          => false,
                        'validate_callback' => 'rest_validate_request_arg',
                        'sanitize_callback' => 'rest_sanitize_boolean',
                        'description'       => 'Consent for advanced analytics features',
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
        try {
            // Verify nonce for security
            $nonce = $request->get_param( 'nonce' );
            if ( $nonce && ! wp_verify_nonce( $nonce, 'rwp_creator_suite_registration' ) ) {
                RWP_Creator_Suite_Error_Logger::log_security_event(
                    'Invalid nonce in registration request',
                    array( 
                        'email' => $request->get_param( 'email' ),
                        'user_agent' => $request->get_header( 'User-Agent' )
                    )
                );
                return new WP_Error(
                    'invalid_nonce',
                    'Security check failed. Please refresh the page and try again.',
                    array( 'status' => 403 )
                );
            }
            
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
                'advanced_features_consent' => $request->get_param( 'advanced_features_consent' ),
                'nonce'       => $request->get_param( 'nonce' ),
            ) );

            if ( is_wp_error( $result ) ) {
                // Log registration errors for debugging
                error_log( 'RWP Creator Suite Registration Error: ' . $result->get_error_message() );
                
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
        } catch ( Exception $e ) {
            // Log unexpected errors
            error_log( 'RWP Creator Suite Registration Exception: ' . $e->getMessage() );
            
            return new WP_Error(
                'registration_exception',
                'An unexpected error occurred during registration. Please try again.',
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Handle user login via REST API.
     *
     * @param WP_REST_Request $request Login request.
     * @return WP_REST_Response|WP_Error Login response.
     */
    public function handle_login( $request ) {
        try {
            // Verify nonce for security
            $nonce = $request->get_param( 'nonce' );
            if ( $nonce && ! wp_verify_nonce( $nonce, 'rwp_creator_suite_login' ) ) {
                RWP_Creator_Suite_Error_Logger::log_security_event(
                    'Invalid nonce in login request',
                    array( 
                        'email' => $request->get_param( 'email' ),
                        'user_agent' => $request->get_header( 'User-Agent' )
                    )
                );
                return new WP_Error(
                    'invalid_nonce',
                    'Security check failed. Please refresh the page and try again.',
                    array( 'status' => 403 )
                );
            }
            $auto_login = new RWP_Creator_Suite_Auto_Login();

            $result = $auto_login->login_user(
                $request->get_param( 'email' ),
                $request->get_param( 'password' ),
                $request->get_param( 'redirect_to' )
            );

            if ( is_wp_error( $result ) ) {
                // Log login errors for security monitoring
                error_log( 'RWP Creator Suite Login Error: ' . $result->get_error_message() );
                
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
        } catch ( Exception $e ) {
            // Log unexpected errors
            error_log( 'RWP Creator Suite Login Exception: ' . $e->getMessage() );
            
            return new WP_Error(
                'login_exception',
                'An unexpected error occurred during login. Please try again.',
                array( 'status' => 500 )
            );
        }
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
     * Enhanced rate limiting check with IP-based protection.
     *
     * @param string $email Email address.
     * @return bool Whether request is within rate limits.
     */
    private function check_rate_limit( $email ) {
        if ( empty( $email ) ) {
            return false;
        }

        $rate_limiter = new RWP_Creator_Suite_Rate_Limiter();
        
        // Check email-based rate limiting
        $email_check = $rate_limiter->check_registration_rate_limit( $email );
        if ( is_wp_error( $email_check ) ) {
            return false;
        }
        
        // Check IP-based rate limiting
        $ip_check = $rate_limiter->check_ip_rate_limit( 'registration', 10, HOUR_IN_SECONDS );
        if ( is_wp_error( $ip_check ) ) {
            return false;
        }
        
        // Detect suspicious patterns
        $current_ip = RWP_Creator_Suite_Network_Utils::get_client_ip();
        if ( $rate_limiter->detect_suspicious_patterns( $current_ip, 'registration' ) ) {
            // Log but don't block immediately
            RWP_Creator_Suite_Error_Logger::log_security_event(
                'Suspicious registration pattern detected',
                array( 
                    'email' => $email,
                    'ip_address' => $current_ip 
                )
            );
        }
        
        // Check for distributed attacks
        $rate_limiter->detect_distributed_attack( 'registration' );

        return true;
    }
    

    /**
     * Check if registration is enabled for the registration endpoint.
     *
     * @return bool Whether registration is enabled.
     */
    public function check_registration_enabled() {
        return (bool) get_option( 'users_can_register', false );
    }

    /**
     * Get nonce for API requests.
     *
     * @param string $action The specific action to create nonce for.
     * @return string Nonce value.
     */
    public static function get_nonce( $action = 'api' ) {
        $valid_actions = array( 'registration', 'login', 'api' );
        $action = in_array( $action, $valid_actions, true ) ? $action : 'api';
        return wp_create_nonce( 'rwp_creator_suite_' . $action );
    }
    
    /**
     * Get registration nonce specifically.
     *
     * @return string Registration nonce value.
     */
    public static function get_registration_nonce() {
        return self::get_nonce( 'registration' );
    }
    
    /**
     * Get login nonce specifically.
     *
     * @return string Login nonce value.
     */
    public static function get_login_nonce() {
        return self::get_nonce( 'login' );
    }
}