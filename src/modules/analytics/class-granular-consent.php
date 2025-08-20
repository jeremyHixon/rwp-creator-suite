<?php
/**
 * Granular Consent Manager
 *
 * Handles granular user consent for different analytics categories with full GDPR compliance.
 * Provides detailed consent options with clear purposes and benefits.
 *
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.7.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Granular_Consent {

    /**
     * Consent version for tracking changes.
     */
    const CONSENT_VERSION = '2.0';

    /**
     * Consent categories with their configurations.
     *
     * @var array
     */
    private $consent_categories = array(
        'basic_analytics' => array(
            'name' => 'Basic Usage Analytics',
            'description' => 'Help us improve the plugin by sharing basic usage patterns',
            'required' => false,
            'data_types' => array( 'feature_usage', 'error_reporting', 'performance_metrics' ),
            'retention_period' => '12 months',
            'legal_basis' => 'consent',
            'benefits' => array()
        ),
        'hashtag_trends' => array(
            'name' => 'Hashtag Trend Analysis',
            'description' => 'Share anonymized hashtag usage to discover trending tags',
            'required' => false,
            'data_types' => array( 'hashtag_frequency', 'platform_correlation' ),
            'retention_period' => '6 months',
            'legal_basis' => 'consent',
            'benefits' => array( 'Monthly trend reports', 'Hashtag recommendations' )
        ),
        'performance_benchmarking' => array(
            'name' => 'Performance Benchmarking',
            'description' => 'Compare your content performance with anonymous community averages',
            'required' => false,
            'data_types' => array( 'engagement_metrics', 'posting_patterns' ),
            'retention_period' => '12 months',
            'legal_basis' => 'consent',
            'benefits' => array( 'Performance insights', 'Optimization suggestions' )
        ),
        'product_improvement' => array(
            'name' => 'Product Development',
            'description' => 'Help us build better features based on usage patterns',
            'required' => false,
            'data_types' => array( 'feature_adoption', 'user_journeys' ),
            'retention_period' => '24 months',
            'legal_basis' => 'consent',
            'benefits' => array( 'Early access to new features', 'Personalized recommendations' )
        )
    );

    /**
     * Initialize the granular consent system.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_granular_consent_endpoints' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_granular_consent_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_granular_consent_assets' ) );
        add_action( 'wp_footer', array( $this, 'display_consent_banner' ) );
        add_shortcode( 'rwp_consent_banner', array( $this, 'render_consent_banner_shortcode' ) );
    }

    /**
     * Register REST API endpoints for granular consent.
     */
    public function register_granular_consent_endpoints() {
        register_rest_route( 'rwp-creator-suite/v1', '/granular-consent', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_granular_consent_status' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'set_granular_consent_status' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'consent_categories' => array(
                        'required' => true,
                        'type'     => 'object',
                    ),
                ),
            ),
        ) );

        register_rest_route( 'rwp-creator-suite/v1', '/consent-categories', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_consent_categories' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Enqueue assets for granular consent interface.
     */
    public function enqueue_granular_consent_assets() {
        wp_enqueue_script(
            'rwp-granular-consent',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/granular-consent.js',
            array( 'wp-api-fetch' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        wp_enqueue_style(
            'rwp-granular-consent',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/css/granular-consent.css',
            array(),
            RWP_CREATOR_SUITE_VERSION
        );

        wp_localize_script( 'rwp-granular-consent', 'rwpGranularConsent', array(
            'apiUrl' => rest_url( 'rwp-creator-suite/v1/' ),
            'nonce'  => wp_create_nonce( 'wp_rest' ),
            'strings' => array(
                'title' => __( 'Your Privacy Choices', 'rwp-creator-suite' ),
                'intro' => __( 'We believe in transparency and control. Choose exactly what data you\'re comfortable sharing.', 'rwp-creator-suite' ),
                'saveButton' => __( 'Save My Preferences', 'rwp-creator-suite' ),
                'rejectAllButton' => __( 'Reject All', 'rwp-creator-suite' ),
                'acceptAllButton' => __( 'Accept All', 'rwp-creator-suite' ),
                'technicalDetailsLabel' => __( 'Technical Details', 'rwp-creator-suite' ),
                'dataTypesLabel' => __( 'Data Types:', 'rwp-creator-suite' ),
                'retentionLabel' => __( 'Retention:', 'rwp-creator-suite' ),
                'legalBasisLabel' => __( 'Legal Basis:', 'rwp-creator-suite' ),
                'benefitsLabel' => __( 'You get:', 'rwp-creator-suite' ),
                'privacyPolicyText' => __( 'Read our Privacy Policy for more details.', 'rwp-creator-suite' ),
                'settingsLinkText' => __( 'You can change these preferences anytime in your Privacy Settings.', 'rwp-creator-suite' ),
            ),
            'privacyPolicyUrl' => $this->get_privacy_policy_url(),
            'privacySettingsUrl' => admin_url( 'admin.php?page=rwp-creator-tools&tab=privacy' ),
        ) );
    }

    /**
     * Render granular consent form.
     *
     * @param string $context Context where form is displayed.
     */
    public function render_consent_form( $context = 'first_time' ) {
        $user_consents = $this->get_user_granular_consents();
        ?>
        <div class="rwp-gdpr-consent-form" data-context="<?php echo esc_attr( $context ); ?>">
            <h3><?php esc_html_e( 'Your Privacy Choices', 'rwp-creator-suite' ); ?></h3>
            
            <p class="consent-intro">
                <?php esc_html_e( 'We believe in transparency and control. Choose exactly what data you\'re comfortable sharing to help us improve your content creation experience.', 'rwp-creator-suite' ); ?>
            </p>
            
            <?php foreach ( $this->consent_categories as $category_id => $category ) : ?>
                <div class="consent-category">
                    <label class="consent-option">
                        <input type="checkbox" 
                               name="rwp_consent[<?php echo esc_attr( $category_id ); ?>]" 
                               value="1"
                               data-category="<?php echo esc_attr( $category_id ); ?>"
                               <?php checked( isset( $user_consents[ $category_id ] ) && $user_consents[ $category_id ], true ); ?>>
                        
                        <div class="consent-details">
                            <strong><?php echo esc_html( $category['name'] ); ?></strong>
                            <p><?php echo esc_html( $category['description'] ); ?></p>
                            
                            <?php if ( ! empty( $category['benefits'] ) ) : ?>
                                <div class="consent-benefits">
                                    <strong><?php esc_html_e( 'You get:', 'rwp-creator-suite' ); ?></strong>
                                    <ul>
                                        <?php foreach ( $category['benefits'] as $benefit ) : ?>
                                            <li><?php echo esc_html( $benefit ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <details class="consent-technical-details">
                                <summary><?php esc_html_e( 'Technical Details', 'rwp-creator-suite' ); ?></summary>
                                <p><strong><?php esc_html_e( 'Data Types:', 'rwp-creator-suite' ); ?></strong> 
                                   <?php echo esc_html( implode( ', ', $category['data_types'] ) ); ?></p>
                                <p><strong><?php esc_html_e( 'Retention:', 'rwp-creator-suite' ); ?></strong> 
                                   <?php echo esc_html( $category['retention_period'] ); ?></p>
                                <p><strong><?php esc_html_e( 'Legal Basis:', 'rwp-creator-suite' ); ?></strong> 
                                   <?php echo esc_html( $category['legal_basis'] ); ?></p>
                            </details>
                        </div>
                    </label>
                </div>
            <?php endforeach; ?>
            
            <div class="consent-actions">
                <button type="button" class="button-primary" id="save-consent-preferences">
                    <?php esc_html_e( 'Save My Preferences', 'rwp-creator-suite' ); ?>
                </button>
                
                <button type="button" class="button-secondary" id="accept-all-consent">
                    <?php esc_html_e( 'Accept All', 'rwp-creator-suite' ); ?>
                </button>
                
                <button type="button" class="button-secondary" id="reject-all-consent">
                    <?php esc_html_e( 'Reject All', 'rwp-creator-suite' ); ?>
                </button>
            </div>
            
            <div class="consent-footer">
                <p><small>
                    <?php printf(
                        /* translators: %1$s: Privacy Settings URL, %2$s: Privacy Policy URL */
                        __( 'You can change these preferences anytime in your <a href="%1$s">Privacy Settings</a>. Read our <a href="%2$s">Privacy Policy</a> for more details.', 'rwp-creator-suite' ),
                        admin_url( 'admin.php?page=rwp-creator-tools&tab=privacy' ),
                        $this->get_privacy_policy_url()
                    ); ?>
                </small></p>
            </div>
        </div>
        <?php
    }

    /**
     * Get consent categories configuration.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_consent_categories( $request ) {
        return new WP_REST_Response( array(
            'success' => true,
            'categories' => $this->consent_categories,
            'version' => self::CONSENT_VERSION,
        ), 200 );
    }

    /**
     * Get granular consent status.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_granular_consent_status( $request ) {
        $user_consents = $this->get_user_granular_consents();
        $consent_record = $this->get_user_consent_record();

        return new WP_REST_Response( array(
            'success' => true,
            'consents' => $user_consents,
            'consent_record' => $consent_record,
            'has_any_consent' => ! empty( array_filter( $user_consents ) ),
        ), 200 );
    }

    /**
     * Set granular consent status.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function set_granular_consent_status( $request ) {
        $consent_categories = $request->get_param( 'consent_categories' );
        
        if ( ! is_array( $consent_categories ) ) {
            return new WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid consent data provided.', 'rwp-creator-suite' ),
            ), 400 );
        }

        // Record the consent with full audit trail
        $consent_record = $this->record_consent( $consent_categories );
        
        // Update analytics system
        $this->update_analytics_consent( $consent_categories );

        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Your privacy preferences have been saved.', 'rwp-creator-suite' ),
            'consent_record' => $consent_record,
        ), 200 );
    }

    /**
     * Record consent with full GDPR audit trail.
     *
     * @param array $consent_categories Consent choices.
     * @return array Consent record.
     */
    public function record_consent( $consent_categories ) {
        $user_id = get_current_user_id();
        
        $consent_record = array(
            'user_id' => $user_id,
            'consent_version' => self::CONSENT_VERSION,
            'consent_date' => current_time( 'mysql' ),
            'consent_method' => 'granular_form',
            'consent_granular' => $consent_categories,
            'ip_address_hash' => hash( 'sha256', $this->get_client_ip() . wp_salt() ),
            'user_agent_hash' => hash( 'sha256', ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) . wp_salt() ),
            'page_url' => $this->get_current_page_url(),
            'consent_text_version' => self::CONSENT_VERSION,
            'withdrawal_method' => null,
            'withdrawal_date' => null
        );

        // Store consent record
        if ( $user_id ) {
            update_user_meta( $user_id, 'rwp_gdpr_consent_record', $consent_record );
            
            // Store individual category consents
            foreach ( $consent_categories as $category => $consented ) {
                update_user_meta( $user_id, "rwp_consent_{$category}", $consented ? 1 : 0 );
            }
        }

        // Log consent for audit trail
        $this->log_consent_event( 'consent_granted', $consent_record );

        return $consent_record;
    }

    /**
     * Withdraw consent for all or specific categories.
     *
     * @param int    $user_id User ID.
     * @param string $withdrawal_method Method of withdrawal.
     * @param array  $categories Optional specific categories to withdraw.
     */
    public function withdraw_consent( $user_id, $withdrawal_method = 'user_request', $categories = array() ) {
        $existing_consent = get_user_meta( $user_id, 'rwp_gdpr_consent_record', true );
        
        if ( $existing_consent ) {
            $existing_consent['withdrawal_method'] = $withdrawal_method;
            $existing_consent['withdrawal_date'] = current_time( 'mysql' );
            
            if ( empty( $categories ) ) {
                // Withdraw all consent
                foreach ( $this->consent_categories as $category_id => $category ) {
                    update_user_meta( $user_id, "rwp_consent_{$category_id}", 0 );
                }
                
                // Schedule data deletion
                wp_schedule_single_event(
                    time() + ( 30 * DAY_IN_SECONDS ), // 30-day grace period
                    'rwp_delete_user_analytics_data',
                    array( $user_id )
                );
            } else {
                // Withdraw specific categories
                foreach ( $categories as $category_id ) {
                    update_user_meta( $user_id, "rwp_consent_{$category_id}", 0 );
                }
            }
            
            update_user_meta( $user_id, 'rwp_gdpr_consent_record', $existing_consent );
            $this->log_consent_event( 'consent_withdrawn', $existing_consent );
        }
    }

    /**
     * Get user's granular consents.
     *
     * @param int $user_id Optional user ID.
     * @return array
     */
    public function get_user_granular_consents( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $consents = array();
        
        foreach ( $this->consent_categories as $category_id => $category ) {
            $consent = get_user_meta( $user_id, "rwp_consent_{$category_id}", true );
            $consents[ $category_id ] = $consent == 1;
        }

        return $consents;
    }

    /**
     * Get user's full consent record.
     *
     * @param int $user_id Optional user ID.
     * @return array|null
     */
    public function get_user_consent_record( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        return get_user_meta( $user_id, 'rwp_gdpr_consent_record', true );
    }

    /**
     * Update analytics system with new consent preferences.
     *
     * @param array $consent_categories Consent choices.
     */
    private function update_analytics_consent( $consent_categories ) {
        $analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
        
        // Set overall consent based on any category being true
        $has_any_consent = ! empty( array_filter( $consent_categories ) );
        $analytics->set_user_consent( $has_any_consent );
        
        // Set granular consents
        foreach ( $consent_categories as $category => $consented ) {
            $analytics->set_category_consent( $category, $consented );
        }
    }

    /**
     * Log consent event for audit trail.
     *
     * @param string $event_type Type of consent event.
     * @param array  $consent_data Consent data.
     */
    private function log_consent_event( $event_type, $consent_data ) {
        RWP_Creator_Suite_Error_Logger::log(
            "GDPR Consent Event: {$event_type}",
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            array(
                'event_type' => $event_type,
                'user_id' => $consent_data['user_id'] ?? 0,
                'consent_version' => $consent_data['consent_version'] ?? '',
                'ip_hash' => $consent_data['ip_address_hash'] ?? '',
                'user_agent_hash' => $consent_data['user_agent_hash'] ?? '',
                'timestamp' => current_time( 'mysql' )
            )
        );
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
        
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Get current page URL.
     *
     * @return string
     */
    private function get_current_page_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        
        return $protocol . $host . $request_uri;
    }

    /**
     * Get privacy policy URL.
     *
     * @return string
     */
    private function get_privacy_policy_url() {
        $privacy_page = get_option( 'wp_page_for_privacy_policy' );
        if ( $privacy_page ) {
            return get_permalink( $privacy_page );
        }
        
        return admin_url( 'admin.php?page=rwp-creator-tools&tab=privacy' );
    }

    /**
     * Check if user has consented to a specific category.
     *
     * @param string $category_id Category ID.
     * @param int    $user_id Optional user ID.
     * @return bool
     */
    public function has_category_consent( $category_id, $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! isset( $this->consent_categories[ $category_id ] ) ) {
            return false;
        }

        $consent = get_user_meta( $user_id, "rwp_consent_{$category_id}", true );
        return $consent == 1;
    }

    /**
     * Get consent statistics for reporting.
     *
     * @return array
     */
    public function get_consent_statistics() {
        global $wpdb;
        
        $stats = array(
            'total_users_with_consent' => 0,
            'categories' => array()
        );

        foreach ( $this->consent_categories as $category_id => $category ) {
            $consented_users = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '1'",
                    "rwp_consent_{$category_id}"
                )
            );
            
            $stats['categories'][ $category_id ] = array(
                'name' => $category['name'],
                'consented_users' => intval( $consented_users ),
            );
        }

        // Count users with any consent
        $consent_keys = array_map( function( $id ) {
            return "rwp_consent_{$id}";
        }, array_keys( $this->consent_categories ) );
        
        $placeholders = implode( ',', array_fill( 0, count( $consent_keys ), '%s' ) );
        
        $total_users = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key IN ({$placeholders}) AND meta_value = '1'",
                ...$consent_keys
            )
        );

        $stats['total_users_with_consent'] = intval( $total_users );

        return $stats;
    }

    /**
     * Display consent banner on frontend if needed.
     */
    public function display_consent_banner() {
        // Don't show on admin pages
        if ( is_admin() ) {
            return;
        }

        // Don't show if user already has consent recorded recently
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $last_consent = get_user_meta( $user_id, 'rwp_last_consent_update', true );
            
            // If consent was given within the last 3 months, don't show banner
            if ( $last_consent && ( time() - $last_consent ) < ( 3 * MONTH_IN_SECONDS ) ) {
                return;
            }
        } else {
            // For non-logged users, check cookie
            if ( isset( $_COOKIE['rwp_consent_given'] ) ) {
                return;
            }
        }

        echo $this->render_consent_banner();
    }

    /**
     * Render consent banner shortcode.
     */
    public function render_consent_banner_shortcode( $atts ) {
        return $this->render_consent_banner();
    }

    /**
     * Render the consent banner HTML.
     */
    private function render_consent_banner() {
        ob_start();
        ?>
        <div id="rwp-gdpr-consent-banner" class="rwp-gdpr-consent-form" style="position: fixed; bottom: 20px; right: 20px; z-index: 999999; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border-radius: 8px; max-width: 400px; width: 90vw;">
            <div style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="margin: 0; font-size: 16px;"><?php esc_html_e( 'Privacy Preferences', 'rwp-creator-suite' ); ?></h4>
                    <button type="button" id="close-consent-banner" style="background: none; border: none; font-size: 18px; cursor: pointer;">&times;</button>
                </div>
                
                <p style="margin: 0 0 15px 0; font-size: 14px; color: #666;">
                    <?php esc_html_e( 'We use cookies and analytics to improve your experience. Choose your preferences below.', 'rwp-creator-suite' ); ?>
                </p>

                <div id="consent-details" style="display: none; margin-bottom: 15px; max-height: 200px; overflow-y: auto;">
                    <?php foreach ( $this->consent_categories as $category_id => $category ) : ?>
                    <div class="consent-category" style="margin: 8px 0; padding: 8px; border: 1px solid #e0e0e0; border-radius: 4px;">
                        <label style="display: flex; align-items: flex-start; cursor: pointer;">
                            <input type="checkbox" 
                                   id="rwp_consent_<?php echo esc_attr( $category_id ); ?>"
                                   name="rwp_consent[<?php echo esc_attr( $category_id ); ?>]"
                                   data-category="<?php echo esc_attr( $category_id ); ?>"
                                   class="consent-checkbox"
                                   style="margin: 2px 8px 0 0;">
                            <div>
                                <strong style="font-size: 13px;"><?php echo esc_html( $category['name'] ); ?></strong>
                                <div style="font-size: 12px; color: #777; margin-top: 2px;"><?php echo esc_html( $category['description'] ); ?></div>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="consent-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" id="accept-all-consent" class="button button-primary" style="flex: 1; min-width: 80px; padding: 8px 12px; font-size: 13px;">
                        <?php esc_html_e( 'Accept All', 'rwp-creator-suite' ); ?>
                    </button>
                    <button type="button" id="customize-consent" class="button" style="flex: 1; min-width: 80px; padding: 8px 12px; font-size: 13px;">
                        <?php esc_html_e( 'Customize', 'rwp-creator-suite' ); ?>
                    </button>
                    <button type="button" id="reject-all-consent" class="button" style="flex: 1; min-width: 80px; padding: 8px 12px; font-size: 13px;">
                        <?php esc_html_e( 'Reject All', 'rwp-creator-suite' ); ?>
                    </button>
                </div>
                
                <div id="detailed-actions" style="display: none; margin-top: 10px;">
                    <button type="button" id="save-consent-preferences" class="button button-primary" style="width: 100%; padding: 8px 12px; font-size: 13px;">
                        <?php esc_html_e( 'Save Preferences', 'rwp-creator-suite' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <style>
        #rwp-gdpr-consent-banner {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.4;
            border: 1px solid #e0e0e0;
        }
        #rwp-gdpr-consent-banner .button {
            border: 1px solid #ccc;
            background: #f7f7f7;
            color: #333;
            cursor: pointer;
            border-radius: 4px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        #rwp-gdpr-consent-banner .button:hover {
            background: #e7e7e7;
        }
        #rwp-gdpr-consent-banner .button-primary {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        #rwp-gdpr-consent-banner .button-primary:hover {
            background: #005a87;
        }
        @media (max-width: 480px) {
            #rwp-gdpr-consent-banner {
                bottom: 10px !important;
                right: 10px !important;
                left: 10px !important;
                max-width: none !important;
                width: auto !important;
            }
        }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var banner = document.getElementById('rwp-gdpr-consent-banner');
            if (!banner) return;

            var consentDetails = document.getElementById('consent-details');
            var detailedActions = document.getElementById('detailed-actions');
            var customizeBtn = document.getElementById('customize-consent');
            var closeBtn = document.getElementById('close-consent-banner');

            function hideConsentBanner() {
                banner.style.display = 'none';
                // Set cookie for non-logged users
                if (!document.body.classList.contains('logged-in')) {
                    document.cookie = 'rwp_consent_given=1; expires=' + new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toUTCString() + '; path=/';
                }
            }

            function showCustomization() {
                consentDetails.style.display = 'block';
                detailedActions.style.display = 'block';
                customizeBtn.style.display = 'none';
            }

            customizeBtn.addEventListener('click', showCustomization);
            
            closeBtn.addEventListener('click', hideConsentBanner);

            document.getElementById('accept-all-consent').addEventListener('click', function() {
                var checkboxes = banner.querySelectorAll('.consent-checkbox');
                checkboxes.forEach(function(cb) { cb.checked = true; });
                hideConsentBanner();
            });

            document.getElementById('reject-all-consent').addEventListener('click', function() {
                var checkboxes = banner.querySelectorAll('.consent-checkbox');
                checkboxes.forEach(function(cb) { cb.checked = false; });
                hideConsentBanner();
            });

            document.getElementById('save-consent-preferences').addEventListener('click', function() {
                hideConsentBanner();
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}