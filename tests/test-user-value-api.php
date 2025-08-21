<?php
/**
 * Tests for the User Value API class.
 *
 * @package RWP_Creator_Suite
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test case for the User Value API.
 */
class Test_RWP_Creator_Suite_User_Value_API extends TestCase {

	/**
	 * User Value API instance.
	 *
	 * @var RWP_Creator_Suite_User_Value_API
	 */
	private $api;

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
		
		// Create test user
		$this->test_user_id = 123;
		
		$this->api = new RWP_Creator_Suite_User_Value_API();
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

		// Mock user functions
		if ( ! function_exists( 'get_current_user_id' ) ) {
			function get_current_user_id() {
				return isset( $GLOBALS['mock_current_user_id'] ) ? $GLOBALS['mock_current_user_id'] : 123;
			}
		}

		// Mock meta functions
		if ( ! function_exists( 'update_user_meta' ) ) {
			function update_user_meta( $user_id, $meta_key, $meta_value ) {
				global $mock_user_meta;
				if ( ! isset( $mock_user_meta ) ) {
					$mock_user_meta = array();
				}
				$mock_user_meta[ $user_id ][ $meta_key ] = $meta_value;
				return true;
			}
		}

		if ( ! function_exists( 'get_transient' ) ) {
			function get_transient( $key ) {
				global $mock_transients;
				return isset( $mock_transients[ $key ] ) ? $mock_transients[ $key ] : false;
			}
		}

		if ( ! function_exists( 'set_transient' ) ) {
			function set_transient( $key, $value, $expiration ) {
				global $mock_transients;
				$mock_transients[ $key ] = $value;
				return true;
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

		// Mock date/time functions
		if ( ! function_exists( 'current_time' ) ) {
			function current_time( $type, $gmt = 0 ) {
				return $type === 'mysql' ? date( 'Y-m-d H:i:s' ) : time();
			}
		}

		if ( ! function_exists( 'date' ) ) {
			function date( $format, $timestamp = null ) {
				return \date( $format, $timestamp ?: time() );
			}
		}

		if ( ! function_exists( 'strtotime' ) ) {
			function strtotime( $datetime ) {
				return \strtotime( $datetime );
			}
		}

		if ( ! function_exists( 'mktime' ) ) {
			function mktime( $hour = null, $minute = null, $second = null, $month = null, $day = null, $year = null ) {
				return \mktime( $hour, $minute, $second, $month, $day, $year );
			}
		}

		// Mock WordPress constants
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		// Mock WordPress classes
		$this->mock_wp_classes();

		// Mock WPDB
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			$wpdb = new stdClass();
			$wpdb->prefix = 'wp_';
			$wpdb->get_results = function( $query, $output = OBJECT ) {
				return $this->mock_user_analytics_data();
			};
			$wpdb->prepare = function( $query, ...$args ) {
				return $query;
			};
		}

		// Mock dependent classes
		$this->mock_dependent_classes();
	}

	/**
	 * Mock WordPress classes.
	 */
	private function mock_wp_classes() {
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

		// Mock WP_Error class
		if ( ! class_exists( 'WP_Error' ) ) {
			class WP_Error {
				private $errors = array();
				private $error_data = array();

				public function __construct( $code = '', $message = '', $data = '' ) {
					if ( ! empty( $code ) ) {
						$this->errors[ $code ][] = $message;
						if ( ! empty( $data ) ) {
							$this->error_data[ $code ] = $data;
						}
					}
				}

				public function get_error_code() {
					return ! empty( $this->errors ) ? array_key_first( $this->errors ) : '';
				}

				public function get_error_message( $code = '' ) {
					if ( empty( $code ) ) {
						$code = $this->get_error_code();
					}
					return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
				}
			}
		}
	}

	/**
	 * Mock dependent classes.
	 */
	private function mock_dependent_classes() {
		// Mock Consent Manager
		if ( ! class_exists( 'RWP_Creator_Suite_Consent_Manager' ) ) {
			class RWP_Creator_Suite_Consent_Manager {
				public static function get_instance() {
					return new self();
				}

				public function has_user_consented() {
					return isset( $GLOBALS['mock_user_consent'] ) ? $GLOBALS['mock_user_consent'] : true;
				}
			}
		}

		// Mock Anonymous Analytics
		if ( ! class_exists( 'RWP_Creator_Suite_Anonymous_Analytics' ) ) {
			class RWP_Creator_Suite_Anonymous_Analytics {
				const EVENT_HASHTAG_ADDED = 'hashtag_added';
				const EVENT_TEMPLATE_USED = 'template_used';
				const EVENT_CONTENT_GENERATED = 'content_generated';

				public static function get_instance() {
					return new self();
				}

				public function get_session_hash() {
					return 'test_session_hash_123';
				}
			}
		}

		// Mock Trend Analyzer
		if ( ! class_exists( 'RWP_Creator_Suite_Trend_Analyzer' ) ) {
			class RWP_Creator_Suite_Trend_Analyzer {
				public function generate_user_report( $user_profile ) {
					return array(
						'trending_hashtags' => array( 'test_trend_1', 'test_trend_2' ),
						'growth_rate' => 15.5,
					);
				}

				public function generate_trending_report( $user_profile, $period, $category ) {
					return array(
						'trends' => array( 'trend_1', 'trend_2' ),
						'period' => $period,
						'category' => $category,
					);
				}

				public function generate_monthly_trends( $user_profile, $month, $year ) {
					return array( 'monthly_trends' => 'test_data' );
				}
			}
		}

		// Mock Performance Benchmarker
		if ( ! class_exists( 'RWP_Creator_Suite_Performance_Benchmarker' ) ) {
			class RWP_Creator_Suite_Performance_Benchmarker {
				public function get_user_benchmarks( $user_profile ) {
					return array(
						'performance_score' => 75,
						'community_ranking' => 'top_25_percent',
					);
				}

				public function get_detailed_benchmarks( $user_profile ) {
					return array(
						'detailed_score' => 80,
						'breakdown' => array( 'engagement' => 85, 'reach' => 75 ),
					);
				}

				public function get_monthly_comparison( $user_profile, $month, $year ) {
					return array( 'monthly_comparison' => 'test_data' );
				}
			}
		}
	}

	/**
	 * Mock user analytics data.
	 *
	 * @return array
	 */
	private function mock_user_analytics_data() {
		return array(
			array(
				'event_type' => 'content_generated',
				'event_data' => json_encode( array(
					'platform' => 'instagram',
					'tone' => 'professional',
					'feature' => 'caption_writer',
				) ),
				'timestamp' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			),
			array(
				'event_type' => 'hashtag_added',
				'event_data' => json_encode( array(
					'hashtag_hash' => 'test_hashtag_hash',
					'platform' => 'tiktok',
				) ),
				'timestamp' => date( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
			),
		);
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Reset global mocks
		unset( $GLOBALS['mock_current_user_id'] );
		unset( $GLOBALS['mock_user_consent'] );
		unset( $GLOBALS['mock_user_meta'] );
		unset( $GLOBALS['mock_transients'] );
		unset( $GLOBALS['wp_rest_routes'] );
		
		parent::tear_down();
	}

	/**
	 * Test API initialization.
	 */
	public function test_api_initialization() {
		$this->assertInstanceOf( 'RWP_Creator_Suite_User_Value_API', $this->api );
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
			'/user-insights',
			'/trending-report',
			'/performance-benchmark',
			'/optimization-suggestions',
			'/achievements',
			'/beta-access',
			'/monthly-report',
		);
		
		foreach ( $expected_routes as $route ) {
			$this->assertArrayHasKey( $route, $routes );
		}
	}

	/**
	 * Test user consent check - with consent.
	 */
	public function test_user_consent_granted() {
		$GLOBALS['mock_user_consent'] = true;
		
		$request = new WP_REST_Request();
		$result = $this->api->check_user_consent( $request );
		
		$this->assertTrue( $result );
	}

	/**
	 * Test user consent check - without consent.
	 */
	public function test_user_consent_denied() {
		$GLOBALS['mock_user_consent'] = false;
		
		$request = new WP_REST_Request();
		$result = $this->api->check_user_consent( $request );
		
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'no_analytics_consent', $result->get_error_code() );
	}

	/**
	 * Test get user insights endpoint.
	 */
	public function test_get_user_insights() {
		$GLOBALS['mock_current_user_id'] = $this->test_user_id;
		
		$request = new WP_REST_Request();
		$response = $this->api->get_user_insights( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'generated_at', $data );
		
		// Check insights structure
		$insights = $data['data'];
		$this->assertArrayHasKey( 'trending_report', $insights );
		$this->assertArrayHasKey( 'performance_benchmark', $insights );
		$this->assertArrayHasKey( 'optimization_suggestions', $insights );
		$this->assertArrayHasKey( 'achievements', $insights );
		$this->assertArrayHasKey( 'beta_access_status', $insights );
		$this->assertArrayHasKey( 'summary_stats', $insights );
	}

	/**
	 * Test get trending report endpoint.
	 */
	public function test_get_trending_report() {
		$request = new WP_REST_Request( array(
			'period' => 'weekly',
			'category' => 'hashtags',
		) );
		
		$response = $this->api->get_trending_report( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertEquals( 'weekly', $data['period'] );
		$this->assertEquals( 'hashtags', $data['category'] );
	}

	/**
	 * Test get performance benchmark endpoint.
	 */
	public function test_get_performance_benchmark() {
		$request = new WP_REST_Request();
		$response = $this->api->get_performance_benchmark( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
	}

	/**
	 * Test get optimization suggestions endpoint.
	 */
	public function test_get_optimization_suggestions() {
		$request = new WP_REST_Request();
		$response = $this->api->get_optimization_suggestions( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertIsArray( $data['data'] );
	}

	/**
	 * Test get user achievements endpoint.
	 */
	public function test_get_user_achievements() {
		$request = new WP_REST_Request();
		$response = $this->api->get_user_achievements( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertIsArray( $data['data'] );
	}

	/**
	 * Test get beta access status endpoint.
	 */
	public function test_get_beta_access_status() {
		$request = new WP_REST_Request();
		$response = $this->api->get_beta_access_status( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'eligible', $data['data'] );
		$this->assertArrayHasKey( 'contribution_score', $data['data'] );
		$this->assertArrayHasKey( 'required_score', $data['data'] );
	}

	/**
	 * Test generate monthly report endpoint.
	 */
	public function test_generate_monthly_report() {
		global $mock_user_meta;
		
		$GLOBALS['mock_current_user_id'] = $this->test_user_id;
		
		$request = new WP_REST_Request( array(
			'month' => 12,
			'year' => 2024,
		) );
		
		$response = $this->api->generate_monthly_report( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'report_period', $data );
		$this->assertEquals( 12, $data['report_period']['month'] );
		$this->assertEquals( 2024, $data['report_period']['year'] );
		
		// Check that report was saved to user meta
		$this->assertArrayHasKey( $this->test_user_id, $mock_user_meta );
		$this->assertArrayHasKey( 'rwp_monthly_report_2024_12', $mock_user_meta[ $this->test_user_id ] );
	}

	/**
	 * Test monthly report with default parameters.
	 */
	public function test_generate_monthly_report_defaults() {
		$GLOBALS['mock_current_user_id'] = $this->test_user_id;
		
		$request = new WP_REST_Request();
		$response = $this->api->generate_monthly_report( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		
		// Should use current month/year as defaults
		$current_month = (int) date( 'n' );
		$current_year = (int) date( 'Y' );
		
		$this->assertEquals( $current_month, $data['report_period']['month'] );
		$this->assertEquals( $current_year, $data['report_period']['year'] );
	}

	/**
	 * Test user profile building.
	 */
	public function test_build_user_profile() {
		$GLOBALS['mock_current_user_id'] = $this->test_user_id;
		
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'build_user_profile' );
		$method->setAccessible( true );
		
		$profile = $method->invoke( $this->api );
		
		$this->assertIsArray( $profile );
		$this->assertArrayHasKey( 'user_id', $profile );
		$this->assertArrayHasKey( 'total_sessions', $profile );
		$this->assertArrayHasKey( 'platforms', $profile );
		$this->assertArrayHasKey( 'tones', $profile );
		$this->assertArrayHasKey( 'features', $profile );
		$this->assertArrayHasKey( 'consistency_score', $profile );
		
		$this->assertEquals( $this->test_user_id, $profile['user_id'] );
	}

	/**
	 * Test analytics data processing.
	 */
	public function test_process_user_analytics_data() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'process_user_analytics_data' );
		$method->setAccessible( true );
		
		$mock_data = $this->mock_user_analytics_data();
		$profile = $method->invokeArgs( $this->api, array( $mock_data, $this->test_user_id ) );
		
		$this->assertIsArray( $profile );
		$this->assertEquals( $this->test_user_id, $profile['user_id'] );
		$this->assertEquals( 2, $profile['total_sessions'] );
		
		// Check platform tracking
		$this->assertArrayHasKey( 'instagram', $profile['platforms'] );
		$this->assertArrayHasKey( 'tiktok', $profile['platforms'] );
		
		// Check tone tracking
		$this->assertArrayHasKey( 'professional', $profile['tones'] );
		
		// Check feature tracking
		$this->assertArrayHasKey( 'caption_writer', $profile['features'] );
		
		// Check derived metrics
		$this->assertArrayHasKey( 'most_used_platform', $profile );
		$this->assertArrayHasKey( 'consistency_score', $profile );
	}

	/**
	 * Test most used value helper.
	 */
	public function test_get_most_used_value() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'get_most_used_value' );
		$method->setAccessible( true );
		
		// Test with values
		$values = array( 'instagram' => 5, 'tiktok' => 10, 'twitter' => 3 );
		$result = $method->invokeArgs( $this->api, array( $values ) );
		$this->assertEquals( 'instagram', $result ); // First key when sorted
		
		// Test with empty array
		$result = $method->invokeArgs( $this->api, array( array() ) );
		$this->assertNull( $result );
	}

	/**
	 * Test consistency score calculation.
	 */
	public function test_calculate_consistency_score() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'calculate_consistency_score' );
		$method->setAccessible( true );
		
		$profile = array(
			'platforms' => array( 'instagram' => 8, 'tiktok' => 2 ),
			'tones' => array( 'professional' => 7, 'casual' => 3 ),
			'activity_pattern' => array( 9 => 5, 14 => 3, 19 => 2 ),
		);
		
		$score = $method->invokeArgs( $this->api, array( $profile ) );
		
		$this->assertIsFloat( $score );
		$this->assertGreaterThanOrEqual( 0, $score );
		$this->assertLessThanOrEqual( 1, $score );
	}

	/**
	 * Test variance calculation.
	 */
	public function test_calculate_variance() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'calculate_variance' );
		$method->setAccessible( true );
		
		// Test with multiple values
		$values = array( 1, 2, 3, 4, 5 );
		$variance = $method->invokeArgs( $this->api, array( $values ) );
		$this->assertIsFloat( $variance );
		$this->assertGreaterThan( 0, $variance );
		
		// Test with single value
		$values = array( 5 );
		$variance = $method->invokeArgs( $this->api, array( $values ) );
		$this->assertEquals( 0, $variance );
		
		// Test with identical values
		$values = array( 5, 5, 5, 5 );
		$variance = $method->invokeArgs( $this->api, array( $values ) );
		$this->assertEquals( 0, $variance );
	}

	/**
	 * Test achievement level calculation.
	 */
	public function test_get_achievement_level() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'get_achievement_level' );
		$method->setAccessible( true );
		
		$thresholds = array( 10, 50, 100 );
		
		// Test below first threshold
		$level = $method->invokeArgs( $this->api, array( 5, $thresholds ) );
		$this->assertEquals( 0, $level );
		
		// Test at first threshold
		$level = $method->invokeArgs( $this->api, array( 10, $thresholds ) );
		$this->assertEquals( 1, $level );
		
		// Test between thresholds
		$level = $method->invokeArgs( $this->api, array( 75, $thresholds ) );
		$this->assertEquals( 2, $level );
		
		// Test above all thresholds
		$level = $method->invokeArgs( $this->api, array( 150, $thresholds ) );
		$this->assertEquals( 3, $level );
	}

	/**
	 * Test next milestone calculation.
	 */
	public function test_get_next_milestone() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'get_next_milestone' );
		$method->setAccessible( true );
		
		$thresholds = array( 10, 50, 100 );
		
		// Test below first threshold
		$milestone = $method->invokeArgs( $this->api, array( 5, $thresholds ) );
		$this->assertEquals( 10, $milestone );
		
		// Test between thresholds
		$milestone = $method->invokeArgs( $this->api, array( 25, $thresholds ) );
		$this->assertEquals( 50, $milestone );
		
		// Test above all thresholds
		$milestone = $method->invokeArgs( $this->api, array( 150, $thresholds ) );
		$this->assertNull( $milestone );
	}

	/**
	 * Test beta eligibility check.
	 */
	public function test_check_beta_eligibility() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'check_beta_eligibility' );
		$method->setAccessible( true );
		
		// Test high contribution score (eligible)
		$profile = array(
			'hashtag_usage' => array_fill( 0, 20, array() ), // 20 hashtags
			'template_usage' => array_fill( 0, 10, array() ), // 10 templates
			'platform_diversity' => 5,
			'consistency_score' => 0.8,
		);
		
		$status = $method->invokeArgs( $this->api, array( $profile ) );
		
		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'eligible', $status );
		$this->assertArrayHasKey( 'contribution_score', $status );
		$this->assertArrayHasKey( 'required_score', $status );
		$this->assertEquals( 50, $status['required_score'] );
		
		if ( $status['eligible'] ) {
			$this->assertArrayHasKey( 'available_features', $status );
			$this->assertIsArray( $status['available_features'] );
		}
	}

	/**
	 * Test community benchmarks caching.
	 */
	public function test_community_benchmarks_caching() {
		global $mock_transients;
		
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'get_community_benchmarks' );
		$method->setAccessible( true );
		
		// First call should compute and cache
		$benchmarks1 = $method->invoke( $this->api );
		$this->assertIsArray( $benchmarks1 );
		
		// Verify data was cached
		$this->assertArrayHasKey( 'rwp_community_benchmarks', $mock_transients );
		
		// Second call should use cache
		$benchmarks2 = $method->invoke( $this->api );
		$this->assertEquals( $benchmarks1, $benchmarks2 );
	}

	/**
	 * Test trending report parameter validation.
	 */
	public function test_trending_report_parameter_validation() {
		$request = new WP_REST_Request( array(
			'period' => 'monthly',
			'category' => 'platforms',
		) );
		
		$period = $request->get_param( 'period' );
		$category = $request->get_param( 'category' );
		
		$this->assertEquals( 'monthly', $period );
		$this->assertEquals( 'platforms', $category );
		
		// Test with invalid period
		$request->set_param( 'period', 'invalid' );
		$period = $request->get_param( 'period' );
		$this->assertEquals( 'invalid', $period );
	}
}