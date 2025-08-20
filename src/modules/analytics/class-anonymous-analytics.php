<?php
/**
 * Anonymous Analytics System
 * 
 * Privacy-first data collection system for tracking user behavior patterns
 * without collecting personally identifiable information.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.6.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Anonymous_Analytics {

    /**
     * Single instance of the class.
     *
     * @var RWP_Creator_Suite_Anonymous_Analytics
     */
    private static $instance = null;

    /**
     * Analytics table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Anonymous session ID for current session.
     *
     * @var string
     */
    private $session_hash;

    /**
     * Whether user has consented to analytics.
     *
     * @var bool
     */
    private $user_consented;

    /**
     * Event types for tracking.
     */
    const EVENT_HASHTAG_ADDED = 'hashtag_added';
    const EVENT_PLATFORM_SELECTED = 'platform_selected';
    const EVENT_TONE_SELECTED = 'tone_selected';
    const EVENT_TEMPLATE_USED = 'template_used';
    const EVENT_CONTENT_GENERATED = 'content_generated';
    const EVENT_FEATURE_USED = 'feature_used';

    /**
     * Get single instance of the class.
     *
     * @return RWP_Creator_Suite_Anonymous_Analytics
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rwp_anonymous_analytics';
        $this->init_session();
        $this->check_user_consent();
    }

    /**
     * Initialize the analytics system.
     */
    public function init() {
        add_action( 'init', array( $this, 'maybe_create_table' ) );
        add_action( 'rwp_creator_suite_daily_cleanup', array( $this, 'cleanup_old_data' ) );
        
        // Schedule daily cleanup if not already scheduled
        if ( ! wp_next_scheduled( 'rwp_creator_suite_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'rwp_creator_suite_daily_cleanup' );
        }
    }

    /**
     * Initialize anonymous session.
     */
    private function init_session() {
        // Check if session already exists in cookie
        if ( isset( $_COOKIE['rwp_analytics_session'] ) ) {
            $session = sanitize_text_field( $_COOKIE['rwp_analytics_session'] );
            if ( $this->validate_session_format( $session ) ) {
                $this->session_hash = $session;
                return;
            }
        }

        // Create new anonymous session
        $this->session_hash = $this->create_anonymous_session();
        
        // Set session cookie (24 hour expiry)
        if ( ! headers_sent() ) {
            setcookie( 
                'rwp_analytics_session', 
                $this->session_hash, 
                time() + DAY_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }
    }

    /**
     * Validate session format.
     *
     * @param string $session Session string to validate.
     * @return bool
     */
    private function validate_session_format( $session ) {
        return preg_match( '/^[a-f0-9]{32}$/', $session );
    }

    /**
     * Create anonymous session ID.
     *
     * @return string
     */
    private function create_anonymous_session() {
        $session_data = array(
            'timestamp' => current_time( 'timestamp' ),
            'user_agent_hash' => hash( 'sha256', $_SERVER['HTTP_USER_AGENT'] ?? '' ),
            'ip_hash' => hash( 'sha256', $this->get_client_ip() . wp_salt( 'analytics' ) ),
            'random' => wp_generate_password( 16, false )
        );
        
        return substr( hash( 'sha256', serialize( $session_data ) ), 0, 32 );
    }

    /**
     * Get client IP address safely.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
        
        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                $ip = $_SERVER[ $key ];
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Check if user has consented to analytics.
     */
    private function check_user_consent() {
        // Default to false - user must explicitly opt in
        $this->user_consented = false;
        
        // Check for consent cookie
        if ( isset( $_COOKIE['rwp_analytics_consent'] ) && $_COOKIE['rwp_analytics_consent'] === 'yes' ) {
            $this->user_consented = true;
        }
        
        // Check for logged-in user preference
        if ( is_user_logged_in() ) {
            $consent_key = RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key();
            $user_consent = get_user_meta( get_current_user_id(), $consent_key, true );
            if ( $user_consent == 1 || $user_consent === 'yes' ) {
                $this->user_consented = true;
            }
        }
    }

    /**
     * Set user consent for analytics.
     *
     * @param bool $consent Whether user consents.
     */
    public function set_user_consent( $consent ) {
        $this->user_consented = $consent;
        
        // Set consent cookie
        if ( ! headers_sent() ) {
            $value = $consent ? 'yes' : 'no';
            setcookie( 
                'rwp_analytics_consent', 
                $value, 
                time() + YEAR_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }
        
        // Save preference for logged-in users
        if ( is_user_logged_in() ) {
            $consent_key = RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key();
            update_user_meta( get_current_user_id(), $consent_key, $consent ? 1 : 0 );
        }
    }

    /**
     * Check if user has consented to analytics.
     *
     * @return bool
     */
    public function user_has_consented() {
        return $this->user_consented;
    }

    /**
     * Track user-added hashtag.
     *
     * @param string $hashtag The hashtag (without #).
     * @param array  $context Context information.
     */
    public function track_user_hashtag( $hashtag, $context = array() ) {
        if ( ! $this->user_has_consented() ) {
            return;
        }

        $data = array(
            'hashtag_hash' => $this->anonymize_hashtag( $hashtag ),
            'platform' => sanitize_text_field( $context['platform'] ?? '' ),
            'tone' => sanitize_text_field( $context['tone'] ?? '' ),
            'content_type' => sanitize_text_field( $context['content_type'] ?? '' ),
            'source' => sanitize_text_field( $context['source'] ?? 'manual' ) // manual, template, etc.
        );

        $this->store_event( self::EVENT_HASHTAG_ADDED, $data );
    }

    /**
     * Track platform selection.
     *
     * @param string $platform Platform name.
     * @param array  $context Context information.
     */
    public function track_platform_selection( $platform, $context = array() ) {
        if ( ! $this->user_has_consented() ) {
            return;
        }

        $data = array(
            'platform' => sanitize_text_field( $platform ),
            'previous_platform' => sanitize_text_field( $context['previous_platform'] ?? '' ),
            'feature' => sanitize_text_field( $context['feature'] ?? '' ), // caption_writer, repurposer, etc.
        );

        $this->store_event( self::EVENT_PLATFORM_SELECTED, $data );
    }

    /**
     * Track tone selection.
     *
     * @param string $tone Tone selected.
     * @param array  $context Context information.
     */
    public function track_tone_selection( $tone, $context = array() ) {
        if ( ! $this->user_has_consented() ) {
            return;
        }

        $data = array(
            'tone' => sanitize_text_field( $tone ),
            'platform' => sanitize_text_field( $context['platform'] ?? '' ),
            'feature' => sanitize_text_field( $context['feature'] ?? '' ),
        );

        $this->store_event( self::EVENT_TONE_SELECTED, $data );
    }

    /**
     * Track template usage.
     *
     * @param string $template_id Template identifier.
     * @param array  $context Context information.
     */
    public function track_template_usage( $template_id, $context = array() ) {
        if ( ! $this->user_has_consented() ) {
            return;
        }

        $template_hash = hash( 'sha256', $template_id . wp_salt( 'analytics' ) );

        $data = array(
            'template_hash' => $template_hash,
            'platform' => sanitize_text_field( $context['platform'] ?? '' ),
            'tone' => sanitize_text_field( $context['tone'] ?? '' ),
            'completion_status' => sanitize_text_field( $context['completion_status'] ?? 'completed' ),
            'customizations_made' => (int) ( $context['customizations_made'] ?? 0 ),
        );

        $this->store_event( self::EVENT_TEMPLATE_USED, $data );
    }

    /**
     * Track content generation.
     *
     * @param array $context Context information.
     */
    public function track_content_generation( $context = array() ) {
        if ( ! $this->user_has_consented() ) {
            return;
        }

        $data = array(
            'feature' => sanitize_text_field( $context['feature'] ?? '' ),
            'platform' => sanitize_text_field( $context['platform'] ?? '' ),
            'tone' => sanitize_text_field( $context['tone'] ?? '' ),
            'success' => (bool) ( $context['success'] ?? true ),
            'processing_time_ms' => (int) ( $context['processing_time_ms'] ?? 0 ),
            'content_length' => (int) ( $context['content_length'] ?? 0 ),
        );

        $this->store_event( self::EVENT_CONTENT_GENERATED, $data );
    }

    /**
     * Track general feature usage.
     *
     * @param string $feature_name Feature name.
     * @param array  $context Context information.
     */
    public function track_feature_usage( $feature_name, $context = array() ) {
        if ( ! $this->user_has_consented() ) {
            return;
        }

        $data = array(
            'feature' => sanitize_text_field( $feature_name ),
            'action' => sanitize_text_field( $context['action'] ?? 'used' ),
            'platform' => sanitize_text_field( $context['platform'] ?? '' ),
        );

        // Add any additional context data
        if ( isset( $context['additional_data'] ) && is_array( $context['additional_data'] ) ) {
            $data = array_merge( $data, $context['additional_data'] );
        }

        $this->store_event( self::EVENT_FEATURE_USED, $data );
    }

    /**
     * Anonymize hashtag while preserving uniqueness.
     *
     * @param string $hashtag Hashtag to anonymize.
     * @return string
     */
    private function anonymize_hashtag( $hashtag ) {
        $cleaned = strtolower( trim( $hashtag, '# ' ) );
        return hash( 'sha256', $cleaned . wp_salt( 'analytics' ) );
    }

    /**
     * Store analytics event.
     *
     * @param string $event_type Event type.
     * @param array  $event_data Event data.
     */
    private function store_event( $event_type, $event_data ) {
        global $wpdb;

        $data = array(
            'event_type' => $event_type,
            'event_data' => wp_json_encode( $event_data ),
            'anonymous_session_hash' => $this->session_hash,
            'timestamp' => current_time( 'mysql' ),
            'platform' => sanitize_text_field( $event_data['platform'] ?? '' ),
        );

        $result = $wpdb->insert( $this->table_name, $data );

        if ( false === $result ) {
            RWP_Creator_Suite_Error_Logger::log(
                'Failed to store analytics event',
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_ERROR,
                array(
                    'event_type' => $event_type,
                    'db_error' => $wpdb->last_error
                )
            );
        }
    }

    /**
     * Create analytics table if it doesn't exist.
     */
    public function maybe_create_table() {
        global $wpdb;

        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table_name
        ) );

        if ( $table_exists !== $this->table_name ) {
            $this->create_table();
        }
    }

    /**
     * Create the analytics table.
     */
    private function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            event_data LONGTEXT NOT NULL,
            anonymous_session_hash VARCHAR(64) NOT NULL,
            timestamp DATETIME NOT NULL,
            platform VARCHAR(20) DEFAULT '',
            PRIMARY KEY (id),
            INDEX idx_event_type (event_type),
            INDEX idx_timestamp (timestamp),
            INDEX idx_platform (platform),
            INDEX idx_session_hash (anonymous_session_hash)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Log table creation
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->table_name ) ) === $this->table_name ) {
            RWP_Creator_Suite_Error_Logger::log(
                'Analytics table created successfully',
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO
            );
        } else {
            RWP_Creator_Suite_Error_Logger::log(
                'Failed to create analytics table',
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_ERROR,
                array( 'db_error' => $wpdb->last_error )
            );
        }
    }

    /**
     * Clean up old analytics data.
     */
    public function cleanup_old_data() {
        global $wpdb;

        // Delete data older than 12 months
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-12 months' ) );

        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE timestamp < %s",
            $cutoff_date
        ) );

        if ( false !== $deleted && $deleted > 0 ) {
            RWP_Creator_Suite_Error_Logger::log(
                'Cleaned up old analytics data',
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
                array( 'records_deleted' => $deleted )
            );
        }

        // Also clean up session cookies older than 24 hours
        $this->cleanup_old_sessions();
    }

    /**
     * Clean up old session data.
     */
    private function cleanup_old_sessions() {
        global $wpdb;

        // Delete session data older than 24 hours
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE timestamp < %s AND event_type = 'session_created'",
            $cutoff_date
        ) );
    }

    /**
     * Get analytics summary for admin dashboard.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_analytics_summary( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'start_date' => date( 'Y-m-d', strtotime( '-30 days' ) ),
            'end_date' => date( 'Y-m-d' ),
            'event_types' => array(),
        );

        $args = wp_parse_args( $args, $defaults );

        $where_conditions = array();
        $where_values = array();

        // Date range
        if ( $args['start_date'] ) {
            $where_conditions[] = 'timestamp >= %s';
            $where_values[] = $args['start_date'] . ' 00:00:00';
        }

        if ( $args['end_date'] ) {
            $where_conditions[] = 'timestamp <= %s';
            $where_values[] = $args['end_date'] . ' 23:59:59';
        }

        // Event types
        if ( ! empty( $args['event_types'] ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $args['event_types'] ), '%s' ) );
            $where_conditions[] = "event_type IN ($placeholders)";
            $where_values = array_merge( $where_values, $args['event_types'] );
        }

        $where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

        $query = "SELECT 
                    event_type,
                    COUNT(*) as event_count,
                    COUNT(DISTINCT anonymous_session_hash) as unique_sessions,
                    DATE(timestamp) as event_date
                  FROM {$this->table_name} 
                  {$where_clause}
                  GROUP BY event_type, DATE(timestamp)
                  ORDER BY event_date DESC, event_count DESC";

        if ( ! empty( $where_values ) ) {
            $query = $wpdb->prepare( $query, $where_values );
        }

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Get popular hashtags (anonymized).
     *
     * @param int $limit Number of results to return.
     * @return array
     */
    public function get_popular_hashtags( $limit = 20 ) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.hashtag_hash') as hashtag_hash,
                JSON_EXTRACT(event_data, '$.platform') as platform,
                COUNT(*) as usage_count
             FROM {$this->table_name} 
             WHERE event_type = %s 
             AND timestamp >= %s
             GROUP BY hashtag_hash, platform
             ORDER BY usage_count DESC
             LIMIT %d",
            self::EVENT_HASHTAG_ADDED,
            date( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
            $limit
        );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Get platform usage statistics.
     *
     * @return array
     */
    public function get_platform_stats() {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT 
                platform,
                COUNT(*) as total_usage,
                COUNT(DISTINCT anonymous_session_hash) as unique_users
             FROM {$this->table_name} 
             WHERE platform != '' 
             AND timestamp >= %s
             GROUP BY platform
             ORDER BY total_usage DESC",
            date( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
        );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Get the current session hash.
     *
     * @return string
     */
    public function get_session_hash() {
        return $this->session_hash;
    }

    /**
     * Delete all analytics data (for user data requests).
     *
     * @param string $session_hash Optional specific session to delete.
     */
    public function delete_analytics_data( $session_hash = null ) {
        global $wpdb;

        if ( $session_hash ) {
            $wpdb->delete( $this->table_name, array( 'anonymous_session_hash' => $session_hash ) );
        } else {
            // Delete all data for current session
            $wpdb->delete( $this->table_name, array( 'anonymous_session_hash' => $this->session_hash ) );
        }
    }
}