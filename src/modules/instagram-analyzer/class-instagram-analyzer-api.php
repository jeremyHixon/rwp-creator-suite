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
        
        // Data migration endpoints
        add_action( 'wp_ajax_rwp_migrate_instagram_data', array( $this, 'migrate_data' ) );
        
        // Analysis data management
        add_action( 'wp_ajax_rwp_save_instagram_analysis', array( $this, 'save_analysis' ) );
        add_action( 'wp_ajax_rwp_get_instagram_analysis', array( $this, 'get_analysis' ) );
        
        // User preferences
        add_action( 'wp_ajax_rwp_save_instagram_preferences', array( $this, 'save_preferences' ) );
        add_action( 'wp_ajax_rwp_get_instagram_preferences', array( $this, 'get_preferences' ) );
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
     * Migrate data from guest to user account.
     */
    public function migrate_data() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'rwp_instagram_analyzer_nonce' ) ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Invalid nonce' ) ) );
        }

        // Check user authentication
        if ( ! is_user_logged_in() ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'User not authenticated' ) ) );
        }

        $user_id = get_current_user_id();
        $data_type = sanitize_text_field( $_POST['data_type'] );
        $data = wp_unslash( $_POST['data'] );

        switch ( $data_type ) {
            case 'analysis':
                $result = $this->migrate_analysis_data( $user_id, $data );
                break;
            
            case 'whitelist':
                $result = $this->migrate_whitelist_data( $user_id, $data );
                break;
            
            case 'preferences':
                $result = $this->migrate_preferences_data( $user_id, $data );
                break;
            
            default:
                wp_die( json_encode( array( 'success' => false, 'data' => 'Invalid data type' ) ) );
        }

        if ( $result ) {
            wp_die( json_encode( array( 'success' => true, 'data' => 'Data migrated successfully' ) ) );
        } else {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Failed to migrate data' ) ) );
        }
    }

    /**
     * Save Instagram analysis data.
     */
    public function save_analysis() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'rwp_instagram_analyzer_nonce' ) ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Invalid nonce' ) ) );
        }

        // Check user authentication
        if ( ! is_user_logged_in() ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'User not authenticated' ) ) );
        }

        $user_id = get_current_user_id();
        $analysis_data = wp_unslash( $_POST['analysis_data'] );
        
        $result = $this->migrate_analysis_data( $user_id, $analysis_data );
        
        if ( $result ) {
            wp_die( json_encode( array( 'success' => true, 'data' => 'Analysis data saved' ) ) );
        } else {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Failed to save analysis data' ) ) );
        }
    }

    /**
     * Get Instagram analysis data.
     */
    public function get_analysis() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'rwp_instagram_analyzer_nonce' ) ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Invalid nonce' ) ) );
        }

        // Check user authentication
        if ( ! is_user_logged_in() ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'User not authenticated' ) ) );
        }

        $user_id = get_current_user_id();
        $analysis_data = get_user_meta( $user_id, 'instagram_analyzer_data', true );
        
        wp_die( json_encode( array( 'success' => true, 'data' => $analysis_data ) ) );
    }

    /**
     * Save user preferences.
     */
    public function save_preferences() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'rwp_instagram_analyzer_nonce' ) ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Invalid nonce' ) ) );
        }

        // Check user authentication
        if ( ! is_user_logged_in() ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'User not authenticated' ) ) );
        }

        $user_id = get_current_user_id();
        $preferences_data = wp_unslash( $_POST['preferences'] );
        
        $result = $this->migrate_preferences_data( $user_id, $preferences_data );
        
        if ( $result ) {
            wp_die( json_encode( array( 'success' => true, 'data' => 'Preferences saved' ) ) );
        } else {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Failed to save preferences' ) ) );
        }
    }

    /**
     * Get user preferences.
     */
    public function get_preferences() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'rwp_instagram_analyzer_nonce' ) ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'Invalid nonce' ) ) );
        }

        // Check user authentication
        if ( ! is_user_logged_in() ) {
            wp_die( json_encode( array( 'success' => false, 'data' => 'User not authenticated' ) ) );
        }

        $user_id = get_current_user_id();
        $preferences = get_user_meta( $user_id, 'instagram_analyzer_preferences', true );
        
        if ( ! is_array( $preferences ) ) {
            $preferences = array(
                'showPreviewImages' => true,
                'itemsPerPage' => 20,
                'sortOrder' => 'username',
                'theme' => 'light'
            );
        }

        wp_die( json_encode( array( 'success' => true, 'data' => $preferences ) ) );
    }

    /**
     * Migrate analysis data.
     */
    private function migrate_analysis_data( $user_id, $data_json ) {
        $analysis_data = json_decode( $data_json, true );
        
        if ( ! is_array( $analysis_data ) ) {
            return false;
        }

        // Sanitize the analysis data
        $sanitized_data = $this->sanitize_analysis_data( $analysis_data );
        
        // Add timestamp and user info
        $sanitized_data['migrated_at'] = current_time( 'timestamp' );
        $sanitized_data['user_id'] = $user_id;
        
        return update_user_meta( $user_id, 'instagram_analyzer_data', $sanitized_data );
    }

    /**
     * Migrate whitelist data.
     */
    private function migrate_whitelist_data( $user_id, $data_json ) {
        $whitelist = json_decode( $data_json, true );
        
        if ( ! is_array( $whitelist ) ) {
            return false;
        }

        // Sanitize usernames
        $sanitized_whitelist = array();
        foreach ( $whitelist as $username ) {
            $clean_username = $this->sanitize_instagram_username( $username );
            if ( $clean_username ) {
                $sanitized_whitelist[] = $clean_username;
            }
        }

        return update_user_meta( $user_id, 'instagram_analyzer_whitelist', $sanitized_whitelist );
    }

    /**
     * Migrate preferences data.
     */
    private function migrate_preferences_data( $user_id, $data_json ) {
        $preferences = json_decode( $data_json, true );
        
        if ( ! is_array( $preferences ) ) {
            return false;
        }

        // Sanitize preferences
        $sanitized_preferences = $this->sanitize_preferences( $preferences );
        
        return update_user_meta( $user_id, 'instagram_analyzer_preferences', $sanitized_preferences );
    }

    /**
     * Sanitize analysis data.
     */
    private function sanitize_analysis_data( $data ) {
        $sanitized = array();
        
        // Sanitize followers
        if ( isset( $data['followers'] ) && is_array( $data['followers'] ) ) {
            $sanitized['followers'] = $this->sanitize_account_list( $data['followers'] );
        }
        
        // Sanitize following
        if ( isset( $data['following'] ) && is_array( $data['following'] ) ) {
            $sanitized['following'] = $this->sanitize_account_list( $data['following'] );
        }
        
        // Sanitize notFollowingBack
        if ( isset( $data['notFollowingBack'] ) && is_array( $data['notFollowingBack'] ) ) {
            $sanitized['notFollowingBack'] = $this->sanitize_account_list( $data['notFollowingBack'] );
        }
        
        // Sanitize stats
        if ( isset( $data['stats'] ) && is_array( $data['stats'] ) ) {
            $sanitized['stats'] = array(
                'totalFollowers' => intval( $data['stats']['totalFollowers'] ?? 0 ),
                'totalFollowing' => intval( $data['stats']['totalFollowing'] ?? 0 ),
                'notFollowingBackCount' => intval( $data['stats']['notFollowingBackCount'] ?? 0 ),
                'mutualCount' => intval( $data['stats']['mutualCount'] ?? 0 ),
                'followerToFollowingRatio' => floatval( $data['stats']['followerToFollowingRatio'] ?? 0 )
            );
        }
        
        return $sanitized;
    }

    /**
     * Sanitize account list.
     */
    private function sanitize_account_list( $accounts ) {
        $sanitized = array();
        
        foreach ( $accounts as $account ) {
            if ( is_array( $account ) ) {
                $clean_account = array();
                
                if ( isset( $account['username'] ) ) {
                    $clean_account['username'] = $this->sanitize_instagram_username( $account['username'] );
                }
                
                if ( isset( $account['profileUrl'] ) ) {
                    $clean_account['profileUrl'] = esc_url_raw( $account['profileUrl'] );
                }
                
                if ( isset( $account['timestamp'] ) ) {
                    $clean_account['timestamp'] = sanitize_text_field( $account['timestamp'] );
                }
                
                if ( ! empty( $clean_account['username'] ) ) {
                    $sanitized[] = $clean_account;
                }
            }
        }
        
        return $sanitized;
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

    /**
     * Sanitize preferences.
     */
    private function sanitize_preferences( $preferences ) {
        $sanitized = array();
        
        $sanitized['showPreviewImages'] = isset( $preferences['showPreviewImages'] ) 
            ? (bool) $preferences['showPreviewImages'] : true;
        
        $sanitized['itemsPerPage'] = isset( $preferences['itemsPerPage'] ) 
            ? min( max( intval( $preferences['itemsPerPage'] ), 10 ), 100 ) : 20;
        
        $allowed_sort_orders = array( 'username', 'newest', 'oldest' );
        $sanitized['sortOrder'] = isset( $preferences['sortOrder'] ) 
            && in_array( $preferences['sortOrder'], $allowed_sort_orders ) 
            ? $preferences['sortOrder'] : 'username';
        
        $sanitized['theme'] = isset( $preferences['theme'] ) 
            && in_array( $preferences['theme'], array( 'light', 'dark' ) ) 
            ? $preferences['theme'] : 'light';
        
        if ( isset( $preferences['lastSearch'] ) ) {
            $sanitized['lastSearch'] = sanitize_text_field( $preferences['lastSearch'] );
        }
        
        return $sanitized;
    }
}