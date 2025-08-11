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
     * Check general rate limit by IP address.
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

        $ip_address = $this->get_client_ip();
        
        if ( empty( $ip_address ) ) {
            return true; // Allow if we can't determine IP
        }

        $transient_key = "rwp_creator_suite_{$action}_ip_" . md5( $ip_address );
        $attempts = get_transient( $transient_key );

        if ( false === $attempts ) {
            $attempts = 0;
        }

        if ( $attempts >= $limit ) {
            return new WP_Error(
                'ip_rate_limit_exceeded',
                'Too many attempts from your IP address. Please try again later.',
                array( 'status' => 429 )
            );
        }

        // Increment counter
        set_transient( $transient_key, $attempts + 1, $window );

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
     * Get client IP address.
     *
     * @return string Client IP address.
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                $ip_list = explode( ',', sanitize_text_field( $_SERVER[ $key ] ) );
                $ip = trim( $ip_list[0] );
                
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
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
}