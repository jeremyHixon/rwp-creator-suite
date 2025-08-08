<?php
/**
 * Instagram Analyzer API
 * 
 * Handles AJAX endpoints for Instagram Analyzer functionality.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Instagram_Analyzer_API {

    /**
     * Initialize the API endpoints.
     */
    public function init() {
        // Whitelist management endpoints
        add_action( 'wp_ajax_rwp_sync_instagram_whitelist', array( $this, 'sync_whitelist' ) );
        add_action( 'wp_ajax_rwp_get_instagram_whitelist', array( $this, 'get_whitelist' ) );
        
        
        
    }

    /**
     * Sync whitelist with server.
     */
    public function sync_whitelist() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'rwp_instagram_analyzer_nonce' ) ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Invalid nonce' ) ) );
        }

        // Check user authentication
        if ( ! is_user_logged_in() ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'User not authenticated' ) ) );
        }

        $user_id = get_current_user_id();
        $whitelist_data = wp_unslash( $_POST['whitelist'] );
        
        // Validate and sanitize whitelist data
        $whitelist = json_decode( $whitelist_data, true );
        if ( ! is_array( $whitelist ) ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Invalid whitelist data' ) ) );
        }

        // Sanitize each username
        $sanitized_whitelist = array();
        foreach ( $whitelist as $username ) {
            $clean_username = $this->sanitize_instagram_username( $username );
            if ( $clean_username ) {
                $sanitized_whitelist[] = $clean_username;
            }
        }

        // Save to user meta
        $result = update_user_meta( $user_id, 'instagram_analyzer_whitelist', $sanitized_whitelist );
        
        if ( $result !== false ) {
            wp_die( json_encode( array( 'success' => true, 'data' => 'Whitelist synchronized' ) ) );
        } else {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Failed to save whitelist' ) ) );
        }
    }

    /**
     * Get user whitelist.
     */
    public function get_whitelist() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'rwp_instagram_analyzer_nonce' ) ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Invalid nonce' ) ) );
        }

        // Check user authentication
        if ( ! is_user_logged_in() ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'User not authenticated' ) ) );
        }

        $user_id = get_current_user_id();
        $whitelist = get_user_meta( $user_id, 'instagram_analyzer_whitelist', true );
        
        if ( ! is_array( $whitelist ) ) {
            $whitelist = array();
        }

        wp_die( json_encode( array( 'success' => true, 'data' => $whitelist ) ) );
    }











    /**
     * Sanitize Instagram username.
     */
    private function sanitize_instagram_username( $username ) {
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