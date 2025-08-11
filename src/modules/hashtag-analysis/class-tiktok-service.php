<?php
/**
 * TikTok Service
 * 
 * Handles direct TikTok API integration for hashtag analysis.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_TikTok_Service {

    /**
     * TikTok API base URL.
     */
    private const API_BASE_URL = 'https://open-api.tiktok.com';

    /**
     * Rate limit: 600 requests per minute.
     */
    private const RATE_LIMIT = 600;
    private const RATE_LIMIT_WINDOW = 60; // seconds

    /**
     * API credentials (would be set in WordPress options).
     */
    private $app_id;
    private $app_secret;
    private $access_token;

    /**
     * Constructor.
     */
    public function __construct() {
        // In production, these would be stored in WordPress options or constants
        $this->app_id = get_option( 'rwp_tiktok_app_id', '' );
        $this->app_secret = get_option( 'rwp_tiktok_app_secret', '' );
        $this->access_token = get_option( 'rwp_tiktok_access_token', '' );
    }

    /**
     * Search for hashtag data on TikTok.
     *
     * @param string $hashtag The hashtag to search for.
     * @param int    $limit   Number of results to return.
     * @return array|WP_Error Search results or error.
     */
    public function search_hashtag( $hashtag, $limit = 10 ) {
        // Check rate limit
        $rate_limit_check = $this->check_rate_limit();
        if ( is_wp_error( $rate_limit_check ) ) {
            return $rate_limit_check;
        }

        // For now, return mock data since we need actual TikTok API credentials
        if ( empty( $this->app_id ) || empty( $this->access_token ) ) {
            return $this->get_mock_search_data( $hashtag, $limit );
        }

        // Prepare API request
        $endpoint = '/v2/video/query/';
        $params = array(
            'fields' => 'id,title,video_description,duration,cover_image_url,embed_link,like_count,comment_count,share_count,view_count',
            'hashtag' => $hashtag,
            'max_count' => min( $limit, 20 ), // TikTok API limit
        );

        $response = $this->make_api_request( $endpoint, $params );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->format_search_results( $response['data'] ?? array(), $hashtag );
    }

    /**
     * Get hashtag analytics from TikTok.
     *
     * @param string $hashtag   The hashtag to analyze.
     * @param string $timeframe The timeframe for analytics.
     * @return array|WP_Error Analytics data or error.
     */
    public function get_hashtag_analytics( $hashtag, $timeframe = '7d' ) {
        // Check rate limit
        $rate_limit_check = $this->check_rate_limit();
        if ( is_wp_error( $rate_limit_check ) ) {
            return $rate_limit_check;
        }

        // For now, return mock data since we need actual TikTok API credentials
        if ( empty( $this->app_id ) || empty( $this->access_token ) ) {
            return $this->get_mock_analytics_data( $hashtag, $timeframe );
        }

        // In production, this would make multiple API calls to gather analytics
        $search_results = $this->search_hashtag( $hashtag, 50 );
        
        if ( is_wp_error( $search_results ) ) {
            return $search_results;
        }

        return $this->calculate_analytics( $search_results, $timeframe );
    }

    /**
     * Get trending hashtags from TikTok.
     *
     * @return array|WP_Error Trending hashtags or error.
     */
    public function get_trending_hashtags() {
        // For now, return mock data since we need actual TikTok API credentials
        if ( empty( $this->app_id ) || empty( $this->access_token ) ) {
            return array(
                'viral',
                'trending',
                'fyp',
                'foryou',
                'tiktok',
                'dance',
                'comedy',
                'lifestyle',
                'tutorial',
                'music'
            );
        }

        // In production, this would query TikTok's trending endpoints
        // Note: TikTok doesn't have a public trending hashtags endpoint,
        // so this would need to be inferred from popular videos
        
        return array();
    }

    /**
     * Make API request to TikTok.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @return array|WP_Error Response data or error.
     */
    private function make_api_request( $endpoint, $params = array() ) {
        if ( empty( $this->access_token ) ) {
            return new WP_Error( 'no_access_token', 'TikTok API access token not configured.', array( 'status' => 500 ) );
        }

        $url = self::API_BASE_URL . $endpoint;
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode( $params ),
            'method' => 'POST',
            'timeout' => 30,
        );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return new WP_Error( 'invalid_response', 'Invalid response from TikTok API.', array( 'status' => 500 ) );
        }

        if ( isset( $data['error'] ) ) {
            return new WP_Error( 'api_error', $data['error']['message'] ?? 'TikTok API error.', array( 'status' => 400 ) );
        }

        // Record successful API call for rate limiting
        $this->record_api_call();

        return $data;
    }

    /**
     * Check rate limit before making API calls.
     *
     * @return true|WP_Error True if within limit, error if exceeded.
     */
    private function check_rate_limit() {
        $transient_key = 'rwp_tiktok_api_calls';
        $api_calls = get_transient( $transient_key ) ?: array();

        // Clean old calls outside the window
        $current_time = time();
        $api_calls = array_filter( $api_calls, function( $timestamp ) use ( $current_time ) {
            return ( $current_time - $timestamp ) < self::RATE_LIMIT_WINDOW;
        });

        // Check if we're at the rate limit
        if ( count( $api_calls ) >= self::RATE_LIMIT ) {
            return new WP_Error( 
                'rate_limit_exceeded', 
                'TikTok API rate limit exceeded. Please try again later.', 
                array( 'status' => 429 ) 
            );
        }

        return true;
    }

    /**
     * Record an API call for rate limiting.
     */
    private function record_api_call() {
        $transient_key = 'rwp_tiktok_api_calls';
        $api_calls = get_transient( $transient_key ) ?: array();
        
        $api_calls[] = time();
        
        set_transient( $transient_key, $api_calls, self::RATE_LIMIT_WINDOW );
    }

    /**
     * Format search results into standardized format.
     *
     * @param array  $raw_results Raw API results.
     * @param string $hashtag     The searched hashtag.
     * @return array Formatted results.
     */
    private function format_search_results( $raw_results, $hashtag ) {
        $formatted = array();

        foreach ( $raw_results as $video ) {
            $formatted[] = array(
                'id' => $video['id'] ?? '',
                'title' => $video['title'] ?? '',
                'description' => $video['video_description'] ?? '',
                'thumbnail' => $video['cover_image_url'] ?? '',
                'url' => $video['embed_link'] ?? '',
                'metrics' => array(
                    'likes' => $video['like_count'] ?? 0,
                    'comments' => $video['comment_count'] ?? 0,
                    'shares' => $video['share_count'] ?? 0,
                    'views' => $video['view_count'] ?? 0,
                ),
                'duration' => $video['duration'] ?? 0,
                'platform' => 'tiktok',
                'hashtag' => $hashtag,
            );
        }

        return $formatted;
    }

    /**
     * Calculate analytics from search results.
     *
     * @param array  $results   Search results.
     * @param string $timeframe Analytics timeframe.
     * @return array Analytics data.
     */
    private function calculate_analytics( $results, $timeframe ) {
        $total_posts = count( $results );
        $total_likes = array_sum( array_column( array_column( $results, 'metrics' ), 'likes' ) );
        $total_comments = array_sum( array_column( array_column( $results, 'metrics' ), 'comments' ) );
        $total_shares = array_sum( array_column( array_column( $results, 'metrics' ), 'shares' ) );
        $total_views = array_sum( array_column( array_column( $results, 'metrics' ), 'views' ) );

        $engagement_rate = $total_views > 0 ? ( ( $total_likes + $total_comments + $total_shares ) / $total_views ) * 100 : 0;

        return array(
            'total_posts' => $total_posts,
            'total_engagement' => $total_likes + $total_comments + $total_shares,
            'total_views' => $total_views,
            'engagement_rate' => round( $engagement_rate, 2 ),
            'average_likes' => $total_posts > 0 ? round( $total_likes / $total_posts ) : 0,
            'average_comments' => $total_posts > 0 ? round( $total_comments / $total_posts ) : 0,
            'average_shares' => $total_posts > 0 ? round( $total_shares / $total_posts ) : 0,
            'top_posts' => array_slice( 
                usort( $results, function( $a, $b ) {
                    return $b['metrics']['likes'] - $a['metrics']['likes'];
                }), 
                0, 
                5 
            ),
            'timeframe' => $timeframe,
        );
    }

    /**
     * Get mock search data for development/demo purposes.
     *
     * @param string $hashtag The hashtag.
     * @param int    $limit   Number of results.
     * @return array Mock data.
     */
    private function get_mock_search_data( $hashtag, $limit ) {
        $mock_results = array();
        
        for ( $i = 1; $i <= $limit; $i++ ) {
            $mock_results[] = array(
                'id' => 'tiktok_' . $hashtag . '_' . $i,
                'title' => "TikTok video about #{$hashtag} #{$i}",
                'description' => "This is a demo TikTok video about #{$hashtag}. In production, this would come from the real TikTok API.",
                'thumbnail' => "https://picsum.photos/400/400?random={$i}",
                'url' => "https://www.tiktok.com/@demo/video/{$i}",
                'metrics' => array(
                    'likes' => rand( 100, 10000 ),
                    'comments' => rand( 10, 500 ),
                    'shares' => rand( 5, 200 ),
                    'views' => rand( 1000, 100000 ),
                ),
                'duration' => rand( 15, 60 ),
                'platform' => 'tiktok',
                'hashtag' => $hashtag,
            );
        }

        return $mock_results;
    }

    /**
     * Get mock analytics data for development/demo purposes.
     *
     * @param string $hashtag   The hashtag.
     * @param string $timeframe The timeframe.
     * @return array Mock analytics data.
     */
    private function get_mock_analytics_data( $hashtag, $timeframe ) {
        $base_multiplier = array(
            '1d' => 1,
            '7d' => 7,
            '30d' => 30,
        );

        $multiplier = $base_multiplier[ $timeframe ] ?? 7;
        
        return array(
            'total_posts' => rand( 50, 500 ) * $multiplier,
            'total_engagement' => rand( 10000, 100000 ) * $multiplier,
            'total_views' => rand( 100000, 1000000 ) * $multiplier,
            'engagement_rate' => rand( 200, 800 ) / 100, // 2-8%
            'average_likes' => rand( 100, 1000 ),
            'average_comments' => rand( 10, 100 ),
            'average_shares' => rand( 5, 50 ),
            'growth_rate' => rand( -10, 50 ), // -10% to +50% growth
            'timeframe' => $timeframe,
            'hashtag' => $hashtag,
            'platform' => 'tiktok',
        );
    }
}