# WordPress Build & Deployment (AI-Optimized)

## Build System Architecture

### Package.json Configuration
```json
{
  "name": "plugin-name",
  "version": "1.0.0",
  "scripts": {
    "dev": "wp-scripts start --webpack-src-dir=assets/js --output-path=assets/dist",
    "build": "wp-scripts build --webpack-src-dir=assets/js --output-path=assets/dist",
    "build:production": "NODE_ENV=production npm run build",
    "watch": "wp-scripts start",
    "lint:js": "wp-scripts lint-js assets/js/",
    "lint:css": "wp-scripts lint-style assets/css/",
    "format": "wp-scripts format",
    "test": "jest",
    "package": "npm run build:production && npm run create-zip"
  },
  "devDependencies": {
    "@wordpress/scripts": "^27.0.0",
    "archiver": "^5.3.0"
  }
}
```

### Module-Based Asset Organization
```
assets/
├── js/
│   ├── modules/
│   │   ├── state-manager/
│   │   │   ├── index.js
│   │   │   └── state-manager.js
│   │   ├── api-client/
│   │   │   ├── index.js
│   │   │   └── api-client.js
│   │   ├── user-management/
│   │   │   ├── index.js
│   │   │   ├── user-preferences.js
│   │   │   └── user-transition.js
│   │   └── form-persistence/
│   │       ├── index.js
│   │       └── form-persistence.js
│   ├── admin.js
│   ├── frontend.js
│   └── blocks.js
├── css/
│   ├── admin.scss
│   ├── frontend.scss
│   └── blocks.scss
└── dist/ (generated)
    ├── admin.js
    ├── frontend.js
    ├── admin.css
    └── frontend.css
```

### Webpack Configuration (wp-scripts based)
```javascript
// webpack.config.js (optional customization)
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        admin: path.resolve(__dirname, 'assets/js/admin.js'),
        frontend: path.resolve(__dirname, 'assets/js/frontend.js'),
        blocks: path.resolve(__dirname, 'assets/js/blocks.js'),
    },
    resolve: {
        ...defaultConfig.resolve,
        alias: {
            '@modules': path.resolve(__dirname, 'assets/js/modules'),
            '@utils': path.resolve(__dirname, 'assets/js/utils'),
        }
    },
    optimization: {
        ...defaultConfig.optimization,
        splitChunks: {
            cacheGroups: {
                vendor: {
                    test: /[\\/]node_modules[\\/]/,
                    name: 'vendor',
                    chunks: 'all',
                },
                common: {
                    name: 'common',
                    minChunks: 2,
                    chunks: 'all',
                    enforce: true
                }
            }
        }
    }
};
```

## Module System Implementation

### Module Entry Points
```javascript
// assets/js/admin.js
import StateManager from '@modules/state-manager';
import APIClient from '@modules/api-client';
import UserManagement from '@modules/user-management';

class PluginNameAdmin {
    constructor() {
        this.stateManager = new StateManager();
        this.apiClient = new APIClient();
        this.userManagement = new UserManagement(this.stateManager, this.apiClient);
        
        this.init();
    }
    
    init() {
        // Initialize admin-specific functionality
        this.setupAdminInterface();
        this.registerEventListeners();
    }
    
    setupAdminInterface() {
        // Admin-specific setup
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new PluginNameAdmin();
});
```

```javascript
// assets/js/frontend.js
import StateManager from '@modules/state-manager';
import APIClient from '@modules/api-client';
import FormPersistence from '@modules/form-persistence';

class PluginNameFrontend {
    constructor() {
        this.stateManager = new StateManager();
        this.apiClient = new APIClient();
        this.formPersistence = new FormPersistence(this.stateManager);
        
        this.init();
    }
    
    init() {
        // Initialize frontend-specific functionality
        this.setupPublicInterface();
        this.initializeForms();
    }
    
    initializeForms() {
        // Register forms that need persistence
        const forms = document.querySelectorAll('.plugin-name-form');
        forms.forEach(form => {
            this.formPersistence.registerForm(form.id, {
                excludeFields: ['password', 'confirm-password'],
                autoSave: true
            });
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new PluginNameFrontend();
});
```

### Lazy Loading Modules
```javascript
// assets/js/utils/module-loader.js
class ModuleLoader {
    constructor() {
        this.loadedModules = new Map();
        this.loadingPromises = new Map();
    }
    
    async loadModule(moduleName) {
        // Return cached module if already loaded
        if (this.loadedModules.has(moduleName)) {
            return this.loadedModules.get(moduleName);
        }
        
        // Return existing promise if already loading
        if (this.loadingPromises.has(moduleName)) {
            return this.loadingPromises.get(moduleName);
        }
        
        // Load module dynamically
        const loadingPromise = this.dynamicImport(moduleName);
        this.loadingPromises.set(moduleName, loadingPromise);
        
        try {
            const module = await loadingPromise;
            this.loadedModules.set(moduleName, module);
            this.loadingPromises.delete(moduleName);
            
            return module;
        } catch (error) {
            this.loadingPromises.delete(moduleName);
            throw error;
        }
    }
    
    async dynamicImport(moduleName) {
        const moduleMap = {
            'chart-renderer': () => import('@modules/chart-renderer'),
            'file-uploader': () => import('@modules/file-uploader'),
            'advanced-filters': () => import('@modules/advanced-filters'),
        };
        
        if (moduleMap[moduleName]) {
            const module = await moduleMap[moduleName]();
            return module.default || module;
        }
        
        throw new Error(`Module ${moduleName} not found`);
    }
}

export default ModuleLoader;
```

## PHP Asset Management

### Enqueue Strategy
```php
class Plugin_Name_Assets {
    private $version;
    private $is_debug;
    
    public function __construct() {
        $this->version = defined( 'PLUGIN_NAME_VERSION' ) ? PLUGIN_NAME_VERSION : '1.0.0';
        $this->is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
    }
    
    public function enqueue_admin_assets( $hook_suffix ) {
        // Only load on plugin pages
        if ( ! $this->is_plugin_page( $hook_suffix ) ) {
            return;
        }
        
        $this->enqueue_script( 'admin', array( 'wp-api-fetch', 'wp-element' ) );
        $this->enqueue_style( 'admin' );
        
        // Localize data for admin
        wp_localize_script( 'plugin-name-admin', 'pluginNameAdmin', array(
            'apiUrl'      => rest_url( 'plugin-name/v1/' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'userId'      => get_current_user_id(),
            'userCan'     => array(
                'manage_options' => current_user_can( 'manage_options' ),
                'edit_posts'     => current_user_can( 'edit_posts' ),
            ),
            'settings'    => $this->get_plugin_settings(),
        ) );
    }
    
    public function enqueue_frontend_assets() {
        // Load frontend assets conditionally
        if ( ! $this->should_load_frontend_assets() ) {
            return;
        }
        
        $this->enqueue_script( 'frontend', array( 'wp-api-fetch' ) );
        $this->enqueue_style( 'frontend' );
        
        // Localize data for frontend
        wp_localize_script( 'plugin-name-frontend', 'pluginNameFrontend', array(
            'apiUrl'    => rest_url( 'plugin-name/v1/' ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'userId'    => get_current_user_id(),
            'isLoggedIn' => is_user_logged_in(),
            'guestData' => $this->get_guest_data(),
        ) );
    }
    
    private function enqueue_script( $handle, $dependencies = array() ) {
        $script_path = "assets/dist/{$handle}.js";
        $script_url = plugin_dir_url( PLUGIN_NAME_FILE ) . $script_path;
        $script_file = plugin_dir_path( PLUGIN_NAME_FILE ) . $script_path;
        
        // Use file modification time for cache busting in development
        $version = $this->is_debug && file_exists( $script_file ) 
            ? filemtime( $script_file ) 
            : $this->version;
        
        wp_enqueue_script(
            "plugin-name-{$handle}",
            $script_url,
            $dependencies,
            $version,
            true
        );
    }
    
    private function enqueue_style( $handle ) {
        $style_path = "assets/dist/{$handle}.css";
        $style_url = plugin_dir_url( PLUGIN_NAME_FILE ) . $style_path;
        $style_file = plugin_dir_path( PLUGIN_NAME_FILE ) . $style_path;
        
        $version = $this->is_debug && file_exists( $style_file ) 
            ? filemtime( $style_file ) 
            : $this->version;
        
        wp_enqueue_style(
            "plugin-name-{$handle}",
            $style_url,
            array(),
            $version
        );
    }
    
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
        
        // Check for plugin-specific pages
        if ( is_page_template( 'plugin-name-template.php' ) ) {
            return true;
        }
        
        return false;
    }
}
```

## Cache Busting & Versioning

### Asset Version Management
```php
class Plugin_Name_Asset_Versioning {
    private $manifest_file;
    private $manifest_data;
    
    public function __construct() {
        $this->manifest_file = plugin_dir_path( PLUGIN_NAME_FILE ) . 'assets/dist/manifest.json';
        $this->load_manifest();
    }
    
    private function load_manifest() {
        if ( file_exists( $this->manifest_file ) ) {
            $this->manifest_data = json_decode( 
                file_get_contents( $this->manifest_file ), 
                true 
            );
        } else {
            $this->manifest_data = array();
        }
    }
    
    public function get_asset_url( $asset_name ) {
        $base_url = plugin_dir_url( PLUGIN_NAME_FILE ) . 'assets/dist/';
        
        // Check for hashed filename in manifest
        if ( isset( $this->manifest_data[ $asset_name ] ) ) {
            return $base_url . $this->manifest_data[ $asset_name ];
        }
        
        // Fallback to standard filename
        return $base_url . $asset_name;
    }
    
    public function get_asset_version( $asset_name ) {
        // Use content hash if available
        if ( isset( $this->manifest_data[ $asset_name . '.hash' ] ) ) {
            return $this->manifest_data[ $asset_name . '.hash' ];
        }
        
        // Use file modification time
        $asset_path = plugin_dir_path( PLUGIN_NAME_FILE ) . 'assets/dist/' . $asset_name;
        if ( file_exists( $asset_path ) ) {
            return filemtime( $asset_path );
        }
        
        return PLUGIN_NAME_VERSION;
    }
}
```

### Build Scripts
```javascript
// scripts/build.js
const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

class PluginBuilder {
    constructor() {
        this.distDir = path.resolve(__dirname, '../assets/dist');
        this.buildDir = path.resolve(__dirname, '../build');
        this.packageDir = path.resolve(__dirname, '../package');
    }
    
    async build() {
        console.log('Building plugin assets...');
        
        // Generate asset manifest
        await this.generateManifest();
        
        // Optimize assets for production
        await this.optimizeAssets();
        
        // Create distribution package
        if (process.env.NODE_ENV === 'production') {
            await this.createPackage();
        }
        
        console.log('Build completed successfully!');
    }
    
    async generateManifest() {
        const manifest = {};
        
        if (fs.existsSync(this.distDir)) {
            const files = fs.readdirSync(this.distDir);
            
            files.forEach(file => {
                const filePath = path.join(this.distDir, file);
                const stats = fs.statSync(filePath);
                
                if (stats.isFile()) {
                    // Create content hash
                    const content = fs.readFileSync(filePath);
                    const hash = require('crypto')
                        .createHash('md5')
                        .update(content)
                        .digest('hex')
                        .substring(0, 8);
                    
                    manifest[file] = file;
                    manifest[file + '.hash'] = hash;
                    manifest[file + '.size'] = stats.size;
                }
            });
        }
        
        // Write manifest file
        const manifestPath = path.join(this.distDir, 'manifest.json');
        fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
        
        console.log('Asset manifest generated');
    }
    
    async optimizeAssets() {
        // Additional optimizations for production
        if (process.env.NODE_ENV === 'production') {
            // Could add additional optimizations here
            console.log('Production optimizations applied');
        }
    }
    
    async createPackage() {
        if (!fs.existsSync(this.packageDir)) {
            fs.mkdirSync(this.packageDir, { recursive: true });
        }
        
        const packageJson = require('../package.json');
        const zipFileName = `${packageJson.name}-${packageJson.version}.zip`;
        const zipPath = path.join(this.packageDir, zipFileName);
        
        const output = fs.createWriteStream(zipPath);
        const archive = archiver('zip', { zlib: { level: 9 } });
        
        output.on('close', () => {
            console.log(`Package created: ${zipFileName} (${archive.pointer()} bytes)`);
        });
        
        archive.on('error', (err) => {
            throw err;
        });
        
        archive.pipe(output);
        
        // Add plugin files (exclude development files)
        const excludePatterns = [
            'node_modules/**',
            'tests/**',
            'src/**',
            '.git/**',
            '.gitignore',
            'package*.json',
            'webpack.config.js',
            'jest.config.js',
            '.eslintrc.js',
            'phpunit.xml',
            'composer.json',
            'composer.lock',
            'assets/js/**', // Source files
            'assets/css/**', // Source files
            '**/*.map', // Source maps
        ];
        
        archive.glob('**/*', {
            ignore: excludePatterns,
            cwd: path.resolve(__dirname, '..')
        });
        
        await archive.finalize();
    }
}

// Run build
if (require.main === module) {
    new PluginBuilder().build().catch(console.error);
}

module.exports = PluginBuilder;
```

### Development Scripts
```javascript
// scripts/dev-server.js
const chokidar = require('chokidar');
const { spawn } = require('child_process');

class DevServer {
    constructor() {
        this.webpackProcess = null;
        this.isBuilding = false;
    }
    
    start() {
        console.log('Starting development server...');
        
        // Start webpack in watch mode
        this.startWebpack();
        
        // Watch for PHP file changes (for development workflow)
        this.watchPHPFiles();
        
        process.on('SIGINT', () => {
            this.stop();
        });
    }
    
    startWebpack() {
        this.webpackProcess = spawn('npm', ['run', 'watch'], {
            stdio: 'inherit',
            shell: true
        });
        
        this.webpackProcess.on('exit', (code) => {
            if (code !== 0) {
                console.error('Webpack process exited with code', code);
            }
        });
    }
    
    watchPHPFiles() {
        const phpWatcher = chokidar.watch(['includes/**/*.php', '*.php'], {
            ignored: /node_modules/,
            persistent: true
        });
        
        phpWatcher.on('change', (path) => {
            console.log(`PHP file changed: ${path}`);
            // Could trigger PHP linting or other checks here
        });
    }
    
    stop() {
        console.log('Stopping development server...');
        
        if (this.webpackProcess) {
            this.webpackProcess.kill('SIGTERM');
        }
        
        process.exit(0);
    }
}

if (require.main === module) {
    new DevServer().start();
}
```

## Deployment Strategies

### Environment Configuration
```php
class Plugin_Name_Environment {
    const DEVELOPMENT = 'development';
    const STAGING = 'staging';
    const PRODUCTION = 'production';
    
    private static $environment = null;
    
    public static function get_environment() {
        if ( self::$environment === null ) {
            self::$environment = self::detect_environment();
        }
        
        return self::$environment;
    }
    
    private static function detect_environment() {
        // Check for explicit environment constant
        if ( defined( 'PLUGIN_NAME_ENVIRONMENT' ) ) {
            return PLUGIN_NAME_ENVIRONMENT;
        }
        
        // Check WordPress environment constants
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
            return WP_ENVIRONMENT_TYPE;
        }
        
        // Check for development indicators
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            return self::DEVELOPMENT;
        }
        
        // Check domain patterns
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if ( strpos( $host, 'localhost' ) !== false || 
             strpos( $host, '.local' ) !== false ||
             strpos( $host, '.dev' ) !== false ) {
            return self::DEVELOPMENT;
        }
        
        if ( strpos( $host, 'staging' ) !== false ||
             strpos( $host, '.stage' ) !== false ) {
            return self::STAGING;
        }
        
        return self::PRODUCTION;
    }
    
    public static function is_development() {
        return self::get_environment() === self::DEVELOPMENT;
    }
    
    public static function is_staging() {
        return self::get_environment() === self::STAGING;
    }
    
    public static function is_production() {
        return self::get_environment() === self::PRODUCTION;
    }
    
    public static function get_config() {
        $environment = self::get_environment();
        
        $configs = array(
            self::DEVELOPMENT => array(
                'debug'            => true,
                'cache_assets'     => false,
                'minify_assets'    => false,
                'error_reporting'  => true,
                'api_cache_time'   => 0,
            ),
            self::STAGING => array(
                'debug'            => false,
                'cache_assets'     => true,
                'minify_assets'    => true,
                'error_reporting'  => true,
                'api_cache_time'   => 300, // 5 minutes
            ),
            self::PRODUCTION => array(
                'debug'            => false,
                'cache_assets'     => true,
                'minify_assets'    => true,
                'error_reporting'  => false,
                'api_cache_time'   => 3600, // 1 hour
            ),
        );
        
        return $configs[ $environment ] ?? $configs[ self::PRODUCTION ];
    }
}
```

### Database Migration System
```php
class Plugin_Name_Migrations {
    private $version_option = 'plugin_name_db_version';
    private $migrations_dir;
    
    public function __construct() {
        $this->migrations_dir = plugin_dir_path( PLUGIN_NAME_FILE ) . 'migrations/';
    }
    
    public function run_migrations() {
        $current_version = get_option( $this->version_option, '0.0.0' );
        $target_version = PLUGIN_NAME_VERSION;
        
        if ( version_compare( $current_version, $target_version, '<' ) ) {
            $this->execute_migrations( $current_version, $target_version );
            update_option( $this->version_option, $target_version );
        }
    }
    
    private function execute_migrations( $from_version, $to_version ) {
        $migration_files = $this->get_migration_files();
        
        foreach ( $migration_files as $file ) {
            $migration_version = $this->extract_version_from_filename( $file );
            
            if ( version_compare( $migration_version, $from_version, '>' ) &&
                 version_compare( $migration_version, $to_version, '<=' ) ) {
                
                $this->execute_migration_file( $file );
            }
        }
    }
    
    private function get_migration_files() {
        if ( ! is_dir( $this->migrations_dir ) ) {
            return array();
        }
        
        $files = scandir( $this->migrations_dir );
        $migration_files = array();
        
        foreach ( $files as $file ) {
            if ( preg_match( '/^(\d+\.\d+\.\d+)_.*\.php$/', $file ) ) {
                $migration_files[] = $file;
            }
        }
        
        // Sort by version
        usort( $migration_files, function( $a, $b ) {
            $version_a = $this->extract_version_from_filename( $a );
            $version_b = $this->extract_version_from_filename( $b );
            return version_compare( $version_a, $version_b );
        } );
        
        return $migration_files;
    }
    
    private function extract_version_from_filename( $filename ) {
        preg_match( '/^(\d+\.\d+\.\d+)_/', $filename, $matches );
        return $matches[1] ?? '0.0.0';
    }
    
    private function execute_migration_file( $filename ) {
        $filepath = $this->migrations_dir . $filename;
        
        if ( ! file_exists( $filepath ) ) {
            return;
        }
        
        // Include the migration file
        include_once $filepath;
        
        // Extract migration class name from filename
        $class_name = $this->get_migration_class_name( $filename );
        
        if ( class_exists( $class_name ) ) {
            $migration = new $class_name();
            
            if ( method_exists( $migration, 'up' ) ) {
                $migration->up();
            }
        }
    }
    
    private function get_migration_class_name( $filename ) {
        // Convert filename to class name
        // e.g., "1.1.0_add_user_preferences_table.php" -> "Plugin_Name_Migration_1_1_0_Add_User_Preferences_Table"
        $base_name = basename( $filename, '.php' );
        $class_parts = explode( '_', $base_name );
        
        // Convert version dots to underscores
        $class_parts[0] = str_replace( '.', '_', $class_parts[0] );
        
        // Capitalize each part
        $class_parts = array_map( 'ucfirst', $class_parts );
        
        return 'Plugin_Name_Migration_' . implode( '_', $class_parts );
    }
}
```

### Example Migration File
```php
<?php
// migrations/1.1.0_add_user_preferences_table.php

class Plugin_Name_Migration_1_1_0_Add_User_Preferences_Table {
    
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'plugin_name_user_preferences';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            preference_key varchar(255) NOT NULL,
            preference_value longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_preference (user_id, preference_key),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        
        // Add any additional setup
        $this->seed_default_preferences();
    }
    
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'plugin_name_user_preferences';
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
    }
    
    private function seed_default_preferences() {
        // Add default preferences if needed
    }
}
```

## GitHub Actions Deployment

### Automated Testing & Deployment
```yaml
# .github/workflows/deploy.yml
name: Deploy Plugin

on:
  push:
    tags:
      - 'v*'
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: [7.4, 8.0, 8.1]
        wordpress-version: [5.8, 6.0, latest]
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mysql, zip
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
    
    - name: Install PHP dependencies
      run: composer install --no-dev --optimize-autoloader
    
    - name: Install JS dependencies
      run: npm ci
    
    - name: Run PHP tests
      run: ./vendor/bin/phpunit
    
    - name: Run JS tests
      run: npm test
    
    - name: Build assets
      run: npm run build:production

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: startsWith(github.ref, 'refs/tags/v')
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
    
    - name: Install dependencies
      run: npm ci
    
    - name: Build and package
      run: |
        npm run build:production
        npm run package
    
    - name: Create Release
      uses: actions/create-release@v1
      env:
        GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
      with:
        tag_name: ${{ github.ref }}
        release_name: Release ${{ github.ref }}
        draft: false
        prerelease: false
    
    - name: Upload Release Asset
      uses: actions/upload-release-asset@v1
      env:
        GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
      with:
        upload_url: ${{ steps.create_release.outputs.upload_url }}
        asset_path: ./package/*.zip
        asset_name: plugin-name-${{ github.ref_name }}.zip
        asset_content_type: application/zip
```

## Performance Optimization

### Asset Optimization
```javascript
// webpack.production.js
const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');

module.exports = {
    mode: 'production',
    optimization: {
        minimize: true,
        minimizer: [
            new TerserPlugin({
                terserOptions: {
                    compress: {
                        drop_console: true,
                        drop_debugger: true,
                    },
                },
                extractComments: false,
            }),
            new CssMinimizerPlugin(),
        ],
        splitChunks: {
            chunks: 'all',
            cacheGroups: {
                vendor: {
                    test: /[\\/]node_modules[\\/]/,
                    name: 'vendor',
                    chunks: 'all',
                    priority: 2,
                },
                common: {
                    name: 'common',
                    minChunks: 2,
                    chunks: 'all',
                    priority: 1,
                },
            },
        },
    },
    performance: {
        maxAssetSize: 250000,
        maxEntrypointSize: 250000,
        hints: 'warning',
    },
};
```

## Version Management Process

### Version Update Workflow
When releasing a new version, update these locations in order:

1. **package.json**: Update version number
   ```json
   {
     "name": "rwp-creator-suite",
     "version": "1.2.0"
   }
   ```

2. **Main Plugin File Header**: Update version in plugin header
   ```php
   /**
    * Plugin Name: RWP Creator Suite
    * Version: 1.2.0
    * Description: WordPress plugin for content creators
    */
   ```

3. **readme.txt**: Update stable tag
   ```
   === RWP Creator Suite ===
   Stable tag: 1.2.0
   Tested up to: 6.4
   Requires at least: 5.8
   ```

### Build Process Commands
```bash
# Development build
npm run build

# Production build with optimizations
npm run build:production

# Create deployment package
npm run package

# Complete release workflow
npm version patch  # Updates package.json
npm run build:production
npm run package
```

### Deployment Package Creation
```bash
# Manual package creation
mkdir -p package
zip -r package/rwp-creator-suite-1.2.0.zip . \
  -x "node_modules/*" \
     "tests/*" \
     "src/*" \
     ".git/*" \
     ".gitignore" \
     "package*.json" \
     "webpack.config.js" \
     "jest.config.js" \
     ".eslintrc.js" \
     "phpunit.xml" \
     "composer.json" \
     "composer.lock" \
     "assets/js/*" \
     "assets/css/*" \
     "**/*.map"
```

### Automated Version Bumping
```javascript
// scripts/version-bump.js
const fs = require('fs');
const path = require('path');

function updatePluginVersion(version) {
    // Update main plugin file
    const pluginFile = path.resolve(__dirname, '../rwp-creator-suite.php');
    let content = fs.readFileSync(pluginFile, 'utf8');
    content = content.replace(
        /Version:\s*[\d.]+/,
        `Version: ${version}`
    );
    fs.writeFileSync(pluginFile, content);
    
    // Update readme.txt
    const readmeFile = path.resolve(__dirname, '../readme.txt');
    let readmeContent = fs.readFileSync(readmeFile, 'utf8');
    readmeContent = readmeContent.replace(
        /Stable tag:\s*[\d.]+/,
        `Stable tag: ${version}`
    );
    fs.writeFileSync(readmeFile, readmeContent);
    
    console.log(`Updated version to ${version}`);
}

// Run with: node scripts/version-bump.js 1.2.0
const version = process.argv[2];
if (version) {
    updatePluginVersion(version);
} else {
    console.error('Please provide a version number');
}
```

### Release Checklist
- [ ] Update version in package.json
- [ ] Update version in main plugin file header
- [ ] Update stable tag in readme.txt
- [ ] Run full test suite (`npm run test:coverage`)
- [ ] Build production assets (`npm run build:production`)
- [ ] Test in clean WordPress installation
- [ ] Create git tag for release
- [ ] Generate deployment package
- [ ] Update changelog/release notes

### Critical Deployment Rules

1. **Always Build for Production** - Use `NODE_ENV=production`
2. **Version Everything** - Assets, database schemas, API versions
3. **Test Multiple PHP/WP Versions** - Ensure compatibility
4. **Environment-Specific Configs** - Different settings per environment
5. **Database Migrations** - Handle schema changes gracefully
6. **Asset Optimization** - Minify, compress, cache-bust
7. **Automated Testing** - Never deploy without tests passing
8. **Nest All Admin Pages** - All admin option pages MUST use `add_submenu_page()` with parent slug `'rwp-creator-tools'` - never create additional top-level menus
9. **Rollback Strategy** - Always have a way to revert changes
10. **Version Consistency** - Update all version references consistently
11. **Clean Packages** - Exclude development files from deployment packages

## LocalWP Development Environment Issues

### NODE_ENV Production Override Issue

**Problem**: LocalWP's site shell automatically sets `NODE_ENV=production`, which tells npm to skip installing devDependencies entirely, causing build failures for WordPress block development.

**Root Cause**: 
- LocalWP sets `NODE_ENV=production` by default in site shells
- This is a known issue in the LocalWP community  
- npm respects `NODE_ENV` and skips devDependencies when set to "production"
- WordPress block development requires devDependencies like `@wordpress/scripts`

**Solution**: Override the environment variable before installing:
```bash
export NODE_ENV=development && npm install
```

**Key Findings**:
- LocalWP sets `NODE_ENV=production` by default in site shells
- npm skips devDependencies when `NODE_ENV=production` 
- The fix is simple but not obvious - requires manually setting `NODE_ENV=development`
- After fix: 1500+ packages install successfully vs 0 devDependencies before
- `wp-scripts build` works properly after environment override
- All WordPress blocks compile correctly with proper dependencies

**Best Practices for LocalWP Development**:
1. Always check `NODE_ENV` value when setting up a new LocalWP site: `echo $NODE_ENV`
2. Override to development before any npm operations: `export NODE_ENV=development`
3. Add to shell profile for permanent fix: `echo "export NODE_ENV=development" >> ~/.bashrc`
4. Verify devDependencies installed: `npm list --depth=0 --dev`

**Alternative Solutions**:
```bash
# Option 1: One-time override
NODE_ENV=development npm install

# Option 2: Permanent shell override
echo "export NODE_ENV=development" >> ~/.zshrc && source ~/.zshrc

# Option 3: Package.json script override
"scripts": {
  "install:dev": "NODE_ENV=development npm install"
}
```

## Monitoring & Health Checks

### Plugin Health Monitor
```php
class Plugin_Name_Health_Monitor {
    
    public function check_plugin_health() {
        $health_checks = array(
            'database' => $this->check_database_health(),
            'assets' => $this->check_asset_health(),
            'api' => $this->check_api_health(),
            'permissions' => $this->check_permissions_health(),
        );
        
        return array(
            'status' => $this->calculate_overall_status( $health_checks ),
            'checks' => $health_checks,
            'timestamp' => time(),
        );
    }
    
    private function check_database_health() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'plugin_name_data',
        );
        
        foreach ( $required_tables as $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ) );
            
            if ( ! $exists ) {
                return array(
                    'status' => 'error',
                    'message' => "Missing table: {$table}",
                );
            }
        }
        
        return array( 'status' => 'ok' );
    }
    
    private function check_asset_health() {
        $required_assets = array(
            'assets/dist/frontend.js',
            'assets/dist/frontend.css',
            'assets/dist/admin.js',
            'assets/dist/admin.css',
        );
        
        foreach ( $required_assets as $asset ) {
            $asset_path = plugin_dir_path( PLUGIN_NAME_FILE ) . $asset;
            
            if ( ! file_exists( $asset_path ) ) {
                return array(
                    'status' => 'error',
                    'message' => "Missing asset: {$asset}",
                );
            }
        }
        
        return array( 'status' => 'ok' );
    }
}