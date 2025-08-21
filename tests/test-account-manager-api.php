<?php
/**
 * Tests for the Account Manager API class.
 *
 * @package RWP_Creator_Suite
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test case for the Account Manager API.
 */
class Test_RWP_Creator_Suite_Account_API extends TestCase {

	/**
	 * Account API instance.
	 *
	 * @var RWP_Creator_Suite_Account_API
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
		
		$this->api = new RWP_Creator_Suite_Account_API();
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
				return isset( $GLOBALS['mock_current_user_id'] ) ? $GLOBALS['mock_current_user_id'] : 0;
			}
		}

		if ( ! function_exists( 'get_userdata' ) ) {
			function get_userdata( $user_id ) {
				if ( ! $user_id || ! isset( $GLOBALS['mock_users'][ $user_id ] ) ) {
					return false;
				}
				return (object) $GLOBALS['mock_users'][ $user_id ];
			}
		}

		if ( ! function_exists( 'wp_update_user' ) ) {
			function wp_update_user( $userdata ) {
				global $mock_users;
				$user_id = $userdata['ID'];
				
				if ( ! isset( $mock_users[ $user_id ] ) ) {
					return new WP_Error( 'invalid_user_id', 'Invalid user ID' );
				}
				
				// Simulate update
				foreach ( $userdata as $key => $value ) {
					if ( $key !== 'ID' ) {
						$mock_users[ $user_id ][ $key ] = $value;
					}
				}
				
				return $user_id;
			}
		}

		// Mock email functions
		if ( ! function_exists( 'is_email' ) ) {
			function is_email( $email ) {
				return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
			}
		}

		if ( ! function_exists( 'email_exists' ) ) {
			function email_exists( $email ) {
				global $mock_users;
				foreach ( $mock_users as $user_id => $user ) {
					if ( $user['user_email'] === $email ) {
						return $user_id;
					}
				}
				return false;
			}
		}

		// Mock URL functions
		if ( ! function_exists( 'wp_login_url' ) ) {
			function wp_login_url( $redirect = '', $force_reauth = false ) {
				return 'http://example.com/wp-login.php';
			}
		}

		if ( ! function_exists( 'wp_logout_url' ) ) {
			function wp_logout_url( $redirect = '' ) {
				return 'http://example.com/wp-login.php?action=logout';
			}
		}

		if ( ! function_exists( 'admin_url' ) ) {
			function admin_url( $path = '', $scheme = 'admin' ) {
				return 'http://example.com/wp-admin/' . $path;
			}
		}

		// Mock sanitization functions
		if ( ! function_exists( 'sanitize_text_field' ) ) {
			function sanitize_text_field( $str ) {
				return trim( strip_tags( $str ) );
			}
		}

		if ( ! function_exists( 'sanitize_email' ) ) {
			function sanitize_email( $email ) {
				return filter_var( $email, FILTER_SANITIZE_EMAIL );
			}
		}

		if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
			function rest_sanitize_boolean( $value ) {
				return (bool) $value;
			}
		}

		if ( ! function_exists( 'rest_validate_request_arg' ) ) {
			function rest_validate_request_arg( $value, $request, $param ) {
				return true;
			}
		}

		// Mock date/time functions
		if ( ! function_exists( 'current_time' ) ) {
			function current_time( $type, $gmt = 0 ) {
				switch ( $type ) {
					case 'timestamp':
						return time();
					case 'c':
						return date( 'c' );
					case 'mysql':
					default:
						return date( 'Y-m-d H:i:s' );
				}
			}
		}

		// Mock action functions
		if ( ! function_exists( 'do_action' ) ) {
			function do_action( $tag, ...$args ) {
				// Silent for tests
				return true;
			}
		}

		// Mock permission functions
		if ( ! function_exists( 'current_user_can' ) ) {
			function current_user_can( $capability ) {
				return isset( $GLOBALS['mock_user_can_manage_options'] ) ? $GLOBALS['mock_user_can_manage_options'] : false;
			}
		}

		// Mock WordPress classes
		$this->mock_wp_classes();
		
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

		// Mock is_wp_error function
		if ( ! function_exists( 'is_wp_error' ) ) {
			function is_wp_error( $thing ) {
				return $thing instanceof WP_Error;
			}
		}
	}

	/**
	 * Mock dependent classes.
	 */
	private function mock_dependent_classes() {
		// Mock Registration Consent Handler
		if ( ! class_exists( 'RWP_Creator_Suite_Registration_Consent_Handler' ) ) {
			class RWP_Creator_Suite_Registration_Consent_Handler {
				public static function get_consent_meta_key() {
					return 'rwp_creator_suite_consent';
				}

				public function get_user_consent( $user_id ) {
					global $mock_user_consents;
					return isset( $mock_user_consents[ $user_id ] ) ? $mock_user_consents[ $user_id ] : false;
				}

				public function update_user_consent( $user_id, $consent ) {
					global $mock_user_consents;
					$mock_user_consents[ $user_id ] = $consent;
					return true;
				}

				public function get_consent_statistics() {
					global $mock_user_consents;
					$total = count( $mock_user_consents );
					$consented = array_sum( $mock_user_consents );
					
					return array(
						'total_users' => $total,
						'consented_users' => $consented,
						'non_consented_users' => $total - $consented,
						'consent_rate' => $total > 0 ? round( ( $consented / $total ) * 100, 2 ) : 0,
					);
				}
			}
		}
	}

	/**
	 * Set up mock user data.
	 */
	private function setup_mock_user() {
		$GLOBALS['mock_users'] = array(
			$this->test_user_id => array(
				'ID' => $this->test_user_id,
				'display_name' => 'Test User',
				'user_email' => 'test@example.com',
				'user_registered' => '2024-01-01 00:00:00',
			),
		);
		
		$GLOBALS['mock_user_consents'] = array(
			$this->test_user_id => true,
		);
		
		$GLOBALS['mock_current_user_id'] = $this->test_user_id;
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Reset global mocks
		unset( $GLOBALS['mock_current_user_id'] );
		unset( $GLOBALS['mock_users'] );
		unset( $GLOBALS['mock_user_consents'] );
		unset( $GLOBALS['mock_user_can_manage_options'] );
		unset( $GLOBALS['wp_rest_routes'] );
		
		parent::tear_down();
	}

	/**
	 * Test API initialization.
	 */
	public function test_api_initialization() {
		$this->assertInstanceOf( 'RWP_Creator_Suite_Account_API', $this->api );
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
			'/consent/status',
			'/consent/update',
			'/account/dashboard',
			'/account/profile',
			'/consent/statistics',
		);
		
		foreach ( $expected_routes as $route ) {
			$this->assertArrayHasKey( $route, $routes );
		}
	}

	/**
	 * Test get consent status - logged in user.
	 */
	public function test_get_consent_status_logged_in() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request();
		$response = $this->api->get_consent_status( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'consent', $data['data'] );
		$this->assertArrayHasKey( 'user_id', $data['data'] );
		$this->assertArrayHasKey( 'meta_key', $data['data'] );
		
		$this->assertEquals( $this->test_user_id, $data['data']['user_id'] );
		$this->assertTrue( $data['data']['consent'] );
	}

	/**
	 * Test get consent status - not logged in.
	 */
	public function test_get_consent_status_not_logged_in() {
		$GLOBALS['mock_current_user_id'] = 0;
		
		$request = new WP_REST_Request();
		$response = $this->api->get_consent_status( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'unauthorized', $data['code'] );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test update consent - valid request.
	 */
	public function test_update_consent_valid() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request( array(
			'consent' => false,
		) );
		
		$response = $this->api->update_consent( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertFalse( $data['data']['consent'] );
		$this->assertEquals( $this->test_user_id, $data['data']['user_id'] );
		$this->assertArrayHasKey( 'updated_at', $data['data'] );
	}

	/**
	 * Test update consent - invalid parameter.
	 */
	public function test_update_consent_invalid_parameter() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request( array(
			'consent' => 'invalid',
		) );
		
		$response = $this->api->update_consent( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'invalid_consent', $data['code'] );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test update consent - not logged in.
	 */
	public function test_update_consent_not_logged_in() {
		$GLOBALS['mock_current_user_id'] = 0;
		
		$request = new WP_REST_Request( array(
			'consent' => true,
		) );
		
		$response = $this->api->update_consent( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'unauthorized', $data['code'] );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test get dashboard data.
	 */
	public function test_get_dashboard_data() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request();
		$response = $this->api->get_dashboard_data( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		
		$dashboard = $data['data'];
		$this->assertArrayHasKey( 'user', $dashboard );
		$this->assertArrayHasKey( 'consent', $dashboard );
		$this->assertArrayHasKey( 'account', $dashboard );
		
		// Check user data
		$this->assertEquals( $this->test_user_id, $dashboard['user']['id'] );
		$this->assertEquals( 'Test User', $dashboard['user']['display_name'] );
		$this->assertEquals( 'test@example.com', $dashboard['user']['email'] );
		
		// Check consent data
		$this->assertTrue( $dashboard['consent']['status'] );
		
		// Check account URLs
		$this->assertArrayHasKey( 'login_url', $dashboard['account'] );
		$this->assertArrayHasKey( 'profile_url', $dashboard['account'] );
		$this->assertArrayHasKey( 'logout_url', $dashboard['account'] );
	}

	/**
	 * Test get profile data.
	 */
	public function test_get_profile_data() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request();
		$response = $this->api->get_profile_data( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		
		$profile = $data['data'];
		$this->assertEquals( $this->test_user_id, $profile['user_id'] );
		$this->assertEquals( 'Test User', $profile['display_name'] );
		$this->assertEquals( 'test@example.com', $profile['user_email'] );
	}

	/**
	 * Test get profile data - user not found.
	 */
	public function test_get_profile_data_user_not_found() {
		$GLOBALS['mock_current_user_id'] = 999; // Non-existent user
		
		$request = new WP_REST_Request();
		$response = $this->api->get_profile_data( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'user_not_found', $data['code'] );
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test update profile data - display name.
	 */
	public function test_update_profile_data_display_name() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request( array(
			'display_name' => 'Updated Test User',
		) );
		
		$response = $this->api->update_profile_data( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		
		$profile = $data['data'];
		$this->assertEquals( 'Updated Test User', $profile['display_name'] );
		$this->assertContains( 'display_name', $profile['updated_fields'] );
		$this->assertArrayHasKey( 'updated_at', $profile );
	}

	/**
	 * Test update profile data - email.
	 */
	public function test_update_profile_data_email() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request( array(
			'user_email' => 'updated@example.com',
		) );
		
		$response = $this->api->update_profile_data( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		
		$profile = $data['data'];
		$this->assertEquals( 'updated@example.com', $profile['user_email'] );
		$this->assertContains( 'user_email', $profile['updated_fields'] );
	}

	/**
	 * Test update profile data - invalid email.
	 */
	public function test_update_profile_data_invalid_email() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request( array(
			'user_email' => 'invalid-email',
		) );
		
		$response = $this->api->update_profile_data( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'invalid_email', $data['code'] );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test update profile data - empty display name.
	 */
	public function test_update_profile_data_empty_display_name() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request( array(
			'display_name' => '   ',
		) );
		
		$response = $this->api->update_profile_data( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'invalid_display_name', $data['code'] );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test update profile data - email exists.
	 */
	public function test_update_profile_data_email_exists() {
		$this->setup_mock_user();
		
		// Add another user with an email
		$GLOBALS['mock_users'][456] = array(
			'ID' => 456,
			'user_email' => 'existing@example.com',
		);
		
		$request = new WP_REST_Request( array(
			'user_email' => 'existing@example.com',
		) );
		
		$response = $this->api->update_profile_data( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'email_exists', $data['code'] );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test update profile data - no changes.
	 */
	public function test_update_profile_data_no_changes() {
		$this->setup_mock_user();
		
		$request = new WP_REST_Request( array(
			'display_name' => 'Test User', // Same as current
			'user_email' => 'test@example.com', // Same as current
		) );
		
		$response = $this->api->update_profile_data( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'no_changes', $data['code'] );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test get consent statistics - admin user.
	 */
	public function test_get_consent_statistics_admin() {
		$this->setup_mock_user();
		$GLOBALS['mock_user_can_manage_options'] = true;
		
		$request = new WP_REST_Request();
		$response = $this->api->get_consent_statistics( $request );
		
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		
		$stats = $data['data'];
		$this->assertArrayHasKey( 'total_users', $stats );
		$this->assertArrayHasKey( 'consented_users', $stats );
		$this->assertArrayHasKey( 'non_consented_users', $stats );
		$this->assertArrayHasKey( 'consent_rate', $stats );
	}

	/**
	 * Test email validation method.
	 */
	public function test_validate_email() {
		$request = new WP_REST_Request();
		
		// Test valid email
		$result = $this->api->validate_email( 'test@example.com', $request, 'user_email' );
		$this->assertTrue( $result );
		
		// Test invalid email
		$result = $this->api->validate_email( 'invalid-email', $request, 'user_email' );
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_email', $result->get_error_code() );
		
		// Test empty email (should be allowed)
		$result = $this->api->validate_email( '', $request, 'user_email' );
		$this->assertTrue( $result );
		
		// Test null email (should be allowed)
		$result = $this->api->validate_email( null, $request, 'user_email' );
		$this->assertTrue( $result );
	}

	/**
	 * Test logged in user permission check.
	 */
	public function test_check_user_logged_in() {
		// Test with logged in user
		$GLOBALS['mock_current_user_id'] = $this->test_user_id;
		
		$request = new WP_REST_Request();
		$result = $this->api->check_user_logged_in( $request );
		$this->assertTrue( $result );
		
		// Test with not logged in user
		$GLOBALS['mock_current_user_id'] = 0;
		
		$result = $this->api->check_user_logged_in( $request );
		$this->assertFalse( $result );
	}

	/**
	 * Test admin permission check.
	 */
	public function test_check_admin_permission() {
		// Test with admin user
		$GLOBALS['mock_user_can_manage_options'] = true;
		
		$request = new WP_REST_Request();
		$result = $this->api->check_admin_permission( $request );
		$this->assertTrue( $result );
		
		// Test with non-admin user
		$GLOBALS['mock_user_can_manage_options'] = false;
		
		$result = $this->api->check_admin_permission( $request );
		$this->assertFalse( $result );
	}
}