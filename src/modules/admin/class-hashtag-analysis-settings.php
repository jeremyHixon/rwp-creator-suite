<?php
/**
 * Hashtag Analysis Settings
 * 
 * Handles admin settings page for hashtag analysis configuration.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Hashtag_Analysis_Settings {

    /**
     * Settings page slug.
     */
    private const PAGE_SLUG = 'rwp-hashtag-analysis-settings';

    /**
     * Settings group.
     */
    private const SETTINGS_GROUP = 'rwp_hashtag_analysis_settings';

    /**
     * Initialize the settings page.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_test_hashtag_analysis_apis', array( $this, 'ajax_test_api_connections' ) );
    }

    /**
     * Add admin menu item.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Hashtag Analysis Settings', 'rwp-creator-suite' ),
            __( 'Hashtag Analysis', 'rwp-creator-suite' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        // TikTok API Settings
        register_setting( self::SETTINGS_GROUP, 'rwp_tiktok_app_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        register_setting( self::SETTINGS_GROUP, 'rwp_tiktok_app_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        register_setting( self::SETTINGS_GROUP, 'rwp_tiktok_access_token', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        // Apify Settings
        register_setting( self::SETTINGS_GROUP, 'rwp_apify_api_token', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        register_setting( self::SETTINGS_GROUP, 'rwp_apify_actor_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        // Data365 Settings
        register_setting( self::SETTINGS_GROUP, 'rwp_data365_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        register_setting( self::SETTINGS_GROUP, 'rwp_data365_client_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        // General Settings
        register_setting( self::SETTINGS_GROUP, 'rwp_hashtag_analysis_guest_limit', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 5,
        ));

        register_setting( self::SETTINGS_GROUP, 'rwp_hashtag_analysis_user_limit', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 20,
        ));

        register_setting( self::SETTINGS_GROUP, 'rwp_hashtag_analysis_cache_duration', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 3600, // 1 hour
        ));

        // Add settings sections
        add_settings_section(
            'tiktok_api_section',
            __( 'TikTok API Configuration', 'rwp-creator-suite' ),
            array( $this, 'render_tiktok_section_description' ),
            self::PAGE_SLUG
        );

        add_settings_section(
            'aggregators_section',
            __( 'Third-Party Aggregators', 'rwp-creator-suite' ),
            array( $this, 'render_aggregators_section_description' ),
            self::PAGE_SLUG
        );

        add_settings_section(
            'general_section',
            __( 'General Settings', 'rwp-creator-suite' ),
            array( $this, 'render_general_section_description' ),
            self::PAGE_SLUG
        );

        // Add settings fields
        $this->add_tiktok_fields();
        $this->add_aggregator_fields();
        $this->add_general_fields();
    }

    /**
     * Add TikTok API fields.
     */
    private function add_tiktok_fields() {
        add_settings_field(
            'rwp_tiktok_app_id',
            __( 'TikTok App ID', 'rwp-creator-suite' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'tiktok_api_section',
            array(
                'field_name' => 'rwp_tiktok_app_id',
                'description' => __( 'Your TikTok App ID from TikTok Developer Portal', 'rwp-creator-suite' ),
                'type' => 'password'
            )
        );

        add_settings_field(
            'rwp_tiktok_app_secret',
            __( 'TikTok App Secret', 'rwp-creator-suite' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'tiktok_api_section',
            array(
                'field_name' => 'rwp_tiktok_app_secret',
                'description' => __( 'Your TikTok App Secret', 'rwp-creator-suite' ),
                'type' => 'password'
            )
        );

        add_settings_field(
            'rwp_tiktok_access_token',
            __( 'TikTok Access Token', 'rwp-creator-suite' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'tiktok_api_section',
            array(
                'field_name' => 'rwp_tiktok_access_token',
                'description' => __( 'Your TikTok API Access Token', 'rwp-creator-suite' ),
                'type' => 'password'
            )
        );
    }

    /**
     * Add aggregator fields.
     */
    private function add_aggregator_fields() {
        add_settings_field(
            'rwp_apify_api_token',
            __( 'Apify API Token', 'rwp-creator-suite' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'aggregators_section',
            array(
                'field_name' => 'rwp_apify_api_token',
                'description' => __( 'Your Apify API Token for Instagram/Facebook data', 'rwp-creator-suite' ),
                'type' => 'password'
            )
        );

        add_settings_field(
            'rwp_apify_actor_id',
            __( 'Apify Actor ID', 'rwp-creator-suite' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'aggregators_section',
            array(
                'field_name' => 'rwp_apify_actor_id',
                'description' => __( 'Apify Actor ID for hashtag scraping', 'rwp-creator-suite' ),
                'type' => 'text'
            )
        );

        add_settings_field(
            'rwp_data365_api_key',
            __( 'Data365 API Key', 'rwp-creator-suite' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'aggregators_section',
            array(
                'field_name' => 'rwp_data365_api_key',
                'description' => __( 'Your Data365 API Key (alternative aggregator)', 'rwp-creator-suite' ),
                'type' => 'password'
            )
        );

        add_settings_field(
            'rwp_data365_client_id',
            __( 'Data365 Client ID', 'rwp-creator-suite' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'aggregators_section',
            array(
                'field_name' => 'rwp_data365_client_id',
                'description' => __( 'Your Data365 Client ID', 'rwp-creator-suite' ),
                'type' => 'text'
            )
        );
    }

    /**
     * Add general fields.
     */
    private function add_general_fields() {
        add_settings_field(
            'rwp_hashtag_analysis_guest_limit',
            __( 'Guest User Limit', 'rwp-creator-suite' ),
            array( $this, 'render_number_field' ),
            self::PAGE_SLUG,
            'general_section',
            array(
                'field_name' => 'rwp_hashtag_analysis_guest_limit',
                'description' => __( 'Maximum number of searches per day for non-logged-in users', 'rwp-creator-suite' ),
                'min' => 0,
                'max' => 100
            )
        );

        add_settings_field(
            'rwp_hashtag_analysis_user_limit',
            __( 'Logged-in User Limit', 'rwp-creator-suite' ),
            array( $this, 'render_number_field' ),
            self::PAGE_SLUG,
            'general_section',
            array(
                'field_name' => 'rwp_hashtag_analysis_user_limit',
                'description' => __( 'Maximum number of searches per day for logged-in users', 'rwp-creator-suite' ),
                'min' => 0,
                'max' => 1000
            )
        );

        add_settings_field(
            'rwp_hashtag_analysis_cache_duration',
            __( 'Cache Duration (seconds)', 'rwp-creator-suite' ),
            array( $this, 'render_number_field' ),
            self::PAGE_SLUG,
            'general_section',
            array(
                'field_name' => 'rwp_hashtag_analysis_cache_duration',
                'description' => __( 'How long to cache API responses (3600 = 1 hour)', 'rwp-creator-suite' ),
                'min' => 300,
                'max' => 86400
            )
        );
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'rwp-creator-suite' ) );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="rwp-settings-notice">
                <p><strong><?php _e( 'Important:', 'rwp-creator-suite' ); ?></strong> 
                <?php _e( 'API credentials are stored securely but will be visible to site administrators. Only configure APIs you intend to use.', 'rwp-creator-suite' ); ?></p>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields( self::SETTINGS_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                ?>
                
                <div class="rwp-settings-actions">
                    <?php submit_button( __( 'Save Settings', 'rwp-creator-suite' ), 'primary' ); ?>
                    <button type="button" class="button" id="test-api-connections">
                        <?php _e( 'Test API Connections', 'rwp-creator-suite' ); ?>
                    </button>
                </div>
            </form>

            <div id="api-test-results" style="display: none;"></div>
        </div>
        <?php
    }

    /**
     * Render section descriptions.
     */
    public function render_tiktok_section_description() {
        echo '<p>' . __( 'Configure your TikTok API credentials for direct access to TikTok hashtag data.', 'rwp-creator-suite' ) . '</p>';
        echo '<p><a href="https://developers.tiktok.com/" target="_blank">' . __( 'Get TikTok API credentials', 'rwp-creator-suite' ) . '</a></p>';
    }

    public function render_aggregators_section_description() {
        echo '<p>' . __( 'Configure third-party services for Instagram and Facebook hashtag data.', 'rwp-creator-suite' ) . '</p>';
        echo '<p>' . __( 'You only need to configure one aggregator service. Apify is recommended.', 'rwp-creator-suite' ) . '</p>';
    }

    public function render_general_section_description() {
        echo '<p>' . __( 'General settings for hashtag analysis functionality.', 'rwp-creator-suite' ) . '</p>';
    }

    /**
     * Render text field.
     */
    public function render_text_field( $args ) {
        $field_name = $args['field_name'];
        $value = get_option( $field_name, '' );
        $type = $args['type'] ?? 'text';
        $description = $args['description'] ?? '';
        
        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr( $type ),
            esc_attr( $field_name ),
            esc_attr( $field_name ),
            esc_attr( $value )
        );
        
        if ( $description ) {
            printf( '<p class="description">%s</p>', esc_html( $description ) );
        }
    }

    /**
     * Render number field.
     */
    public function render_number_field( $args ) {
        $field_name = $args['field_name'];
        $value = get_option( $field_name, $args['default'] ?? 0 );
        $min = $args['min'] ?? 0;
        $max = $args['max'] ?? 999999;
        $description = $args['description'] ?? '';
        
        printf(
            '<input type="number" id="%s" name="%s" value="%s" min="%d" max="%d" class="small-text" />',
            esc_attr( $field_name ),
            esc_attr( $field_name ),
            esc_attr( $value ),
            intval( $min ),
            intval( $max )
        );
        
        if ( $description ) {
            printf( '<p class="description">%s</p>', esc_html( $description ) );
        }
    }

    /**
     * Enqueue admin scripts.
     */
    public function enqueue_admin_scripts( $hook_suffix ) {
        if ( strpos( $hook_suffix, self::PAGE_SLUG ) === false ) {
            return;
        }

        wp_enqueue_script(
            'rwp-hashtag-analysis-admin',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/admin/hashtag-analysis-settings.js',
            array( 'jquery' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        wp_localize_script(
            'rwp-hashtag-analysis-admin',
            'rwpHashtagAnalysisAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'rwp_hashtag_analysis_admin' ),
                'strings' => array(
                    'testing' => __( 'Testing connections...', 'rwp-creator-suite' ),
                    'success' => __( 'All configured APIs are working correctly.', 'rwp-creator-suite' ),
                    'error' => __( 'Some API connections failed. Check your credentials.', 'rwp-creator-suite' ),
                )
            )
        );

        wp_enqueue_style(
            'rwp-hashtag-analysis-admin',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/css/admin/hashtag-analysis-settings.css',
            array(),
            RWP_CREATOR_SUITE_VERSION
        );
    }

    /**
     * Test API connections (AJAX handler).
     */
    public function ajax_test_api_connections() {
        check_ajax_referer( 'rwp_hashtag_analysis_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'rwp-creator-suite' ) );
        }

        $results = array();
        $details = array();

        // Test TikTok API - only if credentials are configured
        $tiktok_app_id = get_option( 'rwp_tiktok_app_id', '' );
        $tiktok_access_token = get_option( 'rwp_tiktok_access_token', '' );
        
        if ( ! empty( $tiktok_app_id ) && ! empty( $tiktok_access_token ) ) {
            $tiktok_service = new RWP_Creator_Suite_TikTok_Service();
            $tiktok_test = $this->test_tiktok_connection( $tiktok_service );
            
            $results['tiktok'] = $tiktok_test['success'];
            $details['tiktok'] = $tiktok_test['message'];
        } else {
            $results['tiktok'] = false;
            $details['tiktok'] = 'No TikTok credentials configured';
        }

        // Test Aggregator services - only if credentials are configured
        $apify_token = get_option( 'rwp_apify_api_token', '' );
        $data365_key = get_option( 'rwp_data365_api_key', '' );
        
        if ( ! empty( $apify_token ) || ! empty( $data365_key ) ) {
            $aggregator_service = new RWP_Creator_Suite_Aggregator_Service();
            $aggregator_test = $this->test_aggregator_connection( $aggregator_service );
            
            $results['aggregators'] = $aggregator_test['success'];
            $details['aggregators'] = $aggregator_test['message'];
        } else {
            $results['aggregators'] = false;
            $details['aggregators'] = 'No aggregator credentials configured';
        }

        wp_send_json_success( array(
            'results' => $results,
            'details' => $details,
            'has_any_connection' => array_filter( $results )
        ) );
    }

    /**
     * Test TikTok API connection with sandbox and production support.
     *
     * @param RWP_Creator_Suite_TikTok_Service $service The TikTok service.
     * @return array Test result with success status and message.
     */
    private function test_tiktok_connection( $service ) {
        try {
            // Make a simple API call to verify credentials
            $test_result = $service->search_hashtag( 'test', 1 );
            
            if ( is_wp_error( $test_result ) ) {
                $error_code = $test_result->get_error_code();
                
                // Check for authentication errors vs other errors
                if ( in_array( $error_code, array( 'no_access_token', 'invalid_credentials', 'unauthorized' ) ) ) {
                    return array(
                        'success' => false,
                        'message' => 'Invalid TikTok credentials: ' . $test_result->get_error_message()
                    );
                } elseif ( $error_code === 'rate_limit_exceeded' ) {
                    // Rate limit means credentials work, but we're hitting limits
                    return array(
                        'success' => true,
                        'message' => 'TikTok API connected (rate limit reached - credentials valid)'
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => 'TikTok API error: ' . $test_result->get_error_message()
                    );
                }
            }
            
            // Analyze the response to determine environment and validity
            $connection_status = $this->analyze_tiktok_response( $test_result );
            
            return array(
                'success' => $connection_status['is_valid'],
                'message' => $connection_status['message']
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'TikTok connection failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Analyze TikTok API response to determine environment and validity.
     *
     * @param array $response The API response.
     * @return array Analysis result with validity and message.
     */
    private function analyze_tiktok_response( $response ) {
        if ( ! is_array( $response ) || empty( $response ) ) {
            return array(
                'is_valid' => false,
                'message' => 'TikTok API returned empty or invalid response'
            );
        }
        
        $first_result = reset( $response );
        
        // Check for obvious mock data patterns
        if ( isset( $first_result['id'] ) && strpos( $first_result['id'], 'tiktok_test_' ) === 0 ) {
            return array(
                'is_valid' => false,
                'message' => 'TikTok API returning mock data - check credentials'
            );
        }
        
        // Detect sandbox environment patterns
        $is_sandbox = $this->detect_tiktok_sandbox( $first_result, $response );
        
        if ( $is_sandbox ) {
            return array(
                'is_valid' => true,
                'message' => 'TikTok Sandbox connected successfully (test environment)'
            );
        }
        
        // Check for valid production data structure
        if ( $this->is_valid_tiktok_response_structure( $first_result ) ) {
            return array(
                'is_valid' => true,
                'message' => 'TikTok Production API connected successfully'
            );
        }
        
        // Response doesn't match expected patterns
        return array(
            'is_valid' => false,
            'message' => 'TikTok API response format unexpected - verify credentials'
        );
    }
    
    /**
     * Detect if TikTok response is from sandbox environment.
     *
     * @param array $first_result First result from API response.
     * @param array $full_response Full API response.
     * @return bool True if sandbox detected.
     */
    private function detect_tiktok_sandbox( $first_result, $full_response ) {
        // Common sandbox indicators
        $sandbox_indicators = array(
            // Sandbox often uses specific test user IDs
            'author' => array( 'test_user', 'sandbox_user', 'tiktok_test' ),
            // Sandbox data often has predictable titles
            'title' => array( 'Test Video', 'Sample Content', 'Sandbox Video' ),
            // Sandbox IDs often follow patterns
            'id' => array( 'sandbox_', 'test_', 'demo_' ),
            // Sandbox URLs often point to test domains
            'url' => array( 'sandbox.tiktok.com', 'test-api.tiktok.com' )
        );
        
        foreach ( $sandbox_indicators as $field => $patterns ) {
            if ( isset( $first_result[ $field ] ) ) {
                $value = strtolower( $first_result[ $field ] );
                foreach ( $patterns as $pattern ) {
                    if ( strpos( $value, strtolower( $pattern ) ) !== false ) {
                        return true;
                    }
                }
            }
        }
        
        // Check if all results have identical or very similar metrics (common in sandbox)
        if ( count( $full_response ) > 1 ) {
            $first_likes = isset( $first_result['metrics']['likes'] ) ? $first_result['metrics']['likes'] : null;
            if ( $first_likes !== null ) {
                $identical_metrics = 0;
                foreach ( $full_response as $result ) {
                    if ( isset( $result['metrics']['likes'] ) && $result['metrics']['likes'] === $first_likes ) {
                        $identical_metrics++;
                    }
                }
                // If most results have identical metrics, likely sandbox
                if ( $identical_metrics >= count( $full_response ) * 0.8 ) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Validate TikTok response structure.
     *
     * @param array $result Single result from API response.
     * @return bool True if structure is valid.
     */
    private function is_valid_tiktok_response_structure( $result ) {
        // Check for required fields in a valid TikTok post
        $required_fields = array( 'id', 'title', 'metrics' );
        
        foreach ( $required_fields as $field ) {
            if ( ! isset( $result[ $field ] ) ) {
                return false;
            }
        }
        
        // Check metrics structure
        if ( ! is_array( $result['metrics'] ) ) {
            return false;
        }
        
        // Valid TikTok metrics should have at least some engagement data
        $metric_fields = array( 'likes', 'comments', 'shares', 'views' );
        $has_metrics = false;
        
        foreach ( $metric_fields as $metric ) {
            if ( isset( $result['metrics'][ $metric ] ) && is_numeric( $result['metrics'][ $metric ] ) ) {
                $has_metrics = true;
                break;
            }
        }
        
        return $has_metrics;
    }

    /**
     * Test aggregator service connection.
     *
     * @param RWP_Creator_Suite_Aggregator_Service $service The aggregator service.
     * @return array Test result with success status and message.
     */
    private function test_aggregator_connection( $service ) {
        try {
            $test_result = $service->search_hashtag( 'test', 'instagram', 1 );
            
            if ( is_wp_error( $test_result ) ) {
                $error_code = $test_result->get_error_code();
                
                if ( in_array( $error_code, array( 'invalid_provider', 'no_access_token', 'unauthorized' ) ) ) {
                    return array(
                        'success' => false,
                        'message' => 'Invalid aggregator credentials: ' . $test_result->get_error_message()
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => 'Aggregator API error: ' . $test_result->get_error_message()
                    );
                }
            }
            
            // Check if we got mock data
            if ( is_array( $test_result ) && ! empty( $test_result ) ) {
                $first_result = reset( $test_result );
                if ( isset( $first_result['id'] ) && strpos( $first_result['id'], 'instagram_test_' ) === 0 ) {
                    return array(
                        'success' => false,
                        'message' => 'Aggregator returning mock data - check credentials'
                    );
                }
            }
            
            return array(
                'success' => true,
                'message' => 'Aggregator service connected successfully'
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'Aggregator connection failed: ' . $e->getMessage()
            );
        }
    }
}