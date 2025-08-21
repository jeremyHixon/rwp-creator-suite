<?php
/**
 * Tests for the Instagram Analyzer API class.
 *
 * @package RWP_Creator_Suite
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test case for the Instagram Analyzer API.
 */
class Test_RWP_Creator_Suite_Instagram_Analyzer_API extends TestCase {

	/**
	 * Instagram Analyzer API instance.
	 *
	 * @var RWP_Creator_Suite_Instagram_Analyzer_API
	 */
	private $api;

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

		if ( ! function_exists( 'sanitize_text_field' ) ) {
			function sanitize_text_field( $str ) {
				return trim( strip_tags( $str ) );
			}
		}

		if ( ! function_exists( 'sanitize_textarea_field' ) ) {
			function sanitize_textarea_field( $str ) {
				return trim( strip_tags( $str ) );
			}
		}

		if ( ! function_exists( 'wp_unslash' ) ) {
			function wp_unslash( $str ) {
				return stripslashes( $str );
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

		if ( ! function_exists( 'update_user_meta' ) ) {
			function update_user_meta( $user_id, $meta_key, $meta_value ) {
				global $mock_user_meta;
				$mock_user_meta[ $user_id ][ $meta_key ] = $meta_value;
				return true;
			}
		}

		if ( ! function_exists( 'get_user_meta' ) ) {
			function get_user_meta( $user_id, $meta_key, $single = false ) {
				global $mock_user_meta;
				if ( isset( $mock_user_meta[ $user_id ][ $meta_key ] ) ) {
					return $mock_user_meta[ $user_id ][ $meta_key ];
				}
				return $single ? '' : array();
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

		if ( ! function_exists( 'error_log' ) ) {
			function error_log( $message ) {
				return true;
			}
		}
		
		$this->api = new RWP_Creator_Suite_Instagram_Analyzer_API();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Reset global mocks
		unset( $GLOBALS['mock_user_logged_in'] );
		unset( $GLOBALS['mock_current_user_id'] );
		unset( $GLOBALS['mock_user_meta'] );
		unset( $GLOBALS['mock_json_response'] );
		
		// Clear $_POST superglobal
		$_POST = array();
		
		parent::tear_down();
	}

	/**
	 * Test API initialization.
	 */
	public function test_api_initialization() {
		$this->assertInstanceOf( 'RWP_Creator_Suite_Instagram_Analyzer_API', $this->api );
	}

	/**
	 * Test sync whitelist with valid data.
	 */
	public function test_sync_whitelist_valid_data() {
		global $mock_json_response;

		$_POST['nonce'] = 'valid_nonce';
		$_POST['whitelist'] = json_encode( array( 'user1', 'user_2' ) );
		$GLOBALS['mock_user_logged_in'] = true;
		$GLOBALS['mock_current_user_id'] = 123;

		$this->api->sync_whitelist();

		$this->assertNotNull( $mock_json_response );
		$this->assertTrue( $mock_json_response['response']['success'] );
	}

	/**
	 * Test sync whitelist with invalid nonce.
	 */
	public function test_sync_whitelist_invalid_nonce() {
		global $mock_json_response;

		$_POST['nonce'] = 'invalid_nonce';
		$_POST['whitelist'] = json_encode( array( 'user1' ) );

		$this->api->sync_whitelist();

		$this->assertNotNull( $mock_json_response );
		$this->assertFalse( $mock_json_response['response']['success'] );
	}

	/**
	 * Test get whitelist endpoint.
	 */
	public function test_get_whitelist() {
		global $mock_json_response;

		$_POST['nonce'] = 'valid_nonce';
		$GLOBALS['mock_user_logged_in'] = true;
		$GLOBALS['mock_current_user_id'] = 123;

		$this->api->get_whitelist();

		$this->assertNotNull( $mock_json_response );
		$this->assertTrue( $mock_json_response['response']['success'] );
	}

	/**
	 * Test Instagram username sanitization.
	 */
	public function test_username_sanitization() {
		// Use reflection to test private method
		$reflection = new ReflectionClass( $this->api );
		$method = $reflection->getMethod( 'sanitize_instagram_username' );
		$method->setAccessible( true );

		// Test normal username
		$result = $method->invokeArgs( $this->api, array( 'username123' ) );
		$this->assertEquals( 'username123', $result );

		// Test username with @ symbol removed
		$result = $method->invokeArgs( $this->api, array( '@username123' ) );
		$this->assertEquals( 'username123', $result );

		// Test empty username
		$result = $method->invokeArgs( $this->api, array( '' ) );
		$this->assertFalse( $result );
	}
}