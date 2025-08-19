<?php
/**
 * Account Manager Module
 *
 * Main class for the account management functionality
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Account_Manager {

    /**
     * Account API instance.
     *
     * @var RWP_Creator_Suite_Account_API
     */
    private $api;

    /**
     * Initialize the account manager.
     */
    public function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize API
        $this->api = new RWP_Creator_Suite_Account_API();
        $this->api->init();
        
        // Add hooks
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_scripts' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
        
        // Add user profile fields if needed
        add_action( 'show_user_profile', array( $this, 'add_user_profile_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'add_user_profile_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
    }

    /**
     * Load required dependencies.
     */
    private function load_dependencies() {
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/account-manager/class-account-api.php';
        require_once RWP_CREATOR_SUITE_PLUGIN_DIR . 'src/modules/user-registration/class-registration-consent-handler.php';
    }

    /**
     * Maybe enqueue scripts on frontend.
     */
    public function maybe_enqueue_scripts() {
        global $post;
        
        if ( ! $post || ! has_block( 'rwp-creator-suite/account-manager', $post ) ) {
            return;
        }
        
        // Scripts are enqueued by the block manager
        // This method can be used for additional frontend scripts if needed
    }

    /**
     * Enqueue editor assets.
     */
    public function enqueue_editor_assets() {
        // Editor-specific scripts can be enqueued here if needed
    }

    /**
     * Add user profile fields for consent management.
     *
     * @param WP_User $user User object.
     */
    public function add_user_profile_fields( $user ) {
        $consent_handler = new RWP_Creator_Suite_Registration_Consent_Handler();
        $current_consent = $consent_handler->get_user_consent( $user->ID );
        
        ?>
        <h3><?php esc_html_e( 'Creator Suite Preferences', 'rwp-creator-suite' ); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php esc_html_e( 'Advanced Analytics', 'rwp-creator-suite' ); ?>
                </th>
                <td>
                    <label for="<?php echo esc_attr( RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key() ); ?>">
                        <input name="<?php echo esc_attr( RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key() ); ?>" 
                               type="checkbox" 
                               id="<?php echo esc_attr( RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key() ); ?>" 
                               value="1" 
                               <?php checked( 1, $current_consent ); ?> />
                        <?php esc_html_e( 'Enable advanced analytics features for personalized insights', 'rwp-creator-suite' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'When enabled, you will receive detailed analytics and personalized recommendations to improve your content performance.', 'rwp-creator-suite' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user profile fields.
     *
     * @param int $user_id User ID.
     */
    public function save_user_profile_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $consent_key = RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key();
        $consent = isset( $_POST[ $consent_key ] ) ? 1 : 0;
        
        $consent_handler = new RWP_Creator_Suite_Registration_Consent_Handler();
        $consent_handler->update_user_consent( $user_id, $consent );
    }

    /**
     * Get the API instance.
     *
     * @return RWP_Creator_Suite_Account_API
     */
    public function get_api() {
        return $this->api;
    }

    /**
     * Get user account dashboard data.
     *
     * @param int $user_id User ID. If not provided, uses current user.
     * @return array Dashboard data array.
     */
    public function get_dashboard_data( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        if ( ! $user_id ) {
            return array();
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return array();
        }

        $consent_handler = new RWP_Creator_Suite_Registration_Consent_Handler();
        
        return array(
            'user' => array(
                'id' => $user_id,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'registered' => $user->user_registered,
                'roles' => $user->roles,
            ),
            'consent' => array(
                'status' => $consent_handler->get_user_consent( $user_id ),
                'meta_key' => RWP_Creator_Suite_Registration_Consent_Handler::get_consent_meta_key(),
            ),
            'stats' => array(
                'total_users' => count_users(),
                'consent_stats' => current_user_can( 'manage_options' ) ? $consent_handler->get_consent_statistics() : null,
            ),
        );
    }

    /**
     * Check if user can manage account settings.
     *
     * @param int $user_id User ID to check.
     * @return bool
     */
    public function user_can_manage_account( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        if ( ! $user_id ) {
            return false;
        }
        
        // Users can manage their own account
        if ( $user_id === get_current_user_id() ) {
            return true;
        }
        
        // Admins can manage any account
        return current_user_can( 'edit_users' );
    }

    /**
     * Register admin menu items if needed.
     */
    public function register_admin_menu() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        add_submenu_page(
            'rwp-creator-tools',
            __( 'User Consent', 'rwp-creator-suite' ),
            __( 'User Consent', 'rwp-creator-suite' ),
            'manage_options',
            'rwp-user-consent',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Render admin page for consent management.
     */
    public function render_admin_page() {
        $consent_handler = new RWP_Creator_Suite_Registration_Consent_Handler();
        $statistics = $consent_handler->get_consent_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'User Consent Management', 'rwp-creator-suite' ); ?></h1>
            
            <div class="card">
                <h2><?php esc_html_e( 'Consent Statistics', 'rwp-creator-suite' ); ?></h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e( 'Total Users with Consent Data', 'rwp-creator-suite' ); ?></strong></td>
                            <td><?php echo esc_html( $statistics['total_with_consent'] ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Users Who Gave Consent', 'rwp-creator-suite' ); ?></strong></td>
                            <td><?php echo esc_html( $statistics['consented'] ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Users Who Declined', 'rwp-creator-suite' ); ?></strong></td>
                            <td><?php echo esc_html( $statistics['declined'] ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Consent Rate', 'rwp-creator-suite' ); ?></strong></td>
                            <td><?php echo esc_html( $statistics['consent_rate'] ); ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2><?php esc_html_e( 'Integration Instructions', 'rwp-creator-suite' ); ?></h2>
                <p><?php esc_html_e( 'To add an account manager interface to any page or post, use the "Account Manager" block in the WordPress block editor.', 'rwp-creator-suite' ); ?></p>
                <p><?php esc_html_e( 'Users will be able to manage their consent preferences and view their account information through this interface.', 'rwp-creator-suite' ); ?></p>
            </div>
        </div>
        <?php
    }
}