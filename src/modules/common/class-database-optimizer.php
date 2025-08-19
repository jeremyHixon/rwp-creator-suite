<?php
/**
 * Database Schema Optimizer
 * 
 * Handles database optimizations including indexes, partitioning, and query optimization
 * for improved performance and scalability.
 */

defined( 'ABSPATH' ) || exit;

class RWP_Creator_Suite_Database_Optimizer {

    /**
     * Singleton instance.
     *
     * @var RWP_Creator_Suite_Database_Optimizer
     */
    private static $instance = null;

    /**
     * WordPress database instance.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Optimization results.
     *
     * @var array
     */
    private $optimization_results = array();

    /**
     * Database table mappings.
     *
     * @var array
     */
    private $table_mappings = array();

    /**
     * Get singleton instance.
     *
     * @return RWP_Creator_Suite_Database_Optimizer
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->init_table_mappings();
        $this->init_hooks();
    }

    /**
     * Initialize table mappings.
     */
    private function init_table_mappings() {
        $this->table_mappings = array(
            'usage_stats' => $this->wpdb->prefix . 'rwp_usage_stats',
            'content_generations' => $this->wpdb->prefix . 'rwp_content_generations', 
            'ai_requests' => $this->wpdb->prefix . 'rwp_ai_requests',
            'user_quotas' => $this->wpdb->prefix . 'rwp_user_quotas',
            'analytics_events' => $this->wpdb->prefix . 'rwp_analytics_events',
            'cache_entries' => $this->wpdb->prefix . 'rwp_cache_entries',
        );
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        add_action( 'wp_loaded', array( $this, 'check_optimizations' ) );
        add_action( 'rwp_creator_suite_daily_maintenance', array( $this, 'run_daily_optimizations' ) );
        
        // Schedule optimization checks
        if ( ! wp_next_scheduled( 'rwp_creator_suite_daily_maintenance' ) ) {
            wp_schedule_event( time(), 'daily', 'rwp_creator_suite_daily_maintenance' );
        }
    }

    /**
     * Check if optimizations are needed.
     */
    public function check_optimizations() {
        $last_check = get_option( 'rwp_creator_suite_last_optimization_check', 0 );
        
        // Check weekly
        if ( time() - $last_check > WEEK_IN_SECONDS ) {
            $this->analyze_database_performance();
            update_option( 'rwp_creator_suite_last_optimization_check', time() );
        }
    }

    /**
     * Run comprehensive database optimization.
     *
     * @return array Optimization results.
     */
    public function optimize_database() {
        $this->optimization_results = array();

        // Create optimized indexes
        $this->create_performance_indexes();

        // Optimize table structure
        $this->optimize_table_structure();

        // Create materialized views (if supported)
        $this->create_materialized_views();

        // Partition large tables (if supported)
        $this->implement_table_partitioning();

        // Archive old data
        $this->archive_old_data();

        // Analyze query performance
        $this->analyze_query_performance();

        // Update optimization timestamp
        update_option( 'rwp_creator_suite_last_optimization', time() );

        return $this->optimization_results;
    }

    /**
     * Create performance indexes on frequently queried fields.
     */
    private function create_performance_indexes() {
        $indexes = array(
            // Usage stats indexes
            array(
                'table' => 'usage_stats',
                'name' => 'idx_user_date',
                'columns' => array( 'user_id', 'date_created' ),
                'type' => 'INDEX'
            ),
            array(
                'table' => 'usage_stats',
                'name' => 'idx_feature_date',
                'columns' => array( 'feature', 'date_created' ),
                'type' => 'INDEX'
            ),
            array(
                'table' => 'usage_stats',
                'name' => 'idx_date_feature_user',
                'columns' => array( 'date_created', 'feature', 'user_id' ),
                'type' => 'INDEX'
            ),

            // Content generations indexes
            array(
                'table' => 'content_generations',
                'name' => 'idx_user_created',
                'columns' => array( 'user_id', 'created_at' ),
                'type' => 'INDEX'
            ),
            array(
                'table' => 'content_generations',
                'name' => 'idx_status_created',
                'columns' => array( 'status', 'created_at' ),
                'type' => 'INDEX'
            ),
            array(
                'table' => 'content_generations',
                'name' => 'idx_platforms',
                'columns' => array( 'platforms' ),
                'type' => 'INDEX',
                'length' => 50
            ),

            // AI requests indexes
            array(
                'table' => 'ai_requests',
                'name' => 'idx_request_type_date',
                'columns' => array( 'request_type', 'created_at' ),
                'type' => 'INDEX'
            ),
            array(
                'table' => 'ai_requests',
                'name' => 'idx_user_status',
                'columns' => array( 'user_id', 'status' ),
                'type' => 'INDEX'
            ),
            array(
                'table' => 'ai_requests',
                'name' => 'idx_processing_time',
                'columns' => array( 'processing_time' ),
                'type' => 'INDEX'
            ),

            // User quotas indexes
            array(
                'table' => 'user_quotas',
                'name' => 'idx_user_feature',
                'columns' => array( 'user_id', 'feature' ),
                'type' => 'UNIQUE'
            ),
            array(
                'table' => 'user_quotas',
                'name' => 'idx_reset_date',
                'columns' => array( 'reset_date' ),
                'type' => 'INDEX'
            ),

            // Analytics events indexes
            array(
                'table' => 'analytics_events',
                'name' => 'idx_event_date',
                'columns' => array( 'event_type', 'event_date' ),
                'type' => 'INDEX'
            ),
            array(
                'table' => 'analytics_events',
                'name' => 'idx_user_event',
                'columns' => array( 'user_id', 'event_type', 'event_date' ),
                'type' => 'INDEX'
            ),
        );

        foreach ( $indexes as $index ) {
            $this->create_index( $index );
        }
    }

    /**
     * Create a database index.
     *
     * @param array $index_config Index configuration.
     */
    private function create_index( $index_config ) {
        $table_name = $this->table_mappings[ $index_config['table'] ] ?? null;
        
        if ( ! $table_name || ! $this->table_exists( $table_name ) ) {
            return;
        }

        // Check if index already exists
        if ( $this->index_exists( $table_name, $index_config['name'] ) ) {
            $this->optimization_results[] = array(
                'type' => 'index',
                'action' => 'skip',
                'table' => $index_config['table'],
                'index' => $index_config['name'],
                'message' => 'Index already exists'
            );
            return;
        }

        // Build column specification
        $columns = array();
        foreach ( $index_config['columns'] as $column ) {
            $column_spec = "`{$column}`";
            if ( isset( $index_config['length'] ) && is_string( $column ) ) {
                $column_spec .= "({$index_config['length']})";
            }
            $columns[] = $column_spec;
        }

        $columns_sql = implode( ', ', $columns );
        $index_type = $index_config['type'] ?? 'INDEX';

        $sql = "CREATE {$index_type} `{$index_config['name']}` ON `{$table_name}` ({$columns_sql})";

        $result = $this->wpdb->query( $sql );

        $this->optimization_results[] = array(
            'type' => 'index',
            'action' => $result !== false ? 'created' : 'failed',
            'table' => $index_config['table'],
            'index' => $index_config['name'],
            'sql' => $sql,
            'error' => $result === false ? $this->wpdb->last_error : null
        );
    }

    /**
     * Optimize table structure for better performance.
     */
    private function optimize_table_structure() {
        foreach ( $this->table_mappings as $table_key => $table_name ) {
            if ( ! $this->table_exists( $table_name ) ) {
                continue;
            }

            // Analyze table
            $this->wpdb->query( "ANALYZE TABLE `{$table_name}`" );

            // Optimize table
            $result = $this->wpdb->query( "OPTIMIZE TABLE `{$table_name}`" );

            $this->optimization_results[] = array(
                'type' => 'table_optimization',
                'action' => $result !== false ? 'optimized' : 'failed',
                'table' => $table_key,
                'error' => $result === false ? $this->wpdb->last_error : null
            );
        }
    }

    /**
     * Create materialized views for complex queries.
     */
    private function create_materialized_views() {
        // Note: MySQL doesn't support materialized views natively
        // We create regular views and use event schedulers for refresh
        
        $views = array(
            'user_usage_summary' => array(
                'sql' => "
                    SELECT 
                        us.user_id,
                        us.feature,
                        COUNT(*) as total_requests,
                        SUM(us.usage_count) as total_usage,
                        AVG(us.usage_count) as avg_usage,
                        MAX(us.date_created) as last_activity,
                        DATE(us.date_created) as activity_date
                    FROM {$this->table_mappings['usage_stats']} us
                    WHERE us.date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY us.user_id, us.feature, DATE(us.date_created)
                ",
                'refresh_schedule' => 'daily'
            ),
            'platform_performance' => array(
                'sql' => "
                    SELECT 
                        cg.platforms,
                        COUNT(*) as generation_count,
                        AVG(ar.processing_time) as avg_processing_time,
                        SUM(CASE WHEN cg.status = 'completed' THEN 1 ELSE 0 END) as success_count,
                        (SUM(CASE WHEN cg.status = 'completed' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as success_rate,
                        DATE(cg.created_at) as date
                    FROM {$this->table_mappings['content_generations']} cg
                    LEFT JOIN {$this->table_mappings['ai_requests']} ar ON cg.request_id = ar.id
                    WHERE cg.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY cg.platforms, DATE(cg.created_at)
                ",
                'refresh_schedule' => 'hourly'
            ),
            'quota_utilization' => array(
                'sql' => "
                    SELECT 
                        uq.user_id,
                        uq.feature,
                        uq.current_usage,
                        uq.quota_limit,
                        (uq.current_usage / uq.quota_limit) * 100 as utilization_percentage,
                        uq.reset_date,
                        CASE 
                            WHEN (uq.current_usage / uq.quota_limit) >= 0.9 THEN 'high'
                            WHEN (uq.current_usage / uq.quota_limit) >= 0.7 THEN 'medium'
                            ELSE 'low'
                        END as utilization_level
                    FROM {$this->table_mappings['user_quotas']} uq
                    WHERE uq.quota_limit > 0
                ",
                'refresh_schedule' => 'hourly'
            )
        );

        foreach ( $views as $view_name => $view_config ) {
            $this->create_view( $view_name, $view_config );
        }
    }

    /**
     * Create or update a database view.
     *
     * @param string $view_name View name.
     * @param array  $view_config View configuration.
     */
    private function create_view( $view_name, $view_config ) {
        $full_view_name = $this->wpdb->prefix . 'rwp_view_' . $view_name;

        // Drop existing view
        $this->wpdb->query( "DROP VIEW IF EXISTS `{$full_view_name}`" );

        // Create view
        $sql = "CREATE VIEW `{$full_view_name}` AS {$view_config['sql']}";
        $result = $this->wpdb->query( $sql );

        $this->optimization_results[] = array(
            'type' => 'view',
            'action' => $result !== false ? 'created' : 'failed',
            'view' => $view_name,
            'error' => $result === false ? $this->wpdb->last_error : null
        );
    }

    /**
     * Implement table partitioning for large datasets.
     */
    private function implement_table_partitioning() {
        // Check if partitioning is supported
        $partition_support = $this->wpdb->get_var( 
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.PLUGINS WHERE PLUGIN_NAME = 'partition'"
        );

        if ( ! $partition_support ) {
            $this->optimization_results[] = array(
                'type' => 'partitioning',
                'action' => 'skip',
                'message' => 'Table partitioning not supported'
            );
            return;
        }

        // Partition large tables by date
        $partition_tables = array(
            'usage_stats' => array(
                'column' => 'date_created',
                'type' => 'RANGE',
                'interval' => 'MONTH'
            ),
            'analytics_events' => array(
                'column' => 'event_date', 
                'type' => 'RANGE',
                'interval' => 'MONTH'
            ),
            'ai_requests' => array(
                'column' => 'created_at',
                'type' => 'RANGE',
                'interval' => 'MONTH'
            )
        );

        foreach ( $partition_tables as $table_key => $config ) {
            $this->partition_table( $table_key, $config );
        }
    }

    /**
     * Partition a table by date range.
     *
     * @param string $table_key Table key.
     * @param array  $config Partitioning configuration.
     */
    private function partition_table( $table_key, $config ) {
        $table_name = $this->table_mappings[ $table_key ] ?? null;
        
        if ( ! $table_name || ! $this->table_exists( $table_name ) ) {
            return;
        }

        // Check if table is already partitioned
        $is_partitioned = $this->wpdb->get_var( 
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.PARTITIONS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND PARTITION_NAME IS NOT NULL",
                DB_NAME,
                $table_name
            )
        );

        if ( $is_partitioned ) {
            $this->optimization_results[] = array(
                'type' => 'partitioning',
                'action' => 'skip',
                'table' => $table_key,
                'message' => 'Table already partitioned'
            );
            return;
        }

        // Create partitions for the last 12 months and next 6 months
        $partitions = array();
        for ( $i = -12; $i <= 6; $i++ ) {
            $date = date( 'Y-m-01', strtotime( "{$i} months" ) );
            $next_date = date( 'Y-m-01', strtotime( ( $i + 1 ) . ' months' ) );
            $partition_name = 'p' . str_replace( '-', '', $date );
            
            $partitions[] = "PARTITION {$partition_name} VALUES LESS THAN (TO_DAYS('{$next_date}'))";
        }

        $partitions_sql = implode( ', ', $partitions );
        
        $sql = "ALTER TABLE `{$table_name}` 
                PARTITION BY RANGE (TO_DAYS({$config['column']})) 
                ({$partitions_sql})";

        $result = $this->wpdb->query( $sql );

        $this->optimization_results[] = array(
            'type' => 'partitioning',
            'action' => $result !== false ? 'partitioned' : 'failed',
            'table' => $table_key,
            'partitions' => count( $partitions ),
            'error' => $result === false ? $this->wpdb->last_error : null
        );
    }

    /**
     * Archive old data to separate tables.
     */
    private function archive_old_data() {
        $archive_configs = array(
            'usage_stats' => array(
                'date_column' => 'date_created',
                'archive_after' => '6 MONTH',
                'batch_size' => 1000
            ),
            'analytics_events' => array(
                'date_column' => 'event_date',
                'archive_after' => '3 MONTH', 
                'batch_size' => 1000
            ),
            'ai_requests' => array(
                'date_column' => 'created_at',
                'archive_after' => '1 YEAR',
                'batch_size' => 500
            )
        );

        foreach ( $archive_configs as $table_key => $config ) {
            $this->archive_table_data( $table_key, $config );
        }
    }

    /**
     * Archive old data from a table.
     *
     * @param string $table_key Table key.
     * @param array  $config Archive configuration.
     */
    private function archive_table_data( $table_key, $config ) {
        $table_name = $this->table_mappings[ $table_key ] ?? null;
        $archive_table_name = $table_name . '_archive';
        
        if ( ! $table_name || ! $this->table_exists( $table_name ) ) {
            return;
        }

        // Create archive table if it doesn't exist
        $this->create_archive_table( $table_name, $archive_table_name );

        // Find records to archive
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$config['archive_after']}" ) );
        
        $count_sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table_name}` WHERE `{$config['date_column']}` < %s",
            $cutoff_date
        );
        
        $total_records = $this->wpdb->get_var( $count_sql );
        
        if ( ! $total_records ) {
            $this->optimization_results[] = array(
                'type' => 'archiving',
                'action' => 'skip',
                'table' => $table_key,
                'message' => 'No records to archive'
            );
            return;
        }

        $archived = 0;
        $batch_size = $config['batch_size'];

        while ( $archived < $total_records ) {
            // Copy batch to archive table
            $copy_sql = $this->wpdb->prepare(
                "INSERT INTO `{$archive_table_name}` 
                 SELECT * FROM `{$table_name}` 
                 WHERE `{$config['date_column']}` < %s 
                 LIMIT %d",
                $cutoff_date,
                $batch_size
            );

            $copied = $this->wpdb->query( $copy_sql );
            
            if ( $copied === false ) {
                break;
            }

            // Delete from main table
            $delete_sql = $this->wpdb->prepare(
                "DELETE FROM `{$table_name}` 
                 WHERE `{$config['date_column']}` < %s 
                 LIMIT %d",
                $cutoff_date,
                $copied
            );

            $this->wpdb->query( $delete_sql );
            $archived += $copied;

            // Prevent timeout
            if ( time() > ( $_SERVER['REQUEST_TIME'] + 25 ) ) {
                break;
            }
        }

        $this->optimization_results[] = array(
            'type' => 'archiving',
            'action' => 'completed',
            'table' => $table_key,
            'records_archived' => $archived,
            'total_found' => $total_records
        );
    }

    /**
     * Create archive table with same structure as main table.
     *
     * @param string $main_table Main table name.
     * @param string $archive_table Archive table name.
     */
    private function create_archive_table( $main_table, $archive_table ) {
        if ( $this->table_exists( $archive_table ) ) {
            return;
        }

        $sql = "CREATE TABLE `{$archive_table}` LIKE `{$main_table}`";
        $this->wpdb->query( $sql );
    }

    /**
     * Analyze query performance.
     */
    private function analyze_query_performance() {
        // Enable slow query log analysis
        $slow_queries = $this->get_slow_queries();
        
        $analysis = array(
            'slow_query_count' => count( $slow_queries ),
            'queries_analyzed' => 0,
            'optimization_suggestions' => array()
        );

        foreach ( $slow_queries as $query ) {
            $suggestions = $this->analyze_query( $query );
            if ( ! empty( $suggestions ) ) {
                $analysis['optimization_suggestions'][] = $suggestions;
            }
            $analysis['queries_analyzed']++;
        }

        $this->optimization_results[] = array(
            'type' => 'query_analysis',
            'action' => 'completed',
            'analysis' => $analysis
        );
    }

    /**
     * Get slow queries from performance schema.
     *
     * @return array Slow queries.
     */
    private function get_slow_queries() {
        // This requires MySQL performance schema to be enabled
        $sql = "SELECT sql_text, exec_count, avg_timer_wait / 1000000000 as avg_time_seconds
                FROM performance_schema.events_statements_summary_by_digest
                WHERE schema_name = %s 
                AND avg_timer_wait > 1000000000
                ORDER BY avg_timer_wait DESC
                LIMIT 10";

        $prepared_sql = $this->wpdb->prepare( $sql, DB_NAME );
        return $this->wpdb->get_results( $prepared_sql, ARRAY_A ) ?: array();
    }

    /**
     * Analyze a specific query for optimization opportunities.
     *
     * @param array $query_data Query data.
     * @return array Optimization suggestions.
     */
    private function analyze_query( $query_data ) {
        $suggestions = array();

        // Check for missing indexes
        if ( str_contains( $query_data['sql_text'] ?? '', 'WHERE' ) ) {
            $suggestions[] = 'Consider adding indexes on WHERE clause columns';
        }

        // Check for inefficient JOINs
        if ( str_contains( $query_data['sql_text'] ?? '', 'JOIN' ) ) {
            $suggestions[] = 'Review JOIN conditions and ensure proper indexing';
        }

        // Check for SELECT *
        if ( str_contains( $query_data['sql_text'] ?? '', 'SELECT *' ) ) {
            $suggestions[] = 'Avoid SELECT * - specify only needed columns';
        }

        return array(
            'query' => substr( $query_data['sql_text'], 0, 100 ) . '...',
            'avg_time' => $query_data['avg_time_seconds'],
            'exec_count' => $query_data['exec_count'],
            'suggestions' => $suggestions
        );
    }

    /**
     * Run daily optimization tasks.
     */
    public function run_daily_optimizations() {
        // Archive old data
        $this->archive_old_data();

        // Update table statistics
        foreach ( $this->table_mappings as $table_name ) {
            if ( $this->table_exists( $table_name ) ) {
                $this->wpdb->query( "ANALYZE TABLE `{$table_name}`" );
            }
        }

        // Clean up temporary tables
        $this->cleanup_temporary_tables();
    }

    /**
     * Cleanup temporary tables and expired data.
     */
    private function cleanup_temporary_tables() {
        // Remove temporary tables older than 1 day
        $temp_tables = $this->wpdb->get_results(
            "SHOW TABLES LIKE '{$this->wpdb->prefix}rwp_temp_%'",
            ARRAY_N
        );

        foreach ( $temp_tables as $table ) {
            $table_name = $table[0];
            
            // Check table creation time
            $create_time = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT CREATE_TIME FROM INFORMATION_SCHEMA.TABLES 
                     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                    DB_NAME,
                    $table_name
                )
            );

            if ( $create_time && strtotime( $create_time ) < strtotime( '-1 day' ) ) {
                $this->wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
            }
        }
    }

    /**
     * Analyze current database performance.
     *
     * @return array Performance analysis.
     */
    public function analyze_database_performance() {
        $analysis = array(
            'table_sizes' => array(),
            'index_usage' => array(),
            'query_performance' => array(),
            'recommendations' => array()
        );

        // Analyze table sizes
        foreach ( $this->table_mappings as $table_key => $table_name ) {
            if ( $this->table_exists( $table_name ) ) {
                $size_info = $this->wpdb->get_row(
                    $this->wpdb->prepare(
                        "SELECT 
                            ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb,
                            TABLE_ROWS as row_count,
                            ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_size_mb,
                            ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS index_size_mb
                         FROM INFORMATION_SCHEMA.TABLES 
                         WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                        DB_NAME,
                        $table_name
                    ),
                    ARRAY_A
                );

                $analysis['table_sizes'][ $table_key ] = $size_info;

                // Add recommendations based on size
                if ( $size_info['size_mb'] > 100 ) {
                    $analysis['recommendations'][] = "Consider partitioning {$table_key} table (size: {$size_info['size_mb']}MB)";
                }

                if ( $size_info['row_count'] > 100000 ) {
                    $analysis['recommendations'][] = "Consider archiving old data from {$table_key} table ({$size_info['row_count']} rows)";
                }
            }
        }

        return $analysis;
    }

    /**
     * Get database optimization status.
     *
     * @return array Optimization status.
     */
    public function get_optimization_status() {
        return array(
            'last_optimization' => get_option( 'rwp_creator_suite_last_optimization', 0 ),
            'last_check' => get_option( 'rwp_creator_suite_last_optimization_check', 0 ),
            'optimization_results' => $this->optimization_results,
            'table_count' => count( $this->table_mappings ),
            'optimized_tables' => $this->get_optimized_table_count()
        );
    }

    // Helper methods

    /**
     * Check if table exists.
     *
     * @param string $table_name Table name.
     * @return bool
     */
    private function table_exists( $table_name ) {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );
        return ! empty( $result );
    }

    /**
     * Check if index exists on table.
     *
     * @param string $table_name Table name.
     * @param string $index_name Index name.
     * @return bool
     */
    private function index_exists( $table_name, $index_name ) {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s",
                $index_name
            )
        );
        return ! empty( $result );
    }

    /**
     * Get count of optimized tables.
     *
     * @return int Optimized table count.
     */
    private function get_optimized_table_count() {
        $count = 0;
        foreach ( $this->table_mappings as $table_name ) {
            if ( $this->table_exists( $table_name ) ) {
                $count++;
            }
        }
        return $count;
    }
}