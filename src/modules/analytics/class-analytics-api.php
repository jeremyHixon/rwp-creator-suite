<?php
/**
 * Analytics API
 * 
 * REST API endpoints for retrieving aggregated analytics data.
 * All endpoints require admin privileges and return only anonymized data.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.6.0
 */

defined( 'ABSPATH' ) || exit;

require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/common/traits/trait-api-response.php';
require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/common/traits/trait-api-permissions.php';

class RWP_Creator_Suite_Analytics_API {
    use RWP_Creator_Suite_API_Response_Trait;
    use RWP_Creator_Suite_API_Permissions_Trait;

    /**
     * API namespace.
     *
     * @var string
     */
    private $namespace = 'rwp-creator-suite/v1';

    /**
     * Analytics system instance.
     *
     * @var RWP_Creator_Suite_Anonymous_Analytics
     */
    private $analytics;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
    }

    /**
     * Initialize the Analytics API.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Analytics summary endpoint
        register_rest_route( $this->namespace, '/analytics/summary', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_analytics_summary' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
            'args'                => array(
                'start_date' => array(
                    'type'              => 'string',
                    'format'            => 'date',
                    'default'           => date( 'Y-m-d', strtotime( '-30 days' ) ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'end_date' => array(
                    'type'              => 'string',
                    'format'            => 'date',
                    'default'           => date( 'Y-m-d' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // Popular hashtags endpoint
        register_rest_route( $this->namespace, '/analytics/hashtags', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_popular_hashtags' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
            'args'                => array(
                'limit' => array(
                    'type'              => 'integer',
                    'default'           => 20,
                    'minimum'           => 1,
                    'maximum'           => 100,
                    'sanitize_callback' => 'absint',
                ),
                'platform' => array(
                    'type'              => 'string',
                    'enum'              => array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // Platform statistics endpoint
        register_rest_route( $this->namespace, '/analytics/platforms', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_platform_stats' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
            'args'                => array(
                'start_date' => array(
                    'type'              => 'string',
                    'format'            => 'date',
                    'default'           => date( 'Y-m-d', strtotime( '-30 days' ) ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'end_date' => array(
                    'type'              => 'string',
                    'format'            => 'date',
                    'default'           => date( 'Y-m-d' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // Feature usage statistics endpoint
        register_rest_route( $this->namespace, '/analytics/features', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_feature_stats' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
            'args'                => array(
                'start_date' => array(
                    'type'              => 'string',
                    'format'            => 'date',
                    'default'           => date( 'Y-m-d', strtotime( '-30 days' ) ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'end_date' => array(
                    'type'              => 'string',
                    'format'            => 'date',
                    'default'           => date( 'Y-m-d' ),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // Template usage statistics endpoint
        register_rest_route( $this->namespace, '/analytics/templates', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_template_stats' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
            'args'                => array(
                'limit' => array(
                    'type'              => 'integer',
                    'default'           => 10,
                    'minimum'           => 1,
                    'maximum'           => 50,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // Usage trends endpoint
        register_rest_route( $this->namespace, '/analytics/trends', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_usage_trends' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
            'args'                => array(
                'period' => array(
                    'type'              => 'string',
                    'enum'              => array( 'daily', 'weekly', 'monthly' ),
                    'default'           => 'daily',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'days' => array(
                    'type'              => 'integer',
                    'default'           => 30,
                    'minimum'           => 7,
                    'maximum'           => 365,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // Consent statistics endpoint
        register_rest_route( $this->namespace, '/analytics/consent', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_consent_stats' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );
    }

    /**
     * Check if user has admin permission.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function check_admin_permission( $request ) {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get analytics summary.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_analytics_summary( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date = $request->get_param( 'end_date' );

        $summary = $this->analytics->get_analytics_summary( array(
            'start_date' => $start_date,
            'end_date' => $end_date,
        ) );

        // Process and aggregate the data
        $processed_summary = $this->process_analytics_summary( $summary, $start_date, $end_date );

        return $this->success_response(
            $processed_summary,
            __( 'Analytics summary retrieved successfully', 'rwp-creator-suite' ),
            array(
                'period' => array(
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ),
            )
        );
    }

    /**
     * Get popular hashtags.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_popular_hashtags( $request ) {
        $limit = $request->get_param( 'limit' );
        $platform = $request->get_param( 'platform' );

        $hashtags = $this->analytics->get_popular_hashtags( $limit );

        // Filter by platform if specified
        if ( $platform ) {
            $hashtags = array_filter( $hashtags, function( $hashtag ) use ( $platform ) {
                return $hashtag['platform'] === $platform;
            } );
        }

        // Remove hash values for security - only return usage counts
        $processed_hashtags = array_map( function( $hashtag ) {
            return array(
                'platform' => $hashtag['platform'],
                'usage_count' => (int) $hashtag['usage_count'],
                'hashtag_id' => substr( $hashtag['hashtag_hash'], 0, 8 ), // Short ID for reference
            );
        }, $hashtags );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $processed_hashtags,
            'total' => count( $processed_hashtags ),
        ), 200 );
    }

    /**
     * Get platform statistics.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_platform_stats( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date = $request->get_param( 'end_date' );

        $stats = $this->analytics->get_platform_stats();

        // Add additional processing for date range
        $filtered_stats = $this->filter_platform_stats_by_date( $stats, $start_date, $end_date );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $filtered_stats,
            'period' => array(
                'start_date' => $start_date,
                'end_date' => $end_date,
            ),
        ), 200 );
    }

    /**
     * Get feature usage statistics.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_feature_stats( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date = $request->get_param( 'end_date' );

        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

        $query = $wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.feature') as feature,
                COUNT(*) as usage_count,
                COUNT(DISTINCT anonymous_session_hash) as unique_users
             FROM {$table_name} 
             WHERE event_type IN (%s, %s, %s)
             AND timestamp >= %s
             AND timestamp <= %s
             AND JSON_EXTRACT(event_data, '$.feature') IS NOT NULL
             GROUP BY feature
             ORDER BY usage_count DESC",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_CONTENT_GENERATED,
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_FEATURE_USED,
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_TEMPLATE_USED,
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        $processed_results = array_map( function( $result ) {
            return array(
                'feature' => trim( $result['feature'], '"' ),
                'usage_count' => (int) $result['usage_count'],
                'unique_users' => (int) $result['unique_users'],
            );
        }, $results );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $processed_results,
            'period' => array(
                'start_date' => $start_date,
                'end_date' => $end_date,
            ),
        ), 200 );
    }

    /**
     * Get template usage statistics.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_template_stats( $request ) {
        $limit = $request->get_param( 'limit' );

        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

        $query = $wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.template_hash') as template_hash,
                JSON_EXTRACT(event_data, '$.platform') as platform,
                JSON_EXTRACT(event_data, '$.completion_status') as completion_status,
                AVG(CAST(JSON_EXTRACT(event_data, '$.customizations_made') AS UNSIGNED)) as avg_customizations,
                COUNT(*) as usage_count
             FROM {$table_name} 
             WHERE event_type = %s
             AND timestamp >= %s
             GROUP BY template_hash, platform
             ORDER BY usage_count DESC
             LIMIT %d",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_TEMPLATE_USED,
            date( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
            $limit
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        $processed_results = array_map( function( $result ) {
            return array(
                'template_id' => substr( trim( $result['template_hash'], '"' ), 0, 8 ),
                'platform' => trim( $result['platform'], '"' ),
                'completion_status' => trim( $result['completion_status'], '"' ),
                'avg_customizations' => round( (float) $result['avg_customizations'], 1 ),
                'usage_count' => (int) $result['usage_count'],
            );
        }, $results );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $processed_results,
            'total' => count( $processed_results ),
        ), 200 );
    }

    /**
     * Get usage trends over time.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_usage_trends( $request ) {
        $period = $request->get_param( 'period' );
        $days = $request->get_param( 'days' );

        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

        $date_format = $this->get_date_format_for_period( $period );
        $start_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $query = $wpdb->prepare(
            "SELECT 
                DATE_FORMAT(timestamp, %s) as period_date,
                event_type,
                COUNT(*) as event_count,
                COUNT(DISTINCT anonymous_session_hash) as unique_sessions
             FROM {$table_name} 
             WHERE timestamp >= %s
             GROUP BY period_date, event_type
             ORDER BY period_date ASC",
            $date_format,
            $start_date
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        $processed_trends = $this->process_usage_trends( $results, $period );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $processed_trends,
            'period' => $period,
            'days' => $days,
        ), 200 );
    }

    /**
     * Get consent statistics.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_consent_stats( $request ) {
        $consent_manager = RWP_Creator_Suite_Consent_Manager::get_instance();
        $stats = $consent_manager->get_consent_stats();

        // Add additional metrics
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'rwp_anonymous_analytics';

        // Get unique sessions with analytics data
        $analytics_sessions = $wpdb->get_var(
            "SELECT COUNT(DISTINCT anonymous_session_hash) 
             FROM {$analytics_table} 
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        $stats['analytics_sessions_30_days'] = (int) $analytics_sessions;

        // Calculate consent rate
        if ( $stats['total_users_with_preference'] > 0 ) {
            $stats['consent_rate'] = round( 
                ( $stats['consented_users'] / $stats['total_users_with_preference'] ) * 100, 
                1 
            );
        } else {
            $stats['consent_rate'] = 0;
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $stats,
        ), 200 );
    }

    /**
     * Process analytics summary data.
     *
     * @param array  $summary Raw summary data.
     * @param string $start_date Start date.
     * @param string $end_date End date.
     * @return array
     */
    private function process_analytics_summary( $summary, $start_date, $end_date ) {
        $totals = array(
            'total_events' => 0,
            'unique_sessions' => 0,
            'hashtags_tracked' => 0,
            'content_generated' => 0,
            'templates_used' => 0,
        );

        $event_types = array();
        $daily_breakdown = array();

        foreach ( $summary as $row ) {
            $totals['total_events'] += (int) $row['event_count'];
            
            if ( ! isset( $event_types[ $row['event_type'] ] ) ) {
                $event_types[ $row['event_type'] ] = array(
                    'count' => 0,
                    'unique_sessions' => 0,
                );
            }
            
            $event_types[ $row['event_type'] ]['count'] += (int) $row['event_count'];
            $event_types[ $row['event_type'] ]['unique_sessions'] += (int) $row['unique_sessions'];

            // Daily breakdown
            if ( ! isset( $daily_breakdown[ $row['event_date'] ] ) ) {
                $daily_breakdown[ $row['event_date'] ] = array(
                    'date' => $row['event_date'],
                    'events' => 0,
                    'sessions' => 0,
                );
            }

            $daily_breakdown[ $row['event_date'] ]['events'] += (int) $row['event_count'];
            $daily_breakdown[ $row['event_date'] ]['sessions'] += (int) $row['unique_sessions'];

            // Specific event type counting
            switch ( $row['event_type'] ) {
                case RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED:
                    $totals['hashtags_tracked'] += (int) $row['event_count'];
                    break;
                case RWP_Creator_Suite_Anonymous_Analytics::EVENT_CONTENT_GENERATED:
                    $totals['content_generated'] += (int) $row['event_count'];
                    break;
                case RWP_Creator_Suite_Anonymous_Analytics::EVENT_TEMPLATE_USED:
                    $totals['templates_used'] += (int) $row['event_count'];
                    break;
            }
        }

        // Get unique sessions across all events
        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';
        $totals['unique_sessions'] = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT anonymous_session_hash) 
             FROM {$table_name} 
             WHERE timestamp >= %s AND timestamp <= %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ) );

        return array(
            'totals' => $totals,
            'event_types' => $event_types,
            'daily_breakdown' => array_values( $daily_breakdown ),
        );
    }

    /**
     * Filter platform stats by date range.
     *
     * @param array  $stats Platform stats.
     * @param string $start_date Start date.
     * @param string $end_date End date.
     * @return array
     */
    private function filter_platform_stats_by_date( $stats, $start_date, $end_date ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

        $query = $wpdb->prepare(
            "SELECT 
                platform,
                COUNT(*) as total_usage,
                COUNT(DISTINCT anonymous_session_hash) as unique_users
             FROM {$table_name} 
             WHERE platform != '' 
             AND timestamp >= %s
             AND timestamp <= %s
             GROUP BY platform
             ORDER BY total_usage DESC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Get date format for SQL based on period.
     *
     * @param string $period Period type.
     * @return string
     */
    private function get_date_format_for_period( $period ) {
        switch ( $period ) {
            case 'weekly':
                return '%Y-%u'; // Year-week
            case 'monthly':
                return '%Y-%m'; // Year-month
            case 'daily':
            default:
                return '%Y-%m-%d'; // Year-month-day
        }
    }

    /**
     * Process usage trends data.
     *
     * @param array  $results Raw trend data.
     * @param string $period Period type.
     * @return array
     */
    private function process_usage_trends( $results, $period ) {
        $trends = array();

        foreach ( $results as $row ) {
            $date = $row['period_date'];
            
            if ( ! isset( $trends[ $date ] ) ) {
                $trends[ $date ] = array(
                    'period' => $date,
                    'total_events' => 0,
                    'unique_sessions' => 0,
                    'event_breakdown' => array(),
                );
            }

            $trends[ $date ]['total_events'] += (int) $row['event_count'];
            $trends[ $date ]['unique_sessions'] += (int) $row['unique_sessions'];
            $trends[ $date ]['event_breakdown'][ $row['event_type'] ] = (int) $row['event_count'];
        }

        return array_values( $trends );
    }
}