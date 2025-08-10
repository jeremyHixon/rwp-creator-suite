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
        try {
            // Sanitize and verify nonce
            $nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
            if ( ! wp_verify_nonce( $nonce, 'rwp_instagram_analyzer_nonce' ) ) {
                $this->send_json_error( 'Invalid nonce', 403 );
                return;
            }

            // Check user authentication
            if ( ! is_user_logged_in() ) {
                $this->send_json_error( 'User not authenticated', 401 );
                return;
            }

            $user_id = get_current_user_id();
            $whitelist_data = sanitize_textarea_field( wp_unslash( $_POST['whitelist'] ?? '' ) );
            
            // Validate and sanitize whitelist data
            $whitelist = json_decode( $whitelist_data, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                error_log( 'RWP Creator Suite JSON Decode Error: ' . json_last_error_msg() );
                $this->send_json_error( 'Invalid JSON data provided', 400 );
                return;
            }
            
            if ( ! is_array( $whitelist ) ) {
                $this->send_json_error( 'Invalid whitelist data format', 400 );
                return;
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
                $this->send_json_success( 'Whitelist synchronized', $sanitized_whitelist );
            } else {
                error_log( 'RWP Creator Suite: Failed to save whitelist for user ' . $user_id );
                $this->send_json_error( 'Failed to save whitelist', 500 );
            }
        } catch ( Exception $e ) {
            error_log( 'RWP Creator Suite Whitelist Sync Exception: ' . $e->getMessage() );
            $this->send_json_error( 'An unexpected error occurred', 500 );
        }
    }

    /**
     * Get user whitelist.
     */
    public function get_whitelist() {
        // Sanitize and verify nonce
        $nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, 'rwp_instagram_analyzer_nonce' ) ) {
            $this->send_json_error( 'Invalid nonce', 403 );
            return;
        }

        // Check user authentication
        if ( ! is_user_logged_in() ) {
            $this->send_json_error( 'User not authenticated', 401 );
            return;
        }

        $user_id = get_current_user_id();
        $whitelist = get_user_meta( $user_id, 'instagram_analyzer_whitelist', true );
        
        if ( ! is_array( $whitelist ) ) {
            $whitelist = array();
        }

        $this->send_json_success( 'Whitelist retrieved', $whitelist );
    }











    /**
     * Sanitize Instagram username.
     *
     * @param string $username The username to sanitize.
     * @return string|false The sanitized username or false if invalid.
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

    /**
     * Send JSON success response.
     *
     * @param string $message Success message.
     * @param mixed  $data Optional data to include.
     */
    private function send_json_success( $message, $data = null ) {
        $response = array(
            'success' => true,
            'message' => $message,
        );
        
        if ( $data !== null ) {
            $response['data'] = $data;
        }
        
        wp_send_json( $response );
    }

    /**
     * Send JSON error response.
     *
     * @param string $message Error message.
     * @param int    $status_code HTTP status code.
     */
    private function send_json_error( $message, $status_code = 400 ) {
        $response = array(
            'success' => false,
            'message' => $message,
        );
        
        wp_send_json( $response, $status_code );
    }

}