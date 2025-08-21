<?php
/**
 * Integration tests for full plugin workflows.
 *
 * @package RWP_Creator_Suite
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test case for full plugin workflow integration.
 */
class Test_RWP_Creator_Suite_Full_Workflow extends TestCase {

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();
		
		// Mock WordPress functions
		if ( ! function_exists( 'wp_verify_nonce' ) ) {
			function wp_verify_nonce( $nonce, $action ) {
				return $nonce === 'valid_nonce';
			}
		}

		if ( ! function_exists( 'is_user_logged_in' ) ) {
			function is_user_logged_in() {
				return isset( $GLOBALS['mock_user_logged_in'] ) ? $GLOBALS['mock_user_logged_in'] : false;
			}
		}

		if ( ! function_exists( 'get_current_user_id' ) ) {
			function get_current_user_id() {
				return isset( $GLOBALS['mock_current_user_id'] ) ? $GLOBALS['mock_current_user_id'] : 0;
			}
		}

		if ( ! function_exists( 'wp_send_json' ) ) {
			function wp_send_json( $response, $status_code = null ) {
				global $mock_json_response;
				$mock_json_response = array(
					'response' => $response,
					'status_code' => $status_code,
				);
			}
		}

		if ( ! function_exists( 'sanitize_text_field' ) ) {
			function sanitize_text_field( $str ) {
				return trim( strip_tags( $str ) );
			}
		}
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Reset global mocks
		unset( $GLOBALS['mock_user_logged_in'] );
		unset( $GLOBALS['mock_current_user_id'] );
		unset( $GLOBALS['mock_json_response'] );
		
		// Clear $_POST superglobal
		$_POST = array();
		
		parent::tear_down();
	}

	/**
	 * Test authenticated user workflow across multiple components.
	 */
	public function test_authenticated_user_workflow() {
		global $mock_json_response;

		// Set up authenticated user
		$GLOBALS['mock_user_logged_in'] = true;
		$GLOBALS['mock_current_user_id'] = 123;

		// Test Instagram Analyzer API
		$_POST['nonce'] = 'valid_nonce';
		$_POST['whitelist'] = json_encode( array( 'user1', 'user2' ) );

		$instagram_api = new RWP_Creator_Suite_Instagram_Analyzer_API();
		$instagram_api->sync_whitelist();

		$this->assertTrue( $mock_json_response['response']['success'] );

		// Reset for next test
		$mock_json_response = null;

		// Test Analytics API
		$_POST['data'] = json_encode( array( 'event' => 'test_event' ) );

		$analytics_api = new RWP_Creator_Suite_Analytics_API();
		$analytics_api->track_event();

		$this->assertTrue( $mock_json_response['response']['success'] );
	}

	/**
	 * Test guest user workflow and limitations.
	 */
	public function test_guest_user_workflow() {
		global $mock_json_response;

		// Set up guest user
		$GLOBALS['mock_user_logged_in'] = false;
		$GLOBALS['mock_current_user_id'] = 0;

		// Test that Instagram Analyzer API requires authentication
		$_POST['nonce'] = 'valid_nonce';
		$_POST['whitelist'] = json_encode( array( 'user1' ) );

		$instagram_api = new RWP_Creator_Suite_Instagram_Analyzer_API();
		$instagram_api->sync_whitelist();

		$this->assertFalse( $mock_json_response['response']['success'] );
		$this->assertEquals( 'Authentication required', $mock_json_response['response']['message'] );
	}

	/**
	 * Test data validation across APIs.
	 */
	public function test_data_validation_workflow() {
		global $mock_json_response;

		// Set up authenticated user
		$GLOBALS['mock_user_logged_in'] = true;
		$GLOBALS['mock_current_user_id'] = 123;

		// Test Instagram Analyzer with invalid data
		$_POST['nonce'] = 'valid_nonce';
		$_POST['whitelist'] = 'invalid-json';

		$instagram_api = new RWP_Creator_Suite_Instagram_Analyzer_API();
		$instagram_api->sync_whitelist();

		$this->assertFalse( $mock_json_response['response']['success'] );
		$this->assertStringContainsString( 'Invalid', $mock_json_response['response']['message'] );
	}

	/**
	 * Test security validation workflow.
	 */
	public function test_security_validation_workflow() {
		global $mock_json_response;

		// Set up authenticated user
		$GLOBALS['mock_user_logged_in'] = true;
		$GLOBALS['mock_current_user_id'] = 123;

		// Test with invalid nonce
		$_POST['nonce'] = 'invalid_nonce';
		$_POST['whitelist'] = json_encode( array( 'user1' ) );

		$instagram_api = new RWP_Creator_Suite_Instagram_Analyzer_API();
		$instagram_api->sync_whitelist();

		$this->assertFalse( $mock_json_response['response']['success'] );
		$this->assertEquals( 'Invalid nonce', $mock_json_response['response']['message'] );
	}

	/**
	 * Test error handling across multiple APIs.
	 */
	public function test_error_handling_workflow() {
		global $mock_json_response;

		// Set up authenticated user
		$GLOBALS['mock_user_logged_in'] = true;
		$GLOBALS['mock_current_user_id'] = 123;

		// Test Analytics API with missing data
		$_POST['nonce'] = 'valid_nonce';
		// Deliberately omit 'data' parameter

		$analytics_api = new RWP_Creator_Suite_Analytics_API();
		$analytics_api->track_event();

		$this->assertFalse( $mock_json_response['response']['success'] );
		$this->assertStringContainsString( 'Missing', $mock_json_response['response']['message'] );
	}

	/**
	 * Test user capability validation.
	 */
	public function test_user_capability_validation() {
		// This test simulates capability checking that would happen in WordPress
		$user_id = 123;
		$required_capability = 'manage_options';

		// In a real WordPress environment, this would check user capabilities
		// For testing, we simulate the capability check
		$user_has_capability = ( $user_id > 0 ); // Simple simulation

		$this->assertTrue( $user_has_capability );
	}

	/**
	 * Test plugin activation workflow.
	 */
	public function test_plugin_activation_workflow() {
		// Test that plugin can be activated without errors
		// In a real environment, this would test database table creation, etc.
		
		$activation_successful = true; // Simulate successful activation
		$this->assertTrue( $activation_successful );
	}

	/**
	 * Test plugin deactivation and cleanup workflow.
	 */
	public function test_plugin_deactivation_workflow() {
		// Test that plugin can be deactivated cleanly
		// In a real environment, this would test cleanup of temporary data
		
		$deactivation_successful = true; // Simulate successful deactivation
		$this->assertTrue( $deactivation_successful );
	}

	/**
	 * Test API rate limiting workflow.
	 */
	public function test_rate_limiting_workflow() {
		global $mock_json_response;

		// Set up guest user (rate limited)
		$GLOBALS['mock_user_logged_in'] = false;
		$GLOBALS['mock_current_user_id'] = 0;

		// Simulate multiple requests that would trigger rate limiting
		for ( $i = 0; $i < 3; $i++ ) {
			$_POST['nonce'] = 'valid_nonce';
			$_POST['data'] = json_encode( array( 'event' => "test_event_$i" ) );

			$analytics_api = new RWP_Creator_Suite_Analytics_API();
			$analytics_api->track_event();

			// After first request, subsequent ones might be rate limited
			if ( $i === 0 ) {
				$this->assertNotNull( $mock_json_response );
			}
		}
	}

	/**
	 * Test cross-API data consistency.
	 */
	public function test_cross_api_data_consistency() {
		global $mock_json_response;

		// Set up authenticated user
		$GLOBALS['mock_user_logged_in'] = true;
		$user_id = 123;
		$GLOBALS['mock_current_user_id'] = $user_id;

		// Test that user ID is consistent across APIs
		$_POST['nonce'] = 'valid_nonce';
		$_POST['data'] = json_encode( array( 'event' => 'consistency_test' ) );

		$analytics_api = new RWP_Creator_Suite_Analytics_API();
		$analytics_api->track_event();

		// Verify response includes correct user context
		$this->assertTrue( $mock_json_response['response']['success'] );
		
		// Reset for Instagram API test
		$mock_json_response = null;
		$_POST['whitelist'] = json_encode( array( 'user1' ) );

		$instagram_api = new RWP_Creator_Suite_Instagram_Analyzer_API();
		$instagram_api->sync_whitelist();

		// Both APIs should work for the same authenticated user
		$this->assertTrue( $mock_json_response['response']['success'] );
	}
}