<?php
/**
 * Auto Login Class
 *
 * Handles automatic login after user registration.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Auto_Login {

    /**
     * Redirect handler instance.
     *
     * @var RWP_Creator_Suite_Redirect_Handler
     */
    private $redirect_handler;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->redirect_handler = new RWP_Creator_Suite_Redirect_Handler();
    }

    /**
     * Login user after successful registration.
     *
     * @param int    $user_id The user ID to login.
     * @param string $redirect_to Optional redirect URL.
     * @return array|WP_Error Login result or error.
     */
    public function login_user_after_registration( $user_id, $redirect_to = '' ) {
        if ( is_wp_error( $user_id ) || ! $user_id ) {
            return new WP_Error(
                'invalid_user_id',
                'Invalid user ID provided.',
                array( 'status' => 400 )
            );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new WP_Error(
                'user_not_found',
                'User not found.',
                array( 'status' => 404 )
            );
        }

        // Log in the user
        wp_clear_auth_cookie();
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );

        // Update user's last login
        update_user_meta( $user_id, 'rwp_creator_suite_last_login', current_time( 'timestamp' ) );
        
        // Determine redirect URL
        $redirect_url = $this->get_redirect_url( $redirect_to, $user_id );
        
        // Fire action for other plugins
        do_action( 'rwp_creator_suite_user_auto_login', $user_id, $redirect_url );
        
        return array(
            'success'      => true,
            'auto_login'   => true,
            'redirect_url' => $redirect_url,
            'user_id'      => $user_id,
            'username'     => $user->user_login,
        );
    }

    /**
     * Get the appropriate redirect URL after login.
     *
     * @param string $redirect_to Requested redirect URL.
     * @param int    $user_id The logged-in user ID.
     * @return string Final redirect URL.
     */
    private function get_redirect_url( $redirect_to, $user_id ) {
        // Start with provided redirect_to
        $redirect_url = $redirect_to;

        // If no redirect_to provided, try to get stored URL
        if ( empty( $redirect_url ) ) {
            $redirect_url = $this->redirect_handler->get_stored_redirect_url();
        }

        // Apply filter for customization
        $redirect_url = apply_filters( 'rwp_creator_suite_registration_redirect_url', $redirect_url, $user_id );

        // Validate and sanitize the URL
        if ( ! $this->redirect_handler->is_valid_redirect_url( $redirect_url ) ) {
            $redirect_url = home_url();
        }

        return esc_url_raw( $redirect_url );
    }

    /**
     * Login existing user (not after registration).
     *
     * @param string $email User's email address.
     * @param string $password User's password.
     * @param string $redirect_to Optional redirect URL.
     * @return array|WP_Error Login result or error.
     */
    public function login_user( $email, $password, $redirect_to = '' ) {
        $email = sanitize_email( $email );
        $password = sanitize_text_field( $password );

        if ( ! is_email( $email ) ) {
            return new WP_Error(
                'invalid_email',
                'Please enter a valid email address.',
                array( 'status' => 400 )
            );
        }

        $user = wp_authenticate( $email, $password );

        if ( is_wp_error( $user ) ) {
            return $user;
        }

        // Log in the user
        wp_clear_auth_cookie();
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        // Update user's last login
        update_user_meta( $user->ID, 'rwp_creator_suite_last_login', current_time( 'timestamp' ) );

        $redirect_url = $this->get_redirect_url( $redirect_to, $user->ID );

        do_action( 'rwp_creator_suite_user_login', $user->ID, $redirect_url );

        return array(
            'success'      => true,
            'redirect_url' => $redirect_url,
            'user_id'      => $user->ID,
            'username'     => $user->user_login,
        );
    }
}