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

### Naming
```css
.plugin-name-container {}
.plugin-name-button-primary {}

/* Breakpoints */
@media screen and (max-width: 782px) {}
```

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

## Critical Rules
1. Prefix everything: `plugin_name_`
2. Escape output: `esc_*`
3. Sanitize input: `sanitize_*`
4. Use nonces for forms/AJAX
5. Check capabilities
6. Use `$wpdb->prepare()`
7. Text domain: `'plugin-name'`