<?php
/**
 * Integration Test for Phase 2 Components
 * 
 * Tests how the new components work together in realistic scenarios.
 */

// Include WordPress
require_once '/Users/jhixon/Local Sites/creatortools/app/public/wp-load.php';

echo "=== Phase 2 Integration Tests ===\n\n";

$total_tests = 0;
$passed_tests = 0;

function assert_integration($condition, $message) {
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

// Integration Test 1: Service Container + Transient Manager
echo "--- Testing Service Container + Transient Manager Integration ---\n";

try {
    $container = RWP_Creator_Suite_Service_Container::get_instance();
    
    // Register Transient Manager as a service
    $transient_manager = RWP_Creator_Suite_Transient_Manager::get_instance();
    $container->register('transient_manager', $transient_manager);
    
    assert_integration($container->has('transient_manager'), "Transient Manager registered in Service Container");
    
    $retrieved_manager = $container->get('transient_manager');
    assert_integration($retrieved_manager === $transient_manager, "Same Transient Manager instance retrieved");
    
    // Test using transient manager through service container
    $retrieved_manager->set('integration_test', 'container_value');
    $value = $retrieved_manager->get('integration_test');
    assert_integration($value === 'container_value', "Transient operations work through Service Container");
    
} catch (Exception $e) {
    echo "❌ Integration test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Integration Test 2: API Validation + Rate Limiting Traits
echo "--- Testing API Validation + Rate Limiting Integration ---\n";

try {
    // Create a mock API class that uses both traits
    $api_class = new class {
        use RWP_Creator_Suite_API_Validation_Trait;
        use RWP_Creator_Suite_Rate_Limiting_Trait;
        
        public function process_request($data) {
            // Validate input
            $validation = $this->validate_description($data['description']);
            if (is_wp_error($validation)) {
                return $validation;
            }
            
            $platforms_validation = $this->validate_platforms($data['platforms']);
            if (is_wp_error($platforms_validation)) {
                return $platforms_validation;
            }
            
            // Check rate limit
            $rate_check = $this->check_rate_limit('api_request');
            if (is_wp_error($rate_check)) {
                return $rate_check;
            }
            
            // Track usage
            $this->track_usage('api_request');
            
            // Return success response
            return $this->success_response(array(
                'processed' => true,
                'description' => $data['description'],
                'platforms' => $data['platforms']
            ));
        }
    };
    
    // Test valid request
    $valid_data = array(
        'description' => 'This is a valid description for testing.',
        'platforms' => array('instagram', 'twitter')
    );
    
    $result = $api_class->process_request($valid_data);
    assert_integration(!is_wp_error($result), "Valid request processed successfully");
    
    if (!is_wp_error($result)) {
        $response_data = $result->get_data();
        assert_integration($response_data['success'] === true, "Success response format correct");
        assert_integration($response_data['data']['processed'] === true, "Response data correct");
    }
    
    // Test invalid request
    $invalid_data = array(
        'description' => '', // Empty description
        'platforms' => array('invalid_platform')
    );
    
    $result = $api_class->process_request($invalid_data);
    assert_integration(is_wp_error($result), "Invalid request properly rejected");
    
} catch (Exception $e) {
    echo "❌ API integration test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Integration Test 3: All Traits + Service Container
echo "--- Testing Complete Integration (All Components) ---\n";

try {
    // Create a comprehensive service class
    $comprehensive_service = new class {
        use RWP_Creator_Suite_API_Validation_Trait;
        use RWP_Creator_Suite_Rate_Limiting_Trait;
        use RWP_Creator_Suite_Guest_Handling_Trait;
        
        private $container;
        private $transient_manager;
        
        public function __construct() {
            $this->container = RWP_Creator_Suite_Service_Container::get_instance();
            $this->transient_manager = RWP_Creator_Suite_Transient_Manager::get_instance();
        }
        
        public function handle_guest_request($data) {
            // Check guest access
            $guest_check = $this->check_guest_access('content_repurposer');
            if (is_wp_error($guest_check)) {
                return $guest_check;
            }
            
            // Validate request for guests
            $request_mock_class = new class($data) {
                private $params;
                public function __construct($params) { $this->params = $params; }
                public function get_param($key) { return $this->params[$key] ?? null; }
            };
            
            $request = $request_mock_class;
            $validation = $this->validate_guest_request($request, 'content_repurposer');
            if (is_wp_error($validation)) {
                return $validation;
            }
            
            // Cache the result using transient manager
            $cache_key = 'guest_request_' . md5(serialize($data));
            $cached_result = $this->transient_manager->get($cache_key);
            
            if ($cached_result === false) {
                // Process request
                $result = array(
                    'processed' => true,
                    'data' => $data,
                    'timestamp' => time()
                );
                
                // Cache for 1 hour
                $this->transient_manager->set($cache_key, $result, HOUR_IN_SECONDS);
                $result['from_cache'] = false;
            } else {
                $result = $cached_result;
                $result['from_cache'] = true;
            }
            
            // Track usage
            $this->track_guest_usage('content_repurposer');
            
            return $this->success_response($result);
        }
    };
    
    // Test the comprehensive service
    $test_data = array(
        'content' => 'This is test content for repurposing.',
        'platforms' => array('twitter', 'linkedin')
    );
    
    $result = $comprehensive_service->handle_guest_request($test_data);
    assert_integration(!is_wp_error($result), "Comprehensive service handles request");
    
    if (!is_wp_error($result)) {
        $response_data = $result->get_data();
        assert_integration($response_data['success'] === true, "Comprehensive response success");
        assert_integration($response_data['data']['from_cache'] === false, "First request not from cache");
    }
    
    // Test cache hit
    $result2 = $comprehensive_service->handle_guest_request($test_data);
    if (!is_wp_error($result2)) {
        $response_data2 = $result2->get_data();
        assert_integration($response_data2['data']['from_cache'] === true, "Second request from cache");
    }
    
} catch (Exception $e) {
    echo "❌ Comprehensive integration test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Performance Test
echo "--- Performance Test ---\n";

try {
    $start_time = microtime(true);
    
    $container = RWP_Creator_Suite_Service_Container::get_instance();
    $transient_manager = RWP_Creator_Suite_Transient_Manager::get_instance();
    
    // Perform 100 operations
    for ($i = 0; $i < 100; $i++) {
        $transient_manager->set("perf_test_$i", "value_$i", HOUR_IN_SECONDS);
        $value = $transient_manager->get("perf_test_$i");
        $container->has('ai_service'); // Service lookup
    }
    
    $end_time = microtime(true);
    $duration = ($end_time - $start_time) * 1000; // Convert to milliseconds
    
    assert_integration($duration < 1000, "100 operations completed in under 1 second (took {$duration}ms)");
    
    // Cleanup
    for ($i = 0; $i < 100; $i++) {
        $transient_manager->delete("perf_test_$i");
    }
    
} catch (Exception $e) {
    echo "❌ Performance test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "=== Integration Test Summary ===\n";
echo "Total Integration Tests: $total_tests\n";
echo "Passed: $passed_tests\n";
echo "Failed: " . ($total_tests - $passed_tests) . "\n";
echo "Success Rate: " . round(($passed_tests / $total_tests) * 100, 2) . "%\n";

if ($passed_tests === $total_tests) {
    echo "\n🎉 All integration tests passed! Phase 2 components work together seamlessly.\n";
} else {
    echo "\n⚠️  Some integration tests failed. Please review the output above.\n";
}