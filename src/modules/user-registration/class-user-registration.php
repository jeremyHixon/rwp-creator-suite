<?php
/**
 * User Registration Class
 *
 * Handles email-only user registration with automatic login.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_User_Registration {

    /**
     * Username generator instance.
     *
     * @var RWP_Creator_Suite_Username_Generator
     */
    private $username_generator;

    /**
     * Auto login handler instance.
     *
     * @var RWP_Creator_Suite_Auto_Login
     */
    private $auto_login;

    /**
     * Rate limiter instance.
     *
     * @var RWP_Creator_Suite_Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->username_generator = new RWP_Creator_Suite_Username_Generator();
        $this->auto_login = new RWP_Creator_Suite_Auto_Login();
        $this->rate_limiter = new RWP_Creator_Suite_Rate_Limiter();
    }

    /**
     * Register a new user with email only.
     *
     * @param string $email The user's email address.
     * @param string $redirect_to Optional redirect URL after registration.
     * @return array|WP_Error Registration result or error.
     */
    public function register_user( $email, $redirect_to = '' ) {
        // Validate email
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return new WP_Error(
                'invalid_email',
                'Please enter a valid email address.',
                array( 'status' => 400 )
            );
        }

        // Check rate limiting
        $rate_check = $this->rate_limiter->check_registration_rate_limit( $email );
        if ( is_wp_error( $rate_check ) ) {
            return $rate_check;
        }

        // Check if email already exists
        if ( email_exists( $email ) ) {
            return new WP_Error(
                'email_exists',
                'An account with this email already exists.',
                array( 'status' => 409 )
            );
        }

        // Check if registration is allowed
        if ( ! get_option( 'users_can_register' ) ) {
            return new WP_Error(
                'registration_disabled',
                'Registration is currently disabled.',
                array( 'status' => 403 )
            );
        }

        // Generate username
        $username = $this->username_generator->generate_from_email( $email );
        if ( is_wp_error( $username ) ) {
            return $username;
        }

        // Fire before registration action
        do_action( 'rwp_creator_suite_before_user_registration', $email, $redirect_to );

        // Generate random password
        $password = wp_generate_password( 12, false );

        // Create user
        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Set user role to subscriber
        $user = new WP_User( $user_id );
        $user->set_role( 'subscriber' );

        // Store registration metadata
        add_user_meta( $user_id, 'rwp_creator_suite_registration_method', 'email_only' );
        add_user_meta( $user_id, 'rwp_creator_suite_auto_login', true );
        add_user_meta( $user_id, 'rwp_creator_suite_original_url', sanitize_text_field( $redirect_to ) );

        // Fire after registration action
        do_action( 'rwp_creator_suite_after_user_registration', $user_id, array(
            'username' => $username,
            'email'    => $email,
            'redirect_to' => $redirect_to,
        ) );

        // Auto-login the user
        $login_result = $this->auto_login->login_user_after_registration( $user_id, $redirect_to );

        if ( is_wp_error( $login_result ) ) {
            return new WP_Error(
                'registration_success_login_failed',
                'Registration successful but login failed. Please try logging in manually.',
                array(
                    'status' => 201,
                    'user_id' => $user_id,
                )
            );
        }

        return array_merge( $login_result, array(
            'user_id' => $user_id,
            'username' => $username,
            'email' => $email,
        ) );
    }

    /**
     * Handle registration via AJAX or form submission.
     *
     * @param array $data Registration data.
     * @return array|WP_Error Registration result.
     */
    public function handle_registration_request( $data ) {
        // Verify nonce if provided
        if ( isset( $data['nonce'] ) ) {
            if ( ! wp_verify_nonce( $data['nonce'], 'rwp_creator_suite_register' ) ) {
                return new WP_Error(
                    'invalid_nonce',
                    'Security check failed.',
                    array( 'status' => 403 )
                );
            }
        }

        $email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
        $redirect_to = isset( $data['redirect_to'] ) ? esc_url_raw( $data['redirect_to'] ) : '';

        return $this->register_user( $email, $redirect_to );
    }
}