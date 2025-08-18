<?php
/**
 * Comment Security Tests
 *
 * @package RWP_Creator_Suite
 * @subpackage Tests
 */

require_once __DIR__ . '/bootstrap.php';

class Test_Comment_Security extends Plugin_Name_Test_Case {

	private $comment_security;

	protected function setUp(): void {
		parent::setUp();
		
		// Mock WordPress functions
		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( true );
		\Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 1 );
		\Brain\Monkey\Functions\when( 'current_time' )->with( 'timestamp' )->justReturn( time() );
		\Brain\Monkey\Functions\when( 'defined' )->justReturn( false );
		
		// Create instance
		$this->comment_security = new RWP_Creator_Suite_Comment_Security();
	}

	/**
	 * Test that XML-RPC comment methods are properly disabled.
	 */
	public function test_xmlrpc_comment_methods_disabled() {
		// Create mock methods array with comment methods
		$methods = array(
			'wp.newComment'        => 'wp_newComment',
			'wp.editComment'       => 'wp_editComment',
			'wp.deleteComment'     => 'wp_deleteComment',
			'wp.getComment'        => 'wp_getComment',
			'wp.getComments'       => 'wp_getComments',
			'wp.getCommentCount'   => 'wp_getCommentCount',
			'wp.getCommentStatusList' => 'wp_getCommentStatusList',
			'wp.getPost'           => 'wp_getPost', // Should remain
			'wp.getPosts'          => 'wp_getPosts', // Should remain
		);

		$filtered_methods = $this->comment_security->disable_xmlrpc_comment_methods( $methods );

		// Verify comment methods are removed
		$this->assertArrayNotHasKey( 'wp.newComment', $filtered_methods );
		$this->assertArrayNotHasKey( 'wp.editComment', $filtered_methods );
		$this->assertArrayNotHasKey( 'wp.deleteComment', $filtered_methods );
		$this->assertArrayNotHasKey( 'wp.getComment', $filtered_methods );
		$this->assertArrayNotHasKey( 'wp.getComments', $filtered_methods );
		$this->assertArrayNotHasKey( 'wp.getCommentCount', $filtered_methods );
		$this->assertArrayNotHasKey( 'wp.getCommentStatusList', $filtered_methods );

		// Verify non-comment methods remain
		$this->assertArrayHasKey( 'wp.getPost', $filtered_methods );
		$this->assertArrayHasKey( 'wp.getPosts', $filtered_methods );
	}

	/**
	 * Test that REST API comment endpoints are properly disabled.
	 */
	public function test_rest_comment_endpoints_disabled() {
		// Create mock endpoints array with comment endpoints
		$endpoints = array(
			'/wp/v2/comments' => array(
				array(
					'methods'  => 'GET',
					'callback' => 'get_comments_callback',
				),
				array(
					'methods'  => 'POST',
					'callback' => 'create_comment_callback',
				),
			),
			'/wp/v2/comments/(?P<id>[\d]+)' => array(
				array(
					'methods'  => 'GET',
					'callback' => 'get_comment_callback',
				),
			),
			'/wp/v2/posts' => array( // Should remain
				array(
					'methods'  => 'GET',
					'callback' => 'get_posts_callback',
				),
			),
		);

		$filtered_endpoints = $this->comment_security->disable_rest_comment_endpoints( $endpoints );

		// Verify comment endpoints are removed
		$this->assertArrayNotHasKey( '/wp/v2/comments', $filtered_endpoints );
		$this->assertArrayNotHasKey( '/wp/v2/comments/(?P<id>[\d]+)', $filtered_endpoints );

		// Verify non-comment endpoints remain
		$this->assertArrayHasKey( '/wp/v2/posts', $filtered_endpoints );
	}

	/**
	 * Test that the security module can report its status correctly.
	 */
	public function test_security_status() {
		// Mock the has_filter function
		\Brain\Monkey\Functions\when( 'has_filter' )->justReturn( true );

		$this->assertTrue( $this->comment_security->is_active() );
	}

	/**
	 * Test that disabled methods are properly reported.
	 */
	public function test_get_disabled_methods() {
		$disabled_methods = $this->comment_security->get_disabled_methods();

		$this->assertArrayHasKey( 'xmlrpc_methods', $disabled_methods );
		$this->assertArrayHasKey( 'rest_endpoints', $disabled_methods );

		// Verify expected XML-RPC methods are listed
		$expected_xmlrpc = array(
			'wp.newComment',
			'wp.editComment',
			'wp.deleteComment',
			'wp.getComment',
			'wp.getComments',
			'wp.getCommentCount',
			'wp.getCommentStatusList',
		);
		$this->assertEquals( $expected_xmlrpc, $disabled_methods['xmlrpc_methods'] );

		// Verify expected REST endpoints are listed
		$expected_rest = array(
			'/wp/v2/comments',
			'/wp/v2/comments/(?P<id>[\d]+)',
		);
		$this->assertEquals( $expected_rest, $disabled_methods['rest_endpoints'] );
	}

	/**
	 * Test that initialization adds proper filters.
	 */
	public function test_initialization() {
		\Brain\Monkey\Actions\expectAdded( 'init' );
		\Brain\Monkey\Filters\expectAdded( 'xmlrpc_methods' );
		\Brain\Monkey\Filters\expectAdded( 'rest_endpoints' );

		$this->comment_security->init();

		$this->assertTrue( true ); // If we get here, the expectations passed
	}
}

// Run a simple test if called directly
if ( basename( __FILE__ ) === basename( $_SERVER['SCRIPT_NAME'] ) ) {
	echo "Running Comment Security Tests...\n";
	
	// Mock the Error Logger class for testing
	if ( ! class_exists( 'RWP_Creator_Suite_Error_Logger' ) ) {
		class RWP_Creator_Suite_Error_Logger {
			const LOG_LEVEL_INFO = 'info';
			const LOG_LEVEL_ERROR = 'error';
			
			public static function log( $message, $level = '', $context = array() ) {
				echo "LOG [{$level}]: {$message}\n";
				if ( ! empty( $context ) ) {
					echo "  Context: " . print_r( $context, true ) . "\n";
				}
			}
		}
	}
	
	// Include the class being tested
	require_once dirname( __DIR__ ) . '/src/modules/security/class-comment-security.php';
	
	// Simple functional test
	$security = new RWP_Creator_Suite_Comment_Security();
	
	// Test XML-RPC method filtering
	echo "Testing XML-RPC method filtering...\n";
	$test_methods = array(
		'wp.newComment' => 'callback',
		'wp.getPost' => 'callback',
		'wp.editComment' => 'callback',
	);
	
	$filtered = $security->disable_xmlrpc_comment_methods( $test_methods );
	
	if ( ! isset( $filtered['wp.newComment'] ) && ! isset( $filtered['wp.editComment'] ) && isset( $filtered['wp.getPost'] ) ) {
		echo "✓ XML-RPC comment methods correctly filtered\n";
	} else {
		echo "✗ XML-RPC filtering failed\n";
	}
	
	// Test REST endpoint filtering
	echo "Testing REST endpoint filtering...\n";
	$test_endpoints = array(
		'/wp/v2/comments' => array( 'callback' ),
		'/wp/v2/posts' => array( 'callback' ),
		'/wp/v2/comments/123' => array( 'callback' ),
	);
	
	$filtered_endpoints = $security->disable_rest_comment_endpoints( $test_endpoints );
	
	if ( ! isset( $filtered_endpoints['/wp/v2/comments'] ) && isset( $filtered_endpoints['/wp/v2/posts'] ) ) {
		echo "✓ REST comment endpoints correctly filtered\n";
	} else {
		echo "✗ REST endpoint filtering failed\n";
	}
	
	echo "Comment Security Tests completed.\n";
}