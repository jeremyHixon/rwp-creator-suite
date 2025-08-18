# WordPress Architecture Preferences (AI-Optimized)

> **⚠️ Conflict Check Required**: AI agents should flag any directives that contradict the WordPress coding standards or block development documentation. When conflicts arise, either rethink the approach or seek WordPress-compliant alternatives.

## Development Notes
- **Private Plugin**: Architecture optimized for specific use case, not public distribution
- **Documentation Over Standards**: Detailed docs compensate for non-traditional patterns
- **SEO Trade-off**: Interactive apps prioritized over search visibility for user-specific content
- **WordPress Compliance**: Blocks strictly follow guidelines, apps have freedom
- **No Server Fallbacks**: Client-side storage failures handled with warnings, not database storage

## Development Safety Guidelines

### Destructive Action Policy
- **Additive Development Only**: All development should add or enhance functionality
- **No Inferred Removals**: Never remove existing functionality unless explicitly requested
- **Explicit Confirmation Required**: Any destructive changes must be specifically requested by the developer
- **Preserve Existing Patterns**: When updating code, maintain existing functionality and patterns
- **Database Safety**: Never drop tables, columns, or user data without explicit instruction
- **API Backwards Compatibility**: Maintain existing endpoints and response formats
- **Block Preservation**: Don't modify existing block functionality without explicit request

### What Constitutes Destructive Actions
- Removing existing functions, classes, or methods
- Dropping database tables or columns  
- Removing API endpoints or changing response structures
- Deleting user data or preferences
- Removing existing WordPress hooks or filters
- Changing existing block attributes or functionality
- Removing CSS classes or changing existing styling behavior

### Safe Development Practices
- **Extend, don't replace**: Add new methods alongside existing ones
- **Deprecate gracefully**: Mark old functions as deprecated before removal
- **Version API changes**: Create new API versions rather than breaking existing ones
- **Feature flags**: Use feature toggles for experimental functionality
- **Database migrations**: Only add columns/tables, never remove without explicit instruction

### Admin Interface Architecture

#### Existing Menu Structure
The plugin follows a single top-level menu pattern:
- **Main Dashboard**: `'rwp-creator-tools'` (see `src/modules/admin/class-admin-page.php`)
- **Sub-pages**: All feature settings nest under the main menu

#### Implementation Pattern for New Features
```php
class RWP_Creator_Suite_New_Feature_Admin {
    private $menu_slug = 'rwp-new-feature';
    
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
        // Note: Priority 20 to load after main page (priority 10)
    }
    
    public function add_admin_menu() {
        // REQUIRED: Check capabilities first
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // REQUIRED: Use 'rwp-creator-tools' as parent slug
        add_submenu_page(
            'rwp-creator-tools',                        // Parent from class-admin-page.php
            __( 'New Feature Settings', 'rwp-creator-suite' ),
            __( 'New Feature', 'rwp-creator-suite' ),
            'manage_options',
            $this->menu_slug,
            array( $this, 'render_settings_page' )
        );
    }
}
```

#### Integration with Main Dashboard
New features should be represented on the main dashboard (`class-admin-page.php` lines 100-145):
```php
// Add to the "Available Tools" section
<div class="rwp-tool-item">
    <div class="rwp-tool-icon">
        <span class="dashicons dashicons-your-icon"></span>
    </div>
    <div class="rwp-tool-content">
        <h4><?php esc_html_e( 'Your Feature Name', 'rwp-creator-suite' ); ?></h4>
        <p><?php esc_html_e( 'Brief description of the feature.', 'rwp-creator-suite' ); ?></p>
    </div>
</div>
```

## Modular Architecture

### Service-Based Organization
```php
// Preferred: Feature-based modules
src/
├── modules/
│   ├── user-management/
│   │   ├── class-user-service.php
│   │   ├── class-user-api.php
│   │   └── class-user-frontend.php
│   ├── content-management/
│   └── analytics/
├── core/
│   ├── class-service-container.php
│   ├── class-module-loader.php
│   └── interfaces/
├── blocks/
│   └── app-container/
│       ├── block.json
│       ├── index.js
│       ├── edit.js
│       └── save.js
└── assets/
    └── modules/
        ├── user-management/
        └── content-management/
```

### Block-App Integration
```php
// Bridge between blocks and client-side apps
class Plugin_Name_Block_App_Bridge {
    
    public function register_blocks() {
        register_block_type( __DIR__ . '/build/blocks/app-container', array(
            'render_callback' => array( $this, 'render_app_container' ),
        ) );
    }
    
    public function render_app_container( $attributes, $content ) {
        $wrapper_attributes = get_block_wrapper_attributes( array(
            'id' => 'plugin-name-app-' . wp_unique_id(),
            'data-app-type' => $attributes['appType'] ?? 'dashboard',
            'data-config' => wp_json_encode( $attributes ),
        ) );
        
        return sprintf( '<div %s></div>', $wrapper_attributes );
    }
    
    public function enqueue_app_assets() {
        if ( $this->has_app_blocks() ) {
            // Your client-side architecture loads here
            wp_enqueue_script(
                'plugin-name-apps',
                plugin_dir_url( __FILE__ ) . 'build/apps.js',
                array( 'wp-api-fetch' ),
                PLUGIN_NAME_VERSION,
                true
            );
            
            wp_localize_script( 'plugin-name-apps', 'pluginNameData', array(
                'apiUrl' => rest_url( 'plugin-name/v1/' ),
                'nonce'  => wp_create_nonce( 'wp_rest' ),
                'userId' => get_current_user_id(),
                'isLoggedIn' => is_user_logged_in(),
            ) );
        }
    }
    
    private function has_app_blocks() {
        global $post;
        return $post && has_block( 'plugin-name/app-container', $post );
    }
}
```

## Cache-Friendly Patterns

### Guest User Optimization
```php
// Aggressive caching for guests
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

### Static Content Generation
```php
// Generate static content when possible
class Plugin_Name_Static_Generator {
    public function maybe_generate_static_content( $content_type ) {
        if ( ! $this->is_dynamic_content( $content_type ) ) {
            $cache_key = $this->get_cache_key( $content_type );
            $cached = wp_cache_get( $cache_key, 'plugin_name' );
            
            if ( false === $cached ) {
                $cached = $this->generate_content( $content_type );
                wp_cache_set( $cache_key, $cached, 'plugin_name', DAY_IN_SECONDS );
            }
            
            return $cached;
        }
        
        return null;
    }
}
```

## Storage Strategy

### Client-Side Storage Manager with Fallbacks
```javascript
class PluginNameStorage {
    constructor() {
        this.prefix = 'pluginName_';
        this.maxAge = 24 * 60 * 60 * 1000; // 24 hours
        this.storageMode = this.detectStorageMode();
    }
    
    detectStorageMode() {
        try {
            const test = '__test__';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return 'localStorage';
        } catch (e) {
            console.warn('localStorage unavailable, degraded functionality');
            return 'memory';
        }
    }
    
    set(key, data, options = {}) {
        const item = {
            data: data,
            timestamp: Date.now(),
            maxAge: options.maxAge || this.maxAge
        };
        
        try {
            if (this.storageMode === 'localStorage') {
                localStorage.setItem(this.prefix + key, JSON.stringify(item));
                return;
            }
        } catch (e) {
            console.warn('localStorage unavailable, using memory storage');
            this.storageMode = 'memory';
        }
        
        // Fallback to memory storage (session-only)
        this.memoryStorage = this.memoryStorage || {};
        this.memoryStorage[key] = item;
    }
    
    get(key) {
        let item;
        
        if (this.storageMode === 'localStorage') {
            try {
                const stored = localStorage.getItem(this.prefix + key);
                item = stored ? JSON.parse(stored) : null;
            } catch (e) {
                item = this.memoryStorage?.[key] || null;
            }
        } else {
            item = this.memoryStorage?.[key] || null;
        }
        
        if (!item) return null;
        
        // Check expiration
        if (Date.now() - item.timestamp > item.maxAge) {
            this.remove(key);
            return null;
        }
        
        return item.data;
    }
    
    // Sync with server for logged-in users only (no guest storage by design)
    async syncWithServer(key, apiEndpoint) {
        if (!this.isUserLoggedIn()) {
            return; // No server storage for guests
        }
        
        try {
            const serverData = await fetch(apiEndpoint).then(r => r.json());
            this.set(key, serverData);
            return serverData;
        } catch (error) {
            console.warn('Server sync failed for', key, error);
        }
    }
    
    isUserLoggedIn() {
        return document.body.classList.contains('logged-in');
    }
}
```

### User Metadata API Pattern
```php
// Only store user-specific data in database (no guest fallbacks)
class Plugin_Name_User_API {
    public function update_user_preferences( $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'unauthorized', 'Must be logged in', array( 'status' => 401 ) );
        }
        
        $preferences = $request->get_json_params();
        
        // Validate preferences
        $clean_preferences = $this->sanitize_preferences( $preferences );
        
        // Store in user meta (NOT options table, NO guest storage)
        $updated = update_user_meta( $user_id, 'plugin_name_preferences', $clean_preferences );
        
        if ( $updated ) {
            // Clear any related caches
            wp_cache_delete( "user_prefs_{$user_id}", 'plugin_name' );
            
            return rest_ensure_response( array(
                'success' => true,
                'data'    => $clean_preferences,
            ) );
        }
        
        return new WP_Error( 'update_failed', 'Failed to update preferences', array( 'status' => 500 ) );
    }
}
```

## Conflict Detection Points

> **⚠️ AI Agent Instructions**: Flag these potential conflicts:

1. **localStorage usage** - ✅ **RESOLVED** - Apps use localStorage with warning fallbacks
2. **Heavy client-side rendering** - ✅ **ACCEPTED** - SEO trade-off for user-specific content
3. **API-heavy approach** - ✅ **ACCEPTED** - Documented for private use
4. **Minimal PHP rendering** - ✅ **ACCEPTED** - Apps handle presentation layer
5. **Custom module loading** - ✅ **ACCEPTED** - Documentation compensates for non-standard patterns
6. **Block complexity** - ✅ **RESOLVED** - Blocks are simple containers only

## Critical Principles

1. **Modularity First** - Every feature is a self-contained module
2. **Blocks as Containers** - Use WordPress blocks only as mounting points
3. **Client-Side Heavy** - Push logic to JavaScript apps, not blocks
4. **Cache Everything** - Especially for guest users (block containers are static)
5. **localStorage Primary** - Database only for logged-in user preferences
6. **WordPress Compliance** - Never deviate from block guidelines
7. **Warning Over Fallback** - Notify users of storage issues, don't store on server
8. **Nest All Admin Pages** - All admin option pages MUST use `add_submenu_page()` with parent slug `'rwp-creator-tools'` - never create additional top-level menus
9. **Progressive Enhancement** - Ensure basic functionality without JavaScript
10. **Documentation Over Standards** - Detailed docs for non-traditional patterns
11. **Private Use Optimization** - Architecture serves specific needs, not general use

## Performance Goals
- **Guest users**: Fully cacheable block containers, localStorage for app state
- **Logged-in users**: Fast initial load via localStorage, background sync for preferences only
- **SEO friendly**: Static content outside apps handles search visibility
- **Accessible**: Progressive enhancement ensures core functionality without JS
- **Block editor**: Simple, fast placeholders that don't slow down editing
- **Cache plugin compatibility**: Static block containers work with all caching solutions
```

### App Mounting Strategy
```javascript
// Frontend app loader
class PluginNameAppLoader {
    constructor() {
        this.apps = new Map();
        this.init();
    }
    
    init() {
        const containers = document.querySelectorAll('[id^="plugin-name-app-"]');
        containers.forEach(container => this.mountApp(container));
    }
    
    async mountApp(container) {
        const appType = container.dataset.appType;
        const config = JSON.parse(container.dataset.config || '{}');
        
        try {
            const AppClass = await this.loadAppModule(appType);
            const app = new AppClass(container, config);
            this.apps.set(container.id, app);
        } catch (error) {
            console.error(`Failed to load app ${appType}:`, error);
            this.renderFallback(container, appType);
        }
    }
    
    async loadAppModule(appType) {
        const appModules = {
            'dashboard': () => import('@modules/dashboard-app'),
            'user-profile': () => import('@modules/user-profile-app'),
            'data-viewer': () => import('@modules/data-viewer-app'),
        };
        
        if (!appModules[appType]) {
            throw new Error(`Unknown app type: ${appType}`);
        }
        
        const module = await appModules[appType]();
        return module.default;
    }
    
    renderFallback(container, appType) {
        container.innerHTML = `
            <div class="plugin-name-error">
                <p>Unable to load ${appType} app. Please refresh the page.</p>
                <button onclick="location.reload()">Refresh</button>
            </div>
        `;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new PluginNameAppLoader();
});
```

### Module Interface
```php
interface Plugin_Name_Module_Interface {
    public function init();
    public function get_dependencies();
    public function register_hooks();
    public function register_api_endpoints();
}

class Plugin_Name_User_Module implements Plugin_Name_Module_Interface {
    public function init() {
        if ( $this->should_load() ) {
            $this->register_hooks();
            $this->register_api_endpoints();
        }
    }
    
    private function should_load() {
        // Conditional loading logic
        return true;
    }
}
```

### Service Container
```php
class Plugin_Name_Container {
    private static $services = array();
    
    public static function register( $name, $callback ) {
        self::$services[ $name ] = $callback;
    }
    
    public static function get( $name ) {
        if ( ! isset( self::$services[ $name ] ) ) {
            throw new Exception( "Service {$name} not found" );
        }
        
        if ( is_callable( self::$services[ $name ] ) ) {
            self::$services[ $name ] = call_user_func( self::$services[ $name ] );
        }
        
        return self::$services[ $name ];
    }
}
```

## Client-Side Heavy Architecture

### API-First Approach
```php
// Minimal PHP - mostly API endpoints
class Plugin_Name_API {
    public function register_routes() {
        register_rest_route( 'plugin-name/v1', '/user-preferences', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_user_preferences' ),
            'permission_callback' => array( $this, 'check_user_permissions' ),
        ) );
        
        register_rest_route( 'plugin-name/v1', '/user-preferences', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'update_user_preferences' ),
            'permission_callback' => array( $this, 'check_user_permissions' ),
            'args'                => $this->get_preference_schema(),
        ) );
    }
    
    public function get_user_preferences( $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'unauthorized', 'User not logged in', array( 'status' => 401 ) );
        }
        
        $preferences = get_user_meta( $user_id, 'plugin_name_preferences', true );
        return rest_ensure_response( $preferences ?: array() );
    }
}
```

### JavaScript Application Layer
```javascript
// Heavy client-side logic
class PluginNameApp {
    constructor(container, config) {
        this.container = container;
        this.config = config;
        this.api = new PluginNameAPI();
        this.storage = new PluginNameStorage();
        this.state = new PluginNameState();
        this.init();
    }
    
    async init() {
        // Load from localStorage first for speed
        const cachedData = this.storage.get('appData');
        if (cachedData) {
            this.state.setState(cachedData);
            this.render();
        }
        
        // Sync with server for logged-in users only
        if (this.isUserLoggedIn()) {
            try {
                const serverData = await this.api.getUserPreferences();
                this.storage.merge(serverData);
                this.state.setState(serverData);
                this.render();
            } catch (error) {
                console.warn('Failed to sync with server:', error);
                // Continue with cached data - no server fallback by design
            }
        }
    }
    
    render() {
        // Render app into the block container
        this.container.innerHTML = this.getAppHTML();
        this.attachEventListeners();
    }
    
    isUserLoggedIn() {
        return document.body.classList.contains('logged-in');
    }
}