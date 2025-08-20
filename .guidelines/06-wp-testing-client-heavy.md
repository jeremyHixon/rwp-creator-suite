# WordPress Testing for Client-Heavy Architecture (AI-Optimized)

## Available Test Commands

### PHP Testing Commands
```bash
# Run all PHP tests
composer test
# OR
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/test-error-logger.php

# Run with coverage report
composer run test:coverage
# OR  
./vendor/bin/phpunit --coverage-html coverage/

# Run tests for specific component
./vendor/bin/phpunit --filter=Error_Logger

# Install WordPress test environment
composer run install-wp-tests
```

### JavaScript Testing Commands  
```bash
# Run all JS tests
npm run test:js

# Run tests in watch mode
npm run test:js:watch

# Run with coverage
npm run test:js:coverage

# Run specific test file
npx jest tests/js/state-manager.test.js

# Run all tests (PHP + JS)
npm test
```

### Combined Testing Workflow
```bash
# Complete test suite for CI/deployment
npm run test:coverage    # Runs both PHP and JS with coverage
```

## Test File Organization Standards

### PHP Test File Naming
- **Unit Tests**: `test-{class-name}.php` (e.g., `test-error-logger.php`)
- **Integration Tests**: `integration-{feature}.php` (e.g., `integration-test.php`)
- **API Tests**: `test-{api-name}-api.php` (e.g., `test-registration-api.php`)
- **Trait Tests**: `test-{trait-name}-trait.php` (e.g., `test-api-validation-trait.php`)

### JavaScript Test File Naming
- **Unit Tests**: `{component}.test.js` (e.g., `state-manager.test.js`)
- **Block Tests**: `{block-name}/edit.test.js` (e.g., `caption-writer/edit.test.js`)
- **Utility Tests**: `utils/{utility}.test.js` (e.g., `utils/dom-helpers.test.js`)
- **Integration Tests**: `integration/{feature}.test.js`

### Test Directory Structure
```
tests/
├── bootstrap.php                    # PHPUnit bootstrap
├── test-{core-classes}.php         # Main plugin tests
├── test-{module-name}*.php         # Module-specific tests
├── integration-*.php               # Integration tests
├── phase*-test*.php                # Development phase tests
└── js/
    ├── setup.js                    # Jest setup file
    ├── {component}.test.js         # Component tests
    ├── blocks/
    │   └── {block-name}/
    │       └── edit.test.js        # Block tests
    └── utils/
        └── {utility}.test.js       # Utility tests
```

## Development Phase Testing Strategy

### Phase-Based Test Files (Current Pattern)
- `phase1-simple-test.php` - Basic functionality tests
- `phase1-optimization-test.php` - Performance/optimization tests  
- `user-value-test-runner.php` - Advanced feature tests
- `simple-test-runner.php` - Quick development tests

### Phase Testing Approach
1. **Phase 1**: Core functionality, basic unit tests
2. **Phase 2**: Integration tests, API endpoint tests
3. **Phase 3**: Performance tests, edge cases, full integration

### When to Create Phase Tests
- During major feature development requiring staged testing
- For complex features that need incremental validation
- When testing performance optimizations step-by-step
- For experimental features before full integration

## Test Coverage Requirements

### Coverage Targets
- **PHP Code**: Minimum 80% line coverage for core classes
- **JavaScript Code**: Minimum 85% line coverage for modules
- **Critical Paths**: 100% coverage for security-related functions
- **API Endpoints**: 100% coverage for all REST endpoints

### Coverage Exclusions
- Vendor dependencies (`vendor/`, `node_modules/`)
- Build artifacts (`build/`, `assets/dist/`)
- Development tools (`bin/`, `scripts/`)
- WordPress core functions (mocked in tests)

### Coverage Commands
```bash
# PHP coverage (HTML report)
composer run test:coverage

# JavaScript coverage  
npm run test:js:coverage

# Combined coverage report
npm run test:coverage
```

### Coverage Analysis
- Review coverage reports before deployment
- Identify untested critical paths
- Add tests for low-coverage areas
- Document any intentional coverage exclusions

## LocalWP Development Environment

### WP-CLI Testing Setup
```bash
# Access LocalWP site shell
# Use LocalWP's "Open site shell" feature

# Install testing framework
wp package install wp-cli/scaffold-package-command

# Create plugin tests directory
mkdir wp-content/plugins/plugin-name/tests
cd wp-content/plugins/plugin-name/tests

# Generate test bootstrap
wp scaffold plugin-tests plugin-name

# Install PHPUnit
composer require --dev phpunit/phpunit
composer require --dev brain/monkey
```

### Database Testing Setup
```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE plugin_name_test;"

# Install WordPress test suite
bash bin/install-wp-tests.sh plugin_name_test root password localhost latest
```

## PHP Testing Patterns

### Test Bootstrap
```php
<?php
// tests/bootstrap.php

// Mock WordPress functions for unit tests
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize Brain Monkey for WordPress function mocking
\Brain\Monkey\setUp();

// Load plugin files
require_once __DIR__ . '/../includes/class-plugin-name-api.php';
require_once __DIR__ . '/../includes/class-plugin-name-user-preferences.php';

class Plugin_Name_Test_Case extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
    }
    
    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }
    
    protected function mock_current_user( $user_id = 1, $capabilities = array() ) {
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( $user_id );
        \Brain\Monkey\Functions\when( 'is_user_logged_in' )->justReturn( $user_id > 0 );
        
        foreach ( $capabilities as $cap ) {
            \Brain\Monkey\Functions\when( 'current_user_can' )
                ->with( $cap )
                ->justReturn( true );
        }
    }
}
```

### API Endpoint Testing
```php
<?php
// tests/test-api-endpoints.php

class Plugin_Name_API_Test extends Plugin_Name_Test_Case {
    private $api;
    
    protected function setUp(): void {
        parent::setUp();
        $this->api = new Plugin_Name_API();
    }
    
    public function test_get_user_preferences_requires_login() {
        // Mock logged-out user
        \Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 0 );
        \Brain\Monkey\Functions\when( 'is_user_logged_in' )->justReturn( false );
        
        $request = new WP_REST_Request();
        $response = $this->api->get_user_preferences( $request );
        
        $this->assertInstanceOf( 'WP_Error', $response );
        $this->assertEquals( 'unauthorized', $response->get_error_code() );
    }
    
    public function test_update_user_preferences_success() {
        // Mock logged-in user
        $this->mock_current_user( 123 );
        
        // Mock WordPress functions
        \Brain\Monkey\Functions\expect( 'get_user_meta' )
            ->once()
            ->with( 123, 'plugin_name_preferences', true )
            ->andReturn( array() );
            
        \Brain\Monkey\Functions\expect( 'update_user_meta' )
            ->once()
            ->with( 123, 'plugin_name_preferences', \Mockery::type( 'array' ) )
            ->andReturn( true );
            
        \Brain\Monkey\Functions\expect( 'wp_cache_delete' )
            ->once()
            ->with( 'user_prefs_123', 'plugin_name' );
        
        // Create request with preferences
        $request = new WP_REST_Request();
        $request->set_json_params( array(
            'theme' => 'dark',
            'notifications' => true,
        ) );
        
        $response = $this->api->update_user_preferences( $request );
        
        $this->assertNotInstanceOf( 'WP_Error', $response );
        $this->assertTrue( $response['success'] );
    }
}
```

### User Preferences Testing
```php
<?php
// tests/test-user-preferences.php

class Plugin_Name_User_Preferences_Test extends Plugin_Name_Test_Case {
    private $preferences;
    
    protected function setUp(): void {
        parent::setUp();
        $this->preferences = new Plugin_Name_User_Preferences();
    }
    
    public function test_sanitize_preferences() {
        $dirty_input = array(
            'theme'         => '<script>alert("xss")</script>dark',
            'notifications' => '1',
            'invalid_key'   => 'should be removed',
            'display_settings' => array(
                'sidebar' => 'left<script>',
                'layout'  => 'grid'
            )
        );
        
        $sanitized = $this->call_private_method( 
            $this->preferences, 
            'sanitize_preferences', 
            array( $dirty_input ) 
        );
        
        $this->assertEquals( 'dark', $sanitized['theme'] );
        $this->assertTrue( $sanitized['notifications'] );
        $this->assertArrayNotHasKey( 'invalid_key', $sanitized );
        $this->assertEquals( 'left', $sanitized['display_settings']['sidebar'] );
    }
    
    // Helper to call private methods
    private function call_private_method( $object, $method, $args = array() ) {
        $reflection = new ReflectionClass( get_class( $object ) );
        $method = $reflection->getMethod( $method );
        $method->setAccessible( true );
        
        return $method->invokeArgs( $object, $args );
    }
}
```

## JavaScript Testing

### Jest Setup
```json
// package.json
{
  "devDependencies": {
    "jest": "^29.0.0",
    "jest-environment-jsdom": "^29.0.0",
    "@testing-library/jest-dom": "^5.16.0"
  },
  "scripts": {
    "test": "jest",
    "test:watch": "jest --watch",
    "test:coverage": "jest --coverage"
  },
  "jest": {
    "testEnvironment": "jsdom",
    "setupFilesAfterEnv": ["<rootDir>/tests/js/setup.js"],
    "testMatch": ["<rootDir>/tests/js/**/*.test.js"],
    "collectCoverageFrom": [
      "assets/js/**/*.js",
      "!assets/js/vendor/**"
    ]
  }
}
```

### Jest Test Setup
```javascript
// tests/js/setup.js
import '@testing-library/jest-dom';

// Mock localStorage
const localStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
};
global.localStorage = localStorageMock;

// Mock WordPress global
global.pluginNameData = {
  apiUrl: 'https://example.com/wp-json',
  nonce: 'test-nonce-123',
  debug: true,
  userId: 0,
  isLoggedIn: false
};

// Mock fetch
global.fetch = jest.fn();

// Reset mocks before each test
beforeEach(() => {
  jest.clearAllMocks();
  localStorage.clear();
  fetch.mockClear();
});
```

### State Management Tests
```javascript
// tests/js/state-manager.test.js
import { PluginNameStateManager } from '../../assets/js/state-manager.js';

describe('PluginNameStateManager', () => {
  let stateManager;
  
  beforeEach(() => {
    stateManager = new PluginNameStateManager();
  });
  
  test('initializes with default state', () => {
    const state = stateManager.getState();
    
    expect(state.user.isLoggedIn).toBe(false);
    expect(state.app.currentView).toBe('default');
    expect(state.user.preferences).toEqual({});
  });
  
  test('loads persisted state from localStorage', () => {
    const persistedData = {
      app: { currentView: 'dashboard' },
      user: { preferences: { theme: 'dark' } }
    };
    
    localStorage.getItem.mockReturnValue(JSON.stringify({
      data: persistedData,
      timestamp: Date.now(),
      version: '1.0'
    }));
    
    const newStateManager = new PluginNameStateManager();
    const state = newStateManager.getState();
    
    expect(state.app.currentView).toBe('dashboard');
    expect(state.user.preferences.theme).toBe('dark');
  });
  
  test('updates state and triggers persistence', () => {
    const newState = {
      app: { currentView: 'profile' }
    };
    
    stateManager.updateState(newState);
    
    expect(stateManager.getState().app.currentView).toBe('profile');
    
    // Should save to localStorage (debounced, so we need to wait)
    setTimeout(() => {
      expect(localStorage.setItem).toHaveBeenCalled();
    }, 1100);
  });
});
```

### API Client Tests
```javascript
// tests/js/api-client.test.js
import { PluginNameAPIClient } from '../../assets/js/api-client.js';

describe('PluginNameAPIClient', () => {
  let apiClient;
  
  beforeEach(() => {
    apiClient = new PluginNameAPIClient();
  });
  
  test('makes GET request successfully', async () => {
    const mockResponse = {
      success: true,
      data: { items: ['item1', 'item2'] }
    };
    
    fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => mockResponse
    });
    
    const result = await apiClient.get('test-endpoint');
    
    expect(fetch).toHaveBeenCalledWith(
      'https://example.com/wp-json/plugin-name/v1/test-endpoint',
      expect.objectContaining({
        method: 'GET',
        headers: expect.objectContaining({
          'X-WP-Nonce': 'test-nonce-123'
        })
      })
    );
    
    expect(result).toEqual(mockResponse);
  });
  
  test('handles API errors properly', async () => {
    fetch.mockResolvedValueOnce({
      ok: false,
      status: 403,
      statusText: 'Forbidden'
    });
    
    await expect(apiClient.get('protected-endpoint'))
      .rejects
      .toThrow('HTTP 403: Forbidden');
  });
  
  test('handles WordPress error responses', async () => {
    const errorResponse = {
      success: false,
      error: {
        code: 'unauthorized',
        message: 'You are not logged in'
      }
    };
    
    fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => errorResponse
    });
    
    await expect(apiClient.get('user-data'))
      .rejects
      .toThrow('You are not logged in');
  });
});
```

### Form Persistence Tests
```javascript
// tests/js/form-persistence.test.js
import { PluginNameFormPersistence } from '../../assets/js/form-persistence.js';

describe('PluginNameFormPersistence', () => {
  let mockStateManager;
  let formPersistence;
  let mockForm;
  
  beforeEach(() => {
    mockStateManager = {
      getState: jest.fn(() => ({
        app: { formData: {} }
      })),
      updateState: jest.fn()
    };
    
    formPersistence = new PluginNameFormPersistence(mockStateManager);
    
    // Create mock form
    document.body.innerHTML = `
      <form id="test-form">
        <input name="name" value="" />
        <input name="email" type="email" value="" />
        <input name="password" type="password" value="" />
      </form>
    `;
    
    mockForm = document.getElementById('test-form');
  });
  
  afterEach(() => {
    document.body.innerHTML = '';
  });
  
  test('registers form and excludes password fields', () => {
    formPersistence.registerForm('test-form', {
      excludeFields: ['password']
    });
    
    // Simulate input
    const nameField = mockForm.querySelector('[name="name"]');
    nameField.value = 'John Doe';
    nameField.dispatchEvent(new Event('input'));
    
    const passwordField = mockForm.querySelector('[name="password"]');
    passwordField.value = 'secret123';
    passwordField.dispatchEvent(new Event('input'));
    
    // Should update state with name but not password
    expect(mockStateManager.updateState).toHaveBeenCalledWith(
      expect.objectContaining({
        app: {
          formData: {
            'test-form': {
              name: 'John Doe',
              email: ''
              // password should not be included
            }
          }
        }
      })
    );
  });
  
  test('restores form data from state', () => {
    mockStateManager.getState.mockReturnValue({
      app: {
        formData: {
          'test-form': {
            name: 'Jane Doe',
            email: 'jane@example.com'
          }
        }
      }
    });
    
    formPersistence.registerForm('test-form');
    
    expect(mockForm.querySelector('[name="name"]').value).toBe('Jane Doe');
    expect(mockForm.querySelector('[name="email"]').value).toBe('jane@example.com');
    expect(mockForm.querySelector('[name="password"]').value).toBe('');
  });
});
```

## Integration Testing

### WP-CLI Integration Tests
```bash
#!/bin/bash
# tests/integration/run-tests.sh

# Set up test environment
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress

# Test API endpoints
echo "Testing API endpoints..."
wp eval 'do_action("rest_api_init");'

# Test user preferences
echo "Testing user preferences..."
wp user create testuser test@example.com --role=subscriber
wp eval '
  $user_id = get_user_by("login", "testuser")->ID;
  wp_set_current_user($user_id);
  $prefs = new Plugin_Name_User_Preferences();
  $result = $prefs->update_user_preferences(["theme" => "dark"]);
  echo $result ? "SUCCESS" : "FAILED";
'

# Test database schema
echo "Testing database schema..."
wp eval '
  global $wpdb;
  $plugin_tables = $wpdb->get_results("SHOW TABLES LIKE \"{$wpdb->prefix}plugin_name_%\"");
  echo "Found " . count($plugin_tables) . " plugin tables\n";
'
```

### Frontend Integration Tests
```javascript
// tests/integration/frontend.test.js
import { JSDOM } from 'jsdom';

describe('Frontend Integration', () => {
  let dom;
  let window;
  let document;
  
  beforeEach(() => {
    dom = new JSDOM(`
      <!DOCTYPE html>
      <html>
        <body class="logged-in">
          <div id="plugin-name-app"></div>
          <form id="contact-form">
            <input name="name" />
            <input name="email" />
          </form>
        </body>
      </html>
    `);
    
    window = dom.window;
    document = window.document;
    global.window = window;
    global.document = document;
  });
  
  test('app initializes and detects logged-in user', () => {
    // Import and initialize app
    const { PluginNameApp } = require('../../assets/js/app.js');
    
    const app = new PluginNameApp();
    
    expect(app.stateManager.getState().user.isLoggedIn).toBe(true);
  });
});
```

## Test Commands

### WP-CLI Test Commands
```bash
# Run PHP unit tests
./vendor/bin/phpunit

# Run specific test class
./vendor/bin/phpunit tests/test-user-preferences.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/

# Test with different PHP versions (if available)
php7.4 ./vendor/bin/phpunit
php8.0 ./vendor/bin/phpunit
```

### JavaScript Test Commands
```bash
# Run all JavaScript tests
npm test

# Run tests in watch mode
npm run test:watch

# Run tests with coverage
npm run test:coverage

# Run specific test file
npx jest tests/js/state-manager.test.js

# Run integration tests
npx jest tests/integration/
```

### LocalWP Database Tests
```bash
# Open LocalWP site shell, then:

# Test plugin activation/deactivation
wp plugin activate plugin-name
wp plugin deactivate plugin-name

# Test with different WordPress versions
wp core update --version=6.0
wp plugin activate plugin-name

# Test multisite
wp core multisite-install --title="Test Network"
wp plugin activate plugin-name --network
```

## WordPress Testing Environment Setup (Detailed)

### Environment Configuration and Setup

#### Current Bootstrap Analysis
The project's `tests/bootstrap.php` implements:
- WordPress test suite path detection: `/tmp/wordpress-tests-lib`
- Plugin auto-loading via `_manually_load_plugin()`
- Conditional Brain Monkey integration
- Environment-specific constants and configurations

#### Environment Variables
```bash
# Required environment variables
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress
export WP_TESTS_CONFIG_FILE_PATH=/tmp/wp-tests-config.php

# Database configuration
export WP_TESTS_DB_NAME=wordpress_test
export WP_TESTS_DB_USER=root
export WP_TESTS_DB_PASSWORD=password
export WP_TESTS_DB_HOST=localhost
```

#### Multi-Environment Setup Strategies

**Local Development Environment**
```bash
# Method 1: Using Local by Flywheel / LocalWP
# Access LocalWP site shell and run:
cd /app/public/wp-content/plugins/rwp-creator-suite
composer install
./vendor/bin/phpunit

# Method 2: Using WP-ENV (WordPress official)
npm install -g @wordpress/env
wp-env start
wp-env run tests-cli "cd wp-content/plugins/rwp-creator-suite && composer test"

# Method 3: Custom local setup
mysql -u root -p -e "CREATE DATABASE rwp_creator_suite_test;"
bash bin/install-wp-tests.sh rwp_creator_suite_test root password localhost latest
```

**CI/CD Environment Configuration**
```yaml
# GitHub Actions example
services:
  mysql:
    image: mysql:5.7
    env:
      MYSQL_DATABASE: wordpress_test
      MYSQL_ROOT_PASSWORD: password

strategy:
  matrix:
    php-version: [7.4, 8.0, 8.1, 8.2]
    wordpress-version: [5.9, 6.0, 6.1, 6.2, latest]

steps:
- name: Install WordPress Test Suite
  run: bash bin/install-wp-tests.sh wordpress_test root password 127.0.0.1:3306 ${{ matrix.wordpress-version }}
```

#### WordPress Version Compatibility Testing
```bash
# Test against multiple WordPress versions
for version in 5.9 6.0 6.1 6.2 latest; do
    echo "Testing WordPress $version"
    bash bin/install-wp-tests.sh wordpress_test_$version root password localhost $version
    WP_TESTS_DIR=/tmp/wordpress-tests-lib-$version ./vendor/bin/phpunit
done
```

#### Database Setup and Cleanup
```php
// Enhanced test base class with proper cleanup
abstract class RWP_Creator_Suite_Test_Case extends \PHPUnit\Framework\TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        $this->clean_database_state();
        \Brain\Monkey\setUp();
        $this->reset_wordpress_globals();
    }
    
    protected function tearDown(): void {
        $this->clean_database_state();
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }
    
    private function clean_database_state() {
        global $wpdb;
        
        // Clean plugin-specific tables
        $wpdb->query( "TRUNCATE TABLE IF EXISTS {$wpdb->prefix}rwp_creator_suite_cache" );
        
        // Clean user meta and options
        $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'rwp_creator_suite_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'rwp_creator_suite_%'" );
        
        // Clear WordPress caches
        wp_cache_flush();
    }
}
```

#### WordPress Function Mocking Patterns
```php
// Current project pattern (avoid direct function definitions)
// BAD - Don't do this:
if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() {
        return 1;
    }
}

// GOOD - Use Brain Monkey instead:
\Brain\Monkey\Functions\when( 'get_current_user_id' )->justReturn( 1 );
\Brain\Monkey\Functions\when( 'is_user_logged_in' )->justReturn( true );
\Brain\Monkey\Functions\when( 'current_user_can' )
    ->with( 'manage_options' )
    ->justReturn( true );
```

#### Plugin Dependency Management
```php
// Handle plugin dependencies in tests
class RWP_Creator_Suite_Test_Dependencies {
    
    public static function load_required_plugins() {
        $required_plugins = array(
            'advanced-custom-fields/acf.php',
            'woocommerce/woocommerce.php',
        );
        
        foreach ( $required_plugins as $plugin ) {
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin;
            if ( file_exists( $plugin_path ) ) {
                require_once $plugin_path;
            } else {
                self::create_plugin_mock( $plugin );
            }
        }
    }
    
    private static function create_plugin_mock( $plugin ) {
        // Create basic mocks for required plugin functions
        if ( strpos( $plugin, 'advanced-custom-fields' ) !== false ) {
            \Brain\Monkey\Functions\when( 'get_field' )->justReturn( 'mocked_value' );
            \Brain\Monkey\Functions\when( 'update_field' )->justReturn( true );
        }
    }
}
```

#### Troubleshooting Common Setup Issues

**Environment Validation and Auto-Fix**
```php
class RWP_Creator_Suite_Test_Environment_Validator {
    
    public static function validate_and_fix() {
        // Check WordPress test suite
        if ( ! file_exists( '/tmp/wordpress-tests-lib/includes/functions.php' ) ) {
            echo "❌ WordPress test suite not found\n";
            echo "💡 Run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
            exit( 1 );
        }
        
        // Check database connection
        if ( ! self::can_connect_to_database() ) {
            echo "❌ Cannot connect to test database\n";
            echo "💡 Check database credentials and ensure MySQL is running\n";
            exit( 1 );
        }
        
        // Check required PHP extensions
        $required_extensions = array( 'mysqli', 'zip', 'gd' );
        foreach ( $required_extensions as $ext ) {
            if ( ! extension_loaded( $ext ) ) {
                echo "❌ Required PHP extension '{$ext}' not loaded\n";
                echo "💡 Install PHP {$ext} extension\n";
                exit( 1 );
            }
        }
    }
    
    private static function can_connect_to_database() {
        $host = getenv( 'WP_TESTS_DB_HOST' ) ?: 'localhost';
        $user = getenv( 'WP_TESTS_DB_USER' ) ?: 'root';
        $password = getenv( 'WP_TESTS_DB_PASSWORD' ) ?: '';
        
        $connection = @mysqli_connect( $host, $user, $password );
        if ( $connection ) {
            mysqli_close( $connection );
            return true;
        }
        return false;
    }
}
```

## Critical Testing Rules

1. **Mock WordPress Functions** - Use Brain Monkey for unit tests
2. **Test Both PHP and JavaScript** - Don't forget client-side logic
3. **Integration Tests** - Test API endpoints with real WordPress
4. **localStorage Mocking** - Always mock browser storage in tests
5. **User State Testing** - Test logged-in vs guest scenarios
6. **Form Persistence** - Test form data saving/restoration
7. **Error Handling** - Test failure scenarios
8. **Nest All Admin Pages** - All admin option pages MUST use `add_submenu_page()` with parent slug `'rwp-creator-tools'` - never create additional top-level menus
9. **Performance Testing** - Test with large datasets