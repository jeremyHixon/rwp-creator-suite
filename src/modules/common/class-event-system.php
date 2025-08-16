<?php
/**
 * Event System Implementation
 * 
 * Provides decoupled communication between modules through an event-driven architecture.
 * Enables plugin extensions and better module isolation.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Event_System {

    /**
     * Singleton instance.
     *
     * @var RWP_Creator_Suite_Event_System
     */
    private static $instance = null;

    /**
     * Event listeners registry.
     *
     * @var array
     */
    private $listeners = array();

    /**
     * Event middleware stack.
     *
     * @var array
     */
    private $middleware = array();

    /**
     * Performance metrics.
     *
     * @var array
     */
    private $metrics = array();

    /**
     * Debug mode flag.
     *
     * @var bool
     */
    private $debug = false;

    /**
     * Get singleton instance.
     *
     * @return RWP_Creator_Suite_Event_System
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $this->init_core_events();
    }

    /**
     * Initialize core event definitions.
     */
    private function init_core_events() {
        // Define core events that plugins can listen to
        $this->define_event( 'rwp_ai_request_started', array(
            'description' => 'Fired when an AI request begins processing',
            'parameters' => array( 'request_type', 'user_id', 'request_data' ),
        ) );

        $this->define_event( 'rwp_ai_request_completed', array(
            'description' => 'Fired when an AI request is completed successfully',
            'parameters' => array( 'request_type', 'user_id', 'response_data', 'processing_time' ),
        ) );

        $this->define_event( 'rwp_ai_request_failed', array(
            'description' => 'Fired when an AI request fails',
            'parameters' => array( 'request_type', 'user_id', 'error_message', 'error_code' ),
        ) );

        $this->define_event( 'rwp_user_quota_exceeded', array(
            'description' => 'Fired when a user exceeds their usage quota',
            'parameters' => array( 'user_id', 'feature', 'quota_type', 'current_usage', 'limit' ),
        ) );

        $this->define_event( 'rwp_guest_conversion', array(
            'description' => 'Fired when a guest user creates an account',
            'parameters' => array( 'user_id', 'guest_identifier', 'migration_data' ),
        ) );

        $this->define_event( 'rwp_content_generated', array(
            'description' => 'Fired when content is successfully generated',
            'parameters' => array( 'content_type', 'user_id', 'platforms', 'content_data' ),
        ) );

        $this->define_event( 'rwp_feature_accessed', array(
            'description' => 'Fired when a user accesses a plugin feature',
            'parameters' => array( 'feature', 'user_id', 'is_guest', 'access_method' ),
        ) );

        $this->define_event( 'rwp_cache_invalidated', array(
            'description' => 'Fired when cache is invalidated',
            'parameters' => array( 'cache_group', 'cache_keys', 'reason' ),
        ) );
    }

    /**
     * Define an event type for documentation and validation.
     *
     * @param string $event_name Event name.
     * @param array  $definition Event definition with description and parameters.
     */
    public function define_event( $event_name, $definition ) {
        if ( ! isset( $this->listeners[ $event_name ] ) ) {
            $this->listeners[ $event_name ] = array(
                'definition' => $definition,
                'callbacks' => array(),
            );
        }
    }

    /**
     * Register an event listener.
     *
     * @param string   $event_name Event name to listen for.
     * @param callable $callback   Callback function to execute.
     * @param int      $priority   Priority level (lower numbers = higher priority).
     * @param int      $accepted_args Number of arguments the callback accepts.
     * @return bool Success status.
     */
    public function listen( $event_name, $callback, $priority = 10, $accepted_args = 1 ) {
        if ( ! is_callable( $callback ) ) {
            if ( $this->debug ) {
                error_log( "RWP Event System: Invalid callback for event '$event_name'" );
            }
            return false;
        }

        if ( ! isset( $this->listeners[ $event_name ] ) ) {
            $this->define_event( $event_name, array(
                'description' => 'Custom event',
                'parameters' => array(),
            ) );
        }

        $this->listeners[ $event_name ]['callbacks'][] = array(
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
            'id' => uniqid(),
        );

        // Sort callbacks by priority
        usort( $this->listeners[ $event_name ]['callbacks'], function( $a, $b ) {
            return $a['priority'] - $b['priority'];
        } );

        return true;
    }

    /**
     * Remove an event listener.
     *
     * @param string   $event_name Event name.
     * @param callable $callback   Callback to remove.
     * @return bool Success status.
     */
    public function unlisten( $event_name, $callback ) {
        if ( ! isset( $this->listeners[ $event_name ] ) ) {
            return false;
        }

        $callbacks = &$this->listeners[ $event_name ]['callbacks'];
        
        foreach ( $callbacks as $index => $listener ) {
            if ( $listener['callback'] === $callback ) {
                unset( $callbacks[ $index ] );
                $callbacks = array_values( $callbacks );
                return true;
            }
        }

        return false;
    }

    /**
     * Emit an event to all registered listeners.
     *
     * @param string $event_name Event name to emit.
     * @param array  $data       Event data to pass to listeners.
     * @return array Results from all listeners.
     */
    public function emit( $event_name, $data = array() ) {
        $start_time = microtime( true );
        $results = array();

        if ( $this->debug ) {
            error_log( "RWP Event System: Emitting event '$event_name' with data: " . wp_json_encode( $data ) );
        }

        // Apply middleware before processing
        $data = $this->apply_middleware( 'before', $event_name, $data );

        if ( ! isset( $this->listeners[ $event_name ] ) ) {
            if ( $this->debug ) {
                error_log( "RWP Event System: No listeners registered for event '$event_name'" );
            }
            return $results;
        }

        $callbacks = $this->listeners[ $event_name ]['callbacks'];

        foreach ( $callbacks as $listener ) {
            try {
                $callback = $listener['callback'];
                $accepted_args = $listener['accepted_args'];

                // Prepare arguments based on accepted_args count
                $args = array_slice( array_values( $data ), 0, $accepted_args );
                
                $listener_start = microtime( true );
                $result = call_user_func_array( $callback, $args );
                $listener_time = microtime( true ) - $listener_start;

                $results[] = array(
                    'callback_id' => $listener['id'],
                    'result' => $result,
                    'execution_time' => $listener_time,
                );

                if ( $this->debug && $listener_time > 0.1 ) {
                    error_log( "RWP Event System: Slow listener detected for '$event_name' - {$listener_time}s" );
                }

            } catch ( Exception $e ) {
                $results[] = array(
                    'callback_id' => $listener['id'],
                    'error' => $e->getMessage(),
                    'execution_time' => 0,
                );

                if ( $this->debug ) {
                    error_log( "RWP Event System: Error in listener for '$event_name': " . $e->getMessage() );
                }
            }
        }

        // Apply middleware after processing
        $results = $this->apply_middleware( 'after', $event_name, $results, $data );

        $total_time = microtime( true ) - $start_time;
        
        // Track performance metrics
        $this->record_metric( $event_name, $total_time, count( $callbacks ) );

        // Fire WordPress action for compatibility
        do_action( $event_name, $data, $results );

        return $results;
    }

    /**
     * Add middleware to the event processing pipeline.
     *
     * @param string   $phase    Processing phase ('before' or 'after').
     * @param callable $callback Middleware callback.
     * @param int      $priority Priority level.
     */
    public function add_middleware( $phase, $callback, $priority = 10 ) {
        if ( ! in_array( $phase, array( 'before', 'after' ), true ) ) {
            return false;
        }

        if ( ! is_callable( $callback ) ) {
            return false;
        }

        if ( ! isset( $this->middleware[ $phase ] ) ) {
            $this->middleware[ $phase ] = array();
        }

        $this->middleware[ $phase ][] = array(
            'callback' => $callback,
            'priority' => $priority,
        );

        // Sort by priority
        usort( $this->middleware[ $phase ], function( $a, $b ) {
            return $a['priority'] - $b['priority'];
        } );

        return true;
    }

    /**
     * Apply middleware to event processing.
     *
     * @param string $phase      Processing phase.
     * @param string $event_name Event name.
     * @param mixed  $data       Data to process.
     * @param mixed  $results    Results (for 'after' phase).
     * @return mixed Processed data.
     */
    private function apply_middleware( $phase, $event_name, $data, $results = null ) {
        if ( ! isset( $this->middleware[ $phase ] ) ) {
            return $data;
        }

        foreach ( $this->middleware[ $phase ] as $middleware ) {
            try {
                if ( 'before' === $phase ) {
                    $data = call_user_func( $middleware['callback'], $event_name, $data );
                } else {
                    $data = call_user_func( $middleware['callback'], $event_name, $data, $results );
                }
            } catch ( Exception $e ) {
                if ( $this->debug ) {
                    error_log( "RWP Event System: Error in middleware: " . $e->getMessage() );
                }
            }
        }

        return $data;
    }

    /**
     * Record performance metrics for an event.
     *
     * @param string $event_name     Event name.
     * @param float  $execution_time Execution time in seconds.
     * @param int    $listener_count Number of listeners executed.
     */
    private function record_metric( $event_name, $execution_time, $listener_count ) {
        if ( ! isset( $this->metrics[ $event_name ] ) ) {
            $this->metrics[ $event_name ] = array(
                'total_executions' => 0,
                'total_time' => 0,
                'average_time' => 0,
                'max_time' => 0,
                'listener_count' => 0,
            );
        }

        $metric = &$this->metrics[ $event_name ];
        $metric['total_executions']++;
        $metric['total_time'] += $execution_time;
        $metric['average_time'] = $metric['total_time'] / $metric['total_executions'];
        $metric['max_time'] = max( $metric['max_time'], $execution_time );
        $metric['listener_count'] = $listener_count;
    }

    /**
     * Get performance metrics for events.
     *
     * @param string $event_name Optional event name to get specific metrics.
     * @return array Performance metrics.
     */
    public function get_metrics( $event_name = null ) {
        if ( $event_name ) {
            return isset( $this->metrics[ $event_name ] ) ? $this->metrics[ $event_name ] : array();
        }

        return $this->metrics;
    }

    /**
     * Get all registered events and their listeners.
     *
     * @return array Event registry.
     */
    public function get_registry() {
        $registry = array();

        foreach ( $this->listeners as $event_name => $event_data ) {
            $registry[ $event_name ] = array(
                'definition' => $event_data['definition'],
                'listener_count' => count( $event_data['callbacks'] ),
                'listeners' => array_map( function( $listener ) {
                    return array(
                        'priority' => $listener['priority'],
                        'accepted_args' => $listener['accepted_args'],
                        'id' => $listener['id'],
                    );
                }, $event_data['callbacks'] ),
            );
        }

        return $registry;
    }

    /**
     * Clear all listeners for an event or all events.
     *
     * @param string $event_name Optional event name to clear specific event.
     */
    public function clear_listeners( $event_name = null ) {
        if ( $event_name ) {
            if ( isset( $this->listeners[ $event_name ] ) ) {
                $this->listeners[ $event_name ]['callbacks'] = array();
            }
        } else {
            foreach ( $this->listeners as $name => $data ) {
                $this->listeners[ $name ]['callbacks'] = array();
            }
        }
    }

    /**
     * Enable or disable debug mode.
     *
     * @param bool $enabled Debug mode status.
     */
    public function set_debug( $enabled ) {
        $this->debug = (bool) $enabled;
    }

    /**
     * Check if an event has listeners.
     *
     * @param string $event_name Event name to check.
     * @return bool True if event has listeners.
     */
    public function has_listeners( $event_name ) {
        return isset( $this->listeners[ $event_name ] ) && 
               ! empty( $this->listeners[ $event_name ]['callbacks'] );
    }

    /**
     * Emit an event asynchronously using WordPress cron.
     *
     * @param string $event_name Event name.
     * @param array  $data       Event data.
     * @param int    $delay      Delay in seconds before processing.
     * @return bool Success status.
     */
    public function emit_async( $event_name, $data = array(), $delay = 0 ) {
        $hook = 'rwp_async_event_' . $event_name;
        $timestamp = time() + $delay;

        // Schedule the event
        wp_schedule_single_event( $timestamp, $hook, array( $event_name, $data ) );

        // Register the handler if not already registered
        if ( ! has_action( $hook ) ) {
            add_action( $hook, array( $this, 'handle_async_event' ), 10, 2 );
        }

        return true;
    }

    /**
     * Handle asynchronously scheduled events.
     *
     * @param string $event_name Event name.
     * @param array  $data       Event data.
     */
    public function handle_async_event( $event_name, $data ) {
        $this->emit( $event_name, $data );
    }

    /**
     * Create a scoped event emitter for a specific context.
     *
     * @param string $context Context identifier.
     * @return RWP_Creator_Suite_Scoped_Event_Emitter
     */
    public function create_scoped_emitter( $context ) {
        return new RWP_Creator_Suite_Scoped_Event_Emitter( $this, $context );
    }
}

/**
 * Scoped Event Emitter
 * 
 * Provides a context-specific interface for emitting events.
 */
class RWP_Creator_Suite_Scoped_Event_Emitter {

    /**
     * Parent event system.
     *
     * @var RWP_Creator_Suite_Event_System
     */
    private $event_system;

    /**
     * Context identifier.
     *
     * @var string
     */
    private $context;

    /**
     * Constructor.
     *
     * @param RWP_Creator_Suite_Event_System $event_system Parent event system.
     * @param string                         $context      Context identifier.
     */
    public function __construct( $event_system, $context ) {
        $this->event_system = $event_system;
        $this->context = sanitize_key( $context );
    }

    /**
     * Emit an event with context prefix.
     *
     * @param string $event_name Event name.
     * @param array  $data       Event data.
     * @return array Results from listeners.
     */
    public function emit( $event_name, $data = array() ) {
        $contextual_event = $this->context . '_' . $event_name;
        
        // Add context to event data
        $data['_context'] = $this->context;
        
        return $this->event_system->emit( $contextual_event, $data );
    }

    /**
     * Listen for events in this context.
     *
     * @param string   $event_name Event name.
     * @param callable $callback   Callback function.
     * @param int      $priority   Priority level.
     * @param int      $accepted_args Number of accepted arguments.
     * @return bool Success status.
     */
    public function listen( $event_name, $callback, $priority = 10, $accepted_args = 1 ) {
        $contextual_event = $this->context . '_' . $event_name;
        return $this->event_system->listen( $contextual_event, $callback, $priority, $accepted_args );
    }
}