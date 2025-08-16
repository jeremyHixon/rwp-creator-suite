<?php
/**
 * Cache Manager Tests
 * 
 * Tests for the RWP Creator Suite Cache Manager functionality.
 */

// Include WordPress
require_once '/Users/jhixon/Local Sites/creatortools/app/public/wp-load.php';

echo "=== Cache Manager Tests ===\n\n";

$total_tests = 0;
$passed_tests = 0;

function assert_cache_test($condition, $message) {
    global $total_tests, $passed_tests;
    $total_tests++;
    if ($condition) {
        echo "✅ PASS: $message\n";
        $passed_tests++;
        return true;
    } else {
        echo "❌ FAIL: $message\n";
        return false;
    }
}

// Test 1: Cache Manager Instantiation
echo "--- Testing Cache Manager Instantiation ---\n";

try {
    $cache_manager = RWP_Creator_Suite_Cache_Manager::get_instance();
    
    assert_cache_test($cache_manager !== null, "Cache Manager instantiation");
    
    // Test singleton pattern
    $cache_manager2 = RWP_Creator_Suite_Cache_Manager::get_instance();
    assert_cache_test($cache_manager === $cache_manager2, "Cache Manager singleton pattern");
    
} catch (Exception $e) {
    echo "❌ Cache Manager instantiation failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Basic Cache Operations
echo "--- Testing Basic Cache Operations ---\n";

try {
    $test_data = array('key' => 'value', 'number' => 123, 'array' => array(1, 2, 3));
    
    // Test set
    $set_result = $cache_manager->set('test_key', $test_data, 'default', 300);
    assert_cache_test($set_result === true, "Cache set operation");
    
    // Test get
    $retrieved_data = $cache_manager->get('test_key', 'default');
    assert_cache_test($retrieved_data === $test_data, "Cache get operation");
    
    // Test get non-existent key
    $non_existent = $cache_manager->get('non_existent_key', 'default');
    assert_cache_test($non_existent === false, "Cache miss for non-existent key");
    
    // Test delete
    $delete_result = $cache_manager->delete('test_key', 'default');
    assert_cache_test($delete_result === true, "Cache delete operation");
    
    // Verify deletion
    $deleted_data = $cache_manager->get('test_key', 'default');
    assert_cache_test($deleted_data === false, "Cache data deleted successfully");
    
} catch (Exception $e) {
    echo "❌ Basic cache operations failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Cache Groups
echo "--- Testing Cache Groups ---\n";

try {
    $ai_data = array('response' => 'Generated content', 'tokens' => 150);
    $settings_data = array('api_key' => 'test_key', 'enabled' => true);
    
    // Test different cache groups
    $cache_manager->set('ai_test', $ai_data, 'ai_responses');
    $cache_manager->set('setting_test', $settings_data, 'settings');
    
    $retrieved_ai = $cache_manager->get('ai_test', 'ai_responses');
    $retrieved_settings = $cache_manager->get('setting_test', 'settings');
    
    assert_cache_test($retrieved_ai === $ai_data, "AI responses cache group");
    assert_cache_test($retrieved_settings === $settings_data, "Settings cache group");
    
    // Test group isolation
    $cross_group = $cache_manager->get('ai_test', 'settings');
    assert_cache_test($cross_group === false, "Cache group isolation");
    
} catch (Exception $e) {
    echo "❌ Cache groups test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Remember Pattern
echo "--- Testing Remember Pattern ---\n";

try {
    $callback_executed = false;
    $expensive_operation = function() use (&$callback_executed) {
        $callback_executed = true;
        // Simulate expensive operation
        return array('computed' => 'expensive_result', 'timestamp' => time());
    };
    
    // First call should execute callback
    $result1 = $cache_manager->remember('expensive_key', $expensive_operation, 'default', 300);
    assert_cache_test($callback_executed, "Remember pattern - cache miss callback execution");
    assert_cache_test($result1['computed'] === 'expensive_result', "Remember pattern - cache miss result");
    
    // Second call should use cache
    $callback_executed = false;
    $result2 = $cache_manager->remember('expensive_key', $expensive_operation, 'default', 300);
    assert_cache_test(!$callback_executed, "Remember pattern - cache hit (no callback execution)");
    assert_cache_test($result2 === $result1, "Remember pattern - cache hit result");
    
} catch (Exception $e) {
    echo "❌ Remember pattern test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Cache Invalidation
echo "--- Testing Cache Invalidation ---\n";

try {
    // Set up test data in different groups
    $cache_manager->set('config1', 'value1', 'settings');
    $cache_manager->set('config2', 'value2', 'settings');
    $cache_manager->set('ai1', 'ai_value1', 'ai_responses');
    $cache_manager->set('user1', 'user_value1', 'user_quotas');
    
    // Test group invalidation
    $invalidated = $cache_manager->invalidate('settings', 'group');
    assert_cache_test($invalidated === true, "Group invalidation executed");
    
    // Verify settings group invalidated
    $config1_after = $cache_manager->get('config1', 'settings');
    $config2_after = $cache_manager->get('config2', 'settings');
    assert_cache_test($config1_after === false && $config2_after === false, "Settings group invalidated");
    
    // Verify other groups not affected
    $ai1_after = $cache_manager->get('ai1', 'ai_responses');
    $user1_after = $cache_manager->get('user1', 'user_quotas');
    assert_cache_test($ai1_after === 'ai_value1' && $user1_after === 'user_value1', "Other groups preserved");
    
} catch (Exception $e) {
    echo "❌ Cache invalidation test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 6: Cache Statistics
echo "--- Testing Cache Statistics ---\n";

try {
    // Perform various cache operations
    $cache_manager->set('stats_test_1', 'value1', 'default');
    $cache_manager->set('stats_test_2', 'value2', 'default');
    
    // Hits
    $cache_manager->get('stats_test_1', 'default');
    $cache_manager->get('stats_test_1', 'default');
    
    // Misses
    $cache_manager->get('non_existent_1', 'default');
    $cache_manager->get('non_existent_2', 'default');
    
    // Deletes
    $cache_manager->delete('stats_test_2', 'default');
    
    $stats = $cache_manager->get_stats();
    
    assert_cache_test(isset($stats['metrics']), "Statistics available");
    assert_cache_test(isset($stats['hit_rate']), "Hit rate calculated");
    assert_cache_test($stats['metrics']['hits'] >= 2, "Hit tracking");
    assert_cache_test($stats['metrics']['misses'] >= 2, "Miss tracking");
    assert_cache_test($stats['metrics']['sets'] >= 2, "Set tracking");
    assert_cache_test($stats['metrics']['deletes'] >= 1, "Delete tracking");
    
} catch (Exception $e) {
    echo "❌ Cache statistics test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 7: Multi-Tier Caching
echo "--- Testing Multi-Tier Caching ---\n";

try {
    $large_data = array_fill(0, 1000, 'test_data_item');
    
    // Test memory + transient caching
    $cache_manager->set('multi_tier_test', $large_data, 'ai_responses', 600);
    
    // Should retrieve from memory cache first
    $retrieved = $cache_manager->get('multi_tier_test', 'ai_responses');
    assert_cache_test($retrieved === $large_data, "Multi-tier cache retrieval");
    
    // Test that data persists in transient cache
    $transient_key = 'rwp_ai_responses_' . hash('sha256', 'multi_tier_test');
    $transient_exists = get_transient($transient_key) !== false;
    assert_cache_test($transient_exists, "Transient cache persistence");
    
} catch (Exception $e) {
    echo "❌ Multi-tier caching test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 8: Cache Compression
echo "--- Testing Cache Compression ---\n";

try {
    if (function_exists('gzcompress')) {
        $large_text_data = str_repeat('This is a test string for compression. ', 100);
        
        // Store in a group that uses compression
        $cache_manager->set('compression_test', $large_text_data, 'ai_responses', 300);
        
        $retrieved_data = $cache_manager->get('compression_test', 'ai_responses');
        assert_cache_test($retrieved_data === $large_text_data, "Compressed data integrity");
        
        // Check if data was actually compressed in storage
        $transient_key = 'rwp_ai_responses_' . hash('sha256', 'compression_test');
        $stored_data = get_transient($transient_key);
        $is_compressed = is_array($stored_data) && isset($stored_data['compressed']) && $stored_data['compressed'];
        assert_cache_test($is_compressed, "Data compression applied");
    } else {
        assert_cache_test(true, "Compression test skipped (gzcompress not available)");
    }
    
} catch (Exception $e) {
    echo "❌ Cache compression test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 9: Cache Warming
echo "--- Testing Cache Warming ---\n";

try {
    $warming_executed = false;
    
    // Add a warming strategy
    $warming_strategy = function($cache_manager) use (&$warming_executed) {
        $warming_executed = true;
        $cache_manager->set('warmed_data', 'preloaded_value', 'platform_configs', 3600);
    };
    
    $cache_manager->add_warming_strategy('platform_configs', $warming_strategy);
    
    // Trigger cache warming
    $cache_manager->warm_cache();
    
    assert_cache_test($warming_executed, "Cache warming strategy executed");
    
    $warmed_data = $cache_manager->get('warmed_data', 'platform_configs');
    assert_cache_test($warmed_data === 'preloaded_value', "Warmed data available");
    
} catch (Exception $e) {
    echo "❌ Cache warming test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 10: Error Handling and Edge Cases
echo "--- Testing Error Handling and Edge Cases ---\n";

try {
    // Test invalid callback in remember
    $invalid_remember = $cache_manager->remember('invalid_test', 'not_a_callback', 'default');
    assert_cache_test($invalid_remember === false, "Invalid callback handling in remember");
    
    // Test empty key handling
    $empty_key_set = $cache_manager->set('', 'value', 'default');
    $empty_key_get = $cache_manager->get('', 'default');
    assert_cache_test($empty_key_get !== false || $empty_key_set === false, "Empty key handling");
    
    // Test null data caching
    $null_set = $cache_manager->set('null_test', null, 'default');
    $null_get = $cache_manager->get('null_test', 'default');
    assert_cache_test($null_get === null, "Null data caching");
    
    // Test very large key
    $large_key = str_repeat('x', 300);
    $large_key_set = $cache_manager->set($large_key, 'value', 'default');
    $large_key_get = $cache_manager->get($large_key, 'default');
    assert_cache_test($large_key_get === 'value', "Large key handling");
    
} catch (Exception $e) {
    echo "❌ Error handling test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 11: Performance Test
echo "--- Testing Cache Performance ---\n";

try {
    $start_time = microtime(true);
    
    // Perform multiple cache operations
    for ($i = 0; $i < 100; $i++) {
        $cache_manager->set("perf_test_$i", "value_$i", 'default', 300);
    }
    
    for ($i = 0; $i < 100; $i++) {
        $cache_manager->get("perf_test_$i", 'default');
    }
    
    $end_time = microtime(true);
    $duration = ($end_time - $start_time) * 1000; // Convert to milliseconds
    
    assert_cache_test($duration < 1000, "200 cache operations completed in under 1 second ({$duration}ms)");
    
    // Cleanup performance test data
    for ($i = 0; $i < 100; $i++) {
        $cache_manager->delete("perf_test_$i", 'default');
    }
    
} catch (Exception $e) {
    echo "❌ Performance test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "=== Cache Manager Test Summary ===\n";
echo "Total Tests: $total_tests\n";
echo "Passed: $passed_tests\n";
echo "Failed: " . ($total_tests - $passed_tests) . "\n";
echo "Success Rate: " . round(($passed_tests / $total_tests) * 100, 2) . "%\n";

if ($passed_tests === $total_tests) {
    echo "\n🎉 All Cache Manager tests passed!\n";
} else {
    echo "\n⚠️  Some Cache Manager tests failed. Please review the output above.\n";
}