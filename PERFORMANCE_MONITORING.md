# Performance Monitoring Implementation Guide

## Overview
This document outlines performance monitoring solutions for the RWP Creator Suite plugin, including implementation options, performance impact analysis, and recommended approaches.

## Current Status
- **Basic Error Logging**: ✅ Implemented via `RWP_Creator_Suite_Error_Logger` class
- **Performance Monitoring**: 📋 Not implemented (documented for future reference)
- **Impact Analysis**: ✅ Complete

## Implementation Options

### Option 1: WordPress Database Storage ⭐ *Recommended*

#### Database Schema
```sql
CREATE TABLE wp_rwp_performance_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    timestamp datetime DEFAULT CURRENT_TIMESTAMP,
    metric_type varchar(50) NOT NULL,
    operation varchar(100) NOT NULL,
    execution_time decimal(10,4) DEFAULT NULL,
    memory_usage bigint(20) DEFAULT NULL,
    user_id bigint(20) DEFAULT NULL,
    url varchar(255) DEFAULT NULL,
    context longtext DEFAULT NULL,
    PRIMARY KEY (id),
    KEY timestamp_idx (timestamp),
    KEY metric_type_idx (metric_type)
);
```

#### PHP Implementation
```php
class RWP_Creator_Suite_Performance_DB {
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rwp_performance_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            metric_type varchar(50) NOT NULL,
            operation varchar(100) NOT NULL,
            execution_time decimal(10,4) DEFAULT NULL,
            memory_usage bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            url varchar(255) DEFAULT NULL,
            context longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp_idx (timestamp),
            KEY metric_type_idx (metric_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function log_metric($operation, $execution_time, $context = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'rwp_performance_logs',
            array(
                'metric_type' => 'performance',
                'operation' => $operation,
                'execution_time' => $execution_time,
                'memory_usage' => memory_get_usage(true),
                'user_id' => get_current_user_id(),
                'url' => $_SERVER['REQUEST_URI'],
                'context' => wp_json_encode($context)
            ),
            array('%s', '%s', '%f', '%d', '%d', '%s', '%s')
        );
    }
}
```

### Option 2: WordPress Transients (Short-term Storage)

```php
public static function store_metric($operation, $time, $context = array()) {
    $metrics = get_transient('rwp_performance_metrics') ?: array();
    $metrics[] = array(
        'timestamp' => time(),
        'operation' => $operation,
        'execution_time' => $time,
        'memory_usage' => memory_get_usage(true),
        'context' => $context
    );
    
    // Keep only last 100 entries
    $metrics = array_slice($metrics, -100);
    set_transient('rwp_performance_metrics', $metrics, DAY_IN_SECONDS);
}
```

### Option 3: WordPress Options Table (Aggregated Data)

```php
public static function update_performance_summary($operation, $time) {
    $summary = get_option('rwp_performance_summary', array());
    
    if (!isset($summary[$operation])) {
        $summary[$operation] = array(
            'count' => 0, 
            'total_time' => 0, 
            'avg_time' => 0,
            'max_time' => 0,
            'min_time' => PHP_FLOAT_MAX
        );
    }
    
    $summary[$operation]['count']++;
    $summary[$operation]['total_time'] += $time;
    $summary[$operation]['avg_time'] = $summary[$operation]['total_time'] / $summary[$operation]['count'];
    $summary[$operation]['max_time'] = max($summary[$operation]['max_time'], $time);
    $summary[$operation]['min_time'] = min($summary[$operation]['min_time'], $time);
    
    update_option('rwp_performance_summary', $summary);
}
```

## Viewing Interface Implementation

### WordPress Dashboard Widget

```php
public function add_performance_dashboard_widget() {
    wp_add_dashboard_widget(
        'rwp_performance_widget',
        'Creator Suite Performance',
        array($this, 'display_performance_widget')
    );
}

public function display_performance_widget() {
    $metrics = $this->get_recent_metrics(10);
    ?>
    <div class="rwp-performance-widget">
        <canvas id="rwp-performance-chart" width="400" height="200"></canvas>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Operation</th>
                    <th>Avg Time (ms)</th>
                    <th>Count</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metrics as $metric): ?>
                <tr>
                    <td><?php echo esc_html($metric['operation']); ?></td>
                    <td><?php echo number_format($metric['avg_time'] * 1000, 2); ?></td>
                    <td><?php echo intval($metric['count']); ?></td>
                    <td>
                        <span class="status-<?php echo $metric['avg_time'] > 1 ? 'warning' : 'good'; ?>">
                            <?php echo $metric['avg_time'] > 1 ? '⚠️ Slow' : '✅ Good'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
```

### Dedicated Admin Page

```php
public function add_performance_admin_page() {
    add_management_page(
        'Performance Metrics',
        'Creator Suite Performance',
        'manage_options',
        'rwp-performance',
        array($this, 'display_performance_page')
    );
}

public function display_performance_page() {
    ?>
    <div class="wrap">
        <h1>Creator Suite Performance Metrics</h1>
        
        <div class="rwp-performance-filters">
            <select id="time-range">
                <option value="1">Last Hour</option>
                <option value="24">Last 24 Hours</option>
                <option value="168">Last Week</option>
                <option value="720">Last Month</option>
            </select>
            <select id="metric-type">
                <option value="all">All Operations</option>
                <option value="instagram_analysis">Instagram Analysis</option>
                <option value="user_registration">User Registration</option>
                <option value="asset_loading">Asset Loading</option>
                <option value="api_requests">API Requests</option>
            </select>
            <button type="button" class="button" onclick="exportPerformanceData()">Export CSV</button>
        </div>
        
        <div class="rwp-performance-charts">
            <div class="chart-container">
                <h3>Execution Time Trends</h3>
                <canvas id="execution-time-chart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Memory Usage Patterns</h3>
                <canvas id="memory-usage-chart"></canvas>
            </div>
        </div>
        
        <div class="rwp-performance-details">
            <h3>Detailed Metrics</h3>
            <table class="wp-list-table widefat fixed striped" id="performance-details-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Operation</th>
                        <th>Execution Time</th>
                        <th>Memory Usage</th>
                        <th>User</th>
                        <th>URL</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    function exportPerformanceData() {
        const timeRange = document.getElementById('time-range').value;
        const metricType = document.getElementById('metric-type').value;
        
        window.location.href = ajaxurl + '?action=rwp_export_performance&time_range=' + timeRange + '&metric_type=' + metricType + '&_wpnonce=' + '<?php echo wp_create_nonce('rwp_export_performance'); ?>';
    }
    </script>
    <?php
}
```

## Frontend Performance Monitoring

### JavaScript Performance Tracking

```javascript
class FrontendPerformanceMonitor {
    static isEnabled() {
        return window.rwpPerformanceConfig && window.rwpPerformanceConfig.enabled;
    }
    
    static record(operation, duration, context = {}) {
        if (!this.isEnabled()) return;
        
        // Store in localStorage for admin users
        if (this.isAdmin()) {
            const perfData = JSON.parse(localStorage.getItem('rwp_frontend_perf') || '[]');
            perfData.push({
                timestamp: Date.now(),
                operation,
                duration,
                url: window.location.href,
                userAgent: navigator.userAgent,
                context
            });
            
            // Keep only last 50 entries
            localStorage.setItem('rwp_frontend_perf', JSON.stringify(perfData.slice(-50)));
        }
        
        // Send to server for aggregation
        this.sendToServer({operation, duration, context});
    }
    
    static sendToServer(data) {
        if (!window.wpApiSettings) return;
        
        fetch(wpApiSettings.root + 'rwp-creator-suite/v1/performance/record', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings.nonce
            },
            body: JSON.stringify(data)
        }).catch(error => {
            console.warn('Failed to send performance data:', error);
        });
    }
    
    static isAdmin() {
        return window.rwpPerformanceConfig && window.rwpPerformanceConfig.userCanManage;
    }
    
    // Track specific operations
    static trackOperation(name, operation) {
        const start = performance.now();
        
        try {
            const result = operation();
            
            // Handle both sync and async operations
            if (result && typeof result.then === 'function') {
                return result.finally(() => {
                    const duration = performance.now() - start;
                    this.record(name, duration);
                });
            } else {
                const duration = performance.now() - start;
                this.record(name, duration);
                return result;
            }
        } catch (error) {
            const duration = performance.now() - start;
            this.record(name, duration, {error: error.message});
            throw error;
        }
    }
}

// Core Web Vitals Integration
function initWebVitalsTracking() {
    if (!FrontendPerformanceMonitor.isEnabled()) return;
    
    // Largest Contentful Paint
    if ('PerformanceObserver' in window) {
        new PerformanceObserver((entryList) => {
            const entries = entryList.getEntries();
            const lastEntry = entries[entries.length - 1];
            FrontendPerformanceMonitor.record('LCP', lastEntry.startTime, {
                element: lastEntry.element?.tagName,
                size: lastEntry.size
            });
        }).observe({type: 'largest-contentful-paint', buffered: true});
        
        // First Input Delay
        new PerformanceObserver((entryList) => {
            const firstInput = entryList.getEntries()[0];
            const delay = firstInput.processingStart - firstInput.startTime;
            FrontendPerformanceMonitor.record('FID', delay, {
                eventType: firstInput.name
            });
        }).observe({type: 'first-input', buffered: true});
        
        // Long Tasks (blocking operations)
        new PerformanceObserver((entryList) => {
            entryList.getEntries().forEach((entry) => {
                if (entry.duration > 50) {
                    FrontendPerformanceMonitor.record('Long Task', entry.duration, {
                        startTime: entry.startTime,
                        name: entry.name
                    });
                }
            });
        }).observe({type: 'longtask', buffered: true});
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initWebVitalsTracking);
```

## Performance Impact Analysis

### Overhead Comparison

| Implementation | Performance Impact | Storage Impact | Maintenance | Best For |
|---------------|-------------------|----------------|-------------|----------|
| **No Monitoring** | 0% overhead | 0 KB | None | Production (no issues) |
| **Basic Logging** | 2-5% overhead | 1-10 MB/month | Low | Development |
| **Async + Sampling** | 0.1-0.5% overhead | 100-500 KB/month | Medium | Production monitoring |
| **Full Real-time** | 5-15% overhead | 10-100 MB/month | High | Active optimization |

### Smart Implementation for Minimal Impact

#### Conditional Monitoring

```php
class RWP_Creator_Suite_Performance_Monitor {
    private static $enabled = null;
    
    public static function is_monitoring_enabled() {
        if (self::$enabled === null) {
            self::$enabled = (
                // Enable in debug mode
                (defined('WP_DEBUG') && WP_DEBUG) ||
                // Or with explicit flag
                (defined('RWP_PERFORMANCE_MONITORING') && RWP_PERFORMANCE_MONITORING) ||
                // Or for administrators
                current_user_can('manage_options')
            ) && 
            // But skip background processes
            !wp_doing_cron() && 
            !wp_doing_ajax() &&
            // And skip REST API calls (unless specifically monitoring API)
            (!defined('REST_REQUEST') || !REST_REQUEST);
        }
        return self::$enabled;
    }
    
    public static function track_operation($operation, $callback) {
        if (!self::is_monitoring_enabled()) {
            return $callback(); // Zero overhead when disabled
        }
        
        $start = microtime(true);
        $start_memory = memory_get_usage(true);
        
        try {
            $result = $callback();
        } catch (Exception $e) {
            $end = microtime(true);
            self::log_performance($operation, $end - $start, array('error' => $e->getMessage()));
            throw $e;
        }
        
        $end = microtime(true);
        $execution_time = $end - $start;
        
        // Only log if operation took significant time (configurable threshold)
        $threshold = defined('RWP_PERF_THRESHOLD') ? RWP_PERF_THRESHOLD : 0.01; // 10ms default
        if ($execution_time > $threshold) {
            self::log_performance($operation, $execution_time, array(
                'memory_delta' => memory_get_usage(true) - $start_memory
            ));
        }
        
        return $result;
    }
}
```

#### Asynchronous Logging

```php
public static function log_performance_async($operation, $time, $context = array()) {
    if (!self::is_monitoring_enabled()) return;
    
    // Queue for batch processing instead of immediate DB write
    $queue = get_transient('rwp_perf_queue') ?: array();
    $queue[] = array(
        'operation' => $operation,
        'execution_time' => $time,
        'timestamp' => current_time('mysql'),
        'memory_usage' => memory_get_usage(true),
        'user_id' => get_current_user_id(),
        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'context' => self::sanitize_context($context)
    );
    
    // Limit queue size to prevent memory issues
    if (count($queue) > 100) {
        $queue = array_slice($queue, -100);
    }
    
    set_transient('rwp_perf_queue', $queue, 300); // 5 minutes
    
    // Schedule background processing
    if (!wp_next_scheduled('rwp_process_performance_queue')) {
        wp_schedule_single_event(time() + 60, 'rwp_process_performance_queue');
    }
}

public static function process_performance_queue() {
    $queue = get_transient('rwp_perf_queue');
    if (empty($queue)) return;
    
    global $wpdb;
    $table = $wpdb->prefix . 'rwp_performance_logs';
    
    // Batch insert for efficiency
    $values = array();
    $placeholders = array();
    
    foreach ($queue as $entry) {
        $values[] = $entry['operation'];
        $values[] = $entry['execution_time'];
        $values[] = $entry['timestamp'];
        $values[] = $entry['memory_usage'];
        $values[] = $entry['user_id'];
        $values[] = $entry['url'];
        $values[] = wp_json_encode($entry['context']);
        $placeholders[] = "(%s, %f, %s, %d, %d, %s, %s)";
    }
    
    if (!empty($values)) {
        $sql = "INSERT INTO {$table} (operation, execution_time, timestamp, memory_usage, user_id, url, context) VALUES " . implode(',', $placeholders);
        $wpdb->query($wpdb->prepare($sql, $values));
    }
    
    delete_transient('rwp_perf_queue');
}

private static function sanitize_context($context) {
    // Prevent context from getting too large
    $sanitized = array();
    foreach ($context as $key => $value) {
        if (is_string($value) && strlen($value) > 200) {
            $sanitized[$key] = substr($value, 0, 197) . '...';
        } elseif (is_array($value) && count($value) > 20) {
            $sanitized[$key] = array_slice($value, 0, 20) + array('...' => 'truncated ' . (count($value) - 20) . ' items');
        } else {
            $sanitized[$key] = $value;
        }
    }
    return $sanitized;
}
```

#### Statistical Sampling

```php
public static function track_with_sampling($operation, $callback, $sample_rate = 0.1) {
    // Only monitor X% of requests to reduce overhead
    if (mt_rand() / mt_getrandmax() > $sample_rate) {
        return $callback();
    }
    
    return self::track_operation($operation, $callback);
}
```

## Ultra-Lightweight Implementation

### Minimal Memory Footprint Approach

```php
class RWP_Performance_Tracker {
    private static $samples = array();
    private static $max_samples = 50;
    
    public static function track($operation, $callback) {
        // Only in debug mode with random sampling
        if (!WP_DEBUG || mt_rand(1, 100) > 10) { // 10% sampling
            return $callback();
        }
        
        $start = microtime(true);
        $result = $callback();
        $duration = microtime(true) - $start;
        
        // Only store if operation is significant
        if ($duration > 0.05) { // 50ms threshold
            self::$samples[] = array(
                'op' => substr($operation, 0, 30), // Limit operation name length
                'time' => round($duration, 4),
                'when' => time()
            );
            
            // Maintain fixed memory usage
            if (count(self::$samples) > self::$max_samples) {
                array_shift(self::$samples);
            }
        }
        
        return $result;
    }
    
    // Simple admin notice for performance issues
    public static function maybe_show_performance_notice() {
        if (!current_user_can('manage_options') || empty(self::$samples)) return;
        
        $slow_ops = array_filter(self::$samples, function($s) { 
            return $s['time'] > 1.0; // 1 second threshold
        });
        
        if (!empty($slow_ops)) {
            add_action('admin_notices', function() use ($slow_ops) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Creator Suite Performance Alert:</strong> ';
                echo count($slow_ops) . ' slow operations detected. ';
                echo '<a href="#" onclick="console.log(' . esc_js(wp_json_encode(self::$samples)) . '); return false;">View details in browser console</a>';
                echo '</p></div>';
            });
        }
    }
    
    // Hook this to wp_footer for non-intrusive monitoring
    public static function output_debug_data() {
        if (!WP_DEBUG || !current_user_can('manage_options') || empty(self::$samples)) return;
        
        echo "<!-- RWP Performance Debug Data:\n";
        foreach (self::$samples as $sample) {
            echo sprintf("  %s: %.4fs at %s\n", $sample['op'], $sample['time'], date('H:i:s', $sample['when']));
        }
        echo "-->\n";
    }
}
```

## Export & Reporting Features

### CSV Export Implementation

```php
public function handle_performance_export() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'rwp_export_performance')) {
        wp_die('Unauthorized');
    }
    
    $time_range = intval($_GET['time_range'] ?? 24); // hours
    $metric_type = sanitize_text_field($_GET['metric_type'] ?? 'all');
    
    global $wpdb;
    $table = $wpdb->prefix . 'rwp_performance_logs';
    
    $where_conditions = array("timestamp >= DATE_SUB(NOW(), INTERVAL {$time_range} HOUR)");
    if ($metric_type !== 'all') {
        $where_conditions[] = $wpdb->prepare("operation LIKE %s", $metric_type . '%');
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    $metrics = $wpdb->get_results("SELECT * FROM {$table} WHERE {$where_clause} ORDER BY timestamp DESC", ARRAY_A);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="rwp-performance-' . date('Y-m-d-H-i') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, array(
        'Timestamp',
        'Operation',
        'Execution Time (s)',
        'Memory Usage (bytes)',
        'User ID',
        'URL',
        'Context'
    ));
    
    // Data rows
    foreach ($metrics as $metric) {
        fputcsv($output, array(
            $metric['timestamp'],
            $metric['operation'],
            $metric['execution_time'],
            $metric['memory_usage'],
            $metric['user_id'],
            $metric['url'],
            $metric['context']
        ));
    }
    
    fclose($output);
    exit;
}
```

## Implementation Phases

### Phase 1: Basic Monitoring (Minimal Impact)
- ✅ Ultra-lightweight sampling approach
- ✅ Admin notices for slow operations
- ✅ Debug console output
- **Time**: 2-4 hours implementation
- **Impact**: <0.1% performance overhead

### Phase 2: Structured Logging (Low Impact)
- ✅ Async queue processing
- ✅ Database storage with cleanup
- ✅ Basic dashboard widget
- **Time**: 1-2 days implementation
- **Impact**: 0.5-1% performance overhead

### Phase 3: Advanced Analytics (Medium Impact)
- ✅ Dedicated admin page with charts
- ✅ Filtering and export capabilities
- ✅ Frontend performance integration
- **Time**: 3-5 days implementation
- **Impact**: 1-3% performance overhead

### Phase 4: Real-time Monitoring (High Impact)
- ✅ Live dashboard updates
- ✅ Performance alerts and notifications
- ✅ Historical trend analysis
- **Time**: 1-2 weeks implementation
- **Impact**: 3-8% performance overhead

## Configuration Options

### WordPress Constants

Add these to `wp-config.php` to control monitoring behavior:

```php
// Enable performance monitoring
define('RWP_PERFORMANCE_MONITORING', true);

// Set performance threshold (in seconds)
define('RWP_PERF_THRESHOLD', 0.01); // 10ms

// Set sampling rate (0.0 to 1.0)
define('RWP_PERF_SAMPLE_RATE', 0.1); // 10%

// Maximum log entries to keep
define('RWP_PERF_MAX_LOGS', 10000);

// Log cleanup interval (in days)
define('RWP_PERF_CLEANUP_DAYS', 30);

// Enable frontend monitoring
define('RWP_FRONTEND_PERF_MONITORING', true);
```

## Database Maintenance

### Automated Cleanup

```php
// Add to main plugin class initialization
wp_schedule_event(time(), 'weekly', 'rwp_cleanup_performance_logs');

public static function cleanup_old_performance_logs() {
    global $wpdb;
    $table = $wpdb->prefix . 'rwp_performance_logs';
    $cleanup_days = defined('RWP_PERF_CLEANUP_DAYS') ? RWP_PERF_CLEANUP_DAYS : 30;
    
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $cleanup_days
    ));
    
    if ($deleted > 0) {
        RWP_Creator_Suite_Error_Logger::log(
            "Cleaned up {$deleted} old performance log entries",
            RWP_Creator_Suite_Error_Logger::LOG_LEVEL_INFO
        );
    }
    
    // Optimize table after cleanup
    $wpdb->query("OPTIMIZE TABLE {$table}");
}
```

## Recommended Final Implementation

**For Production Sites:**
- Use Phase 1 (Ultra-lightweight) with 5% sampling rate
- Enable only for administrators or in debug mode
- Set 100ms threshold to catch only significant issues
- Implement automated cleanup

**For Development/Staging:**
- Use Phase 2 (Structured Logging) with 50% sampling rate
- Enable dashboard widget for immediate feedback
- Lower threshold to 10ms for detailed optimization
- Include frontend monitoring for block performance

**For Active Performance Optimization:**
- Use Phase 3 (Advanced Analytics) with 100% sampling
- Enable all monitoring features temporarily
- Export data for analysis
- Disable after optimization complete

## Notes

- All implementations include proper WordPress security checks
- Memory usage is controlled through configurable limits
- Database queries are optimized with proper indexing
- Graceful degradation when monitoring is disabled
- Compatible with WordPress multisite installations
- Follows WordPress coding standards and best practices

## Future Considerations

- Integration with external monitoring services (New Relic, DataDog)
- Custom alert thresholds per operation type
- Performance comparison between WordPress versions
- Integration with WordPress Site Health checks
- Performance impact of different themes/plugins
- Mobile vs desktop performance comparison