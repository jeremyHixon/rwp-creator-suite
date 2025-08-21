/**
 * Shared State Management Utilities for RWP Creator Suite
 *
 * Provides enhanced state management functionality with guest persistence,
 * user login detection, and cross-application utilities.
 */

/**
 * Enhanced RWP State Manager with guest persistence and user transition support.
 *
 * Extends the basic RWPStateManager with capabilities for:
 * - Guest user state persistence
 * - User login detection
 * - State transition between guest and authenticated users
 * - Automatic cleanup and data migration
 */
class RWPEnhancedStateManager extends RWPStateManager {
	constructor( namespace, initialState = {}, options = {} ) {
		super( namespace, initialState );

		this.options = {
			guestPersistence: true,
			guestDataMaxAge: 7 * 24 * 60 * 60 * 1000, // 7 days default
			autoCleanup: true,
			...options,
		};

		this.isLoggedIn = this.detectUserLoginState();
		this.guestStorageKey = `rwp_${ namespace }_guest_state`;

		// Load guest state if applicable
		if ( ! this.isLoggedIn && this.options.guestPersistence ) {
			this.loadGuestState();
		}

		// Setup automatic cleanup
		if ( this.options.autoCleanup ) {
			this.cleanupExpiredGuestData();
		}
	}

	/**
	 * Detect if user is currently logged in (client-side detection).
	 */
	detectUserLoginState() {
		// Multiple detection methods for reliability
		const methods = [
			// WordPress admin bar presence
			() => document.body.classList.contains( 'admin-bar' ),

			// WordPress global variables
			() =>
				typeof window.wpApiSettings !== 'undefined' &&
				window.wpApiSettings.nonce !== '',

			// Check for WordPress REST API nonce
			() =>
				typeof window.wp !== 'undefined' &&
				window.wp.apiRequest &&
				window.wp.apiRequest.nonceMiddleware,

			// Check for user-specific elements
			() => document.querySelector( '.logged-in' ) !== null,

			// Check body classes for logged-in status
			() => document.body.classList.contains( 'logged-in' ),

			// Check for WordPress customizer or admin elements
			() => document.querySelector( '#wpadminbar' ) !== null,
		];

		return methods.some( ( method ) => {
			try {
				return method();
			} catch ( error ) {
				return false;
			}
		} );
	}

	/**
	 * Save guest state to localStorage with timestamp and cleanup.
	 */
	saveGuestState() {
		if ( this.isLoggedIn || ! this.options.guestPersistence ) {
			return;
		}

		try {
			const currentState = this.getState();

			// Filter out sensitive data for guest storage
			const guestSafeState = this.filterGuestSafeData( currentState );

			const dataToStore = {
				state: guestSafeState,
				timestamp: Date.now(),
				version: '1.0',
				namespace: this.namespace,
			};

			localStorage.setItem(
				this.guestStorageKey,
				JSON.stringify( dataToStore )
			);
		} catch ( error ) {
			console.warn( 'Failed to save guest state:', error );
		}
	}

	/**
	 * Load guest state from localStorage if valid and recent.
	 */
	loadGuestState() {
		try {
			const stored = localStorage.getItem( this.guestStorageKey );
			if ( ! stored ) {
				return;
			}

			const parsedData = JSON.parse( stored );

			// Check if data is recent enough
			const maxAge = this.options.guestDataMaxAge;
			if ( Date.now() - parsedData.timestamp > maxAge ) {
				// Clean up expired data
				localStorage.removeItem( this.guestStorageKey );
				return;
			}

			// Merge guest state with current state
			if ( parsedData.state && typeof parsedData.state === 'object' ) {
				this.state = { ...this.state, ...parsedData.state };
			}
		} catch ( error ) {
			console.warn( 'Failed to load guest state:', error );
		}
	}

	/**
	 * Filter state data to only include guest-safe information.
	 * Override this method in subclasses to customize what data is safe for guest storage.
	 * @param state
	 */
	filterGuestSafeData( state ) {
		// Default implementation - remove any keys containing 'user', 'private', 'secret', etc.
		const sensitiveKeys = [
			'user',
			'private',
			'secret',
			'token',
			'auth',
			'password',
		];
		const safestateMap = {};

		Object.keys( state ).forEach( ( key ) => {
			const keyLower = key.toLowerCase();
			const isSensitive = sensitiveKeys.some( ( sensitiveKey ) =>
				keyLower.includes( sensitiveKey )
			);

			if ( ! isSensitive ) {
				safestateMap[ key ] = state[ key ];
			}
		} );

		return safestateMap;
	}

	/**
	 * Handle user login transition - migrate guest data to user storage.
	 */
	handleUserLoginTransition() {
		if ( ! this.isLoggedIn ) {
			return;
		}

		// Get guest data before clearing it
		const guestData = this.getGuestData();

		if ( guestData && guestData.state ) {
			// Merge guest data with current authenticated user state
			this.setState( guestData.state );
		}

		// Clean up guest storage
		this.clearGuestStorage();
	}

	/**
	 * Get stored guest data without loading it into current state.
	 */
	getGuestData() {
		try {
			const stored = localStorage.getItem( this.guestStorageKey );
			if ( stored ) {
				return JSON.parse( stored );
			}
		} catch ( error ) {
			console.warn( 'Failed to get guest data:', error );
		}
		return null;
	}

	/**
	 * Clear guest storage.
	 */
	clearGuestStorage() {
		try {
			localStorage.removeItem( this.guestStorageKey );
		} catch ( error ) {
			console.warn( 'Failed to clear guest storage:', error );
		}
	}

	/**
	 * Clean up expired guest data across all namespaces.
	 */
	cleanupExpiredGuestData() {
		try {
			const keysToRemove = [];
			const maxAge = this.options.guestDataMaxAge;

			for ( let i = 0; i < localStorage.length; i++ ) {
				const key = localStorage.key( i );
				if ( key && key.includes( '_guest_state' ) ) {
					try {
						const data = JSON.parse( localStorage.getItem( key ) );
						if (
							data.timestamp &&
							Date.now() - data.timestamp > maxAge
						) {
							keysToRemove.push( key );
						}
					} catch ( error ) {
						// If we can't parse it, it's probably corrupted, remove it
						keysToRemove.push( key );
					}
				}
			}

			// Remove expired items
			keysToRemove.forEach( ( key ) => localStorage.removeItem( key ) );
		} catch ( error ) {
			console.warn( 'Failed to cleanup expired guest data:', error );
		}
	}

	/**
	 * Override setState to also save guest state when appropriate.
	 * @param updates
	 */
	setState( updates ) {
		super.setState( updates );

		// Also save guest state if not logged in
		if ( ! this.isLoggedIn && this.options.guestPersistence ) {
			this.saveGuestState();
		}
	}
}

/**
 * User Detection Utilities
 *
 * Shared utilities for detecting user login state and capabilities.
 */
class RWPUserUtils {
	/**
	 * Comprehensive user login detection.
	 */
	static isUserLoggedIn() {
		const manager = new RWPEnhancedStateManager( 'temp' );
		const result = manager.detectUserLoginState();
		return result;
	}

	/**
	 * Get current user ID if available from client-side data.
	 */
	static getCurrentUserId() {
		// Try to get user ID from various WordPress global variables
		if (
			typeof window.wpApiSettings !== 'undefined' &&
			window.wpApiSettings.user &&
			window.wpApiSettings.user.id
		) {
			return window.wpApiSettings.user.id;
		}

		// Try getting from wp global
		if (
			typeof window.wp !== 'undefined' &&
			window.wp.data &&
			typeof window.wp.data.select === 'function'
		) {
			try {
				const coreData = window.wp.data.select( 'core' );
				if ( coreData && coreData.getCurrentUser ) {
					const user = coreData.getCurrentUser();
					return user ? user.id : null;
				}
			} catch ( error ) {
				// Silently fail
			}
		}

		return null;
	}

	/**
	 * Check if user has specific capability (if detectable client-side).
	 */
	static userCanManageOptions() {
		// Check for admin bar presence as a proxy for manage_options capability
		return document.querySelector( '#wpadminbar' ) !== null;
	}

	/**
	 * Get REST API nonce if available.
	 */
	static getRestNonce() {
		if (
			typeof window.wpApiSettings !== 'undefined' &&
			window.wpApiSettings.nonce
		) {
			return window.wpApiSettings.nonce;
		}

		if (
			typeof window.wp !== 'undefined' &&
			window.wp.apiRequest &&
			window.wp.apiRequest.nonceMiddleware
		) {
			// Try to extract nonce from middleware
			try {
				return window.wp.apiRequest.nonceMiddleware.nonce;
			} catch ( error ) {
				// Silently fail
			}
		}

		return null;
	}
}

/**
 * Storage Utilities
 *
 * Shared utilities for storage management and detection.
 */
class RWPStorageUtils {
	/**
	 * Test if localStorage is available and working.
	 */
	static isLocalStorageAvailable() {
		try {
			const test = '__rwp_storage_test__';
			localStorage.setItem( test, test );
			localStorage.removeItem( test );
			return true;
		} catch ( error ) {
			return false;
		}
	}

	/**
	 * Test if sessionStorage is available and working.
	 */
	static isSessionStorageAvailable() {
		try {
			const test = '__rwp_session_test__';
			sessionStorage.setItem( test, test );
			sessionStorage.removeItem( test );
			return true;
		} catch ( error ) {
			return false;
		}
	}

	/**
	 * Get storage quota information if available.
	 */
	static getStorageQuota() {
		if ( 'storage' in navigator && 'estimate' in navigator.storage ) {
			return navigator.storage.estimate();
		}
		return Promise.resolve( { quota: null, usage: null } );
	}

	/**
	 * Show storage warning notification.
	 * @param message
	 * @param duration
	 */
	static showStorageWarning( message, duration = 5000 ) {
		const warning = document.createElement( 'div' );
		warning.className = 'rwp-storage-warning';
		warning.innerHTML = `
            <div class="rwp-warning-content">
                <span class="rwp-warning-icon">⚠️</span>
                <span class="rwp-warning-text">${ message }</span>
                <button class="rwp-warning-close" onclick="this.parentElement.parentElement.remove()">×</button>
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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        `;

		document.body.appendChild( warning );

		// Auto-remove after specified duration
		setTimeout( () => {
			if ( warning.parentElement ) {
				warning.remove();
			}
		}, duration );
	}
}

/**
 * App-Specific State Managers
 *
 * Pre-configured state managers for common RWP applications.
 */

/**
 * Caption Writer specific state manager.
 */
class RWPCaptionWriterStateManager extends RWPEnhancedStateManager {
	constructor( initialState = {} ) {
		const defaultState = {
			description: '',
			platforms: [ 'instagram' ],
			tone: 'casual',
			generatedCaptions: [],
			templates: [],
			favorites: [],
			finalCaption: '',
			isGenerating: false,
			activeTab: 'generator',
			characterCount: 0,
		};

		super(
			'caption_writer',
			{ ...defaultState, ...initialState },
			{
				guestPersistence: true,
				guestDataMaxAge: 7 * 24 * 60 * 60 * 1000, // 7 days
				autoCleanup: true,
			}
		);
	}

	/**
	 * Filter guest-safe data for caption writer.
	 * @param state
	 */
	filterGuestSafeData( state ) {
		// Caption writer guest-safe fields
		const safeFields = [
			'description',
			'platforms',
			'tone',
			'finalCaption',
			'activeTab',
			'characterCount',
		];

		const safestateMap = {};
		safeFields.forEach( ( field ) => {
			if ( state.hasOwnProperty( field ) ) {
				safestateMap[ field ] = state[ field ];
			}
		} );

		return safestateMap;
	}

	/**
	 * Get unique storage key based on current page context.
	 */
	getContextualStorageKey() {
		const pageId = document.body.classList.contains( 'single' )
			? document.querySelector( 'article' )?.id || 'unknown'
			: window.location.pathname;
		return `rwp_caption_writer_guest_${ btoa( pageId ).slice( 0, 10 ) }`;
	}
}

/**
 * Content Repurposer specific state manager.
 */
class RWPContentRepurposerStateManager extends RWPEnhancedStateManager {
	constructor( initialState = {} ) {
		const defaultState = {
			content: '',
			platforms: [ 'twitter', 'linkedin' ],
			tone: 'professional',
			repurposedContent: {},
			isProcessing: false,
			showUsageStats: true,
			usageStats: null,
			error: null,
		};

		super(
			'content_repurposer',
			{ ...defaultState, ...initialState },
			{
				guestPersistence: true,
				guestDataMaxAge: 30 * 60 * 1000, // 30 minutes for content repurposer
				autoCleanup: true,
			}
		);
	}

	/**
	 * Filter guest-safe data for content repurposer.
	 * @param state
	 */
	filterGuestSafeData( state ) {
		// Content repurposer guest-safe fields
		const safeFields = [ 'content', 'platforms', 'tone', 'showUsageStats' ];

		const safestateMap = {};
		safeFields.forEach( ( field ) => {
			if ( state.hasOwnProperty( field ) ) {
				safestateMap[ field ] = state[ field ];
			}
		} );

		return safestateMap;
	}
}

// Export utilities for use in other scripts
if ( typeof window !== 'undefined' ) {
	window.RWPEnhancedStateManager = RWPEnhancedStateManager;
	window.RWPUserUtils = RWPUserUtils;
	window.RWPStorageUtils = RWPStorageUtils;
	window.RWPCaptionWriterStateManager = RWPCaptionWriterStateManager;
	window.RWPContentRepurposerStateManager = RWPContentRepurposerStateManager;
}
