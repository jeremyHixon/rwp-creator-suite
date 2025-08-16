<?php
/**
 * Tests for Transient Manager
 */

class Test_Transient_Manager extends WP_UnitTestCase {

    protected $manager;

    public function setUp(): void {
        parent::setUp();
        
        // Reset singleton for testing
        $reflection = new ReflectionClass( 'RWP_Creator_Suite_Transient_Manager' );
        $instance = $reflection->getProperty( 'instance' );
        $instance->setAccessible( true );
        $instance->setValue( null );
        
        $this->manager = RWP_Creator_Suite_Transient_Manager::get_instance();
        $this->manager->reset_stats();
    }

    public function tearDown(): void {
        $this->manager->flush_all();
        parent::tearDown();
    }

    public function test_singleton_pattern() {
        $manager1 = RWP_Creator_Suite_Transient_Manager::get_instance();
        $manager2 = RWP_Creator_Suite_Transient_Manager::get_instance();
        
        $this->assertSame( $manager1, $manager2 );
    }

    public function test_set_and_get_transient() {
        $test_data = array( 'key' => 'value', 'number' => 123 );
        
        $result = $this->manager->set( 'test_key', $test_data, HOUR_IN_SECONDS );
        $this->assertTrue( $result );
        
        $retrieved = $this->manager->get( 'test_key' );
        $this->assertEquals( $test_data, $retrieved );
    }

    public function test_get_nonexistent_transient() {
        $result = $this->manager->get( 'nonexistent_key' );
        $this->assertFalse( $result );
    }

    public function test_delete_transient() {
        $test_data = 'test_value';
        
        $this->manager->set( 'delete_test', $test_data );
        $this->assertEquals( $test_data, $this->manager->get( 'delete_test' ) );
        
        $result = $this->manager->delete( 'delete_test' );
        $this->assertTrue( $result );
        
        $this->assertFalse( $this->manager->get( 'delete_test' ) );
    }

    public function test_group_functionality() {
        $this->manager->set( 'key1', 'value1', HOUR_IN_SECONDS, 'group1' );
        $this->manager->set( 'key2', 'value2', HOUR_IN_SECONDS, 'group1' );
        $this->manager->set( 'key3', 'value3', HOUR_IN_SECONDS, 'group2' );
        
        $group1_data = $this->manager->get_group( 'group1' );
        
        $this->assertCount( 2, $group1_data );
        $this->assertArrayHasKey( 'rwp_creator_suite_group1_key1', $group1_data );
        $this->assertArrayHasKey( 'rwp_creator_suite_group1_key2', $group1_data );
    }

    public function test_delete_group() {
        $this->manager->set( 'key1', 'value1', HOUR_IN_SECONDS, 'test_group' );
        $this->manager->set( 'key2', 'value2', HOUR_IN_SECONDS, 'test_group' );
        $this->manager->set( 'key3', 'value3', HOUR_IN_SECONDS, 'other_group' );
        
        $deleted_count = $this->manager->delete_group( 'test_group' );
        $this->assertEquals( 2, $deleted_count );
        
        // Check that test_group items are gone
        $this->assertFalse( $this->manager->get( 'key1', 'test_group' ) );
        $this->assertFalse( $this->manager->get( 'key2', 'test_group' ) );
        
        // Check that other_group item remains
        $this->assertEquals( 'value3', $this->manager->get( 'key3', 'other_group' ) );
    }

    public function test_remember_cache_hit() {
        $callback_called = false;
        $callback = function() use ( &$callback_called ) {
            $callback_called = true;
            return 'generated_value';
        };
        
        // First call should execute callback
        $result1 = $this->manager->remember( 'remember_test', $callback );
        $this->assertEquals( 'generated_value', $result1 );
        $this->assertTrue( $callback_called );
        
        // Reset flag
        $callback_called = false;
        
        // Second call should use cache
        $result2 = $this->manager->remember( 'remember_test', $callback );
        $this->assertEquals( 'generated_value', $result2 );
        $this->assertFalse( $callback_called );
    }

    public function test_remember_cache_miss() {
        $callback = function() {
            return 'fresh_value';
        };
        
        $result = $this->manager->remember( 'new_key', $callback );
        $this->assertEquals( 'fresh_value', $result );
        
        // Verify it was cached
        $cached_result = $this->manager->get( 'new_key' );
        $this->assertEquals( 'fresh_value', $cached_result );
    }

    public function test_delete_by_pattern() {
        $this->manager->set( 'pattern_test_1', 'value1' );
        $this->manager->set( 'pattern_test_2', 'value2' );
        $this->manager->set( 'other_key', 'value3' );
        
        $deleted_count = $this->manager->delete_by_pattern( 'pattern_test_*' );
        $this->assertEquals( 2, $deleted_count );
        
        $this->assertFalse( $this->manager->get( 'pattern_test_1' ) );
        $this->assertFalse( $this->manager->get( 'pattern_test_2' ) );
        $this->assertEquals( 'value3', $this->manager->get( 'other_key' ) );
    }

    public function test_expiration_validation() {
        // Test minimum expiration
        $this->manager->set( 'test_min', 'value', 30 ); // 30 seconds, should be increased to 1 minute
        
        // Test maximum expiration
        $this->manager->set( 'test_max', 'value', WEEK_IN_SECONDS * 2 ); // 2 weeks, should be reduced to 1 week
        
        // Both should be successfully set despite invalid expiration times
        $this->assertEquals( 'value', $this->manager->get( 'test_min' ) );
        $this->assertEquals( 'value', $this->manager->get( 'test_max' ) );
    }

    public function test_stats_tracking() {
        $initial_stats = $this->manager->get_stats();
        
        // Perform operations
        $this->manager->set( 'stats_test', 'value' );
        $this->manager->get( 'stats_test' ); // Hit
        $this->manager->get( 'nonexistent' ); // Miss
        $this->manager->delete( 'stats_test' );
        
        $final_stats = $this->manager->get_stats();
        
        $this->assertEquals( $initial_stats['sets'] + 1, $final_stats['sets'] );
        $this->assertEquals( $initial_stats['hits'] + 1, $final_stats['hits'] );
        $this->assertEquals( $initial_stats['misses'] + 1, $final_stats['misses'] );
        $this->assertEquals( $initial_stats['deletes'] + 1, $final_stats['deletes'] );
        
        $this->assertArrayHasKey( 'hit_rate_percent', $final_stats );
    }

    public function test_memory_stats() {
        $this->manager->set( 'memory_test_1', 'small_value' );
        $this->manager->set( 'memory_test_2', str_repeat( 'large_value', 100 ) );
        
        $stats = $this->manager->get_memory_stats();
        
        $this->assertArrayHasKey( 'total_transients', $stats );
        $this->assertArrayHasKey( 'total_size_bytes', $stats );
        $this->assertArrayHasKey( 'total_size_human', $stats );
        
        $this->assertGreaterThan( 0, $stats['total_transients'] );
        $this->assertGreaterThan( 0, $stats['total_size_bytes'] );
    }

    public function test_key_length_limitation() {
        $very_long_key = str_repeat( 'a', 200 ); // Longer than WordPress 172 char limit
        
        $result = $this->manager->set( $very_long_key, 'value' );
        $this->assertTrue( $result );
        
        $retrieved = $this->manager->get( $very_long_key );
        $this->assertEquals( 'value', $retrieved );
    }

    public function test_flush_all() {
        $this->manager->set( 'flush_test_1', 'value1' );
        $this->manager->set( 'flush_test_2', 'value2', HOUR_IN_SECONDS, 'group1' );
        
        $deleted_count = $this->manager->flush_all();
        $this->assertGreaterThan( 0, $deleted_count );
        
        $this->assertFalse( $this->manager->get( 'flush_test_1' ) );
        $this->assertFalse( $this->manager->get( 'flush_test_2', 'group1' ) );
    }

    public function test_remember_with_false_return() {
        $callback = function() {
            return false;
        };
        
        $result = $this->manager->remember( 'false_test', $callback );
        $this->assertFalse( $result );
        
        // Should not be cached when callback returns false
        $cached = $this->manager->get( 'false_test' );
        $this->assertFalse( $cached );
    }

    public function test_remember_with_non_callable() {
        $result = $this->manager->remember( 'non_callable_test', 'not_callable' );
        $this->assertFalse( $result );
    }

    /**
     * @group hooks
     */
    public function test_cleanup_hooks_scheduled() {
        // Check if cleanup hook is scheduled
        $this->assertNotFalse( wp_next_scheduled( 'rwp_creator_suite_cleanup_transients' ) );
    }

    public function test_group_with_empty_key() {
        $this->manager->set( '', 'empty_key_value', HOUR_IN_SECONDS, 'test_group' );
        
        $result = $this->manager->get( '', 'test_group' );
        $this->assertEquals( 'empty_key_value', $result );
    }

    public function test_key_sanitization() {
        $unsafe_key = 'test key with spaces & special chars!@#';
        
        $this->manager->set( $unsafe_key, 'sanitized_value' );
        $result = $this->manager->get( $unsafe_key );
        
        $this->assertEquals( 'sanitized_value', $result );
    }

    public function test_complex_data_storage() {
        $complex_data = array(
            'string' => 'test',
            'number' => 123,
            'array' => array( 1, 2, 3 ),
            'object' => (object) array( 'prop' => 'value' ),
            'null' => null,
            'boolean' => true,
        );
        
        $this->manager->set( 'complex_test', $complex_data );
        $retrieved = $this->manager->get( 'complex_test' );
        
        $this->assertEquals( $complex_data, $retrieved );
    }
}