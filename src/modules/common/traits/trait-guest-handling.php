<?php
/**
 * Guest Handling Trait
 * 
 * Provides shared functionality for handling guest users and their data.
 * Manages guest access permissions and data storage patterns.
 */

defined( 'ABSPATH' ) || exit;

trait RWP_Creator_Suite_Guest_Handling_Trait {

    /**
     * Check if guest access is allowed for a specific feature.
     *
     * @param string $feature Feature identifier (e.g., 'content_repurposer', 'caption_writer').
     * @return bool|WP_Error
     */
    public function check_guest_access( $feature ) {
        $is_guest = ! is_user_logged_in();
        
        if ( ! $is_guest ) {
            return true; // Logged-in users always have access
        }

        // Check global guest access setting
        $guest_access_enabled = get_option( 'rwp_creator_suite_allow_guest_access', 1 );
        if ( ! $guest_access_enabled ) {
            return new WP_Error(
                'guest_access_disabled',
                __( 'Guest access is disabled. Please log in to continue.', 'rwp-creator-suite' ),
                array( 'status' => 401 )
            );
        }

        // Check feature-specific guest access
        $feature_access = $this->check_feature_guest_access( $feature );
        if ( is_wp_error( $feature_access ) ) {
            return $feature_access;
        }

        return true;
    }

    /**
     * Check guest access for specific features.
     *
     * @param string $feature Feature identifier.
     * @return bool|WP_Error
     */
    private function check_feature_guest_access( $feature ) {
        $feature_settings = array(
            'content_repurposer' => get_option( 'rwp_creator_suite_allow_guest_repurpose', 1 ),
            'caption_writer' => get_option( 'rwp_creator_suite_allow_guest_captions', 0 ),
            'instagram_analyzer' => get_option( 'rwp_creator_suite_allow_guest_analyzer', 0 ),
        );

        $is_allowed = isset( $feature_settings[ $feature ] ) 
            ? (bool) $feature_settings[ $feature ] 
            : false;

        // Apply filters for customization
        $is_allowed = apply_filters( "rwp_creator_suite_guest_access_{$feature}", $is_allowed );
        $is_allowed = apply_filters( 'rwp_creator_suite_guest_access', $is_allowed, $feature );

        if ( ! $is_allowed ) {
            $feature_name = $this->get_feature_display_name( $feature );
            return new WP_Error(
                'feature_guest_access_disabled',
                sprintf(
                    __( 'Guest access to %s is disabled. Please log in to continue.', 'rwp-creator-suite' ),
                    $feature_name
                ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Get display name for feature.
     *
     * @param string $feature Feature identifier.
     * @return string
     */
    private function get_feature_display_name( $feature ) {
        $display_names = array(
            'content_repurposer' => __( 'Content Repurposer', 'rwp-creator-suite' ),
            'caption_writer' => __( 'Caption Writer', 'rwp-creator-suite' ),
            'instagram_analyzer' => __( 'Instagram Analyzer', 'rwp-creator-suite' ),
        );

        return isset( $display_names[ $feature ] ) 
            ? $display_names[ $feature ] 
            : ucwords( str_replace( '_', ' ', $feature ) );
    }

    /**
     * Get guest identifier for tracking purposes.
     *
     * @return string
     */
    public function get_guest_user_identifier() {
        // Use Network Utils if available
        if ( class_exists( 'RWP_Creator_Suite_Network_Utils' ) ) {
            return RWP_Creator_Suite_Network_Utils::get_client_ip_hash();
        }

        // Fallback to basic IP hashing
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return hash( 'sha256', $ip . wp_salt( 'secure_auth' ) );
    }

    /**
     * Store guest data in browser storage (localStorage).
     * This method returns instructions for the frontend to handle storage.
     *
     * @param string $key Storage key.
     * @param mixed  $data Data to store.
     * @return array Instructions for frontend storage.
     */
    public function store_guest_data( $key, $data ) {
        return array(
            'action' => 'store_locally',
            'key' => sanitize_key( $key ),
            'data' => $data,
            'message' => __( 'Data will be stored locally in your browser.', 'rwp-creator-suite' ),
        );
    }

    /**
     * Get guest usage limits.
     *
     * @param string $feature Feature identifier.
     * @return array Usage limits information.
     */
    public function get_guest_limits( $feature ) {
        $limits = array(
            'content_repurposer' => array(
                'hourly' => get_option( 'rwp_creator_suite_guest_repurpose_hourly', 5 ),
                'daily' => get_option( 'rwp_creator_suite_guest_repurpose_daily', 20 ),
                'platforms_per_request' => get_option( 'rwp_creator_suite_guest_platforms_limit', 3 ),
            ),
            'caption_writer' => array(
                'hourly' => get_option( 'rwp_creator_suite_guest_captions_hourly', 3 ),
                'daily' => get_option( 'rwp_creator_suite_guest_captions_daily', 10 ),
                'platforms_per_request' => get_option( 'rwp_creator_suite_guest_platforms_limit', 1 ),
            ),
            'instagram_analyzer' => array(
                'hourly' => get_option( 'rwp_creator_suite_guest_analyzer_hourly', 2 ),
                'daily' => get_option( 'rwp_creator_suite_guest_analyzer_daily', 5 ),
                'platforms_per_request' => 1,
            ),
        );

        $default_limits = array(
            'hourly' => 5,
            'daily' => 20,
            'platforms_per_request' => 3,
        );

        $feature_limits = isset( $limits[ $feature ] ) ? $limits[ $feature ] : $default_limits;

        // Apply filters for customization
        return apply_filters( 'rwp_creator_suite_guest_limits', $feature_limits, $feature );
    }

    /**
     * Check if guest has exceeded usage limits.
     *
     * @param string $feature Feature identifier.
     * @param string $period Time period ('hourly' or 'daily').
     * @return bool|WP_Error
     */
    public function check_guest_usage_limit( $feature, $period = 'hourly' ) {
        $identifier = $this->get_guest_user_identifier();
        $limits = $this->get_guest_limits( $feature );

        if ( ! isset( $limits[ $period ] ) ) {
            return true;
        }

        $limit = $limits[ $period ];
        $window = $period === 'daily' ? DAY_IN_SECONDS : HOUR_IN_SECONDS;

        $transient_key = "rwp_guest_usage_{$feature}_{$period}_{$identifier}";
        $current_usage = get_transient( $transient_key );

        if ( false === $current_usage ) {
            $current_usage = 0;
        }

        if ( $current_usage >= $limit ) {
            return new WP_Error(
                'guest_limit_exceeded',
                sprintf(
                    __( 'Guest usage limit exceeded. You can use this feature %d times per %s.', 'rwp-creator-suite' ),
                    $limit,
                    $period === 'daily' ? __( 'day', 'rwp-creator-suite' ) : __( 'hour', 'rwp-creator-suite' )
                ),
                array( 
                    'status' => 429,
                    'limit' => $limit,
                    'period' => $period,
                    'remaining' => 0
                )
            );
        }

        return true;
    }

    /**
     * Track guest usage.
     *
     * @param string $feature Feature identifier.
     * @param int    $count Usage count to add.
     */
    public function track_guest_usage( $feature, $count = 1 ) {
        $identifier = $this->get_guest_user_identifier();

        // Track hourly usage
        $hourly_key = "rwp_guest_usage_{$feature}_hourly_{$identifier}";
        $hourly_usage = get_transient( $hourly_key );
        if ( false === $hourly_usage ) {
            $hourly_usage = 0;
        }
        set_transient( $hourly_key, $hourly_usage + $count, HOUR_IN_SECONDS );

        // Track daily usage
        $daily_key = "rwp_guest_usage_{$feature}_daily_{$identifier}";
        $daily_usage = get_transient( $daily_key );
        if ( false === $daily_usage ) {
            $daily_usage = 0;
        }
        set_transient( $daily_key, $daily_usage + $count, DAY_IN_SECONDS );

        // Fire action for tracking
        do_action( 'rwp_creator_suite_guest_usage_tracked', $feature, $count, $identifier );
    }

    /**
     * Get guest usage statistics.
     *
     * @param string $feature Feature identifier.
     * @return array
     */
    public function get_guest_usage_stats( $feature ) {
        $identifier = $this->get_guest_user_identifier();
        $limits = $this->get_guest_limits( $feature );

        $hourly_key = "rwp_guest_usage_{$feature}_hourly_{$identifier}";
        $daily_key = "rwp_guest_usage_{$feature}_daily_{$identifier}";

        $hourly_usage = get_transient( $hourly_key );
        $daily_usage = get_transient( $daily_key );

        if ( false === $hourly_usage ) {
            $hourly_usage = 0;
        }
        if ( false === $daily_usage ) {
            $daily_usage = 0;
        }

        return array(
            'hourly' => array(
                'used' => $hourly_usage,
                'limit' => $limits['hourly'],
                'remaining' => max( 0, $limits['hourly'] - $hourly_usage ),
            ),
            'daily' => array(
                'used' => $daily_usage,
                'limit' => $limits['daily'],
                'remaining' => max( 0, $limits['daily'] - $daily_usage ),
            ),
            'platforms_per_request' => $limits['platforms_per_request'],
            'is_guest' => true,
        );
    }

    /**
     * Provide upgrade message for guests.
     *
     * @param string $feature Feature identifier.
     * @return array
     */
    public function get_guest_upgrade_message( $feature ) {
        $feature_name = $this->get_feature_display_name( $feature );
        
        return array(
            'title' => __( 'Want More?', 'rwp-creator-suite' ),
            'message' => sprintf(
                __( 'Create a free account to get higher limits and save your %s history.', 'rwp-creator-suite' ),
                $feature_name
            ),
            'cta_text' => __( 'Sign Up Free', 'rwp-creator-suite' ),
            'cta_url' => wp_registration_url(),
            'benefits' => array(
                __( 'Higher usage limits', 'rwp-creator-suite' ),
                __( 'Save favorites and history', 'rwp-creator-suite' ),
                __( 'Access to all features', 'rwp-creator-suite' ),
                __( 'Priority support', 'rwp-creator-suite' ),
            ),
        );
    }

    /**
     * Handle guest to user data migration instructions.
     * This provides instructions for the frontend to migrate localStorage data.
     *
     * @return array Migration instructions.
     */
    public function get_guest_migration_instructions() {
        return array(
            'action' => 'migrate_guest_data',
            'endpoint' => rest_url( 'rwp-creator-suite/v1/user/migrate-guest-data' ),
            'message' => __( 'Welcome! Your guest data can be saved to your account.', 'rwp-creator-suite' ),
            'data_types' => array(
                'favorites',
                'preferences',
                'recent_generations',
            ),
        );
    }

    /**
     * Validate guest request parameters.
     *
     * @param WP_REST_Request $request Request object.
     * @param string          $feature Feature identifier.
     * @return bool|WP_Error
     */
    public function validate_guest_request( $request, $feature ) {
        // Check guest access
        $access_check = $this->check_guest_access( $feature );
        if ( is_wp_error( $access_check ) ) {
            return $access_check;
        }

        // Check usage limits
        $usage_check = $this->check_guest_usage_limit( $feature, 'hourly' );
        if ( is_wp_error( $usage_check ) ) {
            return $usage_check;
        }

        $daily_check = $this->check_guest_usage_limit( $feature, 'daily' );
        if ( is_wp_error( $daily_check ) ) {
            return $daily_check;
        }

        // Validate platform limits for guests
        $platforms = $request->get_param( 'platforms' );
        if ( is_array( $platforms ) ) {
            $limits = $this->get_guest_limits( $feature );
            $max_platforms = $limits['platforms_per_request'];
            
            if ( count( $platforms ) > $max_platforms ) {
                return new WP_Error(
                    'guest_platform_limit',
                    sprintf(
                        __( 'Guests can select a maximum of %d platform(s) per request.', 'rwp-creator-suite' ),
                        $max_platforms
                    ),
                    array( 'status' => 400 )
                );
            }
        }

        return true;
    }
}