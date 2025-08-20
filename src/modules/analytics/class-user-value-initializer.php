<?php
/**
 * User Value Initializer
 * 
 * Initializes all user value delivery components including
 * trend analysis, benchmarking, recommendations, and notifications.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_User_Value_Initializer {

    /**
     * Single instance of the class.
     *
     * @var RWP_Creator_Suite_User_Value_Initializer
     */
    private static $instance = null;

    /**
     * User value components.
     */
    private $user_value_api;
    private $trend_analyzer;
    private $performance_benchmarker;
    private $value_notifications;
    private $insights_admin_page;

    /**
     * Get single instance of the class.
     *
     * @return RWP_Creator_Suite_User_Value_Initializer
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
        // Constructor intentionally empty - use init() method
    }

    /**
     * Initialize user value components.
     */
    public function init() {
        // Check if analytics consent system is active
        if ( ! class_exists( 'RWP_Creator_Suite_Consent_Manager' ) ) {
            return; // Cannot initialize without consent management components
        }

        $this->load_dependencies();
        $this->init_components();
        
        // Hook into plugin lifecycle
        add_action( 'wp_loaded', array( $this, 'late_init' ) );
    }

    /**
     * Load user value dependencies.
     */
    private function load_dependencies() {
        $analytics_dir = RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/analytics/';

        // Core user value classes
        require_once $analytics_dir . 'class-user-value-api.php';
        require_once $analytics_dir . 'class-trend-analyzer.php';
        require_once $analytics_dir . 'class-performance-benchmarker.php';
        require_once $analytics_dir . 'class-value-notifications.php';
        require_once $analytics_dir . 'class-insights-admin-page.php';
    }

    /**
     * Initialize user value components.
     */
    private function init_components() {
        // Core value delivery system
        $this->user_value_api = new RWP_Creator_Suite_User_Value_API();
        $this->trend_analyzer = new RWP_Creator_Suite_Trend_Analyzer();
        $this->performance_benchmarker = new RWP_Creator_Suite_Performance_Benchmarker();
        
        // Notification system
        $this->value_notifications = RWP_Creator_Suite_Value_Notifications::get_instance();
        
        // Admin interface
        $this->insights_admin_page = new RWP_Creator_Suite_Insights_Admin_Page();

        // Initialize all components
        $this->user_value_api->init();
        $this->value_notifications->init();
        $this->insights_admin_page->init();
    }

    /**
     * Late initialization for components that need other plugins loaded.
     */
    public function late_init() {
        // Schedule automated tasks
        $this->schedule_value_delivery_tasks();
        
        // Register additional hooks
        $this->register_value_delivery_hooks();
        
        // Check for updates and migrations
        $this->check_user_value_updates();
    }

    /**
     * Schedule automated value delivery tasks.
     */
    private function schedule_value_delivery_tasks() {
        // Weekly trending analysis
        if ( ! wp_next_scheduled( 'rwp_weekly_trend_analysis' ) ) {
            wp_schedule_event( 
                strtotime( 'next sunday 06:00' ), 
                'weekly', 
                'rwp_weekly_trend_analysis' 
            );
        }

        // Monthly benchmark calculation
        if ( ! wp_next_scheduled( 'rwp_monthly_benchmark_calculation' ) ) {
            wp_schedule_event( 
                strtotime( 'first day of next month 05:00' ), 
                'monthly', 
                'rwp_monthly_benchmark_calculation' 
            );
        }

        // Daily opportunity scanning
        if ( ! wp_next_scheduled( 'rwp_daily_opportunity_scan' ) ) {
            wp_schedule_event( 
                strtotime( 'tomorrow 08:00' ), 
                'daily', 
                'rwp_daily_opportunity_scan' 
            );
        }

        // Register cron handlers
        add_action( 'rwp_weekly_trend_analysis', array( $this, 'execute_weekly_trend_analysis' ) );
        add_action( 'rwp_monthly_benchmark_calculation', array( $this, 'execute_monthly_benchmark_calculation' ) );
        add_action( 'rwp_daily_opportunity_scan', array( $this, 'execute_daily_opportunity_scan' ) );
    }

    /**
     * Register value delivery hooks.
     */
    private function register_value_delivery_hooks() {
        // Track user achievements
        add_action( 'rwp_content_generated', array( $this, 'check_content_achievements' ), 10, 2 );
        add_action( 'rwp_hashtag_added', array( $this, 'check_hashtag_achievements' ), 10, 2 );
        add_action( 'rwp_template_used', array( $this, 'check_template_achievements' ), 10, 2 );

        // Trigger notifications for significant events
        add_action( 'rwp_achievement_unlocked', array( $this, 'send_achievement_notification' ), 10, 2 );
        add_action( 'rwp_high_impact_opportunity', array( $this, 'send_opportunity_notification' ), 10, 2 );

        // User data cleanup for GDPR compliance
        add_action( 'delete_user', array( $this, 'cleanup_user_value_data' ) );
        add_action( 'wpmu_delete_user', array( $this, 'cleanup_user_value_data' ) );
    }

    /**
     * Check for user value updates and run migrations.
     */
    private function check_user_value_updates() {
        $current_version = get_option( 'rwp_user_value_version', '0' );
        $plugin_version = RWP_CREATOR_SUITE_VERSION;

        if ( version_compare( $current_version, $plugin_version, '<' ) ) {
            $this->run_user_value_migrations( $current_version, $plugin_version );
            update_option( 'rwp_user_value_version', $plugin_version );
        }
    }

    /**
     * Run user value data migrations.
     *
     * @param string $from_version Previous version.
     * @param string $to_version New version.
     */
    private function run_user_value_migrations( $from_version, $to_version ) {
        global $wpdb;

        // Create any new database tables or indexes needed for user value system
        $this->create_user_value_tables();
        
        // Migrate existing user preferences if needed
        if ( version_compare( $from_version, '1.7.0', '<' ) ) {
            $this->migrate_notification_preferences();
        }

        // Log the migration
        RWP_Creator_Suite_Error_Logger::log(
            'User value migration completed',
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            array(
                'from_version' => $from_version,
                'to_version' => $to_version,
            )
        );
    }

    /**
     * Create user value database tables.
     */
    private function create_user_value_tables() {
        global $wpdb;

        // User achievements table
        $achievements_table = $wpdb->prefix . 'rwp_user_achievements';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$achievements_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            achievement_key VARCHAR(50) NOT NULL,
            achievement_level INT(11) NOT NULL DEFAULT 1,
            progress INT(11) NOT NULL DEFAULT 0,
            unlocked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_achievement (user_id, achievement_key),
            INDEX idx_user_id (user_id),
            INDEX idx_achievement_key (achievement_key),
            INDEX idx_unlocked_at (unlocked_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // User monthly reports table  
        $reports_table = $wpdb->prefix . 'rwp_monthly_reports';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$reports_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            report_month INT(2) NOT NULL,
            report_year INT(4) NOT NULL,
            report_data LONGTEXT NOT NULL,
            generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_month_year (user_id, report_month, report_year),
            INDEX idx_user_id (user_id),
            INDEX idx_report_date (report_year, report_month)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Migrate notification preferences from old format.
     */
    private function migrate_notification_preferences() {
        global $wpdb;

        // Get users who might have old notification preferences
        $users = get_users( array(
            'meta_query' => array(
                array(
                    'key' => 'rwp_analytics_consent',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'fields' => 'ID',
        ) );

        foreach ( $users as $user_id ) {
            // Check if they already have new preferences
            $existing_prefs = get_user_meta( $user_id, 'rwp_notification_preferences', true );
            
            if ( ! $existing_prefs ) {
                // Set up default preferences for existing consented users
                $default_preferences = array(
                    'weekly_trends' => true,
                    'monthly_reports' => true,
                    'achievement_notifications' => true,
                    'opportunity_alerts' => true,
                    'breaking_trends' => false,
                    'email_format' => 'html',
                    'notification_time' => '10:00',
                );
                
                update_user_meta( $user_id, 'rwp_notification_preferences', $default_preferences );
            }
        }
    }

    /**
     * Execute weekly trend analysis.
     */
    public function execute_weekly_trend_analysis() {
        // Calculate trending hashtags, topics, and platform performance
        try {
            $this->trend_analyzer->calculate_weekly_trends();
            
            // Clear related caches
            wp_cache_delete( 'rwp_trending_hashtags', 'rwp_analytics' );
            wp_cache_delete( 'rwp_community_benchmarks', 'rwp_analytics' );
            
            RWP_Creator_Suite_Error_Logger::log(
                'Weekly trend analysis completed successfully',
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO
            );
            
        } catch ( Exception $e ) {
            RWP_Creator_Suite_Error_Logger::log(
                'Weekly trend analysis failed: ' . $e->getMessage(),
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_ERROR
            );
        }
    }

    /**
     * Execute monthly benchmark calculation.
     */
    public function execute_monthly_benchmark_calculation() {
        try {
            // Recalculate community averages and benchmarks
            $this->performance_benchmarker->calculate_monthly_benchmarks();
            
            // Clear benchmark caches
            wp_cache_delete( 'rwp_community_averages', 'rwp_analytics' );
            wp_cache_delete( 'rwp_benchmark_percentiles', 'rwp_analytics' );
            
            RWP_Creator_Suite_Error_Logger::log(
                'Monthly benchmark calculation completed successfully',
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO
            );
            
        } catch ( Exception $e ) {
            RWP_Creator_Suite_Error_Logger::log(
                'Monthly benchmark calculation failed: ' . $e->getMessage(),
                RWP_Creator_Suite_Error_Logger::LOG_LEVEL_ERROR
            );
        }
    }

    /**
     * Execute daily opportunity scanning.
     */
    public function execute_daily_opportunity_scan() {
        // This is handled by the value notifications system
        if ( $this->value_notifications ) {
            $this->value_notifications->scan_and_notify_opportunities();
        }
    }

    /**
     * Check for content-related achievements.
     *
     * @param int   $user_id User ID.
     * @param array $context Content context.
     */
    public function check_content_achievements( $user_id, $context ) {
        if ( ! $user_id || ! is_user_logged_in() ) {
            return;
        }

        $this->check_and_update_achievement( $user_id, 'content_creator', 1 );
        
        // Check for volume-based achievements
        $content_count = $this->get_user_content_count( $user_id );
        
        if ( $content_count >= 100 ) {
            $this->check_and_update_achievement( $user_id, 'content_machine', 3 );
        } elseif ( $content_count >= 50 ) {
            $this->check_and_update_achievement( $user_id, 'content_machine', 2 );
        } elseif ( $content_count >= 10 ) {
            $this->check_and_update_achievement( $user_id, 'content_machine', 1 );
        }
    }

    /**
     * Check for hashtag-related achievements.
     *
     * @param int   $user_id User ID.
     * @param array $context Hashtag context.
     */
    public function check_hashtag_achievements( $user_id, $context ) {
        if ( ! $user_id || ! is_user_logged_in() ) {
            return;
        }

        $hashtag_count = $this->get_user_hashtag_count( $user_id );
        
        if ( $hashtag_count >= 100 ) {
            $this->check_and_update_achievement( $user_id, 'hashtag_master', 3 );
        } elseif ( $hashtag_count >= 50 ) {
            $this->check_and_update_achievement( $user_id, 'hashtag_master', 2 );
        } elseif ( $hashtag_count >= 10 ) {
            $this->check_and_update_achievement( $user_id, 'hashtag_master', 1 );
        }

        // Check for trend adoption
        if ( $this->is_trending_hashtag( $context['hashtag'] ?? '' ) ) {
            $this->check_and_update_achievement( $user_id, 'trend_spotter', 1 );
        }
    }

    /**
     * Check for template-related achievements.
     *
     * @param int   $user_id User ID.
     * @param array $context Template context.
     */
    public function check_template_achievements( $user_id, $context ) {
        if ( ! $user_id || ! is_user_logged_in() ) {
            return;
        }

        $template_count = $this->get_user_template_count( $user_id );
        
        if ( $template_count >= 50 ) {
            $this->check_and_update_achievement( $user_id, 'template_explorer', 2 );
        } elseif ( $template_count >= 10 ) {
            $this->check_and_update_achievement( $user_id, 'template_explorer', 1 );
        }
    }

    /**
     * Check and update user achievement.
     *
     * @param int    $user_id User ID.
     * @param string $achievement_key Achievement key.
     * @param int    $target_level Target achievement level.
     */
    private function check_and_update_achievement( $user_id, $achievement_key, $target_level ) {
        global $wpdb;

        $table = $wpdb->prefix . 'rwp_user_achievements';
        
        $current = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND achievement_key = %s",
            $user_id,
            $achievement_key
        ) );

        if ( ! $current ) {
            // Create new achievement record
            $wpdb->insert( $table, array(
                'user_id' => $user_id,
                'achievement_key' => $achievement_key,
                'achievement_level' => $target_level,
                'progress' => $this->calculate_achievement_progress( $user_id, $achievement_key ),
                'unlocked_at' => current_time( 'mysql' ),
            ) );

            // Trigger achievement notification
            do_action( 'rwp_achievement_unlocked', $user_id, $achievement_key );
            
        } elseif ( $current->achievement_level < $target_level ) {
            // Update existing achievement
            $wpdb->update( $table, 
                array(
                    'achievement_level' => $target_level,
                    'progress' => $this->calculate_achievement_progress( $user_id, $achievement_key ),
                    'unlocked_at' => current_time( 'mysql' ),
                ),
                array(
                    'user_id' => $user_id,
                    'achievement_key' => $achievement_key,
                )
            );

            // Trigger achievement notification for level up
            do_action( 'rwp_achievement_unlocked', $user_id, $achievement_key );
        }
    }

    /**
     * Send achievement notification.
     *
     * @param int    $user_id User ID.
     * @param string $achievement_key Achievement key.
     */
    public function send_achievement_notification( $user_id, $achievement_key ) {
        if ( $this->value_notifications ) {
            $achievement = $this->get_achievement_data( $achievement_key );
            $this->value_notifications->send_achievement_notification( $user_id, $achievement );
        }
    }

    /**
     * Send opportunity notification.
     *
     * @param int   $user_id User ID.
     * @param array $opportunity Opportunity data.
     */
    public function send_opportunity_notification( $user_id, $opportunity ) {
        if ( $this->value_notifications ) {
            $this->value_notifications->send_opportunity_alert( $user_id, $opportunity );
        }
    }

    /**
     * Clean up user value data.
     *
     * @param int $user_id User ID.
     */
    public function cleanup_user_value_data( $user_id ) {
        global $wpdb;

        // Clean up achievements
        $achievements_table = $wpdb->prefix . 'rwp_user_achievements';
        $wpdb->delete( $achievements_table, array( 'user_id' => $user_id ) );

        // Clean up monthly reports
        $reports_table = $wpdb->prefix . 'rwp_monthly_reports';
        $wpdb->delete( $reports_table, array( 'user_id' => $user_id ) );

        // Clean up notification data
        if ( $this->value_notifications ) {
            $this->value_notifications->clear_user_notification_data( $user_id );
        }

        // Clear user caches
        wp_cache_delete( "rwp_user_profile_{$user_id}", 'rwp_analytics' );
        wp_cache_delete( "rwp_user_achievements_{$user_id}", 'rwp_analytics' );
    }

    /**
     * Utility methods for achievement calculation.
     */

    private function get_user_content_count( $user_id ) {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'rwp_anonymous_analytics';
        $analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
        $session_hash = $analytics->get_session_hash();

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$analytics_table} 
             WHERE anonymous_session_hash = %s 
             AND event_type = %s",
            $session_hash,
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_CONTENT_GENERATED
        ) );
    }

    private function get_user_hashtag_count( $user_id ) {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'rwp_anonymous_analytics';
        $analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
        $session_hash = $analytics->get_session_hash();

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT JSON_EXTRACT(event_data, '$.hashtag_hash')) 
             FROM {$analytics_table} 
             WHERE anonymous_session_hash = %s 
             AND event_type = %s",
            $session_hash,
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_HASHTAG_ADDED
        ) );
    }

    private function get_user_template_count( $user_id ) {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'rwp_anonymous_analytics';
        $analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
        $session_hash = $analytics->get_session_hash();

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT JSON_EXTRACT(event_data, '$.template_hash')) 
             FROM {$analytics_table} 
             WHERE anonymous_session_hash = %s 
             AND event_type = %s",
            $session_hash,
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_TEMPLATE_USED
        ) );
    }

    private function is_trending_hashtag( $hashtag ) {
        $trending_hashtags = get_transient( 'rwp_trending_hashtags' );
        
        if ( ! $trending_hashtags ) {
            return false;
        }

        $hashtag_hash = hash( 'sha256', strtolower( trim( $hashtag, '# ' ) ) . wp_salt( 'analytics' ) );
        return in_array( $hashtag_hash, array_keys( $trending_hashtags ) );
    }

    private function calculate_achievement_progress( $user_id, $achievement_key ) {
        switch ( $achievement_key ) {
            case 'content_creator':
            case 'content_machine':
                return $this->get_user_content_count( $user_id );
            case 'hashtag_master':
                return $this->get_user_hashtag_count( $user_id );
            case 'template_explorer':
                return $this->get_user_template_count( $user_id );
            default:
                return 0;
        }
    }

    private function get_achievement_data( $achievement_key ) {
        $achievements = array(
            'content_creator' => array(
                'name' => __( 'Content Creator', 'rwp-creator-suite' ),
                'description' => __( 'Created your first piece of content', 'rwp-creator-suite' ),
                'icon' => '✨',
            ),
            'content_machine' => array(
                'name' => __( 'Content Machine', 'rwp-creator-suite' ),
                'description' => __( 'Consistently creating amazing content', 'rwp-creator-suite' ),
                'icon' => '🚀',
            ),
            'hashtag_master' => array(
                'name' => __( 'Hashtag Master', 'rwp-creator-suite' ),
                'description' => __( 'Expert at using effective hashtags', 'rwp-creator-suite' ),
                'icon' => '#️⃣',
            ),
            'template_explorer' => array(
                'name' => __( 'Template Explorer', 'rwp-creator-suite' ),
                'description' => __( 'Discovered many content templates', 'rwp-creator-suite' ),
                'icon' => '🗺️',
            ),
            'trend_spotter' => array(
                'name' => __( 'Trend Spotter', 'rwp-creator-suite' ),
                'description' => __( 'Early adopter of trending hashtags', 'rwp-creator-suite' ),
                'icon' => '🔥',
            ),
        );

        return $achievements[ $achievement_key ] ?? array(
            'name' => __( 'Unknown Achievement', 'rwp-creator-suite' ),
            'description' => __( 'Achievement data not found', 'rwp-creator-suite' ),
            'icon' => '🏆',
        );
    }
}