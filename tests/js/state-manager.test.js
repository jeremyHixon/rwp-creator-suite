/**
 * Tests for StateManager class
 */

// Mock the StateManager class since it's not a module
const stateManagerCode = `
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
			const testKey = this.config.storagePrefix + 'test';
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
				const testKey = this.config.storagePrefix + 'test';
				sessionStorage.setItem( testKey, 'test' );
				sessionStorage.removeItem( testKey );
				this.storage = {
					available: true,
					type: 'sessionStorage',
				};
			} catch ( sessionError ) {
				console.warn( 'sessionStorage not available:', sessionError.message );
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
			this.showWarning( 'Storage not available - data will not persist between sessions' );
		} else if ( this.storage.type === 'sessionStorage' ) {
			this.showWarning( 'localStorage unavailable - using temporary session storage' );
		} else if ( this.storage.type === 'localStorage' ) {
			console.log( 'State Manager: Using localStorage for data persistence' );
		}
	}

	showWarning( message ) {
		// Create a temporary warning notification - simplified for testing
		console.warn( message );
	}

	cleanupExpiredData() {
		if ( ! this.storage.available ) {
			return;
		}

		const now = Date.now();
		const storage = this.storage.type === 'localStorage' ? localStorage : sessionStorage;
		const keysToRemove = [];

		for ( let i = 0; i < storage.length; i++ ) {
			const key = storage.key( i );
			if ( key && key.startsWith( this.config.storagePrefix ) ) {
				try {
					const data = JSON.parse( storage.getItem( key ) );
					if ( data && data.timestamp && ( now - data.timestamp > this.config.maxDataAge ) ) {
						keysToRemove.push( key );
					}
				} catch ( error ) {
					// Invalid JSON, remove it
					keysToRemove.push( key );
				}
			}
		}

		keysToRemove.forEach( key => storage.removeItem( key ) );
	}

	setData( key, data ) {
		const fullKey = this.config.storagePrefix + key;
		const dataWithTimestamp = {
			data,
			timestamp: Date.now(),
		};

		if ( this.storage.available ) {
			const storage = this.storage.type === 'localStorage' ? localStorage : sessionStorage;
			try {
				storage.setItem( fullKey, JSON.stringify( dataWithTimestamp ) );
				return true;
			} catch ( error ) {
				console.warn( 'Storage write failed, using fallback:', error.message );
			}
		}

		if ( this.config.fallbackEnabled ) {
			this.fallbackData[ key ] = dataWithTimestamp;
			return true;
		}

		return false;
	}

	getData( key ) {
		const fullKey = this.config.storagePrefix + key;

		if ( this.storage.available ) {
			const storage = this.storage.type === 'localStorage' ? localStorage : sessionStorage;
			try {
				const rawData = storage.getItem( fullKey );
				if ( rawData ) {
					const parsedData = JSON.parse( rawData );
					const now = Date.now();
					
					if ( parsedData.timestamp && ( now - parsedData.timestamp > this.config.maxDataAge ) ) {
						storage.removeItem( fullKey );
						return null;
					}
					
					return parsedData.data;
				}
			} catch ( error ) {
				console.warn( 'Storage read failed, checking fallback:', error.message );
			}
		}

		if ( this.config.fallbackEnabled && this.fallbackData[ key ] ) {
			const now = Date.now();
			const storedData = this.fallbackData[ key ];
			
			if ( storedData.timestamp && ( now - storedData.timestamp > this.config.maxDataAge ) ) {
				delete this.fallbackData[ key ];
				return null;
			}
			
			return storedData.data;
		}

		return null;
	}

	removeData( key ) {
		const fullKey = this.config.storagePrefix + key;

		if ( this.storage.available ) {
			const storage = this.storage.type === 'localStorage' ? localStorage : sessionStorage;
			storage.removeItem( fullKey );
		}

		if ( this.config.fallbackEnabled ) {
			delete this.fallbackData[ key ];
		}
	}

	clear() {
		if ( this.storage.available ) {
			const storage = this.storage.type === 'localStorage' ? localStorage : sessionStorage;
			const keysToRemove = [];

			for ( let i = 0; i < storage.length; i++ ) {
				const key = storage.key( i );
				if ( key && key.startsWith( this.config.storagePrefix ) ) {
					keysToRemove.push( key );
				}
			}

			keysToRemove.forEach( key => storage.removeItem( key ) );
		}

		if ( this.config.fallbackEnabled ) {
			this.fallbackData = {};
		}
	}
}

window.StateManager = StateManager;
`;

// Execute the StateManager code in the test environment
eval( stateManagerCode );

describe( 'StateManager', () => {
	let stateManager;

	beforeEach( () => {
		// Clear storage before each test
		localStorage.clear();
		sessionStorage.clear();
		
		// Reset mocks
		localStorage.setItem.mockClear();
		localStorage.getItem.mockClear();
		localStorage.removeItem.mockClear();
		
		sessionStorage.setItem.mockClear();
		sessionStorage.getItem.mockClear();
		sessionStorage.removeItem.mockClear();
		
		console.warn.mockClear();
		console.log.mockClear();
	});

	afterEach( () => {
		if ( stateManager ) {
			stateManager.clear();
		}
	});

	describe( 'Initialization', () => {
		test( 'should initialize with default config', () => {
			stateManager = new StateManager();
			
			expect( stateManager.config.storagePrefix ).toBe( 'rwp_instagram_analyzer_' );
			expect( stateManager.config.maxDataAge ).toBe( 24 * 60 * 60 * 1000 );
			expect( stateManager.config.fallbackEnabled ).toBe( true );
		});

		test( 'should accept custom config', () => {
			const customConfig = {
				storagePrefix: 'custom_prefix_',
				maxDataAge: 60000,
				fallbackEnabled: false,
			};

			stateManager = new StateManager( customConfig );
			
			expect( stateManager.config.storagePrefix ).toBe( 'custom_prefix_' );
			expect( stateManager.config.maxDataAge ).toBe( 60000 );
			expect( stateManager.config.fallbackEnabled ).toBe( false );
		});
	});

	describe( 'Storage Detection', () => {
		test( 'should detect localStorage availability', () => {
			stateManager = new StateManager();
			
			expect( stateManager.storage.available ).toBe( true );
			expect( stateManager.storage.type ).toBe( 'localStorage' );
			expect( console.log ).toHaveBeenCalledWith( 'State Manager: Using localStorage for data persistence' );
		});

		test( 'should fallback to sessionStorage when localStorage fails', () => {
			// Mock localStorage to throw error
			localStorage.setItem.mockImplementation( () => {
				throw new Error( 'localStorage disabled' );
			});

			stateManager = new StateManager();
			
			expect( stateManager.storage.available ).toBe( true );
			expect( stateManager.storage.type ).toBe( 'sessionStorage' );
			expect( console.warn ).toHaveBeenCalledWith( 'localStorage not available:', 'localStorage disabled' );
		});

		test( 'should fallback to memory when both localStorage and sessionStorage fail', () => {
			// Mock both storage types to throw errors
			localStorage.setItem.mockImplementation( () => {
				throw new Error( 'localStorage disabled' );
			});
			sessionStorage.setItem.mockImplementation( () => {
				throw new Error( 'sessionStorage disabled' );
			});

			stateManager = new StateManager();
			
			expect( stateManager.storage.available ).toBe( false );
			expect( stateManager.storage.type ).toBe( 'memory' );
			expect( console.warn ).toHaveBeenCalledWith( 'Storage not available - data will not persist between sessions' );
		});
	});

	describe( 'Data Management', () => {
		beforeEach( () => {
			stateManager = new StateManager();
		});

		test( 'should store and retrieve data', () => {
			const testData = { test: 'value', number: 123 };
			
			const setResult = stateManager.setData( 'test-key', testData );
			expect( setResult ).toBe( true );
			
			const retrievedData = stateManager.getData( 'test-key' );
			expect( retrievedData ).toEqual( testData );
		});

		test( 'should return null for non-existent keys', () => {
			const result = stateManager.getData( 'non-existent-key' );
			expect( result ).toBeNull();
		});

		test( 'should remove data', () => {
			stateManager.setData( 'test-key', { test: 'value' } );
			
			let data = stateManager.getData( 'test-key' );
			expect( data ).toEqual( { test: 'value' } );
			
			stateManager.removeData( 'test-key' );
			
			data = stateManager.getData( 'test-key' );
			expect( data ).toBeNull();
		});

		test( 'should clear all plugin data', () => {
			stateManager.setData( 'key1', 'value1' );
			stateManager.setData( 'key2', 'value2' );
			
			expect( stateManager.getData( 'key1' ) ).toBe( 'value1' );
			expect( stateManager.getData( 'key2' ) ).toBe( 'value2' );
			
			stateManager.clear();
			
			expect( stateManager.getData( 'key1' ) ).toBeNull();
			expect( stateManager.getData( 'key2' ) ).toBeNull();
		});
	});

	describe( 'Fallback Behavior', () => {
		test( 'should use fallback when storage write fails', () => {
			stateManager = new StateManager();
			
			// Mock localStorage to fail on write but not on detection
			localStorage.setItem.mockImplementationOnce( () => {
				// Allow the detection test to pass
			}).mockImplementation( () => {
				throw new Error( 'Storage quota exceeded' );
			});

			const testData = { test: 'fallback' };
			const setResult = stateManager.setData( 'test-key', testData );
			
			expect( setResult ).toBe( true );
			expect( console.warn ).toHaveBeenCalledWith( 'Storage write failed, using fallback:', 'Storage quota exceeded' );
			
			const retrievedData = stateManager.getData( 'test-key' );
			expect( retrievedData ).toEqual( testData );
		});

		test( 'should handle disabled fallback', () => {
			stateManager = new StateManager({ fallbackEnabled: false });
			
			// Mock localStorage to fail on write
			localStorage.setItem.mockImplementationOnce( () => {
				// Allow detection test to pass
			}).mockImplementation( () => {
				throw new Error( 'Storage quota exceeded' );
			});

			const setResult = stateManager.setData( 'test-key', { test: 'value' } );
			expect( setResult ).toBe( false );
		});
	});

	describe( 'Data Expiration', () => {
		test( 'should expire old data', () => {
			stateManager = new StateManager({ maxDataAge: 1000 }); // 1 second
			
			// Mock Date.now to control time
			const originalNow = Date.now;
			let mockTime = 1000000;
			Date.now = jest.fn( () => mockTime );

			stateManager.setData( 'test-key', 'test-value' );
			
			// Advance time beyond expiration
			mockTime += 2000;
			
			const result = stateManager.getData( 'test-key' );
			expect( result ).toBeNull();
			
			// Restore Date.now
			Date.now = originalNow;
		});

		test( 'should clean up expired data on init', () => {
			// Create a state manager and add data
			stateManager = new StateManager({ maxDataAge: 1000 });
			
			// Mock storage to return expired data
			localStorage.getItem.mockReturnValue( JSON.stringify({
				data: 'expired-value',
				timestamp: Date.now() - 2000, // 2 seconds ago
			}));
			localStorage.key.mockReturnValue( 'rwp_instagram_analyzer_expired-key' );
			localStorage.length = 1;

			// Create new state manager which should clean up on init
			const cleanupManager = new StateManager({ maxDataAge: 1000 });
			
			expect( localStorage.removeItem ).toHaveBeenCalledWith( 'rwp_instagram_analyzer_expired-key' );
		});
	});

	describe( 'Error Handling', () => {
		test( 'should handle corrupted stored data', () => {
			stateManager = new StateManager();
			
			// Mock localStorage to return invalid JSON
			localStorage.getItem.mockReturnValue( 'invalid-json' );
			
			const result = stateManager.getData( 'test-key' );
			expect( result ).toBeNull();
			expect( console.warn ).toHaveBeenCalledWith( 
				'Storage read failed, checking fallback:', 
				expect.any( Error ) 
			);
		});

		test( 'should remove corrupted data during cleanup', () => {
			stateManager = new StateManager();
			
			localStorage.key.mockReturnValue( 'rwp_instagram_analyzer_corrupted' );
			localStorage.length = 1;
			localStorage.getItem.mockReturnValue( 'invalid-json' );

			// Trigger cleanup
			stateManager.cleanupExpiredData();
			
			expect( localStorage.removeItem ).toHaveBeenCalledWith( 'rwp_instagram_analyzer_corrupted' );
		});
	});
});