<?php
/**
 * Tests for Service Container
 */

class Test_Service_Container extends WP_UnitTestCase {

    protected $container;

    public function setUp(): void {
        parent::setUp();
        
        // Reset singleton for testing
        $reflection = new ReflectionClass( 'RWP_Creator_Suite_Service_Container' );
        $instance = $reflection->getProperty( 'instance' );
        $instance->setAccessible( true );
        $instance->setValue( null );
        
        $this->container = RWP_Creator_Suite_Service_Container::get_instance();
        $this->container->clear();
    }

    public function tearDown(): void {
        $this->container->clear();
        parent::tearDown();
    }

    public function test_singleton_pattern() {
        $container1 = RWP_Creator_Suite_Service_Container::get_instance();
        $container2 = RWP_Creator_Suite_Service_Container::get_instance();
        
        $this->assertSame( $container1, $container2 );
    }

    public function test_register_and_get_singleton_service() {
        $test_service = new stdClass();
        $test_service->value = 'test';
        
        $this->container->register( 'test_service', $test_service );
        $retrieved = $this->container->get( 'test_service' );
        
        $this->assertSame( $test_service, $retrieved );
    }

    public function test_register_and_get_factory_service() {
        $factory = function() {
            $service = new stdClass();
            $service->value = 'factory_created';
            return $service;
        };
        
        $this->container->register_factory( 'factory_service', $factory );
        $service1 = $this->container->get( 'factory_service' );
        $service2 = $this->container->get( 'factory_service' );
        
        $this->assertEquals( 'factory_created', $service1->value );
        $this->assertEquals( 'factory_created', $service2->value );
        $this->assertNotSame( $service1, $service2 ); // Factory creates new instances
    }

    public function test_register_with_callable() {
        $callable = function() {
            $service = new stdClass();
            $service->value = 'callable_created';
            return $service;
        };
        
        $this->container->register( 'callable_service', $callable );
        $service1 = $this->container->get( 'callable_service' );
        $service2 = $this->container->get( 'callable_service' );
        
        $this->assertEquals( 'callable_created', $service1->value );
        $this->assertSame( $service1, $service2 ); // Singleton behavior
    }

    public function test_has_service() {
        $test_service = new stdClass();
        $this->container->register( 'test_service', $test_service );
        
        $this->assertTrue( $this->container->has( 'test_service' ) );
        $this->assertFalse( $this->container->has( 'non_existent_service' ) );
    }

    public function test_get_nonexistent_service_throws_exception() {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( "Service 'nonexistent' is not registered" );
        
        $this->container->get( 'nonexistent' );
    }

    public function test_register_empty_service_name_throws_exception() {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Service name cannot be empty' );
        
        $this->container->register( '', new stdClass() );
    }

    public function test_register_factory_empty_name_throws_exception() {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Service name cannot be empty' );
        
        $this->container->register_factory( '', function() {} );
    }

    public function test_register_factory_non_callable_throws_exception() {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Factory must be callable' );
        
        $this->container->register_factory( 'test', 'not_callable' );
    }

    public function test_get_registered_services() {
        $this->container->register( 'service1', new stdClass() );
        $this->container->register( 'service2', new stdClass(), false );
        $this->container->register_factory( 'service3', function() {} );
        
        $services = $this->container->get_registered_services();
        
        $this->assertContains( 'service1', $services );
        $this->assertContains( 'service2', $services );
        $this->assertContains( 'service3', $services );
    }

    public function test_get_stats() {
        $this->container->register( 'service1', new stdClass() );
        $this->container->register_factory( 'service2', function() {} );
        
        $stats = $this->container->get_stats();
        
        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total_services', $stats );
        $this->assertArrayHasKey( 'singletons_count', $stats );
        $this->assertArrayHasKey( 'factories_count', $stats );
        $this->assertArrayHasKey( 'initialized', $stats );
        
        $this->assertEquals( 2, $stats['total_services'] );
    }

    public function test_clear() {
        $this->container->register( 'service1', new stdClass() );
        $this->container->register_factory( 'service2', function() {} );
        
        $this->assertTrue( $this->container->has( 'service1' ) );
        $this->assertTrue( $this->container->has( 'service2' ) );
        
        $this->container->clear();
        
        $this->assertFalse( $this->container->has( 'service1' ) );
        $this->assertFalse( $this->container->has( 'service2' ) );
    }

    public function test_init_hooks() {
        $initial_stats = $this->container->get_stats();
        $this->assertFalse( $initial_stats['initialized'] );
        
        $this->container->init();
        
        $updated_stats = $this->container->get_stats();
        $this->assertTrue( $updated_stats['initialized'] );
    }

    /**
     * @group core_services
     */
    public function test_core_services_registration() {
        // AI Service should be registered
        $this->assertTrue( $this->container->has( 'ai_service' ) );
        
        // Network Utils should be registered
        $this->assertTrue( $this->container->has( 'network_utils' ) );
        
        // Error Logger should be registered
        $this->assertTrue( $this->container->has( 'error_logger' ) );
    }

    /**
     * @group core_services
     */
    public function test_core_services_instantiation() {
        if ( class_exists( 'RWP_Creator_Suite_AI_Service' ) ) {
            $ai_service = $this->container->get( 'ai_service' );
            $this->assertInstanceOf( 'RWP_Creator_Suite_AI_Service', $ai_service );
        }
        
        if ( class_exists( 'RWP_Creator_Suite_Network_Utils' ) ) {
            $network_utils = $this->container->get( 'network_utils' );
            $this->assertInstanceOf( 'RWP_Creator_Suite_Network_Utils', $network_utils );
        }
    }

    public function test_container_dependency_injection() {
        // Test that container is passed to factory functions
        $container_passed = null;
        
        $factory = function( $container ) use ( &$container_passed ) {
            $container_passed = $container;
            return new stdClass();
        };
        
        $this->container->register( 'di_test', $factory );
        $this->container->get( 'di_test' );
        
        $this->assertSame( $this->container, $container_passed );
    }

    public function test_non_singleton_services() {
        $counter = 0;
        $factory = function() use ( &$counter ) {
            $counter++;
            $service = new stdClass();
            $service->counter = $counter;
            return $service;
        };
        
        $this->container->register( 'non_singleton', $factory, false );
        
        $service1 = $this->container->get( 'non_singleton' );
        $service2 = $this->container->get( 'non_singleton' );
        
        // Each call should create a new instance
        $this->assertEquals( 1, $service1->counter );
        $this->assertEquals( 2, $service2->counter );
        $this->assertNotSame( $service1, $service2 );
    }
}