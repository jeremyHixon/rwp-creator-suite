<?php
/**
 * Event System Tests
 * 
 * Tests for the RWP Creator Suite Event System functionality.
 */

// Include WordPress
require_once '/Users/jhixon/Local Sites/creatortools/app/public/wp-load.php';

echo "=== Event System Tests ===\n\n";

$total_tests = 0;
$passed_tests = 0;

function assert_event_test($condition, $message) {
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

// Test 1: Event System Instantiation
echo "--- Testing Event System Instantiation ---\n";

try {
    $event_system = RWP_Creator_Suite_Event_System::get_instance();
    
    assert_event_test($event_system !== null, "Event System instantiation");
    
    // Test singleton pattern
    $event_system2 = RWP_Creator_Suite_Event_System::get_instance();
    assert_event_test($event_system === $event_system2, "Event System singleton pattern");
    
} catch (Exception $e) {
    echo "❌ Event System instantiation failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Event Listener Registration
echo "--- Testing Event Listener Registration ---\n";

try {
    $callback_executed = false;
    $test_callback = function($data) use (&$callback_executed) {
        $callback_executed = true;
        return "callback_result";
    };
    
    $result = $event_system->listen('test_event', $test_callback);
    assert_event_test($result === true, "Event listener registration");
    
    // Test invalid callback
    $invalid_result = $event_system->listen('test_event', 'invalid_callback');
    assert_event_test($invalid_result === false, "Invalid callback rejection");
    
} catch (Exception $e) {
    echo "❌ Event listener registration failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Event Emission
echo "--- Testing Event Emission ---\n";

try {
    $callback_executed = false;
    $received_data = null;
    
    $test_callback = function($data) use (&$callback_executed, &$received_data) {
        $callback_executed = true;
        $received_data = $data;
        return "success";
    };
    
    $event_system->listen('emit_test_event', $test_callback);
    
    $test_data = array('key' => 'value', 'number' => 123);
    $results = $event_system->emit('emit_test_event', $test_data);
    
    assert_event_test($callback_executed, "Event callback execution");
    assert_event_test($received_data === $test_data['key'], "Event data transmission");
    assert_event_test(is_array($results) && count($results) > 0, "Event results returned");
    
} catch (Exception $e) {
    echo "❌ Event emission failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Multiple Listeners and Priority
echo "--- Testing Multiple Listeners and Priority ---\n";

try {
    $execution_order = array();
    
    $callback1 = function() use (&$execution_order) {
        $execution_order[] = 'callback1';
        return 'result1';
    };
    
    $callback2 = function() use (&$execution_order) {
        $execution_order[] = 'callback2';
        return 'result2';
    };
    
    $callback3 = function() use (&$execution_order) {
        $execution_order[] = 'callback3';
        return 'result3';
    };
    
    // Register with different priorities
    $event_system->listen('priority_test', $callback2, 20); // Lower priority (higher number)
    $event_system->listen('priority_test', $callback1, 10); // Higher priority (lower number)
    $event_system->listen('priority_test', $callback3, 5);  // Highest priority
    
    $results = $event_system->emit('priority_test');
    
    assert_event_test($execution_order === array('callback3', 'callback1', 'callback2'), "Priority-based execution order");
    assert_event_test(count($results) === 3, "All callbacks executed");
    
} catch (Exception $e) {
    echo "❌ Multiple listeners test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Event Middleware
echo "--- Testing Event Middleware ---\n";

try {
    $middleware_executed = false;
    $modified_data = null;
    
    // Before middleware
    $before_middleware = function($event_name, $data) use (&$middleware_executed, &$modified_data) {
        $middleware_executed = true;
        $data['middleware_added'] = 'test_value';
        $modified_data = $data;
        return $data;
    };
    
    $event_system->add_middleware('before', $before_middleware);
    
    $test_callback = function($data) {
        return array('received_data' => $data);
    };
    
    $event_system->listen('middleware_test', $test_callback);
    
    $original_data = array('original' => 'value');
    $results = $event_system->emit('middleware_test', $original_data);
    
    assert_event_test($middleware_executed, "Middleware execution");
    assert_event_test(isset($modified_data['middleware_added']), "Middleware data modification");
    
} catch (Exception $e) {
    echo "❌ Event middleware test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 6: Core Events Definition
echo "--- Testing Core Events Definition ---\n";

try {
    $registry = $event_system->get_registry();
    
    // Check for core events
    $core_events = array(
        'rwp_ai_request_started',
        'rwp_ai_request_completed', 
        'rwp_ai_request_failed',
        'rwp_user_quota_exceeded',
        'rwp_guest_conversion',
        'rwp_content_generated'
    );
    
    $events_found = 0;
    foreach ($core_events as $event) {
        if (isset($registry[$event])) {
            $events_found++;
        }
    }
    
    assert_event_test($events_found === count($core_events), "Core events defined ({$events_found}/" . count($core_events) . ")");
    
} catch (Exception $e) {
    echo "❌ Core events test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 7: Event Registry and Listener Management
echo "--- Testing Event Registry and Listener Management ---\n";

try {
    $event_system->clear_listeners('registry_test');
    
    $callback1 = function() { return 'test1'; };
    $callback2 = function() { return 'test2'; };
    
    $event_system->listen('registry_test', $callback1);
    $event_system->listen('registry_test', $callback2);
    
    $has_listeners = $event_system->has_listeners('registry_test');
    assert_event_test($has_listeners, "Event has listeners check");
    
    $registry = $event_system->get_registry();
    $listener_count = $registry['registry_test']['listener_count'] ?? 0;
    assert_event_test($listener_count === 2, "Correct listener count in registry");
    
    // Test unlisten
    $event_system->unlisten('registry_test', $callback1);
    $registry_after = $event_system->get_registry();
    $listener_count_after = $registry_after['registry_test']['listener_count'] ?? 0;
    assert_event_test($listener_count_after === 1, "Listener removal");
    
    // Test clear all listeners
    $event_system->clear_listeners('registry_test');
    $has_listeners_after_clear = $event_system->has_listeners('registry_test');
    assert_event_test(!$has_listeners_after_clear, "Clear all listeners");
    
} catch (Exception $e) {
    echo "❌ Event registry test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 8: Performance Metrics
echo "--- Testing Performance Metrics ---\n";

try {
    // Clear previous metrics by getting new instance
    $event_system->set_debug(true);
    
    $callback = function() { 
        // Small delay to test timing
        usleep(1000); // 1ms
        return 'performance_test'; 
    };
    
    $event_system->listen('performance_test', $callback);
    
    // Emit multiple times
    for ($i = 0; $i < 3; $i++) {
        $event_system->emit('performance_test');
    }
    
    $metrics = $event_system->get_metrics('performance_test');
    
    assert_event_test(isset($metrics['total_executions']), "Metrics tracking enabled");
    assert_event_test($metrics['total_executions'] === 3, "Execution count tracking");
    assert_event_test(isset($metrics['average_time']), "Average time tracking");
    
} catch (Exception $e) {
    echo "❌ Performance metrics test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 9: Scoped Event Emitter
echo "--- Testing Scoped Event Emitter ---\n";

try {
    $scoped_emitter = $event_system->create_scoped_emitter('test_context');
    
    $callback_executed = false;
    $received_context = null;
    
    $callback = function($data) use (&$callback_executed, &$received_context) {
        $callback_executed = true;
        $received_context = $data['_context'] ?? null;
        return 'scoped_result';
    };
    
    $scoped_emitter->listen('scoped_event', $callback);
    $results = $scoped_emitter->emit('scoped_event', array('test' => 'data'));
    
    assert_event_test($callback_executed, "Scoped emitter callback execution");
    assert_event_test($received_context === 'test_context', "Context injection");
    
} catch (Exception $e) {
    echo "❌ Scoped event emitter test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 10: Error Handling
echo "--- Testing Error Handling ---\n";

try {
    $error_callback = function() {
        throw new Exception("Test error");
    };
    
    $success_callback = function() {
        return "success";
    };
    
    $event_system->listen('error_test', $error_callback);
    $event_system->listen('error_test', $success_callback);
    
    $results = $event_system->emit('error_test');
    
    // Should have results from both callbacks, with error recorded
    assert_event_test(count($results) === 2, "Both callbacks processed despite error");
    
    $has_error = false;
    foreach ($results as $result) {
        if (isset($result['error'])) {
            $has_error = true;
            break;
        }
    }
    
    assert_event_test($has_error, "Error captured in results");
    
} catch (Exception $e) {
    echo "❌ Error handling test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "=== Event System Test Summary ===\n";
echo "Total Tests: $total_tests\n";
echo "Passed: $passed_tests\n";
echo "Failed: " . ($total_tests - $passed_tests) . "\n";
echo "Success Rate: " . ($total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0) . "%\n";

if ($passed_tests === $total_tests) {
    echo "\n🎉 All Event System tests passed!\n";
} else {
    echo "\n⚠️  Some Event System tests failed. Please review the output above.\n";
}