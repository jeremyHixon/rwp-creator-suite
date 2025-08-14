<?php
/**
 * Tests for the Error Logger class.
 *
 * @package RWP_Creator_Suite
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test case for the Error Logger.
 */
class Test_RWP_Creator_Suite_Error_Logger extends TestCase {

	/**
	 * Captured error logs.
	 *
	 * @var array
	 */
	private $captured_logs = array();

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();
		
		$this->captured_logs = array();
		
		// Mock WordPress functions
		if ( ! function_exists( 'current_time' ) ) {
			function current_time( $format ) {
				return date( $format );
			}
		}
		
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			function is_user_logged_in() {
				return false;
			}
		}
		
		if ( ! function_exists( 'get_current_user_id' ) ) {
			function get_current_user_id() {
				return 0;
			}
		}
		
		if ( ! function_exists( 'sanitize_text_field' ) ) {
			function sanitize_text_field( $str ) {
				return strip_tags( $str );
			}
		}
		
		if ( ! function_exists( 'wp_json_encode' ) ) {
			function wp_json_encode( $data ) {
				return json_encode( $data );
			}
		}
		
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}
		
		if ( ! defined( 'RWP_CREATOR_SUITE_VERSION' ) ) {
			define( 'RWP_CREATOR_SUITE_VERSION', '1.5.1' );
		}
		
		// Mock error_log to capture logs
		if ( ! function_exists( 'error_log' ) ) {
			function error_log( $message ) {
				global $test_captured_logs;
				$test_captured_logs[] = $message;
			}
		}
	}

	/**
	 * Test basic logging functionality.
	 */
	public function test_basic_logging() {
		global $test_captured_logs;
		$test_captured_logs = array();
		
		RWP_Creator_Suite_Error_Logger::log( 'Test error message' );
		
		$this->assertCount( 1, $test_captured_logs );
		$this->assertStringContainsString( 'Test error message', $test_captured_logs[0] );
		$this->assertStringContainsString( 'RWP Creator Suite', $test_captured_logs[0] );
	}

	/**
	 * Test different log levels.
	 */
	public function test_log_levels() {
		global $test_captured_logs;
		$test_captured_logs = array();
		
		RWP_Creator_Suite_Error_Logger::log( 
			'Debug message', 
			RWP_Creator_Suite_Error_Logger::LOG_LEVEL_DEBUG 
		);
		
		RWP_Creator_Suite_Error_Logger::log( 
			'Critical message', 
			RWP_Creator_Suite_Error_Logger::LOG_LEVEL_CRITICAL 
		);
		
		// Should have both messages
		$this->assertCount( 2, $test_captured_logs );
		$this->assertStringContainsString( '[debug]', $test_captured_logs[0] );
		$this->assertStringContainsString( '[critical]', $test_captured_logs[1] );
	}

	/**
	 * Test logging with context data.
	 */
	public function test_logging_with_context() {
		global $test_captured_logs;
		$test_captured_logs = array();
		
		$context = array(
			'user_id' => 123,
			'action' => 'test_action',
			'extra_data' => array( 'key' => 'value' ),
		);
		
		RWP_Creator_Suite_Error_Logger::log( 'Test with context', 'error', $context );
		
		$this->assertCount( 1, $test_captured_logs );
		$this->assertStringContainsString( 'Test with context', $test_captured_logs[0] );
		$this->assertStringContainsString( 'user_id', $test_captured_logs[0] );
		$this->assertStringContainsString( '123', $test_captured_logs[0] );
	}

	/**
	 * Test security event logging.
	 */
	public function test_security_event_logging() {
		global $test_captured_logs;
		$test_captured_logs = array();
		
		// Mock $_SERVER variables
		$_SERVER['HTTP_REFERER'] = 'https://example.com/referer';
		$_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		
		RWP_Creator_Suite_Error_Logger::log_security_event( 'Unauthorized access attempt' );
		
		$this->assertCount( 1, $test_captured_logs );
		$this->assertStringContainsString( 'Unauthorized access attempt', $test_captured_logs[0] );
		$this->assertStringContainsString( 'security', $test_captured_logs[0] );
		$this->assertStringContainsString( '127.0.0.1', $test_captured_logs[0] );
	}

	/**
	 * Test performance logging.
	 */
	public function test_performance_logging() {
		global $test_captured_logs;
		$test_captured_logs = array();
		
		RWP_Creator_Suite_Error_Logger::log_performance( 'Test operation', 2.5 );
		
		$this->assertCount( 1, $test_captured_logs );
		$this->assertStringContainsString( 'Test operation completed in 2.5000 seconds', $test_captured_logs[0] );
		$this->assertStringContainsString( 'performance', $test_captured_logs[0] );
		$this->assertStringContainsString( '[info]', $test_captured_logs[0] );
	}

	/**
	 * Test performance warning for slow operations.
	 */
	public function test_performance_warning() {
		global $test_captured_logs;
		$test_captured_logs = array();
		
		RWP_Creator_Suite_Error_Logger::log_performance( 'Slow operation', 6.0 );
		
		$this->assertCount( 1, $test_captured_logs );
		$this->assertStringContainsString( '[warning]', $test_captured_logs[0] );
	}

	/**
	 * Test log constants are defined.
	 */
	public function test_log_level_constants() {
		$this->assertEquals( 'debug', RWP_Creator_Suite_Error_Logger::LOG_LEVEL_DEBUG );
		$this->assertEquals( 'info', RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO );
		$this->assertEquals( 'warning', RWP_Creator_Suite_Error_Logger::LOG_LEVEL_WARNING );
		$this->assertEquals( 'error', RWP_Creator_Suite_Error_Logger::LOG_LEVEL_ERROR );
		$this->assertEquals( 'critical', RWP_Creator_Suite_Error_Logger::LOG_LEVEL_CRITICAL );
	}

	/**
	 * Test critical error notification prevention.
	 */
	public function test_critical_error_notification_throttling() {
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
		
		if ( ! function_exists( 'do_action' ) ) {
			function do_action( $hook, ...$args ) {
				// Mock do_action
			}
		}
		
		global $test_captured_logs;
		$test_captured_logs = array();
		
		// Log first critical error
		RWP_Creator_Suite_Error_Logger::log( 
			'Critical error', 
			RWP_Creator_Suite_Error_Logger::LOG_LEVEL_CRITICAL 
		);
		
		// Log same critical error again - should still log but not trigger duplicate notification
		RWP_Creator_Suite_Error_Logger::log( 
			'Critical error', 
			RWP_Creator_Suite_Error_Logger::LOG_LEVEL_CRITICAL 
		);
		
		$this->assertCount( 2, $test_captured_logs );
	}

	/**
	 * Test IP address detection.
	 */
	public function test_ip_address_detection() {
		global $test_captured_logs;
		$test_captured_logs = array();
		
		// Test various IP headers
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1, 10.0.0.1';
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		
		RWP_Creator_Suite_Error_Logger::log_security_event( 'IP test' );
		
		$this->assertCount( 1, $test_captured_logs );
		$this->assertStringContainsString( '192.168.1.1', $test_captured_logs[0] );
	}
}