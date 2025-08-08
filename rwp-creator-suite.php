<?php
/**
 * Plugin Name: RWP Creator Suite
 * Description: A suite of tools for content creators including streamlined user authentication.
 * Version: 1.2.0
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
define( 'RWP_CREATOR_SUITE_VERSION', '1.2.0' );
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

        // Initialize all components
        $this->wp_login_integration->init();
        $this->subscriber_restrictions->init();
        $this->redirect_handler->init();
        $this->registration_api->init();
        $this->block_manager->init();

        // Load text domain
        load_plugin_textdomain( 'rwp-creator-suite', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        // Additional hooks
        add_action( 'init', array( $this, 'maybe_redirect_wp_login' ) );
    }

    /**
     * Load plugin dependencies.
     */
    private function load_dependencies() {
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