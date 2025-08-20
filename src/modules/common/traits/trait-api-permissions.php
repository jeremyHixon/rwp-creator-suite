<?php
/**
 * API Permissions Trait
 * 
 * Provides standardized permission checking methods for REST API endpoints.
 * Ensures consistent permission validation across all Creator Suite APIs.
 */

defined( 'ABSPATH' ) || exit;

trait RWP_Creator_Suite_API_Permissions_Trait {

    /**
     * Check if user is logged in with valid nonce.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_logged_in_with_nonce( $request ) {
        $login_check = $this->check_user_logged_in();
        if ( is_wp_error( $login_check ) ) {
            return $login_check;
        }

        return $this->verify_nonce_permission( $request );
    }

    /**
     * Check admin permission.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_admin_permission( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'insufficient_permissions',
                __( 'You do not have permission to access this resource.', 'rwp-creator-suite' ),
                array( 'status' => 403 )
            );
        }

        return $this->verify_nonce_permission( $request );
    }

    /**
     * Check user consent for data processing.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_user_consent( $request ) {
        $login_check = $this->check_logged_in_with_nonce( $request );
        if ( is_wp_error( $login_check ) ) {
            return $login_check;
        }

        $user_id = get_current_user_id();
        $consent = get_user_meta( $user_id, 'rwp_creator_suite_consent', true );
        
        if ( empty( $consent ) || 'yes' !== $consent ) {
            return new WP_Error(
                'consent_required',
                __( 'User consent is required for this operation.', 'rwp-creator-suite' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Allow access for both guests and logged-in users.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_guest_or_logged_in( $request ) {
        // Always allow access for this permission type
        return true;
    }

    /**
     * Check if user is logged in.
     *
     * @return bool|WP_Error
     */
    public function check_user_logged_in() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'not_logged_in',
                __( 'You must be logged in to access this resource.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Verify nonce permission for API requests.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function verify_nonce_permission( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        
        if ( empty( $nonce ) ) {
            return new WP_Error(
                'missing_nonce',
                __( 'Missing security token.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }

        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid security token.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Check if current user owns the resource.
     *
     * @param int $resource_user_id User ID that owns the resource.
     * @return bool|WP_Error
     */
    public function check_resource_owner( $resource_user_id ) {
        $current_user_id = get_current_user_id();
        
        if ( ! $current_user_id ) {
            return new WP_Error(
                'not_logged_in',
                __( 'You must be logged in to access this resource.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }

        if ( $current_user_id !== (int) $resource_user_id && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'access_denied',
                __( 'You do not have permission to access this resource.', 'rwp-creator-suite' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }
}