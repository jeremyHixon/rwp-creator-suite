<?php
/**
 * GDPR Compliance Monitor
 *
 * Provides automated compliance checking, monitoring, and audit trail functionality
 * to ensure ongoing GDPR compliance with real-time alerts and reporting.
 *
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Compliance_Monitor {

    /**
     * Initialize compliance monitoring.
     */
    public function init() {
        add_action( 'init', array( $this, 'schedule_compliance_checks' ) );
        add_action( 'rwp_daily_compliance_check', array( $this, 'run_daily_compliance_check' ) );
        add_action( 'rwp_weekly_compliance_report', array( $this, 'generate_weekly_compliance_report' ) );
        add_action( 'rest_api_init', array( $this, 'register_compliance_endpoints' ) );
        add_action( 'admin_init', array( $this, 'check_critical_compliance_issues' ) );
    }

    /**
     * Schedule recurring compliance checks.
     */
    public function schedule_compliance_checks() {
        if ( ! wp_next_scheduled( 'rwp_daily_compliance_check' ) ) {
            wp_schedule_event( time(), 'daily', 'rwp_daily_compliance_check' );
        }

        if ( ! wp_next_scheduled( 'rwp_weekly_compliance_report' ) ) {
            wp_schedule_event( time(), 'weekly', 'rwp_weekly_compliance_report' );
        }
    }

    /**
     * Register REST API endpoints for compliance monitoring.
     */
    public function register_compliance_endpoints() {
        register_rest_route( 'rwp-creator-suite/v1', '/compliance-status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_compliance_status' ),
            'permission_callback' => array( $this, 'check_admin_permissions' ),
        ) );

        register_rest_route( 'rwp-creator-suite/v1', '/compliance-report', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_compliance_report' ),
            'permission_callback' => array( $this, 'check_admin_permissions' ),
        ) );

        register_rest_route( 'rwp-creator-suite/v1', '/run-compliance-check', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'run_manual_compliance_check' ),
            'permission_callback' => array( $this, 'check_admin_permissions' ),
        ) );
    }

    /**
     * Run daily automated compliance check.
     *
     * @return array Compliance report.
     */
    public function run_daily_compliance_check() {
        $compliance_report = array(
            'timestamp' => current_time( 'mysql' ),
            'checks_performed' => array(),
            'issues_found' => array(),
            'compliance_score' => 100,
            'status' => 'compliant',
        );

        // Check data retention compliance
        $retention_check = $this->check_data_retention_compliance();
        $compliance_report['checks_performed'][] = 'data_retention';
        
        if ( ! $retention_check['compliant'] ) {
            $compliance_report['issues_found'][] = $retention_check;
            $compliance_report['compliance_score'] -= 20;
        }

        // Check consent validity
        $consent_check = $this->check_consent_validity();
        $compliance_report['checks_performed'][] = 'consent_validity';
        
        if ( ! $consent_check['compliant'] ) {
            $compliance_report['issues_found'][] = $consent_check;
            $compliance_report['compliance_score'] -= 30;
        }

        // Check data minimization
        $minimization_check = $this->check_data_minimization();
        $compliance_report['checks_performed'][] = 'data_minimization';
        
        if ( ! $minimization_check['compliant'] ) {
            $compliance_report['issues_found'][] = $minimization_check;
            $compliance_report['compliance_score'] -= 25;
        }

        // Check consent record integrity
        $consent_integrity_check = $this->check_consent_record_integrity();
        $compliance_report['checks_performed'][] = 'consent_integrity';
        
        if ( ! $consent_integrity_check['compliant'] ) {
            $compliance_report['issues_found'][] = $consent_integrity_check;
            $compliance_report['compliance_score'] -= 15;
        }

        // Check data processing lawfulness
        $lawfulness_check = $this->check_data_processing_lawfulness();
        $compliance_report['checks_performed'][] = 'processing_lawfulness';
        
        if ( ! $lawfulness_check['compliant'] ) {
            $compliance_report['issues_found'][] = $lawfulness_check;
            $compliance_report['compliance_score'] -= 10;
        }

        // Determine overall status
        if ( $compliance_report['compliance_score'] < 70 ) {
            $compliance_report['status'] = 'critical';
        } elseif ( $compliance_report['compliance_score'] < 85 ) {
            $compliance_report['status'] = 'warning';
        } elseif ( $compliance_report['compliance_score'] < 95 ) {
            $compliance_report['status'] = 'minor_issues';
        }

        // Store compliance report
        update_option( 'rwp_daily_compliance_report', $compliance_report );
        update_option( 'rwp_last_compliance_check', current_time( 'timestamp' ) );

        // Alert if critical issues found
        if ( $compliance_report['compliance_score'] < 80 ) {
            $this->send_compliance_alert( $compliance_report );
        }

        // Log compliance check
        $this->log_compliance_check( $compliance_report );

        return $compliance_report;
    }

    /**
     * Check data retention compliance.
     *
     * @return array Compliance check result.
     */
    private function check_data_retention_compliance() {
        global $wpdb;

        $issues = array();

        // Check for analytics data older than retention periods
        $expired_analytics = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count, MIN(timestamp) as oldest_record
             FROM {$wpdb->prefix}rwp_anonymous_analytics 
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY event_type"
        );

        if ( ! empty( $expired_analytics ) ) {
            $issues[] = array(
                'type' => 'expired_analytics_data',
                'description' => 'Analytics data found beyond retention period',
                'data' => $expired_analytics,
                'severity' => 'high',
            );
        }

        // Check for consent records older than legal requirement
        $expired_consents = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'rwp_gdpr_consent_record' 
             AND meta_value LIKE '%consent_date%' 
             AND STR_TO_DATE(
                 SUBSTRING_INDEX(SUBSTRING_INDEX(meta_value, '\"consent_date\":\"', -1), '\"', 1), 
                 '%Y-%m-%d %H:%i:%s'
             ) < DATE_SUB(NOW(), INTERVAL 3 YEAR)"
        );

        if ( $expired_consents > 0 ) {
            $issues[] = array(
                'type' => 'expired_consent_records',
                'description' => 'Consent records found beyond legal retention period',
                'count' => $expired_consents,
                'severity' => 'medium',
            );
        }

        return array(
            'compliant' => empty( $issues ),
            'check_type' => 'data_retention',
            'issues' => $issues,
            'action_required' => ! empty( $issues ) ? 'delete_expired_data' : null,
        );
    }

    /**
     * Check consent validity and completeness.
     *
     * @return array Compliance check result.
     */
    private function check_consent_validity() {
        global $wpdb;

        $issues = array();

        // Check for users with analytics data but no consent record
        $users_without_consent = $wpdb->get_results(
            "SELECT DISTINCT u.ID, u.user_email 
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}rwp_session_mapping sm ON u.ID = sm.user_id
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'rwp_gdpr_consent_record'
             WHERE um.meta_value IS NULL"
        );

        if ( ! empty( $users_without_consent ) ) {
            $issues[] = array(
                'type' => 'missing_consent_records',
                'description' => 'Users found with analytics data but no consent record',
                'count' => count( $users_without_consent ),
                'severity' => 'high',
            );
        }

        // Check for consent records without valid version
        $invalid_consents = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'rwp_gdpr_consent_record' 
             AND (meta_value NOT LIKE '%consent_version%' OR meta_value LIKE '%consent_version\":\"\"%')"
        );

        if ( $invalid_consents > 0 ) {
            $issues[] = array(
                'type' => 'invalid_consent_versions',
                'description' => 'Consent records found with missing or invalid versions',
                'count' => $invalid_consents,
                'severity' => 'medium',
            );
        }

        return array(
            'compliant' => empty( $issues ),
            'check_type' => 'consent_validity',
            'issues' => $issues,
            'action_required' => ! empty( $issues ) ? 'fix_consent_records' : null,
        );
    }

    /**
     * Check data minimization compliance.
     *
     * @return array Compliance check result.
     */
    private function check_data_minimization() {
        global $wpdb;

        $issues = array();

        // Check for analytics events with potentially excessive data
        $oversized_events = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count, AVG(LENGTH(event_data)) as avg_size
             FROM {$wpdb->prefix}rwp_anonymous_analytics 
             WHERE LENGTH(event_data) > 1000
             GROUP BY event_type"
        );

        if ( ! empty( $oversized_events ) ) {
            $issues[] = array(
                'type' => 'oversized_analytics_data',
                'description' => 'Analytics events found with potentially excessive data',
                'data' => $oversized_events,
                'severity' => 'low',
            );
        }

        // Check for direct user identification in anonymous data
        $potentially_identifying = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rwp_anonymous_analytics 
             WHERE event_data LIKE '%email%' 
             OR event_data LIKE '%user_login%' 
             OR event_data LIKE '%display_name%'"
        );

        if ( $potentially_identifying > 0 ) {
            $issues[] = array(
                'type' => 'potentially_identifying_data',
                'description' => 'Potentially identifying data found in anonymous analytics',
                'count' => $potentially_identifying,
                'severity' => 'high',
            );
        }

        return array(
            'compliant' => empty( $issues ),
            'check_type' => 'data_minimization',
            'issues' => $issues,
            'action_required' => ! empty( $issues ) ? 'review_data_collection' : null,
        );
    }

    /**
     * Check consent record integrity.
     *
     * @return array Compliance check result.
     */
    private function check_consent_record_integrity() {
        global $wpdb;

        $issues = array();

        // Check for consent records with missing required fields
        $incomplete_records = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'rwp_gdpr_consent_record' 
             AND (meta_value NOT LIKE '%consent_date%' 
                  OR meta_value NOT LIKE '%ip_address_hash%' 
                  OR meta_value NOT LIKE '%user_agent_hash%')"
        );

        if ( $incomplete_records > 0 ) {
            $issues[] = array(
                'type' => 'incomplete_consent_records',
                'description' => 'Consent records found with missing required fields',
                'count' => $incomplete_records,
                'severity' => 'medium',
            );
        }

        return array(
            'compliant' => empty( $issues ),
            'check_type' => 'consent_integrity',
            'issues' => $issues,
            'action_required' => ! empty( $issues ) ? 'repair_consent_records' : null,
        );
    }

    /**
     * Check data processing lawfulness.
     *
     * @return array Compliance check result.
     */
    private function check_data_processing_lawfulness() {
        global $wpdb;

        $issues = array();

        // Check for analytics data without corresponding consent
        $unlawful_processing = $wpdb->get_results(
            "SELECT COUNT(*) as count, a.event_type
             FROM {$wpdb->prefix}rwp_anonymous_analytics a
             INNER JOIN {$wpdb->prefix}rwp_session_mapping sm ON a.anonymous_session_hash = sm.anonymous_session_hash
             LEFT JOIN {$wpdb->usermeta} um ON sm.user_id = um.user_id AND um.meta_key LIKE 'rwp_consent_%'
             WHERE um.meta_value != '1'
             GROUP BY a.event_type"
        );

        if ( ! empty( $unlawful_processing ) ) {
            $total_unlawful = array_sum( array_column( $unlawful_processing, 'count' ) );
            if ( $total_unlawful > 0 ) {
                $issues[] = array(
                    'type' => 'unlawful_data_processing',
                    'description' => 'Analytics data found without corresponding user consent',
                    'count' => $total_unlawful,
                    'data' => $unlawful_processing,
                    'severity' => 'critical',
                );
            }
        }

        return array(
            'compliant' => empty( $issues ),
            'check_type' => 'processing_lawfulness',
            'issues' => $issues,
            'action_required' => ! empty( $issues ) ? 'cease_unlawful_processing' : null,
        );
    }

    /**
     * Send compliance alert for critical issues.
     *
     * @param array $compliance_report Compliance report.
     */
    private function send_compliance_alert( $compliance_report ) {
        $admin_email = get_option( 'admin_email' );
        
        if ( ! $admin_email ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Site name */
            __( 'GDPR Compliance Alert - %s', 'rwp-creator-suite' ),
            get_bloginfo( 'name' )
        );

        $message = sprintf(
            /* translators: %1$d: Compliance score, %2$s: Issues list */
            __( 'GDPR Compliance Alert

Your website\'s GDPR compliance score has dropped to %1$d%%.

Issues found:
%2$s

Please review and address these issues immediately to maintain compliance.

You can view the full compliance report in your WordPress admin dashboard under Creator Tools > Privacy.

This is an automated alert from the RWP Creator Suite GDPR compliance monitor.', 'rwp-creator-suite' ),
            $compliance_report['compliance_score'],
            $this->format_issues_for_email( $compliance_report['issues_found'] )
        );

        wp_mail( $admin_email, $subject, $message );

        // Log alert
        RWP_Creator_Suite_Error_Logger::log(
            'GDPR Compliance Alert Sent',
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_WARNING,
            array(
                'compliance_score' => $compliance_report['compliance_score'],
                'issues_count' => count( $compliance_report['issues_found'] ),
                'timestamp' => current_time( 'mysql' ),
            )
        );
    }

    /**
     * Format issues for email notification.
     *
     * @param array $issues Issues array.
     * @return string Formatted issues.
     */
    private function format_issues_for_email( $issues ) {
        $formatted = '';
        
        foreach ( $issues as $issue ) {
            $formatted .= "• {$issue['description']}\n";
            if ( isset( $issue['severity'] ) ) {
                $formatted .= "  Severity: {$issue['severity']}\n";
            }
            if ( isset( $issue['action_required'] ) ) {
                $formatted .= "  Action Required: {$issue['action_required']}\n";
            }
            $formatted .= "\n";
        }

        return $formatted;
    }

    /**
     * Log compliance check results.
     *
     * @param array $compliance_report Compliance report.
     */
    private function log_compliance_check( $compliance_report ) {
        RWP_Creator_Suite_Error_Logger::log(
            'GDPR Compliance Check Completed',
            $compliance_report['compliance_score'] < 80 ? RWP_Creator_Suite_Error_Logger::LOG_LEVEL_WARNING : RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            array(
                'compliance_score' => $compliance_report['compliance_score'],
                'status' => $compliance_report['status'],
                'checks_performed' => $compliance_report['checks_performed'],
                'issues_count' => count( $compliance_report['issues_found'] ),
                'timestamp' => $compliance_report['timestamp'],
            )
        );
    }

    /**
     * Generate weekly compliance report.
     */
    public function generate_weekly_compliance_report() {
        $weekly_report = array(
            'period_start' => date( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
            'period_end' => current_time( 'mysql' ),
            'daily_reports' => $this->get_weekly_daily_reports(),
            'trends' => $this->analyze_compliance_trends(),
            'recommendations' => $this->generate_compliance_recommendations(),
            'generated_at' => current_time( 'mysql' ),
        );

        update_option( 'rwp_weekly_compliance_report', $weekly_report );

        // Send weekly report to admin if configured
        if ( get_option( 'rwp_send_weekly_compliance_report', false ) ) {
            $this->send_weekly_compliance_report( $weekly_report );
        }
    }

    /**
     * Get daily reports for the past week.
     *
     * @return array Weekly daily reports.
     */
    private function get_weekly_daily_reports() {
        $reports = array();
        
        for ( $i = 0; $i < 7; $i++ ) {
            $date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $report = get_option( "rwp_daily_compliance_report_{$date}" );
            
            if ( $report ) {
                $reports[ $date ] = $report;
            }
        }

        return $reports;
    }

    /**
     * Analyze compliance trends over time.
     *
     * @return array Trend analysis.
     */
    private function analyze_compliance_trends() {
        $daily_reports = $this->get_weekly_daily_reports();
        $scores = array_column( $daily_reports, 'compliance_score' );
        
        if ( empty( $scores ) ) {
            return array(
                'trend' => 'no_data',
                'average_score' => 0,
                'improvement' => false,
            );
        }

        $average_score = array_sum( $scores ) / count( $scores );
        $first_score = reset( $scores );
        $last_score = end( $scores );
        
        $trend = 'stable';
        if ( $last_score > $first_score + 5 ) {
            $trend = 'improving';
        } elseif ( $last_score < $first_score - 5 ) {
            $trend = 'declining';
        }

        return array(
            'trend' => $trend,
            'average_score' => round( $average_score, 2 ),
            'improvement' => $last_score > $first_score,
            'score_change' => $last_score - $first_score,
        );
    }

    /**
     * Generate compliance recommendations.
     *
     * @return array Recommendations.
     */
    private function generate_compliance_recommendations() {
        $latest_report = get_option( 'rwp_daily_compliance_report', array() );
        $recommendations = array();

        if ( empty( $latest_report['issues_found'] ) ) {
            $recommendations[] = array(
                'type' => 'general',
                'priority' => 'low',
                'title' => __( 'Excellent Compliance', 'rwp-creator-suite' ),
                'description' => __( 'Your GDPR compliance is excellent. Continue regular monitoring to maintain this status.', 'rwp-creator-suite' ),
            );
            return $recommendations;
        }

        foreach ( $latest_report['issues_found'] as $issue ) {
            switch ( $issue['check_type'] ) {
                case 'data_retention':
                    $recommendations[] = array(
                        'type' => 'data_retention',
                        'priority' => 'high',
                        'title' => __( 'Implement Automated Data Cleanup', 'rwp-creator-suite' ),
                        'description' => __( 'Set up automated processes to delete data beyond retention periods.', 'rwp-creator-suite' ),
                        'action' => 'setup_automated_cleanup',
                    );
                    break;

                case 'consent_validity':
                    $recommendations[] = array(
                        'type' => 'consent',
                        'priority' => 'critical',
                        'title' => __( 'Fix Consent Records', 'rwp-creator-suite' ),
                        'description' => __( 'Review and fix invalid or missing consent records immediately.', 'rwp-creator-suite' ),
                        'action' => 'review_consent_records',
                    );
                    break;

                case 'data_minimization':
                    $recommendations[] = array(
                        'type' => 'data_minimization',
                        'priority' => 'medium',
                        'title' => __( 'Review Data Collection Practices', 'rwp-creator-suite' ),
                        'description' => __( 'Ensure only necessary data is being collected and stored.', 'rwp-creator-suite' ),
                        'action' => 'audit_data_collection',
                    );
                    break;
            }
        }

        return $recommendations;
    }

    /**
     * Check for critical compliance issues on admin pages.
     */
    public function check_critical_compliance_issues() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $latest_report = get_option( 'rwp_daily_compliance_report', array() );
        
        if ( ! empty( $latest_report ) && $latest_report['compliance_score'] < 70 ) {
            add_action( 'admin_notices', array( $this, 'display_critical_compliance_notice' ) );
        }
    }

    /**
     * Display critical compliance notice in admin.
     */
    public function display_critical_compliance_notice() {
        $latest_report = get_option( 'rwp_daily_compliance_report', array() );
        ?>
        <div class="notice notice-error is-dismissible">
            <h3><?php esc_html_e( 'GDPR Compliance Alert', 'rwp-creator-suite' ); ?></h3>
            <p>
                <?php printf(
                    /* translators: %d: Compliance score */
                    esc_html__( 'Your GDPR compliance score has dropped to %d%%. Immediate action is required to maintain legal compliance.', 'rwp-creator-suite' ),
                    esc_html( $latest_report['compliance_score'] )
                ); ?>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwp-creator-tools&tab=privacy' ) ); ?>" class="button-primary">
                    <?php esc_html_e( 'View Compliance Report', 'rwp-creator-suite' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * REST endpoint to get compliance status.
     *
     * @return WP_REST_Response
     */
    public function get_compliance_status() {
        $latest_report = get_option( 'rwp_daily_compliance_report', array() );
        $last_check = get_option( 'rwp_last_compliance_check', 0 );

        return new WP_REST_Response( array(
            'success' => true,
            'latest_report' => $latest_report,
            'last_check' => $last_check,
            'next_check' => wp_next_scheduled( 'rwp_daily_compliance_check' ),
        ), 200 );
    }

    /**
     * REST endpoint to get comprehensive compliance report.
     *
     * @return WP_REST_Response
     */
    public function get_compliance_report() {
        $daily_report = get_option( 'rwp_daily_compliance_report', array() );
        $weekly_report = get_option( 'rwp_weekly_compliance_report', array() );
        
        return new WP_REST_Response( array(
            'success' => true,
            'daily_report' => $daily_report,
            'weekly_report' => $weekly_report,
            'statistics' => $this->get_compliance_statistics(),
        ), 200 );
    }

    /**
     * REST endpoint to run manual compliance check.
     *
     * @return WP_REST_Response
     */
    public function run_manual_compliance_check() {
        $report = $this->run_daily_compliance_check();

        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Compliance check completed successfully.', 'rwp-creator-suite' ),
            'report' => $report,
        ), 200 );
    }

    /**
     * Get compliance statistics.
     *
     * @return array Statistics.
     */
    public function get_compliance_statistics() {
        global $wpdb;

        $stats = array(
            'total_users_with_consent' => 0,
            'total_analytics_events' => 0,
            'consent_rate' => 0,
            'data_retention_status' => 'compliant',
        );

        // Count users with any consent
        $users_with_consent = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'rwp_consent_%' AND meta_value = '1'"
        );
        $stats['total_users_with_consent'] = intval( $users_with_consent );

        // Count total analytics events
        $total_events = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rwp_anonymous_analytics"
        );
        $stats['total_analytics_events'] = intval( $total_events );

        // Calculate consent rate
        $total_users = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
        if ( $total_users > 0 ) {
            $stats['consent_rate'] = round( ( $users_with_consent / $total_users ) * 100, 2 );
        }

        return $stats;
    }

    /**
     * Check admin permissions for compliance endpoints.
     *
     * @return bool
     */
    public function check_admin_permissions() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Send weekly compliance report email.
     *
     * @param array $weekly_report Weekly report data.
     */
    private function send_weekly_compliance_report( $weekly_report ) {
        $admin_email = get_option( 'admin_email' );
        
        if ( ! $admin_email ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: Site name */
            __( 'Weekly GDPR Compliance Report - %s', 'rwp-creator-suite' ),
            get_bloginfo( 'name' )
        );

        $message = sprintf(
            /* translators: %1$s: Average score, %2$s: Trend, %3$s: Recommendations count */
            __( 'Weekly GDPR Compliance Report

Average Compliance Score: %1$s%%
Trend: %2$s
Recommendations: %3$d

For detailed information, please visit your WordPress admin dashboard.

This is your weekly automated compliance report from RWP Creator Suite.', 'rwp-creator-suite' ),
            $weekly_report['trends']['average_score'] ?? 'N/A',
            $weekly_report['trends']['trend'] ?? 'No data',
            count( $weekly_report['recommendations'] ?? array() )
        );

        wp_mail( $admin_email, $subject, $message );
    }
}