<?php
/**
 * Service Container
 * 
 * Centralized dependency injection container for better testability and modularity.
 * Implements singleton pattern with lazy loading for performance optimization.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Service_Container {

    private static $instance = null;
    private $services = array();
    private $singletons = array();
    private $factories = array();
    private $initialized = false;

    /**
     * Get single instance of the service container.
     *
     * @return RWP_Creator_Suite_Service_Container
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct() {
        $this->register_core_services();
    }

    /**
     * Initialize the service container.
     */
    public function init() {
        if ( $this->initialized ) {
            return;
        }

        do_action( 'rwp_creator_suite_before_services_init', $this );
        
        $this->initialized = true;
        
        do_action( 'rwp_creator_suite_after_services_init', $this );
    }

    /**
     * Register a service with the container.
     *
     * @param string $service_name Service identifier.
     * @param mixed  $service Service instance or factory callable.
     * @param bool   $singleton Whether this should be a singleton.
     */
    public function register( $service_name, $service, $singleton = true ) {
        if ( empty( $service_name ) ) {
            throw new InvalidArgumentException( 'Service name cannot be empty' );
        }

        if ( $singleton ) {
            $this->singletons[ $service_name ] = $service;
        } else {
            $this->services[ $service_name ] = $service;
        }
    }

    /**
     * Register a factory for creating service instances.
     *
     * @param string   $service_name Service identifier.
     * @param callable $factory Factory function.
     */
    public function register_factory( $service_name, $factory ) {
        if ( empty( $service_name ) ) {
            throw new InvalidArgumentException( 'Service name cannot be empty' );
        }

        if ( ! is_callable( $factory ) ) {
            throw new InvalidArgumentException( 'Factory must be callable' );
        }

        $this->factories[ $service_name ] = $factory;
    }

    /**
     * Get a service from the container.
     *
     * @param string $service_name Service identifier.
     * @return mixed Service instance.
     * @throws InvalidArgumentException If service is not registered.
     */
    public function get( $service_name ) {
        // Check if service is already instantiated
        if ( isset( $this->services[ $service_name ] ) ) {
            return $this->services[ $service_name ];
        }

        // Check for singleton
        if ( isset( $this->singletons[ $service_name ] ) ) {
            $service = $this->singletons[ $service_name ];
            
            // If it's a callable, instantiate it
            if ( is_callable( $service ) && ! is_object( $service ) ) {
                $service = call_user_func( $service, $this );
                $this->singletons[ $service_name ] = $service;
            }
            
            return $service;
        }

        // Check for factory
        if ( isset( $this->factories[ $service_name ] ) ) {
            return call_user_func( $this->factories[ $service_name ], $this );
        }

        throw new InvalidArgumentException( "Service '{$service_name}' is not registered" );
    }

    /**
     * Check if a service is registered.
     *
     * @param string $service_name Service identifier.
     * @return bool
     */
    public function has( $service_name ) {
        return isset( $this->services[ $service_name ] ) 
            || isset( $this->singletons[ $service_name ] ) 
            || isset( $this->factories[ $service_name ] );
    }

    /**
     * Get all registered service names.
     *
     * @return array
     */
    public function get_registered_services() {
        return array_merge(
            array_keys( $this->services ),
            array_keys( $this->singletons ),
            array_keys( $this->factories )
        );
    }

    /**
     * Register core services used throughout the plugin.
     */
    private function register_core_services() {
        // AI Service
        $this->register( 'ai_service', function() {
            return new RWP_Creator_Suite_AI_Service();
        } );

        // Network Utils (static class, but wrapped for consistency)
        $this->register( 'network_utils', function() {
            return new RWP_Creator_Suite_Network_Utils();
        } );

        // Error Logger (static class, but wrapped for consistency)
        $this->register( 'error_logger', function() {
            return new RWP_Creator_Suite_Error_Logger();
        } );

        // Rate Limiter
        $this->register( 'rate_limiter', function() {
            if ( class_exists( 'RWP_Creator_Suite_Rate_Limiter' ) ) {
                return new RWP_Creator_Suite_Rate_Limiter();
            }
            return null;
        } );

        // Key Manager
        $this->register( 'key_manager', function() {
            if ( class_exists( 'RWP_Creator_Suite_Key_Manager' ) ) {
                return new RWP_Creator_Suite_Key_Manager();
            }
            return null;
        } );

        // Caption Cache
        $this->register( 'caption_cache', function() {
            if ( class_exists( 'RWP_Creator_Suite_Caption_Cache' ) ) {
                return new RWP_Creator_Suite_Caption_Cache();
            }
            return null;
        } );

        // Template Manager
        $this->register( 'template_manager', function() {
            if ( class_exists( 'RWP_Creator_Suite_Template_Manager' ) ) {
                return new RWP_Creator_Suite_Template_Manager();
            }
            return null;
        } );

        // Username Generator
        $this->register( 'username_generator', function() {
            if ( class_exists( 'RWP_Creator_Suite_Username_Generator' ) ) {
                return new RWP_Creator_Suite_Username_Generator();
            }
            return null;
        } );

        // User Registration
        $this->register( 'user_registration', function() {
            if ( class_exists( 'RWP_Creator_Suite_User_Registration' ) ) {
                return new RWP_Creator_Suite_User_Registration();
            }
            return null;
        } );

        // Auto Login
        $this->register( 'auto_login', function() {
            if ( class_exists( 'RWP_Creator_Suite_Auto_Login' ) ) {
                return new RWP_Creator_Suite_Auto_Login();
            }
            return null;
        } );

        // Allow other components to register their services
        do_action( 'rwp_creator_suite_register_services', $this );
    }

    /**
     * Clear all services (useful for testing).
     */
    public function clear() {
        $this->services = array();
        $this->singletons = array();
        $this->factories = array();
        $this->initialized = false;
    }

    /**
     * Get service statistics for debugging.
     *
     * @return array
     */
    public function get_stats() {
        return array(
            'total_services' => count( $this->get_registered_services() ),
            'singletons_count' => count( $this->singletons ),
            'factories_count' => count( $this->factories ),
            'regular_services_count' => count( $this->services ),
            'initialized' => $this->initialized,
        );
    }

    /**
     * Prevent cloning of the singleton.
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the singleton.
     */
    public function __wakeup() {
        throw new Exception( 'Cannot unserialize singleton' );
    }
}