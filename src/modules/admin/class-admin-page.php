<?php
/**
 * RWP Creator Tools Admin Page
 * 
 * Handles the main admin page for RWP Creator Tools plugin.
 *
 * @package    RWP_Creator_Suite
 * @subpackage RWP_Creator_Suite/admin
 * @since      1.6.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Admin_Page {

    /**
     * The menu slug for the main admin page.
     *
     * @var string
     */
    private $menu_slug = 'rwp-creator-tools';

    /**
     * Initialize the admin page.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 10 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    /**
     * Add the main admin menu page.
     */
    public function add_admin_menu() {
        // Only add menu if user has capability
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        add_menu_page(
            __( 'RWP Creator Tools', 'rwp-creator-suite' ),
            __( 'RWP Creator Tools', 'rwp-creator-suite' ),
            'manage_options',
            $this->menu_slug,
            array( $this, 'render_admin_page' ),
            'dashicons-admin-tools',
            4
        );

        // Add the dashboard as the first submenu item to ensure proper URL structure
        add_submenu_page(
            $this->menu_slug,
            __( 'Dashboard', 'rwp-creator-suite' ),
            __( 'Dashboard', 'rwp-creator-suite' ),
            'manage_options',
            $this->menu_slug,
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Render the main admin page.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="rwp-creator-tools-dashboard">
                <div class="rwp-dashboard-grid">
                    <!-- Welcome Section -->
                    <div class="rwp-dashboard-card rwp-welcome-card">
                        <h2><?php esc_html_e( 'Welcome to RWP Creator Tools', 'rwp-creator-suite' ); ?></h2>
                        <p><?php esc_html_e( 'A comprehensive suite of tools designed to help content creators streamline their workflow and enhance their content creation process.', 'rwp-creator-suite' ); ?></p>
                        
                        <div class="rwp-version-info">
                            <span class="rwp-version-label"><?php esc_html_e( 'Version:', 'rwp-creator-suite' ); ?></span>
                            <span class="rwp-version-number"><?php echo esc_html( RWP_CREATOR_SUITE_VERSION ); ?></span>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="rwp-dashboard-card rwp-quick-actions">
                        <h3><?php esc_html_e( 'Quick Actions', 'rwp-creator-suite' ); ?></h3>
                        <div class="rwp-action-buttons">
                            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-caption-writer' ) ); ?>" class="button button-primary">
                                    <?php esc_html_e( 'Configure AI Settings', 'rwp-creator-suite' ); ?>
                                </a>
                            <?php endif; ?>
                            
                            <a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>" class="button">
                                <?php esc_html_e( 'Create New Content', 'rwp-creator-suite' ); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Available Tools -->
                    <div class="rwp-dashboard-card rwp-tools-overview">
                        <h3><?php esc_html_e( 'Available Tools', 'rwp-creator-suite' ); ?></h3>
                        <div class="rwp-tools-grid">
                            <div class="rwp-tool-item">
                                <div class="rwp-tool-icon">
                                    <span class="dashicons dashicons-edit"></span>
                                </div>
                                <div class="rwp-tool-content">
                                    <h4><?php esc_html_e( 'Caption Writer', 'rwp-creator-suite' ); ?></h4>
                                    <p><?php esc_html_e( 'AI-powered caption generation for social media posts.', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </div>

                            <div class="rwp-tool-item">
                                <div class="rwp-tool-icon">
                                    <span class="dashicons dashicons-update"></span>
                                </div>
                                <div class="rwp-tool-content">
                                    <h4><?php esc_html_e( 'Content Repurposer', 'rwp-creator-suite' ); ?></h4>
                                    <p><?php esc_html_e( 'Transform existing content for different platforms.', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </div>

                            <div class="rwp-tool-item">
                                <div class="rwp-tool-icon">
                                    <span class="dashicons dashicons-analytics"></span>
                                </div>
                                <div class="rwp-tool-content">
                                    <h4><?php esc_html_e( 'Instagram Analyzer', 'rwp-creator-suite' ); ?></h4>
                                    <p><?php esc_html_e( 'Analyze Instagram content for insights and optimization.', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </div>

                            <div class="rwp-tool-item">
                                <div class="rwp-tool-icon">
                                    <span class="dashicons dashicons-groups"></span>
                                </div>
                                <div class="rwp-tool-content">
                                    <h4><?php esc_html_e( 'User Management', 'rwp-creator-suite' ); ?></h4>
                                    <p><?php esc_html_e( 'Streamlined user registration and authentication.', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Status -->
                    <div class="rwp-dashboard-card rwp-system-status">
                        <h3><?php esc_html_e( 'System Status', 'rwp-creator-suite' ); ?></h3>
                        <div class="rwp-status-items">
                            <?php
                            $ai_provider = get_option( 'rwp_creator_suite_ai_provider', 'mock' );
                            $is_ai_configured = ( $ai_provider !== 'mock' );
                            ?>
                            <div class="rwp-status-item">
                                <span class="rwp-status-label"><?php esc_html_e( 'AI Service:', 'rwp-creator-suite' ); ?></span>
                                <span class="rwp-status-value <?php echo $is_ai_configured ? 'status-active' : 'status-warning'; ?>">
                                    <?php 
                                    if ( $is_ai_configured ) {
                                        echo esc_html( ucfirst( $ai_provider ) );
                                    } else {
                                        esc_html_e( 'Demo Mode', 'rwp-creator-suite' );
                                    }
                                    ?>
                                </span>
                            </div>

                            <div class="rwp-status-item">
                                <span class="rwp-status-label"><?php esc_html_e( 'User Registration:', 'rwp-creator-suite' ); ?></span>
                                <span class="rwp-status-value <?php echo get_option( 'users_can_register' ) ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo get_option( 'users_can_register' ) ? esc_html__( 'Enabled', 'rwp-creator-suite' ) : esc_html__( 'Disabled', 'rwp-creator-suite' ); ?>
                                </span>
                            </div>

                            <div class="rwp-status-item">
                                <span class="rwp-status-label"><?php esc_html_e( 'Guest Access:', 'rwp-creator-suite' ); ?></span>
                                <span class="rwp-status-value <?php echo get_option( 'rwp_creator_suite_allow_guest_repurpose' ) ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo get_option( 'rwp_creator_suite_allow_guest_repurpose' ) ? esc_html__( 'Enabled', 'rwp-creator-suite' ) : esc_html__( 'Disabled', 'rwp-creator-suite' ); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Support & Documentation -->
                    <div class="rwp-dashboard-card rwp-support">
                        <h3><?php esc_html_e( 'Support & Documentation', 'rwp-creator-suite' ); ?></h3>
                        <p><?php esc_html_e( 'Need help getting started or have questions about the plugin?', 'rwp-creator-suite' ); ?></p>
                        
                        <div class="rwp-support-links">
                            <a href="<?php echo esc_url( 'https://jeremyhixon.com' ); ?>" class="button" target="_blank" rel="noopener">
                                <?php esc_html_e( 'Documentation', 'rwp-creator-suite' ); ?>
                            </a>
                            <a href="<?php echo esc_url( 'mailto:support@jeremyhixon.com' ); ?>" class="button">
                                <?php esc_html_e( 'Contact Support', 'rwp-creator-suite' ); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on our admin page
        if ( strpos( $hook, $this->menu_slug ) === false ) {
            return;
        }

        wp_enqueue_style(
            'rwp-admin-dashboard',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            array(),
            RWP_CREATOR_SUITE_VERSION
        );

        wp_enqueue_script(
            'rwp-admin-dashboard',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            array( 'jquery' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        wp_localize_script(
            'rwp-admin-dashboard',
            'rwpAdminDashboard',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'rwp_admin_dashboard_nonce' ),
                'strings' => array(
                    'loading' => __( 'Loading...', 'rwp-creator-suite' ),
                    'error'   => __( 'An error occurred. Please try again.', 'rwp-creator-suite' ),
                ),
            )
        );
    }
}