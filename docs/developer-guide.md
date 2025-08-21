# RWP Creator Suite Developer Guide

## Architecture Overview

The RWP Creator Suite is built using modern WordPress development practices with a modular architecture designed for extensibility and maintainability.

### Core Architecture

```
rwp-creator-suite/
├── src/
│   ├── blocks/              # Gutenberg blocks
│   └── modules/             # PHP backend modules
├── assets/                  # Frontend assets
│   ├── js/                  # JavaScript files
│   └── css/                 # Stylesheet files
├── templates/               # Email templates
└── docs/                    # Documentation
```

### Key Components

1. **Block System**: Gutenberg blocks for frontend interaction
2. **Module System**: Modular PHP classes for backend functionality
3. **State Management**: Client-side state management with RWPStateManager
4. **API Layer**: REST API endpoints for AJAX interactions
5. **Analytics System**: Privacy-focused analytics with GDPR compliance

## Adding New Blocks

### Step 1: Create Block Structure

```bash
# Create new block directory
mkdir src/blocks/my-new-block

# Create required files
touch src/blocks/my-new-block/block.json
touch src/blocks/my-new-block/index.js
touch src/blocks/my-new-block/edit.js
touch src/blocks/my-new-block/save.js
touch src/blocks/my-new-block/render.php
touch src/blocks/my-new-block/style.scss
touch src/blocks/my-new-block/editor.scss
```

### Step 2: Configure block.json

```json
{
    "apiVersion": 2,
    "name": "rwp-creator-suite/my-new-block",
    "title": "My New Block",
    "category": "rwp-creator-suite",
    "description": "Description of what the block does",
    "keywords": ["keyword1", "keyword2"],
    "version": "1.0.0",
    "textdomain": "rwp-creator-suite",
    "supports": {
        "html": false,
        "customClassName": false
    },
    "attributes": {
        "myAttribute": {
            "type": "string",
            "default": ""
        }
    },
    "render": "file:./render.php"
}
```

### Step 3: Add Webpack Entry Points

In `webpack.config.js`:

```javascript
'blocks/my-new-block/index': path.resolve(
    __dirname,
    'src/blocks/my-new-block/index.js'
),
'blocks/my-new-block/style': path.resolve(
    __dirname,
    'src/blocks/my-new-block/style.js'
),
'blocks/my-new-block/editor': path.resolve(
    __dirname,
    'src/blocks/my-new-block/editor.js'
),
```

### Step 4: Register Block in PHP

In `src/modules/blocks/class-block-manager.php`:

```php
$this->register_single_block('my-new-block');
```

## Extending APIs

### Creating New REST Endpoints

1. **Create API Class**:

```php
<?php
class RWP_Creator_Suite_My_API {
    
    public function register_routes() {
        register_rest_route('rwp-creator-suite/v1', '/my-endpoint', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_request'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }
    
    public function handle_request($request) {
        // Handle the request
        return new WP_REST_Response($data, 200);
    }
    
    public function check_permissions($request) {
        return current_user_can('edit_posts');
    }
}
```

2. **Register in Main Plugin File**:

```php
$api = new RWP_Creator_Suite_My_API();
add_action('rest_api_init', array($api, 'register_routes'));
```

## State Management

### Using RWPStateManager

The plugin uses a custom state management system for consistent data handling across components.

```javascript
// Initialize state
const state = new RWPStateManager('my_component', {
    initialProperty: 'value',
    anotherProperty: []
});

// Get current state
const currentState = state.getState();

// Update state
state.setState({ 
    initialProperty: 'new value' 
});

// Subscribe to changes
state.subscribe((newState, previousState) => {
    console.log('State changed:', newState);
});
```

### State Persistence

For guest users, state can be persisted to localStorage:

```javascript
// Save state for guest users
saveGuestState() {
    if (!this.detectUserLoginState()) {
        const key = this.getGuestStorageKey();
        const stateToSave = {
            state: this.state.getState(),
            timestamp: Date.now()
        };
        localStorage.setItem(key, JSON.stringify(stateToSave));
    }
}
```

## Testing Guidelines

### Running Tests

```bash
# Run PHP tests
npm run test:php

# Run JavaScript tests
npm run test:js

# Run all tests
npm run test

# Run tests with coverage
npm run test:coverage
```

### Writing PHP Tests

```php
<?php
class Test_My_Feature extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        // Setup test data
    }
    
    public function test_my_functionality() {
        // Arrange
        $expected = 'expected result';
        
        // Act
        $actual = my_function();
        
        // Assert
        $this->assertEquals($expected, $actual);
    }
}
```

### Writing JavaScript Tests

```javascript
import { MyComponent } from '../src/components/MyComponent';

describe('MyComponent', () => {
    test('should render correctly', () => {
        const component = new MyComponent();
        expect(component.render()).toBeDefined();
    });
});
```

## Performance Best Practices

### Database Queries

1. **Use Caching**:
```php
$cache_key = 'my_cache_key';
$result = wp_cache_get($cache_key, 'my_group');

if (false === $result) {
    $result = $this->expensive_operation();
    wp_cache_set($cache_key, $result, 'my_group', HOUR_IN_SECONDS);
}
```

2. **Optimize Queries**:
```php
// Use prepared statements
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE status = %s",
    $status
));

// Combine multiple queries when possible
$stats = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
     FROM {$table} 
     WHERE created >= %s",
    $start_date
), ARRAY_A);
```

### Frontend Optimization

1. **Lazy Load Components**:
```javascript
// Only initialize when needed
if (document.querySelector('[data-my-component]')) {
    import('./MyComponent').then(({ MyComponent }) => {
        new MyComponent();
    });
}
```

2. **Debounce User Input**:
```javascript
const debouncedHandler = this.debounce((value) => {
    this.handleSearch(value);
}, 300);
```

## Security Guidelines

### Input Sanitization

Always sanitize user input:

```php
// Sanitize text input
$clean_text = sanitize_text_field($_POST['user_input']);

// Sanitize textarea
$clean_textarea = sanitize_textarea_field($_POST['description']);

// Sanitize email
$clean_email = sanitize_email($_POST['email']);
```

### Nonce Verification

```php
// Create nonce
$nonce = wp_create_nonce('my_action_nonce');

// Verify nonce
if (!wp_verify_nonce($_POST['nonce'], 'my_action_nonce')) {
    wp_die('Security check failed');
}
```

### Escape Output

```php
// Escape HTML
echo esc_html($user_data);

// Escape attributes
echo '<div class="' . esc_attr($css_class) . '">';

// Escape URLs
echo '<a href="' . esc_url($link) . '">';
```

## Debugging

### Enable Debug Mode

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Logging

```php
// Log errors
error_log('Debug message: ' . $variable);

// Log arrays/objects
error_log('Debug data: ' . print_r($data, true));
```

### Browser Debugging

```javascript
// Use console groups for organized logging
console.group('My Component Debug');
console.log('State:', this.state.getState());
console.log('Elements:', this.elements);
console.groupEnd();
```

## Deployment

### Build Process

```bash
# Development build
npm run build

# Production build with optimizations
npm run build:production

# Create deployment package
npm run package
```

### Version Management

Update version in:
1. `package.json`
2. Main plugin file header
3. `readme.txt` stable tag

## Contributing

### Code Style

The project uses:
- WordPress Coding Standards for PHP
- ESLint for JavaScript
- Prettier for code formatting

### Pull Request Process

1. Create feature branch from `main`
2. Implement changes with tests
3. Run linting and tests
4. Submit pull request with detailed description
5. Ensure CI passes

### Commit Messages

Use conventional commit format:
- `feat:` new features
- `fix:` bug fixes
- `docs:` documentation changes
- `style:` code style changes
- `refactor:` code refactoring
- `test:` adding tests