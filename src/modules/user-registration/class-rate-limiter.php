<?php
/**
 * Rate Limiter Class
 *
 * Handles rate limiting for registration attempts.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Rate_Limiter {

    /**
     * Check registration rate limit for an email.
     *
     * @param string $email Email address to check.
     * @return bool|WP_Error True if within limits, WP_Error if exceeded.
     */
    public function check_registration_rate_limit( $email ) {
        // Bypass rate limiting in development
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return true;
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            return new WP_Error(
                'invalid_email',
                'Invalid email address.',
                array( 'status' => 400 )
            );
        }

        $transient_key = 'rwp_creator_suite_reg_' . md5( $email );
        $attempts = get_transient( $transient_key );

        if ( false === $attempts ) {
            $attempts = 0;
        }

        // Allow 3 attempts per hour
        if ( $attempts >= 3 ) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many registration attempts. Please try again later.',
                array( 'status' => 429 )
            );
        }

        // Increment counter
        set_transient( $transient_key, $attempts + 1, HOUR_IN_SECONDS );

        return true;
    }

    /**
     * Check login rate limit for an email.
     *
     * @param string $email Email address to check.
     * @return bool|WP_Error True if within limits, WP_Error if exceeded.
     */
    public function check_login_rate_limit( $email ) {
        // Bypass rate limiting in development
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return true;
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            return new WP_Error(
                'invalid_email',
                'Invalid email address.',
                array( 'status' => 400 )
            );
        }

        $transient_key = 'rwp_creator_suite_login_' . md5( $email );
        $attempts = get_transient( $transient_key );

        if ( false === $attempts ) {
            $attempts = 0;
        }

        // Allow 5 login attempts per 15 minutes
        if ( $attempts >= 5 ) {
            return new WP_Error(
                'login_rate_limit_exceeded',
                'Too many login attempts. Please try again in 15 minutes.',
                array( 'status' => 429 )
            );
        }

        // Increment counter
        set_transient( $transient_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );

        return true;
    }

    /**
     * Check general rate limit by IP address with enhanced brute force protection.
     *
     * @param string $action Action being rate limited.
     * @param int    $limit Maximum attempts allowed.
     * @param int    $window Time window in seconds.
     * @return bool|WP_Error True if within limits, WP_Error if exceeded.
     */
    public function check_ip_rate_limit( $action, $limit = 10, $window = HOUR_IN_SECONDS ) {
        // Bypass rate limiting in development
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return true;
        }

        $ip_address = RWP_Creator_Suite_Network_Utils::get_client_ip();
        
        if ( empty( $ip_address ) ) {
            return true; // Allow if we can't determine IP
        }

        // Check if IP is whitelisted
        if ( $this->is_ip_whitelisted( $ip_address ) ) {
            return true;
        }

        // Check if IP is temporarily banned
        $ban_key = "rwp_creator_suite_banned_ip_" . md5( $ip_address );
        if ( get_transient( $ban_key ) ) {
            RWP_Creator_Suite_Error_Logger::log_security_event(
                'Blocked request from banned IP',
                array( 
                    'ip_address' => $ip_address,
                    'action' => $action,
                    'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : 'unknown'
                )
            );
            
            return new WP_Error(
                'ip_banned',
                'Your IP address has been temporarily banned due to suspicious activity.',
                array( 'status' => 403 )
            );
        }

        $transient_key = "rwp_creator_suite_{$action}_ip_" . md5( $ip_address );
        $attempts_data = get_transient( $transient_key );

        if ( false === $attempts_data ) {
            $attempts_data = array(
                'count' => 0,
                'first_attempt' => time(),
                'last_attempt' => time(),
            );
        }

        $attempts_data['count']++;
        $attempts_data['last_attempt'] = time();

        if ( $attempts_data['count'] >= $limit ) {
            // Progressive blocking: longer bans for repeat offenders
            $previous_bans = get_transient( "rwp_creator_suite_ban_history_" . md5( $ip_address ) ) ?: 0;
            $ban_duration = $this->calculate_ban_duration( $previous_bans );
            
            // Set ban
            set_transient( $ban_key, array(
                'banned_at' => time(),
                'reason' => "Exceeded rate limit for action: {$action}",
                'attempts' => $attempts_data['count'],
                'ban_count' => $previous_bans + 1,
            ), $ban_duration );
            
            // Update ban history
            set_transient( "rwp_creator_suite_ban_history_" . md5( $ip_address ), $previous_bans + 1, DAY_IN_SECONDS );
            
            // Log security event
            RWP_Creator_Suite_Error_Logger::log_security_event(
                'IP address banned for rate limit violation',
                array( 
                    'ip_address' => $ip_address,
                    'action' => $action,
                    'attempts' => $attempts_data['count'],
                    'ban_duration' => $ban_duration,
                    'previous_bans' => $previous_bans,
                    'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : 'unknown',
                    'referer' => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( $_SERVER['HTTP_REFERER'] ) : 'unknown'
                )
            );
            
            return new WP_Error(
                'ip_rate_limit_exceeded',
                'Too many attempts from your IP address. You have been temporarily banned.',
                array( 'status' => 429, 'ban_duration' => $ban_duration )
            );
        }

        // Increment counter
        set_transient( $transient_key, $attempts_data, $window );

        // Log suspicious activity if approaching limit
        if ( $attempts_data['count'] >= ( $limit * 0.8 ) ) {
            RWP_Creator_Suite_Error_Logger::log_security_event(
                'High rate limit usage detected',
                array( 
                    'ip_address' => $ip_address,
                    'action' => $action,
                    'attempts' => $attempts_data['count'],
                    'limit' => $limit,
                    'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : 'unknown'
                )
            );
        }

        return true;
    }

    /**
     * Reset rate limit for an email.
     *
     * @param string $email Email address.
     * @param string $type Type of rate limit (registration, login).
     */
    public function reset_rate_limit( $email, $type = 'registration' ) {
        if ( empty( $email ) || ! is_email( $email ) ) {
            return;
        }

        $prefix = $type === 'login' ? 'login' : 'reg';
        $transient_key = "rwp_creator_suite_{$prefix}_" . md5( $email );
        
        delete_transient( $transient_key );
    }


    /**
     * Check if IP address is whitelisted.
     *
     * @param string $ip_address IP address to check.
     * @return bool Whether IP is whitelisted.
     */
    public function is_ip_whitelisted( $ip_address ) {
        $whitelisted_ips = apply_filters( 'rwp_creator_suite_whitelisted_ips', array() );
        
        return in_array( $ip_address, $whitelisted_ips, true );
    }

    /**
     * Calculate progressive ban duration based on previous bans.
     *
     * @param int $previous_bans Number of previous bans.
     * @return int Ban duration in seconds.
     */
    private function calculate_ban_duration( $previous_bans ) {
        $base_duration = 15 * MINUTE_IN_SECONDS; // 15 minutes base
        
        switch ( $previous_bans ) {
            case 0:
                return $base_duration; // 15 minutes
            case 1:
                return $base_duration * 4; // 1 hour
            case 2:
                return $base_duration * 16; // 4 hours
            case 3:
                return $base_duration * 48; // 12 hours
            default:
                return DAY_IN_SECONDS; // 24 hours for repeat offenders
        }
    }

    /**
     * Check for suspicious patterns in requests.
     *
     * @param string $ip_address IP address to check.
     * @param string $action Action being performed.
     * @return bool Whether suspicious patterns are detected.
     */
    public function detect_suspicious_patterns( $ip_address, $action ) {
        if ( empty( $ip_address ) ) {
            return false;
        }

        // Check for rapid requests from same IP
        $rapid_key = "rwp_creator_suite_rapid_" . md5( $ip_address );
        $rapid_requests = get_transient( $rapid_key ) ?: array();
        
        $current_time = time();
        $rapid_requests[] = $current_time;
        
        // Keep only requests from last 60 seconds
        $rapid_requests = array_filter( $rapid_requests, function( $timestamp ) use ( $current_time ) {
            return ( $current_time - $timestamp ) <= 60;
        });
        
        set_transient( $rapid_key, $rapid_requests, MINUTE_IN_SECONDS );
        
        // Suspicious if more than 10 requests per minute
        if ( count( $rapid_requests ) > 10 ) {
            RWP_Creator_Suite_Error_Logger::log_security_event(
                'Rapid requests detected - possible bot activity',
                array( 
                    'ip_address' => $ip_address,
                    'action' => $action,
                    'requests_per_minute' => count( $rapid_requests ),
                    'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : 'unknown'
                )
            );
            return true;
        }

        return false;
    }

    /**
     * Check for distributed attack patterns.
     *
     * @param string $action Action being performed.
     * @return bool Whether distributed attack is detected.
     */
    public function detect_distributed_attack( $action ) {
        $attack_key = "rwp_creator_suite_distributed_{$action}";
        $attack_data = get_transient( $attack_key ) ?: array(
            'unique_ips' => array(),
            'total_attempts' => 0,
            'window_start' => time(),
        );

        $current_ip = RWP_Creator_Suite_Network_Utils::get_client_ip();
        if ( ! empty( $current_ip ) && ! in_array( $current_ip, $attack_data['unique_ips'], true ) ) {
            $attack_data['unique_ips'][] = $current_ip;
        }
        
        $attack_data['total_attempts']++;
        
        set_transient( $attack_key, $attack_data, HOUR_IN_SECONDS );
        
        // Suspicious if more than 20 unique IPs and 100 attempts in an hour
        if ( count( $attack_data['unique_ips'] ) > 20 && $attack_data['total_attempts'] > 100 ) {
            RWP_Creator_Suite_Error_Logger::log_security_event(
                'Distributed attack pattern detected',
                array( 
                    'action' => $action,
                    'unique_ips' => count( $attack_data['unique_ips'] ),
                    'total_attempts' => $attack_data['total_attempts'],
                    'time_window' => time() - $attack_data['window_start']
                )
            );
            return true;
        }

        return false;
    }

    /**
     * Get comprehensive ban information for an IP.
     *
     * @param string $ip_address IP address to check.
     * @return array|null Ban information or null if not banned.
     */
    public function get_ban_info( $ip_address ) {
        if ( empty( $ip_address ) ) {
            return null;
        }

        $ban_key = "rwp_creator_suite_banned_ip_" . md5( $ip_address );
        return get_transient( $ban_key );
    }

    /**
     * Manually ban an IP address.
     *
     * @param string $ip_address IP address to ban.
     * @param int    $duration Ban duration in seconds.
     * @param string $reason Reason for ban.
     * @return bool Whether ban was successful.
     */
    public function manual_ban_ip( $ip_address, $duration = DAY_IN_SECONDS, $reason = 'Manual ban' ) {
        if ( empty( $ip_address ) || ! filter_var( $ip_address, FILTER_VALIDATE_IP ) ) {
            return false;
        }

        $ban_key = "rwp_creator_suite_banned_ip_" . md5( $ip_address );
        $ban_data = array(
            'banned_at' => time(),
            'reason' => $reason,
            'ban_type' => 'manual',
            'banned_by' => get_current_user_id(),
        );

        set_transient( $ban_key, $ban_data, $duration );
        
        RWP_Creator_Suite_Error_Logger::log_security_event(
            'IP address manually banned',
            array( 
                'ip_address' => $ip_address,
                'duration' => $duration,
                'reason' => $reason,
                'banned_by' => get_current_user_id()
            )
        );

        return true;
    }

    /**
     * Unban an IP address.
     *
     * @param string $ip_address IP address to unban.
     * @return bool Whether unban was successful.
     */
    public function unban_ip( $ip_address ) {
        if ( empty( $ip_address ) ) {
            return false;
        }

        $ban_key = "rwp_creator_suite_banned_ip_" . md5( $ip_address );
        $ban_info = get_transient( $ban_key );
        
        if ( $ban_info ) {
            delete_transient( $ban_key );
            
            RWP_Creator_Suite_Error_Logger::log_security_event(
                'IP address unbanned',
                array( 
                    'ip_address' => $ip_address,
                    'unbanned_by' => get_current_user_id(),
                    'original_ban_reason' => isset( $ban_info['reason'] ) ? $ban_info['reason'] : 'unknown'
                )
            );
            
            return true;
        }

        return false;
    }

    /**
     * Get rate limit status for debugging.
     *
     * @param string $email Email address.
     * @return array Rate limit status information.
     */
    public function get_rate_limit_status( $email ) {
        if ( empty( $email ) || ! is_email( $email ) ) {
            return array();
        }

        $reg_key = 'rwp_creator_suite_reg_' . md5( $email );
        $login_key = 'rwp_creator_suite_login_' . md5( $email );
        
        return array(
            'registration_attempts' => get_transient( $reg_key ) ?: 0,
            'login_attempts'       => get_transient( $login_key ) ?: 0,
            'registration_remaining' => max( 0, 3 - ( get_transient( $reg_key ) ?: 0 ) ),
            'login_remaining'       => max( 0, 5 - ( get_transient( $login_key ) ?: 0 ) ),
        );
    }

    /**
     * Clean up old rate limiting data.
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // Clean up old transients (WordPress doesn't auto-clean expired transients)
        $expired_transients = $wpdb->get_col( 
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                AND option_name LIKE %s 
                ORDER BY option_id LIMIT 100",
                '_transient_timeout_rwp_creator_suite_%',
                '%'
            )
        );
        
        $current_time = time();
        foreach ( $expired_transients as $timeout_key ) {
            $timeout_value = get_option( $timeout_key );
            if ( $timeout_value && $timeout_value < $current_time ) {
                $transient_key = str_replace( '_transient_timeout_', '_transient_', $timeout_key );
                delete_option( $timeout_key );
                delete_option( $transient_key );
            }
        }
    }
}