<?php
/**
 * Analytics Insights Admin Page
 * 
 * Handles the analytics insights admin interface for displaying user value
 * and community insights to content creators.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Insights_Admin_Page {

    /**
     * User Value API instance.
     *
     * @var RWP_Creator_Suite_User_Value_API
     */
    private $value_api;

    /**
     * Consent Manager instance.
     *
     * @var RWP_Creator_Suite_Consent_Manager
     */
    private $consent_manager;

    /**
     * Notifications system instance.
     *
     * @var RWP_Creator_Suite_Value_Notifications
     */
    private $notifications;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->value_api = new RWP_Creator_Suite_User_Value_API();
        $this->consent_manager = RWP_Creator_Suite_Consent_Manager::get_instance();
        $this->notifications = RWP_Creator_Suite_Value_Notifications::get_instance();
    }

    /**
     * Initialize the insights admin page.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_insights_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_insights_scripts' ) );
        add_action( 'wp_ajax_rwp_get_user_insights', array( $this, 'ajax_get_user_insights' ) );
        add_action( 'wp_ajax_rwp_generate_monthly_report', array( $this, 'ajax_generate_monthly_report' ) );
        add_action( 'wp_ajax_rwp_get_trending_report', array( $this, 'ajax_get_trending_report' ) );
    }

    /**
     * Add insights submenu page.
     */
    public function add_insights_submenu() {
        add_submenu_page(
            'rwp-creator-tools',
            __( 'Creator Insights', 'rwp-creator-suite' ),
            __( 'Insights', 'rwp-creator-suite' ),
            'edit_posts', // Lower permission level for insights
            'rwp-creator-insights',
            array( $this, 'render_insights_page' )
        );

        add_submenu_page(
            'rwp-creator-tools',
            __( 'Notification Settings', 'rwp-creator-suite' ),
            __( 'Notifications', 'rwp-creator-suite' ),
            'edit_posts',
            'rwp-creator-notifications',
            array( $this, 'render_notifications_page' )
        );

        add_submenu_page(
            'rwp-creator-tools',
            __( 'Achievements', 'rwp-creator-suite' ),
            __( 'Achievements', 'rwp-creator-suite' ),
            'edit_posts',
            'rwp-creator-achievements',
            array( $this, 'render_achievements_page' )
        );
    }

    /**
     * Enqueue insights admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_insights_scripts( $hook ) {
        if ( ! in_array( $hook, array( 'rwp-creator-tools_page_rwp-creator-insights', 'rwp-creator-tools_page_rwp-creator-notifications', 'rwp-creator-tools_page_rwp-creator-achievements' ) ) ) {
            return;
        }

        wp_enqueue_script(
            'rwp-insights-admin',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/insights-admin.js',
            array( 'jquery', 'wp-api-fetch' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        wp_enqueue_style(
            'rwp-insights-admin',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/css/insights-admin.css',
            array(),
            RWP_CREATOR_SUITE_VERSION
        );

        wp_localize_script( 'rwp-insights-admin', 'rwpInsights', array(
            'apiUrl' => rest_url( 'rwp-creator-suite/v1/' ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'ajaxNonce' => wp_create_nonce( 'rwp_insights_nonce' ),
            'hasConsent' => $this->consent_manager->has_user_consented() === true,
            'strings' => array(
                'loading' => __( 'Loading insights...', 'rwp-creator-suite' ),
                'error' => __( 'Failed to load insights. Please try again.', 'rwp-creator-suite' ),
                'noData' => __( 'No data available yet. Start creating content to see insights!', 'rwp-creator-suite' ),
                'consentRequired' => __( 'Please enable analytics consent to view insights.', 'rwp-creator-suite' ),
            ),
        ) );

        // Include Chart.js for analytics visualization
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );
    }

    /**
     * Render the main insights page.
     */
    public function render_insights_page() {
        $has_consent = $this->consent_manager->has_user_consented();
        
        ?>
        <div class="wrap rwp-insights-page">
            <h1><?php esc_html_e( 'Creator Insights', 'rwp-creator-suite' ); ?></h1>
            
            <?php if ( $has_consent !== true ) : ?>
                <?php $this->render_consent_required_notice(); ?>
            <?php else : ?>
                <?php $this->render_insights_dashboard(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the notifications settings page.
     */
    public function render_notifications_page() {
        ?>
        <div class="wrap rwp-notifications-page">
            <h1><?php esc_html_e( 'Notification Settings', 'rwp-creator-suite' ); ?></h1>
            
            <div class="rwp-notifications-container">
                <div class="rwp-notification-preferences">
                    <h2><?php esc_html_e( 'Email Preferences', 'rwp-creator-suite' ); ?></h2>
                    <p><?php esc_html_e( 'Choose what insights and updates you\'d like to receive:', 'rwp-creator-suite' ); ?></p>
                    
                    <form id="rwp-notification-preferences-form" class="rwp-preferences-form">
                        <div class="rwp-preference-group">
                            <label class="rwp-preference-item">
                                <input type="checkbox" name="weekly_trends" value="1" />
                                <div class="rwp-preference-content">
                                    <strong><?php esc_html_e( 'Weekly Trend Reports', 'rwp-creator-suite' ); ?></strong>
                                    <p><?php esc_html_e( 'Get the latest trending hashtags and content insights every Monday.', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </label>

                            <label class="rwp-preference-item">
                                <input type="checkbox" name="monthly_reports" value="1" />
                                <div class="rwp-preference-content">
                                    <strong><?php esc_html_e( 'Monthly Performance Reports', 'rwp-creator-suite' ); ?></strong>
                                    <p><?php esc_html_e( 'Comprehensive monthly analysis of your content performance and growth.', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </label>

                            <label class="rwp-preference-item">
                                <input type="checkbox" name="achievement_notifications" value="1" />
                                <div class="rwp-preference-content">
                                    <strong><?php esc_html_e( 'Achievement Celebrations', 'rwp-creator-suite' ); ?></strong>
                                    <p><?php esc_html_e( 'Get notified when you unlock new achievements and milestones.', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </label>

                            <label class="rwp-preference-item">
                                <input type="checkbox" name="opportunity_alerts" value="1" />
                                <div class="rwp-preference-content">
                                    <strong><?php esc_html_e( 'Growth Opportunities', 'rwp-creator-suite' ); ?></strong>
                                    <p><?php esc_html_e( 'Personalized recommendations for trending hashtags and content strategies.', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </label>

                            <label class="rwp-preference-item">
                                <input type="checkbox" name="breaking_trends" value="1" />
                                <div class="rwp-preference-content">
                                    <strong><?php esc_html_e( 'Breaking Trend Alerts', 'rwp-creator-suite' ); ?></strong>
                                    <p><?php esc_html_e( 'Real-time notifications about rapidly emerging trends (max 1 per week).', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </label>
                        </div>

                        <div class="rwp-preference-settings">
                            <h3><?php esc_html_e( 'Email Format', 'rwp-creator-suite' ); ?></h3>
                            <label>
                                <input type="radio" name="email_format" value="html" checked />
                                <?php esc_html_e( 'Rich HTML emails with charts and formatting', 'rwp-creator-suite' ); ?>
                            </label>
                            <label>
                                <input type="radio" name="email_format" value="text" />
                                <?php esc_html_e( 'Plain text emails only', 'rwp-creator-suite' ); ?>
                            </label>

                            <h3><?php esc_html_e( 'Preferred Time', 'rwp-creator-suite' ); ?></h3>
                            <select name="notification_time">
                                <option value="09:00"><?php esc_html_e( '9:00 AM', 'rwp-creator-suite' ); ?></option>
                                <option value="10:00" selected><?php esc_html_e( '10:00 AM', 'rwp-creator-suite' ); ?></option>
                                <option value="12:00"><?php esc_html_e( '12:00 PM', 'rwp-creator-suite' ); ?></option>
                                <option value="18:00"><?php esc_html_e( '6:00 PM', 'rwp-creator-suite' ); ?></option>
                            </select>
                        </div>

                        <div class="rwp-form-actions">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Save Preferences', 'rwp-creator-suite' ); ?>
                            </button>
                            <button type="button" id="rwp-test-notification" class="button">
                                <?php esc_html_e( 'Send Test Email', 'rwp-creator-suite' ); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rwp-notification-stats">
                    <h2><?php esc_html_e( 'Recent Notifications', 'rwp-creator-suite' ); ?></h2>
                    <div id="rwp-notification-history">
                        <p><?php esc_html_e( 'Loading notification history...', 'rwp-creator-suite' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the achievements page.
     */
    public function render_achievements_page() {
        $has_consent = $this->consent_manager->has_user_consented();
        
        ?>
        <div class="wrap rwp-achievements-page">
            <h1><?php esc_html_e( 'Your Creator Achievements', 'rwp-creator-suite' ); ?></h1>
            
            <?php if ( $has_consent !== true ) : ?>
                <?php $this->render_consent_required_notice(); ?>
            <?php else : ?>
                <div class="rwp-achievements-container">
                    <div class="rwp-achievements-header">
                        <p><?php esc_html_e( 'Track your progress and celebrate your creative milestones!', 'rwp-creator-suite' ); ?></p>
                    </div>
                    
                    <div id="rwp-achievements-grid" class="rwp-achievements-grid">
                        <div class="rwp-loading">
                            <p><?php esc_html_e( 'Loading your achievements...', 'rwp-creator-suite' ); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render consent required notice.
     */
    private function render_consent_required_notice() {
        ?>
        <div class="rwp-consent-notice">
            <div class="rwp-consent-card">
                <div class="rwp-consent-icon">
                    <span class="dashicons dashicons-analytics"></span>
                </div>
                <div class="rwp-consent-content">
                    <h2><?php esc_html_e( 'Analytics Consent Required', 'rwp-creator-suite' ); ?></h2>
                    <p><?php esc_html_e( 'To view personalized insights and community trends, please enable analytics data collection. Your data is completely anonymous and helps us provide better recommendations for all creators.', 'rwp-creator-suite' ); ?></p>
                    
                    <div class="rwp-consent-benefits">
                        <h3><?php esc_html_e( 'What you\'ll get:', 'rwp-creator-suite' ); ?></h3>
                        <ul>
                            <li>📈 <?php esc_html_e( 'Trending hashtag recommendations', 'rwp-creator-suite' ); ?></li>
                            <li>🎯 <?php esc_html_e( 'Performance benchmarking vs. community', 'rwp-creator-suite' ); ?></li>
                            <li>📊 <?php esc_html_e( 'Monthly performance reports', 'rwp-creator-suite' ); ?></li>
                            <li>🏆 <?php esc_html_e( 'Achievement tracking and gamification', 'rwp-creator-suite' ); ?></li>
                            <li>💡 <?php esc_html_e( 'Personalized optimization suggestions', 'rwp-creator-suite' ); ?></li>
                        </ul>
                    </div>

                    <div class="rwp-consent-actions">
                        <button id="rwp-enable-analytics" class="button button-primary button-large">
                            <?php esc_html_e( 'Enable Analytics & Get Insights', 'rwp-creator-suite' ); ?>
                        </button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=privacy' ) ); ?>" class="button">
                            <?php esc_html_e( 'Learn More About Privacy', 'rwp-creator-suite' ); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render insights dashboard.
     */
    private function render_insights_dashboard() {
        ?>
        <div class="rwp-insights-dashboard">
            <div class="rwp-insights-header">
                <div class="rwp-insights-summary" id="rwp-insights-summary">
                    <div class="rwp-loading">
                        <p><?php esc_html_e( 'Loading your insights...', 'rwp-creator-suite' ); ?></p>
                    </div>
                </div>
                
                <div class="rwp-insights-actions">
                    <button id="rwp-refresh-insights" class="button">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Refresh', 'rwp-creator-suite' ); ?>
                    </button>
                    <button id="rwp-generate-report" class="button button-primary">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php esc_html_e( 'Generate Report', 'rwp-creator-suite' ); ?>
                    </button>
                </div>
            </div>

            <div class="rwp-insights-tabs">
                <nav class="rwp-tab-nav">
                    <button class="rwp-tab-button active" data-tab="overview"><?php esc_html_e( 'Overview', 'rwp-creator-suite' ); ?></button>
                    <button class="rwp-tab-button" data-tab="trending"><?php esc_html_e( 'Trending', 'rwp-creator-suite' ); ?></button>
                    <button class="rwp-tab-button" data-tab="benchmarks"><?php esc_html_e( 'Benchmarks', 'rwp-creator-suite' ); ?></button>
                    <button class="rwp-tab-button" data-tab="recommendations"><?php esc_html_e( 'Recommendations', 'rwp-creator-suite' ); ?></button>
                    <button class="rwp-tab-button" data-tab="achievements"><?php esc_html_e( 'Achievements', 'rwp-creator-suite' ); ?></button>
                </nav>

                <div class="rwp-tab-content">
                    <div id="rwp-tab-overview" class="rwp-tab-panel active">
                        <div class="rwp-insights-grid">
                            <div class="rwp-insight-card rwp-stats-card">
                                <h3><?php esc_html_e( 'Your Statistics', 'rwp-creator-suite' ); ?></h3>
                                <div id="rwp-user-stats" class="rwp-stats-grid">
                                    <div class="rwp-loading">
                                        <p><?php esc_html_e( 'Loading stats...', 'rwp-creator-suite' ); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="rwp-insight-card rwp-chart-card">
                                <h3><?php esc_html_e( 'Platform Distribution', 'rwp-creator-suite' ); ?></h3>
                                <div class="rwp-chart-container">
                                    <canvas id="rwp-platform-chart"></canvas>
                                </div>
                            </div>

                            <div class="rwp-insight-card rwp-recent-activity">
                                <h3><?php esc_html_e( 'Recent Activity', 'rwp-creator-suite' ); ?></h3>
                                <div id="rwp-activity-feed">
                                    <div class="rwp-loading">
                                        <p><?php esc_html_e( 'Loading activity...', 'rwp-creator-suite' ); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="rwp-tab-trending" class="rwp-tab-panel">
                        <div class="rwp-trending-container">
                            <div class="rwp-trending-header">
                                <h3><?php esc_html_e( 'What\'s Trending', 'rwp-creator-suite' ); ?></h3>
                                <select id="rwp-trending-period">
                                    <option value="weekly"><?php esc_html_e( 'This Week', 'rwp-creator-suite' ); ?></option>
                                    <option value="monthly"><?php esc_html_e( 'This Month', 'rwp-creator-suite' ); ?></option>
                                </select>
                            </div>
                            <div id="rwp-trending-content">
                                <div class="rwp-loading">
                                    <p><?php esc_html_e( 'Loading trending data...', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="rwp-tab-benchmarks" class="rwp-tab-panel">
                        <div class="rwp-benchmarks-container">
                            <div id="rwp-benchmark-content">
                                <div class="rwp-loading">
                                    <p><?php esc_html_e( 'Loading benchmarks...', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="rwp-tab-recommendations" class="rwp-tab-panel">
                        <div class="rwp-recommendations-container">
                            <div id="rwp-recommendations-content">
                                <div class="rwp-loading">
                                    <p><?php esc_html_e( 'Loading recommendations...', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="rwp-tab-achievements" class="rwp-tab-panel">
                        <div class="rwp-achievements-container">
                            <div id="rwp-achievements-content">
                                <div class="rwp-loading">
                                    <p><?php esc_html_e( 'Loading achievements...', 'rwp-creator-suite' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for getting user insights.
     */
    public function ajax_get_user_insights() {
        check_ajax_referer( 'rwp_insights_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'Insufficient permissions.', 'rwp-creator-suite' ) );
        }

        $request = new WP_REST_Request( 'GET', '/rwp-creator-suite/v1/user-insights' );
        $response = $this->value_api->get_user_insights( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        wp_send_json_success( $response->get_data() );
    }

    /**
     * AJAX handler for generating monthly report.
     */
    public function ajax_generate_monthly_report() {
        check_ajax_referer( 'rwp_insights_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'Insufficient permissions.', 'rwp-creator-suite' ) );
        }

        $month = isset( $_POST['month'] ) ? sanitize_text_field( $_POST['month'] ) : date( 'n' );
        $year = isset( $_POST['year'] ) ? sanitize_text_field( $_POST['year'] ) : date( 'Y' );

        $request = new WP_REST_Request( 'POST', '/rwp-creator-suite/v1/monthly-report' );
        $request->set_param( 'month', $month );
        $request->set_param( 'year', $year );

        $response = $this->value_api->generate_monthly_report( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        wp_send_json_success( $response->get_data() );
    }

    /**
     * AJAX handler for getting trending report.
     */
    public function ajax_get_trending_report() {
        check_ajax_referer( 'rwp_insights_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'Insufficient permissions.', 'rwp-creator-suite' ) );
        }

        $period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'weekly';

        $request = new WP_REST_Request( 'GET', '/rwp-creator-suite/v1/trending-report' );
        $request->set_param( 'period', $period );

        $response = $this->value_api->get_trending_report( $request );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        wp_send_json_success( $response->get_data() );
    }
}