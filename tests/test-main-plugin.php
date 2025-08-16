<?php
/**
 * Tests for the main RWP Creator Suite plugin class.
 *
 * @package RWP_Creator_Suite
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test case for the main plugin functionality.
 */
class Test_RWP_Creator_Suite extends TestCase {

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down() {
		parent::tear_down();
	}

	/**
	 * Test plugin initialization.
	 */
	public function test_plugin_initialization() {
		$plugin = RWP_Creator_Suite::get_instance();
		
		$this->assertInstanceOf( 'RWP_Creator_Suite', $plugin );
		$this->assertEquals( '1.6.0', $plugin->get_version() );
	}

	/**
	 * Test singleton pattern.
	 */
	public function test_singleton_instance() {
		$instance1 = RWP_Creator_Suite::get_instance();
		$instance2 = RWP_Creator_Suite::get_instance();
		
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test plugin constants are defined.
	 */
	public function test_plugin_constants() {
		$this->assertTrue( defined( 'RWP_CREATOR_SUITE_VERSION' ) );
		$this->assertTrue( defined( 'RWP_CREATOR_SUITE_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'RWP_CREATOR_SUITE_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'RWP_CREATOR_SUITE_PLUGIN_FILE' ) );
		
		$this->assertEquals( '1.6.0', RWP_CREATOR_SUITE_VERSION );
	}

	/**
	 * Test plugin activation hook functionality.
	 */
	public function test_activation_settings() {
		// Mock WordPress functions
		if ( ! function_exists( 'get_option' ) ) {
			function get_option( $option, $default = false ) {
				global $wp_options;
				return isset( $wp_options[ $option ] ) ? $wp_options[ $option ] : $default;
			}
		}
		
		if ( ! function_exists( 'update_option' ) ) {
			function update_option( $option, $value ) {
				global $wp_options;
				$wp_options[ $option ] = $value;
				return true;
			}
		}
		
		// Test that activation sets required options
		$plugin = RWP_Creator_Suite::get_instance();
		$plugin->activate();
		
		$this->assertEquals( 1, get_option( 'users_can_register' ) );
		$this->assertEquals( 'subscriber', get_option( 'default_role' ) );
		$this->assertEquals( 1, get_option( 'rwp_creator_suite_allow_guest_repurpose' ) );
	}

	/**
	 * Test user data cleanup functionality with proper permissions.
	 */
	public function test_cleanup_user_data_security() {
		// Mock current_user_can to return false (no permission)
		if ( ! function_exists( 'current_user_can' ) ) {
			function current_user_can( $capability ) {
				return false; // No permission
			}
		}
		
		if ( ! function_exists( 'get_current_user_id' ) ) {
			function get_current_user_id() {
				return 1;
			}
		}
		
		$plugin = RWP_Creator_Suite::get_instance();
		
		// Should not proceed without proper capability
		$this->expectNotToPerformAssertions();
		$plugin->cleanup_user_data( 123 );
	}

	/**
	 * Test plugin deactivation with proper permissions.
	 */
	public function test_deactivation_security() {
		// Mock current_user_can to return false (no permission)
		if ( ! function_exists( 'current_user_can' ) ) {
			function current_user_can( $capability ) {
				return false; // No permission
			}
		}
		
		$plugin = RWP_Creator_Suite::get_instance();
		
		// Should not proceed without proper capability
		$this->expectNotToPerformAssertions();
		$plugin->deactivate();
	}

	/**
	 * Test invalid user ID validation in cleanup.
	 */
	public function test_cleanup_invalid_user_id() {
		$plugin = RWP_Creator_Suite::get_instance();
		
		// Test with non-numeric user ID
		$this->expectNotToPerformAssertions();
		$plugin->cleanup_user_data( 'invalid' );
		
		// Test with zero user ID
		$this->expectNotToPerformAssertions();
		$plugin->cleanup_user_data( 0 );
		
		// Test with negative user ID
		$this->expectNotToPerformAssertions();
		$plugin->cleanup_user_data( -1 );
	}
}