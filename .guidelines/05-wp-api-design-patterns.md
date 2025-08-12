# WordPress API Design Patterns (AI-Optimized)

## Core Principles
- **WordPress REST API** as foundation
- **Consistent response formats** across all endpoints
- **Proper nonce handling** for security
- **Guest vs authenticated** response patterns
- **Cache-friendly headers** for guest content

## API Structure

### Endpoint Registration
```php
class Plugin_Name_API {
    private $namespace = 'plugin-name/v1';
    
    public function register_routes() {
        // Guest-accessible endpoints
        register_rest_route( $this->namespace, '/public-data', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_public_data' ),
            'permission_callback' => '__return_true', // Public access
            'args'                => $this->get_public_data_args(),
        ) );
        
        // User-only endpoints
        register_rest_route( $this->namespace, '/user-preferences', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_user_preferences' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'update_user_preferences' ),
                'permission_callback' => array( $this, 'check_user_logged_in' ),
                'args'                => $this->get_preferences_args(),
            ),
        ) );
        
        // Admin-only endpoints
        register_rest_route( $this->namespace, '/admin/settings', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'update_settings' ),
            'permission_callback' => array( $this, 'check_admin_permissions' ),
            'args'                => $this->get_settings_args(),
        ) );
    }
}
```

### Permission Callbacks
```php
class Plugin_Name_API_Permissions {
    
    public function check_user_logged_in( $request ) {
        return is_user_logged_in();
    }
    
    public function check_admin_permissions( $request ) {
        return current_user_can( 'manage_options' );
    }
    
    public function check_user_can_edit( $request ) {
        $user_id = $request->get_param( 'user_id' );
        $current_user = get_current_user_id();
        
        // Users can edit their own data, admins can edit anyone's
        return $current_user === (int) $user_id || current_user_can( 'edit_users' );
    }
    
    public function check_nonce_and_permissions( $request ) {
        // Verify nonce for state-changing operations
        $nonce = $request->get_header( 'X-WP-Nonce' );
        
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'rest_forbidden', 'Invalid nonce', array( 'status' => 403 ) );
        }
        
        return $this->check_user_logged_in( $request );
    }
}
```

## Response Patterns

### Consistent Response Format
```php
class Plugin_Name_API_Response {
    
    public function success_response( $data, $message = '', $meta = array() ) {
        $response = array(
            'success' => true,
            'data'    => $data,
        );
        
        if ( $message ) {
            $response['message'] = $message;
        }
        
        if ( ! empty( $meta ) ) {
            $response['meta'] = $meta;
        }
        
        return rest_ensure_response( $response );
    }
    
    public function error_response( $code, $message, $data = null, $status = 400 ) {
        $response = array(
            'success' => false,
            'error'   => array(
                'code'    => $code,
                'message' => $message,
            ),
        );
        
        if ( $data ) {
            $response['error']['data'] = $data;
        }
        
        return new WP_Error( $code, $message, array( 'status' => $status ) );
    }
    
    public function paginated_response( $items, $total, $page = 1, $per_page = 20 ) {
        $total_pages = ceil( $total / $per_page );
        
        return $this->success_response( $items, '', array(
            'pagination' => array(
                'total'       => $total,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'per_page'    => $per_page,
                'has_next'    => $page < $total_pages,
                'has_prev'    => $page > 1,
            ),
        ) );
    }
}
```

### Cache-Friendly Headers
```php
class Plugin_Name_API_Caching {
    
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
    
    public function add_etag( $response, $data ) {
        $etag = '"' . md5( wp_json_encode( $data ) ) . '"';
        $response->header( 'ETag', $etag );
        
        // Check if client has current version
        $client_etag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ( $client_etag === $etag ) {
            $response->set_status( 304 );
            $response->set_data( null );
        }
        
        return $response;
    }
}
```

## Client-Side API Client

### Base API Client
```javascript
class PluginNameAPIClient {
    constructor() {
        this.baseUrl = pluginNameData.apiUrl; // Localized from PHP
        this.nonce = pluginNameData.nonce;
        this.namespace = 'plugin-name/v1';
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}/${this.namespace}/${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce,
            },
            credentials: 'same-origin',
        };
        
        const requestOptions = { ...defaultOptions, ...options };
        
        // Add JSON body if data provided
        if (options.data && !options.body) {
            requestOptions.body = JSON.stringify(options.data);
        }
        
        try {
            const response = await fetch(url, requestOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            // Handle WordPress error responses
            if (data.success === false) {
                throw new Error(data.error?.message || 'API request failed');
            }
            
            return data;
            
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }
    
    // GET request
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        
        return this.request(url, { method: 'GET' });
    }
    
    // POST request
    async post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            data: data,
        });
    }
    
    // PUT request
    async put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            data: data,
        });
    }
    
    // DELETE request
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
}
```

### Specific API Methods
```javascript
class PluginNameAPI extends PluginNameAPIClient {
    
    // Public data (cacheable for guests)
    async getPublicData(filters = {}) {
        return this.get('public-data', filters);
    }
    
    // User preferences (auth required)
    async getUserPreferences() {
        return this.get('user-preferences');
    }
    
    async updateUserPreferences(preferences) {
        return this.post('user-preferences', preferences);
    }
    
    // Paginated data
    async getPaginatedItems(page = 1, perPage = 20, filters = {}) {
        const params = {
            page: page,
            per_page: perPage,
            ...filters
        };
        
        return this.get('items', params);
    }
    
    // File upload
    async uploadFile(file, additionalData = {}) {
        const formData = new FormData();
        formData.append('file', file);
        
        Object.keys(additionalData).forEach(key => {
            formData.append(key, additionalData[key]);
        });
        
        return this.request('upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-WP-Nonce': this.nonce,
                // Don't set Content-Type, let browser set it for FormData
            },
        });
    }
}
```

## Error Handling

### PHP Error Handling
```php
class Plugin_Name_API_Error_Handler {
    
    public function handle_validation_error( $errors ) {
        if ( is_wp_error( $errors ) ) {
            $error_data = array();
            
            foreach ( $errors->get_error_codes() as $code ) {
                $error_data[ $code ] = $errors->get_error_messages( $code );
            }
            
            return new WP_Error(
                'validation_failed',
                'Validation failed',
                array(
                    'status' => 400,
                    'errors' => $error_data,
                )
            );
        }
        
        return $errors;
    }
    
    public function handle_database_error( $result, $operation = 'database operation' ) {
        if ( false === $result ) {
            return new WP_Error(
                'database_error',
                sprintf( 'Failed to perform %s', $operation ),
                array( 'status' => 500 )
            );
        }
        
        return $result;
    }
    
    public function handle_not_found( $item, $type = 'item' ) {
        if ( ! $item ) {
            return new WP_Error(
                'not_found',
                sprintf( '%s not found', ucfirst( $type ) ),
                array( 'status' => 404 )
            );
        }
        
        return $item;
    }
}
```

### JavaScript Error Handling
```javascript
class PluginNameErrorHandler {
    constructor() {
        this.errors = [];
        this.maxErrors = 10;
    }
    
    handleAPIError(error, context = '') {
        const errorInfo = {
            message: error.message,
            context: context,
            timestamp: Date.now(),
            url: window.location.href,
            userAgent: navigator.userAgent
        };
        
        // Store error for debugging
        this.errors.push(errorInfo);
        if (this.errors.length > this.maxErrors) {
            this.errors.shift(); // Remove oldest error
        }
        
        // Log to console in development
        if (pluginNameData.debug) {
            console.error('API Error:', errorInfo);
        }
        
        // Show user-friendly message
        this.showUserMessage(this.getUserFriendlyMessage(error));
        
        // Optionally send to server for logging
        this.maybeLogToServer(errorInfo);
    }
    
    getUserFriendlyMessage(error) {
        const friendlyMessages = {
            'Network Error': 'Connection problem. Please check your internet connection.',
            'HTTP 403': 'You don\'t have permission to perform this action.',
            'HTTP 404': 'The requested resource was not found.',
            'HTTP 500': 'Server error. Please try again later.',
            'validation_failed': 'Please check your input and try again.',
        };
        
        for (const [key, message] of Object.entries(friendlyMessages)) {
            if (error.message.includes(key)) {
                return message;
            }
        }
        
        return 'Something went wrong. Please try again.';
    }
    
    showUserMessage(message, type = 'error') {
        // Simple notification system
        const notification = document.createElement('div');
        notification.className = `plugin-name-notification plugin-name-notification--${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
}
```

## WordPress Integration

### Localize API Data
```php
public function enqueue_scripts() {
    wp_enqueue_script(
        'plugin-name-api',
        plugin_dir_url( __FILE__ ) . 'assets/js/api.js',
        array(),
        '1.0.0',
        true
    );
    
    wp_localize_script(
        'plugin-name-api',
        'pluginNameData',
        array(
            'apiUrl'    => rest_url(),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'debug'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'userId'    => get_current_user_id(),
            'isLoggedIn' => is_user_logged_in(),
        )
    );
}
```

### Guest vs User Response Logic
```php
public function get_content( $request ) {
    $content = $this->fetch_content( $request->get_params() );
    
    if ( is_user_logged_in() ) {
        // Add user-specific data
        $content['user_data'] = $this->get_user_specific_data();
        $content['permissions'] = $this->get_user_permissions();
    } else {
        // Add guest-appropriate data only
        $content['guest_message'] = 'Sign up to unlock more features!';
    }
    
    $response = rest_ensure_response( array(
        'success' => true,
        'data'    => $content,
    ) );
    
    // Set appropriate caching
    return $this->set_cache_headers( $response );
}
```

## Critical Rules

1. **Always Use WordPress Nonces** - Include `X-WP-Nonce` header
2. **Consistent Response Format** - Success/error structure
3. **Guest-Friendly Caching** - Cache public endpoints
4. **Permission Callbacks** - Check permissions before processing
5. **Error Handling** - User-friendly messages, proper logging
6. **Data Validation** - Sanitize inputs, validate schemas
7. **Rate Limiting** - Prevent abuse (WordPress plugins available)