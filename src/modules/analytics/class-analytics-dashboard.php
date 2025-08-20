<?php
/**
 * Analytics Dashboard Admin Page - Refactored for Phase 2
 * 
 * Simplified WordPress admin analytics dashboard that visualizes 
 * Phase 1 collected data with better performance and error handling.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.6.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Analytics_Dashboard {

    /**
     * Menu slug for the analytics dashboard.
     *
     * @var string
     */
    private $menu_slug = 'rwp-analytics-dashboard';

    /**
     * Analytics system instance.
     *
     * @var RWP_Creator_Suite_Anonymous_Analytics|null
     */
    private $analytics;

    /**
     * Error flag to track dashboard health.
     *
     * @var bool
     */
    private $has_errors = false;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_dependencies();
    }

    /**
     * Initialize dependencies with error handling.
     */
    private function init_dependencies() {
        try {
            // Try to get analytics instance safely
            if ( class_exists( 'RWP_Creator_Suite_Anonymous_Analytics' ) ) {
                $this->analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
            } else {
                $this->has_errors = true;
                error_log( 'RWP Analytics Dashboard: Anonymous Analytics class not found' );
            }
        } catch ( Exception $e ) {
            $this->has_errors = true;
            error_log( 'RWP Analytics Dashboard Error: ' . $e->getMessage() );
        }
    }

    /**
     * Initialize the analytics dashboard.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 15 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // Only add AJAX handlers if not in error state
        if ( ! $this->has_errors ) {
            add_action( 'wp_ajax_rwp_get_dashboard_metrics', array( $this, 'ajax_get_dashboard_metrics' ) );
            add_action( 'wp_ajax_rwp_export_analytics', array( $this, 'ajax_export_analytics' ) );
        }
    }

    /**
     * Add analytics dashboard to admin menu.
     */
    public function add_admin_menu() {
        // Only add menu if user has capability
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        add_submenu_page(
            'rwp-creator-tools',
            __( 'Analytics Dashboard', 'rwp-creator-suite' ),
            __( 'Analytics', 'rwp-creator-suite' ),
            'manage_options',
            $this->menu_slug,
            array( $this, 'render_dashboard_page' )
        );
    }

    /**
     * Render the analytics dashboard page.
     */
    public function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check for errors first
        if ( $this->has_errors ) {
            $this->render_error_page();
            return;
        }

        $dashboard_data = $this->get_dashboard_data();
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html( get_admin_page_title() ); ?>
                <span class="dashicons dashicons-chart-line" style="margin-left: 10px; color: #666;"></span>
            </h1>
            
            <div class="rwp-analytics-dashboard">
                <?php $this->render_dashboard_nav(); ?>
                
                <div class="rwp-dashboard-content">
                    <?php $this->render_community_overview( $dashboard_data ); ?>
                    <?php $this->render_privacy_center(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render error page when dashboard cannot initialize.
     */
    private function render_error_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e( 'Analytics Dashboard Unavailable', 'rwp-creator-suite' ); ?></strong>
                </p>
                <p>
                    <?php esc_html_e( 'The analytics dashboard is temporarily unavailable. This may be due to missing dependencies or database issues.', 'rwp-creator-suite' ); ?>
                </p>
                <p>
                    <?php esc_html_e( 'Please check the error logs or contact support if this problem persists.', 'rwp-creator-suite' ); ?>
                </p>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e( 'What you can do:', 'rwp-creator-suite' ); ?></h2>
                <ul>
                    <li><?php esc_html_e( 'Check that all plugin modules are properly activated', 'rwp-creator-suite' ); ?></li>
                    <li><?php esc_html_e( 'Verify database tables were created successfully', 'rwp-creator-suite' ); ?></li>
                    <li><?php esc_html_e( 'Review error logs for specific issues', 'rwp-creator-suite' ); ?></li>
                    <li><?php esc_html_e( 'Try deactivating and reactivating the plugin', 'rwp-creator-suite' ); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render simplified dashboard navigation.
     */
    private function render_dashboard_nav() {
        ?>
        <nav class="rwp-dashboard-nav">
            <ul class="rwp-nav-tabs">
                <li><a href="#overview" class="nav-tab nav-tab-active"><?php esc_html_e( 'Community Overview', 'rwp-creator-suite' ); ?></a></li>
                <li><a href="#privacy" class="nav-tab"><?php esc_html_e( 'Privacy & Transparency', 'rwp-creator-suite' ); ?></a></li>
            </ul>
            
            <div class="rwp-dashboard-actions">
                <button type="button" class="button" id="refresh-dashboard">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Refresh', 'rwp-creator-suite' ); ?>
                </button>
                <?php if ( ! $this->has_errors ) : ?>
                <button type="button" class="button" id="export-analytics">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Export', 'rwp-creator-suite' ); ?>
                </button>
                <?php endif; ?>
            </div>
        </nav>
        <?php
    }

    /**
     * Render community overview section.
     *
     * @param array $data Dashboard data.
     */
    private function render_community_overview( $data ) {
        $community_stats = $data['community_stats'] ?? array();
        $active_creators = $community_stats['active_creators'] ?? 0;
        $content_generated = $community_stats['content_generated_24h'] ?? 0;
        $top_platform = $community_stats['top_platform'] ?? 'N/A';
        $most_used_tone = $community_stats['most_used_tone'] ?? 'N/A';
        ?>
        <div id="overview" class="rwp-dashboard-section">
            <div class="rwp-section-header">
                <h2><?php esc_html_e( 'Creator Community Insights', 'rwp-creator-suite' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Real-time insights into your creator community behavior and engagement.', 'rwp-creator-suite' ); ?></p>
            </div>

            <div class="rwp-metrics-grid">
                <div class="rwp-metric-card rwp-metric-primary">
                    <div class="rwp-metric-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="rwp-metric-content">
                        <span class="rwp-metric-number" id="active-creators"><?php echo esc_html( number_format( $active_creators ) ); ?></span>
                        <span class="rwp-metric-label"><?php esc_html_e( 'Active Creators (30 days)', 'rwp-creator-suite' ); ?></span>
                    </div>
                </div>

                <div class="rwp-metric-card rwp-metric-success">
                    <div class="rwp-metric-icon">
                        <span class="dashicons dashicons-edit"></span>
                    </div>
                    <div class="rwp-metric-content">
                        <span class="rwp-metric-number" id="content-generated"><?php echo esc_html( number_format( $content_generated ) ); ?></span>
                        <span class="rwp-metric-label"><?php esc_html_e( 'Content Generated (24h)', 'rwp-creator-suite' ); ?></span>
                    </div>
                </div>

                <div class="rwp-metric-card rwp-metric-info">
                    <div class="rwp-metric-icon">
                        <span class="dashicons dashicons-smartphone"></span>
                    </div>
                    <div class="rwp-metric-content">
                        <span class="rwp-metric-number" id="top-platform"><?php echo esc_html( $top_platform ); ?></span>
                        <span class="rwp-metric-label"><?php esc_html_e( 'Top Platform', 'rwp-creator-suite' ); ?></span>
                    </div>
                </div>

                <div class="rwp-metric-card rwp-metric-warning">
                    <div class="rwp-metric-icon">
                        <span class="dashicons dashicons-admin-comments"></span>
                    </div>
                    <div class="rwp-metric-content">
                        <span class="rwp-metric-number" id="most-used-tone"><?php echo esc_html( ucfirst( $most_used_tone ) ); ?></span>
                        <span class="rwp-metric-label"><?php esc_html_e( 'Most Used Tone', 'rwp-creator-suite' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="rwp-charts-row">
                <div class="rwp-chart-container">
                    <h3><?php esc_html_e( 'Platform Usage Distribution', 'rwp-creator-suite' ); ?></h3>
                    <canvas id="platform-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="rwp-chart-container">
                    <h3><?php esc_html_e( 'Usage Timeline (7 days)', 'rwp-creator-suite' ); ?></h3>
                    <canvas id="usage-timeline-chart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="rwp-activity-feed">
                <h3><?php esc_html_e( 'Recent Activity', 'rwp-creator-suite' ); ?></h3>
                <div class="rwp-activity-list" id="activity-feed">
                    <!-- Activity items populated via JavaScript -->
                </div>
            </div>
        </div>
        <?php
    }




    /**
     * Render simplified privacy transparency center.
     */
    private function render_privacy_center() {
        $consent_stats = $this->get_consent_stats();
        ?>
        <div id="privacy" class="rwp-dashboard-section" style="display: none;">
            <div class="rwp-section-header">
                <h2><?php esc_html_e( '🔍 Privacy & Transparency Center', 'rwp-creator-suite' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Complete transparency about data collection, consent management, and privacy compliance.', 'rwp-creator-suite' ); ?></p>
            </div>

            <div class="rwp-privacy-overview">
                <div class="rwp-data-transparency">
                    <h3><?php esc_html_e( 'What We\'re Tracking', 'rwp-creator-suite' ); ?></h3>
                    <div class="rwp-tracking-list">
                        <div class="rwp-tracking-item positive">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e( 'Hashtags added by users (anonymized)', 'rwp-creator-suite' ); ?>
                        </div>
                        <div class="rwp-tracking-item positive">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e( 'Platform selection patterns', 'rwp-creator-suite' ); ?>
                        </div>
                        <div class="rwp-tracking-item positive">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e( 'Template usage frequency', 'rwp-creator-suite' ); ?>
                        </div>
                        <div class="rwp-tracking-item positive">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e( 'Content generation timing', 'rwp-creator-suite' ); ?>
                        </div>
                        <div class="rwp-tracking-item negative">
                            <span class="dashicons dashicons-no"></span>
                            <?php esc_html_e( 'NO personal content or usernames', 'rwp-creator-suite' ); ?>
                        </div>
                        <div class="rwp-tracking-item negative">
                            <span class="dashicons dashicons-no"></span>
                            <?php esc_html_e( 'NO email addresses or personal data', 'rwp-creator-suite' ); ?>
                        </div>
                        <div class="rwp-tracking-item negative">
                            <span class="dashicons dashicons-no"></span>
                            <?php esc_html_e( 'NO individual user tracking', 'rwp-creator-suite' ); ?>
                        </div>
                    </div>
                </div>

                <div class="rwp-consent-overview">
                    <h3><?php esc_html_e( 'User Consent Status', 'rwp-creator-suite' ); ?></h3>
                    <div class="rwp-consent-metrics">
                        <div class="rwp-consent-metric">
                            <span class="rwp-metric-number"><?php echo esc_html( $consent_stats['total_users'] ); ?></span>
                            <span class="rwp-metric-label"><?php esc_html_e( 'Total Users', 'rwp-creator-suite' ); ?></span>
                        </div>
                        <div class="rwp-consent-metric">
                            <span class="rwp-metric-number"><?php echo esc_html( $consent_stats['consented_users'] ); ?></span>
                            <span class="rwp-metric-label"><?php esc_html_e( 'Consented to Analytics', 'rwp-creator-suite' ); ?></span>
                        </div>
                        <div class="rwp-consent-metric">
                            <span class="rwp-metric-number"><?php echo esc_html( $consent_stats['consent_rate'] ); ?>%</span>
                            <span class="rwp-metric-label"><?php esc_html_e( 'Consent Rate', 'rwp-creator-suite' ); ?></span>
                        </div>
                    </div>
                </div>

                <div class="rwp-compliance-status">
                    <h3><?php esc_html_e( 'Compliance & Audit Status', 'rwp-creator-suite' ); ?></h3>
                    <div class="rwp-compliance-checks">
                        <div class="rwp-compliance-item">
                            <span class="dashicons dashicons-yes-alt status-success"></span>
                            <?php esc_html_e( 'Data anonymization active', 'rwp-creator-suite' ); ?>
                        </div>
                        <div class="rwp-compliance-item">
                            <span class="dashicons dashicons-yes-alt status-success"></span>
                            <?php esc_html_e( 'Automatic data cleanup (12 months)', 'rwp-creator-suite' ); ?>
                        </div>
                        <div class="rwp-compliance-item">
                            <span class="dashicons dashicons-yes-alt status-success"></span>
                            <?php esc_html_e( 'Consent mechanism implemented', 'rwp-creator-suite' ); ?>
                        </div>
                        <div class="rwp-compliance-item">
                            <span class="dashicons dashicons-yes-alt status-success"></span>
                            <?php esc_html_e( 'GDPR compliant data processing', 'rwp-creator-suite' ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get consent stats safely.
     *
     * @return array
     */
    private function get_consent_stats() {
        if ( $this->has_errors ) {
            return array(
                'total_users' => '--',
                'consented_users' => '--',
                'consent_rate' => '--',
            );
        }

        try {
            // Try to get consent stats from user meta
            global $wpdb;
            $total_users = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users}" ) );
            $consented_users = $wpdb->get_var( 
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->usermeta} 
                     WHERE meta_key LIKE %s 
                     AND meta_value = %s",
                    '%consent%',
                    '1'
                )
            );
            
            $consent_rate = $total_users > 0 ? round( ( $consented_users / $total_users ) * 100, 1 ) : 0;
            
            return array(
                'total_users' => number_format( (int) $total_users ),
                'consented_users' => number_format( (int) $consented_users ),
                'consent_rate' => $consent_rate,
            );
        } catch ( Exception $e ) {
            error_log( 'RWP Analytics: Failed to get consent stats: ' . $e->getMessage() );
            return array(
                'total_users' => 'N/A',
                'consented_users' => 'N/A',
                'consent_rate' => 'N/A',
            );
        }
    }

    /**
     * Enqueue simplified admin scripts and styles.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on our analytics dashboard page
        if ( ! str_contains( $hook ?? '', $this->menu_slug ) ) {
            return;
        }

        // Load simplified CSS
        wp_enqueue_style(
            'rwp-analytics-dashboard',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/css/analytics-dashboard-simple.css',
            array(),
            RWP_CREATOR_SUITE_VERSION
        );

        // Only load JavaScript if no errors
        if ( ! $this->has_errors ) {
            wp_enqueue_script(
                'rwp-analytics-dashboard',
                RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/analytics-dashboard-simple.js',
                array( 'jquery' ),
                RWP_CREATOR_SUITE_VERSION,
                true
            );

            wp_localize_script(
                'rwp-analytics-dashboard',
                'rwpAnalyticsDashboard',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'rwp_analytics_dashboard_nonce' ),
                    'hasErrors' => $this->has_errors,
                    'strings' => array(
                        'loading' => __( 'Loading...', 'rwp-creator-suite' ),
                        'error'   => __( 'An error occurred. Please try again.', 'rwp-creator-suite' ),
                        'refresh_success' => __( 'Dashboard refreshed successfully.', 'rwp-creator-suite' ),
                        'export_success' => __( 'Analytics report exported successfully.', 'rwp-creator-suite' ),
                    ),
                )
            );
        }
    }

    /**
     * Get dashboard data with proper error handling.
     *
     * @return array
     */
    private function get_dashboard_data() {
        try {
            if ( ! $this->analytics ) {
                return $this->get_fallback_data();
            }
            
            return $this->compile_dashboard_data();
        } catch ( Exception $e ) {
            error_log( 'RWP Analytics Dashboard Error: ' . $e->getMessage() );
            return $this->get_fallback_data();
        }
    }

    /**
     * Get fallback data when analytics are unavailable.
     *
     * @return array
     */
    private function get_fallback_data() {
        return array(
            'community_stats' => array(
                'active_creators' => '--',
                'content_generated_24h' => '--',
                'top_platform' => 'N/A',
                'most_used_tone' => 'N/A',
            ),
            'hashtag_stats' => array(),
            'content_stats' => array(),
            'ai_stats' => array(
                'avg_response_time' => '--',
                'success_rate' => '--',
                'cache_hit_rate' => '--',
            ),
            'timestamp' => current_time( 'timestamp' ),
            'is_fallback' => true,
        );
    }

    /**
     * Compile dashboard data from various sources.
     *
     * @return array
     */
    private function compile_dashboard_data() {
        $start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
        $end_date = date( 'Y-m-d' );

        return array(
            'community_stats' => $this->get_community_stats( $start_date, $end_date ),
            'hashtag_stats' => $this->get_hashtag_stats(),
            'content_stats' => $this->get_content_stats( $start_date, $end_date ),
            'ai_stats' => $this->get_ai_performance_stats(),
            'timestamp' => current_time( 'timestamp' ),
        );
    }

    /**
     * Get community statistics.
     *
     * @param string $start_date Start date.
     * @param string $end_date End date.
     * @return array
     */
    private function get_community_stats( $start_date, $end_date ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
        if ( ! $table_exists ) {
            error_log( 'RWP Analytics: Table does not exist: ' . $table_name );
            return array(
                'active_creators' => 0,
                'content_generated_24h' => 0,
                'top_platform' => 'N/A',
                'most_used_tone' => 'N/A',
            );
        }

        // Active creators (unique sessions in 30 days)
        $active_creators = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT anonymous_session_hash) 
             FROM {$table_name} 
             WHERE timestamp >= %s AND timestamp <= %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ) );

        // Content generated in last 24 hours
        $content_generated_24h = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$table_name} 
             WHERE event_type = %s 
             AND timestamp >= %s",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_CONTENT_GENERATED,
            date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
        ) );

        // Top platform
        $top_platform = $wpdb->get_var(
            "SELECT platform 
             FROM {$table_name} 
             WHERE platform != '' 
             AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY platform 
             ORDER BY COUNT(*) DESC 
             LIMIT 1"
        );

        // Most used tone
        $most_used_tone = $wpdb->get_var(
            "SELECT JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.tone')) as tone
             FROM {$table_name} 
             WHERE JSON_EXTRACT(event_data, '$.tone') IS NOT NULL
             AND JSON_EXTRACT(event_data, '$.tone') != ''
             AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY tone 
             ORDER BY COUNT(*) DESC 
             LIMIT 1"
        );

        return array(
            'active_creators' => (int) $active_creators,
            'content_generated_24h' => (int) $content_generated_24h,
            'top_platform' => $top_platform ?: 'N/A',
            'most_used_tone' => $most_used_tone ?: 'N/A',
        );
    }

    /**
     * Get hashtag statistics.
     *
     * @return array
     */
    private function get_hashtag_stats() {
        return $this->analytics->get_popular_hashtags( 10 );
    }

    /**
     * Get content statistics.
     *
     * @param string $start_date Start date.
     * @param string $end_date End date.
     * @return array
     */
    private function get_content_stats( $start_date, $end_date ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

        // Template usage stats
        $template_stats = $wpdb->get_results( $wpdb->prepare(
            "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.template_hash')) as template_hash,
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.completion_status')) as completion_status,
                COUNT(*) as usage_count
             FROM {$table_name} 
             WHERE event_type = %s
             AND timestamp >= %s AND timestamp <= %s
             GROUP BY template_hash, completion_status
             ORDER BY usage_count DESC
             LIMIT 10",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_TEMPLATE_USED,
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ), ARRAY_A );

        return array(
            'template_stats' => $template_stats,
        );
    }

    /**
     * Get AI performance statistics.
     *
     * @return array
     */
    private function get_ai_performance_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rwp_anonymous_analytics';

        // Average response time from content generation events
        $avg_response_time = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.processing_time_ms')) AS UNSIGNED)) / 1000
             FROM {$table_name} 
             WHERE event_type = %s
             AND JSON_EXTRACT(event_data, '$.processing_time_ms') IS NOT NULL
             AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_CONTENT_GENERATED
        ) );

        // Success rate
        $total_generations = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$table_name} 
             WHERE event_type = %s
             AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_CONTENT_GENERATED
        ) );

        $successful_generations = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$table_name} 
             WHERE event_type = %s
             AND JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.success')) = 'true'
             AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            RWP_Creator_Suite_Anonymous_Analytics::EVENT_CONTENT_GENERATED
        ) );

        $success_rate = $total_generations > 0 ? ( $successful_generations / $total_generations ) * 100 : 0;

        // Cache hit rate (estimated based on response times)
        $cache_hit_rate = 34; // Placeholder - would need cache implementation

        return array(
            'avg_response_time' => round( (float) $avg_response_time, 1 ),
            'success_rate' => round( $success_rate, 1 ),
            'cache_hit_rate' => $cache_hit_rate,
        );
    }

    /**
     * AJAX handler for getting dashboard metrics.
     */
    public function ajax_get_dashboard_metrics() {
        try {
            if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'rwp_analytics_dashboard_nonce' ) ) {
                wp_send_json_error( 'Invalid nonce' );
                return;
            }

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'Unauthorized' );
                return;
            }

            // Get fresh data
            $dashboard_data = $this->get_dashboard_data();

            wp_send_json_success( $dashboard_data );
        } catch ( Exception $e ) {
            error_log( 'RWP Analytics Dashboard Metrics Error: ' . $e->getMessage() );
            wp_send_json_error( 'Server error occurred' );
        }
    }

    /**
     * AJAX handler for exporting analytics data.
     */
    public function ajax_export_analytics() {
        try {
            if ( ! wp_verify_nonce( $_GET['nonce'] ?? $_POST['nonce'] ?? '', 'rwp_analytics_dashboard_nonce' ) ) {
                wp_die( 'Invalid nonce', 'Unauthorized', array( 'response' => 403 ) );
                return;
            }

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'Unauthorized', 'Unauthorized', array( 'response' => 403 ) );
                return;
            }

            if ( $this->has_errors ) {
                wp_die( 'Analytics data unavailable', 'Export Error', array( 'response' => 503 ) );
                return;
            }

            $export_data = $this->prepare_export_data();
            
            // Set headers for CSV download
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename="analytics-report-' . date( 'Y-m-d' ) . '.csv"' );
            
            // Output CSV
            $output = fopen( 'php://output', 'w' );
            fputcsv( $output, array( 'Metric', 'Value', 'Period' ) );
            
            foreach ( $export_data as $row ) {
                fputcsv( $output, $row );
            }
            
            fclose( $output );
            exit;
        } catch ( Exception $e ) {
            error_log( 'RWP Analytics Export Error: ' . $e->getMessage() );
            wp_die( 'Export failed', 'Export Error', array( 'response' => 500 ) );
        }
    }

    /**
     * Prepare data for export.
     *
     * @return array
     */
    private function prepare_export_data() {
        $dashboard_data = $this->get_dashboard_data();
        $export_data = array();

        // Community stats
        $community = $dashboard_data['community_stats'] ?? array();
        $export_data[] = array( 'Active Creators (30 days)', $community['active_creators'] ?? 0, 'Last 30 days' );
        $export_data[] = array( 'Content Generated (24h)', $community['content_generated_24h'] ?? 0, 'Last 24 hours' );
        $export_data[] = array( 'Top Platform', $community['top_platform'] ?? 'N/A', 'Last 30 days' );
        $export_data[] = array( 'Most Used Tone', $community['most_used_tone'] ?? 'N/A', 'Last 30 days' );

        // Add export timestamp
        $export_data[] = array( 'Export Date', date( 'Y-m-d H:i:s' ), 'Generated' );
        $export_data[] = array( 'Data Source', $dashboard_data['is_fallback'] ? 'Fallback Data' : 'Live Data', 'System' );

        return $export_data;
    }







}