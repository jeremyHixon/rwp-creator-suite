<?php
/**
 * PHPUnit bootstrap file for RWP Creator Suite
 *
 * @package RWP_Creator_Suite
 */

// Check if we're running in CI or local environment
$wordpress_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wordpress_tests_dir ) {
	$wordpress_tests_dir = '/tmp/wordpress-tests-lib';
}

// Check for WordPress test configuration
if ( ! file_exists( $wordpress_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test suite at: $wordpress_tests_dir" . PHP_EOL;
	echo "Please run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest" . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $wordpress_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/rwp-creator-suite.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $wordpress_tests_dir . '/includes/bootstrap.php';

// Load PHPUnit Polyfills for cross-version compatibility
if ( class_exists( 'Yoast\PHPUnitPolyfills\Autoload' ) ) {
	require_once 'vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
}

// Load Composer autoloader if available
if ( file_exists( dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php' ) ) {
	require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
}

// Define constants for testing
if ( ! defined( 'RWP_CREATOR_SUITE_TESTS_RUNNING' ) ) {
	define( 'RWP_CREATOR_SUITE_TESTS_RUNNING', true );
}

// Mock external dependencies
if ( class_exists( 'Brain\Monkey' ) ) {
	Brain\Monkey\setUp();
}