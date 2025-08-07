<?php
/**
 * WordPress Login Integration Class
 *
 * Integrates with WordPress built-in login and registration forms.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_WP_Login_Integration {

    /**
     * User registration instance.
     *
     * @var RWP_Creator_Suite_User_Registration
     */
    private $user_registration;

    /**
     * Redirect handler instance.
     *
     * @var RWP_Creator_Suite_Redirect_Handler
     */
    private $redirect_handler;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->user_registration = new RWP_Creator_Suite_User_Registration();
        $this->redirect_handler = new RWP_Creator_Suite_Redirect_Handler();
    }

    /**
     * Initialize WordPress login integration.
     */
    public function init() {
        // Hook into WordPress registration process
        add_action( 'user_register', array( $this, 'handle_wp_registration' ), 10, 1 );
        
        // Modify registration form to email-only
        add_action( 'login_form_register', array( $this, 'modify_registration_form' ) );
        
        // Handle login redirects
        add_filter( 'login_redirect', array( $this, 'handle_login_redirect' ), 10, 3 );
        
        // Remove username field from registration form
        add_action( 'login_head', array( $this, 'hide_username_field' ) );
        
        // Auto-generate username during registration
        add_filter( 'pre_user_login', array( $this, 'auto_generate_username' ) );
        
        // Validate email-only registration
        add_filter( 'registration_errors', array( $this, 'validate_email_only_registration' ), 10, 3 );
        
        // Auto-login after registration
        add_action( 'wp_login', array( $this, 'handle_auto_login_after_registration' ), 10, 2 );
        
        // Store original URL for redirects
        add_action( 'login_init', array( $this, 'store_original_url' ) );
        
        // Handle automatic logout redirect
        add_action( 'login_init', array( $this, 'handle_automatic_logout' ) );
    }

    /**
     * Handle WordPress registration and auto-login.
     *
     * @param int $user_id Newly registered user ID.
     */
    public function handle_wp_registration( $user_id ) {
        // Set user role to subscriber
        $user = new WP_User( $user_id );
        $user->set_role( 'subscriber' );

        // Store registration metadata
        add_user_meta( $user_id, 'rwp_creator_suite_registration_method', 'email_only' );
        add_user_meta( $user_id, 'rwp_creator_suite_auto_login', true );
        
        // Get redirect URL from form data or stored redirect
        $redirect_to = isset( $_POST['redirect_to'] ) ? sanitize_text_field( $_POST['redirect_to'] ) : '';
        
        // If no redirect_to from form, check for stored redirect URL
        if ( empty( $redirect_to ) ) {
            $redirect_to = $this->redirect_handler->get_stored_redirect_url();
        }
        
        add_user_meta( $user_id, 'rwp_creator_suite_original_url', $redirect_to );

        // Fire our custom action
        do_action( 'rwp_creator_suite_after_user_registration', $user_id, array(
            'email' => $user->user_email,
            'redirect_to' => $redirect_to,
        ) );

        // Auto-login the user
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );
        
        do_action( 'rwp_creator_suite_user_auto_login', $user_id, $redirect_to );
        
        // If this is a WordPress registration form submission and we have a redirect URL, redirect immediately
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'register' && ! empty( $redirect_to ) && $this->redirect_handler->is_valid_redirect_url( $redirect_to ) ) {
            wp_safe_redirect( $redirect_to );
            exit;
        }
    }

    /**
     * Modify registration form behavior.
     */
    public function modify_registration_form() {
        // Enable registration if not already enabled
        if ( ! get_option( 'users_can_register' ) ) {
            return;
        }

        // Add JavaScript to handle email-only registration
        add_action( 'login_footer', array( $this, 'add_registration_script' ) );
    }

    /**
     * Hide username field on registration form.
     */
    public function hide_username_field() {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'register' ) {
            ?>
            <style type="text/css">
                #registerform label[for="user_login"],
                #registerform #user_login {
                    display: none !important;
                }
                #registerform p:first-child {
                    display: none !important;
                }
            </style>
            <?php
        }
    }

    /**
     * Auto-generate username from email during registration.
     *
     * @param string $user_login Username.
     * @return string Generated username.
     */
    public function auto_generate_username( $user_login ) {
        // Only run during registration
        if ( ! isset( $_POST['user_email'] ) || ! isset( $_GET['action'] ) || $_GET['action'] !== 'register' ) {
            return $user_login;
        }

        $email = sanitize_email( $_POST['user_email'] );
        if ( ! is_email( $email ) ) {
            return $user_login;
        }

        $username_generator = new RWP_Creator_Suite_Username_Generator();
        $generated_username = $username_generator->generate_from_email( $email );

        if ( ! is_wp_error( $generated_username ) ) {
            return $generated_username;
        }

        return $user_login;
    }

    /**
     * Validate email-only registration.
     *
     * @param WP_Error $errors Registration errors.
     * @param string   $sanitized_user_login Sanitized username.
     * @param string   $user_email User email.
     * @return WP_Error Modified errors.
     */
    public function validate_email_only_registration( $errors, $sanitized_user_login, $user_email ) {
        // Check rate limiting
        $rate_limiter = new RWP_Creator_Suite_Rate_Limiter();
        $rate_check = $rate_limiter->check_registration_rate_limit( $user_email );
        
        if ( is_wp_error( $rate_check ) ) {
            $errors->add( 'rate_limit', $rate_check->get_error_message() );
        }

        return $errors;
    }

    /**
     * Handle auto-login after registration.
     *
     * @param string  $user_login Username.
     * @param WP_User $user User object.
     */
    public function handle_auto_login_after_registration( $user_login, $user ) {
        // Check if this is a new registration with auto-login
        $is_auto_login = get_user_meta( $user->ID, 'rwp_creator_suite_auto_login', true );
        
        if ( $is_auto_login ) {
            // Update last login
            update_user_meta( $user->ID, 'rwp_creator_suite_last_login', current_time( 'timestamp' ) );
            
            // Remove the auto-login flag
            delete_user_meta( $user->ID, 'rwp_creator_suite_auto_login' );
        }
    }

    /**
     * Handle login redirects.
     *
     * @param string  $redirect_to URL to redirect to.
     * @param string  $request Requested redirect URL.
     * @param WP_User $user User object.
     * @return string Final redirect URL.
     */
    public function handle_login_redirect( $redirect_to, $request, $user ) {
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            // Handle subscriber redirects - check for stored redirect first
            if ( in_array( 'subscriber', $user->roles, true ) && ! current_user_can( 'edit_posts' ) ) {
                
                // First check if user has a stored original URL from registration
                $stored_url = get_user_meta( $user->ID, 'rwp_creator_suite_original_url', true );
                if ( ! empty( $stored_url ) && $this->redirect_handler->is_valid_redirect_url( $stored_url ) ) {
                    // Clean up the stored URL
                    delete_user_meta( $user->ID, 'rwp_creator_suite_original_url' );
                    return esc_url_raw( $stored_url );
                }
                
                // Check for stored redirect from session/cookies
                $session_url = $this->redirect_handler->get_stored_redirect_url();
                if ( ! empty( $session_url ) && $this->redirect_handler->is_valid_redirect_url( $session_url ) ) {
                    return $session_url;
                }
                
                // Use provided redirect_to if valid
                if ( ! empty( $redirect_to ) && $this->redirect_handler->is_valid_redirect_url( $redirect_to ) ) {
                    return $redirect_to;
                }
                
                // Use request URL if valid
                if ( ! empty( $request ) && $this->redirect_handler->is_valid_redirect_url( $request ) ) {
                    return $request;
                }
                
                // Fallback to account page or home
                $account_redirect = apply_filters( 'rwp_creator_suite_subscriber_redirect_url', home_url( '/account/' ) );
                return $account_redirect;
            }
        }

        return $redirect_to;
    }

    /**
     * Store original URL during login process.
     */
    public function store_original_url() {
        // Let the redirect handler manage this
        $this->redirect_handler->store_original_url();
    }

    /**
     * Add JavaScript for registration form enhancement.
     */
    public function add_registration_script() {
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var registerForm = document.getElementById('registerform');
            var emailField = document.getElementById('user_email');
            var usernameField = document.getElementById('user_login');
            
            if (registerForm && emailField && usernameField) {
                // Hide username field
                var usernameLabel = document.querySelector('label[for="user_login"]');
                var usernameParagraph = usernameField.closest('p');
                
                if (usernameLabel) usernameLabel.style.display = 'none';
                if (usernameParagraph) usernameParagraph.style.display = 'none';
                
                // Auto-generate username from email (remove @ and . from full email)
                emailField.addEventListener('input', function() {
                    var email = this.value;
                    if (email && email.includes('@')) {
                        var username = email.replace(/[@.]/g, '').replace(/[^a-zA-Z0-9._-]/g, '');
                        if (username.length < 3) {
                            username = 'user_' + username;
                        }
                        usernameField.value = username;
                    }
                });
                
                // Add hidden redirect_to field from URL params
                var urlParams = new URLSearchParams(window.location.search);
                var redirectTo = urlParams.get('redirect_to');
                if (redirectTo) {
                    var redirectInput = document.createElement('input');
                    redirectInput.type = 'hidden';
                    redirectInput.name = 'redirect_to';
                    redirectInput.value = redirectTo;
                    registerForm.appendChild(redirectInput);
                }
                
                // Update form labels and messaging
                var emailLabel = document.querySelector('label[for="user_email"]');
                if (emailLabel) {
                    emailLabel.innerHTML = 'Email Address <span class="required">*</span>';
                }
                
                // Add helpful text
                var helpText = document.createElement('p');
                helpText.className = 'description';
                helpText.innerHTML = 'Your username will be generated automatically from your email address.';
                helpText.style.fontSize = '12px';
                helpText.style.color = '#666';
                emailField.parentNode.appendChild(helpText);
            }
        });
        </script>
        <?php
    }

    /**
     * Handle automatic logout redirect.
     */
    public function handle_automatic_logout() {
        // Check if this is a logout request without nonce and user is logged in
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'logout' && ! isset( $_GET['_wpnonce'] ) && is_user_logged_in() ) {
            // Create a nonce and validate the logout request
            $nonce = wp_create_nonce( 'log-out' );
            
            // Verify the nonce we just created (this allows the logout to proceed)
            if ( wp_verify_nonce( $nonce, 'log-out' ) ) {
                // Perform the logout
                wp_logout();
                
                // Redirect to homepage
                wp_safe_redirect( home_url() );
                exit;
            }
        }
    }
}