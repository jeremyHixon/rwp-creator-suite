<?php
/**
 * GDPR Admin Interface
 *
 * Provides comprehensive admin interface for GDPR compliance management,
 * reporting, and user privacy controls within the WordPress admin dashboard.
 *
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_GDPR_Admin_Interface {

    /**
     * Initialize admin interface.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_privacy_submenu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_rwp_export_compliance_report', array( $this, 'handle_compliance_report_export' ) );
        add_action( 'wp_ajax_rwp_run_compliance_check', array( $this, 'handle_manual_compliance_check' ) );
        add_action( 'wp_ajax_rwp_cleanup_expired_data', array( $this, 'handle_expired_data_cleanup' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_settings' ) );
    }

    /**
     * Add privacy submenu to admin.
     */
    public function add_privacy_submenu() {
        add_submenu_page(
            'rwp-creator-tools', // Parent menu slug
            __( 'Privacy & GDPR Compliance', 'rwp-creator-suite' ),
            __( 'Privacy', 'rwp-creator-suite' ),
            'manage_options',
            'rwp-privacy-compliance',
            array( $this, 'render_privacy_compliance_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        if ( $hook_suffix !== 'creator-tools_page_rwp-privacy-compliance' ) {
            return;
        }

        wp_enqueue_script(
            'rwp-gdpr-admin',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/gdpr-admin.js',
            array( 'jquery', 'wp-api-fetch', 'chart-js' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        wp_enqueue_style(
            'rwp-gdpr-admin',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/css/gdpr-admin.css',
            array( 'wp-admin' ),
            RWP_CREATOR_SUITE_VERSION
        );

        wp_localize_script( 'rwp-gdpr-admin', 'rwpGdprAdmin', array(
            'apiUrl' => rest_url( 'rwp-creator-suite/v1/' ),
            'nonce' => wp_create_nonce( 'rwp_gdpr_admin' ),
            'strings' => array(
                'runningComplianceCheck' => __( 'Running compliance check...', 'rwp-creator-suite' ),
                'complianceCheckComplete' => __( 'Compliance check completed', 'rwp-creator-suite' ),
                'exportingReport' => __( 'Exporting report...', 'rwp-creator-suite' ),
                'cleaningUpData' => __( 'Cleaning up expired data...', 'rwp-creator-suite' ),
                'dataCleanupComplete' => __( 'Data cleanup completed', 'rwp-creator-suite' ),
                'confirmDataCleanup' => __( 'Are you sure you want to permanently delete expired data? This action cannot be undone.', 'rwp-creator-suite' ),
                'error' => __( 'An error occurred. Please try again.', 'rwp-creator-suite' ),
                'success' => __( 'Action completed successfully.', 'rwp-creator-suite' ),
            ),
        ) );
    }

    /**
     * Render privacy compliance admin page.
     */
    public function render_privacy_compliance_page() {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';
        
        ?>
        <div class="wrap rwp-gdpr-admin">
            <h1><?php esc_html_e( 'Privacy & GDPR Compliance', 'rwp-creator-suite' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <?php $this->render_admin_tabs( $current_tab ); ?>
            </nav>

            <div class="rwp-gdpr-content">
                <?php
                switch ( $current_tab ) {
                    case 'overview':
                        $this->render_overview_tab();
                        break;
                    case 'compliance':
                        $this->render_compliance_tab();
                        break;
                    case 'consent':
                        $this->render_consent_tab();
                        break;
                    case 'data-rights':
                        $this->render_data_rights_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    default:
                        $this->render_overview_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render admin navigation tabs.
     *
     * @param string $current_tab Current active tab.
     */
    private function render_admin_tabs( $current_tab ) {
        $tabs = array(
            'overview' => __( 'Overview', 'rwp-creator-suite' ),
            'compliance' => __( 'Compliance Monitor', 'rwp-creator-suite' ),
            'consent' => __( 'Consent Management', 'rwp-creator-suite' ),
            'data-rights' => __( 'Data Subject Rights', 'rwp-creator-suite' ),
            'settings' => __( 'Settings', 'rwp-creator-suite' ),
        );

        foreach ( $tabs as $tab_key => $tab_label ) {
            $url = admin_url( 'admin.php?page=rwp-privacy-compliance&tab=' . $tab_key );
            $active_class = $current_tab === $tab_key ? 'nav-tab-active' : '';
            
            printf(
                '<a href="%s" class="nav-tab %s">%s</a>',
                esc_url( $url ),
                esc_attr( $active_class ),
                esc_html( $tab_label )
            );
        }
    }

    /**
     * Render overview tab.
     */
    private function render_overview_tab() {
        $compliance_status = $this->get_compliance_overview();
        $consent_stats = $this->get_consent_statistics();
        
        ?>
        <div class="rwp-gdpr-overview">
            <div class="rwp-gdpr-cards">
                <div class="rwp-card compliance-score">
                    <h3><?php esc_html_e( 'Compliance Score', 'rwp-creator-suite' ); ?></h3>
                    <div class="score-display">
                        <span class="score-number score-<?php echo esc_attr( $compliance_status['status'] ); ?>">
                            <?php echo esc_html( $compliance_status['score'] ); ?>%
                        </span>
                        <span class="score-status"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $compliance_status['status'] ) ) ); ?></span>
                    </div>
                </div>

                <div class="rwp-card consent-overview">
                    <h3><?php esc_html_e( 'Consent Overview', 'rwp-creator-suite' ); ?></h3>
                    <div class="consent-metrics">
                        <div class="metric">
                            <span class="metric-number"><?php echo esc_html( $consent_stats['total_users_with_consent'] ); ?></span>
                            <span class="metric-label"><?php esc_html_e( 'Users with Consent', 'rwp-creator-suite' ); ?></span>
                        </div>
                        <div class="metric">
                            <span class="metric-number"><?php echo esc_html( number_format( $consent_stats['consent_rate'], 1 ) ); ?>%</span>
                            <span class="metric-label"><?php esc_html_e( 'Consent Rate', 'rwp-creator-suite' ); ?></span>
                        </div>
                    </div>
                </div>

                <div class="rwp-card data-overview">
                    <h3><?php esc_html_e( 'Data Overview', 'rwp-creator-suite' ); ?></h3>
                    <div class="data-metrics">
                        <div class="metric">
                            <span class="metric-number"><?php echo esc_html( $consent_stats['total_analytics_events'] ); ?></span>
                            <span class="metric-label"><?php esc_html_e( 'Analytics Events', 'rwp-creator-suite' ); ?></span>
                        </div>
                        <div class="metric">
                            <span class="metric-number metric-<?php echo esc_attr( $consent_stats['data_retention_status'] ); ?>">
                                <?php echo esc_html( ucfirst( str_replace( '_', ' ', $consent_stats['data_retention_status'] ) ) ); ?>
                            </span>
                            <span class="metric-label"><?php esc_html_e( 'Data Retention', 'rwp-creator-suite' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rwp-gdpr-recent-activity">
                <h3><?php esc_html_e( 'Recent Activity', 'rwp-creator-suite' ); ?></h3>
                <?php $this->render_recent_activity(); ?>
            </div>

            <div class="rwp-gdpr-quick-actions">
                <h3><?php esc_html_e( 'Quick Actions', 'rwp-creator-suite' ); ?></h3>
                <div class="quick-actions-grid">
                    <button type="button" class="button button-primary" id="run-compliance-check">
                        <?php esc_html_e( 'Run Compliance Check', 'rwp-creator-suite' ); ?>
                    </button>
                    <button type="button" class="button" id="export-compliance-report">
                        <?php esc_html_e( 'Export Compliance Report', 'rwp-creator-suite' ); ?>
                    </button>
                    <button type="button" class="button" id="cleanup-expired-data">
                        <?php esc_html_e( 'Clean Up Expired Data', 'rwp-creator-suite' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render compliance monitor tab.
     */
    private function render_compliance_tab() {
        $latest_report = get_option( 'rwp_daily_compliance_report', array() );
        $weekly_report = get_option( 'rwp_weekly_compliance_report', array() );
        
        ?>
        <div class="rwp-compliance-monitor">
            <div class="rwp-compliance-header">
                <h3><?php esc_html_e( 'Compliance Monitoring', 'rwp-creator-suite' ); ?></h3>
                <div class="compliance-actions">
                    <button type="button" class="button button-primary" id="refresh-compliance-data">
                        <?php esc_html_e( 'Refresh Data', 'rwp-creator-suite' ); ?>
                    </button>
                </div>
            </div>

            <?php if ( ! empty( $latest_report ) ) : ?>
                <div class="compliance-status-banner status-<?php echo esc_attr( $latest_report['status'] ?? 'unknown' ); ?>">
                    <div class="status-info">
                        <h4><?php esc_html_e( 'Current Compliance Status', 'rwp-creator-suite' ); ?></h4>
                        <p class="status-description">
                            <?php
                            printf(
                                /* translators: %1$d: Compliance score, %2$s: Last check time */
                                esc_html__( 'Score: %1$d%% - Last checked: %2$s', 'rwp-creator-suite' ),
                                esc_html( $latest_report['compliance_score'] ),
                                esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $latest_report['timestamp'] ) ) )
                            );
                            ?>
                        </p>
                    </div>
                </div>

                <?php if ( ! empty( $latest_report['issues_found'] ) ) : ?>
                    <div class="compliance-issues">
                        <h4><?php esc_html_e( 'Issues Requiring Attention', 'rwp-creator-suite' ); ?></h4>
                        <?php foreach ( $latest_report['issues_found'] as $issue ) : ?>
                            <div class="issue-item severity-<?php echo esc_attr( $issue['severity'] ?? 'medium' ); ?>">
                                <h5><?php echo esc_html( $issue['description'] ); ?></h5>
                                <?php if ( isset( $issue['action_required'] ) ) : ?>
                                    <p class="issue-action">
                                        <strong><?php esc_html_e( 'Action Required:', 'rwp-creator-suite' ); ?></strong>
                                        <?php echo esc_html( $issue['action_required'] ); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ( isset( $issue['count'] ) ) : ?>
                                    <p class="issue-count">
                                        <strong><?php esc_html_e( 'Affected Records:', 'rwp-creator-suite' ); ?></strong>
                                        <?php echo esc_html( $issue['count'] ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="compliance-checks-performed">
                    <h4><?php esc_html_e( 'Checks Performed', 'rwp-creator-suite' ); ?></h4>
                    <ul class="checks-list">
                        <?php foreach ( $latest_report['checks_performed'] as $check ) : ?>
                            <li class="check-item">
                                <span class="check-name"><?php echo esc_html( str_replace( '_', ' ', ucwords( $check ) ) ); ?></span>
                                <span class="check-status check-passed"><?php esc_html_e( 'Completed', 'rwp-creator-suite' ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if ( ! empty( $weekly_report['trends'] ) ) : ?>
                    <div class="compliance-trends">
                        <h4><?php esc_html_e( 'Compliance Trends', 'rwp-creator-suite' ); ?></h4>
                        <div class="trend-info">
                            <p>
                                <strong><?php esc_html_e( 'Trend:', 'rwp-creator-suite' ); ?></strong>
                                <span class="trend-<?php echo esc_attr( $weekly_report['trends']['trend'] ); ?>">
                                    <?php echo esc_html( ucfirst( str_replace( '_', ' ', $weekly_report['trends']['trend'] ) ) ); ?>
                                </span>
                            </p>
                            <p>
                                <strong><?php esc_html_e( 'Average Score:', 'rwp-creator-suite' ); ?></strong>
                                <?php echo esc_html( $weekly_report['trends']['average_score'] ); ?>%
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="no-compliance-data">
                    <p><?php esc_html_e( 'No compliance data available yet. Run your first compliance check to get started.', 'rwp-creator-suite' ); ?></p>
                    <button type="button" class="button button-primary" id="initial-compliance-check">
                        <?php esc_html_e( 'Run Initial Compliance Check', 'rwp-creator-suite' ); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render consent management tab.
     */
    private function render_consent_tab() {
        if ( class_exists( 'RWP_Creator_Suite_Granular_Consent' ) ) {
            $granular_consent = new RWP_Creator_Suite_Granular_Consent();
            $consent_stats = $granular_consent->get_consent_statistics();
        } else {
            $consent_manager = RWP_Creator_Suite_Consent_Manager::get_instance();
            $consent_stats = $consent_manager->get_consent_stats();
        }
        
        ?>
        <div class="rwp-consent-management">
            <div class="consent-overview-cards">
                <div class="rwp-card">
                    <h3><?php esc_html_e( 'Total Users with Consent', 'rwp-creator-suite' ); ?></h3>
                    <div class="metric-display">
                        <span class="metric-number"><?php echo esc_html( $consent_stats['total_users_with_consent'] ?? 0 ); ?></span>
                    </div>
                </div>

                <?php if ( isset( $consent_stats['categories'] ) ) : ?>
                    <?php foreach ( $consent_stats['categories'] as $category_id => $category_data ) : ?>
                        <div class="rwp-card consent-category">
                            <h3><?php echo esc_html( $category_data['name'] ); ?></h3>
                            <div class="metric-display">
                                <span class="metric-number"><?php echo esc_html( $category_data['consented_users'] ); ?></span>
                                <span class="metric-label"><?php esc_html_e( 'users', 'rwp-creator-suite' ); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="consent-banner-preview">
                <h3><?php esc_html_e( 'Consent Banner Preview', 'rwp-creator-suite' ); ?></h3>
                <div class="banner-preview-container">
                    <?php
                    if ( class_exists( 'RWP_Creator_Suite_Granular_Consent' ) ) {
                        $granular_consent = new RWP_Creator_Suite_Granular_Consent();
                        $granular_consent->render_consent_form( 'admin_preview' );
                    }
                    ?>
                </div>
            </div>

            <div class="consent-settings">
                <h3><?php esc_html_e( 'Consent Settings', 'rwp-creator-suite' ); ?></h3>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'rwp_consent_settings' );
                    do_settings_sections( 'rwp_consent_settings' );
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Consent Renewal Period', 'rwp-creator-suite' ); ?></th>
                            <td>
                                <select name="rwp_consent_renewal_period">
                                    <option value="never"><?php esc_html_e( 'Never', 'rwp-creator-suite' ); ?></option>
                                    <option value="annually" <?php selected( get_option( 'rwp_consent_renewal_period', 'never' ), 'annually' ); ?>>
                                        <?php esc_html_e( 'Annually', 'rwp-creator-suite' ); ?>
                                    </option>
                                    <option value="biannually" <?php selected( get_option( 'rwp_consent_renewal_period', 'never' ), 'biannually' ); ?>>
                                        <?php esc_html_e( 'Every 6 months', 'rwp-creator-suite' ); ?>
                                    </option>
                                </select>
                                <p class="description"><?php esc_html_e( 'How often to request consent renewal from users.', 'rwp-creator-suite' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Cookie Consent Banner', 'rwp-creator-suite' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rwp_show_consent_banner" value="1" <?php checked( get_option( 'rwp_show_consent_banner', 1 ) ); ?>>
                                    <?php esc_html_e( 'Show consent banner to new visitors', 'rwp-creator-suite' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render data subject rights tab.
     */
    private function render_data_rights_tab() {
        $recent_requests = $this->get_recent_data_rights_requests();
        
        ?>
        <div class="rwp-data-rights">
            <div class="data-rights-overview">
                <h3><?php esc_html_e( 'Data Subject Rights Management', 'rwp-creator-suite' ); ?></h3>
                <p><?php esc_html_e( 'Monitor and manage data subject rights requests (GDPR Articles 15-22).', 'rwp-creator-suite' ); ?></p>
            </div>

            <div class="data-rights-cards">
                <div class="rwp-card">
                    <h4><?php esc_html_e( 'Data Export Requests', 'rwp-creator-suite' ); ?></h4>
                    <div class="rights-info">
                        <p><?php esc_html_e( 'Users can request a copy of all their personal data (Article 15).', 'rwp-creator-suite' ); ?></p>
                        <p><strong><?php esc_html_e( 'Response Time:', 'rwp-creator-suite' ); ?></strong> Automated</p>
                    </div>
                </div>

                <div class="rwp-card">
                    <h4><?php esc_html_e( 'Data Rectification', 'rwp-creator-suite' ); ?></h4>
                    <div class="rights-info">
                        <p><?php esc_html_e( 'Users can correct inaccurate personal data (Article 16).', 'rwp-creator-suite' ); ?></p>
                        <p><strong><?php esc_html_e( 'Response Time:', 'rwp-creator-suite' ); ?></strong> Immediate</p>
                    </div>
                </div>

                <div class="rwp-card">
                    <h4><?php esc_html_e( 'Data Erasure', 'rwp-creator-suite' ); ?></h4>
                    <div class="rights-info">
                        <p><?php esc_html_e( 'Users can request deletion of their personal data (Article 17).', 'rwp-creator-suite' ); ?></p>
                        <p><strong><?php esc_html_e( 'Response Time:', 'rwp-creator-suite' ); ?></strong> 30 days grace period</p>
                    </div>
                </div>

                <div class="rwp-card">
                    <h4><?php esc_html_e( 'Data Portability', 'rwp-creator-suite' ); ?></h4>
                    <div class="rights-info">
                        <p><?php esc_html_e( 'Users can download their data in portable formats (Article 20).', 'rwp-creator-suite' ); ?></p>
                        <p><strong><?php esc_html_e( 'Formats:', 'rwp-creator-suite' ); ?></strong> JSON, CSV, XML</p>
                    </div>
                </div>
            </div>

            <?php if ( ! empty( $recent_requests ) ) : ?>
                <div class="recent-requests">
                    <h4><?php esc_html_e( 'Recent Requests', 'rwp-creator-suite' ); ?></h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Date', 'rwp-creator-suite' ); ?></th>
                                <th><?php esc_html_e( 'User', 'rwp-creator-suite' ); ?></th>
                                <th><?php esc_html_e( 'Request Type', 'rwp-creator-suite' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'rwp-creator-suite' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_requests as $request ) : ?>
                                <tr>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $request['date'] ) ) ); ?></td>
                                    <td><?php echo esc_html( $request['user_email'] ); ?></td>
                                    <td><?php echo esc_html( $request['request_type'] ); ?></td>
                                    <td><span class="status-<?php echo esc_attr( $request['status'] ); ?>"><?php echo esc_html( ucfirst( $request['status'] ) ); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="data-rights-tools">
                <h4><?php esc_html_e( 'Administrative Tools', 'rwp-creator-suite' ); ?></h4>
                <div class="tools-grid">
                    <div class="tool-item">
                        <h5><?php esc_html_e( 'Bulk Data Export', 'rwp-creator-suite' ); ?></h5>
                        <p><?php esc_html_e( 'Export data for multiple users at once for compliance audits.', 'rwp-creator-suite' ); ?></p>
                        <button type="button" class="button" disabled>
                            <?php esc_html_e( 'Coming Soon', 'rwp-creator-suite' ); ?>
                        </button>
                    </div>
                    
                    <div class="tool-item">
                        <h5><?php esc_html_e( 'Data Anonymization', 'rwp-creator-suite' ); ?></h5>
                        <p><?php esc_html_e( 'Anonymize user data while preserving analytics insights.', 'rwp-creator-suite' ); ?></p>
                        <button type="button" class="button" disabled>
                            <?php esc_html_e( 'Coming Soon', 'rwp-creator-suite' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings tab.
     */
    private function render_settings_tab() {
        ?>
        <div class="rwp-gdpr-settings">
            <form method="post" action="options.php">
                <?php
                settings_fields( 'rwp_gdpr_settings' );
                do_settings_sections( 'rwp_gdpr_settings' );
                ?>

                <h3><?php esc_html_e( 'Compliance Monitoring Settings', 'rwp-creator-suite' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Daily Compliance Checks', 'rwp-creator-suite' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rwp_enable_daily_compliance_checks" value="1" <?php checked( get_option( 'rwp_enable_daily_compliance_checks', 1 ) ); ?>>
                                <?php esc_html_e( 'Enable automatic daily compliance monitoring', 'rwp-creator-suite' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Compliance Alerts', 'rwp-creator-suite' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rwp_send_compliance_alerts" value="1" <?php checked( get_option( 'rwp_send_compliance_alerts', 1 ) ); ?>>
                                <?php esc_html_e( 'Send email alerts for critical compliance issues', 'rwp-creator-suite' ); ?>
                            </label>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: Admin email address */
                                    esc_html__( 'Alerts will be sent to: %s', 'rwp-creator-suite' ),
                                    esc_html( get_option( 'admin_email' ) )
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Weekly Reports', 'rwp-creator-suite' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rwp_send_weekly_compliance_report" value="1" <?php checked( get_option( 'rwp_send_weekly_compliance_report', 0 ) ); ?>>
                                <?php esc_html_e( 'Send weekly compliance summary reports', 'rwp-creator-suite' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e( 'Data Retention Settings', 'rwp-creator-suite' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Automatic Data Cleanup', 'rwp-creator-suite' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rwp_enable_automatic_data_cleanup" value="1" <?php checked( get_option( 'rwp_enable_automatic_data_cleanup', 1 ) ); ?>>
                                <?php esc_html_e( 'Automatically delete data beyond retention periods', 'rwp-creator-suite' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'This helps maintain compliance with data retention requirements.', 'rwp-creator-suite' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Retention Grace Period', 'rwp-creator-suite' ); ?></th>
                        <td>
                            <select name="rwp_retention_grace_period">
                                <option value="0" <?php selected( get_option( 'rwp_retention_grace_period', '30' ), '0' ); ?>>
                                    <?php esc_html_e( 'No grace period', 'rwp-creator-suite' ); ?>
                                </option>
                                <option value="7" <?php selected( get_option( 'rwp_retention_grace_period', '30' ), '7' ); ?>>
                                    <?php esc_html_e( '7 days', 'rwp-creator-suite' ); ?>
                                </option>
                                <option value="30" <?php selected( get_option( 'rwp_retention_grace_period', '30' ), '30' ); ?>>
                                    <?php esc_html_e( '30 days', 'rwp-creator-suite' ); ?>
                                </option>
                                <option value="90" <?php selected( get_option( 'rwp_retention_grace_period', '30' ), '90' ); ?>>
                                    <?php esc_html_e( '90 days', 'rwp-creator-suite' ); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Grace period before permanently deleting data after retention period expires.', 'rwp-creator-suite' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e( 'Privacy by Design Settings', 'rwp-creator-suite' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Data Minimization', 'rwp-creator-suite' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rwp_enable_data_minimization" value="1" <?php checked( get_option( 'rwp_enable_data_minimization', 1 ) ); ?>>
                                <?php esc_html_e( 'Apply data minimization rules to all data collection', 'rwp-creator-suite' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Automatically filter collected data to include only necessary fields.', 'rwp-creator-suite' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Purpose Limitation', 'rwp-creator-suite' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rwp_enable_purpose_limitation" value="1" <?php checked( get_option( 'rwp_enable_purpose_limitation', 1 ) ); ?>>
                                <?php esc_html_e( 'Enforce purpose limitation for data processing', 'rwp-creator-suite' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Ensure data is only processed for stated purposes with proper consent.', 'rwp-creator-suite' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle admin settings form submissions.
     */
    public function handle_admin_settings() {
        // Register settings
        register_setting( 'rwp_gdpr_settings', 'rwp_enable_daily_compliance_checks' );
        register_setting( 'rwp_gdpr_settings', 'rwp_send_compliance_alerts' );
        register_setting( 'rwp_gdpr_settings', 'rwp_send_weekly_compliance_report' );
        register_setting( 'rwp_gdpr_settings', 'rwp_enable_automatic_data_cleanup' );
        register_setting( 'rwp_gdpr_settings', 'rwp_retention_grace_period' );
        register_setting( 'rwp_gdpr_settings', 'rwp_enable_data_minimization' );
        register_setting( 'rwp_gdpr_settings', 'rwp_enable_purpose_limitation' );

        register_setting( 'rwp_consent_settings', 'rwp_consent_renewal_period' );
        register_setting( 'rwp_consent_settings', 'rwp_show_consent_banner' );
    }

    /**
     * Handle compliance report export AJAX request.
     */
    public function handle_compliance_report_export() {
        check_ajax_referer( 'rwp_gdpr_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized access.', 'rwp-creator-suite' ) );
        }

        $daily_report = get_option( 'rwp_daily_compliance_report', array() );
        $weekly_report = get_option( 'rwp_weekly_compliance_report', array() );

        $export_data = array(
            'export_date' => current_time( 'c' ),
            'site_url' => home_url(),
            'daily_report' => $daily_report,
            'weekly_report' => $weekly_report,
        );

        $filename = 'gdpr-compliance-report-' . date( 'Y-m-d-H-i-s' ) . '.json';
        
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( wp_json_encode( $export_data ) ) );
        
        echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
        
        wp_die();
    }

    /**
     * Handle manual compliance check AJAX request.
     */
    public function handle_manual_compliance_check() {
        check_ajax_referer( 'rwp_gdpr_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized access.', 'rwp-creator-suite' ) );
        }

        if ( class_exists( 'RWP_Creator_Suite_Compliance_Monitor' ) ) {
            $monitor = new RWP_Creator_Suite_Compliance_Monitor();
            $report = $monitor->run_daily_compliance_check();

            wp_send_json_success( array(
                'message' => __( 'Compliance check completed successfully.', 'rwp-creator-suite' ),
                'report' => $report,
            ) );
        } else {
            wp_send_json_error( __( 'Compliance monitor not available.', 'rwp-creator-suite' ) );
        }
    }

    /**
     * Handle expired data cleanup AJAX request.
     */
    public function handle_expired_data_cleanup() {
        check_ajax_referer( 'rwp_gdpr_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized access.', 'rwp-creator-suite' ) );
        }

        if ( class_exists( 'RWP_Creator_Suite_Privacy_By_Design' ) ) {
            $privacy_manager = new RWP_Creator_Suite_Privacy_By_Design();
            $privacy_manager->perform_retention_cleanup();

            wp_send_json_success( array(
                'message' => __( 'Expired data cleanup completed successfully.', 'rwp-creator-suite' ),
            ) );
        } else {
            wp_send_json_error( __( 'Privacy manager not available.', 'rwp-creator-suite' ) );
        }
    }

    /**
     * Get compliance overview data.
     *
     * @return array Compliance overview.
     */
    private function get_compliance_overview() {
        $latest_report = get_option( 'rwp_daily_compliance_report', array() );

        if ( empty( $latest_report ) ) {
            return array(
                'score' => 0,
                'status' => 'unknown',
            );
        }

        return array(
            'score' => $latest_report['compliance_score'] ?? 0,
            'status' => $latest_report['status'] ?? 'unknown',
        );
    }

    /**
     * Get consent statistics.
     *
     * @return array Consent statistics.
     */
    private function get_consent_statistics() {
        if ( class_exists( 'RWP_Creator_Suite_Compliance_Monitor' ) ) {
            $monitor = new RWP_Creator_Suite_Compliance_Monitor();
            return $monitor->get_compliance_statistics();
        }

        // Fallback statistics
        return array(
            'total_users_with_consent' => 0,
            'total_analytics_events' => 0,
            'consent_rate' => 0,
            'data_retention_status' => 'unknown',
        );
    }

    /**
     * Render recent activity widget.
     */
    private function render_recent_activity() {
        $activities = array();

        // Get recent compliance check
        $last_check = get_option( 'rwp_last_compliance_check' );
        if ( $last_check ) {
            $activities[] = array(
                'type' => 'compliance_check',
                'description' => __( 'Compliance check completed', 'rwp-creator-suite' ),
                'timestamp' => $last_check,
            );
        }

        // Get recent data cleanup
        $last_cleanup = get_option( 'rwp_last_retention_cleanup' );
        if ( $last_cleanup ) {
            $activities[] = array(
                'type' => 'data_cleanup',
                'description' => __( 'Data retention cleanup performed', 'rwp-creator-suite' ),
                'timestamp' => strtotime( $last_cleanup['timestamp'] ?? '' ),
            );
        }

        // Sort by timestamp
        usort( $activities, function( $a, $b ) {
            return $b['timestamp'] - $a['timestamp'];
        } );

        if ( empty( $activities ) ) {
            echo '<p>' . esc_html__( 'No recent activity.', 'rwp-creator-suite' ) . '</p>';
            return;
        }

        echo '<ul class="activity-list">';
        foreach ( array_slice( $activities, 0, 5 ) as $activity ) {
            printf(
                '<li class="activity-item activity-%s">
                    <span class="activity-description">%s</span>
                    <span class="activity-time">%s</span>
                </li>',
                esc_attr( $activity['type'] ),
                esc_html( $activity['description'] ),
                esc_html( human_time_diff( $activity['timestamp'], current_time( 'timestamp' ) ) . ' ago' )
            );
        }
        echo '</ul>';
    }

    /**
     * Get recent data rights requests.
     *
     * @return array Recent requests.
     */
    private function get_recent_data_rights_requests() {
        // This would typically query a requests log
        // For now, return empty array as this feature would be implemented
        // based on actual request logging implementation
        return array();
    }
}