# WordPress State Management (AI-Optimized)

## Core Principles
- **localStorage primary** for all temporary state
- **Database only** for logged-in user preferences
- **Seamless transitions** from guest to authenticated user
- **State persistence** across page reloads and sessions
- **Graceful degradation** when localStorage unavailable

## Fallback Strategy
- **Warning notifications** if localStorage unavailable (no server fallback)
- **Session-only state** as fallback mode
- **Memory storage** as last resort
- **User education** about browser storage requirements

## State Architecture

### State Manager Class with Fallbacks
```javascript
class PluginNameStateManager {
    constructor() {
        this.prefix = 'pluginName_';
        this.storageAvailable = this.checkStorageAvailability();
        this.memoryStorage = new Map();
        this.state = {
            user: {
                isLoggedIn: false,
                preferences: {},
                tempData: {}
            },
            app: {
                currentView: 'default',
                formData: {},
                filters: {},
                selections: []
            }
        };
        
        this.init();
    }
    
    checkStorageAvailability() {
        try {
            const test = '__storage_test__';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return true;
        } catch (e) {
            this.showStorageWarning();
            return false;
        }
    }
    
    showStorageWarning() {
        // Show user-friendly warning about localStorage
        const warning = document.createElement('div');
        warning.className = 'plugin-name-storage-warning';
        warning.innerHTML = `
            <div class="notice notice-warning">
                <p><strong>Plugin Name:</strong> Browser storage is disabled. Your preferences won't be saved between sessions. 
                <a href="#" onclick="this.parentElement.parentElement.remove()">Dismiss</a></p>
            </div>
        `;
        
        document.body.insertBefore(warning, document.body.firstChild);
        
        // Auto-dismiss after 10 seconds
        setTimeout(() => {
            if (warning.parentNode) {
                warning.parentNode.removeChild(warning);
            }
        }, 10000);
    }
    
    init() {
        // Load persisted state
        this.loadPersistedState();
        
        // Set up auto-save
        this.setupAutoSave();
        
        // Handle login state changes
        this.handleAuthStateChange();
    }
    
    loadPersistedState() {
        const persistedState = this.getFromStorage('appState');
        if (persistedState) {
            this.state = { ...this.state, ...persistedState };
        }
        
        // Check if user is logged in (WordPress sets body class)
        this.state.user.isLoggedIn = document.body.classList.contains('logged-in');
    }
    
    getFromStorage(key) {
        if (this.storageAvailable) {
            try {
                const stored = localStorage.getItem(this.prefix + key);
                return stored ? JSON.parse(stored) : null;
            } catch (e) {
                console.warn('Failed to read from localStorage:', e);
            }
        }
        
        // Fallback to memory storage
        return this.memoryStorage.get(key) || null;
    }
    
    setToStorage(key, data) {
        if (this.storageAvailable) {
            try {
                localStorage.setItem(this.prefix + key, JSON.stringify(data));
                return true;
            } catch (e) {
                console.warn('Failed to write to localStorage:', e);
                this.storageAvailable = false;
                this.showStorageWarning();
            }
        }
        
        // Fallback to memory storage (session-only)
        this.memoryStorage.set(key, data);
        return false; // Indicates data won't persist
    }
}
```

### Guest to User Transition
```javascript
class PluginNameUserTransition {
    constructor(stateManager, apiClient) {
        this.stateManager = stateManager;
        this.apiClient = apiClient;
    }
    
    async handleUserLogin() {
        const guestState = this.stateManager.getState();
        
        try {
            // Fetch user preferences from server
            const userPreferences = await this.apiClient.getUserPreferences();
            
            // Merge guest state with user preferences
            const mergedState = this.mergeGuestWithUser(guestState, userPreferences);
            
            // Update state
            this.stateManager.setState(mergedState);
            
            // Save merged preferences to server
            await this.apiClient.updateUserPreferences(mergedState.user.preferences);
            
            // Clean up guest-only data if needed
            this.cleanupGuestData();
            
        } catch (error) {
            console.warn('Failed to sync user data:', error);
            // Continue with guest state - no server fallback by design
        }
    }
    
    mergeGuestWithUser(guestState, userPreferences) {
        return {
            ...guestState,
            user: {
                ...guestState.user,
                isLoggedIn: true,
                preferences: {
                    // User preferences take precedence
                    ...guestState.user.preferences,
                    ...userPreferences
                }
            }
        };
    }
    
    cleanupGuestData() {
        // Remove temporary guest data that shouldn't persist
        const cleanState = { ...this.stateManager.getState() };
        delete cleanState.user.tempData;
        this.stateManager.setState(cleanState);
    }
}
```

## State Persistence Patterns

### Auto-Save Strategy with Fallbacks
```javascript
class PluginNamePersistence {
    constructor(stateManager) {
        this.stateManager = stateManager;
        this.saveTimeout = null;
        this.saveDelay = 1000; // 1 second debounce
        this.persistenceMode = 'localStorage'; // 'localStorage', 'memory', or 'disabled'
    }
    
    setupAutoSave() {
        // Debounced save to localStorage
        this.stateManager.on('stateChange', () => {
            this.debouncedSave();
        });
        
        // Save before page unload (if possible)
        window.addEventListener('beforeunload', () => {
            this.saveImmediately();
        });
        
        // Save periodically for long sessions
        setInterval(() => {
            this.saveImmediately();
        }, 30000); // Every 30 seconds
    }
    
    debouncedSave() {
        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
            this.saveToStorage();
        }, this.saveDelay);
    }
    
    saveToStorage() {
        const state = this.stateManager.getState();
        const persistableState = this.filterPersistableData(state);
        
        const success = this.stateManager.setToStorage('appState', {
            data: persistableState,
            timestamp: Date.now(),
            version: '1.0'
        });
        
        if (!success && this.persistenceMode === 'localStorage') {
            this.persistenceMode = 'memory';
            this.showPersistenceWarning();
        }
    }
    
    showPersistenceWarning() {
        const warning = document.createElement('div');
        warning.className = 'plugin-name-persistence-warning';
        warning.innerHTML = `
            <div class="notice notice-info">
                <p><strong>Plugin Name:</strong> Using session-only storage. Your changes won't be saved when you close this tab.
                <a href="#" onclick="this.parentElement.parentElement.remove()">Dismiss</a></p>
            </div>
        `;
        
        document.body.insertBefore(warning, document.body.firstChild);
    }
    
    filterPersistableData(state) {
        // Only persist certain parts of state
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

### Form Data Persistence with Fallbacks
```javascript
class PluginNameFormPersistence {
    constructor(stateManager) {
        this.stateManager = stateManager;
        this.forms = new Map();
        this.sessionForms = new Map(); // Fallback for when localStorage unavailable
    }
    
    registerForm(formId, options = {}) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        this.forms.set(formId, {
            element: form,
            persistKey: options.persistKey || formId,
            excludeFields: options.excludeFields || ['password', 'token'],
            autoSave: options.autoSave !== false
        });
        
        // Restore form data
        this.restoreFormData(formId);
        
        // Set up auto-save
        if (options.autoSave !== false) {
            this.setupFormAutoSave(formId);
        }
    }
    
    restoreFormData(formId) {
        const formConfig = this.forms.get(formId);
        const state = this.stateManager.getState();
        const formData = state.app.formData[formConfig.persistKey];
        
        if (formData) {
            Object.entries(formData).forEach(([name, value]) => {
                const field = formConfig.element.querySelector(`[name="${name}"]`);
                if (field && !formConfig.excludeFields.includes(name)) {
                    field.value = value;
                }
            });
        }
    }
    
    setupFormAutoSave(formId) {
        const formConfig = this.forms.get(formId);
        
        formConfig.element.addEventListener('input', (e) => {
            const formData = new FormData(formConfig.element);
            const data = {};
            
            formData.forEach((value, name) => {
                if (!formConfig.excludeFields.includes(name)) {
                    data[name] = value;
                }
            });
            
            this.stateManager.updateState({
                app: {
                    formData: {
                        [formConfig.persistKey]: data
                    }
                }
            });
            
            // Session-only fallback if localStorage unavailable
            if (!this.stateManager.storageAvailable) {
                this.sessionForms.set(formConfig.persistKey, data);
            }
        });
    }
    
    clearFormData(formId) {
        const formConfig = this.forms.get(formId);
        this.stateManager.updateState({
            app: {
                formData: {
                    [formConfig.persistKey]: {}
                }
            }
        });
        
        // Clear session fallback too
        this.sessionForms.delete(formConfig.persistKey);
    }
}
```

## WordPress Integration

### Server Sync for Preferences (No Fallback Storage)
```javascript
class PluginNameServerSync {
    constructor(stateManager, apiClient) {
        this.stateManager = stateManager;
        this.apiClient = apiClient;
        this.syncTimeout = null;
    }
    
    async syncUserPreferences() {
        if (!this.stateManager.getState().user.isLoggedIn) {
            return; // Only sync for logged-in users - no guest data storage
        }
        
        const preferences = this.stateManager.getState().user.preferences;
        
        try {
            await this.apiClient.updateUserPreferences(preferences);
            
            // Mark as synced
            this.stateManager.updateState({
                user: {
                    lastSyncTime: Date.now(),
                    syncStatus: 'success'
                }
            });
            
        } catch (error) {
            console.warn('Preference sync failed:', error);
            
            this.stateManager.updateState({
                user: {
                    syncStatus: 'failed',
                    syncError: error.message
                }
            });
            
            // Retry after delay
            this.scheduleRetry();
        }
    }
    
    scheduleRetry() {
        clearTimeout(this.syncTimeout);
        this.syncTimeout = setTimeout(() => {
            this.syncUserPreferences();
        }, 30000); // Retry after 30 seconds
    }
    
    // Sync preferences when they change
    setupPreferenceSync() {
        this.stateManager.on('preferenceChange', () => {
            // Debounced sync
            clearTimeout(this.syncTimeout);
            this.syncTimeout = setTimeout(() => {
                this.syncUserPreferences();
            }, 2000);
        });
    }
}
```

### PHP User Preference Handler
```php
class Plugin_Name_User_Preferences {
    
    public function get_user_preferences() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return array();
        }
        
        $preferences = get_user_meta( $user_id, 'plugin_name_preferences', true );
        return $preferences ?: array();
    }
    
    public function update_user_preferences( $preferences ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        
        // Sanitize preferences
        $clean_preferences = $this->sanitize_preferences( $preferences );
        
        // Update user meta (NO guest data storage by design)
        return update_user_meta( $user_id, 'plugin_name_preferences', $clean_preferences );
    }
    
    private function sanitize_preferences( $preferences ) {
        $allowed_keys = array(
            'theme',
            'language',
            'notifications',
            'display_settings',
            'form_defaults'
        );
        
        $sanitized = array();
        
        foreach ( $allowed_keys as $key ) {
            if ( isset( $preferences[ $key ] ) ) {
                switch ( $key ) {
                    case 'theme':
                        $sanitized[ $key ] = sanitize_text_field( $preferences[ $key ] );
                        break;
                    case 'notifications':
                        $sanitized[ $key ] = (bool) $preferences[ $key ];
                        break;
                    case 'display_settings':
                        $sanitized[ $key ] = $this->sanitize_array( $preferences[ $key ] );
                        break;
                    default:
                        $sanitized[ $key ] = sanitize_text_field( $preferences[ $key ] );
                }
            }
        }
        
        return $sanitized;
    }
}
```

## Storage Fallback Implementation

### Enhanced Storage Manager
```javascript
class PluginNameStorage {
    constructor() {
        this.prefix = 'pluginName_';
        this.maxAge = 24 * 60 * 60 * 1000; // 24 hours
        this.storageMode = this.detectStorageMode();
        this.sessionStorage = new Map();
    }
    
    detectStorageMode() {
        // Test localStorage availability
        try {
            const test = '__test__';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return 'localStorage';
        } catch (e) {
            console.warn('localStorage unavailable, using session storage');
        }
        
        // Test sessionStorage availability
        try {
            const test = '__test__';
            sessionStorage.setItem(test, test);
            sessionStorage.removeItem(test);
            return 'sessionStorage';
        } catch (e) {
            console.warn('sessionStorage unavailable, using memory storage');
        }
        
        return 'memory';
    }
    
    set(key, data, options = {}) {
        const item = {
            data: data,
            timestamp: Date.now(),
            maxAge: options.maxAge || this.maxAge
        };
        
        switch (this.storageMode) {
            case 'localStorage':
                try {
                    localStorage.setItem(this.prefix + key, JSON.stringify(item));
                    return 'persistent';
                } catch (e) {
                    this.storageMode = 'memory';
                    // Fall through to memory storage
                }
                break;
                
            case 'sessionStorage':
                try {
                    sessionStorage.setItem(this.prefix + key, JSON.stringify(item));
                    return 'session';
                } catch (e) {
                    this.storageMode = 'memory';
                    // Fall through to memory storage
                }
                break;
        }
        
        // Memory storage fallback (lost on page reload)
        this.sessionStorage.set(key, item);
        return 'memory';
    }
    
    get(key) {
        let item = null;
        
        switch (this.storageMode) {
            case 'localStorage':
                try {
                    const stored = localStorage.getItem(this.prefix + key);
                    item = stored ? JSON.parse(stored) : null;
                } catch (e) {
                    console.warn('Failed to read from localStorage:', e);
                }
                break;
                
            case 'sessionStorage':
                try {
                    const stored = sessionStorage.getItem(this.prefix + key);
                    item = stored ? JSON.parse(stored) : null;
                } catch (e) {
                    console.warn('Failed to read from sessionStorage:', e);
                }
                break;
                
            case 'memory':
                item = this.sessionStorage.get(key) || null;
                break;
        }
        
        if (!item) return null;
        
        // Check expiration
        if (Date.now() - item.timestamp > item.maxAge) {
            this.remove(key);
            return null;
        }
        
        return item.data;
    }
    
    remove(key) {
        switch (this.storageMode) {
            case 'localStorage':
                localStorage.removeItem(this.prefix + key);
                break;
            case 'sessionStorage':
                sessionStorage.removeItem(this.prefix + key);
                break;
            case 'memory':
                this.sessionStorage.delete(key);
                break;
        }
    }
    
    // Sync with server for logged-in users only (no guest storage)
    async syncWithServer(key, apiEndpoint) {
        if (!this.isUserLoggedIn()) {
            return; // No server storage for guests by design
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
    
    getStorageInfo() {
        return {
            mode: this.storageMode,
            persistent: this.storageMode === 'localStorage',
            sessionOnly: this.storageMode === 'sessionStorage',
            memoryOnly: this.storageMode === 'memory'
        };
    }
}
```

## Usage Examples

### Initialize State Management with Fallbacks
```javascript
// Main app initialization
document.addEventListener('DOMContentLoaded', () => {
    const stateManager = new PluginNameStateManager();
    const apiClient = new PluginNameAPIClient();
    
    // Check storage availability and warn user if needed
    const storageInfo = stateManager.storage?.getStorageInfo?.();
    if (storageInfo && !storageInfo.persistent) {
        console.warn('Using fallback storage mode:', storageInfo.mode);
    }
    
    // Set up transitions
    const userTransition = new PluginNameUserTransition(stateManager, apiClient);
    
    // Set up persistence
    const persistence = new PluginNamePersistence(stateManager);
    const formPersistence = new PluginNameFormPersistence(stateManager);
    
    // Set up server sync (user preferences only)
    const serverSync = new PluginNameServerSync(stateManager, apiClient);
    serverSync.setupPreferenceSync();
    
    // Register forms that should persist
    formPersistence.registerForm('contact-form', {
        excludeFields: ['email', 'phone'], // Don't persist sensitive data
        autoSave: true
    });
    
    // Handle login detection
    if (stateManager.getState().user.isLoggedIn) {
        userTransition.handleUserLogin();
    }
});
```

### State Updates with Storage Awareness
```javascript
// Update application state
stateManager.updateState({
    app: {
        currentView: 'dashboard',
        filters: { category: 'news', status: 'published' }
    }
});

// Update user preferences (will sync to server if logged in)
stateManager.updateState({
    user: {
        preferences: {
            theme: 'dark',
            notifications: true
        }
    }
});

// Check if data will persist
const storageInfo = stateManager.storage.getStorageInfo();
if (!storageInfo.persistent) {
    console.warn('Data will not persist beyond this session');
}
```

## Critical Rules

1. **localStorage First** - Always check localStorage before API calls
2. **Graceful Degradation** - Apps work even if localStorage fails
3. **Warning Notifications** - Inform users about storage limitations
4. **No Server Fallback** - Guest data stays client-side only
5. **Seamless Transitions** - Guest state smoothly becomes user state
6. **Auto-Save Everything** - Forms, preferences, view state (when possible)
7. **Server Sync Only for Users** - Never store guest data in database
8. **Nest All Admin Pages** - All admin option pages MUST use `add_submenu_page()` with parent slug `'rwp-creator-tools'` - never create additional top-level menus
9. **State Validation** - Always sanitize data from any storage
10. **Memory Management** - Clean up old/unused state data
11. **Session Awareness** - Track storage mode and inform user accordingly