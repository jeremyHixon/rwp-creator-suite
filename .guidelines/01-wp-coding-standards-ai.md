# WordPress Coding Standards (AI-Optimized)

## PHP

### Naming
```php
// Files
class-plugin-name-loader.php
interface-plugin-name-api.php
trait-plugin-name-helper.php

// Classes
class Plugin_Name_Admin {}

// Functions/Methods
function plugin_name_activate() {}
public function get_user_data() {}

// Variables
$user_email = '';
$is_admin_page = false;
```

### Structure
```php
// Spacing: tabs, spaces around operators
if ( $condition ) {
    $result = $value_one + $value_two;
    $array = array( 'key' => 'value' );
}

// Always use braces
if ( $x ) {
    action();
}
```

### Database
```php
global $wpdb;
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}table_name WHERE column = %s",
        $value
    )
);
```

### Security
```php
// Input
$title = sanitize_text_field( $_POST['title'] );
$content = wp_kses_post( $_POST['content'] );
$email = sanitize_email( $_POST['email'] );
$url = esc_url_raw( $_POST['url'] );

// Output
echo esc_html( $title );
echo esc_url( $link );
echo esc_attr( $attribute );
echo wp_kses_post( $content );

// Nonces
wp_nonce_field( 'plugin_name_action', 'plugin_name_nonce' );
if ( ! wp_verify_nonce( $_POST['plugin_name_nonce'], 'plugin_name_action' ) ) {
    wp_die( 'Security check failed' );
}

// Capabilities
if ( ! current_user_can( 'manage_options' ) ) {
    return;
}
```

### Hooks
```php
// Actions
do_action( 'plugin_name_init', $args );
add_action( 'init', 'plugin_name_init', 10, 1 );

// Filters
$output = apply_filters( 'plugin_name_output', $output, $args );
add_filter( 'the_content', 'plugin_name_filter_content', 10, 1 );
```

### Admin Pages
```php
// CORRECT: Follow existing pattern - nest under main plugin menu
add_submenu_page(
    'rwp-creator-tools',                    // Parent slug (from class-admin-page.php)
    __( 'New Feature Settings', 'rwp-creator-suite' ),
    __( 'New Feature', 'rwp-creator-suite' ),
    'manage_options',
    'rwp-new-feature',                      // Unique menu slug
    array( $this, 'render_settings_page' )
);

// WRONG: Do not create additional top-level menus
add_menu_page(
    'Another Tool',
    'Another Tool',
    'manage_options',
    'rwp-another-tool',
    'callback'
); // This clutters the admin menu

// Pattern from existing caption writer (class-admin-settings.php:57-64)
public function add_admin_menu() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    add_submenu_page(
        'rwp-creator-tools',                // Always use this parent
        __( 'Caption Writer AI Settings', 'rwp-creator-suite' ),
        __( 'Caption Writer', 'rwp-creator-suite' ),
        'manage_options',
        $this->menu_slug,
        array( $this, 'render_settings_page' )
    );
}
```

### i18n
```php
__( 'Text', 'plugin-name' );
_e( 'Text', 'plugin-name' );
_x( 'Post', 'noun', 'plugin-name' );
_n( '%s item', '%s items', $count, 'plugin-name' );
```

## JavaScript

### Structure
```javascript
// Variables: camelCase
const userName = '';
let isActive = false;

// Functions: camelCase
function calculateTotal() {}

// Classes: PascalCase
class PluginHandler {}

// jQuery
jQuery( document ).ready( function( $ ) {
    $( '.selector' ).on( 'click', function() {} );
});
```

### AJAX
```javascript
// Localize
wp_localize_script( 'plugin-name-script', 'pluginName', array(
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'plugin_name_nonce' ),
) );

// Request
jQuery.ajax({
    url: pluginName.ajaxUrl,
    type: 'POST',
    data: {
        action: 'plugin_name_action',
        nonce: pluginName.nonce,
        data: someData
    },
    success: function( response ) {}
});
```

## CSS

### RWP Creator Suite Naming Standards
```css
/* Universal RWP prefix for all components */
.rwp-form {}
.rwp-form-group {}
.rwp-content-input {}
.rwp-tone-select {}
.rwp-button {}
.rwp-button--primary {}
.rwp-button--secondary {}
.rwp-generate-button {}
.rwp-copy-button {}

/* Platform selection components */
.rwp-platform-selection {}
.rwp-platform-checkbox {}
.rwp-platform-icon {}
.rwp-platform-name {}

/* Results and content */
.rwp-results-container {}
.rwp-platform-result {}
.rwp-content-versions {}
.rwp-content-version {}

/* Loading and states */
.rwp-loading-container {}
.rwp-loading-spinner {}
.rwp-error-message {}
.rwp-success-notification {}

/* Character counting */
.rwp-character-counter {}
.rwp-character-count {}
.rwp-count-warning {}
.rwp-count-error {}

/* Block-specific containers follow pattern */
.wp-block-rwp-creator-suite-caption-writer {}
.rwp-caption-writer-container {}

.wp-block-rwp-creator-suite-content-repurposer {}
.rwp-content-repurposer-container {}

.wp-block-rwp-creator-suite-account-manager {}
.rwp-account-manager-container {}

/* Tailwind utilities use blk- prefix to avoid conflicts */
.blk-text-center {}
.blk-bg-blue-500 {} 
.blk-p-4 {}
.blk-rounded-lg {}

/* Legacy classes to remove */
/* ❌ Avoid: Classes without rwp- or blk- prefixes */
.old-style-class {}
.mixed_naming-convention {}

/* Breakpoints */
@media screen and (max-width: 782px) {}
```

### Component Organization
- **Form Components**: `.rwp-form`, `.rwp-form-group`, `.rwp-content-input`
- **Platform Selection**: `.rwp-platform-selection`, `.rwp-platform-checkbox`
- **Buttons**: `.rwp-button`, `.rwp-button--primary`, `.rwp-generate-button`
- **Results**: `.rwp-results-container`, `.rwp-platform-result`
- **States**: `.rwp-loading`, `.rwp-error-message`, `.rwp-copied`

## File Headers
```php
<?php
/**
 * @package    Plugin_Name
 * @subpackage Plugin_Name/includes
 * @since      1.0.0
 */

/**
 * @since 1.0.0
 * @param int $id
 * @return array|false
 */
function name( $id ) {}
```

## Debugging

### Enable Debug Mode
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Logging Patterns
```php
// Log errors
error_log('Debug message: ' . $variable);

// Log arrays/objects
error_log('Debug data: ' . print_r($data, true));

// Performance logging
$start_time = microtime(true);
// ... code execution ...
$duration = microtime(true) - $start_time;
if ($duration > 1.0) {
    error_log("Slow operation: {$duration}s");
}
```

### Browser Debugging
```javascript
// Use console groups for organized logging
console.group('My Component Debug');
console.log('State:', this.state.getState());
console.log('Elements:', this.elements);
console.groupEnd();

// Performance timing
console.time('Operation');
// ... code execution ...
console.timeEnd('Operation');
```

## Critical Rules
1. Prefix everything: `plugin_name_`
2. Escape output: `esc_*`
3. Sanitize input: `sanitize_*`
4. Use nonces for forms/AJAX
5. Check capabilities
6. Use `$wpdb->prepare()`
7. Text domain: `'plugin-name'`
8. **RWP Class Naming**: Use universal `rwp-` prefix for components, `blk-` for Tailwind utilities
9. **Nest All Admin Pages**: All admin option pages MUST use `add_submenu_page()` with parent slug `'rwp-creator-tools'` - never create additional top-level menus
10. **Enable Debug Logging**: Use WordPress debug constants and error_log for development
11. **Performance Aware**: Log slow operations and monitor execution times