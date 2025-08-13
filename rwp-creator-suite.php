<?php
/**
 * Plugin Name: RWP Creator Suite
 * Description: A suite of tools for content creators including streamlined user authentication.
 * Version: 1.5.0
 * Author: Jeremy Hixon
 * Author URI: https://jeremyhixon.com
 * Text Domain: rwp-creator-suite
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * Network: false
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants
define( 'RWP_CREATOR_SUITE_VERSION', '1.5.0' );
define( 'RWP_CREATOR_SUITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RWP_CREATOR_SUITE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RWP_CREATOR_SUITE_PLUGIN_FILE', __FILE__ );

/**
 * Main plugin class.
 */
class RWP_Creator_Suite {

    /**
     * Single instance of the plugin.
     *
     * @var RWP_Creator_Suite
     */
    private static $instance = null;

    /**
     * Plugin components.
     */
    private $wp_login_integration;
    private $subscriber_restrictions;
    private $redirect_handler;
    private $registration_api;
    private $block_manager;
    private $caption_api;
    private $caption_admin;

    /**
     * Get single instance of the plugin.
     *
     * @return RWP_Creator_Suite
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
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Initialize the plugin.
     */
    public function init() {
        // Load required files
        $this->load_dependencies();

        // Initialize components
        $this->wp_login_integration = new RWP_Creator_Suite_WP_Login_Integration();
        $this->subscriber_restrictions = new RWP_Creator_Suite_Subscriber_Restrictions();
        $this->redirect_handler = new RWP_Creator_Suite_Redirect_Handler();
        $this->registration_api = new RWP_Creator_Suite_Registration_API();
        $this->block_manager = new RWP_Creator_Suite_Block_Manager();
        $this->caption_api = new RWP_Creator_Suite_Caption_API();
        $this->caption_admin = new RWP_Creator_Suite_Caption_Admin_Settings();

        // Initialize all components
        $this->wp_login_integration->init();
        $this->subscriber_restrictions->init();
        $this->redirect_handler->init();
        $this->registration_api->init();
        $this->block_manager->init();
        $this->caption_api->init();
        $this->caption_admin->init();

        // Additional hooks
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'maybe_redirect_wp_login' ) );
        
        // User data cleanup hooks
        add_action( 'delete_user', array( $this, 'cleanup_user_data' ) );
        add_action( 'wpmu_delete_user', array( $this, 'cleanup_user_data' ) );
    }

    /**
     * Load plugin dependencies.
     */
    private function load_dependencies() {
        // Common Module
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/common/class-error-logger.php';

        // User Registration Module
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/user-registration/class-username-generator.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/user-registration/class-rate-limiter.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/user-registration/class-user-registration.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/user-registration/class-registration-api.php';

        // User Authentication Module
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/user-authentication/class-redirect-handler.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/user-authentication/class-auto-login.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/user-authentication/class-subscriber-restrictions.php';

        // Frontend Module
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/frontend/class-wp-login-integration.php';

        // Blocks Module
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/blocks/class-block-manager.php';

        // Instagram Analyzer Module
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/instagram-analyzer/class-instagram-analyzer-api.php';
        
        // Caption Writer Module
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/caption-writer/class-key-manager.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/caption-writer/class-caption-cache.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/caption-writer/class-ai-caption-service.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/caption-writer/class-template-manager.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/caption-writer/class-caption-api.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/caption-writer/class-admin-settings.php';

        // Shortcodes Module
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/class-shortcodes.php';
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'rwp-creator-suite', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Maybe redirect to custom registration page.
     */
    public function maybe_redirect_wp_login() {
        // This can be used to redirect to custom pages if needed
        // For now, we're using the built-in WordPress forms
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Enable user registration if not already enabled
        if ( ! get_option( 'users_can_register' ) ) {
            update_option( 'users_can_register', 1 );
        }

        // Set default user role to subscriber
        if ( get_option( 'default_role' ) !== 'subscriber' ) {
            update_option( 'default_role', 'subscriber' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        // Create plugin activation log
        add_option( 'rwp_creator_suite_activated', current_time( 'timestamp' ) );
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Clean up transients
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rwp_creator_suite_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rwp_creator_suite_%'" );

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clean up user data when user is deleted.
     *
     * @param int $user_id The ID of the user being deleted.
     */
    public function cleanup_user_data( $user_id ) {
        if ( ! $user_id || ! is_numeric( $user_id ) ) {
            return;
        }

        // Log the cleanup action
        error_log( "RWP Creator Suite: Cleaning up data for deleted user ID: {$user_id}" );

        // Clean up user meta data
        $this->cleanup_user_meta_data( $user_id );

        // Clean up user-specific transients and cache
        $this->cleanup_user_transients( $user_id );

        // Clean up audit logs mentioning this user
        $this->cleanup_user_audit_logs( $user_id );

        // Fire action for other components to clean up
        do_action( 'rwp_creator_suite_user_data_cleanup', $user_id );
    }

    /**
     * Clean up user meta data.
     *
     * @param int $user_id User ID.
     */
    private function cleanup_user_meta_data( $user_id ) {
        $meta_keys_to_delete = array(
            // Registration and authentication
            'rwp_creator_suite_registration_method',
            'rwp_creator_suite_auto_login',
            'rwp_creator_suite_original_url',
            'rwp_creator_suite_last_login',
            
            // Caption writer data
            'rwp_caption_favorites',
            'rwp_caption_preferences',
            'rwp_caption_templates',
            'rwp_templates_cache_time',
            'rwp_templates_updated',
            
            // Instagram analyzer
            'instagram_analyzer_whitelist',
        );

        foreach ( $meta_keys_to_delete as $meta_key ) {
            delete_user_meta( $user_id, $meta_key );
        }

        // Clean up usage tracking meta (pattern-based)
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} 
            WHERE user_id = %d 
            AND meta_key LIKE 'rwp_caption_usage_%%'",
            $user_id
        ) );
    }

    /**
     * Clean up user-specific transients and cache.
     *
     * @param int $user_id User ID.
     */
    private function cleanup_user_transients( $user_id ) {
        global $wpdb;

        // Get user email for rate limiting cleanup
        $user_email = get_userdata( $user_id );
        if ( $user_email && $user_email->user_email ) {
            $email_hash = md5( $user_email->user_email );
            
            // Clean up rate limiting transients
            delete_transient( "rwp_creator_suite_reg_{$email_hash}" );
            delete_transient( "rwp_creator_suite_login_{$email_hash}" );
        }

        // Clean up user-specific cached templates and favorites
        $cache_keys_to_delete = array(
            "user_templates_{$user_id}",
            "user_favorites_{$user_id}",
        );

        foreach ( $cache_keys_to_delete as $cache_key ) {
            wp_cache_delete( $cache_key, 'rwp_caption_writer' );
            delete_transient( $cache_key );
        }

        // Clean up any remaining user-specific transients
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_%%_user_{$user_id}_%%' 
            OR option_name LIKE '_transient_timeout_%%_user_{$user_id}_%%'"
        ) );
    }

    /**
     * Clean up audit logs mentioning the deleted user.
     *
     * @param int $user_id User ID.
     */
    private function cleanup_user_audit_logs( $user_id ) {
        $audit_log = get_option( 'rwp_api_key_audit', array() );
        
        if ( ! empty( $audit_log ) && is_array( $audit_log ) ) {
            // Remove entries for this user
            $audit_log = array_filter( $audit_log, function( $entry ) use ( $user_id ) {
                return ! isset( $entry['user_id'] ) || $entry['user_id'] != $user_id;
            } );
            
            // Re-index array and update option
            update_option( 'rwp_api_key_audit', array_values( $audit_log ) );
        }
    }

    /**
     * Get plugin version.
     *
     * @return string
     */
    public function get_version() {
        return RWP_CREATOR_SUITE_VERSION;
    }
}

// Initialize the plugin
RWP_Creator_Suite::get_instance();