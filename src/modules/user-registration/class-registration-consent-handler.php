<?php
/**
 * Registration Consent Handler Class
 *
 * Handles the advanced analytics features consent checkbox in registration forms.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Registration_Consent_Handler {

    /**
     * The user meta key for storing consent.
     *
     * @var string
     */
    const CONSENT_META_KEY = 'advanced_features_consent';

    /**
     * Initialize consent handling.
     */
    public function init() {
        // Add consent checkbox to registration form
        add_action( 'register_form', array( $this, 'add_consent_checkbox' ), 999 );
        
        // Validate consent on registration
        add_filter( 'registration_errors', array( $this, 'validate_consent' ), 10, 3 );
        
        // Save consent when user is registered (priority 5 to run before other handlers)
        add_action( 'user_register', array( $this, 'save_consent' ), 5, 1 );
        
        // Add consent field to REST API registration
        add_filter( 'rwp_creator_suite_registration_data', array( $this, 'handle_api_consent' ), 10, 2 );
    }

    /**
     * Add consent checkbox to registration form.
     */
    public function add_consent_checkbox() {
        $checkbox_id = 'advanced_features_consent';
        $checked = isset( $_POST[ $checkbox_id ] ) ? checked( 1, $_POST[ $checkbox_id ], false ) : '';
        
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var checkboxContainer = document.getElementById('<?php echo $checkbox_id; ?>-wrapper');
                var submitWrapper = document.querySelector('.submit');
                
                if (checkboxContainer && submitWrapper) {
                    submitWrapper.parentNode.insertBefore(checkboxContainer, submitWrapper);
                }
            });
        </script>
        <style>
            #registerform > p {
                margin: 10px 0 !important;
            }
            #<?php echo $checkbox_id; ?>-wrapper {
                background: #F0F9FF;
                border: 1px solid #3B82F6;
                padding: 12px 16px;
                border-radius: 4px;
            }
        </style>
        <p id="<?php echo $checkbox_id; ?>-wrapper">
            <label for="<?php echo esc_attr( $checkbox_id ); ?>">
                <input name="<?php echo esc_attr( $checkbox_id ); ?>" 
                       type="checkbox" 
                       id="<?php echo esc_attr( $checkbox_id ); ?>" 
                       value="1" 
                       <?php echo $checked; ?>
                       style="width: auto; margin-right: 8px;" />
                <?php esc_html_e( 'Yes, I would like to enable advanced analytics features to get more personalized reports and insights', 'rwp-creator-suite' ); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Validate consent on registration.
     * Note: Consent is optional, so no validation errors are added.
     *
     * @param WP_Error $errors Registration errors.
     * @param string   $sanitized_user_login Sanitized username.
     * @param string   $user_email User email.
     * @return WP_Error Modified errors.
     */
    public function validate_consent( $errors, $sanitized_user_login, $user_email ) {
        // Consent is optional, so we don't add any validation errors
        // This hook is here for future extensibility if consent becomes required
        return $errors;
    }

    /**
     * Save consent when user is registered.
     *
     * @param int $user_id Newly registered user ID.
     */
    public function save_consent( $user_id ) {
        // Only save consent if we're in a POST request (WordPress registration form submission)
        if ( empty( $_POST ) ) {
            return;
        }
        
        $consent = isset( $_POST[ self::CONSENT_META_KEY ] ) ? 1 : 0;
        $this->update_user_consent( $user_id, $consent );
    }

    /**
     * Handle API consent during registration.
     *
     * @param array $registration_data Registration data.
     * @param array $request_data Original request data.
     * @return array Modified registration data.
     */
    public function handle_api_consent( $registration_data, $request_data ) {
        if ( isset( $request_data[ self::CONSENT_META_KEY ] ) ) {
            $registration_data[ self::CONSENT_META_KEY ] = $request_data[ self::CONSENT_META_KEY ] ? 1 : 0;
        }
        
        return $registration_data;
    }

    /**
     * Update user consent preference.
     *
     * @param int  $user_id User ID.
     * @param bool $consent Consent value (1 for yes, 0 for no).
     * @return bool|int Meta ID on success, false on failure.
     */
    public function update_user_consent( $user_id, $consent ) {
        $consent = $consent ? 1 : 0;
        
        // Log the consent change for audit purposes
        do_action( 'rwp_creator_suite_consent_updated', $user_id, $consent, current_time( 'timestamp' ) );
        
        return update_user_meta( $user_id, self::CONSENT_META_KEY, $consent );
    }

    /**
     * Get user consent preference.
     *
     * @param int $user_id User ID. If not provided, uses current user.
     * @return bool|null Consent value (true for yes, false for no, null if not set).
     */
    public function get_user_consent( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        if ( ! $user_id ) {
            return null;
        }
        
        $consent = get_user_meta( $user_id, self::CONSENT_META_KEY, true );
        
        // Return null if not set, otherwise return boolean
        if ( $consent === '' ) {
            return null;
        }
        
        return (bool) $consent;
    }

    /**
     * Check if user has given consent for advanced features.
     *
     * @param int $user_id User ID. If not provided, uses current user.
     * @return bool True if user has given consent, false otherwise.
     */
    public function user_has_consent( $user_id = null ) {
        $consent = $this->get_user_consent( $user_id );
        return $consent === true;
    }

    /**
     * Get all users who have given consent.
     *
     * @param array $args Optional. Query arguments.
     * @return array Array of user IDs.
     */
    public function get_consented_users( $args = array() ) {
        $default_args = array(
            'meta_query' => array(
                array(
                    'key'     => self::CONSENT_META_KEY,
                    'value'   => '1',
                    'compare' => '='
                )
            ),
            'fields' => 'ID',
            'number' => -1
        );
        
        $args = wp_parse_args( $args, $default_args );
        
        $users = get_users( $args );
        
        return is_array( $users ) ? $users : array();
    }

    /**
     * Get consent statistics.
     *
     * @return array Array with consent statistics.
     */
    public function get_consent_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total users with consent meta
        $total_with_consent = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
            self::CONSENT_META_KEY
        ) );
        
        // Users who gave consent
        $consented_users = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '1'",
            self::CONSENT_META_KEY
        ) );
        
        // Users who declined consent
        $declined_users = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '0'",
            self::CONSENT_META_KEY
        ) );
        
        $stats['total_with_consent'] = (int) $total_with_consent;
        $stats['consented'] = (int) $consented_users;
        $stats['declined'] = (int) $declined_users;
        $stats['consent_rate'] = $total_with_consent > 0 ? round( ( $consented_users / $total_with_consent ) * 100, 2 ) : 0;
        
        return $stats;
    }

    /**
     * Get the consent meta key for external access.
     *
     * @return string The consent meta key.
     */
    public static function get_consent_meta_key() {
        return self::CONSENT_META_KEY;
    }
}