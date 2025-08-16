<?php
/**
 * Rate Limiting Trait
 * 
 * Provides shared rate limiting logic for API endpoints.
 * Handles both guest and authenticated user rate limiting.
 */

defined( 'ABSPATH' ) || exit;

trait RWP_Creator_Suite_Rate_Limiting_Trait {

    /**
     * Check rate limit for current user/IP.
     *
     * @param string $feature Feature identifier (e.g., 'ai_generation', 'registration').
     * @param array  $options Rate limiting options.
     * @return bool|WP_Error True if within limits, WP_Error if exceeded.
     */
    public function check_rate_limit( $feature = 'api_request', $options = array() ) {
        $user_id = get_current_user_id();
        $is_guest = ! $user_id;

        // Get identifier for rate limiting
        if ( $is_guest ) {
            $identifier = $this->get_guest_identifier();
            $limit = $this->get_guest_rate_limit( $feature, $options );
        } else {
            $identifier = $user_id;
            $limit = $this->get_user_rate_limit( $user_id, $feature, $options );
        }

        // Get time window (default 1 hour)
        $window = isset( $options['window'] ) ? (int) $options['window'] : HOUR_IN_SECONDS;

        // Check current usage
        $transient_key = $this->get_rate_limit_key( $feature, $identifier );
        $current_usage = get_transient( $transient_key );

        if ( false === $current_usage ) {
            $current_usage = 0;
        }

        // Apply filters for customization
        $limit = apply_filters( 'rwp_creator_suite_rate_limit', $limit, $identifier, $feature, $options );

        if ( $current_usage >= $limit ) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __( 'Rate limit exceeded. You can make %d requests per %s.', 'rwp-creator-suite' ),
                    $limit,
                    $this->format_time_window( $window )
                ),
                array( 
                    'status' => 429,
                    'retry_after' => $this->get_retry_after( $transient_key ),
                    'limit' => $limit,
                    'remaining' => 0,
                    'reset_time' => time() + $this->get_retry_after( $transient_key )
                )
            );
        }

        return true;
    }

    /**
     * Track API usage for rate limiting.
     *
     * @param string $feature Feature identifier.
     * @param int    $usage_count Number of requests to add (default 1).
     * @param array  $options Rate limiting options.
     */
    public function track_usage( $feature = 'api_request', $usage_count = 1, $options = array() ) {
        $user_id = get_current_user_id();
        $is_guest = ! $user_id;

        // Get identifier
        if ( $is_guest ) {
            $identifier = $this->get_guest_identifier();
        } else {
            $identifier = $user_id;
        }

        // Get time window
        $window = isset( $options['window'] ) ? (int) $options['window'] : HOUR_IN_SECONDS;

        // Update usage counter
        $transient_key = $this->get_rate_limit_key( $feature, $identifier );
        $current_usage = get_transient( $transient_key );

        if ( false === $current_usage ) {
            $current_usage = 0;
        }

        $new_usage = $current_usage + $usage_count;
        set_transient( $transient_key, $new_usage, $window );

        // Track detailed statistics for logged-in users
        if ( ! $is_guest ) {
            $this->track_detailed_usage_stats( $user_id, $feature, $usage_count );
        }

        // Fire action for usage tracking
        do_action( 'rwp_creator_suite_usage_tracked', $identifier, $feature, $usage_count, $is_guest );
    }

    /**
     * Get current usage statistics.
     *
     * @param string $feature Feature identifier.
     * @param array  $options Rate limiting options.
     * @return array Usage statistics.
     */
    public function get_usage_stats( $feature = 'api_request', $options = array() ) {
        $user_id = get_current_user_id();
        $is_guest = ! $user_id;

        if ( $is_guest ) {
            $identifier = $this->get_guest_identifier();
            $limit = $this->get_guest_rate_limit( $feature, $options );
        } else {
            $identifier = $user_id;
            $limit = $this->get_user_rate_limit( $user_id, $feature, $options );
        }

        $window = isset( $options['window'] ) ? (int) $options['window'] : HOUR_IN_SECONDS;
        $transient_key = $this->get_rate_limit_key( $feature, $identifier );
        $current_usage = get_transient( $transient_key );

        if ( false === $current_usage ) {
            $current_usage = 0;
        }

        $stats = array(
            'current_usage' => $current_usage,
            'limit' => $limit,
            'remaining' => max( 0, $limit - $current_usage ),
            'window_seconds' => $window,
            'reset_time' => time() + $this->get_retry_after( $transient_key ),
            'is_guest' => $is_guest,
        );

        // Add detailed stats for logged-in users
        if ( ! $is_guest ) {
            $stats = array_merge( $stats, $this->get_detailed_usage_stats( $user_id, $feature ) );
        }

        return $stats;
    }

    /**
     * Get guest identifier for rate limiting.
     *
     * @return string
     */
    private function get_guest_identifier() {
        if ( class_exists( 'RWP_Creator_Suite_Network_Utils' ) ) {
            return RWP_Creator_Suite_Network_Utils::get_client_ip();
        }
        
        // Fallback
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get rate limit for guest users.
     *
     * @param string $feature Feature identifier.
     * @param array  $options Rate limiting options.
     * @return int
     */
    private function get_guest_rate_limit( $feature, $options = array() ) {
        // Check if custom limit is provided in options
        if ( isset( $options['guest_limit'] ) ) {
            return (int) $options['guest_limit'];
        }

        // Feature-specific limits
        $feature_limits = array(
            'ai_generation' => get_option( 'rwp_creator_suite_rate_limit_guest_ai', 5 ),
            'registration' => get_option( 'rwp_creator_suite_rate_limit_guest_registration', 3 ),
            'api_request' => get_option( 'rwp_creator_suite_rate_limit_guest', 10 ),
        );

        return isset( $feature_limits[ $feature ] ) 
            ? (int) $feature_limits[ $feature ] 
            : (int) get_option( 'rwp_creator_suite_rate_limit_guest', 10 );
    }

    /**
     * Get rate limit for authenticated users.
     *
     * @param int    $user_id User ID.
     * @param string $feature Feature identifier.
     * @param array  $options Rate limiting options.
     * @return int
     */
    private function get_user_rate_limit( $user_id, $feature, $options = array() ) {
        // Check if custom limit is provided in options
        if ( isset( $options['user_limit'] ) ) {
            return (int) $options['user_limit'];
        }

        // Check if user is premium
        $is_premium = apply_filters( 'rwp_creator_suite_is_premium_user', false, $user_id );

        // Feature-specific limits
        if ( $is_premium ) {
            $feature_limits = array(
                'ai_generation' => get_option( 'rwp_creator_suite_rate_limit_premium_ai', 100 ),
                'registration' => get_option( 'rwp_creator_suite_rate_limit_premium_registration', 10 ),
                'api_request' => get_option( 'rwp_creator_suite_rate_limit_premium', 200 ),
            );
        } else {
            $feature_limits = array(
                'ai_generation' => get_option( 'rwp_creator_suite_rate_limit_free_ai', 20 ),
                'registration' => get_option( 'rwp_creator_suite_rate_limit_free_registration', 5 ),
                'api_request' => get_option( 'rwp_creator_suite_rate_limit_free', 50 ),
            );
        }

        $default_limit = $is_premium 
            ? get_option( 'rwp_creator_suite_rate_limit_premium', 200 )
            : get_option( 'rwp_creator_suite_rate_limit_free', 50 );

        return isset( $feature_limits[ $feature ] ) 
            ? (int) $feature_limits[ $feature ] 
            : (int) $default_limit;
    }

    /**
     * Generate rate limit transient key.
     *
     * @param string $feature Feature identifier.
     * @param string $identifier User ID or IP address.
     * @return string
     */
    private function get_rate_limit_key( $feature, $identifier ) {
        $safe_identifier = hash( 'sha256', $identifier . wp_salt( 'secure_auth' ) );
        return "rwp_rate_limit_{$feature}_{$safe_identifier}";
    }

    /**
     * Get time until rate limit resets.
     *
     * @param string $transient_key Transient key.
     * @return int Seconds until reset.
     */
    private function get_retry_after( $transient_key ) {
        $timeout_key = '_transient_timeout_' . $transient_key;
        $timeout = get_option( $timeout_key );
        
        if ( ! $timeout ) {
            return HOUR_IN_SECONDS; // Default fallback
        }
        
        return max( 0, $timeout - time() );
    }

    /**
     * Format time window for user display.
     *
     * @param int $seconds Time window in seconds.
     * @return string Formatted time string.
     */
    private function format_time_window( $seconds ) {
        if ( $seconds >= DAY_IN_SECONDS ) {
            $days = floor( $seconds / DAY_IN_SECONDS );
            return sprintf( _n( '%d day', '%d days', $days, 'rwp-creator-suite' ), $days );
        } elseif ( $seconds >= HOUR_IN_SECONDS ) {
            $hours = floor( $seconds / HOUR_IN_SECONDS );
            return sprintf( _n( '%d hour', '%d hours', $hours, 'rwp-creator-suite' ), $hours );
        } elseif ( $seconds >= MINUTE_IN_SECONDS ) {
            $minutes = floor( $seconds / MINUTE_IN_SECONDS );
            return sprintf( _n( '%d minute', '%d minutes', $minutes, 'rwp-creator-suite' ), $minutes );
        } else {
            return sprintf( _n( '%d second', '%d seconds', $seconds, 'rwp-creator-suite' ), $seconds );
        }
    }

    /**
     * Track detailed usage statistics for logged-in users.
     *
     * @param int    $user_id User ID.
     * @param string $feature Feature identifier.
     * @param int    $usage_count Usage count to add.
     */
    private function track_detailed_usage_stats( $user_id, $feature, $usage_count ) {
        // Track total usage across all features
        $total_usage = get_user_meta( $user_id, 'rwp_total_usage', true );
        if ( ! $total_usage ) {
            $total_usage = 0;
        }
        update_user_meta( $user_id, 'rwp_total_usage', $total_usage + $usage_count );

        // Track monthly usage
        $current_month = gmdate( 'Y-m' );
        $monthly_usage = get_user_meta( $user_id, "rwp_usage_{$current_month}", true );
        if ( ! $monthly_usage ) {
            $monthly_usage = 0;
        }
        update_user_meta( $user_id, "rwp_usage_{$current_month}", $monthly_usage + $usage_count );

        // Track feature-specific usage
        $feature_key = "rwp_usage_{$feature}_{$current_month}";
        $feature_usage = get_user_meta( $user_id, $feature_key, true );
        if ( ! $feature_usage ) {
            $feature_usage = 0;
        }
        update_user_meta( $user_id, $feature_key, $feature_usage + $usage_count );
    }

    /**
     * Get detailed usage statistics for logged-in users.
     *
     * @param int    $user_id User ID.
     * @param string $feature Feature identifier.
     * @return array
     */
    private function get_detailed_usage_stats( $user_id, $feature ) {
        $current_month = gmdate( 'Y-m' );
        
        return array(
            'total_usage' => (int) get_user_meta( $user_id, 'rwp_total_usage', true ),
            'monthly_usage' => (int) get_user_meta( $user_id, "rwp_usage_{$current_month}", true ),
            'feature_usage_monthly' => (int) get_user_meta( $user_id, "rwp_usage_{$feature}_{$current_month}", true ),
            'is_premium' => apply_filters( 'rwp_creator_suite_is_premium_user', false, $user_id ),
        );
    }

    /**
     * Check if rate limiting is bypassed for current user.
     *
     * @param string $feature Feature identifier.
     * @return bool
     */
    public function is_rate_limit_bypassed( $feature = 'api_request' ) {
        // Bypass in development mode
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return apply_filters( 'rwp_creator_suite_bypass_rate_limit_debug', false, $feature );
        }

        // Check user capability
        if ( current_user_can( 'manage_options' ) ) {
            return apply_filters( 'rwp_creator_suite_bypass_rate_limit_admin', true, $feature );
        }

        // Allow custom bypass logic
        return apply_filters( 'rwp_creator_suite_bypass_rate_limit', false, $feature );
    }

    /**
     * Reset rate limit for a specific identifier.
     *
     * @param string $feature Feature identifier.
     * @param string $identifier Optional specific identifier to reset.
     */
    public function reset_rate_limit( $feature = 'api_request', $identifier = null ) {
        if ( null === $identifier ) {
            $user_id = get_current_user_id();
            $identifier = $user_id ? $user_id : $this->get_guest_identifier();
        }

        $transient_key = $this->get_rate_limit_key( $feature, $identifier );
        delete_transient( $transient_key );

        do_action( 'rwp_creator_suite_rate_limit_reset', $identifier, $feature );
    }
}