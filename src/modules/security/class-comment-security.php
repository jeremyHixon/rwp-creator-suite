<?php
/**
 * Comment Security Manager
 *
 * Disables commenting functionality via XML-RPC and REST API while preserving
 * other functionality.
 *
 * @package    RWP_Creator_Suite
 * @subpackage RWP_Creator_Suite/Security
 * @since      1.6.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class RWP_Creator_Suite_Comment_Security
 *
 * @since 1.6.1
 */
class RWP_Creator_Suite_Comment_Security {

	/**
	 * Initialize the comment security functionality.
	 *
	 * @since 1.6.1
	 */
	public function init() {
		// Disable XML-RPC commenting methods
		add_filter( 'xmlrpc_methods', array( $this, 'disable_xmlrpc_comment_methods' ) );
		
		// Disable REST API comment endpoints
		add_filter( 'rest_endpoints', array( $this, 'disable_rest_comment_endpoints' ) );
		
		// Log security actions
		add_action( 'init', array( $this, 'log_security_initialization' ) );
	}

	/**
	 * Disable XML-RPC commenting methods while preserving other methods.
	 *
	 * @since 1.6.1
	 * @param array $methods Array of available XML-RPC methods.
	 * @return array Modified array with comment methods removed.
	 */
	public function disable_xmlrpc_comment_methods( $methods ) {
		// Remove comment-related XML-RPC methods only
		$comment_methods = array(
			'wp.newComment',
			'wp.editComment',
			'wp.deleteComment',
			'wp.getComment',
			'wp.getComments',
			'wp.getCommentCount',
			'wp.getCommentStatusList',
		);

		foreach ( $comment_methods as $method ) {
			if ( isset( $methods[ $method ] ) ) {
				unset( $methods[ $method ] );
			}
		}

		// Log the disabled methods for audit purposes
		RWP_Creator_Suite_Error_Logger::log(
			'XML-RPC comment methods disabled',
			RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
			array(
				'disabled_methods' => $comment_methods,
				'remaining_methods_count' => count( $methods ),
			)
		);

		return $methods;
	}

	/**
	 * Disable REST API comment endpoints while preserving other endpoints.
	 *
	 * @since 1.6.1
	 * @param array $endpoints Array of available REST API endpoints.
	 * @return array Modified array with comment endpoints removed.
	 */
	public function disable_rest_comment_endpoints( $endpoints ) {
		// Remove comment-related REST API endpoints
		$comment_endpoint_patterns = array(
			'/wp/v2/comments',
			'/wp/v2/comments/(?P<id>[\d]+)',
		);

		foreach ( $comment_endpoint_patterns as $pattern ) {
			if ( isset( $endpoints[ $pattern ] ) ) {
				unset( $endpoints[ $pattern ] );
			}
		}

		// Log the action for audit purposes
		RWP_Creator_Suite_Error_Logger::log(
			'REST API comment endpoints disabled',
			RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
			array(
				'disabled_patterns' => $comment_endpoint_patterns,
				'remaining_endpoints_count' => count( $endpoints ),
			)
		);

		return $endpoints;
	}

	/**
	 * Log security initialization for audit trail.
	 *
	 * @since 1.6.1
	 */
	public function log_security_initialization() {
		// Only log once per request to avoid spam
		if ( ! defined( 'RWP_COMMENT_SECURITY_LOGGED' ) ) {
			define( 'RWP_COMMENT_SECURITY_LOGGED', true );

			RWP_Creator_Suite_Error_Logger::log(
				'Comment security module initialized',
				RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
				array(
					'xmlrpc_enabled' => get_option( 'enable_xmlrpc', true ),
					'user_id' => get_current_user_id(),
					'timestamp' => current_time( 'timestamp' ),
				)
			);
		}
	}

	/**
	 * Check if comment security is active.
	 *
	 * @since 1.6.1
	 * @return bool True if comment security is active.
	 */
	public function is_active() {
		return has_filter( 'xmlrpc_methods', array( $this, 'disable_xmlrpc_comment_methods' ) ) &&
			   has_filter( 'rest_endpoints', array( $this, 'disable_rest_comment_endpoints' ) );
	}

	/**
	 * Get disabled methods for informational purposes.
	 *
	 * @since 1.6.1
	 * @return array Array containing disabled XML-RPC methods and REST endpoints.
	 */
	public function get_disabled_methods() {
		return array(
			'xmlrpc_methods' => array(
				'wp.newComment',
				'wp.editComment',
				'wp.deleteComment',
				'wp.getComment',
				'wp.getComments',
				'wp.getCommentCount',
				'wp.getCommentStatusList',
			),
			'rest_endpoints' => array(
				'/wp/v2/comments',
				'/wp/v2/comments/(?P<id>[\d]+)',
			),
		);
	}
}