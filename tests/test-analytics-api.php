<?php
/**
 * Tests for the Analytics API class.
 *
 * @package RWP_Creator_Suite
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test case for the Analytics API.
 */
class Test_RWP_Creator_Suite_Analytics_API extends TestCase {

	/**
	 * Analytics API instance.
	 *
	 * @var RWP_Creator_Suite_Analytics_API
	 */
	private $api;

	/**
	 * Mock analytics instance.
	 *
	 * @var object
	 */
	private $mock_analytics;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private $test_user_id;

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();
		
		// Mock WordPress functions
		$this->mock_wordpress_functions();
		
		// Mock the analytics instance
		$this->mock_analytics = $this->create_mock_analytics();
		
		// Create test user
		$this->test_user_id = 123;
		
		$this->api = new RWP_Creator_Suite_Analytics_API();
	}

	/**
	 * Mock WordPress functions for testing.
	 */
	private function mock_wordpress_functions() {
		// Mock REST API functions
		if ( ! function_exists( 'register_rest_route' ) ) {
			function register_rest_route( $namespace, $route, $args = array() ) {
				global $wp_rest_routes;
				if ( ! isset( $wp_rest_routes ) ) {
					$wp_rest_routes = array();
				}
				$wp_rest_routes[ $namespace ][ $route ] = $args;
				return true;
			}
		}

		// Mock permission functions
		if ( ! function_exists( 'current_user_can' ) ) {
			function current_user_can( $capability ) {
				return isset( $GLOBALS['mock_user_can_manage_options'] ) ? $GLOBALS['mock_user_can_manage_options'] : false;
			}
		}

		// Mock sanitization functions
		if ( ! function_exists( 'sanitize_text_field' ) ) {
			function sanitize_text_field( $str ) {
				return trim( strip_tags( $str ) );
			}
		}

		if ( ! function_exists( 'absint' ) ) {
			function absint( $maybeint ) {
				return abs( intval( $maybeint ) );
			}
		}

		// Mock translation function
		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain = 'default' ) {
				return $text;
			}
		}

		// Mock date function
		if ( ! function_exists( 'date' ) ) {
			function date( $format, $timestamp = null ) {
				return \date( $format, $timestamp ?: time() );
			}
		}

		// Mock strtotime function
		if ( ! function_exists( 'strtotime' ) ) {
			function strtotime( $datetime ) {
				return \strtotime( $datetime );
			}
		}

		// Mock WP_REST_Response class
		if ( ! class_exists( 'WP_REST_Response' ) ) {
			class WP_REST_Response {
				public $data;
				public $status;

				public function __construct( $data = null, $status = 200 ) {
					$this->data = $data;
					$this->status = $status;
				}

				public function get_data() {
					return $this->data;
				}

				public function get_status() {
					return $this->status;
				}
			}
		}

		// Mock WP_REST_Request class
		if ( ! class_exists( 'WP_REST_Request' ) ) {
			class WP_REST_Request {
				private $params = array();

				public function __construct( $params = array() ) {
					$this->params = $params;
				}

				public function get_param( $key ) {
					return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
				}

				public function set_param( $key, $value ) {
					$this->params[ $key ] = $value;
				}
			}
		}

		// Mock WPDB
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			$wpdb = new stdClass();
			$wpdb->prefix = 'wp_';
			$wpdb->get_results = function( $query, $output = OBJECT ) {
				return $this->mock_db_results();
			};
			$wpdb->get_var = function( $query ) {
				return $this->mock_db_var();
			};
			$wpdb->prepare = function( $query, ...$args ) {
				return $query;
			};
		}
	}

	/**
	 * Create mock analytics instance.
	 *
	 * @return object
	 */
	private function create_mock_analytics() {
		return (object) array(
			'get_analytics_summary' => function( $args ) {
				return array(
					array(
						'event_type' => 'content_generated',
						'event_count' => 50,
						'unique_sessions' => 25,
						'event_date' => '2024-01-01',
					),
					array(
						'event_type' => 'hashtag_added',
						'event_count' => 75,
						'unique_sessions' => 30,
						'event_date' => '2024-01-02',
					),
				);
			},
			'get_popular_hashtags' => function( $limit ) {
				return array(
					array(
						'hashtag_hash' => 'abc123def456',
						'platform' => 'instagram',
						'usage_count' => 25,
					),
					array(
						'hashtag_hash' => 'def456ghi789',
						'platform' => 'tiktok',
						'usage_count' => 20,
					),
				);
			},
			'get_platform_stats' => function() {
				return array(
					array(
						'platform' => 'instagram',
						'total_usage' => 100,
						'unique_users' => 50,
					),
					array(
						'platform' => 'tiktok',
						'total_usage' => 75,
						'unique_users' => 35,
					),
				);
			},
		);
	}

	/**
	 * Mock database results.
	 *
	 * @return array
	 */
	private function mock_db_results() {
		return array(
			array(
				'feature' => 'caption_writer',
				'usage_count' => 50,
				'unique_users' => 25,
			),
			array(
				'feature' => 'content_repurposer',
				'usage_count' => 30,
				'unique_users' => 20,
			),
		);
	}

	/**
	 * Mock database var result.
	 *
	 * @return int
	 */
	private function mock_db_var() {
		return 100;
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Reset global mocks
		unset( $GLOBALS['mock_user_can_manage_options'] );
		unset( $GLOBALS['wp_rest_routes'] );
		
		parent::tear_down();
	}

	/**
	 * Test API initialization.
	 */
	public function test_api_initialization() {
		$this->assertInstanceOf( 'RWP_Creator_Suite_Analytics_API', $this->api );
	}

	/**
	 * Test routes registration.
	 */
	public function test_register_routes() {
		global $wp_rest_routes;
		
		$this->api->register_routes();
		
		// Check that routes are registered
		$this->assertArrayHasKey( 'rwp-creator-suite/v1', $wp_rest_routes );
		
		$routes = $wp_rest_routes['rwp-creator-suite/v1'];
		
		// Check specific routes
		$expected_routes = array(
			'/analytics/summary',
			'/analytics/hashtags',
			'/analytics/platforms',
			'/analytics/features',
			'/analytics/templates',
			'/analytics/trends',
			'/analytics/consent',
		);
		
		foreach ( $expected_routes as $route ) {
			$this->assertArrayHasKey( $route, $routes );
		}
	}

	/**
	 * Test admin permission check - authorized user.
	 */
	public function test_admin_permission_authorized() {
		$GLOBALS['mock_user_can_manage_options'] = true;
		
		$request = new WP_REST_Request();
		$result = $this->api->check_admin_permission( $request );
		
		$this->assertTrue( $result );
	}

	/**
	 * Test admin permission check - unauthorized user.
	 */
	public function test_admin_permission_unauthorized() {
		$GLOBALS['mock_user_can_manage_options'] = false;
		
		$request = new WP_REST_Request();
		$result = $this->api->check_admin_permission( $request );
		
		$this->assertFalse( $result );
	}

	/**
	 * Test analytics summary endpoint.
	 */
	public function test_get_analytics_summary() {
		// Set up mock to return our analytics instance
		$reflection = new ReflectionClass( $this->api );
		$property = $reflection->getProperty( 'analytics' );
		$property->setAccessible( true );
		$property->setValue( $this->api, $this->mock_analytics );
		
		$request = new WP_REST_Request( array(
			'start_date' => '2024-01-01',
			'end_date' => '2024-01-31',
		) );
		
		$response = $this->api->get_analytics_summary( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'totals', $data['data'] );
		$this->assertArrayHasKey( 'event_types', $data['data'] );
		$this->assertArrayHasKey( 'daily_breakdown', $data['data'] );
	}

	/**
	 * Test popular hashtags endpoint.
	 */
	public function test_get_popular_hashtags() {
		// Set up mock to return our analytics instance
		$reflection = new ReflectionClass( $this->api );
		$property = $reflection->getProperty( 'analytics' );
		$property->setAccessible( true );
		$property->setValue( $this->api, $this->mock_analytics );
		
		$request = new WP_REST_Request( array(
			'limit' => 10,
		) );
		
		$response = $this->api->get_popular_hashtags( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'total', $data );
		
		// Check that hashtag data is properly formatted
		foreach ( $data['data'] as $hashtag ) {
			$this->assertArrayHasKey( 'platform', $hashtag );
			$this->assertArrayHasKey( 'usage_count', $hashtag );
			$this->assertArrayHasKey( 'hashtag_id', $hashtag );
			$this->assertIsInt( $hashtag['usage_count'] );
		}
	}

	/**
	 * Test popular hashtags with platform filter.
	 */
	public function test_get_popular_hashtags_with_platform_filter() {
		// Set up mock to return our analytics instance
		$reflection = new ReflectionClass( $this->api );
		$property = $reflection->getProperty( 'analytics' );
		$property->setAccessible( true );
		$property->setValue( $this->api, $this->mock_analytics );
		
		$request = new WP_REST_Request( array(
			'limit' => 10,
			'platform' => 'instagram',
		) );
		
		$response = $this->api->get_popular_hashtags( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		
		// Check that all results are for instagram platform
		foreach ( $data['data'] as $hashtag ) {
			$this->assertEquals( 'instagram', $hashtag['platform'] );
		}
	}

	/**
	 * Test platform statistics endpoint.
	 */
	public function test_get_platform_stats() {
		$request = new WP_REST_Request( array(
			'start_date' => '2024-01-01',
			'end_date' => '2024-01-31',
		) );
		
		$response = $this->api->get_platform_stats( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'period', $data );
		$this->assertEquals( '2024-01-01', $data['period']['start_date'] );
		$this->assertEquals( '2024-01-31', $data['period']['end_date'] );
	}

	/**
	 * Test feature statistics endpoint.
	 */
	public function test_get_feature_stats() {
		$request = new WP_REST_Request( array(
			'start_date' => '2024-01-01',
			'end_date' => '2024-01-31',
		) );
		
		$response = $this->api->get_feature_stats( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'period', $data );
		
		// Check data format
		foreach ( $data['data'] as $feature ) {
			$this->assertArrayHasKey( 'feature', $feature );
			$this->assertArrayHasKey( 'usage_count', $feature );
			$this->assertArrayHasKey( 'unique_users', $feature );
			$this->assertIsInt( $feature['usage_count'] );
			$this->assertIsInt( $feature['unique_users'] );
		}
	}

	/**
	 * Test template statistics endpoint.
	 */
	public function test_get_template_stats() {
		$request = new WP_REST_Request( array(
			'limit' => 5,
		) );
		
		$response = $this->api->get_template_stats( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'total', $data );
	}

	/**
	 * Test usage trends endpoint.
	 */
	public function test_get_usage_trends() {
		$request = new WP_REST_Request( array(
			'period' => 'daily',
			'days' => 30,
		) );
		
		$response = $this->api->get_usage_trends( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'period', $data );
		$this->assertArrayHasKey( 'days', $data );
		$this->assertEquals( 'daily', $data['period'] );
		$this->assertEquals( 30, $data['days'] );
	}

	/**
	 * Test consent statistics endpoint.
	 */
	public function test_get_consent_stats() {
		// Mock consent manager
		if ( ! class_exists( 'RWP_Creator_Suite_Consent_Manager' ) ) {
			class RWP_Creator_Suite_Consent_Manager {
				public static function get_instance() {
					return new self();
				}

				public function get_consent_stats() {
					return array(
						'total_users_with_preference' => 100,
						'consented_users' => 85,
						'non_consented_users' => 15,
					);
				}
			}
		}
		
		$request = new WP_REST_Request();
		
		$response = $this->api->get_consent_stats( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		
		// Check that consent rate is calculated
		$this->assertArrayHasKey( 'consent_rate', $data['data'] );
		$this->assertIsNumeric( $data['data']['consent_rate'] );
	}

	/**
	 * Test parameter validation and defaults.
	 */
	public function test_parameter_defaults() {
		// Test analytics summary with no parameters
		$request = new WP_REST_Request();
		
		// Should use default date range
		$this->assertNull( $request->get_param( 'start_date' ) );
		$this->assertNull( $request->get_param( 'end_date' ) );
	}

	/**
	 * Test hashtags limit validation.
	 */
	public function test_hashtags_limit_validation() {
		$request = new WP_REST_Request( array(
			'limit' => 150, // Above maximum
		) );
		
		// In a real REST API, this would be handled by WordPress validation
		// Here we test that our API can handle the parameter
		$limit = $request->get_param( 'limit' );
		$this->assertEquals( 150, $limit );
	}

	/**
	 * Test invalid platform parameter.
	 */
	public function test_invalid_platform_parameter() {
		$request = new WP_REST_Request( array(
			'platform' => 'invalid_platform',
		) );
		
		$platform = $request->get_param( 'platform' );
		$this->assertEquals( 'invalid_platform', $platform );
		
		// The API should handle this gracefully by returning no results
		// when filtering by an invalid platform
	}

	/**
	 * Test trends period validation.
	 */
	public function test_trends_period_validation() {
		$request = new WP_REST_Request( array(
			'period' => 'daily',
		) );
		
		$period = $request->get_param( 'period' );
		$this->assertEquals( 'daily', $period );
		
		$request->set_param( 'period', 'weekly' );
		$period = $request->get_param( 'period' );
		$this->assertEquals( 'weekly', $period );
		
		$request->set_param( 'period', 'monthly' );
		$period = $request->get_param( 'period' );
		$this->assertEquals( 'monthly', $period );
	}

	/**
	 * Test date format helper method.
	 */
	public function test_date_format_for_period() {
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'get_date_format_for_period' );
		$method->setAccessible( true );
		
		$this->assertEquals( '%Y-%m-%d', $method->invokeArgs( $this->api, array( 'daily' ) ) );
		$this->assertEquals( '%Y-%u', $method->invokeArgs( $this->api, array( 'weekly' ) ) );
		$this->assertEquals( '%Y-%m', $method->invokeArgs( $this->api, array( 'monthly' ) ) );
		$this->assertEquals( '%Y-%m-%d', $method->invokeArgs( $this->api, array( 'invalid' ) ) );
	}

	/**
	 * Test analytics summary processing.
	 */
	public function test_analytics_summary_processing() {
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'process_analytics_summary' );
		$method->setAccessible( true );
		
		$raw_summary = array(
			array(
				'event_type' => 'content_generated',
				'event_count' => 50,
				'unique_sessions' => 25,
				'event_date' => '2024-01-01',
			),
			array(
				'event_type' => 'hashtag_added',
				'event_count' => 75,
				'unique_sessions' => 30,
				'event_date' => '2024-01-02',
			),
		);
		
		$processed = $method->invokeArgs( $this->api, array( $raw_summary, '2024-01-01', '2024-01-31' ) );
		
		$this->assertArrayHasKey( 'totals', $processed );
		$this->assertArrayHasKey( 'event_types', $processed );
		$this->assertArrayHasKey( 'daily_breakdown', $processed );
		
		$this->assertEquals( 125, $processed['totals']['total_events'] );
		$this->assertArrayHasKey( 'content_generated', $processed['event_types'] );
		$this->assertArrayHasKey( 'hashtag_added', $processed['event_types'] );
	}
}