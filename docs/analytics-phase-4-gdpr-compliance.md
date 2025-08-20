# Phase 4: GDPR Compliance

## Overview

Phase 4 ensures full compliance with the General Data Protection Regulation (GDPR) and other privacy laws. This phase is critical for legal operation in the EU and builds trust with users worldwide through comprehensive privacy protections and transparent data handling practices. The plugin has an existing consent module (src/modules/analytics/class-consent-manager.php) being referenced and used throughout the plugin. Refer to that as your doing the work.

## GDPR Compliance Framework

### Core GDPR Principles Implementation

1. **Lawfulness, Fairness, and Transparency**
2. **Purpose Limitation**
3. **Data Minimization**
4. **Accuracy**
5. **Storage Limitation**
6. **Integrity and Confidentiality**
7. **Accountability**

## Legal Basis for Processing

### Consent (Article 6(1)(a))

**Primary Legal Basis:** Explicit user consent for analytics data collection

```php
class RWP_GDPR_Consent_Manager {
    const CONSENT_VERSION = '2.0';
    
    public function record_consent($user_id, $consent_details) {
        $consent_record = [
            'user_id' => $user_id,
            'consent_version' => self::CONSENT_VERSION,
            'consent_date' => current_time('mysql'),
            'consent_method' => $consent_details['method'], // 'checkbox', 'banner', 'settings'
            'consent_granular' => $consent_details['granular_options'],
            'ip_address_hash' => hash('sha256', $this->get_client_ip() . wp_salt()),
            'user_agent_hash' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] . wp_salt()),
            'page_url' => $consent_details['page_url'],
            'consent_text_shown' => $consent_details['consent_text'],
            'withdrawal_method' => null,
            'withdrawal_date' => null
        ];
        
        // Store consent with audit trail
        update_user_meta($user_id, 'rwp_gdpr_consent_record', $consent_record);
        
        // Log for compliance audit
        $this->log_consent_event('consent_granted', $consent_record);
        
        return $consent_record;
    }
    
    public function withdraw_consent($user_id, $withdrawal_method = 'user_request') {
        $existing_consent = get_user_meta($user_id, 'rwp_gdpr_consent_record', true);
        
        if ($existing_consent) {
            $existing_consent['withdrawal_method'] = $withdrawal_method;
            $existing_consent['withdrawal_date'] = current_time('mysql');
            
            update_user_meta($user_id, 'rwp_gdpr_consent_record', $existing_consent);
            
            // Immediately stop data collection
            update_user_meta($user_id, 'advanced_features_consent', 0);
            
            // Schedule data deletion
            wp_schedule_single_event(
                time() + (30 * DAY_IN_SECONDS), // 30-day grace period
                'rwp_delete_user_analytics_data',
                [$user_id]
            );
            
            $this->log_consent_event('consent_withdrawn', $existing_consent);
        }
    }
}
```

## Granular Consent Management

### Multi-Level Consent Options

```php
class RWP_Granular_Consent {
    private $consent_categories = [
        'basic_analytics' => [
            'name' => 'Basic Usage Analytics',
            'description' => 'Help us improve the plugin by sharing basic usage patterns',
            'required' => false,
            'data_types' => ['feature_usage', 'error_reporting', 'performance_metrics'],
            'retention_period' => '12 months',
            'legal_basis' => 'consent'
        ],
        'hashtag_trends' => [
            'name' => 'Hashtag Trend Analysis',
            'description' => 'Share anonymized hashtag usage to discover trending tags',
            'required' => false,
            'data_types' => ['hashtag_frequency', 'platform_correlation'],
            'retention_period' => '6 months',
            'legal_basis' => 'consent',
            'benefits' => ['Monthly trend reports', 'Hashtag recommendations']
        ],
        'performance_benchmarking' => [
            'name' => 'Performance Benchmarking',
            'description' => 'Compare your content performance with anonymous community averages',
            'required' => false,
            'data_types' => ['engagement_metrics', 'posting_patterns'],
            'retention_period' => '12 months',
            'legal_basis' => 'consent',
            'benefits' => ['Performance insights', 'Optimization suggestions']
        ],
        'product_improvement' => [
            'name' => 'Product Development',
            'description' => 'Help us build better features based on usage patterns',
            'required' => false,
            'data_types' => ['feature_adoption', 'user_journeys'],
            'retention_period' => '24 months',
            'legal_basis' => 'consent',
            'benefits' => ['Early access to new features', 'Personalized recommendations']
        ]
    ];
    
    public function render_consent_form($context = 'first_time') {
        ?>
        <div class="rwp-gdpr-consent-form" data-context="<?php echo esc_attr($context); ?>">
            <h3><?php _e('Help Us Improve Your Experience', 'rwp-creator-suite'); ?></h3>
            
            <p class="consent-intro">
                <?php _e('We\'d like to collect some anonymous data to make our tools better for everyone. You have full control over what you share.', 'rwp-creator-suite'); ?>
            </p>
            
            <?php foreach ($this->consent_categories as $category_id => $category): ?>
                <div class="consent-category">
                    <label class="consent-option">
                        <input type="checkbox" 
                               name="rwp_consent[<?php echo esc_attr($category_id); ?>]" 
                               value="1"
                               data-category="<?php echo esc_attr($category_id); ?>">
                        
                        <div class="consent-details">
                            <strong><?php echo esc_html($category['name']); ?></strong>
                            <p><?php echo esc_html($category['description']); ?></p>
                            
                            <?php if (!empty($category['benefits'])): ?>
                                <div class="consent-benefits">
                                    <strong><?php _e('You get:', 'rwp-creator-suite'); ?></strong>
                                    <ul>
                                        <?php foreach ($category['benefits'] as $benefit): ?>
                                            <li><?php echo esc_html($benefit); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <details class="consent-technical-details">
                                <summary><?php _e('Technical Details', 'rwp-creator-suite'); ?></summary>
                                <p><strong><?php _e('Data Types:', 'rwp-creator-suite'); ?></strong> 
                                   <?php echo esc_html(implode(', ', $category['data_types'])); ?></p>
                                <p><strong><?php _e('Retention:', 'rwp-creator-suite'); ?></strong> 
                                   <?php echo esc_html($category['retention_period']); ?></p>
                                <p><strong><?php _e('Legal Basis:', 'rwp-creator-suite'); ?></strong> 
                                   <?php echo esc_html($category['legal_basis']); ?></p>
                            </details>
                        </div>
                    </label>
                </div>
            <?php endforeach; ?>
            
            <div class="consent-actions">
                <button type="button" class="button-primary" id="save-consent-preferences">
                    <?php _e('Save My Preferences', 'rwp-creator-suite'); ?>
                </button>
                
                <button type="button" class="button-secondary" id="reject-all-consent">
                    <?php _e('No Thanks', 'rwp-creator-suite'); ?>
                </button>
            </div>
            
            <div class="consent-footer">
                <p><small>
                    <?php printf(
                        __('You can change these preferences anytime in your <a href="%s">Privacy Settings</a>. Read our <a href="%s">Privacy Policy</a> for more details.', 'rwp-creator-suite'),
                        admin_url('admin.php?page=rwp-privacy-settings'),
                        home_url('/privacy-policy')
                    ); ?>
                </small></p>
            </div>
        </div>
        <?php
    }
}
```

## Data Subject Rights Implementation

### 1. Right of Access (Article 15)

```php
class RWP_Data_Access_Handler {
    public function generate_data_export($user_id) {
        $export_data = [
            'user_info' => $this->get_user_basic_info($user_id),
            'consent_history' => $this->get_consent_history($user_id),
            'analytics_data' => $this->get_user_analytics_data($user_id),
            'preferences' => $this->get_user_preferences($user_id),
            'usage_statistics' => $this->get_usage_statistics($user_id),
            'data_processing_log' => $this->get_processing_log($user_id)
        ];
        
        // Generate secure download link
        $export_file = $this->create_encrypted_export($export_data, $user_id);
        
        // Send email with download link (expires in 48 hours)
        $this->send_data_export_email($user_id, $export_file);
        
        // Log the access request
        $this->log_data_access_request($user_id);
        
        return $export_file;
    }
    
    private function get_user_analytics_data($user_id) {
        global $wpdb;
        
        // Get anonymized analytics data that can be linked to user
        $analytics_data = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, event_data, timestamp 
             FROM {$wpdb->prefix}rwp_anonymous_analytics 
             WHERE anonymous_session_hash IN (
                 SELECT DISTINCT anonymous_session_hash 
                 FROM {$wpdb->prefix}rwp_session_mapping 
                 WHERE user_id = %d
             )
             ORDER BY timestamp DESC",
            $user_id
        ));
        
        return $analytics_data;
    }
}
```

### 2. Right to Rectification (Article 16)

```php
class RWP_Data_Rectification {
    public function update_user_data($user_id, $corrections) {
        $updated_fields = [];
        
        foreach ($corrections as $field => $new_value) {
            switch ($field) {
                case 'preferences':
                    $this->update_user_preferences($user_id, $new_value);
                    $updated_fields[] = 'preferences';
                    break;
                    
                case 'consent_preferences':
                    $this->update_consent_preferences($user_id, $new_value);
                    $updated_fields[] = 'consent_preferences';
                    break;
                    
                // Add other rectifiable fields
            }
        }
        
        // Log rectification request
        $this->log_rectification_request($user_id, $updated_fields);
        
        return $updated_fields;
    }
}
```

### 3. Right to Erasure (Article 17)

```php
class RWP_Data_Erasure_Handler {
    public function process_erasure_request($user_id, $erasure_scope = 'all') {
        $erasure_log = [
            'user_id' => $user_id,
            'request_date' => current_time('mysql'),
            'erasure_scope' => $erasure_scope,
            'data_deleted' => []
        ];
        
        switch ($erasure_scope) {
            case 'all':
                $erasure_log['data_deleted'] = $this->delete_all_user_data($user_id);
                break;
                
            case 'analytics_only':
                $erasure_log['data_deleted'] = $this->delete_analytics_data($user_id);
                break;
                
            case 'preferences_only':
                $erasure_log['data_deleted'] = $this->delete_preferences_data($user_id);
                break;
        }
        
        // Log erasure for compliance audit
        $this->log_erasure_request($erasure_log);
        
        // Confirm erasure to user
        $this->send_erasure_confirmation_email($user_id, $erasure_log);
        
        return $erasure_log;
    }
    
    private function delete_all_user_data($user_id) {
        global $wpdb;
        
        $deleted_data = [];
        
        // Delete user meta data
        $meta_keys = [
            'rwp_caption_favorites',
            'rwp_caption_preferences',
            'rwp_ai_total_usage',
            'rwp_gdpr_consent_record',
            'advanced_features_consent'
        ];
        
        foreach ($meta_keys as $meta_key) {
            if (delete_user_meta($user_id, $meta_key)) {
                $deleted_data[] = $meta_key;
            }
        }
        
        // Delete analytics data (anonymized but linked)
        $deleted_analytics = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}rwp_anonymous_analytics 
             WHERE anonymous_session_hash IN (
                 SELECT anonymous_session_hash 
                 FROM {$wpdb->prefix}rwp_session_mapping 
                 WHERE user_id = %d
             )",
            $user_id
        ));
        
        if ($deleted_analytics) {
            $deleted_data[] = "analytics_records_{$deleted_analytics}";
        }
        
        // Delete session mappings
        $wpdb->delete(
            $wpdb->prefix . 'rwp_session_mapping',
            ['user_id' => $user_id],
            ['%d']
        );
        
        return $deleted_data;
    }
}
```

### 4. Right to Data Portability (Article 20)

```php
class RWP_Data_Portability {
    public function generate_portable_export($user_id, $format = 'json') {
        $portable_data = [
            'user_preferences' => $this->get_structured_preferences($user_id),
            'content_templates' => $this->get_user_templates($user_id),
            'favorites' => $this->get_user_favorites($user_id),
            'usage_patterns' => $this->get_usage_patterns($user_id),
            'export_metadata' => [
                'format_version' => '1.0',
                'export_date' => current_time('c'),
                'data_controller' => get_bloginfo('name'),
                'export_scope' => 'user_data_portable'
            ]
        ];
        
        switch ($format) {
            case 'json':
                return $this->export_as_json($portable_data);
            case 'csv':
                return $this->export_as_csv($portable_data);
            case 'xml':
                return $this->export_as_xml($portable_data);
            default:
                return $this->export_as_json($portable_data);
        }
    }
}
```

## Privacy by Design Implementation

### 1. Data Minimization

```php
class RWP_Data_Minimization {
    private $collection_rules = [
        'hashtag_data' => [
            'collect' => ['hashtag_hash', 'platform', 'timestamp'],
            'exclude' => ['hashtag_text', 'user_content', 'personal_identifiers']
        ],
        'usage_patterns' => [
            'collect' => ['feature_used', 'timestamp', 'platform'],
            'exclude' => ['user_id', 'ip_address', 'detailed_content']
        ],
        'performance_metrics' => [
            'collect' => ['metric_type', 'value', 'context'],
            'exclude' => ['personal_data', 'identifying_information']
        ]
    ];
    
    public function validate_data_collection($data_type, $proposed_data) {
        $rules = $this->collection_rules[$data_type] ?? [];
        
        if (empty($rules)) {
            throw new Exception("No collection rules defined for {$data_type}");
        }
        
        $filtered_data = [];
        
        foreach ($proposed_data as $key => $value) {
            if (in_array($key, $rules['collect'])) {
                if (!in_array($key, $rules['exclude'])) {
                    $filtered_data[$key] = $this->sanitize_data_field($key, $value);
                }
            }
        }
        
        return $filtered_data;
    }
}
```

### 2. Purpose Limitation

```php
class RWP_Purpose_Limitation {
    private $processing_purposes = [
        'trend_analysis' => [
            'description' => 'Analyze hashtag and content trends',
            'data_types' => ['hashtag_usage', 'platform_selection'],
            'retention_period' => '6 months',
            'sharing_allowed' => false
        ],
        'service_improvement' => [
            'description' => 'Improve plugin features and user experience',
            'data_types' => ['feature_usage', 'error_logs', 'performance_metrics'],
            'retention_period' => '12 months',
            'sharing_allowed' => false
        ],
        'user_insights' => [
            'description' => 'Provide personalized insights to users',
            'data_types' => ['usage_patterns', 'performance_data'],
            'retention_period' => '24 months',
            'sharing_allowed' => false
        ]
    ];
    
    public function validate_processing_purpose($purpose, $data_type) {
        if (!isset($this->processing_purposes[$purpose])) {
            throw new Exception("Invalid processing purpose: {$purpose}");
        }
        
        $purpose_config = $this->processing_purposes[$purpose];
        
        if (!in_array($data_type, $purpose_config['data_types'])) {
            throw new Exception("Data type {$data_type} not allowed for purpose {$purpose}");
        }
        
        return true;
    }
}
```

## Compliance Monitoring and Auditing

### Automated Compliance Checks

```php
class RWP_Compliance_Monitor {
    public function run_daily_compliance_check() {
        $compliance_report = [
            'timestamp' => current_time('mysql'),
            'checks_performed' => [],
            'issues_found' => [],
            'compliance_score' => 100
        ];
        
        // Check data retention compliance
        $retention_check = $this->check_data_retention_compliance();
        $compliance_report['checks_performed'][] = 'data_retention';
        
        if (!$retention_check['compliant']) {
            $compliance_report['issues_found'][] = $retention_check;
            $compliance_report['compliance_score'] -= 20;
        }
        
        // Check consent validity
        $consent_check = $this->check_consent_validity();
        $compliance_report['checks_performed'][] = 'consent_validity';
        
        if (!$consent_check['compliant']) {
            $compliance_report['issues_found'][] = $consent_check;
            $compliance_report['compliance_score'] -= 30;
        }
        
        // Check data minimization
        $minimization_check = $this->check_data_minimization();
        $compliance_report['checks_performed'][] = 'data_minimization';
        
        if (!$minimization_check['compliant']) {
            $compliance_report['issues_found'][] = $minimization_check;
            $compliance_report['compliance_score'] -= 25;
        }
        
        // Store compliance report
        update_option('rwp_daily_compliance_report', $compliance_report);
        
        // Alert if critical issues found
        if ($compliance_report['compliance_score'] < 80) {
            $this->send_compliance_alert($compliance_report);
        }
        
        return $compliance_report;
    }
    
    private function check_data_retention_compliance() {
        global $wpdb;
        
        // Check for data older than retention periods
        $expired_data = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count 
             FROM {$wpdb->prefix}rwp_anonymous_analytics 
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY event_type"
        );
        
        return [
            'compliant' => empty($expired_data),
            'issue_type' => 'data_retention',
            'details' => $expired_data,
            'action_required' => !empty($expired_data) ? 'delete_expired_data' : null
        ];
    }
}
```

## Implementation Checklist

### Legal Foundation
- [ ] Updated Privacy Policy with specific analytics disclosures
- [ ] Cookie Policy covering analytics cookies
- [ ] Terms of Service updated for data processing
- [ ] Data Processing Agreement (if using third-party services)
- [ ] Data Protection Impact Assessment (DPIA) completed

### Technical Implementation
- [ ] Granular consent management system
- [ ] Data subject rights automation (access, rectification, erasure, portability)
- [ ] Data retention automation and monitoring
- [ ] Compliance auditing and reporting system
- [ ] Secure data export and import capabilities

### Documentation and Training
- [ ] GDPR compliance documentation
- [ ] Staff training on data protection procedures
- [ ] Incident response procedures
- [ ] Data breach notification procedures
- [ ] Regular compliance review processes

### Monitoring and Maintenance
- [ ] Automated compliance checking
- [ ] Regular privacy impact assessments
- [ ] Consent renewal procedures
- [ ] Data processing audit trails
- [ ] Performance monitoring of privacy features

## Success Metrics for Phase 4

### Compliance Metrics
1. **Legal Compliance**: 100% GDPR compliance validation
2. **Consent Management**: 95%+ consent capture accuracy
3. **Data Subject Rights**: <72 hour response time for requests
4. **Data Retention**: 100% automated compliance with retention policies
5. **Audit Trail**: Complete audit logs for all data processing

### User Trust Metrics
1. **Consent Rate**: Maintain 65%+ opt-in rate
2. **Privacy Satisfaction**: 4.5/5 rating for privacy controls
3. **Data Request Volume**: <2% users request data deletion
4. **Transparency Score**: 90%+ users understand data usage
5. **Trust Indicators**: Zero privacy-related complaints

## Risk Management

### Privacy Risks
- **Risk**: GDPR non-compliance penalties
- **Mitigation**: Automated compliance monitoring and legal review

### Technical Risks
- **Risk**: Data breach or unauthorized access
- **Mitigation**: Encryption, access controls, and incident response procedures

### Operational Risks
- **Risk**: Manual compliance processes causing errors
- **Mitigation**: Automation of all compliance processes where possible

---

*Phase 4 ensures that the analytics system operates within full legal compliance while maintaining user trust through transparent and respectful data handling practices.*