<?php
/**
 * Privacy by Design Implementation
 *
 * Implements privacy by design principles including data minimization, purpose limitation,
 * privacy by default, and proactive privacy protection measures.
 *
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Privacy_By_Design {

    /**
     * Data collection rules for different data types.
     *
     * @var array
     */
    private $collection_rules = array(
        'hashtag_data' => array(
            'collect' => array( 'hashtag_hash', 'platform', 'timestamp', 'usage_count' ),
            'exclude' => array( 'hashtag_text', 'user_content', 'personal_identifiers', 'ip_address' ),
            'retention_days' => 180, // 6 months
            'purpose' => 'hashtag_trend_analysis',
        ),
        'usage_patterns' => array(
            'collect' => array( 'feature_used', 'timestamp', 'platform', 'session_duration' ),
            'exclude' => array( 'user_id', 'ip_address', 'detailed_content', 'personal_data' ),
            'retention_days' => 365, // 12 months
            'purpose' => 'service_improvement',
        ),
        'performance_metrics' => array(
            'collect' => array( 'metric_type', 'value', 'context', 'timestamp' ),
            'exclude' => array( 'personal_data', 'identifying_information', 'content_details' ),
            'retention_days' => 365, // 12 months
            'purpose' => 'performance_optimization',
        ),
        'error_reports' => array(
            'collect' => array( 'error_type', 'error_context', 'timestamp', 'browser_type' ),
            'exclude' => array( 'user_credentials', 'personal_content', 'full_stack_trace' ),
            'retention_days' => 90, // 3 months
            'purpose' => 'debugging_improvement',
        ),
    );

    /**
     * Processing purposes with their allowed data types and restrictions.
     *
     * @var array
     */
    private $processing_purposes = array(
        'hashtag_trend_analysis' => array(
            'description' => 'Analyze hashtag and content trends for community insights',
            'data_types' => array( 'hashtag_usage', 'platform_selection', 'temporal_patterns' ),
            'retention_period' => '6 months',
            'sharing_allowed' => false,
            'automated_decision_making' => false,
            'required_consent' => 'hashtag_trends',
        ),
        'service_improvement' => array(
            'description' => 'Improve plugin features and user experience',
            'data_types' => array( 'feature_usage', 'error_logs', 'performance_metrics' ),
            'retention_period' => '12 months',
            'sharing_allowed' => false,
            'automated_decision_making' => false,
            'required_consent' => 'basic_analytics',
        ),
        'user_insights' => array(
            'description' => 'Provide personalized insights to users',
            'data_types' => array( 'usage_patterns', 'performance_data', 'preferences' ),
            'retention_period' => '24 months',
            'sharing_allowed' => false,
            'automated_decision_making' => true,
            'required_consent' => 'performance_benchmarking',
        ),
        'performance_optimization' => array(
            'description' => 'Optimize system performance and reliability',
            'data_types' => array( 'system_metrics', 'error_rates', 'response_times' ),
            'retention_period' => '12 months',
            'sharing_allowed' => false,
            'automated_decision_making' => false,
            'required_consent' => 'basic_analytics',
        ),
    );

    /**
     * Initialize privacy by design system.
     */
    public function init() {
        add_filter( 'rwp_analytics_data_collection', array( $this, 'apply_data_minimization' ), 10, 3 );
        add_filter( 'rwp_analytics_before_store', array( $this, 'validate_processing_purpose' ), 10, 2 );
        add_action( 'rwp_analytics_data_collected', array( $this, 'log_data_processing' ), 10, 3 );
        add_action( 'init', array( $this, 'schedule_data_retention_cleanup' ) );
        add_action( 'rwp_data_retention_cleanup', array( $this, 'perform_retention_cleanup' ) );
    }

    /**
     * Apply data minimization principles to data collection.
     *
     * @param array  $proposed_data Data proposed for collection.
     * @param string $data_type Type of data being collected.
     * @param string $context Collection context.
     * @return array Filtered data according to minimization rules.
     */
    public function apply_data_minimization( $proposed_data, $data_type, $context = 'general' ) {
        if ( ! isset( $this->collection_rules[ $data_type ] ) ) {
            // If no rules exist, apply strict minimization
            $this->log_minimization_event( 'unknown_data_type', array(
                'data_type' => $data_type,
                'context' => $context,
                'severity' => 'warning',
            ) );
            
            return $this->apply_default_minimization( $proposed_data );
        }

        $rules = $this->collection_rules[ $data_type ];
        $filtered_data = array();

        // Only include allowed fields
        foreach ( $proposed_data as $key => $value ) {
            if ( in_array( $key, $rules['collect'], true ) ) {
                if ( ! in_array( $key, $rules['exclude'], true ) ) {
                    $filtered_data[ $key ] = $this->sanitize_data_field( $key, $value );
                }
            }
        }

        // Add processing metadata
        $filtered_data['collection_purpose'] = $rules['purpose'];
        $filtered_data['retention_until'] = date( 'Y-m-d H:i:s', time() + ( $rules['retention_days'] * DAY_IN_SECONDS ) );
        $filtered_data['minimization_applied'] = true;

        // Log minimization applied
        $this->log_minimization_event( 'minimization_applied', array(
            'data_type' => $data_type,
            'original_fields' => count( $proposed_data ),
            'filtered_fields' => count( $filtered_data ),
            'context' => $context,
        ) );

        return $filtered_data;
    }

    /**
     * Validate that data processing aligns with stated purposes.
     *
     * @param array  $data Data to be processed.
     * @param string $purpose Intended processing purpose.
     * @return array|WP_Error Validated data or error if purpose invalid.
     */
    public function validate_processing_purpose( $data, $purpose ) {
        if ( ! isset( $this->processing_purposes[ $purpose ] ) ) {
            return new WP_Error(
                'invalid_processing_purpose',
                sprintf( 'Invalid processing purpose: %s', $purpose ),
                array( 'status' => 400 )
            );
        }

        $purpose_config = $this->processing_purposes[ $purpose ];
        
        // Check if user has consented to this purpose
        if ( ! $this->has_purpose_consent( $purpose_config['required_consent'] ) ) {
            return new WP_Error(
                'no_consent_for_purpose',
                sprintf( 'User has not consented to purpose: %s', $purpose ),
                array( 'status' => 403 )
            );
        }

        // Validate data types are allowed for this purpose
        $data_type = $data['data_type'] ?? 'unknown';
        if ( ! in_array( $data_type, $purpose_config['data_types'], true ) ) {
            return new WP_Error(
                'data_type_not_allowed',
                sprintf( 'Data type %s not allowed for purpose %s', $data_type, $purpose ),
                array( 'status' => 400 )
            );
        }

        // Add purpose limitation metadata
        $data['processing_purpose'] = $purpose;
        $data['purpose_validated'] = true;
        $data['automated_decision_making'] = $purpose_config['automated_decision_making'];

        return $data;
    }

    /**
     * Check if user has consented to a specific processing purpose.
     *
     * @param string $required_consent Required consent type.
     * @return bool Whether user has consented.
     */
    private function has_purpose_consent( $required_consent ) {
        if ( ! is_user_logged_in() ) {
            // For guests, check basic consent cookie
            return isset( $_COOKIE['rwp_analytics_consent'] ) && $_COOKIE['rwp_analytics_consent'] === 'yes';
        }

        $user_id = get_current_user_id();
        
        // Check granular consent if available
        if ( class_exists( 'RWP_Creator_Suite_Granular_Consent' ) ) {
            $granular_consent = new RWP_Creator_Suite_Granular_Consent();
            return $granular_consent->has_category_consent( $required_consent, $user_id );
        }

        // Fallback to general consent
        $consent_key = 'advanced_features_consent';
        $consent = get_user_meta( $user_id, $consent_key, true );
        return $consent == 1;
    }

    /**
     * Log data processing activities for audit trail.
     *
     * @param array  $data Processed data.
     * @param string $purpose Processing purpose.
     * @param string $legal_basis Legal basis for processing.
     */
    public function log_data_processing( $data, $purpose, $legal_basis = 'consent' ) {
        $processing_log = array(
            'timestamp' => current_time( 'mysql' ),
            'purpose' => $purpose,
            'legal_basis' => $legal_basis,
            'data_type' => $data['data_type'] ?? 'unknown',
            'user_id' => get_current_user_id(),
            'session_hash' => $this->get_session_hash(),
            'data_fields_processed' => array_keys( $data ),
            'retention_until' => $data['retention_until'] ?? null,
            'automated_decision_making' => $data['automated_decision_making'] ?? false,
        );

        // Store processing log
        update_option( 'rwp_processing_log_' . time(), $processing_log, false );

        // Clean up old processing logs (keep for 3 years for compliance)
        $this->cleanup_old_processing_logs();
    }

    /**
     * Apply default minimization when no specific rules exist.
     *
     * @param array $data Original data.
     * @return array Minimized data.
     */
    private function apply_default_minimization( $data ) {
        $allowed_fields = array( 'timestamp', 'event_type', 'platform' );
        $filtered_data = array();

        foreach ( $allowed_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $filtered_data[ $field ] = $this->sanitize_data_field( $field, $data[ $field ] );
            }
        }

        $filtered_data['minimization_applied'] = true;
        $filtered_data['default_rules_used'] = true;
        
        return $filtered_data;
    }

    /**
     * Sanitize data field based on type and security requirements.
     *
     * @param string $field_name Field name.
     * @param mixed  $value Field value.
     * @return mixed Sanitized value.
     */
    private function sanitize_data_field( $field_name, $value ) {
        switch ( $field_name ) {
            case 'hashtag_hash':
                return hash( 'sha256', sanitize_text_field( $value ) );

            case 'ip_address':
                // Should be excluded, but if included, anonymize
                return $this->anonymize_ip_address( $value );

            case 'timestamp':
                return sanitize_text_field( $value );

            case 'platform':
                $allowed_platforms = array( 'instagram', 'twitter', 'facebook', 'tiktok', 'linkedin' );
                return in_array( $value, $allowed_platforms, true ) ? $value : 'unknown';

            case 'feature_used':
                return sanitize_text_field( $value );

            case 'session_duration':
                return is_numeric( $value ) ? intval( $value ) : 0;

            case 'metric_type':
                return sanitize_text_field( $value );

            case 'error_type':
                return sanitize_text_field( $value );

            case 'browser_type':
                return sanitize_text_field( substr( $value, 0, 50 ) ); // Limit length

            default:
                // For unknown fields, apply generic sanitization
                if ( is_string( $value ) ) {
                    return sanitize_text_field( $value );
                } elseif ( is_numeric( $value ) ) {
                    return is_float( $value ) ? floatval( $value ) : intval( $value );
                } else {
                    return null; // Exclude complex data types by default
                }
        }
    }

    /**
     * Anonymize IP address for privacy protection.
     *
     * @param string $ip_address Original IP address.
     * @return string Anonymized IP address.
     */
    private function anonymize_ip_address( $ip_address ) {
        if ( filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            // IPv4: zero out last octet
            $parts = explode( '.', $ip_address );
            $parts[3] = '0';
            return implode( '.', $parts );
        } elseif ( filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            // IPv6: zero out last 80 bits
            $parts = explode( ':', $ip_address );
            for ( $i = 3; $i < count( $parts ); $i++ ) {
                $parts[ $i ] = '0000';
            }
            return implode( ':', $parts );
        }

        return hash( 'sha256', $ip_address ); // Fallback to hash if format unknown
    }

    /**
     * Get current session hash for tracking without identification.
     *
     * @return string Session hash.
     */
    private function get_session_hash() {
        $session_data = array(
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $this->get_client_ip(),
            'timestamp' => date( 'Y-m-d H' ), // Hourly granularity
        );

        return hash( 'sha256', serialize( $session_data ) . wp_salt() );
    }

    /**
     * Get client IP address with privacy consideration.
     *
     * @return string Client IP address.
     */
    private function get_client_ip() {
        $ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
        
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $this->anonymize_ip_address( $ip );
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Schedule automated data retention cleanup.
     */
    public function schedule_data_retention_cleanup() {
        if ( ! wp_next_scheduled( 'rwp_data_retention_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'rwp_data_retention_cleanup' );
        }
    }

    /**
     * Perform automated data retention cleanup.
     */
    public function perform_retention_cleanup() {
        global $wpdb;

        $cleanup_log = array(
            'timestamp' => current_time( 'mysql' ),
            'records_deleted' => array(),
            'total_deleted' => 0,
        );

        foreach ( $this->collection_rules as $data_type => $rules ) {
            $retention_days = $rules['retention_days'];
            $cutoff_date = date( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );

            // Delete expired analytics data
            $deleted = $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}rwp_anonymous_analytics 
                 WHERE event_type = %s AND timestamp < %s",
                $data_type,
                $cutoff_date
            ) );

            if ( $deleted > 0 ) {
                $cleanup_log['records_deleted'][ $data_type ] = $deleted;
                $cleanup_log['total_deleted'] += $deleted;
            }
        }

        // Clean up old processing logs (keep for 3 years)
        $this->cleanup_old_processing_logs();

        // Log retention cleanup
        RWP_Creator_Suite_Error_Logger::log(
            'Data Retention Cleanup Completed',
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            $cleanup_log
        );

        update_option( 'rwp_last_retention_cleanup', $cleanup_log );
    }

    /**
     * Clean up old processing logs.
     */
    private function cleanup_old_processing_logs() {
        global $wpdb;

        $three_years_ago = time() - ( 3 * YEAR_IN_SECONDS );
        
        $old_logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE 'rwp_processing_log_%' 
                 AND CAST(SUBSTRING(option_name, 20) AS UNSIGNED) < %d",
                $three_years_ago
            )
        );

        foreach ( $old_logs as $log ) {
            delete_option( $log->option_name );
        }
    }

    /**
     * Log minimization events for monitoring.
     *
     * @param string $event_type Type of minimization event.
     * @param array  $event_data Event data.
     */
    private function log_minimization_event( $event_type, $event_data ) {
        RWP_Creator_Suite_Error_Logger::log(
            "Data Minimization: {$event_type}",
            $event_data['severity'] === 'warning' ? RWP_Creator_Suite_Error_Logger::LOG_LEVEL_WARNING : RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            $event_data
        );
    }

    /**
     * Get data collection rules for a specific data type.
     *
     * @param string $data_type Data type.
     * @return array|null Collection rules or null if not found.
     */
    public function get_collection_rules( $data_type ) {
        return $this->collection_rules[ $data_type ] ?? null;
    }

    /**
     * Get processing purposes configuration.
     *
     * @return array Processing purposes.
     */
    public function get_processing_purposes() {
        return $this->processing_purposes;
    }

    /**
     * Validate data against collection rules.
     *
     * @param array  $data Data to validate.
     * @param string $data_type Data type.
     * @return bool Whether data passes validation.
     */
    public function validate_data_collection( $data, $data_type ) {
        $rules = $this->get_collection_rules( $data_type );
        
        if ( ! $rules ) {
            return false;
        }

        // Check that all fields are allowed
        foreach ( array_keys( $data ) as $field ) {
            if ( ! in_array( $field, $rules['collect'], true ) ) {
                return false;
            }
            
            if ( in_array( $field, $rules['exclude'], true ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get retention status for different data types.
     *
     * @return array Retention status report.
     */
    public function get_retention_status() {
        global $wpdb;

        $retention_status = array();

        foreach ( $this->collection_rules as $data_type => $rules ) {
            $retention_days = $rules['retention_days'];
            $cutoff_date = date( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );

            $expired_count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rwp_anonymous_analytics 
                 WHERE event_type = %s AND timestamp < %s",
                $data_type,
                $cutoff_date
            ) );

            $total_count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}rwp_anonymous_analytics 
                 WHERE event_type = %s",
                $data_type
            ) );

            $retention_status[ $data_type ] = array(
                'total_records' => intval( $total_count ),
                'expired_records' => intval( $expired_count ),
                'retention_days' => $retention_days,
                'cutoff_date' => $cutoff_date,
                'compliant' => $expired_count == 0,
            );
        }

        return $retention_status;
    }

    /**
     * Generate privacy impact assessment data.
     *
     * @return array Privacy impact assessment.
     */
    public function generate_privacy_impact_assessment() {
        $pia = array(
            'data_types_collected' => array_keys( $this->collection_rules ),
            'processing_purposes' => array_keys( $this->processing_purposes ),
            'data_minimization' => array(
                'enabled' => true,
                'rules_count' => count( $this->collection_rules ),
                'default_minimization' => true,
            ),
            'retention_policies' => array(),
            'privacy_risks' => array(),
            'mitigation_measures' => array(),
        );

        // Add retention policies
        foreach ( $this->collection_rules as $data_type => $rules ) {
            $pia['retention_policies'][ $data_type ] = array(
                'retention_days' => $rules['retention_days'],
                'purpose' => $rules['purpose'],
                'automated_cleanup' => true,
            );
        }

        // Identify privacy risks
        $pia['privacy_risks'] = array(
            'data_breach' => array(
                'likelihood' => 'low',
                'impact' => 'medium',
                'mitigation' => 'Data anonymization and encryption',
            ),
            'unauthorized_access' => array(
                'likelihood' => 'low',
                'impact' => 'high',
                'mitigation' => 'Access controls and audit logging',
            ),
            'data_retention_violation' => array(
                'likelihood' => 'very_low',
                'impact' => 'medium',
                'mitigation' => 'Automated retention cleanup',
            ),
        );

        // List mitigation measures
        $pia['mitigation_measures'] = array(
            'Data anonymization and pseudonymization',
            'Granular consent management',
            'Automated data retention compliance',
            'Regular compliance monitoring',
            'Privacy by design implementation',
            'Data subject rights automation',
        );

        return $pia;
    }
}