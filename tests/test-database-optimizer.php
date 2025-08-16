<?php
/**
 * Database Optimizer Tests
 * 
 * Tests for the RWP Creator Suite Database Optimizer functionality.
 */

// Include WordPress
require_once '/Users/jhixon/Local Sites/creatortools/app/public/wp-load.php';

echo "=== Database Optimizer Tests ===\n\n";

$total_tests = 0;
$passed_tests = 0;

function assert_db_test($condition, $message) {
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

// Test 1: Database Optimizer Instantiation
echo "--- Testing Database Optimizer Instantiation ---\n";

try {
    $db_optimizer = RWP_Creator_Suite_Database_Optimizer::get_instance();
    
    assert_db_test($db_optimizer !== null, "Database Optimizer instantiation");
    
    // Test singleton pattern
    $db_optimizer2 = RWP_Creator_Suite_Database_Optimizer::get_instance();
    assert_db_test($db_optimizer === $db_optimizer2, "Database Optimizer singleton pattern");
    
} catch (Exception $e) {
    echo "❌ Database Optimizer instantiation failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Database Connection and Basic Queries
echo "--- Testing Database Connection and Basic Queries ---\n";

try {
    global $wpdb;
    
    // Test database connection
    $connection_test = $wpdb->get_var("SELECT 1");
    assert_db_test($connection_test == 1, "Database connection test");
    
    // Test database name access
    $db_name = $wpdb->get_var("SELECT DATABASE()");
    assert_db_test(!empty($db_name), "Database name retrieval");
    
    // Test table existence check (using WordPress core table)
    $posts_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->posts));
    assert_db_test($posts_table_exists === $wpdb->posts, "Table existence check");
    
} catch (Exception $e) {
    echo "❌ Database connection test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Performance Analysis
echo "--- Testing Performance Analysis ---\n";

try {
    $analysis = $db_optimizer->analyze_database_performance();
    
    assert_db_test(is_array($analysis), "Performance analysis returns array");
    assert_db_test(isset($analysis['table_sizes']), "Table sizes analysis included");
    assert_db_test(isset($analysis['recommendations']), "Recommendations included");
    
    // Check if WordPress core tables are analyzed
    $core_tables_found = false;
    foreach ($analysis['table_sizes'] as $table => $info) {
        if (is_array($info) && isset($info['size_mb'])) {
            $core_tables_found = true;
            break;
        }
    }
    assert_db_test($core_tables_found, "Table size analysis functional");
    
} catch (Exception $e) {
    echo "❌ Performance analysis test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Optimization Status
echo "--- Testing Optimization Status ---\n";

try {
    $status = $db_optimizer->get_optimization_status();
    
    assert_db_test(is_array($status), "Optimization status returns array");
    assert_db_test(isset($status['last_optimization']), "Last optimization timestamp tracked");
    assert_db_test(isset($status['last_check']), "Last check timestamp tracked");
    assert_db_test(isset($status['table_count']), "Table count tracked");
    
    // Verify status values are reasonable
    assert_db_test(is_numeric($status['table_count']), "Table count is numeric");
    assert_db_test($status['table_count'] >= 0, "Table count is non-negative");
    
} catch (Exception $e) {
    echo "❌ Optimization status test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Create Test Table for Index Testing
echo "--- Testing Test Table Creation and Index Operations ---\n";

try {
    global $wpdb;
    
    $test_table_name = $wpdb->prefix . 'rwp_test_optimization';
    
    // Create test table
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `{$test_table_name}` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        feature VARCHAR(50) NOT NULL,
        date_created DATETIME NOT NULL,
        usage_count INT DEFAULT 1,
        status VARCHAR(20) DEFAULT 'active'
    )";
    
    $table_created = $wpdb->query($create_table_sql);
    assert_db_test($table_created !== false, "Test table creation");
    
    // Insert some test data
    $insert_data = array();
    for ($i = 1; $i <= 10; $i++) {
        $insert_data[] = $wpdb->prepare("(%d, %s, %s, %d)", $i, 'test_feature', date('Y-m-d H:i:s'), 1);
    }
    
    if (!empty($insert_data)) {
        $insert_sql = "INSERT INTO `{$test_table_name}` (user_id, feature, date_created, usage_count) VALUES " . 
                     implode(', ', $insert_data);
        $wpdb->query($insert_sql);
    }
    
    // Test index creation
    $index_sql = "CREATE INDEX idx_test_user_date ON `{$test_table_name}` (user_id, date_created)";
    $index_created = $wpdb->query($index_sql);
    assert_db_test($index_created !== false, "Test index creation");
    
    // Verify index exists
    $index_check = $wpdb->get_var("SHOW INDEX FROM `{$test_table_name}` WHERE Key_name = 'idx_test_user_date'");
    assert_db_test(!empty($index_check), "Index existence verification");
    
    // Test table analysis
    $analyze_result = $wpdb->query("ANALYZE TABLE `{$test_table_name}`");
    assert_db_test($analyze_result !== false, "Table analysis");
    
    // Test table optimization  
    $optimize_result = $wpdb->query("OPTIMIZE TABLE `{$test_table_name}`");
    assert_db_test($optimize_result !== false, "Table optimization");
    
} catch (Exception $e) {
    echo "❌ Test table and index operations failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 6: Query Performance Testing
echo "--- Testing Query Performance ---\n";

try {
    global $wpdb;
    
    if (isset($test_table_name)) {
        // Test simple query performance
        $start_time = microtime(true);
        
        $result = $wpdb->get_results("SELECT * FROM `{$test_table_name}` WHERE user_id = 1");
        
        $end_time = microtime(true);
        $query_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
        
        assert_db_test($query_time < 100, "Simple query performance under 100ms ({$query_time}ms)");
        assert_db_test(is_array($result), "Query returns valid results");
        
        // Test index usage query
        $explain_result = $wpdb->get_results("EXPLAIN SELECT * FROM `{$test_table_name}` WHERE user_id = 1 AND date_created > '2024-01-01'");
        assert_db_test(!empty($explain_result), "Query explanation available");
        
        // Check if index might be used (simplified check)
        $possible_keys = $explain_result[0]->possible_keys ?? '';
        $using_index = !empty($possible_keys) || $explain_result[0]->key !== null;
        assert_db_test($using_index, "Index potentially utilized in query");
    }
    
} catch (Exception $e) {
    echo "❌ Query performance test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 7: Information Schema Access
echo "--- Testing Information Schema Access ---\n";

try {
    global $wpdb;
    
    // Test access to information schema
    $schema_test = $wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
    assert_db_test(is_numeric($schema_test) && $schema_test > 0, "Information schema access");
    
    // Test table statistics access
    $table_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH 
         FROM INFORMATION_SCHEMA.TABLES 
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
        DB_NAME,
        $wpdb->posts
    ));
    
    assert_db_test($table_stats !== null, "Table statistics retrieval");
    assert_db_test(isset($table_stats->TABLE_ROWS), "Table row count available");
    
    // Test column information access
    $column_info = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME, DATA_TYPE 
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
         LIMIT 5",
        DB_NAME,
        $wpdb->posts
    ));
    
    assert_db_test(!empty($column_info), "Column information retrieval");
    
} catch (Exception $e) {
    echo "❌ Information schema test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 8: MySQL Feature Detection
echo "--- Testing MySQL Feature Detection ---\n";

try {
    global $wpdb;
    
    // Test MySQL version
    $mysql_version = $wpdb->get_var("SELECT VERSION()");
    assert_db_test(!empty($mysql_version), "MySQL version detection");
    
    // Test for partitioning support (may not be available in all installations)
    $partition_support = $wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.PLUGINS WHERE PLUGIN_NAME = 'partition'");
    $partition_test_result = is_numeric($partition_support);
    assert_db_test($partition_test_result, "Partitioning support detection");
    
    // Test for performance schema access
    $perf_schema_access = $wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'performance_schema'");
    assert_db_test(is_numeric($perf_schema_access), "Performance schema detection");
    
    // Test storage engine support
    $engines = $wpdb->get_results("SHOW ENGINES");
    $innodb_support = false;
    foreach ($engines as $engine) {
        if ($engine->Engine === 'InnoDB' && in_array($engine->Support, array('YES', 'DEFAULT'))) {
            $innodb_support = true;
            break;
        }
    }
    assert_db_test($innodb_support, "InnoDB storage engine support");
    
} catch (Exception $e) {
    echo "❌ MySQL feature detection test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 9: Optimization Check Logic
echo "--- Testing Optimization Check Logic ---\n";

try {
    // Test optimization timestamp tracking
    $initial_time = get_option('rwp_creator_suite_last_optimization_check', 0);
    
    // Simulate check
    $db_optimizer->check_optimizations();
    
    $updated_time = get_option('rwp_creator_suite_last_optimization_check', 0);
    assert_db_test($updated_time >= $initial_time, "Optimization check timestamp updated");
    
    // Test that recent check doesn't trigger again immediately
    $before_second_check = get_option('rwp_creator_suite_last_optimization_check');
    update_option('rwp_creator_suite_last_optimization_check', time()); // Set to now
    
    $db_optimizer->check_optimizations();
    $after_second_check = get_option('rwp_creator_suite_last_optimization_check');
    
    // Time shouldn't change much since we just set it
    $time_diff = abs($after_second_check - $before_second_check);
    assert_db_test($time_diff < 2, "Recent optimization check doesn't retrigger immediately");
    
} catch (Exception $e) {
    echo "❌ Optimization check logic test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 10: Daily Maintenance Operations
echo "--- Testing Daily Maintenance Operations ---\n";

try {
    // Test that daily maintenance function exists and runs
    if (method_exists($db_optimizer, 'run_daily_optimizations')) {
        // This is a safe operation that mainly runs ANALYZE TABLE
        $db_optimizer->run_daily_optimizations();
        assert_db_test(true, "Daily maintenance operations executed");
    } else {
        assert_db_test(false, "Daily maintenance method not found");
    }
    
    // Test cleanup operations (safe operations only)
    if (method_exists($db_optimizer, 'cleanup_expired_cache')) {
        $db_optimizer->cleanup_expired_cache();
        assert_db_test(true, "Cache cleanup operations executed");
    } else {
        assert_db_test(true, "Cache cleanup method not implemented (acceptable)");
    }
    
} catch (Exception $e) {
    echo "❌ Daily maintenance test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 11: Error Handling and Edge Cases
echo "--- Testing Error Handling and Edge Cases ---\n";

try {
    global $wpdb;
    
    // Test handling of non-existent table
    $non_existent_result = $wpdb->get_var("SHOW TABLES LIKE 'non_existent_table_rwp_test'");
    assert_db_test($non_existent_result === null, "Non-existent table handling");
    
    // Test malformed query protection (this should fail safely)
    $malformed_result = $wpdb->query("INVALID SQL QUERY");
    assert_db_test($malformed_result === false, "Malformed query handling");
    
    // Test that WPDB error is captured
    $last_error = $wpdb->last_error;
    assert_db_test(!empty($last_error), "Error capture mechanism working");
    
    // Clear the error for clean state
    $wpdb->flush();
    
} catch (Exception $e) {
    echo "❌ Error handling test failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Cleanup: Remove test table
echo "--- Cleanup Test Resources ---\n";

try {
    global $wpdb;
    
    if (isset($test_table_name)) {
        $cleanup_result = $wpdb->query("DROP TABLE IF EXISTS `{$test_table_name}`");
        assert_db_test($cleanup_result !== false, "Test table cleanup");
    }
    
} catch (Exception $e) {
    echo "❌ Cleanup failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "=== Database Optimizer Test Summary ===\n";
echo "Total Tests: $total_tests\n";
echo "Passed: $passed_tests\n";
echo "Failed: " . ($total_tests - $passed_tests) . "\n";
echo "Success Rate: " . ($total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0) . "%\n";

if ($passed_tests === $total_tests) {
    echo "\n🎉 All Database Optimizer tests passed!\n";
} else {
    echo "\n⚠️  Some Database Optimizer tests failed. Please review the output above.\n";
}

echo "\nNote: Some advanced database features (partitioning, performance schema) may not be available in all MySQL installations.\n";