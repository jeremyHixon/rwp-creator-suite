<?php
/**
 * Aggregator Service
 * 
 * Handles third-party aggregator integration for Instagram and Facebook data.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Aggregator_Service {

    /**
     * Supported aggregator providers.
     */
    private const PROVIDERS = array(
        'apify' => 'Apify',
        'data365' => 'Data365',
    );

    /**
     * Current provider configuration.
     */
    private $provider;
    private $api_key;
    private $api_url;

    /**
     * Constructor.
     */
    public function __construct() {
        // In production, these would be stored in WordPress options
        $this->provider = get_option( 'rwp_aggregator_provider', 'apify' );
        $this->api_key = get_option( 'rwp_aggregator_api_key', '' );
        
        // Set API URL based on provider
        switch ( $this->provider ) {
            case 'apify':
                $this->api_url = 'https://api.apify.com/v2';
                break;
            case 'data365':
                $this->api_url = 'https://api.data365.co/v1';
                break;
            default:
                $this->api_url = '';
        }
    }

    /**
     * Search for hashtag data on Instagram/Facebook.
     *
     * @param string $hashtag  The hashtag to search for.
     * @param string $platform The platform (instagram or facebook).
     * @param int    $limit    Number of results to return.
     * @return array|WP_Error Search results or error.
     */
    public function search_hashtag( $hashtag, $platform, $limit = 10 ) {
        // Validate platform
        if ( ! in_array( $platform, array( 'instagram', 'facebook' ), true ) ) {
            return new WP_Error( 'invalid_platform', 'Platform must be instagram or facebook.', array( 'status' => 400 ) );
        }

        // For now, return mock data since we need actual aggregator API credentials
        if ( empty( $this->api_key ) ) {
            return $this->get_mock_search_data( $hashtag, $platform, $limit );
        }

        // Make request based on provider
        switch ( $this->provider ) {
            case 'apify':
                return $this->search_apify( $hashtag, $platform, $limit );
            case 'data365':
                return $this->search_data365( $hashtag, $platform, $limit );
            default:
                return new WP_Error( 'invalid_provider', 'Invalid aggregator provider configured.', array( 'status' => 500 ) );
        }
    }

    /**
     * Get hashtag analytics from aggregator services.
     *
     * @param string $hashtag   The hashtag to analyze.
     * @param string $platform  The platform (instagram or facebook).
     * @param string $timeframe The timeframe for analytics.
     * @return array|WP_Error Analytics data or error.
     */
    public function get_hashtag_analytics( $hashtag, $platform, $timeframe = '7d' ) {
        // For now, return mock data since we need actual aggregator API credentials
        if ( empty( $this->api_key ) ) {
            return $this->get_mock_analytics_data( $hashtag, $platform, $timeframe );
        }

        // Get search results and calculate analytics
        $search_results = $this->search_hashtag( $hashtag, $platform, 50 );
        
        if ( is_wp_error( $search_results ) ) {
            return $search_results;
        }

        return $this->calculate_analytics( $search_results, $platform, $timeframe );
    }

    /**
     * Get trending hashtags from aggregator services.
     *
     * @return array|WP_Error Trending hashtags by platform or error.
     */
    public function get_trending_hashtags() {
        // For now, return mock data since we need actual aggregator API credentials
        if ( empty( $this->api_key ) ) {
            return array(
                'instagram' => array(
                    'love',
                    'instagood',
                    'photooftheday',
                    'fashion',
                    'beautiful',
                    'happy',
                    'cute',
                    'tbt',
                    'like4like',
                    'followme'
                ),
                'facebook' => array(
                    'love',
                    'family',
                    'friends',
                    'life',
                    'happy',
                    'blessed',
                    'thankful',
                    'motivation',
                    'inspiration',
                    'nature'
                ),
            );
        }

        // In production, this would query trending endpoints
        return array(
            'instagram' => array(),
            'facebook' => array(),
        );
    }

    /**
     * Search using Apify service.
     *
     * @param string $hashtag  The hashtag.
     * @param string $platform The platform.
     * @param int    $limit    Result limit.
     * @return array|WP_Error Results or error.
     */
    private function search_apify( $hashtag, $platform, $limit ) {
        $actor_id = $platform === 'instagram' ? 'apify/instagram-hashtag-scraper' : 'apify/facebook-posts-scraper';
        
        $endpoint = $this->api_url . '/acts/' . $actor_id . '/runs';
        
        $body = array(
            'hashtag' => $hashtag,
            'resultsLimit' => $limit,
        );

        $response = $this->make_api_request( $endpoint, $body, 'POST' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Apify returns a run ID, we'd need to poll for results
        // For now, return mock data formatted for Apify
        return $this->get_mock_search_data( $hashtag, $platform, $limit );
    }

    /**
     * Search using Data365 service.
     *
     * @param string $hashtag  The hashtag.
     * @param string $platform The platform.
     * @param int    $limit    Result limit.
     * @return array|WP_Error Results or error.
     */
    private function search_data365( $hashtag, $platform, $limit ) {
        $endpoint = $this->api_url . '/' . $platform . '/hashtag/' . urlencode( $hashtag );
        
        $params = array(
            'limit' => $limit,
        );

        $response = $this->make_api_request( $endpoint, $params, 'GET' );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->format_data365_results( $response, $hashtag, $platform );
    }

    /**
     * Make API request to aggregator service.
     *
     * @param string $url    API endpoint URL.
     * @param array  $data   Request data.
     * @param string $method HTTP method.
     * @return array|WP_Error Response data or error.
     */
    private function make_api_request( $url, $data, $method = 'GET' ) {
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'method' => $method,
            'timeout' => 60, // Aggregators can be slower
        );

        if ( $method === 'POST' ) {
            $args['body'] = json_encode( $data );
        } else {
            $url = add_query_arg( $data, $url );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        if ( $status_code >= 400 ) {
            return new WP_Error( 
                'api_error', 
                $decoded['error'] ?? 'Aggregator API error.', 
                array( 'status' => $status_code ) 
            );
        }

        if ( ! $decoded ) {
            return new WP_Error( 'invalid_response', 'Invalid response from aggregator API.', array( 'status' => 500 ) );
        }

        return $decoded;
    }

    /**
     * Calculate analytics from search results.
     *
     * @param array  $results   Search results.
     * @param string $platform  The platform.
     * @param string $timeframe Analytics timeframe.
     * @return array Analytics data.
     */
    private function calculate_analytics( $results, $platform, $timeframe ) {
        $total_posts = count( $results );
        $total_likes = array_sum( array_column( array_column( $results, 'metrics' ), 'likes' ) );
        $total_comments = array_sum( array_column( array_column( $results, 'metrics' ), 'comments' ) );
        $total_shares = array_sum( array_column( array_column( $results, 'metrics' ), 'shares' ) );

        $engagement_rate = $total_posts > 0 ? ( ( $total_likes + $total_comments + $total_shares ) / $total_posts ) : 0;

        return array(
            'total_posts' => $total_posts,
            'total_engagement' => $total_likes + $total_comments + $total_shares,
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
            'platform' => $platform,
        );
    }

    /**
     * Format Data365 API results.
     *
     * @param array  $response Raw API response.
     * @param string $hashtag  The hashtag.
     * @param string $platform The platform.
     * @return array Formatted results.
     */
    private function format_data365_results( $response, $hashtag, $platform ) {
        $formatted = array();
        $posts = $response['data'] ?? array();

        foreach ( $posts as $post ) {
            $formatted[] = array(
                'id' => $post['id'] ?? '',
                'title' => $post['caption'] ?? $post['message'] ?? '',
                'description' => $post['description'] ?? '',
                'thumbnail' => $post['image_url'] ?? $post['thumbnail'] ?? '',
                'url' => $post['permalink'] ?? $post['url'] ?? '',
                'metrics' => array(
                    'likes' => $post['like_count'] ?? 0,
                    'comments' => $post['comment_count'] ?? 0,
                    'shares' => $post['share_count'] ?? 0,
                    'views' => $post['view_count'] ?? 0,
                ),
                'author' => $post['username'] ?? $post['author'] ?? '',
                'platform' => $platform,
                'hashtag' => $hashtag,
                'created_time' => $post['created_time'] ?? '',
            );
        }

        return $formatted;
    }

    /**
     * Get mock search data for development/demo purposes.
     *
     * @param string $hashtag  The hashtag.
     * @param string $platform The platform.
     * @param int    $limit    Number of results.
     * @return array Mock data.
     */
    private function get_mock_search_data( $hashtag, $platform, $limit ) {
        $mock_results = array();
        
        for ( $i = 1; $i <= $limit; $i++ ) {
            $mock_results[] = array(
                'id' => $platform . '_' . $hashtag . '_' . $i,
                'title' => ucfirst( $platform ) . " post about #{$hashtag} #{$i}",
                'description' => "This is a demo {$platform} post about #{$hashtag}. In production, this would come from a third-party aggregator API.",
                'thumbnail' => "https://picsum.photos/400/400?random=" . ( $i + 100 ),
                'url' => "https://www.{$platform}.com/p/{$i}",
                'metrics' => array(
                    'likes' => rand( 50, 5000 ),
                    'comments' => rand( 5, 200 ),
                    'shares' => rand( 2, 100 ),
                    'views' => $platform === 'instagram' ? 0 : rand( 500, 50000 ), // Instagram doesn't always show views
                ),
                'author' => "demo_user_{$i}",
                'platform' => $platform,
                'hashtag' => $hashtag,
                'created_time' => date( 'Y-m-d H:i:s', strtotime( '-' . rand( 1, 30 ) . ' days' ) ),
            );
        }

        return $mock_results;
    }

    /**
     * Get mock analytics data for development/demo purposes.
     *
     * @param string $hashtag   The hashtag.
     * @param string $platform  The platform.
     * @param string $timeframe The timeframe.
     * @return array Mock analytics data.
     */
    private function get_mock_analytics_data( $hashtag, $platform, $timeframe ) {
        $base_multiplier = array(
            '1d' => 1,
            '7d' => 7,
            '30d' => 30,
        );

        $multiplier = $base_multiplier[ $timeframe ] ?? 7;
        
        // Instagram vs Facebook have different engagement patterns
        $engagement_base = $platform === 'instagram' ? 1000 : 500;
        
        return array(
            'total_posts' => rand( 20, 200 ) * $multiplier,
            'total_engagement' => rand( $engagement_base, $engagement_base * 10 ) * $multiplier,
            'engagement_rate' => rand( 100, 600 ) / 100, // 1-6%
            'average_likes' => rand( 50, 500 ),
            'average_comments' => rand( 5, 50 ),
            'average_shares' => rand( 2, 25 ),
            'growth_rate' => rand( -5, 30 ), // -5% to +30% growth
            'timeframe' => $timeframe,
            'hashtag' => $hashtag,
            'platform' => $platform,
        );
    }
}