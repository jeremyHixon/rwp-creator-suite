<?php
/**
 * Analytics Consent Manager
 * 
 * Handles user consent for analytics data collection with GDPR compliance.
 * Provides UI components and APIs for managing consent preferences.
 * 
 * @package    RWP_Creator_Suite
 * @subpackage Analytics
 * @since      1.6.0
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Consent_Manager {

    /**
     * Single instance of the class.
     *
     * @var RWP_Creator_Suite_Consent_Manager
     */
    private static $instance = null;

    /**
     * Consent cookie name.
     */
    const CONSENT_COOKIE = 'rwp_analytics_consent';

    /**
     * Consent shown cookie name.
     */
    const CONSENT_SHOWN_COOKIE = 'rwp_consent_shown';

    /**
     * Get single instance of the class.
     *
     * @return RWP_Creator_Suite_Consent_Manager
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
        // Constructor intentionally empty
    }

    /**
     * Initialize consent manager.
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_consent_endpoints' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_consent_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_consent_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_consent_banner' ) );
        add_action( 'admin_footer', array( $this, 'render_consent_banner' ) );
        
        // Add consent management to user profile
        add_action( 'show_user_profile', array( $this, 'add_user_consent_field' ) );
        add_action( 'edit_user_profile', array( $this, 'add_user_consent_field' ) );
        add_action( 'personal_options_update', array( $this, 'save_user_consent_field' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_user_consent_field' ) );
    }

    /**
     * Register REST API endpoints for consent management.
     */
    public function register_consent_endpoints() {
        register_rest_route( 'rwp-creator-suite/v1', '/consent', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_consent_status' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'set_consent_status' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'consent' => array(
                        'required' => true,
                        'type'     => 'boolean',
                    ),
                ),
            ),
        ) );

        register_rest_route( 'rwp-creator-suite/v1', '/consent/banner-shown', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'mark_banner_shown' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Enqueue consent-related assets.
     */
    public function enqueue_consent_assets() {
        // Only enqueue if consent banner might be shown
        if ( ! $this->should_show_consent_banner() ) {
            return;
        }

        wp_enqueue_script(
            'rwp-consent-manager',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/js/consent-manager.js',
            array( 'wp-api-fetch' ),
            RWP_CREATOR_SUITE_VERSION,
            true
        );

        wp_enqueue_style(
            'rwp-consent-banner',
            RWP_CREATOR_SUITE_PLUGIN_URL . 'assets/css/consent-banner.css',
            array(),
            RWP_CREATOR_SUITE_VERSION
        );

        wp_localize_script( 'rwp-consent-manager', 'rwpConsentManager', array(
            'apiUrl' => rest_url( 'rwp-creator-suite/v1/' ),
            'nonce'  => wp_create_nonce( 'wp_rest' ),
            'strings' => array(
                'title' => __( 'Help Us Improve Your Experience', 'rwp-creator-suite' ),
                'message' => $this->get_consent_message(),
                'acceptButton' => __( 'Yes, I\'m Happy to Help', 'rwp-creator-suite' ),
                'declineButton' => __( 'No Thanks', 'rwp-creator-suite' ),
                'learnMoreButton' => __( 'Learn More', 'rwp-creator-suite' ),
                'learnMoreUrl' => $this->get_privacy_policy_url(),
            ),
        ) );
    }

    /**
     * Get consent message text.
     *
     * @return string
     */
    private function get_consent_message() {
        return __(
            'We\'d like to collect anonymous usage data to help us understand which features work best and improve your content creation experience. This data is completely anonymous and helps us make the tools more useful for creators like you.',
            'rwp-creator-suite'
        );
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
        
        // Fallback to plugin-specific privacy info
        return admin_url( 'admin.php?page=rwp-creator-tools&tab=privacy' );
    }

    /**
     * Check if consent banner should be shown.
     *
     * @return bool
     */
    private function should_show_consent_banner() {
        // Don't show in admin for non-admin users
        if ( is_admin() && ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        // Don't show if already consented
        if ( $this->has_user_consented() !== null ) {
            return false;
        }

        // Don't show if banner was already shown and dismissed
        if ( isset( $_COOKIE[ self::CONSENT_SHOWN_COOKIE ] ) ) {
            return false;
        }

        // Only show on pages with our blocks or admin pages
        if ( is_admin() ) {
            return true;
        }

        // Check if current page has our blocks
        global $post;
        if ( $post && has_blocks( $post->post_content ) ) {
            $blocks = parse_blocks( $post->post_content );
            foreach ( $blocks as $block ) {
                if ( isset( $block['blockName'] ) && str_starts_with( $block['blockName'], 'rwp-creator-suite/' ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Render consent banner HTML.
     */
    public function render_consent_banner() {
        if ( ! $this->should_show_consent_banner() ) {
            return;
        }

        ?>
        <div id="rwp-consent-banner" class="rwp-consent-banner" style="display: none;">
            <div class="rwp-consent-banner-content">
                <div class="rwp-consent-banner-text">
                    <h4 class="rwp-consent-banner-title"></h4>
                    <p class="rwp-consent-banner-message"></p>
                </div>
                <div class="rwp-consent-banner-buttons">
                    <button id="rwp-consent-accept" class="rwp-consent-button rwp-consent-accept">
                        <?php esc_html_e( 'Yes, I\'m Happy to Help', 'rwp-creator-suite' ); ?>
                    </button>
                    <button id="rwp-consent-decline" class="rwp-consent-button rwp-consent-decline">
                        <?php esc_html_e( 'No Thanks', 'rwp-creator-suite' ); ?>
                    </button>
                    <a href="#" id="rwp-consent-learn-more" class="rwp-consent-learn-more">
                        <?php esc_html_e( 'Learn More', 'rwp-creator-suite' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * REST endpoint to get current consent status.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function get_consent_status( $request ) {
        $consent = $this->has_user_consented();
        
        return new WP_REST_Response( array(
            'consented' => $consent,
            'show_banner' => $consent === null && $this->should_show_consent_banner(),
        ), 200 );
    }

    /**
     * REST endpoint to set consent status.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function set_consent_status( $request ) {
        $consent = $request->get_param( 'consent' );
        
        $this->set_user_consent( $consent );
        
        return new WP_REST_Response( array(
            'success' => true,
            'consented' => $consent,
            'message' => $consent 
                ? __( 'Thank you for helping us improve!', 'rwp-creator-suite' )
                : __( 'Your preference has been saved.', 'rwp-creator-suite' )
        ), 200 );
    }

    /**
     * REST endpoint to mark banner as shown.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function mark_banner_shown( $request ) {
        if ( ! headers_sent() ) {
            setcookie( 
                self::CONSENT_SHOWN_COOKIE, 
                '1', 
                time() + WEEK_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }
        
        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Check if user has consented to analytics.
     *
     * @return bool|null True if consented, false if declined, null if not set.
     */
    public function has_user_consented() {
        // Check logged-in user preference first
        if ( is_user_logged_in() ) {
            $user_consent = get_user_meta( get_current_user_id(), 'rwp_analytics_consent', true );
            if ( $user_consent === 'yes' ) {
                return true;
            } elseif ( $user_consent === 'no' ) {
                return false;
            }
        }
        
        // Check cookie
        if ( isset( $_COOKIE[ self::CONSENT_COOKIE ] ) ) {
            return $_COOKIE[ self::CONSENT_COOKIE ] === 'yes';
        }
        
        return null; // Not set
    }

    /**
     * Set user consent preference.
     *
     * @param bool $consent Whether user consents.
     */
    public function set_user_consent( $consent ) {
        $consent_value = $consent ? 'yes' : 'no';
        
        // Set cookie
        if ( ! headers_sent() ) {
            setcookie( 
                self::CONSENT_COOKIE, 
                $consent_value, 
                time() + YEAR_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }
        
        // Save preference for logged-in users
        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), 'rwp_analytics_consent', $consent_value );
        }
        
        // Initialize analytics with new consent status
        $analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
        $analytics->set_user_consent( $consent );
        
        // Log consent change for audit purposes
        RWP_Creator_Suite_Error_Logger::log(
            'User analytics consent changed',
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO,
            array(
                'consent' => $consent_value,
                'user_id' => get_current_user_id(),
                'ip_hash' => hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' )
            )
        );
    }

    /**
     * Add consent field to user profile.
     *
     * @param WP_User $user User object.
     */
    public function add_user_consent_field( $user ) {
        $consent = get_user_meta( $user->ID, 'rwp_analytics_consent', true );
        ?>
        <h3><?php esc_html_e( 'Analytics Preferences', 'rwp-creator-suite' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="rwp_analytics_consent"><?php esc_html_e( 'Analytics Data Collection', 'rwp-creator-suite' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="rwp_analytics_consent" 
                               id="rwp_analytics_consent" 
                               value="yes" 
                               <?php checked( $consent, 'yes' ); ?> />
                        <?php esc_html_e( 'Allow anonymous analytics data collection to help improve the Creator Suite', 'rwp-creator-suite' ); ?>
                    </label>
                    <p class="description">
                        <?php echo esc_html( $this->get_consent_message() ); ?>
                        <br>
                        <a href="<?php echo esc_url( $this->get_privacy_policy_url() ); ?>" target="_blank">
                            <?php esc_html_e( 'Learn more about our privacy practices', 'rwp-creator-suite' ); ?>
                        </a>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user consent field from profile.
     *
     * @param int $user_id User ID.
     */
    public function save_user_consent_field( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $consent = isset( $_POST['rwp_analytics_consent'] ) && $_POST['rwp_analytics_consent'] === 'yes' ? 'yes' : 'no';
        update_user_meta( $user_id, 'rwp_analytics_consent', $consent );
        
        // Update analytics system if this is the current user
        if ( $user_id === get_current_user_id() ) {
            $analytics = RWP_Creator_Suite_Anonymous_Analytics::get_instance();
            $analytics->set_user_consent( $consent === 'yes' );
        }
    }

    /**
     * Get consent statistics for admin dashboard.
     *
     * @return array
     */
    public function get_consent_stats() {
        global $wpdb;
        
        // Count user meta consent preferences
        $user_consents = $wpdb->get_results(
            "SELECT meta_value, COUNT(*) as count 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'rwp_analytics_consent' 
             GROUP BY meta_value"
        );
        
        $stats = array(
            'total_users_with_preference' => 0,
            'consented_users' => 0,
            'declined_users' => 0,
        );
        
        foreach ( $user_consents as $consent ) {
            $stats['total_users_with_preference'] += $consent->count;
            if ( $consent->meta_value === 'yes' ) {
                $stats['consented_users'] = $consent->count;
            } elseif ( $consent->meta_value === 'no' ) {
                $stats['declined_users'] = $consent->count;
            }
        }
        
        return $stats;
    }

    /**
     * Clear all consent data (for data deletion requests).
     *
     * @param int $user_id Optional user ID to clear specific user consent.
     */
    public function clear_consent_data( $user_id = null ) {
        if ( $user_id ) {
            delete_user_meta( $user_id, 'rwp_analytics_consent' );
        } else {
            // Clear current session cookies
            if ( ! headers_sent() ) {
                setcookie( self::CONSENT_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
                setcookie( self::CONSENT_SHOWN_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
            }
        }
    }

    /**
     * Get data export for user consent (GDPR compliance).
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function export_user_consent_data( $user_id ) {
        $consent = get_user_meta( $user_id, 'rwp_analytics_consent', true );
        
        return array(
            'analytics_consent' => $consent ?: 'not_set',
            'last_updated' => get_user_meta( $user_id, 'rwp_analytics_consent_updated', true ),
        );
    }
}