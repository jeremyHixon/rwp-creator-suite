<?php
/**
 * Tests for the Registration API class.
 *
 * @package RWP_Creator_Suite
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test case for the Registration API.
 */
class Test_RWP_Creator_Suite_Registration_API extends TestCase {

	/**
	 * Registration API instance.
	 *
	 * @var RWP_Creator_Suite_Registration_API
	 */
	private $api;

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();
		
		// Mock WordPress REST API functions
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
		
		$this->api = new RWP_Creator_Suite_Registration_API();
	}

	/**
	 * Test API initialization.
	 */
	public function test_api_initialization() {
		$this->assertInstanceOf( 'RWP_Creator_Suite_Registration_API', $this->api );
	}

	/**
	 * Test nonce generation for different actions.
	 */
	public function test_nonce_generation() {
		// Mock wp_create_nonce
		if ( ! function_exists( 'wp_create_nonce' ) ) {
			function wp_create_nonce( $action ) {
				return 'test_nonce_' . $action;
			}
		}
		
		$registration_nonce = RWP_Creator_Suite_Registration_API::get_registration_nonce();
		$login_nonce = RWP_Creator_Suite_Registration_API::get_login_nonce();
		$api_nonce = RWP_Creator_Suite_Registration_API::get_nonce();
		
		$this->assertEquals( 'test_nonce_rwp_creator_suite_registration', $registration_nonce );
		$this->assertEquals( 'test_nonce_rwp_creator_suite_login', $login_nonce );
		$this->assertEquals( 'test_nonce_rwp_creator_suite_api', $api_nonce );
	}

	/**
	 * Test nonce generation with invalid action.
	 */
	public function test_nonce_generation_invalid_action() {
		if ( ! function_exists( 'wp_create_nonce' ) ) {
			function wp_create_nonce( $action ) {
				return 'test_nonce_' . $action;
			}
		}
		
		$nonce = RWP_Creator_Suite_Registration_API::get_nonce( 'invalid_action' );
		
		// Should default to 'api' for invalid actions
		$this->assertEquals( 'test_nonce_rwp_creator_suite_api', $nonce );
	}

	/**
	 * Test rate limiting functionality.
	 */
	public function test_rate_limiting() {
		// Mock transient functions
		global $transients;
		$transients = array();
		
		if ( ! function_exists( 'get_transient' ) ) {
			function get_transient( $key ) {
				global $transients;
				return isset( $transients[ $key ] ) ? $transients[ $key ] : false;
			}
		}
		
		if ( ! function_exists( 'set_transient' ) ) {
			function set_transient( $key, $value, $expiration ) {
				global $transients;
				$transients[ $key ] = $value;
				return true;
			}
		}
		
		// Use reflection to access private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'check_rate_limit' );
		$method->setAccessible( true );
		
		// Test with valid email
		$result1 = $method->invokeArgs( $this->api, array( 'test@example.com' ) );
		$this->assertTrue( $result1 );
		
		// Test with empty email
		$result2 = $method->invokeArgs( $this->api, array( '' ) );
		$this->assertFalse( $result2 );
		
		// Test rate limiting after multiple attempts
		for ( $i = 0; $i < 4; $i++ ) {
			$method->invokeArgs( $this->api, array( 'test2@example.com' ) );
		}
		$result3 = $method->invokeArgs( $this->api, array( 'test2@example.com' ) );
		$this->assertFalse( $result3 );
	}

	/**
	 * Test registration enabled check.
	 */
	public function test_registration_enabled_check() {
		// Mock get_option
		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				if ( $option === 'users_can_register' ) {
					return 1;
				}
				return $default;
			}
		}
		
		$result = $this->api->check_registration_enabled();
		$this->assertTrue( $result );
	}

	/**
	 * Test registration disabled.
	 */
	public function test_registration_disabled() {
		// Mock get_option to return false
		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				if ( $option === 'users_can_register' ) {
					return 0;
				}
				return $default;
			}
		}
		
		$result = $this->api->check_registration_enabled();
		$this->assertFalse( $result );
	}

	/**
	 * Test invalid nonce in registration request.
	 */
	public function test_invalid_nonce_registration() {
		// Mock WordPress functions
		if ( ! function_exists( 'wp_verify_nonce' ) ) {
			function wp_verify_nonce( $nonce, $action ) {
				return false; // Simulate invalid nonce
			}
		}
		
		if ( ! function_exists( 'sanitize_email' ) ) {
			function sanitize_email( $email ) {
				return filter_var( $email, FILTER_SANITIZE_EMAIL );
			}
		}
		
		// Mock WP_REST_Request
		$request = $this->getMockBuilder( 'WP_REST_Request' )
						->setMethods( array( 'get_param', 'get_header' ) )
						->getMock();
		
		$request->method( 'get_param' )->willReturnMap( array(
			array( 'nonce', 'invalid_nonce' ),
			array( 'email', 'test@example.com' ),
		) );
		
		$request->method( 'get_header' )->willReturn( 'Test User Agent' );
		
		$result = $this->api->handle_registration( $request );
		
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_nonce', $result->get_error_code() );
	}

	/**
	 * Test valid registration request structure.
	 */
	public function test_valid_registration_request_structure() {
		global $wp_rest_routes;
		
		$this->api->register_routes();
		
		// Check that the registration route is registered
		$this->assertArrayHasKey( 'rwp-creator-suite/v1', $wp_rest_routes );
		$this->assertArrayHasKey( '/auth/register', $wp_rest_routes['rwp-creator-suite/v1'] );
		
		$route_config = $wp_rest_routes['rwp-creator-suite/v1']['/auth/register'];
		
		$this->assertEquals( 'POST', $route_config['methods'] );
		$this->assertArrayHasKey( 'email', $route_config['args'] );
		$this->assertTrue( $route_config['args']['email']['required'] );
		$this->assertEquals( 'is_email', $route_config['args']['email']['validate_callback'] );
	}
}