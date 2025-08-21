# WordPress Performance Optimization (AI-Optimized)

## Core Performance Principles
- **Database query optimization** with caching and prepared statements
- **Frontend lazy loading** for heavy components
- **Debounced user interactions** to prevent excessive API calls
- **Efficient state management** with localStorage-first approach
- **Asset optimization** and critical path prioritization

## Database Performance

### Query Optimization Patterns
```php
// Use caching for expensive operations
$cache_key = 'my_cache_key';
$result = wp_cache_get($cache_key, 'my_group');

if (false === $result) {
    $result = $this->expensive_operation();
    wp_cache_set($cache_key, $result, 'my_group', HOUR_IN_SECONDS);
}
```

### Efficient Database Queries
```php
// Combine multiple queries when possible
$stats = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
     FROM {$table} 
     WHERE created >= %s",
    $start_date
), ARRAY_A);

// Use prepared statements always
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE status = %s",
    $status
));
```

### Cache Strategy Implementation
```php
class Plugin_Name_Cache_Strategy {
    public function should_cache_for_user() {
        // Cache everything for guests
        if ( ! is_user_logged_in() ) {
            return true;
        }
        
        // Cache public content for logged-in users
        return ! $this->has_personalized_content();
    }
    
    public function get_cache_key( $context = '' ) {
        $key = 'plugin_name_' . $context;
        
        // Add user-specific cache busting only when needed
        if ( is_user_logged_in() && $this->requires_user_context( $context ) ) {
            $key .= '_user_' . get_current_user_id();
        }
        
        return $key;
    }
}
```

## Frontend Performance

### Lazy Loading Components
```javascript
// Only initialize when needed
if (document.querySelector('[data-my-component]')) {
    import('./MyComponent').then(({ MyComponent }) => {
        new MyComponent();
    });
}
```

### Debounced User Input
```javascript
const debouncedHandler = this.debounce((value) => {
    this.handleSearch(value);
}, 300);

// Debounce utility function
debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
```

### State Management Optimization
```javascript
class PluginNameStateManager {
    constructor() {
        this.saveTimeout = null;
        this.saveDelay = 1000; // 1 second debounce
    }
    
    debouncedSave() {
        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
            this.saveToStorage();
        }, this.saveDelay);
    }
    
    saveToStorage() {
        const state = this.getState();
        const persistableState = this.filterPersistableData(state);
        
        this.setToStorage('appState', {
            data: persistableState,
            timestamp: Date.now(),
            version: '1.0'
        });
    }
}
```

## Asset Optimization

### Critical CSS Inlining
```php
function plugin_name_inline_critical_css() {
    $critical_css = '
    .plugin-name-container {
        display: block;
        max-width: 100%;
    }
    .plugin-name-button {
        display: inline-block;
        padding: 0.5rem 1rem;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 0.375rem;
    }
    ';
    
    wp_add_inline_style('wp-block-library', $critical_css);
}
add_action('wp_enqueue_scripts', 'plugin_name_inline_critical_css');
```

### Conditional Asset Loading
```php
private function should_load_frontend_assets() {
    // Load on specific post types or pages with shortcodes
    global $post;
    
    if ( is_admin() ) {
        return false;
    }
    
    // Check for shortcodes
    if ( $post && has_shortcode( $post->post_content, 'plugin_name' ) ) {
        return true;
    }
    
    // Check for specific post types
    if ( is_singular( array( 'product', 'service' ) ) ) {
        return true;
    }
    
    return false;
}
```

### Cache Busting Strategy
```php
private function enqueue_script( $handle, $dependencies = array() ) {
    $script_path = "assets/dist/{$handle}.js";
    $script_file = plugin_dir_path( PLUGIN_NAME_FILE ) . $script_path;
    
    // Use file modification time for cache busting in development
    $version = $this->is_debug && file_exists( $script_file ) 
        ? filemtime( $script_file ) 
        : $this->version;
    
    wp_enqueue_script(
        "plugin-name-{$handle}",
        plugin_dir_url( PLUGIN_NAME_FILE ) . $script_path,
        $dependencies,
        $version,
        true
    );
}
```

## Memory Management

### State Cleanup
```javascript
class PluginNameFormPersistence {
    cleanupExpiredData() {
        const now = Date.now();
        const maxAge = 24 * 60 * 60 * 1000; // 24 hours
        
        Object.keys(this.sessionForms).forEach(key => {
            const data = this.sessionForms.get(key);
            if (data.timestamp && (now - data.timestamp) > maxAge) {
                this.sessionForms.delete(key);
            }
        });
    }
    
    // Clean up old state data
    filterPersistableData(state) {
        return {
            app: {
                currentView: state.app.currentView,
                formData: state.app.formData,
                filters: state.app.filters,
                selections: state.app.selections
            },
            user: {
                preferences: state.user.preferences
                // Don't persist tempData or sensitive info
            }
        };
    }
}
```

### Efficient DOM Manipulation
```javascript
class PluginNameApp {
    updateElements(data) {
        // Batch DOM updates
        const fragment = document.createDocumentFragment();
        
        data.forEach(item => {
            const element = this.createElement(item);
            fragment.appendChild(element);
        });
        
        // Single DOM update
        this.container.appendChild(fragment);
    }
    
    // Use event delegation instead of individual listeners
    setupEventListeners() {
        this.container.addEventListener('click', (e) => {
            if (e.target.matches('.plugin-name-button')) {
                this.handleButtonClick(e);
            }
        });
    }
}
```

## API Performance

### Response Caching
```php
public function set_cache_headers( $response, $cache_time = 3600 ) {
    if ( ! is_user_logged_in() ) {
        // Cache for guests
        $response->header( 'Cache-Control', 'public, max-age=' . $cache_time );
        $response->header( 'Expires', gmdate( 'D, d M Y H:i:s', time() + $cache_time ) . ' GMT' );
    } else {
        // No cache for logged-in users
        $response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );
        $response->header( 'Expires', '0' );
    }
    
    return $response;
}
```

### Request Optimization
```javascript
class PluginNameAPIClient {
    constructor() {
        this.requestCache = new Map();
        this.maxCacheSize = 50;
    }
    
    async request(endpoint, options = {}) {
        // Check cache for GET requests
        if (options.method === 'GET' || !options.method) {
            const cacheKey = `${endpoint}${JSON.stringify(options.data || {})}`;
            if (this.requestCache.has(cacheKey)) {
                return this.requestCache.get(cacheKey);
            }
        }
        
        const response = await this.performRequest(endpoint, options);
        
        // Cache successful GET responses
        if ((options.method === 'GET' || !options.method) && response.success) {
            this.cacheResponse(cacheKey, response);
        }
        
        return response;
    }
    
    cacheResponse(key, response) {
        // Implement LRU cache
        if (this.requestCache.size >= this.maxCacheSize) {
            const firstKey = this.requestCache.keys().next().value;
            this.requestCache.delete(firstKey);
        }
        
        this.requestCache.set(key, response);
    }
}
```

## Monitoring and Profiling

### Performance Metrics Collection
```javascript
class PluginNamePerformanceMonitor {
    constructor() {
        this.metrics = {
            apiCalls: 0,
            cacheHits: 0,
            cacheMisses: 0,
            renderTime: 0
        };
    }
    
    trackAPICall(endpoint, duration) {
        this.metrics.apiCalls++;
        
        if (duration > 1000) { // Slow request
            console.warn(`Slow API call to ${endpoint}: ${duration}ms`);
        }
    }
    
    trackCachePerformance(hit) {
        if (hit) {
            this.metrics.cacheHits++;
        } else {
            this.metrics.cacheMisses++;
        }
    }
    
    getMetrics() {
        return {
            ...this.metrics,
            cacheHitRate: this.metrics.cacheHits / (this.metrics.cacheHits + this.metrics.cacheMisses)
        };
    }
}
```

### PHP Performance Logging
```php
class Plugin_Name_Performance_Logger {
    
    public function log_slow_query( $query, $duration ) {
        if ( $duration > 1.0 ) { // Queries slower than 1 second
            error_log( sprintf(
                'Slow query detected: %s (%.2fs)',
                $query,
                $duration
            ) );
        }
    }
    
    public function track_memory_usage( $context ) {
        $memory_mb = memory_get_usage( true ) / 1024 / 1024;
        $peak_mb = memory_get_peak_usage( true ) / 1024 / 1024;
        
        if ( $memory_mb > 50 ) { // High memory usage
            error_log( sprintf(
                'High memory usage in %s: %.2f MB (peak: %.2f MB)',
                $context,
                $memory_mb,
                $peak_mb
            ) );
        }
    }
}
```

## Critical Performance Rules

1. **Cache Everything Possible** - Especially for guest users
2. **Debounce User Input** - Prevent excessive API calls
3. **Lazy Load Components** - Only load what's needed
4. **Optimize Database Queries** - Use prepared statements and caching
5. **Batch DOM Updates** - Minimize reflow and repaint
6. **Monitor Performance** - Track metrics and identify bottlenecks
7. **Use Event Delegation** - Reduce memory usage
8. **Clean Up Resources** - Remove expired data and unused listeners
9. **Conditional Loading** - Only load assets when needed
10. **Profile Regularly** - Use browser dev tools and WordPress profiling
11. **Nest All Admin Pages** - All admin option pages MUST use `add_submenu_page()` with parent slug `'rwp-creator-tools'` - never create additional top-level menus