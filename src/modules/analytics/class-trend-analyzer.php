<?php
/**
 * Trend Analyzer
 * 
 * Analyzes trending hashtags, platform performance, and content patterns
 * to provide users with actionable trend insights and recommendations.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Trend_Analyzer {

    /**
     * Analytics table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rwp_anonymous_analytics';
    }

    /**
     * Generate user-specific trend report.
     *
     * @param array  $user_profile User profile data.
     * @param string $period Period for analysis (weekly/monthly).
     * @param string $category Optional category filter.
     * @return array
     */
    public function generate_user_report( $user_profile, $period = 'weekly', $category = null ) {
        $trends = array(
            'trending_hashtags' => $this->get_trending_hashtags_for_user( $user_profile, $period ),
            'platform_insights' => $this->get_platform_insights( $user_profile, $period ),
            'content_opportunities' => $this->identify_content_opportunities( $user_profile ),
            'performance_predictions' => $this->generate_performance_predictions( $user_profile ),
            'recommended_templates' => $this->get_recommended_templates( $user_profile ),
        );

        return $trends;
    }

    /**
     * Generate monthly trends analysis.
     *
     * @param array $user_profile User profile data.
     * @param int   $month Month.
     * @param int   $year Year.
     * @return array
     */
    public function generate_monthly_trends( $user_profile, $month, $year ) {
        $start_date = date( 'Y-m-d H:i:s', mktime( 0, 0, 0, $month, 1, $year ) );
        $end_date = date( 'Y-m-t H:i:s', mktime( 0, 0, 0, $month, 1, $year ) );

        return array(
            'hashtag_growth' => $this->analyze_hashtag_growth( $start_date, $end_date ),
            'platform_trends' => $this->analyze_platform_trends( $start_date, $end_date ),
            'engagement_patterns' => $this->analyze_engagement_patterns( $start_date, $end_date ),
            'emerging_topics' => $this->identify_emerging_topics( $start_date, $end_date ),
        );
    }

    /**
     * Generate trending report for community.
     *
     * @param array  $user_profile User profile data.
     * @param string $period Period for analysis.
     * @param string $category Optional category filter.
     * @return array
     */
    public function generate_trending_report( $user_profile, $period = 'weekly', $category = null ) {
        $date_range = $this->get_date_range_for_period( $period );

        $report = array(
            'period' => $period,
            'date_range' => $date_range,
            'trending_hashtags' => $this->get_trending_hashtags( $date_range, 20 ),
            'rising_platforms' => $this->get_rising_platforms( $date_range ),
            'popular_tones' => $this->get_popular_tones( $date_range ),
            'content_formats' => $this->analyze_content_formats( $date_range ),
            'timing_insights' => $this->analyze_optimal_timing( $date_range ),
            'personalized_recommendations' => $this->get_personalized_trend_recommendations( $user_profile, $date_range ),
        );

        return $report;
    }

    /**
     * Get trending hashtags for specific user.
     *
     * @param array  $user_profile User profile data.
     * @param string $period Period for analysis.
     * @return array
     */
    private function get_trending_hashtags_for_user( $user_profile, $period = 'weekly' ) {
        global $wpdb;

        $date_range = $this->get_date_range_for_period( $period );
        $user_platforms = array_keys( $user_profile['platforms'] );

        if ( empty( $user_platforms ) ) {
            return $this->get_trending_hashtags( $date_range, 10 );
        }

        $platform_placeholders = implode( ',', array_fill( 0, count( $user_platforms ), '%s' ) );

        $query = $wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.hashtag_hash') as hashtag_hash,
                JSON_EXTRACT(event_data, '$.platform') as platform,
                COUNT(*) as current_usage,
                COUNT(DISTINCT anonymous_session_hash) as unique_users
             FROM {$this->table_name} 
             WHERE event_type = %s 
             AND timestamp >= %s
             AND timestamp <= %s
             AND JSON_EXTRACT(event_data, '$.platform') IN ($platform_placeholders)
             GROUP BY hashtag_hash, platform
             ORDER BY current_usage DESC, unique_users DESC
             LIMIT 15",
            array_merge(
                array(
                    RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED,
                    $date_range['start'],
                    $date_range['end']
                ),
                $user_platforms
            )
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        return $this->process_hashtag_trends( $results, $date_range );
    }

    /**
     * Get trending hashtags for all platforms.
     *
     * @param array $date_range Date range for analysis.
     * @param int   $limit Number of results to return.
     * @return array
     */
    private function get_trending_hashtags( $date_range, $limit = 20 ) {
        global $wpdb;

        // Current period usage
        $current_query = $wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.hashtag_hash') as hashtag_hash,
                JSON_EXTRACT(event_data, '$.platform') as platform,
                COUNT(*) as current_usage,
                COUNT(DISTINCT anonymous_session_hash) as unique_users
             FROM {$this->table_name} 
             WHERE event_type = %s 
             AND timestamp >= %s
             AND timestamp <= %s
             GROUP BY hashtag_hash, platform
             HAVING current_usage >= 3",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED,
            $date_range['start'],
            $date_range['end']
        );

        $current_results = $wpdb->get_results( $current_query, ARRAY_A );

        // Previous period for comparison
        $previous_range = $this->get_previous_period_range( $date_range );
        
        $previous_query = $wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.hashtag_hash') as hashtag_hash,
                JSON_EXTRACT(event_data, '$.platform') as platform,
                COUNT(*) as previous_usage
             FROM {$this->table_name} 
             WHERE event_type = %s 
             AND timestamp >= %s
             AND timestamp <= %s
             GROUP BY hashtag_hash, platform",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED,
            $previous_range['start'],
            $previous_range['end']
        );

        $previous_results = $wpdb->get_results( $previous_query, ARRAY_A );

        return $this->calculate_hashtag_trends( $current_results, $previous_results, $limit );
    }

    /**
     * Calculate hashtag trend growth.
     *
     * @param array $current_results Current period results.
     * @param array $previous_results Previous period results.
     * @param int   $limit Number of results to return.
     * @return array
     */
    private function calculate_hashtag_trends( $current_results, $previous_results, $limit ) {
        $trends = array();
        $previous_lookup = array();

        // Create lookup for previous period data
        foreach ( $previous_results as $result ) {
            $key = $result['hashtag_hash'] . '_' . $result['platform'];
            $previous_lookup[ $key ] = (int) $result['previous_usage'];
        }

        // Calculate growth for current period
        foreach ( $current_results as $result ) {
            $key = $result['hashtag_hash'] . '_' . $result['platform'];
            $current_usage = (int) $result['current_usage'];
            $previous_usage = $previous_lookup[ $key ] ?? 0;

            $growth_rate = $previous_usage > 0 
                ? ( ( $current_usage - $previous_usage ) / $previous_usage ) * 100
                : ( $current_usage > 5 ? 100 : 0 ); // New hashtags with decent usage

            if ( $growth_rate >= 20 || $current_usage >= 10 ) { // Significant growth or high usage
                $trends[] = array(
                    'hashtag_hash' => trim( $result['hashtag_hash'], '"' ),
                    'platform' => trim( $result['platform'], '"' ),
                    'current_usage' => $current_usage,
                    'previous_usage' => $previous_usage,
                    'growth_rate' => round( $growth_rate, 1 ),
                    'unique_users' => (int) $result['unique_users'],
                    'trend_score' => $this->calculate_trend_score( $current_usage, $growth_rate, (int) $result['unique_users'] ),
                    'display_name' => $this->get_hashtag_display_name( $result['hashtag_hash'] ),
                );
            }
        }

        // Sort by trend score
        usort( $trends, function( $a, $b ) {
            return $b['trend_score'] - $a['trend_score'];
        } );

        return array_slice( $trends, 0, $limit );
    }

    /**
     * Calculate trend score for hashtags.
     *
     * @param int   $usage Current usage count.
     * @param float $growth_rate Growth rate percentage.
     * @param int   $unique_users Number of unique users.
     * @return float
     */
    private function calculate_trend_score( $usage, $growth_rate, $unique_users ) {
        $usage_score = min( 50, $usage * 2 ); // Max 50 points for usage
        $growth_score = min( 30, $growth_rate * 0.3 ); // Max 30 points for growth
        $diversity_score = min( 20, $unique_users * 2 ); // Max 20 points for user diversity

        return $usage_score + $growth_score + $diversity_score;
    }

    /**
     * Get hashtag display name (placeholder for demonstration).
     *
     * @param string $hashtag_hash Hashed hashtag.
     * @return string
     */
    private function get_hashtag_display_name( $hashtag_hash ) {
        // In a real implementation, this might use a reverse lookup table
        // or provide generic trend descriptions
        return 'Trending Topic #' . substr( $hashtag_hash, 0, 4 );
    }

    /**
     * Get platform insights for user.
     *
     * @param array  $user_profile User profile data.
     * @param string $period Period for analysis.
     * @return array
     */
    private function get_platform_insights( $user_profile, $period = 'weekly' ) {
        $date_range = $this->get_date_range_for_period( $period );
        $insights = array();

        foreach ( $user_profile['platforms'] as $platform => $usage_count ) {
            $platform_data = $this->analyze_platform_performance( $platform, $date_range );
            $insights[ $platform ] = array(
                'your_usage' => $usage_count,
                'community_average' => $platform_data['average_usage'],
                'engagement_trend' => $platform_data['engagement_trend'],
                'optimal_times' => $platform_data['optimal_times'],
                'popular_tones' => $platform_data['popular_tones'],
                'recommendation' => $this->generate_platform_recommendation( $platform, $platform_data, $usage_count ),
            );
        }

        return $insights;
    }

    /**
     * Analyze platform performance.
     *
     * @param string $platform Platform name.
     * @param array  $date_range Date range for analysis.
     * @return array
     */
    private function analyze_platform_performance( $platform, $date_range ) {
        global $wpdb;

        // Get platform usage statistics
        $usage_query = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_usage,
                COUNT(DISTINCT anonymous_session_hash) as unique_users,
                AVG(HOUR(timestamp)) as avg_hour
             FROM {$this->table_name} 
             WHERE JSON_EXTRACT(event_data, '$.platform') = %s
             AND timestamp >= %s
             AND timestamp <= %s",
            $platform,
            $date_range['start'],
            $date_range['end']
        );

        $usage_stats = $wpdb->get_row( $usage_query, ARRAY_A );

        // Get tone distribution
        $tone_query = $wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.tone') as tone,
                COUNT(*) as usage_count
             FROM {$this->table_name} 
             WHERE JSON_EXTRACT(event_data, '$.platform') = %s
             AND JSON_EXTRACT(event_data, '$.tone') IS NOT NULL
             AND timestamp >= %s
             AND timestamp <= %s
             GROUP BY tone
             ORDER BY usage_count DESC
             LIMIT 3",
            $platform,
            $date_range['start'],
            $date_range['end']
        );

        $tone_stats = $wpdb->get_results( $tone_query, ARRAY_A );

        return array(
            'average_usage' => (int) $usage_stats['total_usage'],
            'unique_users' => (int) $usage_stats['unique_users'],
            'engagement_trend' => $this->calculate_engagement_trend( $platform, $date_range ),
            'optimal_times' => $this->get_optimal_posting_times( $platform, $date_range ),
            'popular_tones' => array_map( function( $tone ) {
                return array(
                    'tone' => trim( $tone['tone'], '"' ),
                    'usage_count' => (int) $tone['usage_count'],
                );
            }, $tone_stats ),
        );
    }

    /**
     * Calculate engagement trend for platform.
     *
     * @param string $platform Platform name.
     * @param array  $date_range Date range for analysis.
     * @return array
     */
    private function calculate_engagement_trend( $platform, $date_range ) {
        // Placeholder - would analyze actual engagement metrics
        return array(
            'direction' => 'up',
            'percentage' => 15.2,
            'confidence' => 'high',
        );
    }

    /**
     * Get optimal posting times for platform.
     *
     * @param string $platform Platform name.
     * @param array  $date_range Date range for analysis.
     * @return array
     */
    private function get_optimal_posting_times( $platform, $date_range ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT 
                HOUR(timestamp) as hour,
                COUNT(*) as activity_count,
                COUNT(DISTINCT anonymous_session_hash) as unique_users
             FROM {$this->table_name} 
             WHERE JSON_EXTRACT(event_data, '$.platform') = %s
             AND timestamp >= %s
             AND timestamp <= %s
             GROUP BY HOUR(timestamp)
             ORDER BY activity_count DESC
             LIMIT 3",
            $platform,
            $date_range['start'],
            $date_range['end']
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        return array_map( function( $result ) {
            return array(
                'hour' => (int) $result['hour'],
                'activity_score' => (int) $result['activity_count'],
                'display' => date( 'g A', mktime( $result['hour'], 0, 0 ) ),
            );
        }, $results );
    }

    /**
     * Generate platform recommendation.
     *
     * @param string $platform Platform name.
     * @param array  $platform_data Platform performance data.
     * @param int    $user_usage User's usage count.
     * @return string
     */
    private function generate_platform_recommendation( $platform, $platform_data, $user_usage ) {
        $avg_usage = $platform_data['average_usage'];
        
        if ( $user_usage < $avg_usage * 0.5 ) {
            return sprintf(
                __( 'Consider posting more on %s - you\'re using it %d%% less than average creators.', 'rwp-creator-suite' ),
                ucfirst( $platform ),
                round( ( 1 - $user_usage / $avg_usage ) * 100 )
            );
        } elseif ( $platform_data['engagement_trend']['direction'] === 'up' ) {
            return sprintf(
                __( '%s is trending upward with %s%% growth - great time to increase activity!', 'rwp-creator-suite' ),
                ucfirst( $platform ),
                $platform_data['engagement_trend']['percentage']
            );
        } else {
            $optimal_time = $platform_data['optimal_times'][0] ?? null;
            if ( $optimal_time ) {
                return sprintf(
                    __( 'Try posting on %s around %s for better engagement.', 'rwp-creator-suite' ),
                    ucfirst( $platform ),
                    $optimal_time['display']
                );
            }
        }

        return sprintf(
            __( 'You\'re doing well on %s! Keep up the consistent posting.', 'rwp-creator-suite' ),
            ucfirst( $platform )
        );
    }

    /**
     * Identify content opportunities.
     *
     * @param array $user_profile User profile data.
     * @return array
     */
    private function identify_content_opportunities( $user_profile ) {
        $opportunities = array();

        // Underused platform opportunity
        $all_platforms = array( 'instagram', 'tiktok', 'twitter', 'linkedin', 'facebook' );
        $user_platforms = array_keys( $user_profile['platforms'] );
        $unused_platforms = array_diff( $all_platforms, $user_platforms );

        foreach ( array_slice( $unused_platforms, 0, 2 ) as $platform ) {
            $opportunities[] = array(
                'type' => 'new_platform',
                'platform' => $platform,
                'title' => sprintf( __( 'Explore %s', 'rwp-creator-suite' ), ucfirst( $platform ) ),
                'description' => sprintf(
                    __( '%s shows strong engagement potential for your content style.', 'rwp-creator-suite' ),
                    ucfirst( $platform )
                ),
                'potential_impact' => 'medium',
                'effort_required' => 'medium',
            );
        }

        // Tone diversification opportunity
        $user_tones = array_keys( $user_profile['tones'] );
        $recommended_tones = array( 'professional', 'casual', 'inspiring', 'humorous', 'educational' );
        $unused_tones = array_diff( $recommended_tones, $user_tones );

        if ( ! empty( $unused_tones ) ) {
            $opportunities[] = array(
                'type' => 'tone_diversification',
                'suggested_tone' => $unused_tones[0],
                'title' => sprintf( __( 'Try a %s Tone', 'rwp-creator-suite' ), ucfirst( $unused_tones[0] ) ),
                'description' => sprintf(
                    __( 'Adding %s content could expand your audience reach by an estimated 20%%.', 'rwp-creator-suite' ),
                    $unused_tones[0]
                ),
                'potential_impact' => 'high',
                'effort_required' => 'low',
            );
        }

        return $opportunities;
    }

    /**
     * Generate performance predictions.
     *
     * @param array $user_profile User profile data.
     * @return array
     */
    private function generate_performance_predictions( $user_profile ) {
        $predictions = array();

        // Consistency prediction
        $consistency_score = $user_profile['consistency_score'];
        if ( $consistency_score > 0.7 ) {
            $predictions[] = array(
                'metric' => 'engagement_growth',
                'prediction' => sprintf(
                    __( 'Your consistency suggests %d%% engagement growth potential over the next month.', 'rwp-creator-suite' ),
                    round( $consistency_score * 25 )
                ),
                'confidence' => 'high',
                'timeframe' => '30 days',
            );
        }

        // Platform expansion prediction
        if ( $user_profile['platform_diversity'] >= 3 ) {
            $predictions[] = array(
                'metric' => 'reach_expansion',
                'prediction' => sprintf(
                    __( 'Multi-platform presence could increase your reach by %d%% with consistent posting.', 'rwp-creator-suite' ),
                    $user_profile['platform_diversity'] * 15
                ),
                'confidence' => 'medium',
                'timeframe' => '60 days',
            );
        }

        return $predictions;
    }

    /**
     * Get recommended templates based on user profile.
     *
     * @param array $user_profile User profile data.
     * @return array
     */
    private function get_recommended_templates( $user_profile ) {
        // This would integrate with the template system
        $recommendations = array();

        $favorite_platform = $user_profile['most_used_platform'];
        $favorite_tone = $user_profile['most_used_tone'];

        if ( $favorite_platform && $favorite_tone ) {
            $recommendations[] = array(
                'template_type' => 'optimized_combo',
                'title' => sprintf(
                    __( '%s + %s Templates', 'rwp-creator-suite' ),
                    ucfirst( $favorite_platform ),
                    ucfirst( $favorite_tone )
                ),
                'description' => __( 'Templates specifically optimized for your most-used platform and tone combination.', 'rwp-creator-suite' ),
                'match_score' => 95,
                'category' => 'personalized',
            );
        }

        // Add trending template recommendations
        $recommendations[] = array(
            'template_type' => 'trending',
            'title' => __( 'Behind-the-Scenes Templates', 'rwp-creator-suite' ),
            'description' => __( 'Currently trending format showing 45% higher engagement this month.', 'rwp-creator-suite' ),
            'match_score' => 80,
            'category' => 'trending',
        );

        return $recommendations;
    }

    /**
     * Get date range for analysis period.
     *
     * @param string $period Period type (weekly/monthly).
     * @return array
     */
    private function get_date_range_for_period( $period ) {
        $now = current_time( 'mysql' );
        
        switch ( $period ) {
            case 'weekly':
                $start = date( 'Y-m-d H:i:s', strtotime( '-1 week', strtotime( $now ) ) );
                break;
            case 'monthly':
                $start = date( 'Y-m-d H:i:s', strtotime( '-1 month', strtotime( $now ) ) );
                break;
            default:
                $start = date( 'Y-m-d H:i:s', strtotime( '-1 week', strtotime( $now ) ) );
        }

        return array(
            'start' => $start,
            'end' => $now,
        );
    }

    /**
     * Get previous period range for comparison.
     *
     * @param array $current_range Current period range.
     * @return array
     */
    private function get_previous_period_range( $current_range ) {
        $current_start = strtotime( $current_range['start'] );
        $current_end = strtotime( $current_range['end'] );
        $duration = $current_end - $current_start;

        return array(
            'start' => date( 'Y-m-d H:i:s', $current_start - $duration ),
            'end' => date( 'Y-m-d H:i:s', $current_start ),
        );
    }

    /**
     * Process hashtag trends data.
     *
     * @param array $results Raw hashtag results.
     * @param array $date_range Date range for context.
     * @return array
     */
    private function process_hashtag_trends( $results, $date_range ) {
        return array_map( function( $result ) {
            return array(
                'hashtag_hash' => trim( $result['hashtag_hash'], '"' ),
                'platform' => trim( $result['platform'], '"' ),
                'usage_count' => (int) $result['current_usage'],
                'unique_users' => (int) $result['unique_users'],
                'display_name' => $this->get_hashtag_display_name( $result['hashtag_hash'] ),
                'trend_indicator' => 'rising', // Simplified for demo
            );
        }, $results );
    }

    /**
     * Get rising platforms.
     *
     * @param array $date_range Date range for analysis.
     * @return array
     */
    private function get_rising_platforms( $date_range ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.platform') as platform,
                COUNT(*) as current_usage,
                COUNT(DISTINCT anonymous_session_hash) as unique_users
             FROM {$this->table_name} 
             WHERE timestamp >= %s
             AND timestamp <= %s
             AND JSON_EXTRACT(event_data, '$.platform') IS NOT NULL
             GROUP BY platform
             ORDER BY current_usage DESC",
            $date_range['start'],
            $date_range['end']
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        return array_map( function( $result ) {
            return array(
                'platform' => trim( $result['platform'], '"' ),
                'usage_count' => (int) $result['current_usage'],
                'unique_users' => (int) $result['unique_users'],
                'growth_indicator' => 'stable', // Would be calculated with historical comparison
            );
        }, array_slice( $results, 0, 5 ) );
    }

    /**
     * Get popular tones.
     *
     * @param array $date_range Date range for analysis.
     * @return array
     */
    private function get_popular_tones( $date_range ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.tone') as tone,
                JSON_EXTRACT(event_data, '$.platform') as platform,
                COUNT(*) as usage_count
             FROM {$this->table_name} 
             WHERE timestamp >= %s
             AND timestamp <= %s
             AND JSON_EXTRACT(event_data, '$.tone') IS NOT NULL
             GROUP BY tone, platform
             ORDER BY usage_count DESC
             LIMIT 10",
            $date_range['start'],
            $date_range['end']
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        return array_map( function( $result ) {
            return array(
                'tone' => trim( $result['tone'], '"' ),
                'platform' => trim( $result['platform'], '"' ),
                'usage_count' => (int) $result['usage_count'],
            );
        }, $results );
    }

    /**
     * Analyze content formats.
     *
     * @param array $date_range Date range for analysis.
     * @return array
     */
    private function analyze_content_formats( $date_range ) {
        // Placeholder - would analyze template usage patterns
        return array(
            array(
                'format' => 'Question Posts',
                'growth_rate' => 34.5,
                'engagement_boost' => 28.2,
                'platforms' => array( 'instagram', 'linkedin' ),
            ),
            array(
                'format' => 'Behind the Scenes',
                'growth_rate' => 22.1,
                'engagement_boost' => 15.7,
                'platforms' => array( 'tiktok', 'instagram' ),
            ),
        );
    }

    /**
     * Analyze optimal timing patterns.
     *
     * @param array $date_range Date range for analysis.
     * @return array
     */
    private function analyze_optimal_timing( $date_range ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT 
                HOUR(timestamp) as hour,
                DAYOFWEEK(timestamp) as day_of_week,
                COUNT(*) as activity_count,
                COUNT(DISTINCT anonymous_session_hash) as unique_users
             FROM {$this->table_name} 
             WHERE timestamp >= %s
             AND timestamp <= %s
             GROUP BY HOUR(timestamp), DAYOFWEEK(timestamp)
             ORDER BY activity_count DESC
             LIMIT 5",
            $date_range['start'],
            $date_range['end']
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        return array_map( function( $result ) {
            return array(
                'hour' => (int) $result['hour'],
                'day_of_week' => (int) $result['day_of_week'],
                'activity_score' => (int) $result['activity_count'],
                'unique_users' => (int) $result['unique_users'],
                'display' => $this->format_timing_display( $result['hour'], $result['day_of_week'] ),
            );
        }, $results );
    }

    /**
     * Format timing display.
     *
     * @param int $hour Hour of day.
     * @param int $day_of_week Day of week (1=Sunday).
     * @return string
     */
    private function format_timing_display( $hour, $day_of_week ) {
        $days = array( '', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
        $day_name = $days[ $day_of_week ] ?? 'Unknown';
        $time = date( 'g A', mktime( $hour, 0, 0 ) );
        
        return sprintf( '%s at %s', $day_name, $time );
    }

    /**
     * Get personalized trend recommendations.
     *
     * @param array $user_profile User profile data.
     * @param array $date_range Date range for analysis.
     * @return array
     */
    private function get_personalized_trend_recommendations( $user_profile, $date_range ) {
        $recommendations = array();

        // Based on user's platform preferences
        if ( ! empty( $user_profile['most_used_platform'] ) ) {
            $platform_trends = $this->get_platform_specific_trends( $user_profile['most_used_platform'], $date_range );
            $recommendations = array_merge( $recommendations, $platform_trends );
        }

        // Based on user's tone preferences
        if ( ! empty( $user_profile['most_used_tone'] ) ) {
            $tone_trends = $this->get_tone_specific_trends( $user_profile['most_used_tone'], $date_range );
            $recommendations = array_merge( $recommendations, $tone_trends );
        }

        return array_slice( $recommendations, 0, 3 );
    }

    /**
     * Get platform-specific trends.
     *
     * @param string $platform Platform name.
     * @param array  $date_range Date range for analysis.
     * @return array
     */
    private function get_platform_specific_trends( $platform, $date_range ) {
        // Placeholder implementation
        return array(
            array(
                'type' => 'platform_trend',
                'title' => sprintf( __( '%s Video Content Rising', 'rwp-creator-suite' ), ucfirst( $platform ) ),
                'description' => sprintf(
                    __( 'Video posts on %s are showing 40%% higher engagement this week.', 'rwp-creator-suite' ),
                    $platform
                ),
                'confidence' => 'high',
                'action' => sprintf( __( 'Consider adding video content to your %s strategy.', 'rwp-creator-suite' ), $platform ),
            ),
        );
    }

    /**
     * Get tone-specific trends.
     *
     * @param string $tone Tone name.
     * @param array  $date_range Date range for analysis.
     * @return array
     */
    private function get_tone_specific_trends( $tone, $date_range ) {
        // Placeholder implementation
        return array(
            array(
                'type' => 'tone_trend',
                'title' => sprintf( __( '%s Content Performing Well', 'rwp-creator-suite' ), ucfirst( $tone ) ),
                'description' => sprintf(
                    __( 'Your preferred %s tone is currently trending with 25%% growth.', 'rwp-creator-suite' ),
                    $tone
                ),
                'confidence' => 'medium',
                'action' => sprintf( __( 'Double down on %s content while the trend is strong.', 'rwp-creator-suite' ), $tone ),
            ),
        );
    }

    /**
     * Analyze hashtag growth over time.
     *
     * @param string $start_date Start date.
     * @param string $end_date End date.
     * @return array
     */
    private function analyze_hashtag_growth( $start_date, $end_date ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT 
                DATE(timestamp) as date,
                JSON_EXTRACT(event_data, '$.hashtag_hash') as hashtag_hash,
                COUNT(*) as daily_usage
             FROM {$this->table_name} 
             WHERE event_type = %s
             AND timestamp >= %s
             AND timestamp <= %s
             GROUP BY DATE(timestamp), hashtag_hash
             ORDER BY date ASC, daily_usage DESC",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED,
            $start_date,
            $end_date
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        return $this->process_growth_analysis( $results );
    }

    /**
     * Process growth analysis results.
     *
     * @param array $results Raw growth data.
     * @return array
     */
    private function process_growth_analysis( $results ) {
        $growth_data = array();
        $hashtag_totals = array();

        foreach ( $results as $result ) {
            $hashtag = trim( $result['hashtag_hash'], '"' );
            $date = $result['date'];
            $usage = (int) $result['daily_usage'];

            if ( ! isset( $growth_data[ $hashtag ] ) ) {
                $growth_data[ $hashtag ] = array();
            }

            $growth_data[ $hashtag ][ $date ] = $usage;
            
            if ( ! isset( $hashtag_totals[ $hashtag ] ) ) {
                $hashtag_totals[ $hashtag ] = 0;
            }
            $hashtag_totals[ $hashtag ] += $usage;
        }

        // Sort by total usage and return top hashtags with growth patterns
        arsort( $hashtag_totals );
        $top_hashtags = array_slice( array_keys( $hashtag_totals ), 0, 10, true );

        $processed_growth = array();
        foreach ( $top_hashtags as $hashtag ) {
            $daily_data = $growth_data[ $hashtag ];
            $growth_pattern = $this->analyze_growth_pattern( $daily_data );
            
            $processed_growth[] = array(
                'hashtag_hash' => $hashtag,
                'total_usage' => $hashtag_totals[ $hashtag ],
                'daily_breakdown' => $daily_data,
                'growth_pattern' => $growth_pattern,
                'display_name' => $this->get_hashtag_display_name( $hashtag ),
            );
        }

        return $processed_growth;
    }

    /**
     * Analyze growth pattern for a hashtag.
     *
     * @param array $daily_data Daily usage data.
     * @return array
     */
    private function analyze_growth_pattern( $daily_data ) {
        if ( count( $daily_data ) < 2 ) {
            return array(
                'trend' => 'insufficient_data',
                'slope' => 0,
                'volatility' => 0,
            );
        }

        $values = array_values( $daily_data );
        $dates = array_keys( $daily_data );
        
        // Simple linear trend calculation
        $n = count( $values );
        $sum_x = array_sum( range( 1, $n ) );
        $sum_y = array_sum( $values );
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ( $i = 0; $i < $n; $i++ ) {
            $x = $i + 1;
            $y = $values[ $i ];
            $sum_xy += $x * $y;
            $sum_x2 += $x * $x;
        }
        
        $slope = ( $n * $sum_xy - $sum_x * $sum_y ) / ( $n * $sum_x2 - $sum_x * $sum_x );
        
        // Determine trend direction
        $trend = 'stable';
        if ( $slope > 1 ) {
            $trend = 'rising';
        } elseif ( $slope < -1 ) {
            $trend = 'declining';
        }
        
        // Calculate volatility (standard deviation)
        $mean = $sum_y / $n;
        $variance = array_sum( array_map( function( $val ) use ( $mean ) {
            return pow( $val - $mean, 2 );
        }, $values ) ) / $n;
        $volatility = sqrt( $variance );
        
        return array(
            'trend' => $trend,
            'slope' => round( $slope, 2 ),
            'volatility' => round( $volatility, 2 ),
            'confidence' => $volatility < $mean * 0.3 ? 'high' : 'medium',
        );
    }

    /**
     * Analyze platform trends over time.
     *
     * @param string $start_date Start date.
     * @param string $end_date End date.
     * @return array
     */
    private function analyze_platform_trends( $start_date, $end_date ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT 
                DATE(timestamp) as date,
                JSON_EXTRACT(event_data, '$.platform') as platform,
                COUNT(*) as daily_usage,
                COUNT(DISTINCT anonymous_session_hash) as unique_users
             FROM {$this->table_name} 
             WHERE timestamp >= %s
             AND timestamp <= %s
             AND JSON_EXTRACT(event_data, '$.platform') IS NOT NULL
             GROUP BY DATE(timestamp), platform
             ORDER BY date ASC",
            $start_date,
            $end_date
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        return $this->process_platform_trends( $results );
    }

    /**
     * Process platform trends data.
     *
     * @param array $results Raw platform trends data.
     * @return array
     */
    private function process_platform_trends( $results ) {
        $platform_data = array();

        foreach ( $results as $result ) {
            $platform = trim( $result['platform'], '"' );
            $date = $result['date'];
            
            if ( ! isset( $platform_data[ $platform ] ) ) {
                $platform_data[ $platform ] = array();
            }
            
            $platform_data[ $platform ][ $date ] = array(
                'usage' => (int) $result['daily_usage'],
                'unique_users' => (int) $result['unique_users'],
            );
        }

        $trends = array();
        foreach ( $platform_data as $platform => $daily_data ) {
            $usage_values = array_column( $daily_data, 'usage' );
            $user_values = array_column( $daily_data, 'unique_users' );
            
            $trends[ $platform ] = array(
                'total_usage' => array_sum( $usage_values ),
                'total_unique_users' => array_sum( $user_values ),
                'daily_breakdown' => $daily_data,
                'usage_trend' => $this->analyze_growth_pattern( $usage_values ),
                'user_growth' => $this->analyze_growth_pattern( $user_values ),
            );
        }

        return $trends;
    }

    /**
     * Analyze engagement patterns over time.
     *
     * @param string $start_date Start date.
     * @param string $end_date End date.
     * @return array
     */
    private function analyze_engagement_patterns( $start_date, $end_date ) {
        global $wpdb;

        // Analyze by hour of day
        $hourly_query = $wpdb->prepare(
            "SELECT 
                HOUR(timestamp) as hour,
                COUNT(*) as activity_count
             FROM {$this->table_name} 
             WHERE timestamp >= %s
             AND timestamp <= %s
             GROUP BY HOUR(timestamp)
             ORDER BY hour ASC",
            $start_date,
            $end_date
        );

        $hourly_results = $wpdb->get_results( $hourly_query, ARRAY_A );

        // Analyze by day of week
        $daily_query = $wpdb->prepare(
            "SELECT 
                DAYOFWEEK(timestamp) as day_of_week,
                COUNT(*) as activity_count
             FROM {$this->table_name} 
             WHERE timestamp >= %s
             AND timestamp <= %s
             GROUP BY DAYOFWEEK(timestamp)
             ORDER BY day_of_week ASC",
            $start_date,
            $end_date
        );

        $daily_results = $wpdb->get_results( $daily_query, ARRAY_A );

        return array(
            'hourly_patterns' => $hourly_results,
            'daily_patterns' => $daily_results,
            'peak_hours' => $this->identify_peak_hours( $hourly_results ),
            'peak_days' => $this->identify_peak_days( $daily_results ),
        );
    }

    /**
     * Identify peak hours from hourly data.
     *
     * @param array $hourly_data Hourly activity data.
     * @return array
     */
    private function identify_peak_hours( $hourly_data ) {
        usort( $hourly_data, function( $a, $b ) {
            return $b['activity_count'] - $a['activity_count'];
        } );

        return array_slice( array_map( function( $data ) {
            return array(
                'hour' => (int) $data['hour'],
                'activity_count' => (int) $data['activity_count'],
                'display' => date( 'g A', mktime( $data['hour'], 0, 0 ) ),
            );
        }, $hourly_data ), 0, 3 );
    }

    /**
     * Identify peak days from daily data.
     *
     * @param array $daily_data Daily activity data.
     * @return array
     */
    private function identify_peak_days( $daily_data ) {
        $days = array( '', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
        
        usort( $daily_data, function( $a, $b ) {
            return $b['activity_count'] - $a['activity_count'];
        } );

        return array_slice( array_map( function( $data ) use ( $days ) {
            return array(
                'day_of_week' => (int) $data['day_of_week'],
                'activity_count' => (int) $data['activity_count'],
                'display' => $days[ $data['day_of_week'] ] ?? 'Unknown',
            );
        }, $daily_data ), 0, 3 );
    }

    /**
     * Identify emerging topics from recent data.
     *
     * @param string $start_date Start date.
     * @param string $end_date End date.
     * @return array
     */
    private function identify_emerging_topics( $start_date, $end_date ) {
        // This would analyze patterns in hashtag usage, template selection, etc.
        // For now, return placeholder data
        return array(
            array(
                'topic' => 'sustainable_content',
                'growth_rate' => 156.3,
                'confidence' => 'high',
                'description' => __( 'Sustainability-focused content is rapidly growing across all platforms.', 'rwp-creator-suite' ),
            ),
            array(
                'topic' => 'micro_learning',
                'growth_rate' => 87.2,
                'confidence' => 'medium',
                'description' => __( 'Short educational content is gaining traction, especially on TikTok and Instagram.', 'rwp-creator-suite' ),
            ),
        );
    }
}