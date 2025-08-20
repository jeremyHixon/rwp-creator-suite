<?php
/**
 * Value Notifications System
 * 
 * Handles email reports, push notifications, and value delivery notifications
 * for users who have consented to analytics data collection.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Value_Notifications {

    /**
     * Single instance of the class.
     *
     * @var RWP_Creator_Suite_Value_Notifications
     */
    private static $instance = null;

    /**
     * User Value API instance.
     *
     * @var RWP_Creator_Suite_User_Value_API
     */
    private $value_api;

    /**
     * Get single instance of the class.
     *
     * @return RWP_Creator_Suite_Value_Notifications
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->value_api = new RWP_Creator_Suite_User_Value_API();
    }

    /**
     * Initialize notification system.
     */
    public function init() {
        // Schedule notification events
        add_action( 'init', array( $this, 'schedule_notifications' ) );
        
        // Register notification handlers
        add_action( 'rwp_send_weekly_trends', array( $this, 'send_weekly_trends_notification' ), 10, 1 );
        add_action( 'rwp_send_monthly_report', array( $this, 'send_monthly_report_email' ), 10, 1 );
        add_action( 'rwp_send_achievement_notification', array( $this, 'send_achievement_notification' ), 10, 2 );
        add_action( 'rwp_send_opportunity_alert', array( $this, 'send_opportunity_alert' ), 10, 2 );

        // Daily opportunity scanning
        add_action( 'rwp_scan_opportunities', array( $this, 'scan_and_notify_opportunities' ) );

        // User preference hooks
        add_action( 'user_register', array( $this, 'setup_user_notification_preferences' ) );
        add_action( 'profile_update', array( $this, 'handle_notification_preference_update' ), 10, 2 );

        // REST API endpoints for notification preferences
        add_action( 'rest_api_init', array( $this, 'register_notification_endpoints' ) );

        // Admin dashboard widget
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
    }

    /**
     * Schedule notification events if not already scheduled.
     */
    public function schedule_notifications() {
        // Weekly trending insights
        if ( ! wp_next_scheduled( 'rwp_send_weekly_trends' ) ) {
            wp_schedule_event( strtotime( 'next monday 10:00' ), 'weekly', 'rwp_send_weekly_trends' );
        }

        // Monthly performance reports
        if ( ! wp_next_scheduled( 'rwp_send_monthly_report' ) ) {
            wp_schedule_event( strtotime( 'first day of next month 09:00' ), 'monthly', 'rwp_send_monthly_report' );
        }

        // Daily opportunity scanning
        if ( ! wp_next_scheduled( 'rwp_scan_opportunities' ) ) {
            wp_schedule_event( time(), 'daily', 'rwp_scan_opportunities' );
        }
    }

    /**
     * Set up notification preferences for new users.
     *
     * @param int $user_id User ID.
     */
    public function setup_user_notification_preferences( $user_id ) {
        $default_preferences = array(
            'weekly_trends' => true,
            'monthly_reports' => true,
            'achievement_notifications' => true,
            'opportunity_alerts' => true,
            'breaking_trends' => false,
            'email_format' => 'html',
            'notification_time' => '10:00',
        );

        update_user_meta( $user_id, 'rwp_notification_preferences', $default_preferences );
    }

    /**
     * Send weekly trends notification.
     *
     * @param int $user_id Optional user ID to send to specific user.
     */
    public function send_weekly_trends_notification( $user_id = null ) {
        $users_to_notify = $user_id ? array( $user_id ) : $this->get_users_with_consent();

        foreach ( $users_to_notify as $user_id ) {
            if ( ! $this->should_send_notification( $user_id, 'weekly_trends' ) ) {
                continue;
            }

            $user_profile = $this->get_user_profile_for_notifications( $user_id );
            if ( ! $user_profile ) {
                continue;
            }

            $trend_report = $this->value_api->get_trending_report( new WP_REST_Request() );
            
            if ( $trend_report->is_error() ) {
                continue;
            }

            $email_data = array(
                'user_id' => $user_id,
                'user_profile' => $user_profile,
                'trend_data' => $trend_report->get_data()['data'],
                'email_type' => 'weekly_trends',
            );

            $this->send_trends_email( $email_data );

            // Update last notification time
            update_user_meta( $user_id, 'rwp_last_weekly_trends_notification', current_time( 'mysql' ) );
        }
    }

    /**
     * Send monthly report email.
     *
     * @param int $user_id Optional user ID to send to specific user.
     */
    public function send_monthly_report_email( $user_id = null ) {
        $users_to_notify = $user_id ? array( $user_id ) : $this->get_users_with_consent();

        foreach ( $users_to_notify as $user_id ) {
            if ( ! $this->should_send_notification( $user_id, 'monthly_reports' ) ) {
                continue;
            }

            $user_profile = $this->get_user_profile_for_notifications( $user_id );
            if ( ! $user_profile ) {
                continue;
            }

            $month = date( 'n' ) - 1; // Previous month
            $year = date( 'Y' );
            if ( $month < 1 ) {
                $month = 12;
                $year--;
            }

            $request = new WP_REST_Request();
            $request->set_param( 'month', $month );
            $request->set_param( 'year', $year );
            
            $monthly_report = $this->value_api->generate_monthly_report( $request );
            
            if ( $monthly_report->is_error() ) {
                continue;
            }

            $email_data = array(
                'user_id' => $user_id,
                'user_profile' => $user_profile,
                'report_data' => $monthly_report->get_data()['data'],
                'month' => $month,
                'year' => $year,
                'email_type' => 'monthly_report',
            );

            $this->send_monthly_report_email_content( $email_data );

            // Update last notification time
            update_user_meta( $user_id, 'rwp_last_monthly_report_notification', current_time( 'mysql' ) );
        }
    }

    /**
     * Send achievement notification.
     *
     * @param int   $user_id User ID.
     * @param array $achievement Achievement data.
     */
    public function send_achievement_notification( $user_id, $achievement ) {
        if ( ! $this->should_send_notification( $user_id, 'achievement_notifications' ) ) {
            return;
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return;
        }

        $email_data = array(
            'user_id' => $user_id,
            'user' => $user,
            'achievement' => $achievement,
            'email_type' => 'achievement',
        );

        $this->send_achievement_email( $email_data );
    }

    /**
     * Send opportunity alert.
     *
     * @param int   $user_id User ID.
     * @param array $opportunity Opportunity data.
     */
    public function send_opportunity_alert( $user_id, $opportunity ) {
        if ( ! $this->should_send_notification( $user_id, 'opportunity_alerts' ) ) {
            return;
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return;
        }

        $email_data = array(
            'user_id' => $user_id,
            'user' => $user,
            'opportunity' => $opportunity,
            'email_type' => 'opportunity',
        );

        $this->send_opportunity_email( $email_data );
    }

    /**
     * Scan for opportunities and send notifications.
     */
    public function scan_and_notify_opportunities() {
        $users_with_consent = $this->get_users_with_consent();

        foreach ( $users_with_consent as $user_id ) {
            if ( ! $this->should_send_notification( $user_id, 'opportunity_alerts' ) ) {
                continue;
            }

            // Check if user has been notified recently (avoid spam)
            $last_opportunity_notification = get_user_meta( $user_id, 'rwp_last_opportunity_notification', true );
            if ( $last_opportunity_notification && strtotime( $last_opportunity_notification ) > strtotime( '-2 days' ) ) {
                continue;
            }

            $user_profile = $this->get_user_profile_for_notifications( $user_id );
            if ( ! $user_profile ) {
                continue;
            }

            $opportunities = $this->identify_high_impact_opportunities( $user_profile );
            
            if ( ! empty( $opportunities ) ) {
                $this->send_opportunity_alert( $user_id, $opportunities[0] ); // Send top opportunity
                update_user_meta( $user_id, 'rwp_last_opportunity_notification', current_time( 'mysql' ) );
            }
        }
    }

    /**
     * Register REST API endpoints for notification preferences.
     */
    public function register_notification_endpoints() {
        register_rest_route( 'rwp-creator-suite/v1', '/notification-preferences', array(
            array(
                'methods' => 'GET',
                'callback' => array( $this, 'get_notification_preferences' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
            ),
            array(
                'methods' => 'POST',
                'callback' => array( $this, 'update_notification_preferences' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
                'args' => array(
                    'preferences' => array(
                        'required' => true,
                        'type' => 'object',
                    ),
                ),
            ),
        ) );

        register_rest_route( 'rwp-creator-suite/v1', '/test-notification', array(
            'methods' => 'POST',
            'callback' => array( $this, 'send_test_notification' ),
            'permission_callback' => array( $this, 'check_user_logged_in' ),
            'args' => array(
                'type' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array( 'weekly_trends', 'monthly_report', 'achievement', 'opportunity' ),
                ),
            ),
        ) );
    }

    /**
     * Check if user is logged in.
     *
     * @return bool
     */
    public function check_user_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Get notification preferences for current user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_notification_preferences( $request ) {
        $user_id = get_current_user_id();
        $preferences = get_user_meta( $user_id, 'rwp_notification_preferences', true );

        if ( ! $preferences ) {
            $this->setup_user_notification_preferences( $user_id );
            $preferences = get_user_meta( $user_id, 'rwp_notification_preferences', true );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'preferences' => $preferences,
        ), 200 );
    }

    /**
     * Update notification preferences for current user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function update_notification_preferences( $request ) {
        $user_id = get_current_user_id();
        $new_preferences = $request->get_param( 'preferences' );

        // Validate preferences
        $valid_keys = array( 'weekly_trends', 'monthly_reports', 'achievement_notifications', 'opportunity_alerts', 'breaking_trends', 'email_format', 'notification_time' );
        $filtered_preferences = array_intersect_key( $new_preferences, array_flip( $valid_keys ) );

        update_user_meta( $user_id, 'rwp_notification_preferences', $filtered_preferences );

        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Notification preferences updated successfully.', 'rwp-creator-suite' ),
            'preferences' => $filtered_preferences,
        ), 200 );
    }

    /**
     * Send test notification.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function send_test_notification( $request ) {
        $user_id = get_current_user_id();
        $type = $request->get_param( 'type' );

        switch ( $type ) {
            case 'weekly_trends':
                $this->send_weekly_trends_notification( $user_id );
                $message = __( 'Test weekly trends email sent!', 'rwp-creator-suite' );
                break;

            case 'monthly_report':
                $this->send_monthly_report_email( $user_id );
                $message = __( 'Test monthly report email sent!', 'rwp-creator-suite' );
                break;

            case 'achievement':
                $test_achievement = array(
                    'name' => __( 'Test Achievement', 'rwp-creator-suite' ),
                    'description' => __( 'This is a test achievement notification.', 'rwp-creator-suite' ),
                    'icon' => '🏆',
                    'level' => 1,
                );
                $this->send_achievement_notification( $user_id, $test_achievement );
                $message = __( 'Test achievement notification sent!', 'rwp-creator-suite' );
                break;

            case 'opportunity':
                $test_opportunity = array(
                    'type' => 'hashtag_opportunity',
                    'title' => __( 'Test Opportunity', 'rwp-creator-suite' ),
                    'description' => __( 'This is a test opportunity notification.', 'rwp-creator-suite' ),
                    'impact' => 'High',
                );
                $this->send_opportunity_alert( $user_id, $test_opportunity );
                $message = __( 'Test opportunity alert sent!', 'rwp-creator-suite' );
                break;

            default:
                return new WP_Error( 'invalid_type', __( 'Invalid notification type.', 'rwp-creator-suite' ), array( 'status' => 400 ) );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'message' => $message,
        ), 200 );
    }

    /**
     * Add dashboard widget for creator insights.
     */
    public function add_dashboard_widget() {
        // Only show for users with analytics consent
        $consent_manager = RWP_Creator_Suite_Consent_Manager::get_instance();
        if ( $consent_manager->has_user_consented() !== true ) {
            return;
        }

        wp_add_dashboard_widget(
            'rwp_creator_insights',
            __( '📊 Creator Insights', 'rwp-creator-suite' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Render dashboard widget content.
     */
    public function render_dashboard_widget() {
        $user_id = get_current_user_id();
        $user_profile = $this->get_user_profile_for_notifications( $user_id );

        if ( ! $user_profile ) {
            echo '<p>' . esc_html__( 'Start using Creator Suite tools to see your insights here!', 'rwp-creator-suite' ) . '</p>';
            return;
        }

        // Get quick insights
        $insights = array(
            'content_pieces' => array_sum( $user_profile['features'] ?? array() ),
            'platforms' => count( $user_profile['platforms'] ?? array() ),
            'hashtags' => count( $user_profile['hashtag_usage'] ?? array() ),
            'consistency' => round( ( $user_profile['consistency_score'] ?? 0 ) * 100, 1 ),
        );

        ?>
        <div class="rwp-dashboard-insights">
            <div class="rwp-insights-grid">
                <div class="rwp-insight-item">
                    <span class="rwp-insight-number"><?php echo esc_html( $insights['content_pieces'] ); ?></span>
                    <span class="rwp-insight-label"><?php esc_html_e( 'Content Pieces', 'rwp-creator-suite' ); ?></span>
                </div>
                <div class="rwp-insight-item">
                    <span class="rwp-insight-number"><?php echo esc_html( $insights['platforms'] ); ?></span>
                    <span class="rwp-insight-label"><?php esc_html_e( 'Platforms', 'rwp-creator-suite' ); ?></span>
                </div>
                <div class="rwp-insight-item">
                    <span class="rwp-insight-number"><?php echo esc_html( $insights['hashtags'] ); ?></span>
                    <span class="rwp-insight-label"><?php esc_html_e( 'Hashtags Used', 'rwp-creator-suite' ); ?></span>
                </div>
                <div class="rwp-insight-item">
                    <span class="rwp-insight-number"><?php echo esc_html( $insights['consistency'] ); ?>%</span>
                    <span class="rwp-insight-label"><?php esc_html_e( 'Consistency', 'rwp-creator-suite' ); ?></span>
                </div>
            </div>
            
            <div class="rwp-insights-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=insights' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'View Full Insights', 'rwp-creator-suite' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=notifications' ) ); ?>" class="button">
                    <?php esc_html_e( 'Notification Settings', 'rwp-creator-suite' ); ?>
                </a>
            </div>
        </div>

        <style>
        .rwp-dashboard-insights {
            padding: 0;
        }
        .rwp-insights-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .rwp-insight-item {
            text-align: center;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .rwp-insight-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
            line-height: 1;
        }
        .rwp-insight-label {
            display: block;
            font-size: 11px;
            color: #646970;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .rwp-insights-actions {
            display: flex;
            gap: 10px;
        }
        .rwp-insights-actions .button {
            flex: 1;
            text-align: center;
            justify-content: center;
        }
        </style>
        <?php
    }

    /**
     * Get users with analytics consent.
     *
     * @return array Array of user IDs.
     */
    private function get_users_with_consent() {
        $consent_key = RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key();
        
        $users = get_users( array(
            'meta_query' => array(
                array(
                    'key' => $consent_key,
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'fields' => 'ID',
            'number' => -1,
        ) );

        return $users;
    }

    /**
     * Check if user should receive notification.
     *
     * @param int    $user_id User ID.
     * @param string $notification_type Notification type.
     * @return bool
     */
    private function should_send_notification( $user_id, $notification_type ) {
        $preferences = get_user_meta( $user_id, 'rwp_notification_preferences', true );
        
        if ( ! $preferences || ! isset( $preferences[ $notification_type ] ) ) {
            return true; // Default to sending notifications
        }

        return $preferences[ $notification_type ];
    }

    /**
     * Get user profile for notifications.
     *
     * @param int $user_id User ID.
     * @return array|null
     */
    private function get_user_profile_for_notifications( $user_id ) {
        // Temporarily set current user for profile building
        $original_user = wp_get_current_user();
        wp_set_current_user( $user_id );

        $analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
        $session_hash = $analytics->get_session_hash();

        if ( ! $session_hash ) {
            wp_set_current_user( $original_user->ID );
            return null;
        }

        // Get user's recent analytics data
        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

        $user_data = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, event_data, timestamp 
             FROM {$table_name} 
             WHERE anonymous_session_hash = %s 
             AND timestamp >= %s
             ORDER BY timestamp DESC
             LIMIT 100",
            $session_hash,
            date( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
        ), ARRAY_A );

        wp_set_current_user( $original_user->ID );

        if ( empty( $user_data ) ) {
            return null;
        }

        return $this->build_user_profile_from_data( $user_data, $user_id );
    }

    /**
     * Build user profile from analytics data.
     *
     * @param array $user_data Analytics data.
     * @param int   $user_id User ID.
     * @return array
     */
    private function build_user_profile_from_data( $user_data, $user_id ) {
        $profile = array(
            'user_id' => $user_id,
            'platforms' => array(),
            'features' => array(),
            'hashtag_usage' => array(),
            'consistency_score' => 0.5,
        );

        foreach ( $user_data as $event ) {
            $event_data = json_decode( $event['event_data'], true );

            if ( ! empty( $event_data['platform'] ) ) {
                $platform = $event_data['platform'];
                $profile['platforms'][ $platform ] = ( $profile['platforms'][ $platform ] ?? 0 ) + 1;
            }

            if ( ! empty( $event_data['feature'] ) ) {
                $feature = $event_data['feature'];
                $profile['features'][ $feature ] = ( $profile['features'][ $feature ] ?? 0 ) + 1;
            }

            if ( $event['event_type'] === RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED && ! empty( $event_data['hashtag_hash'] ) ) {
                $hash = $event_data['hashtag_hash'];
                $profile['hashtag_usage'][ $hash ] = ( $profile['hashtag_usage'][ $hash ] ?? 0 ) + 1;
            }
        }

        return $profile;
    }

    /**
     * Identify high-impact opportunities for user.
     *
     * @param array $user_profile User profile data.
     * @return array
     */
    private function identify_high_impact_opportunities( $user_profile ) {
        $opportunities = array();

        // Check for trending hashtags the user isn't using
        $trending_hashtags = $this->get_current_trending_hashtags();
        $user_hashtags = array_keys( $user_profile['hashtag_usage'] ?? array() );
        $unused_trending = array_diff( $trending_hashtags, $user_hashtags );

        if ( ! empty( $unused_trending ) ) {
            $opportunities[] = array(
                'type' => 'hashtag_opportunity',
                'title' => __( 'Trending Hashtags Available', 'rwp-creator-suite' ),
                'description' => sprintf(
                    __( '%d trending hashtags could boost your reach by up to 30%%.', 'rwp-creator-suite' ),
                    count( $unused_trending )
                ),
                'impact' => 'High',
                'effort' => 'Low',
                'data' => array_slice( $unused_trending, 0, 3 ),
            );
        }

        // Check for underutilized platforms
        $all_platforms = array( 'instagram', 'tiktok', 'twitter', 'linkedin' );
        $user_platforms = array_keys( $user_profile['platforms'] ?? array() );
        $unused_platforms = array_diff( $all_platforms, $user_platforms );

        if ( ! empty( $unused_platforms ) && count( $user_platforms ) >= 1 ) {
            $opportunities[] = array(
                'type' => 'platform_opportunity',
                'title' => __( 'Platform Expansion Opportunity', 'rwp-creator-suite' ),
                'description' => sprintf(
                    __( 'Expanding to %s could increase your audience by 40%%.', 'rwp-creator-suite' ),
                    ucfirst( $unused_platforms[0] )
                ),
                'impact' => 'Medium',
                'effort' => 'Medium',
                'data' => array( 'platform' => $unused_platforms[0] ),
            );
        }

        return array_slice( $opportunities, 0, 2 ); // Return top 2 opportunities
    }

    /**
     * Get current trending hashtags.
     *
     * @return array
     */
    private function get_current_trending_hashtags() {
        $cache_key = 'rwp_trending_hashtags';
        $trending = get_transient( $cache_key );

        if ( false === $trending ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT JSON_EXTRACT(event_data, '$.hashtag_hash') as hashtag_hash,
                        COUNT(*) as usage_count
                 FROM {$table_name}
                 WHERE event_type = %s
                 AND timestamp >= %s
                 GROUP BY hashtag_hash
                 ORDER BY usage_count DESC
                 LIMIT 20",
                RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED,
                date( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
            ), ARRAY_A );

            $trending = array_map( function( $result ) {
                return trim( $result['hashtag_hash'], '"' );
            }, $results );

            set_transient( $cache_key, $trending, HOUR_IN_SECONDS );
        }

        return $trending;
    }

    /**
     * Send trends email.
     *
     * @param array $email_data Email data.
     */
    private function send_trends_email( $email_data ) {
        $user = get_user_by( 'ID', $email_data['user_id'] );
        $trend_data = $email_data['trend_data'];

        $subject = sprintf(
            __( '🔥 This Week\'s Creator Trends - %s', 'rwp-creator-suite' ),
            date( 'F j, Y' )
        );

        $message = $this->render_email_template( 'weekly-trends', array(
            'user' => $user,
            'trend_data' => $trend_data,
            'user_profile' => $email_data['user_profile'],
        ) );

        wp_mail(
            $user->user_email,
            $subject,
            $message,
            array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Creator Suite <' . get_option( 'admin_email' ) . '>',
            )
        );
    }

    /**
     * Send monthly report email.
     *
     * @param array $email_data Email data.
     */
    private function send_monthly_report_email_content( $email_data ) {
        $user = get_user_by( 'ID', $email_data['user_id'] );
        $report_data = $email_data['report_data'];

        $subject = sprintf(
            __( '📊 Your %s Creator Report - Amazing Progress!', 'rwp-creator-suite' ),
            date( 'F Y', mktime( 0, 0, 0, $email_data['month'], 1, $email_data['year'] ) )
        );

        $message = $this->render_email_template( 'monthly-report', array(
            'user' => $user,
            'report_data' => $report_data,
            'month_year' => date( 'F Y', mktime( 0, 0, 0, $email_data['month'], 1, $email_data['year'] ) ),
        ) );

        wp_mail(
            $user->user_email,
            $subject,
            $message,
            array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Creator Suite <' . get_option( 'admin_email' ) . '>',
            )
        );
    }

    /**
     * Send achievement email.
     *
     * @param array $email_data Email data.
     */
    private function send_achievement_email( $email_data ) {
        $user = $email_data['user'];
        $achievement = $email_data['achievement'];

        $subject = sprintf(
            __( '🏆 Achievement Unlocked: %s!', 'rwp-creator-suite' ),
            $achievement['name']
        );

        $message = $this->render_email_template( 'achievement', array(
            'user' => $user,
            'achievement' => $achievement,
        ) );

        wp_mail(
            $user->user_email,
            $subject,
            $message,
            array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Creator Suite <' . get_option( 'admin_email' ) . '>',
            )
        );
    }

    /**
     * Send opportunity email.
     *
     * @param array $email_data Email data.
     */
    private function send_opportunity_email( $email_data ) {
        $user = $email_data['user'];
        $opportunity = $email_data['opportunity'];

        $subject = sprintf(
            __( '💡 Creator Opportunity: %s', 'rwp-creator-suite' ),
            $opportunity['title']
        );

        $message = $this->render_email_template( 'opportunity', array(
            'user' => $user,
            'opportunity' => $opportunity,
        ) );

        wp_mail(
            $user->user_email,
            $subject,
            $message,
            array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Creator Suite <' . get_option( 'admin_email' ) . '>',
            )
        );
    }

    /**
     * Render email template.
     *
     * @param string $template Template name.
     * @param array  $data Template data.
     * @return string
     */
    private function render_email_template( $template, $data ) {
        ob_start();
        
        // Extract data to variables
        extract( $data );
        
        $template_path = RWP_CREATOR_SUITE_PLUGIN_DIR . "templates/emails/{$template}.php";
        
        if ( file_exists( $template_path ) ) {
            include $template_path;
        } else {
            // Fallback to basic template
            include RWP_CREATOR_SUITE_PLUGIN_DIR . "templates/emails/basic.php";
        }
        
        return ob_get_clean();
    }

    /**
     * Handle notification preference updates.
     *
     * @param int   $user_id User ID.
     * @param array $old_user_data Old user data.
     */
    public function handle_notification_preference_update( $user_id, $old_user_data ) {
        // Log preference changes for audit
        $new_preferences = get_user_meta( $user_id, 'rwp_notification_preferences', true );
        
        if ( $new_preferences ) {
            RWP_Creator_Suite_Error_Logger::log(
                'User notification preferences updated',
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
                array(
                    'user_id' => $user_id,
                    'preferences' => $new_preferences,
                )
            );
        }
    }

    /**
     * Get notification statistics for admin dashboard.
     *
     * @return array
     */
    public function get_notification_stats() {
        global $wpdb;
        
        $consent_key = RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key();
        
        // Users with consent
        $users_with_consent = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '1'",
            $consent_key
        ) );

        // Users with notification preferences
        $users_with_prefs = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'rwp_notification_preferences'"
        );

        // Recent notifications sent (would track in separate table in production)
        $recent_notifications = array(
            'weekly_trends' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} 
                 WHERE meta_key = 'rwp_last_weekly_trends_notification' 
                 AND meta_value >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            ),
            'monthly_reports' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} 
                 WHERE meta_key = 'rwp_last_monthly_report_notification' 
                 AND meta_value >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
        );

        return array(
            'users_with_consent' => (int) $users_with_consent,
            'users_with_preferences' => (int) $users_with_prefs,
            'recent_notifications' => $recent_notifications,
            'notification_types' => array(
                'weekly_trends' => __( 'Weekly Trend Reports', 'rwp-creator-suite' ),
                'monthly_reports' => __( 'Monthly Performance Reports', 'rwp-creator-suite' ),
                'achievement_notifications' => __( 'Achievement Celebrations', 'rwp-creator-suite' ),
                'opportunity_alerts' => __( 'Growth Opportunities', 'rwp-creator-suite' ),
            ),
        );
    }

    /**
     * Clear all notification data for user (GDPR compliance).
     *
     * @param int $user_id User ID.
     */
    public function clear_user_notification_data( $user_id ) {
        delete_user_meta( $user_id, 'rwp_notification_preferences' );
        delete_user_meta( $user_id, 'rwp_last_weekly_trends_notification' );
        delete_user_meta( $user_id, 'rwp_last_monthly_report_notification' );
        delete_user_meta( $user_id, 'rwp_last_opportunity_notification' );
        
        // Clear any cached data
        wp_cache_delete( "rwp_user_profile_{$user_id}", 'rwp_notifications' );
    }
}