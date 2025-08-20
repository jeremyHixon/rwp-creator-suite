<?php
/**
 * User Value Test Runner
 * 
 * Comprehensive test runner for all user value components.
 * Tests Event System, Cache Manager, Database Optimizer, and integration.
 */

// Include WordPress
require_once '/Users/jhixon/Local Sites/creatortools/app/public/wp-load.php';

echo "=== RWP Creator Suite User Value Test Runner ===\n\n";

$total_tests = 0;
$passed_tests = 0;
$test_results = array();

function log_test_result($component, $total, $passed, $success_rate) {
    global $test_results, $total_tests, $passed_tests;
    
    $test_results[] = array(
        'component' => $component,
        'total' => $total,
        'passed' => $passed,
        'failed' => $total - $passed,
        'success_rate' => $success_rate
    );
    
    $total_tests += $total;
    $passed_tests += $passed;
}

// Function to capture test output
function run_test_file($file_path, $component_name) {
    echo "Running {$component_name} tests...\n";
    
    ob_start();
    $start_time = microtime(true);
    
    include $file_path;
    
    $end_time = microtime(true);
    $output = ob_get_clean();
    
    // Parse test results from output
    preg_match('/Total Tests: (\d+)/', $output, $total_matches);
    preg_match('/Passed: (\d+)/', $output, $passed_matches);
    preg_match('/Success Rate: ([\d.]+)%/', $output, $rate_matches);
    
    $total = isset($total_matches[1]) ? (int)$total_matches[1] : 0;
    $passed = isset($passed_matches[1]) ? (int)$passed_matches[1] : 0;
    $rate = isset($rate_matches[1]) ? (float)$rate_matches[1] : 0;
    
    $duration = ($end_time - $start_time) * 1000;
    
    echo "✓ {$component_name}: {$passed}/{$total} tests passed ({$rate}%) in " . round($duration, 2) . "ms\n";
    
    if ($rate < 100) {
        echo "⚠️  Failed tests in {$component_name}:\n";
        $failed_lines = array_filter(explode("\n", $output), function($line) {
            return strpos($line, '❌ FAIL:') !== false;
        });
        foreach ($failed_lines as $line) {
            echo "  " . trim($line) . "\n";
        }
    }
    
    log_test_result($component_name, $total, $passed, $rate);
    
    return array(
        'output' => $output,
        'total' => $total,
        'passed' => $passed,
        'rate' => $rate,
        'duration' => $duration
    );
}

// Test 1: Event System
echo "=== User Value Component Tests ===\n";
$event_system_results = run_test_file(__DIR__ . '/test-event-system.php', 'Event System');

echo "\n";

// Test 2: Cache Manager  
$cache_manager_results = run_test_file(__DIR__ . '/test-cache-manager.php', 'Cache Manager');

echo "\n";

// Test 3: Database Optimizer
$database_optimizer_results = run_test_file(__DIR__ . '/test-database-optimizer.php', 'Database Optimizer');

echo "\n";

// Integration Tests
echo "=== User Value Integration Tests ===\n";

$integration_total = 0;
$integration_passed = 0;

function assert_integration_test($condition, $message) {
    global $integration_total, $integration_passed;
    $integration_total++;
    if ($condition) {
        echo "✅ PASS: $message\n";
        $integration_passed++;
        return true;
    } else {
        echo "❌ FAIL: $message\n";
        return false;
    }
}

// Integration Test 1: Event System + Cache Manager
echo "--- Testing Event System + Cache Manager Integration ---\n";

try {
    $event_system = RWP_Creator_Suite_Event_System::get_instance();
    $cache_manager = RWP_Creator_Suite_Cache_Manager::get_instance();
    
    // Test event-driven cache invalidation
    $cache_invalidated = false;
    $invalidation_listener = function($data) use (&$cache_invalidated) {
        $cache_invalidated = true;
    };
    
    $event_system->listen('rwp_cache_invalidated', $invalidation_listener);
    
    // Set cache data
    $cache_manager->set('integration_test', 'test_value', 'settings');
    
    // Trigger cache invalidation via event
    $cache_manager->invalidate('settings', 'group');
    
    assert_integration_test($cache_invalidated, "Event-driven cache invalidation");
    
    // Verify cache was actually invalidated
    $cached_value = $cache_manager->get('integration_test', 'settings');
    assert_integration_test($cached_value === false, "Cache data actually invalidated");
    
} catch (Exception $e) {
    echo "❌ Event System + Cache Manager integration failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Integration Test 2: All User Value Components Working Together
echo "--- Testing Complete User Value Integration ---\n";

try {
    $event_system = RWP_Creator_Suite_Event_System::get_instance();
    $cache_manager = RWP_Creator_Suite_Cache_Manager::get_instance();
    $db_optimizer = RWP_Creator_Suite_Database_Optimizer::get_instance();
    
    // Create a comprehensive workflow
    $workflow_steps = array();
    
    // Step 1: Cache some data
    $cache_manager->set('workflow_data', array('step' => 1, 'data' => 'initial'), 'default');
    $workflow_steps[] = 'cache_set';
    
    // Step 2: Emit event about data being cached
    $event_results = $event_system->emit('rwp_content_generated', array(
        'content_type' => 'workflow_test',
        'user_id' => 1,
        'platforms' => array('test'),
        'content_data' => 'workflow_content'
    ));
    $workflow_steps[] = 'event_emitted';
    
    // Step 3: Get database optimization status
    $optimization_status = $db_optimizer->get_optimization_status();
    $workflow_steps[] = 'db_status_checked';
    
    // Step 4: Retrieve cached data
    $cached_workflow_data = $cache_manager->get('workflow_data', 'default');
    $workflow_steps[] = 'cache_retrieved';
    
    assert_integration_test(count($workflow_steps) === 4, "All workflow steps completed");
    assert_integration_test($cached_workflow_data['step'] === 1, "Workflow data integrity maintained");
    assert_integration_test(is_array($optimization_status), "Database status accessible");
    assert_integration_test(!empty($event_results), "Event system responsive");
    
} catch (Exception $e) {
    echo "❌ Complete user value integration failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Integration Test 3: Service Container Integration
echo "--- Testing Service Container + User Value Integration ---\n";

try {
    $container = RWP_Creator_Suite_Service_Container::get_instance();
    
    // Register user value services
    $container->register('event_system', RWP_Creator_Suite_Event_System::get_instance());
    $container->register('cache_manager', RWP_Creator_Suite_Cache_Manager::get_instance());
    $container->register('database_optimizer', RWP_Creator_Suite_Database_Optimizer::get_instance());
    
    // Test service retrieval
    $event_service = $container->get('event_system');
    $cache_service = $container->get('cache_manager');
    $db_service = $container->get('database_optimizer');
    
    assert_integration_test($event_service !== null, "Event System service registered");
    assert_integration_test($cache_service !== null, "Cache Manager service registered");
    assert_integration_test($db_service !== null, "Database Optimizer service registered");
    
    // Test services are functional through container
    $cache_service->set('container_test', 'service_value', 'default');
    $container_cached = $cache_service->get('container_test', 'default');
    assert_integration_test($container_cached === 'service_value', "Services functional through container");
    
} catch (Exception $e) {
    echo "❌ Service Container + user value integration failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Performance Test
echo "--- Testing User Value Performance ---\n";

try {
    $start_time = microtime(true);
    
    $event_system = RWP_Creator_Suite_Event_System::get_instance();
    $cache_manager = RWP_Creator_Suite_Cache_Manager::get_instance();
    
    // Perform 50 combined operations
    for ($i = 0; $i < 50; $i++) {
        // Cache operation
        $cache_manager->set("perf_test_$i", "value_$i", 'default');
        
        // Event emission
        $event_system->emit('rwp_ai_request_completed', array(
            'request_type' => 'performance_test',
            'user_id' => $i,
            'processing_time' => 0.1
        ));
        
        // Cache retrieval
        $cache_manager->get("perf_test_$i", 'default');
    }
    
    $end_time = microtime(true);
    $duration = ($end_time - $start_time) * 1000;
    
    assert_integration_test($duration < 2000, "150 user value operations completed in under 2 seconds ({$duration}ms)");
    
    // Cleanup
    for ($i = 0; $i < 50; $i++) {
        $cache_manager->delete("perf_test_$i", 'default');
    }
    
} catch (Exception $e) {
    echo "❌ User value performance test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Add integration results to summary
log_test_result('User Value Integration', $integration_total, $integration_passed, 
               $integration_total > 0 ? round(($integration_passed / $integration_total) * 100, 2) : 0);

// Generate comprehensive report
echo "=== User Value Test Summary Report ===\n\n";

$overall_success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;

echo "Overall Results:\n";
echo "Total Tests: $total_tests\n";
echo "Passed: $passed_tests\n";
echo "Failed: " . ($total_tests - $passed_tests) . "\n";
echo "Success Rate: {$overall_success_rate}%\n\n";

echo "Component Breakdown:\n";
foreach ($test_results as $result) {
    $status_icon = $result['success_rate'] >= 95 ? '🟢' : ($result['success_rate'] >= 85 ? '🟡' : '🔴');
    echo "{$status_icon} {$result['component']}: {$result['passed']}/{$result['total']} ({$result['success_rate']}%)\n";
}

echo "\n";

// Performance summary
$component_times = array(
    'Event System' => $event_system_results['duration'],
    'Cache Manager' => $cache_manager_results['duration'],
    'Database Optimizer' => $database_optimizer_results['duration']
);

echo "Performance Summary:\n";
foreach ($component_times as $component => $time) {
    echo "- {$component}: " . round($time, 2) . "ms\n";
}

$total_time = array_sum($component_times);
echo "- Total Test Time: " . round($total_time, 2) . "ms\n\n";

// Final assessment
if ($overall_success_rate >= 95) {
    echo "🎉 EXCELLENT: User value implementation is highly successful!\n";
    echo "All components are working correctly and integration is seamless.\n";
} elseif ($overall_success_rate >= 85) {
    echo "✅ GOOD: User value implementation is mostly successful!\n";
    echo "Minor issues detected but overall functionality is solid.\n";
} elseif ($overall_success_rate >= 70) {
    echo "⚠️  FAIR: User value implementation has some issues.\n";
    echo "Review failed tests and address issues before production use.\n";
} else {
    echo "❌ POOR: User value implementation has significant issues.\n";
    echo "Major problems detected. Requires thorough review and fixes.\n";
}

echo "\n";

// Recommendations
echo "=== Recommendations ===\n";

if ($overall_success_rate < 100) {
    echo "- Review and fix failed tests before deployment\n";
}

if ($total_time > 5000) {
    echo "- Consider optimizing test performance (current time: " . round($total_time, 2) . "ms)\n";
}

$lowest_performing = min($test_results);
if ($lowest_performing['success_rate'] < 90) {
    echo "- Focus on improving {$lowest_performing['component']} (lowest success rate: {$lowest_performing['success_rate']}%)\n";
}

echo "- Run integration tests in production environment\n";
echo "- Monitor performance metrics after deployment\n";
echo "- Consider implementing automated testing pipeline\n";

echo "\n=== User Value Testing Complete ===\n";