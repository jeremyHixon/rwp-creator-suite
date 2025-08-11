<?php
/**
 * Subscriber Restrictions Class
 *
 * Handles admin bar suppression and admin redirect for subscribers.
 *
 * @package RWP_Creator_Suite
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Subscriber_Restrictions {

    /**
     * Initialize subscriber restrictions.
     */
    public function init() {
        add_action( 'init', array( $this, 'handle_admin_restrictions' ) );
        add_action( 'admin_init', array( $this, 'redirect_subscribers_from_admin' ) );
        add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_for_subscribers' ) );
        add_action( 'wp_before_admin_bar_render', array( $this, 'modify_admin_bar_for_subscribers' ) );
    }

    /**
     * Handle general admin restrictions.
     */
    public function handle_admin_restrictions() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $current_user = wp_get_current_user();
        
        // Only apply restrictions to pure subscribers
        if ( $this->is_subscriber_only( $current_user ) ) {
            // Remove dashboard widgets for subscribers
            add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_widgets' ) );
            
            // Remove admin menu items for subscribers
            add_action( 'admin_menu', array( $this, 'remove_admin_menu_items' ), 999 );
        }
    }

    /**
     * Hide admin bar for subscribers.
     *
     * @param bool $show_admin_bar Whether to show admin bar.
     * @return bool Modified admin bar visibility.
     */
    public function hide_admin_bar_for_subscribers( $show_admin_bar ) {
        if ( ! is_user_logged_in() ) {
            return $show_admin_bar;
        }

        $current_user = wp_get_current_user();
        
        // Hide admin bar for subscribers without additional capabilities
        if ( $this->is_subscriber_only( $current_user ) ) {
            return false;
        }
        
        return $show_admin_bar;
    }

    /**
     * Modify admin bar for subscribers who can see it.
     */
    public function modify_admin_bar_for_subscribers() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        global $wp_admin_bar;
        
        $current_user = wp_get_current_user();
        
        if ( $this->is_subscriber_only( $current_user ) ) {
            // Remove unnecessary admin bar items
            $wp_admin_bar->remove_node( 'wp-logo' );
            $wp_admin_bar->remove_node( 'about' );
            $wp_admin_bar->remove_node( 'wporg' );
            $wp_admin_bar->remove_node( 'documentation' );
            $wp_admin_bar->remove_node( 'support-forums' );
            $wp_admin_bar->remove_node( 'feedback' );
            $wp_admin_bar->remove_node( 'new-content' );
            $wp_admin_bar->remove_node( 'comments' );
            $wp_admin_bar->remove_node( 'updates' );
        }
    }

    /**
     * Redirect subscribers away from wp-admin.
     */
    public function redirect_subscribers_from_admin() {
        if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        // Allow profile.php for subscribers
        if ( isset( $_GET['page'] ) && sanitize_text_field( $_GET['page'] ) === 'profile.php' ) {
            return;
        }

        $current_user = wp_get_current_user();
        
        if ( $this->is_subscriber_only( $current_user ) ) {
            $redirect_url = $this->get_subscriber_redirect_url();
            
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    /**
     * Get redirect URL for subscribers.
     *
     * @return string Redirect URL.
     */
    private function get_subscriber_redirect_url() {
        // Default redirect to account page or homepage
        $redirect_url = home_url( '/account/' );
        
        // Check if account page exists
        $account_page = get_page_by_path( 'account' );
        if ( ! $account_page ) {
            $redirect_url = home_url();
        }
        
        // Apply filter for customization
        $redirect_url = apply_filters( 'rwp_creator_suite_subscriber_redirect_url', $redirect_url );
        
        return esc_url_raw( $redirect_url );
    }

    /**
     * Check if user is a subscriber only (no additional capabilities).
     *
     * @param WP_User $user The user to check.
     * @return bool Whether user is subscriber only.
     */
    private function is_subscriber_only( $user ) {
        if ( ! $user || ! $user->exists() ) {
            return false;
        }

        // Check if user has subscriber role and no additional capabilities
        return in_array( 'subscriber', $user->roles, true ) && ! current_user_can( 'edit_posts' );
    }

    /**
     * Remove dashboard widgets for subscribers.
     */
    public function remove_dashboard_widgets() {
        global $wp_meta_boxes;
        
        // Remove default dashboard widgets
        unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_activity'] );
        unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now'] );
        unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments'] );
        unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links'] );
        unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins'] );
        unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_primary'] );
        unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary'] );
        unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press'] );
        unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_recent_drafts'] );
    }

    /**
     * Remove admin menu items for subscribers.
     */
    public function remove_admin_menu_items() {
        // Keep only essential menu items for subscribers
        remove_menu_page( 'edit.php' );           // Posts
        remove_menu_page( 'upload.php' );        // Media
        remove_menu_page( 'edit-comments.php' ); // Comments
        remove_menu_page( 'themes.php' );        // Appearance
        remove_menu_page( 'plugins.php' );       // Plugins
        remove_menu_page( 'users.php' );         // Users
        remove_menu_page( 'tools.php' );         // Tools
        remove_menu_page( 'options-general.php' ); // Settings
        
        // Remove submenu items
        remove_submenu_page( 'profile.php', 'profile.php' );
        
        // Keep profile.php for account management
        global $submenu;
        if ( isset( $submenu['profile.php'] ) ) {
            foreach ( $submenu['profile.php'] as $key => $item ) {
                if ( $item[2] !== 'profile.php' ) {
                    unset( $submenu['profile.php'][$key] );
                }
            }
        }
    }
}