<?php
/**
 * API Key Security Manager
 * 
 * Handles secure storage and retrieval of API keys with encryption,
 * validation, and audit logging.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Key_Manager {

    /**
     * Encrypt an API key for secure storage.
     */
    private function encrypt_api_key( $key ) {
        if ( empty( $key ) ) {
            return '';
        }
        
        // Check if OpenSSL is available
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            // Fallback to base64 encoding with warning
            error_log( 'RWP Creator Suite: OpenSSL not available, using fallback encoding' );
            return 'fallback:' . base64_encode( $key );
        }
        
        // Use WordPress auth salts for encryption key
        $salt = wp_salt( 'auth' );
        $iv = openssl_random_pseudo_bytes( 16 );
        
        $encrypted = openssl_encrypt( $key, 'AES-256-CBC', $salt, 0, $iv );
        
        if ( false === $encrypted ) {
            return new WP_Error( 'encryption_failed', __( 'Failed to encrypt API key', 'rwp-creator-suite' ) );
        }
        
        // Return base64 encoded IV + encrypted data
        return 'encrypted:' . base64_encode( $iv . $encrypted );
    }
    
    /**
     * Decrypt an API key from secure storage.
     */
    private function decrypt_api_key( $encrypted_key ) {
        if ( empty( $encrypted_key ) ) {
            return '';
        }
        
        // Handle fallback encoding
        if ( strpos( $encrypted_key, 'fallback:' ) === 0 ) {
            return base64_decode( substr( $encrypted_key, 9 ) );
        }
        
        // Handle encrypted data
        if ( strpos( $encrypted_key, 'encrypted:' ) === 0 ) {
            $data = base64_decode( substr( $encrypted_key, 10 ) );
            if ( false === $data ) {
                return '';
            }
            
            $iv = substr( $data, 0, 16 );
            $encrypted = substr( $data, 16 );
            
            $salt = wp_salt( 'auth' );
            $decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $salt, 0, $iv );
            
            return $decrypted !== false ? $decrypted : '';
        }
        
        // Legacy unencrypted key - should be migrated
        return $encrypted_key;
    }
    
    /**
     * Get API key securely with environment variable fallback.
     */
    public function get_api_key( $provider = 'openai' ) {
        // Try environment variable first (most secure)
        $env_var = strtoupper( "RWP_{$provider}_API_KEY" );
        if ( defined( $env_var ) ) {
            $env_key = constant( $env_var );
            if ( ! empty( $env_key ) ) {
                $this->log_key_access( "env_access_{$provider}" );
                return $env_key;
            }
        }
        
        // Fallback to encrypted database storage
        $option_key = "rwp_creator_suite_{$provider}_api_key_encrypted";
        $encrypted_key = get_option( $option_key );
        
        if ( empty( $encrypted_key ) ) {
            // Check for legacy unencrypted key
            $legacy_key = get_option( "rwp_creator_suite_{$provider}_api_key" );
            if ( ! empty( $legacy_key ) ) {
                // Migrate to encrypted storage
                $this->save_api_key( $legacy_key, $provider );
                delete_option( "rwp_creator_suite_{$provider}_api_key" );
                $this->log_key_access( "migration_{$provider}" );
                return $legacy_key;
            }
            return '';
        }
        
        $this->log_key_access( "db_access_{$provider}" );
        return $this->decrypt_api_key( $encrypted_key );
    }
    
    /**
     * Save API key with encryption and validation.
     */
    public function save_api_key( $key, $provider = 'openai' ) {
        // Capability check
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'insufficient_permissions', __( 'Insufficient permissions to save API key', 'rwp-creator-suite' ) );
        }
        
        // Validate key format
        $validation_result = $this->validate_api_key_format( $key, $provider );
        if ( is_wp_error( $validation_result ) ) {
            return $validation_result;
        }
        
        // Test key if possible (optional - may cause delays)
        $test_enabled = apply_filters( 'rwp_creator_suite_test_api_key_on_save', false );
        if ( $test_enabled && ! $this->test_api_key( $key, $provider ) ) {
            return new WP_Error( 'invalid_key', __( 'API key failed validation test', 'rwp-creator-suite' ) );
        }
        
        // Encrypt and save
        $encrypted = $this->encrypt_api_key( $key );
        if ( is_wp_error( $encrypted ) ) {
            return $encrypted;
        }
        
        $option_key = "rwp_creator_suite_{$provider}_api_key_encrypted";
        update_option( $option_key, $encrypted );
        
        // Remove any old unencrypted keys
        delete_option( "rwp_creator_suite_{$provider}_api_key" );
        
        $this->log_key_access( "save_{$provider}" );
        
        return true;
    }
    
    /**
     * Delete API key securely.
     */
    public function delete_api_key( $provider = 'openai' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'insufficient_permissions', __( 'Insufficient permissions to delete API key', 'rwp-creator-suite' ) );
        }
        
        delete_option( "rwp_creator_suite_{$provider}_api_key_encrypted" );
        delete_option( "rwp_creator_suite_{$provider}_api_key" ); // Remove legacy if exists
        
        $this->log_key_access( "delete_{$provider}" );
        
        return true;
    }
    
    /**
     * Validate API key format based on provider.
     */
    private function validate_api_key_format( $key, $provider ) {
        $key = sanitize_text_field( $key );
        
        if ( empty( $key ) ) {
            return new WP_Error( 'empty_key', __( 'API key cannot be empty', 'rwp-creator-suite' ) );
        }
        
        switch ( $provider ) {
            case 'openai':
                // OpenAI keys start with 'sk-' and are 51 characters
                if ( ! preg_match( '/^sk-[a-zA-Z0-9]{48}$/', $key ) ) {
                    return new WP_Error( 'invalid_openai_key', __( 'Invalid OpenAI API key format', 'rwp-creator-suite' ) );
                }
                break;
                
            case 'claude':
                // Claude keys start with 'sk-ant-' 
                if ( ! preg_match( '/^sk-ant-[a-zA-Z0-9_-]+$/', $key ) ) {
                    return new WP_Error( 'invalid_claude_key', __( 'Invalid Claude API key format', 'rwp-creator-suite' ) );
                }
                break;
                
            default:
                // Generic validation - at least 16 characters, alphanumeric + common symbols
                if ( strlen( $key ) < 16 || ! preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) ) {
                    return new WP_Error( 'invalid_key_format', __( 'Invalid API key format', 'rwp-creator-suite' ) );
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Test API key validity (optional, can be resource intensive).
     */
    private function test_api_key( $key, $provider ) {
        // This is a basic test - implement provider-specific validation
        switch ( $provider ) {
            case 'openai':
                return $this->test_openai_key( $key );
            case 'claude':
                return $this->test_claude_key( $key );
            default:
                return true; // Skip test for unknown providers
        }
    }
    
    /**
     * Test OpenAI API key.
     */
    private function test_openai_key( $key ) {
        $response = wp_remote_get( 'https://api.openai.com/v1/models', array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        return $response_code === 200;
    }
    
    /**
     * Test Claude API key.
     */
    private function test_claude_key( $key ) {
        // Implement Claude API validation if needed
        return true; // Placeholder
    }
    
    /**
     * Log API key access for audit purposes.
     */
    private function log_key_access( $action ) {
        $log_entry = array(
            'timestamp' => current_time( 'mysql' ),
            'user_id'   => get_current_user_id(),
            'action'    => sanitize_text_field( $action ),
            'ip'        => $this->get_client_ip(),
        );
        
        $audit_log = get_option( 'rwp_api_key_audit', array() );
        $audit_log[] = $log_entry;
        
        // Keep only last 100 entries
        if ( count( $audit_log ) > 100 ) {
            $audit_log = array_slice( $audit_log, -100 );
        }
        
        update_option( 'rwp_api_key_audit', $audit_log );
    }
    
    /**
     * Get client IP address for audit logging.
     */
    private function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ips = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ips[0] );
                
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        $fallback_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return filter_var( $fallback_ip, FILTER_VALIDATE_IP ) ? $fallback_ip : '127.0.0.1';
    }
    
    /**
     * Get audit log for admin review.
     */
    public function get_audit_log() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'insufficient_permissions', __( 'Insufficient permissions to view audit log', 'rwp-creator-suite' ) );
        }
        
        return get_option( 'rwp_api_key_audit', array() );
    }
    
    /**
     * Migrate existing unencrypted keys.
     */
    public function migrate_existing_keys() {
        $providers = array( 'openai', 'claude' );
        
        foreach ( $providers as $provider ) {
            $old_key = get_option( "rwp_creator_suite_{$provider}_api_key" );
            $new_key_exists = get_option( "rwp_creator_suite_{$provider}_api_key_encrypted" );
            
            if ( $old_key && ! $new_key_exists ) {
                $result = $this->save_api_key( $old_key, $provider );
                if ( ! is_wp_error( $result ) ) {
                    delete_option( "rwp_creator_suite_{$provider}_api_key" );
                    $this->log_key_access( "migration_complete_{$provider}" );
                }
            }
        }
    }
}