<?php
/**
 * User Value API
 * 
 * Transforms anonymous analytics data into actionable insights for users.
 * Provides trend analysis, benchmarking, and optimization recommendations.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_User_Value_API {

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
     * Trend analyzer instance.
     *
     * @var RWP_Creator_Suite_Trend_Analyzer
     */
    private $trend_analyzer;

    /**
     * Benchmarking engine instance.
     *
     * @var RWP_Creator_Suite_Performance_Benchmarker
     */
    private $benchmarker;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
        $this->trend_analyzer = new RWP_Creator_Suite_Trend_Analyzer();
        $this->benchmarker = new RWP_Creator_Suite_Performance_Benchmarker();
    }

    /**
     * Initialize the User Value API.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Main user insights endpoint
        register_rest_route( $this->namespace, '/user-insights', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_user_insights' ),
            'permission_callback' => array( $this, 'check_user_consent' ),
        ) );

        // Trending report endpoint
        register_rest_route( $this->namespace, '/trending-report', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_trending_report' ),
            'permission_callback' => array( $this, 'check_user_consent' ),
            'args'                => array(
                'period' => array(
                    'type'              => 'string',
                    'enum'              => array( 'weekly', 'monthly' ),
                    'default'           => 'weekly',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'category' => array(
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // Performance benchmark endpoint
        register_rest_route( $this->namespace, '/performance-benchmark', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_performance_benchmark' ),
            'permission_callback' => array( $this, 'check_user_consent' ),
        ) );

        // Optimization suggestions endpoint
        register_rest_route( $this->namespace, '/optimization-suggestions', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_optimization_suggestions' ),
            'permission_callback' => array( $this, 'check_user_consent' ),
        ) );

        // User achievements endpoint
        register_rest_route( $this->namespace, '/achievements', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_user_achievements' ),
            'permission_callback' => array( $this, 'check_user_consent' ),
        ) );

        // Beta access status endpoint
        register_rest_route( $this->namespace, '/beta-access', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_beta_access_status' ),
            'permission_callback' => array( $this, 'check_user_consent' ),
        ) );

        // Monthly report generation endpoint
        register_rest_route( $this->namespace, '/monthly-report', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'generate_monthly_report' ),
            'permission_callback' => array( $this, 'check_user_consent' ),
            'args'                => array(
                'month' => array(
                    'type'              => 'string',
                    'format'            => 'date',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'year' => array(
                    'type'              => 'integer',
                    'minimum'           => 2020,
                    'maximum'           => (int) date('Y'),
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );
    }

    /**
     * Check if user has consented to analytics.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_user_consent( $request ) {
        $consent_manager = RWP_Creator_Suite_Consent_Manager::get_instance();
        $has_consent = $consent_manager->has_user_consented();

        if ( $has_consent !== true ) {
            return new WP_Error(
                'no_analytics_consent',
                __( 'Analytics consent required to access user insights.', 'rwp-creator-suite' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Get comprehensive user insights.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_user_insights( $request ) {
        $user_profile = $this->build_user_profile();

        $insights = array(
            'trending_report' => $this->trend_analyzer->generate_user_report( $user_profile ),
            'performance_benchmark' => $this->benchmarker->get_user_benchmarks( $user_profile ),
            'optimization_suggestions' => $this->get_personalized_suggestions( $user_profile ),
            'achievements' => $this->calculate_user_achievements( $user_profile ),
            'beta_access_status' => $this->check_beta_eligibility( $user_profile ),
            'summary_stats' => $this->get_user_summary_stats( $user_profile ),
        );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $insights,
            'generated_at' => current_time( 'mysql' ),
        ), 200 );
    }

    /**
     * Get trending report for user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_trending_report( $request ) {
        $period = $request->get_param( 'period' );
        $category = $request->get_param( 'category' );

        $user_profile = $this->build_user_profile();
        $report = $this->trend_analyzer->generate_trending_report( $user_profile, $period, $category );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $report,
            'period' => $period,
            'category' => $category,
        ), 200 );
    }

    /**
     * Get performance benchmark for user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_performance_benchmark( $request ) {
        $user_profile = $this->build_user_profile();
        $benchmarks = $this->benchmarker->get_detailed_benchmarks( $user_profile );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $benchmarks,
        ), 200 );
    }

    /**
     * Get optimization suggestions for user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_optimization_suggestions( $request ) {
        $user_profile = $this->build_user_profile();
        $suggestions = $this->get_personalized_suggestions( $user_profile );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $suggestions,
        ), 200 );
    }

    /**
     * Get user achievements.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_user_achievements( $request ) {
        $user_profile = $this->build_user_profile();
        $achievements = $this->calculate_user_achievements( $user_profile );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $achievements,
        ), 200 );
    }

    /**
     * Get beta access status.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_beta_access_status( $request ) {
        $user_profile = $this->build_user_profile();
        $beta_status = $this->check_beta_eligibility( $user_profile );

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $beta_status,
        ), 200 );
    }

    /**
     * Generate monthly report.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function generate_monthly_report( $request ) {
        $month = $request->get_param( 'month' ) ?: date( 'n' );
        $year = $request->get_param( 'year' ) ?: date( 'Y' );

        $user_profile = $this->build_user_profile();
        $report_data = $this->compile_monthly_report_data( $user_profile, $month, $year );

        // Store report in user meta for future reference
        $user_id = get_current_user_id();
        $report_key = "rwp_monthly_report_{$year}_{$month}";
        
        if ( $user_id ) {
            update_user_meta( $user_id, $report_key, $report_data );
        }

        return new WP_REST_Response( array(
            'success' => true,
            'data' => $report_data,
            'report_period' => array(
                'month' => $month,
                'year' => $year,
            ),
        ), 200 );
    }

    /**
     * Build user profile from analytics data.
     *
     * @return array
     */
    private function build_user_profile() {
        $session_hash = $this->analytics->get_session_hash();
        $user_id = get_current_user_id();

        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

        // Get user's analytics data from last 90 days
        $user_data = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, event_data, timestamp 
             FROM {$table_name} 
             WHERE anonymous_session_hash = %s 
             AND timestamp >= %s
             ORDER BY timestamp DESC",
            $session_hash,
            date( 'Y-m-d H:i:s', strtotime( '-90 days' ) )
        ), ARRAY_A );

        return $this->process_user_analytics_data( $user_data, $user_id );
    }

    /**
     * Process user analytics data into profile.
     *
     * @param array $user_data Raw analytics data.
     * @param int   $user_id User ID if logged in.
     * @return array
     */
    private function process_user_analytics_data( $user_data, $user_id = 0 ) {
        $profile = array(
            'user_id' => $user_id,
            'total_sessions' => count( $user_data ),
            'platforms' => array(),
            'tones' => array(),
            'features' => array(),
            'hashtag_usage' => array(),
            'template_usage' => array(),
            'activity_pattern' => array(),
            'engagement_metrics' => array(),
        );

        foreach ( $user_data as $event ) {
            $event_data = json_decode( $event['event_data'], true );
            $timestamp = strtotime( $event['timestamp'] );
            $hour = (int) date( 'H', $timestamp );
            $day_of_week = (int) date( 'N', $timestamp );

            // Track platform usage
            if ( ! empty( $event_data['platform'] ) ) {
                $platform = $event_data['platform'];
                if ( ! isset( $profile['platforms'][ $platform ] ) ) {
                    $profile['platforms'][ $platform ] = 0;
                }
                $profile['platforms'][ $platform ]++;
            }

            // Track tone usage
            if ( ! empty( $event_data['tone'] ) ) {
                $tone = $event_data['tone'];
                if ( ! isset( $profile['tones'][ $tone ] ) ) {
                    $profile['tones'][ $tone ] = 0;
                }
                $profile['tones'][ $tone ]++;
            }

            // Track feature usage
            if ( ! empty( $event_data['feature'] ) ) {
                $feature = $event_data['feature'];
                if ( ! isset( $profile['features'][ $feature ] ) ) {
                    $profile['features'][ $feature ] = 0;
                }
                $profile['features'][ $feature ]++;
            }

            // Track activity patterns
            if ( ! isset( $profile['activity_pattern'][ $hour ] ) ) {
                $profile['activity_pattern'][ $hour ] = 0;
            }
            $profile['activity_pattern'][ $hour ]++;

            // Track hashtag and template usage
            switch ( $event['event_type'] ) {
                case RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED:
                    if ( ! empty( $event_data['hashtag_hash'] ) ) {
                        $hash = $event_data['hashtag_hash'];
                        if ( ! isset( $profile['hashtag_usage'][ $hash ] ) ) {
                            $profile['hashtag_usage'][ $hash ] = array(
                                'count' => 0,
                                'platforms' => array(),
                            );
                        }
                        $profile['hashtag_usage'][ $hash ]['count']++;
                        if ( ! empty( $event_data['platform'] ) ) {
                            $profile['hashtag_usage'][ $hash ]['platforms'][] = $event_data['platform'];
                        }
                    }
                    break;

                case RWP_Creator_Suite_Anonymous_Analytics::EVENT_TEMPLATE_USED:
                    if ( ! empty( $event_data['template_hash'] ) ) {
                        $hash = $event_data['template_hash'];
                        if ( ! isset( $profile['template_usage'][ $hash ] ) ) {
                            $profile['template_usage'][ $hash ] = array(
                                'count' => 0,
                                'platforms' => array(),
                                'customizations' => 0,
                            );
                        }
                        $profile['template_usage'][ $hash ]['count']++;
                        if ( ! empty( $event_data['platform'] ) ) {
                            $profile['template_usage'][ $hash ]['platforms'][] = $event_data['platform'];
                        }
                        if ( ! empty( $event_data['customizations_made'] ) ) {
                            $profile['template_usage'][ $hash ]['customizations'] += (int) $event_data['customizations_made'];
                        }
                    }
                    break;
            }
        }

        // Calculate derived metrics
        $profile['most_used_platform'] = $this->get_most_used_value( $profile['platforms'] );
        $profile['most_used_tone'] = $this->get_most_used_value( $profile['tones'] );
        $profile['most_active_hour'] = $this->get_most_used_value( $profile['activity_pattern'] );
        $profile['platform_diversity'] = count( $profile['platforms'] );
        $profile['consistency_score'] = $this->calculate_consistency_score( $profile );

        return $profile;
    }

    /**
     * Get most used value from array.
     *
     * @param array $values Values with counts.
     * @return string|null
     */
    private function get_most_used_value( $values ) {
        if ( empty( $values ) ) {
            return null;
        }

        return array_key_first( array_slice( array_flip( array_flip( $values ) ), 0, 1, true ) );
    }

    /**
     * Calculate user consistency score.
     *
     * @param array $profile User profile data.
     * @return float
     */
    private function calculate_consistency_score( $profile ) {
        $factors = array();

        // Platform consistency (preference for specific platforms)
        if ( ! empty( $profile['platforms'] ) ) {
            $total_platform_usage = array_sum( $profile['platforms'] );
            $max_platform_usage = max( $profile['platforms'] );
            $factors['platform'] = $max_platform_usage / $total_platform_usage;
        }

        // Tone consistency
        if ( ! empty( $profile['tones'] ) ) {
            $total_tone_usage = array_sum( $profile['tones'] );
            $max_tone_usage = max( $profile['tones'] );
            $factors['tone'] = $max_tone_usage / $total_tone_usage;
        }

        // Activity pattern consistency
        if ( ! empty( $profile['activity_pattern'] ) ) {
            $activity_variance = $this->calculate_variance( $profile['activity_pattern'] );
            $factors['timing'] = 1 - min( 1, $activity_variance / 100 ); // Normalize to 0-1
        }

        return ! empty( $factors ) ? array_sum( $factors ) / count( $factors ) : 0;
    }

    /**
     * Calculate variance of an array.
     *
     * @param array $values Numeric values.
     * @return float
     */
    private function calculate_variance( $values ) {
        if ( count( $values ) < 2 ) {
            return 0;
        }

        $mean = array_sum( $values ) / count( $values );
        $sum_squares = 0;

        foreach ( $values as $value ) {
            $sum_squares += pow( $value - $mean, 2 );
        }

        return $sum_squares / count( $values );
    }

    /**
     * Get personalized optimization suggestions.
     *
     * @param array $user_profile User profile data.
     * @return array
     */
    private function get_personalized_suggestions( $user_profile ) {
        $suggestions = array();
        $community_data = $this->get_community_benchmarks();

        // Hashtag opportunities
        $hashtag_suggestions = $this->analyze_hashtag_opportunities( $user_profile, $community_data );
        $suggestions = array_merge( $suggestions, $hashtag_suggestions );

        // Platform optimization
        $platform_suggestions = $this->analyze_platform_opportunities( $user_profile, $community_data );
        $suggestions = array_merge( $suggestions, $platform_suggestions );

        // Timing optimization
        $timing_suggestions = $this->analyze_timing_opportunities( $user_profile, $community_data );
        $suggestions = array_merge( $suggestions, $timing_suggestions );

        // Feature adoption suggestions
        $feature_suggestions = $this->analyze_feature_opportunities( $user_profile, $community_data );
        $suggestions = array_merge( $suggestions, $feature_suggestions );

        // Sort by impact score
        usort( $suggestions, function( $a, $b ) {
            return $b['impact_score'] - $a['impact_score'];
        } );

        return array_slice( $suggestions, 0, 5 ); // Return top 5 suggestions
    }

    /**
     * Analyze hashtag opportunities.
     *
     * @param array $user_profile User profile data.
     * @param array $community_data Community benchmark data.
     * @return array
     */
    private function analyze_hashtag_opportunities( $user_profile, $community_data ) {
        $suggestions = array();

        if ( empty( $community_data['trending_hashtags'] ) ) {
            return $suggestions;
        }

        $user_hashtags = array_keys( $user_profile['hashtag_usage'] );
        $trending_hashtags = array_slice( $community_data['trending_hashtags'], 0, 10 );

        $unused_trending = array_diff_key( $trending_hashtags, array_flip( $user_hashtags ) );

        if ( ! empty( $unused_trending ) ) {
            $top_unused = array_slice( $unused_trending, 0, 3, true );
            
            $suggestions[] = array(
                'type' => 'hashtag_opportunity',
                'title' => __( 'Trending Hashtags You\'re Missing', 'rwp-creator-suite' ),
                'description' => sprintf(
                    __( 'Try these rising hashtags: %s', 'rwp-creator-suite' ),
                    implode( ', ', array_map( function( $tag ) { 
                        return '#' . $tag['display_name']; 
                    }, array_slice( $top_unused, 0, 3 ) ) )
                ),
                'impact' => 'High',
                'effort' => 'Low',
                'impact_score' => 85,
                'data' => $top_unused,
            );
        }

        return $suggestions;
    }

    /**
     * Analyze platform opportunities.
     *
     * @param array $user_profile User profile data.
     * @param array $community_data Community benchmark data.
     * @return array
     */
    private function analyze_platform_opportunities( $user_profile, $community_data ) {
        $suggestions = array();

        if ( empty( $community_data['platform_performance'] ) ) {
            return $suggestions;
        }

        $user_platforms = array_keys( $user_profile['platforms'] );
        $high_performing_platforms = array_filter( $community_data['platform_performance'], function( $data ) {
            return $data['engagement_score'] > 70; // Above average performance
        } );

        $unused_platforms = array_diff_key( $high_performing_platforms, array_flip( $user_platforms ) );

        if ( ! empty( $unused_platforms ) ) {
            $top_platform = array_key_first( $unused_platforms );
            
            $suggestions[] = array(
                'type' => 'platform_opportunity',
                'title' => sprintf( __( 'Consider %s for Better Reach', 'rwp-creator-suite' ), ucfirst( $top_platform ) ),
                'description' => sprintf(
                    __( '%s shows %d%% higher engagement than average. Perfect for your content style.', 'rwp-creator-suite' ),
                    ucfirst( $top_platform ),
                    $unused_platforms[ $top_platform ]['engagement_score']
                ),
                'impact' => 'Medium',
                'effort' => 'Medium',
                'impact_score' => 70,
                'data' => $unused_platforms[ $top_platform ],
            );
        }

        return $suggestions;
    }

    /**
     * Analyze timing opportunities.
     *
     * @param array $user_profile User profile data.
     * @param array $community_data Community benchmark data.
     * @return array
     */
    private function analyze_timing_opportunities( $user_profile, $community_data ) {
        $suggestions = array();

        if ( empty( $community_data['optimal_timing'] ) || empty( $user_profile['most_active_hour'] ) ) {
            return $suggestions;
        }

        $user_hour = $user_profile['most_active_hour'];
        $optimal_hours = $community_data['optimal_timing']['peak_hours'];

        if ( ! in_array( $user_hour, $optimal_hours ) ) {
            $suggestions[] = array(
                'type' => 'timing_optimization',
                'title' => __( 'Optimize Your Posting Time', 'rwp-creator-suite' ),
                'description' => sprintf(
                    __( 'You usually post at %d:00, but %d:00-%d:00 shows %d%% better engagement.', 'rwp-creator-suite' ),
                    $user_hour,
                    $optimal_hours[0],
                    $optimal_hours[0] + 2,
                    $community_data['optimal_timing']['improvement_potential']
                ),
                'impact' => 'Medium',
                'effort' => 'Low',
                'impact_score' => 60,
                'data' => array(
                    'current_hour' => $user_hour,
                    'suggested_hours' => $optimal_hours,
                ),
            );
        }

        return $suggestions;
    }

    /**
     * Analyze feature opportunities.
     *
     * @param array $user_profile User profile data.
     * @param array $community_data Community benchmark data.
     * @return array
     */
    private function analyze_feature_opportunities( $user_profile, $community_data ) {
        $suggestions = array();

        if ( empty( $community_data['popular_features'] ) ) {
            return $suggestions;
        }

        $user_features = array_keys( $user_profile['features'] );
        $popular_features = array_slice( $community_data['popular_features'], 0, 5 );

        $unused_features = array_diff_key( $popular_features, array_flip( $user_features ) );

        if ( ! empty( $unused_features ) ) {
            $top_feature = array_key_first( $unused_features );
            
            $suggestions[] = array(
                'type' => 'feature_opportunity',
                'title' => sprintf( __( 'Try the %s Feature', 'rwp-creator-suite' ), ucwords( str_replace( '_', ' ', $top_feature ) ) ),
                'description' => sprintf(
                    __( '%d%% of active creators use this feature for better content variety.', 'rwp-creator-suite' ),
                    $unused_features[ $top_feature ]['adoption_rate']
                ),
                'impact' => 'Low',
                'effort' => 'Low',
                'impact_score' => 40,
                'data' => $unused_features[ $top_feature ],
            );
        }

        return $suggestions;
    }

    /**
     * Calculate user achievements.
     *
     * @param array $user_profile User profile data.
     * @return array
     */
    private function calculate_user_achievements( $user_profile ) {
        $achievements = array();
        $community_data = $this->get_community_benchmarks();

        // Trend Spotter achievement
        $trend_score = $this->calculate_trend_spotter_score( $user_profile, $community_data );
        $achievements['trend_spotter'] = array(
            'name' => __( 'Trend Spotter', 'rwp-creator-suite' ),
            'description' => __( 'Early adoption of trending hashtags', 'rwp-creator-suite' ),
            'level' => $this->get_achievement_level( $trend_score, array( 20, 50, 100 ) ),
            'progress' => $trend_score,
            'next_milestone' => $this->get_next_milestone( $trend_score, array( 20, 50, 100 ) ),
            'icon' => '🔥',
        );

        // Data Contributor achievement
        $contribution_score = $this->calculate_contribution_score( $user_profile );
        $achievements['data_contributor'] = array(
            'name' => __( 'Data Contributor', 'rwp-creator-suite' ),
            'description' => __( 'Anonymous data points contributed', 'rwp-creator-suite' ),
            'level' => $this->get_achievement_level( $contribution_score, array( 100, 500, 1000 ) ),
            'progress' => $contribution_score,
            'next_milestone' => $this->get_next_milestone( $contribution_score, array( 100, 500, 1000 ) ),
            'icon' => '📊',
        );

        // Platform Master achievement
        $platform_score = $user_profile['platform_diversity'];
        $achievements['platform_master'] = array(
            'name' => __( 'Platform Master', 'rwp-creator-suite' ),
            'description' => __( 'Multi-platform optimization success', 'rwp-creator-suite' ),
            'level' => $this->get_achievement_level( $platform_score, array( 2, 4, 6 ) ),
            'progress' => $platform_score,
            'next_milestone' => $this->get_next_milestone( $platform_score, array( 2, 4, 6 ) ),
            'icon' => '🎯',
        );

        return $achievements;
    }

    /**
     * Calculate trend spotter score.
     *
     * @param array $user_profile User profile data.
     * @param array $community_data Community benchmark data.
     * @return int
     */
    private function calculate_trend_spotter_score( $user_profile, $community_data ) {
        // This would require more complex analysis of hashtag adoption timing
        // For now, return a score based on hashtag diversity and usage
        return min( 100, count( $user_profile['hashtag_usage'] ) * 5 );
    }

    /**
     * Calculate contribution score.
     *
     * @param array $user_profile User profile data.
     * @return int
     */
    private function calculate_contribution_score( $user_profile ) {
        $score = 0;
        
        // Points for hashtag contributions
        $score += count( $user_profile['hashtag_usage'] ) * 2;
        
        // Points for template usage
        $score += count( $user_profile['template_usage'] );
        
        // Points for platform diversity
        $score += $user_profile['platform_diversity'] * 3;
        
        // Points for consistency
        $score += (int) ( $user_profile['consistency_score'] * 50 );

        return min( 1000, $score );
    }

    /**
     * Get achievement level based on score and thresholds.
     *
     * @param int   $score Current score.
     * @param array $thresholds Level thresholds.
     * @return int
     */
    private function get_achievement_level( $score, $thresholds ) {
        $level = 0;
        foreach ( $thresholds as $threshold ) {
            if ( $score >= $threshold ) {
                $level++;
            } else {
                break;
            }
        }
        return $level;
    }

    /**
     * Get next milestone for achievement.
     *
     * @param int   $score Current score.
     * @param array $thresholds Level thresholds.
     * @return int|null
     */
    private function get_next_milestone( $score, $thresholds ) {
        foreach ( $thresholds as $threshold ) {
            if ( $score < $threshold ) {
                return $threshold;
            }
        }
        return null; // Max level reached
    }

    /**
     * Check beta eligibility.
     *
     * @param array $user_profile User profile data.
     * @return array
     */
    private function check_beta_eligibility( $user_profile ) {
        $contribution_score = $this->calculate_contribution_score( $user_profile );
        $is_eligible = $contribution_score >= 50;

        return array(
            'eligible' => $is_eligible,
            'contribution_score' => $contribution_score,
            'required_score' => 50,
            'available_features' => $is_eligible ? $this->get_available_beta_features() : array(),
        );
    }

    /**
     * Get available beta features.
     *
     * @return array
     */
    private function get_available_beta_features() {
        return array(
            'advanced_hashtag_analytics' => __( 'Advanced Hashtag Analytics', 'rwp-creator-suite' ),
            'custom_template_builder' => __( 'Custom Template Builder', 'rwp-creator-suite' ),
            'multi_platform_scheduler' => __( 'Multi-Platform Scheduler', 'rwp-creator-suite' ),
            'advanced_performance_tracking' => __( 'Advanced Performance Tracking', 'rwp-creator-suite' ),
        );
    }

    /**
     * Get user summary stats.
     *
     * @param array $user_profile User profile data.
     * @return array
     */
    private function get_user_summary_stats( $user_profile ) {
        return array(
            'total_content_pieces' => array_sum( $user_profile['features'] ),
            'hashtags_used' => count( $user_profile['hashtag_usage'] ),
            'platforms_active' => count( $user_profile['platforms'] ),
            'consistency_score' => round( $user_profile['consistency_score'] * 100, 1 ),
            'most_productive_hour' => $user_profile['most_active_hour'],
            'favorite_platform' => $user_profile['most_used_platform'],
            'preferred_tone' => $user_profile['most_used_tone'],
        );
    }

    /**
     * Get community benchmarks for comparison.
     *
     * @return array
     */
    private function get_community_benchmarks() {
        // This would be cached data computed periodically
        $cache_key = 'rwp_community_benchmarks';
        $benchmarks = get_transient( $cache_key );

        if ( false === $benchmarks ) {
            $benchmarks = $this->compute_community_benchmarks();
            set_transient( $cache_key, $benchmarks, HOUR_IN_SECONDS );
        }

        return $benchmarks;
    }

    /**
     * Compute community benchmarks from analytics data.
     *
     * @return array
     */
    private function compute_community_benchmarks() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

        $benchmarks = array(
            'trending_hashtags' => $this->get_trending_hashtags_data(),
            'platform_performance' => $this->get_platform_performance_data(),
            'optimal_timing' => $this->get_optimal_timing_data(),
            'popular_features' => $this->get_popular_features_data(),
        );

        return $benchmarks;
    }

    /**
     * Get trending hashtags data.
     *
     * @return array
     */
    private function get_trending_hashtags_data() {
        // Implementation would analyze hashtag growth rates
        return array();
    }

    /**
     * Get platform performance data.
     *
     * @return array
     */
    private function get_platform_performance_data() {
        // Implementation would analyze platform engagement metrics
        return array();
    }

    /**
     * Get optimal timing data.
     *
     * @return array
     */
    private function get_optimal_timing_data() {
        // Implementation would analyze posting time effectiveness
        return array(
            'peak_hours' => array( 10, 14, 19 ),
            'improvement_potential' => 25,
        );
    }

    /**
     * Get popular features data.
     *
     * @return array
     */
    private function get_popular_features_data() {
        // Implementation would analyze feature usage patterns
        return array();
    }

    /**
     * Compile monthly report data.
     *
     * @param array $user_profile User profile data.
     * @param int   $month Month.
     * @param int   $year Year.
     * @return array
     */
    private function compile_monthly_report_data( $user_profile, $month, $year ) {
        return array(
            'period' => array(
                'month' => $month,
                'year' => $year,
                'display' => date( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ),
            ),
            'summary' => $this->get_user_summary_stats( $user_profile ),
            'trending_insights' => $this->trend_analyzer->generate_monthly_trends( $user_profile, $month, $year ),
            'performance_comparison' => $this->benchmarker->get_monthly_comparison( $user_profile, $month, $year ),
            'achievements_earned' => $this->get_monthly_achievements( $user_profile, $month, $year ),
            'recommendations' => $this->get_personalized_suggestions( $user_profile ),
        );
    }

    /**
     * Get achievements earned in specific month.
     *
     * @param array $user_profile User profile data.
     * @param int   $month Month.
     * @param int   $year Year.
     * @return array
     */
    private function get_monthly_achievements( $user_profile, $month, $year ) {
        // Implementation would track achievement progress over time
        return array();
    }
}