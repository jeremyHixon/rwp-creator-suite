<?php
/**
 * Error Logger Class
 *
 * Provides structured error logging for the RWP Creator Suite plugin.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Error_Logger {

    /**
     * Log levels.
     */
    const LOG_LEVEL_DEBUG = 'debug';
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_CRITICAL = 'critical';

    /**
     * Log an error with structured data.
     *
     * @param string $message Error message.
     * @param string $level Log level.
     * @param array  $context Additional context data.
     */
    public static function log( $message, $level = self::LOG_LEVEL_ERROR, $context = array() ) {
        // Only log if WP_DEBUG is enabled or it's a critical error
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            if ( $level !== self::LOG_LEVEL_CRITICAL && $level !== self::LOG_LEVEL_ERROR ) {
                return;
            }
        }

        $log_entry = array(
            'timestamp' => current_time( 'Y-m-d H:i:s' ),
            'level' => $level,
            'message' => $message,
            'plugin' => 'RWP Creator Suite',
            'version' => RWP_CREATOR_SUITE_VERSION,
            'context' => $context,
        );

        // Add user context if available
        if ( is_user_logged_in() ) {
            $log_entry['user_id'] = get_current_user_id();
        }

        // Add request context
        $log_entry['request_uri'] = isset( $_SERVER['REQUEST_URI'] ) 
            ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) 
            : 'unknown';
        
        $log_entry['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) 
            ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) 
            : 'unknown';

        $formatted_message = sprintf(
            '[%s] RWP Creator Suite [%s]: %s | Context: %s',
            $log_entry['level'],
            $log_entry['timestamp'],
            $message,
            wp_json_encode( $log_entry )
        );

        error_log( $formatted_message );

        // For critical errors, also try to notify if notifications are enabled
        if ( $level === self::LOG_LEVEL_CRITICAL ) {
            self::notify_critical_error( $log_entry );
        }
    }

    /**
     * Log security-related events.
     *
     * @param string $event Security event description.
     * @param array  $context Additional context.
     */
    public static function log_security_event( $event, $context = array() ) {
        $security_context = array_merge( $context, array(
            'category' => 'security',
            'ip_address' => RWP_Creator_Suite_Network_Utils::get_client_ip(),
            'referer' => isset( $_SERVER['HTTP_REFERER'] ) 
                ? sanitize_text_field( $_SERVER['HTTP_REFERER'] ) 
                : 'direct',
        ) );

        self::log( $event, self::LOG_LEVEL_WARNING, $security_context );
    }

    /**
     * Log performance metrics.
     *
     * @param string $operation Operation being measured.
     * @param float  $execution_time Execution time in seconds.
     * @param array  $context Additional context.
     */
    public static function log_performance( $operation, $execution_time, $context = array() ) {
        $performance_context = array_merge( $context, array(
            'category' => 'performance',
            'execution_time' => $execution_time,
            'memory_usage' => memory_get_usage( true ),
            'peak_memory' => memory_get_peak_usage( true ),
        ) );

        $level = $execution_time > 5.0 ? self::LOG_LEVEL_WARNING : self::LOG_LEVEL_INFO;
        
        self::log( 
            sprintf( 'Performance: %s completed in %.4f seconds', $operation, $execution_time ),
            $level,
            $performance_context
        );
    }

    /**
     * Handle critical error notifications.
     *
     * @param array $log_entry Log entry data.
     */
    private static function notify_critical_error( $log_entry ) {
        // Set a transient to prevent spam notifications
        $notification_key = 'rwp_creator_suite_critical_notification_' . md5( $log_entry['message'] );
        
        if ( get_transient( $notification_key ) ) {
            return; // Already notified recently
        }

        // Set transient for 1 hour to prevent spam
        set_transient( $notification_key, true, HOUR_IN_SECONDS );

        // Hook for external notification systems
        do_action( 'rwp_creator_suite_critical_error', $log_entry );
    }

    /**
     * Get recent log entries (if custom logging is implemented).
     *
     * @param int $limit Number of entries to retrieve.
     * @return array Log entries.
     */
    public static function get_recent_logs( $limit = 50 ) {
        // This would require custom log storage implementation
        // For now, return empty array as we're using error_log()
        return array();
    }

    /**
     * Clear old log entries (if custom logging is implemented).
     *
     * @param int $days Days to keep logs.
     */
    public static function cleanup_old_logs( $days = 30 ) {
        // This would require custom log storage implementation
        // For now, this is a placeholder
    }
}