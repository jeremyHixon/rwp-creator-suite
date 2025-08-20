<?php
/**
 * Simple test script to verify the refactored analytics dashboard works
 * 
 * This is a temporary test file to check if our refactor resolves the
 * errors and performance issues in the admin area.
 */

// This is for testing only - would be removed after verification
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access not permitted.' );
}

// Only run if we're in WordPress admin and user has proper permissions
if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
    return;
}

/**
 * Test the refactored analytics dashboard
 */
function rwp_test_refactored_dashboard() {
    // Test 1: Check if the class exists and can be instantiated
    if ( ! class_exists( 'RWP_Creator_Suite_Analytics_Dashboard' ) ) {
        error_log( 'RWP Dashboard Test: Analytics Dashboard class not found' );
        return false;
    }
    
    try {
        // Test 2: Try to create instance
        $dashboard = new RWP_Creator_Suite_Analytics_Dashboard();
        
        // Test 3: Check if basic methods exist
        $required_methods = [
            'init',
            'add_admin_menu',
            'render_dashboard_page',
            'enqueue_admin_scripts'
        ];
        
        foreach ( $required_methods as $method ) {
            if ( ! method_exists( $dashboard, $method ) ) {
                error_log( "RWP Dashboard Test: Method $method not found" );
                return false;
            }
        }
        
        // Test 4: Try to initialize (this should not throw errors)
        $dashboard->init();
        
        error_log( 'RWP Dashboard Test: All tests passed - refactored dashboard loads successfully' );
        return true;
        
    } catch ( Exception $e ) {
        error_log( 'RWP Dashboard Test: Exception caught - ' . $e->getMessage() );
        return false;
    } catch ( Error $e ) {
        error_log( 'RWP Dashboard Test: Fatal error caught - ' . $e->getMessage() );
        return false;
    }
}

/**
 * Test analytics dependencies
 */
function rwp_test_analytics_dependencies() {
    $dependencies = [
        'RWP_Creator_Suite_Anonymous_Analytics',
        'RWP_Creator_Suite_Consent_Manager'
    ];
    
    $missing = [];
    foreach ( $dependencies as $class ) {
        if ( ! class_exists( $class ) ) {
            $missing[] = $class;
        }
    }
    
    if ( ! empty( $missing ) ) {
        error_log( 'RWP Dashboard Test: Missing dependencies - ' . implode( ', ', $missing ) );
        return false;
    }
    
    error_log( 'RWP Dashboard Test: All dependencies available' );
    return true;
}

/**
 * Test database requirements
 */
function rwp_test_database_requirements() {
    global $wpdb;
    
    $analytics_table = $wpdb->prefix . 'rwp_anonymous_analytics';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$analytics_table'" );
    
    if ( ! $table_exists ) {
        error_log( 'RWP Dashboard Test: Analytics table does not exist - this is normal for new installations' );
        return 'table_missing'; // Not an error, just info
    }
    
    error_log( 'RWP Dashboard Test: Analytics table exists' );
    return true;
}

// Run tests when WordPress is fully loaded
add_action( 'wp_loaded', function() {
    // Only run once per session to avoid spam
    if ( get_transient( 'rwp_dashboard_test_run' ) ) {
        return;
    }
    
    error_log( 'RWP Dashboard Test: Starting refactor verification tests...' );
    
    $dependency_test = rwp_test_analytics_dependencies();
    $db_test = rwp_test_database_requirements();
    $dashboard_test = rwp_test_refactored_dashboard();
    
    if ( $dependency_test && $dashboard_test ) {
        error_log( 'RWP Dashboard Test: ✅ REFACTOR SUCCESSFUL - Dashboard should work without errors' );
    } else {
        error_log( 'RWP Dashboard Test: ❌ REFACTOR ISSUES DETECTED - Check error logs for details' );
    }
    
    // Don't run again for 1 hour
    set_transient( 'rwp_dashboard_test_run', true, HOUR_IN_SECONDS );
}, 20 );

// Clean up function to remove this test file reminder
add_action( 'admin_notices', function() {
    if ( current_user_can( 'manage_options' ) && file_exists( __FILE__ ) ) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>RWP Creator Suite:</strong> Analytics dashboard refactor test file is active. ';
        echo 'Check error logs for test results. Remove test-dashboard-refactor.php when testing is complete.</p>';
        echo '</div>';
    }
} );