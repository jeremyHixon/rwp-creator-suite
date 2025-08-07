<?php
/**
 * Redirect Handler Class
 *
 * Manages smart redirects during the authentication flow.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Redirect_Handler {

    /**
     * Initialize redirect handling.
     */
    public function init() {
        add_action( 'template_redirect', array( $this, 'store_original_url' ), 1 );
    }

    /**
     * Store the original URL for later redirect.
     */
    public function store_original_url() {
        if ( is_user_logged_in() || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        // Only store for GET requests
        if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
            return;
        }

        $current_url = $this->get_current_url();
        
        // Don't store login/registration pages
        if ( $this->is_auth_page( $current_url ) ) {
            return;
        }

        // Don't store asset URLs (images, CSS, JS, etc.)
        if ( $this->is_asset_url( $current_url ) ) {
            return;
        }

        // Don't store WordPress core URLs
        if ( $this->is_wordpress_core_url( $current_url ) ) {
            return;
        }

        // Only store actual page URLs that users would want to return to
        if ( ! $this->is_valid_page_url( $current_url ) ) {
            return;
        }

        // Store in transient using IP address as key (for non-logged-in users)
        $ip_address = $this->get_client_ip();
        $transient_key = 'rwp_creator_suite_redirect_' . md5( $ip_address );
        set_transient( $transient_key, $current_url, 30 * MINUTE_IN_SECONDS );

        // Also store in cookie as fallback
        if ( ! headers_sent() ) {
            setcookie(
                'rwp_creator_suite_original_url',
                $current_url,
                time() + ( 30 * 60 ), // 30 minutes
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }
    }

    /**
     * Get the stored redirect URL.
     *
     * @param string $default Default URL if none stored.
     * @return string Redirect URL.
     */
    public function get_stored_redirect_url( $default = '' ) {
        $stored_url = '';
        
        // Check transient first (by IP address)
        $ip_address = $this->get_client_ip();
        $transient_key = 'rwp_creator_suite_redirect_' . md5( $ip_address );
        $stored_url = get_transient( $transient_key );
        
        if ( $stored_url ) {
            // Clean up the transient
            delete_transient( $transient_key );
        }
        
        // Check cookie as fallback
        if ( empty( $stored_url ) && isset( $_COOKIE['rwp_creator_suite_original_url'] ) ) {
            $stored_url = $_COOKIE['rwp_creator_suite_original_url'];
            
            // Clear the cookie
            if ( ! headers_sent() ) {
                setcookie(
                    'rwp_creator_suite_original_url',
                    '',
                    time() - 3600,
                    COOKIEPATH,
                    COOKIE_DOMAIN
                );
            }
        }
        
        // Validate and sanitize
        if ( $stored_url && $this->is_valid_redirect_url( $stored_url ) ) {
            return esc_url_raw( $stored_url );
        }
        
        return $default ?: home_url();
    }

    /**
     * Get the current URL.
     *
     * @return string Current URL.
     */
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $request_uri = $_SERVER['REQUEST_URI'];
        
        return $protocol . $host . $request_uri;
    }

    /**
     * Check if the URL is valid for redirect.
     *
     * @param string $url URL to validate.
     * @return bool Whether URL is valid for redirect.
     */
    public function is_valid_redirect_url( $url ) {
        if ( empty( $url ) ) {
            return false;
        }

        $parsed_url = wp_parse_url( $url );
        $site_url = wp_parse_url( home_url() );
        
        // Must be same domain
        if ( $parsed_url['host'] !== $site_url['host'] ) {
            return false;
        }

        // Exclude admin pages
        if ( isset( $parsed_url['path'] ) && strpos( $parsed_url['path'], '/wp-admin' ) !== false ) {
            return false;
        }

        // Exclude login pages
        if ( isset( $parsed_url['path'] ) && strpos( $parsed_url['path'], '/wp-login' ) !== false ) {
            return false;
        }

        // Exclude auth-related pages
        if ( $this->is_auth_page( $url ) ) {
            return false;
        }

        return true;
    }

    /**
     * Check if URL is an authentication-related page.
     *
     * @param string $url URL to check.
     * @return bool Whether URL is auth page.
     */
    private function is_auth_page( $url ) {
        $auth_pages = array(
            'login',
            'register',
            'lost-password',
            'reset-password',
        );

        $parsed_url = wp_parse_url( $url );
        $path = isset( $parsed_url['path'] ) ? trim( $parsed_url['path'], '/' ) : '';

        foreach ( $auth_pages as $auth_page ) {
            if ( strpos( $path, $auth_page ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a redirect URL with return parameter.
     *
     * @param string $auth_url Authentication URL.
     * @param string $return_url Return URL after auth.
     * @return string Complete auth URL with return parameter.
     */
    public function create_auth_url_with_return( $auth_url, $return_url = '' ) {
        if ( empty( $return_url ) ) {
            $return_url = $this->get_current_url();
        }

        if ( ! $this->is_valid_redirect_url( $return_url ) ) {
            $return_url = home_url();
        }

        return add_query_arg( 'redirect_to', urlencode( $return_url ), $auth_url );
    }

    /**
     * Get client IP address.
     *
     * @return string Client IP address.
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                $ip_list = explode( ',', $_SERVER[ $key ] );
                $ip = trim( $ip_list[0] );
                
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * Check if URL is an asset (image, CSS, JS, etc.).
     *
     * @param string $url URL to check.
     * @return bool Whether URL is an asset.
     */
    private function is_asset_url( $url ) {
        $asset_extensions = array(
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',
            'css', 'js', 'woff', 'woff2', 'ttf', 'eot',
            'mp4', 'mp3', 'wav', 'pdf', 'doc', 'docx', 'zip'
        );

        $parsed_url = wp_parse_url( $url );
        $path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        return in_array( $extension, $asset_extensions, true );
    }

    /**
     * Check if URL is a WordPress core URL.
     *
     * @param string $url URL to check.
     * @return bool Whether URL is a WordPress core URL.
     */
    private function is_wordpress_core_url( $url ) {
        $core_paths = array(
            '/wp-admin',
            '/wp-includes',
            '/wp-content/uploads',
            '/wp-content/themes',
            '/wp-content/plugins',
            '/wp-json/',
            '/xmlrpc.php',
            '/wp-cron.php',
            '/wp-login.php'
        );

        $parsed_url = wp_parse_url( $url );
        $path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';

        foreach ( $core_paths as $core_path ) {
            if ( strpos( $path, $core_path ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL is a valid page URL worth redirecting to.
     *
     * @param string $url URL to check.
     * @return bool Whether URL is a valid page URL.
     */
    private function is_valid_page_url( $url ) {
        $parsed_url = wp_parse_url( $url );
        
        // Must have a path
        if ( ! isset( $parsed_url['path'] ) || empty( $parsed_url['path'] ) ) {
            return false;
        }

        $path = $parsed_url['path'];

        // Exclude common non-page paths
        $excluded_patterns = array(
            '/feed/',
            '/trackback/',
            '/embed/',
            '/.well-known/',
            '/robots.txt',
            '/sitemap',
            '/favicon'
        );

        foreach ( $excluded_patterns as $pattern ) {
            if ( strpos( $path, $pattern ) !== false ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handle login redirect filter.
     *
     * @param string  $redirect_to URL to redirect to.
     * @param string  $request Requested redirect URL.
     * @param WP_User $user User object.
     * @return string Final redirect URL.
     */
    public function handle_login_redirect( $redirect_to, $request, $user ) {
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            // Handle subscriber redirects
            if ( in_array( 'subscriber', $user->roles, true ) && ! current_user_can( 'edit_posts' ) ) {
                $stored_url = $this->get_stored_redirect_url();
                
                if ( ! empty( $stored_url ) && $this->is_valid_redirect_url( $stored_url ) ) {
                    return $stored_url;
                }
                
                // Fallback to account page or home
                $account_redirect = apply_filters( 'rwp_creator_suite_subscriber_redirect_url', home_url( '/account/' ) );
                return $account_redirect;
            }
        }

        return $redirect_to;
    }
}