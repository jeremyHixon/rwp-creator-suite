<?php
/**
 * Test data helpers for mock data generation.
 *
 * @package RWP_Creator_Suite
 */

/**
 * Helper class for generating test data.
 */
class RWP_Creator_Suite_Test_Data_Helpers {

	/**
	 * Generate mock Instagram analyzer data.
	 *
	 * @param array $overrides Optional overrides for default data.
	 * @return array Mock Instagram analyzer data.
	 */
	public static function get_mock_instagram_data( $overrides = array() ) {
		$defaults = array(
			'user_id' => 123,
			'whitelist' => array( 'user1', 'user2', 'user3' ),
			'analysis_results' => array(
				'total_followers' => 1000,
				'engagement_rate' => 85.5,
				'authenticity_score' => 92.3,
				'suspicious_accounts' => 15,
				'bot_detection' => array(
					'likely_bots' => 8,
					'suspicious_patterns' => 7,
				),
			),
			'timestamp' => time(),
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Generate mock analytics data.
	 *
	 * @param array $overrides Optional overrides for default data.
	 * @return array Mock analytics data.
	 */
	public static function get_mock_analytics_data( $overrides = array() ) {
		$defaults = array(
			'user_id' => 123,
			'event_type' => 'page_view',
			'event_data' => array(
				'page' => 'instagram-analyzer',
				'timestamp' => time(),
				'user_agent' => 'Test Browser',
				'ip_address' => '127.0.0.1',
			),
			'session_data' => array(
				'session_id' => 'test_session_123',
				'session_start' => time() - 3600,
				'page_views' => 5,
			),
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Generate mock user value data.
	 *
	 * @param array $overrides Optional overrides for default data.
	 * @return array Mock user value data.
	 */
	public static function get_mock_user_value_data( $overrides = array() ) {
		$defaults = array(
			'user_id' => 123,
			'user_tier' => 'premium',
			'subscription_status' => 'active',
			'usage_stats' => array(
				'api_calls_today' => 25,
				'api_calls_month' => 750,
				'features_used' => array( 'instagram_analyzer', 'caption_writer' ),
			),
			'limits' => array(
				'daily_api_calls' => 100,
				'monthly_api_calls' => 3000,
				'concurrent_analyses' => 5,
			),
			'preferences' => array(
				'email_notifications' => true,
				'data_retention_days' => 30,
				'auto_cleanup' => true,
			),
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Generate mock account manager data.
	 *
	 * @param array $overrides Optional overrides for default data.
	 * @return array Mock account manager data.
	 */
	public static function get_mock_account_data( $overrides = array() ) {
		$defaults = array(
			'user_id' => 123,
			'account_settings' => array(
				'display_name' => 'Test User',
				'email' => 'test@example.com',
				'timezone' => 'America/New_York',
				'language' => 'en_US',
			),
			'privacy_settings' => array(
				'data_collection' => true,
				'analytics_tracking' => true,
				'email_marketing' => false,
			),
			'consent_history' => array(
				array(
					'timestamp' => time() - 86400,
					'action' => 'granted',
					'type' => 'analytics',
				),
				array(
					'timestamp' => time() - 172800,
					'action' => 'denied',
					'type' => 'marketing',
				),
			),
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Generate mock API response.
	 *
	 * @param bool  $success Whether the response indicates success.
	 * @param array $data    Optional data to include in response.
	 * @param string $message Optional message.
	 * @return array Mock API response.
	 */
	public static function get_mock_api_response( $success = true, $data = array(), $message = '' ) {
		$response = array(
			'success' => $success,
			'data'    => $data,
		);

		if ( ! empty( $message ) ) {
			$response['message'] = $message;
		}

		if ( ! $success && empty( $message ) ) {
			$response['message'] = 'An error occurred';
		}

		return $response;
	}

	/**
	 * Generate mock WordPress user data.
	 *
	 * @param array $overrides Optional overrides for default data.
	 * @return array Mock WordPress user data.
	 */
	public static function get_mock_wp_user( $overrides = array() ) {
		$defaults = array(
			'ID' => 123,
			'user_login' => 'testuser',
			'user_email' => 'test@example.com',
			'user_registered' => gmdate( 'Y-m-d H:i:s', time() - 86400 ),
			'display_name' => 'Test User',
			'user_status' => 0,
			'roles' => array( 'subscriber' ),
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Generate mock nonce for testing.
	 *
	 * @param string $action The nonce action.
	 * @return string Mock nonce value.
	 */
	public static function get_mock_nonce( $action = 'test_action' ) {
		return 'valid_nonce_' . md5( $action );
	}

	/**
	 * Generate mock $_POST data for API requests.
	 *
	 * @param string $endpoint The API endpoint being tested.
	 * @param array  $data     Additional data to include.
	 * @return array Mock $_POST data.
	 */
	public static function get_mock_post_data( $endpoint, $data = array() ) {
		$base_data = array(
			'nonce' => self::get_mock_nonce( $endpoint ),
		);

		return array_merge( $base_data, $data );
	}

	/**
	 * Generate mock error response.
	 *
	 * @param string $error_code    The error code.
	 * @param string $error_message The error message.
	 * @param int    $http_code     The HTTP status code.
	 * @return array Mock error response.
	 */
	public static function get_mock_error_response( $error_code = 'generic_error', $error_message = 'An error occurred', $http_code = 400 ) {
		return array(
			'success' => false,
			'error'   => array(
				'code'    => $error_code,
				'message' => $error_message,
			),
			'http_code' => $http_code,
		);
	}

	/**
	 * Generate mock validation errors.
	 *
	 * @param array $fields Fields with validation errors.
	 * @return array Mock validation error response.
	 */
	public static function get_mock_validation_errors( $fields = array() ) {
		if ( empty( $fields ) ) {
			$fields = array(
				'email' => 'Invalid email address',
				'nonce' => 'Invalid security token',
			);
		}

		return array(
			'success' => false,
			'errors'  => $fields,
			'message' => 'Validation failed',
		);
	}

	/**
	 * Generate mock rate limit data.
	 *
	 * @param array $overrides Optional overrides for default data.
	 * @return array Mock rate limit data.
	 */
	public static function get_mock_rate_limit_data( $overrides = array() ) {
		$defaults = array(
			'user_id' => 0, // Guest user
			'ip_address' => '127.0.0.1',
			'requests_count' => 5,
			'limit' => 10,
			'window_start' => time() - 3600, // 1 hour ago
			'window_duration' => 3600, // 1 hour
			'reset_time' => time() + 3600, // 1 hour from now
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Generate mock caption writer data.
	 *
	 * @param array $overrides Optional overrides for default data.
	 * @return array Mock caption writer data.
	 */
	public static function get_mock_caption_data( $overrides = array() ) {
		$defaults = array(
			'user_id' => 123,
			'input_description' => 'A beautiful sunset over the ocean',
			'generated_captions' => array(
				'instagram' => 'Chasing sunsets and dreams 🌅 #sunset #ocean #beautiful #nature',
				'twitter' => 'Nothing beats a perfect sunset 🌅 #sunset',
				'linkedin' => 'Taking a moment to appreciate the natural beauty around us.',
				'facebook' => 'What a beautiful way to end the day! There\'s something magical about watching the sun set over the ocean.',
			),
			'character_counts' => array(
				'instagram' => 67,
				'twitter' => 34,
				'linkedin' => 58,
				'facebook' => 95,
			),
			'generation_time' => 2.5, // seconds
			'timestamp' => time(),
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Generate mock content repurposer data.
	 *
	 * @param array $overrides Optional overrides for default data.
	 * @return array Mock content repurposer data.
	 */
	public static function get_mock_repurposer_data( $overrides = array() ) {
		$defaults = array(
			'user_id' => 123,
			'original_content' => 'This is a long-form blog post about social media marketing strategies and best practices for content creators...',
			'repurposed_content' => array(
				'twitter' => 'Essential social media marketing tips for content creators 📱 #SocialMedia #Marketing',
				'linkedin' => 'Sharing key insights on social media marketing strategies that every content creator should know...',
				'facebook' => 'Want to level up your social media game? Here are proven strategies that work!',
				'instagram' => 'Social media marketing made simple ✨ Swipe for tips! #ContentCreator #MarketingTips',
			),
			'platforms_selected' => array( 'twitter', 'linkedin', 'facebook', 'instagram' ),
			'content_analysis' => array(
				'original_word_count' => 500,
				'reading_time' => '2 min',
				'key_topics' => array( 'social media', 'marketing', 'content creation' ),
			),
			'processing_time' => 3.2, // seconds
			'timestamp' => time(),
		);

		return wp_parse_args( $overrides, $defaults );
	}
}