/**
 * State Manager for Instagram Analyzer
 *
 * Handles data persistence with localStorage fallbacks and graceful degradation.
 */

class StateManager {
	constructor( config = {} ) {
		this.config = {
			storagePrefix: 'rwp_instagram_analyzer_',
			maxDataAge: 24 * 60 * 60 * 1000, // 24 hours
			fallbackEnabled: true,
			...config,
		};

		this.storage = {
			available: false,
			type: 'none',
		};

		this.fallbackData = {};
		this.init();
	}

	init() {
		this.detectStorageCapability();
		this.cleanupExpiredData();
	}

	detectStorageCapability() {
		// Test localStorage availability
		try {
			const testKey = `${ this.config.storagePrefix }test`;
			localStorage.setItem( testKey, 'test' );
			localStorage.removeItem( testKey );
			this.storage = {
				available: true,
				type: 'localStorage',
			};
		} catch ( error ) {
			console.warn( 'localStorage not available:', error.message );

			// Test sessionStorage as fallback
			try {
				const testKey = `${ this.config.storagePrefix }test`;
				sessionStorage.setItem( testKey, 'test' );
				sessionStorage.removeItem( testKey );
				this.storage = {
					available: true,
					type: 'sessionStorage',
				};
			} catch ( sessionError ) {
				console.warn(
					'sessionStorage not available:',
					sessionError.message
				);
				this.storage = {
					available: false,
					type: 'memory',
				};
			}
		}

		this.notifyStorageStatus();
	}

	notifyStorageStatus() {
		if ( ! this.storage.available ) {
			this.showWarning(
				'Storage not available - data will not persist between sessions'
			);
		} else if ( this.storage.type === 'sessionStorage' ) {
			this.showWarning(
				'localStorage unavailable - using temporary session storage'
			);
		} else if ( this.storage.type === 'localStorage' ) {
			// Success - localStorage is available and preferred
			console.log( 'State Manager: Using localStorage for data persistence' );
		}
	}

	showWarning( message ) {
		// Create a temporary warning notification
		const warning = document.createElement( 'div' );
		warning.className = 'blk-storage-warning';
		warning.innerHTML = `
            <div class="blk-warning-content">
                <span class="blk-warning-icon">⚠️</span>
                <span class="blk-warning-text">${ message }</span>
                <button class="blk-warning-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

		// Add warning styles
		warning.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fbbf24;
            color: #92400e;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            max-width: 300px;
            font-size: 14px;
        `;

		document.body.appendChild( warning );

		// Auto-remove after 5 seconds
		setTimeout( () => {
			if ( warning.parentElement ) {
				warning.remove();
			}
		}, 5000 );
	}

	getStorageEngine() {
		if ( ! this.storage.available ) {
			return this.fallbackData;
		}

		// Priority order: localStorage (preferred) -> sessionStorage (fallback) -> memory (last resort)
		return this.storage.type === 'localStorage'
			? localStorage
			: sessionStorage;
	}

	setItem( key, data, options = {} ) {
		const fullKey = `${ this.config.storagePrefix }${ key }`;
		const storageData = {
			data: data,
			timestamp: Date.now(),
			version: '1.0',
			options: options,
		};

		try {
			if ( this.storage.available ) {
				const engine = this.getStorageEngine();
				engine.setItem( fullKey, JSON.stringify( storageData ) );
			} else {
				// Fallback to memory storage
				this.fallbackData[ fullKey ] = storageData;
			}
			return true;
		} catch ( error ) {
			console.error( 'Failed to save data:', error );

			// Try fallback storage if main storage fails
			if ( this.storage.available && this.config.fallbackEnabled ) {
				try {
					this.fallbackData[ fullKey ] = storageData;
					this.showWarning(
						'Storage quota exceeded - using temporary storage'
					);
					return true;
				} catch ( fallbackError ) {
					console.error(
						'Fallback storage also failed:',
						fallbackError
					);
				}
			}

			return false;
		}
	}

	getItem( key ) {
		const fullKey = `${ this.config.storagePrefix }${ key }`;

		try {
			let rawData = null;

			if ( this.storage.available ) {
				const engine = this.getStorageEngine();
				rawData = engine.getItem( fullKey );
			} else {
				// Use memory fallback
				rawData = this.fallbackData[ fullKey ]
					? JSON.stringify( this.fallbackData[ fullKey ] )
					: null;
			}

			if ( ! rawData ) {
				return null;
			}

			const storageData = JSON.parse( rawData );

			// Check if data is expired
			if ( this.isExpired( storageData.timestamp ) ) {
				this.removeItem( key );
				return null;
			}

			return storageData.data;
		} catch ( error ) {
			console.error( 'Failed to retrieve data:', error );
			return null;
		}
	}

	removeItem( key ) {
		const fullKey = `${ this.config.storagePrefix }${ key }`;

		try {
			if ( this.storage.available ) {
				const engine = this.getStorageEngine();
				engine.removeItem( fullKey );
			}

			// Also remove from fallback
			delete this.fallbackData[ fullKey ];
		} catch ( error ) {
			console.error( 'Failed to remove data:', error );
		}
	}

	isExpired( timestamp ) {
		return Date.now() - timestamp > this.config.maxDataAge;
	}

	cleanupExpiredData() {
		if ( ! this.storage.available ) {
			// Clean memory fallback
			Object.keys( this.fallbackData ).forEach( ( key ) => {
				if ( key.startsWith( this.config.storagePrefix ) ) {
					const data = this.fallbackData[ key ];
					if ( this.isExpired( data.timestamp ) ) {
						delete this.fallbackData[ key ];
					}
				}
			} );
			return;
		}

		const engine = this.getStorageEngine();
		const keysToRemove = [];

		try {
			for ( let i = 0; i < engine.length; i++ ) {
				const key = engine.key( i );
				if ( key && key.startsWith( this.config.storagePrefix ) ) {
					const rawData = engine.getItem( key );
					if ( rawData ) {
						const storageData = JSON.parse( rawData );
						if ( this.isExpired( storageData.timestamp ) ) {
							keysToRemove.push( key );
						}
					}
				}
			}

			// Remove expired items
			keysToRemove.forEach( ( key ) => {
				engine.removeItem( key );
			} );
		} catch ( error ) {
			console.error( 'Failed to cleanup expired data:', error );
		}
	}

	clear() {
		if ( this.storage.available ) {
			const engine = this.getStorageEngine();
			const keysToRemove = [];

			try {
				for ( let i = 0; i < engine.length; i++ ) {
					const key = engine.key( i );
					if ( key && key.startsWith( this.config.storagePrefix ) ) {
						keysToRemove.push( key );
					}
				}

				keysToRemove.forEach( ( key ) => {
					engine.removeItem( key );
				} );
			} catch ( error ) {
				console.error( 'Failed to clear storage:', error );
			}
		}

		// Clear memory fallback
		Object.keys( this.fallbackData ).forEach( ( key ) => {
			if ( key.startsWith( this.config.storagePrefix ) ) {
				delete this.fallbackData[ key ];
			}
		} );
	}

	getStorageInfo() {
		return {
			available: this.storage.available,
			type: this.storage.type,
			fallbackEnabled: this.config.fallbackEnabled,
			maxDataAge: this.config.maxDataAge,
		};
	}

	// High-level methods for Instagram Analyzer data
	saveAnalysisData( data ) {
		return this.setItem( 'analysis_data', data, { type: 'analysis' } );
	}

	getAnalysisData() {
		return this.getItem( 'analysis_data' );
	}

	saveWhitelist( whitelist ) {
		return this.setItem( 'whitelist', whitelist, { type: 'whitelist' } );
	}

	getWhitelist() {
		return this.getItem( 'whitelist' ) || [];
	}

	saveUserPreferences( preferences ) {
		return this.setItem( 'preferences', preferences, {
			type: 'preferences',
		} );
	}

	getUserPreferences() {
		const defaultPreferences = {
			showPreviewImages: true,
			itemsPerPage: 20,
			sortOrder: 'username',
			theme: 'light',
		};

		const stored = this.getItem( 'preferences' );
		return stored
			? { ...defaultPreferences, ...stored }
			: defaultPreferences;
	}

	saveFormState( formData ) {
		return this.setItem( 'form_state', formData, {
			type: 'form',
			maxAge: 30 * 60 * 1000, // 30 minutes for form data
		} );
	}

	getFormState() {
		return this.getItem( 'form_state' );
	}

	clearFormState() {
		this.removeItem( 'form_state' );
	}

	saveViewedAccounts( viewedAccounts ) {
		return this.setItem( 'viewed_accounts', viewedAccounts, { type: 'viewed' } );
	}

	getViewedAccounts() {
		return this.getItem( 'viewed_accounts' ) || [];
	}

	markAccountAsViewed( username ) {
		const viewedAccounts = this.getViewedAccounts();
		if ( ! viewedAccounts.includes( username ) ) {
			viewedAccounts.push( username );
			this.saveViewedAccounts( viewedAccounts );
		}
		return true;
	}

	clearViewedAccounts() {
		this.removeItem( 'viewed_accounts' );
	}
}

/**
 * Generic RWP State Manager
 * 
 * A lightweight state management solution for RWP blocks with state persistence
 * and event handling capabilities.
 */
class RWPStateManager {
    constructor(namespace, initialState = {}) {
        this.namespace = namespace;
        this.state = { ...initialState };
        this.listeners = [];
        this.storageKey = `rwp_${namespace}_state`;
        
        // Load persisted state
        this.loadState();
    }
    
    setState(updates) {
        const prevState = { ...this.state };
        this.state = { ...this.state, ...updates };
        
        // Persist state
        this.saveState();
        
        // Notify listeners
        this.listeners.forEach(listener => {
            try {
                listener(this.state, prevState);
            } catch (error) {
                console.error('State listener error:', error);
            }
        });
    }
    
    getState() {
        return { ...this.state };
    }
    
    subscribe(listener) {
        if (typeof listener !== 'function') {
            console.error('State listener must be a function');
            return () => {};
        }
        
        this.listeners.push(listener);
        
        // Return unsubscribe function
        return () => {
            const index = this.listeners.indexOf(listener);
            if (index > -1) {
                this.listeners.splice(index, 1);
            }
        };
    }
    
    resetState() {
        this.state = {};
        this.clearPersistedState();
    }
    
    loadState() {
        try {
            if (typeof Storage !== 'undefined') {
                const stored = localStorage.getItem(this.storageKey);
                if (stored) {
                    const parsedState = JSON.parse(stored);
                    this.state = { ...this.state, ...parsedState };
                }
            }
        } catch (error) {
            console.warn('Failed to load persisted state:', error);
        }
    }
    
    saveState() {
        try {
            if (typeof Storage !== 'undefined') {
                localStorage.setItem(this.storageKey, JSON.stringify(this.state));
            }
        } catch (error) {
            console.warn('Failed to persist state:', error);
        }
    }
    
    clearPersistedState() {
        try {
            if (typeof Storage !== 'undefined') {
                localStorage.removeItem(this.storageKey);
            }
        } catch (error) {
            console.warn('Failed to clear persisted state:', error);
        }
    }
}
