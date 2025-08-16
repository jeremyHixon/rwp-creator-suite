<?php
/**
 * Simple Test Runner for Phase 2 Components
 * 
 * This tests the new components outside of the full WordPress test suite
 * to verify basic functionality without requiring WordPress test environment setup.
 */

// Include WordPress
require_once '/Users/jhixon/Local Sites/creatortools/app/public/wp-load.php';

// Simple assertion function
function assert_test($condition, $message) {
    if ($condition) {
        echo "✅ PASS: $message\n";
        return true;
    } else {
        echo "❌ FAIL: $message\n";
        return false;
    }
}

echo "=== RWP Creator Suite Phase 2 Component Tests ===\n\n";

$total_tests = 0;
$passed_tests = 0;

// Test 1: Service Container Basic Functionality
echo "--- Testing Service Container ---\n";

try {
    $container = RWP_Creator_Suite_Service_Container::get_instance();
    
    $total_tests++;
    if (assert_test($container !== null, "Service Container instantiation")) {
        $passed_tests++;
    }
    
    // Test singleton pattern
    $container2 = RWP_Creator_Suite_Service_Container::get_instance();
    $total_tests++;
    if (assert_test($container === $container2, "Service Container singleton pattern")) {
        $passed_tests++;
    }
    
    // Test service registration
    $test_service = new stdClass();
    $test_service->test_value = 'test';
    $container->register('test_service', $test_service);
    
    $total_tests++;
    if (assert_test($container->has('test_service'), "Service registration")) {
        $passed_tests++;
    }
    
    $retrieved = $container->get('test_service');
    $total_tests++;
    if (assert_test($retrieved === $test_service, "Service retrieval")) {
        $passed_tests++;
    }
    
    // Test core services are registered
    $total_tests++;
    if (assert_test($container->has('ai_service'), "AI Service is registered")) {
        $passed_tests++;
    }
    
    $total_tests++;
    if (assert_test($container->has('network_utils'), "Network Utils is registered")) {
        $passed_tests++;
    }
    
} catch (Exception $e) {
    echo "❌ Service Container test failed with exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Transient Manager Basic Functionality
echo "--- Testing Transient Manager ---\n";

try {
    $manager = RWP_Creator_Suite_Transient_Manager::get_instance();
    
    $total_tests++;
    if (assert_test($manager !== null, "Transient Manager instantiation")) {
        $passed_tests++;
    }
    
    // Test singleton pattern
    $manager2 = RWP_Creator_Suite_Transient_Manager::get_instance();
    $total_tests++;
    if (assert_test($manager === $manager2, "Transient Manager singleton pattern")) {
        $passed_tests++;
    }
    
    // Test set and get
    $test_data = array('key' => 'value', 'number' => 123);
    $set_result = $manager->set('test_key', $test_data, HOUR_IN_SECONDS);
    
    $total_tests++;
    if (assert_test($set_result === true, "Transient set operation")) {
        $passed_tests++;
    }
    
    $retrieved_data = $manager->get('test_key');
    $total_tests++;
    if (assert_test($retrieved_data === $test_data, "Transient get operation")) {
        $passed_tests++;
    }
    
    // Test delete
    $delete_result = $manager->delete('test_key');
    $total_tests++;
    if (assert_test($delete_result === true, "Transient delete operation")) {
        $passed_tests++;
    }
    
    $deleted_data = $manager->get('test_key');
    $total_tests++;
    if (assert_test($deleted_data === false, "Transient deleted successfully")) {
        $passed_tests++;
    }
    
    // Test remember functionality
    $callback_called = false;
    $callback = function() use (&$callback_called) {
        $callback_called = true;
        return 'generated_value';
    };
    
    $result = $manager->remember('remember_test', $callback);
    $total_tests++;
    if (assert_test($result === 'generated_value' && $callback_called, "Transient remember - cache miss")) {
        $passed_tests++;
    }
    
    $callback_called = false;
    $result2 = $manager->remember('remember_test', $callback);
    $total_tests++;
    if (assert_test($result2 === 'generated_value' && !$callback_called, "Transient remember - cache hit")) {
        $passed_tests++;
    }
    
} catch (Exception $e) {
    echo "❌ Transient Manager test failed with exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: API Validation Trait
echo "--- Testing API Validation Trait ---\n";

try {
    // Create test class that uses the trait
    $test_class = new class {
        use RWP_Creator_Suite_API_Validation_Trait;
    };
    
    $total_tests++;
    if (assert_test(method_exists($test_class, 'validate_description'), "API Validation trait loaded")) {
        $passed_tests++;
    }
    
    // Test description validation
    $valid_description = "This is a valid description for testing purposes.";
    $result = $test_class->validate_description($valid_description);
    $total_tests++;
    if (assert_test($result === true, "Valid description validation")) {
        $passed_tests++;
    }
    
    $empty_description = "";
    $result = $test_class->validate_description($empty_description);
    $total_tests++;
    if (assert_test(is_wp_error($result), "Empty description validation")) {
        $passed_tests++;
    }
    
    // Test platforms validation
    $valid_platforms = array('instagram', 'twitter', 'linkedin');
    $result = $test_class->validate_platforms($valid_platforms);
    $total_tests++;
    if (assert_test($result === true, "Valid platforms validation")) {
        $passed_tests++;
    }
    
    $invalid_platforms = array('invalid_platform');
    $result = $test_class->validate_platforms($invalid_platforms);
    $total_tests++;
    if (assert_test(is_wp_error($result), "Invalid platforms validation")) {
        $passed_tests++;
    }
    
    // Test platforms sanitization
    $mixed_platforms = array('instagram', 'invalid', 'twitter', 'instagram');
    $sanitized = $test_class->sanitize_platforms($mixed_platforms);
    $expected = array('instagram', 'twitter');
    $total_tests++;
    if (assert_test(count($sanitized) === 2 && in_array('instagram', $sanitized) && in_array('twitter', $sanitized), "Platforms sanitization")) {
        $passed_tests++;
    }
    
} catch (Exception $e) {
    echo "❌ API Validation Trait test failed with exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Rate Limiting Trait
echo "--- Testing Rate Limiting Trait ---\n";

try {
    // Create test class that uses the trait
    $test_class = new class {
        use RWP_Creator_Suite_Rate_Limiting_Trait;
    };
    
    $total_tests++;
    if (assert_test(method_exists($test_class, 'check_rate_limit'), "Rate Limiting trait loaded")) {
        $passed_tests++;
    }
    
    // Test rate limit check (should pass initially)
    $result = $test_class->check_rate_limit('test_feature');
    $total_tests++;
    if (assert_test($result === true, "Initial rate limit check")) {
        $passed_tests++;
    }
    
    // Test usage tracking
    $test_class->track_usage('test_feature', 1);
    $total_tests++;
    if (assert_test(true, "Usage tracking (no exceptions)")) {
        $passed_tests++;
    }
    
    // Test usage stats
    $stats = $test_class->get_usage_stats('test_feature');
    $total_tests++;
    if (assert_test(is_array($stats) && isset($stats['current_usage']), "Usage stats retrieval")) {
        $passed_tests++;
    }
    
} catch (Exception $e) {
    echo "❌ Rate Limiting Trait test failed with exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Guest Handling Trait
echo "--- Testing Guest Handling Trait ---\n";

try {
    // Create test class that uses the trait
    $test_class = new class {
        use RWP_Creator_Suite_Guest_Handling_Trait;
    };
    
    $total_tests++;
    if (assert_test(method_exists($test_class, 'check_guest_access'), "Guest Handling trait loaded")) {
        $passed_tests++;
    }
    
    // Test guest limits
    $limits = $test_class->get_guest_limits('content_repurposer');
    $total_tests++;
    if (assert_test(is_array($limits) && isset($limits['hourly']), "Guest limits retrieval")) {
        $passed_tests++;
    }
    
    // Test guest identifier
    $identifier = $test_class->get_guest_user_identifier();
    $total_tests++;
    if (assert_test(!empty($identifier) && is_string($identifier), "Guest identifier generation")) {
        $passed_tests++;
    }
    
} catch (Exception $e) {
    echo "❌ Guest Handling Trait test failed with exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Total Tests: $total_tests\n";
echo "Passed: $passed_tests\n";
echo "Failed: " . ($total_tests - $passed_tests) . "\n";
echo "Success Rate: " . round(($passed_tests / $total_tests) * 100, 2) . "%\n";

if ($passed_tests === $total_tests) {
    echo "\n🎉 All tests passed! Phase 2 components are working correctly.\n";
} else {
    echo "\n⚠️  Some tests failed. Please review the output above.\n";
}