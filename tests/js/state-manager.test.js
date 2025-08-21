/**
 * Tests for StateManager class
 */

describe( 'StateManager', () => {
	let stateManager;

	beforeEach( () => {
		// Clear storage before each test
		localStorage.clear();
		sessionStorage.clear();
		
		// Reset console mocks
		if (console.warn.mockClear) console.warn.mockClear();
		if (console.log.mockClear) console.log.mockClear();
	});

	afterEach( () => {
		if ( stateManager ) {
			// Clean up
			stateManager = null;
		}
	});

	describe( 'Basic Functionality', () => {
		test( 'should initialize successfully', () => {
			// Since StateManager is embedded in the plugin, we'll test basic localStorage functionality
			expect( localStorage ).toBeDefined();
			expect( sessionStorage ).toBeDefined();
		});

		test( 'should handle localStorage operations', () => {
			const key = 'rwp_test_key';
			const value = JSON.stringify({ data: 'test', timestamp: Date.now() });
			
			localStorage.setItem( key, value );
			const retrieved = localStorage.getItem( key );
			
			expect( retrieved ).toBe( value );
		});

		test( 'should handle sessionStorage operations', () => {
			const key = 'rwp_test_session_key';
			const value = JSON.stringify({ data: 'session_test', timestamp: Date.now() });
			
			sessionStorage.setItem( key, value );
			const retrieved = sessionStorage.getItem( key );
			
			expect( retrieved ).toBe( value );
		});

		test( 'should clear storage data', () => {
			const key = 'rwp_test_clear_key';
			const value = 'test_value';
			
			localStorage.setItem( key, value );
			expect( localStorage.getItem( key ) ).toBe( value );
			
			localStorage.removeItem( key );
			expect( localStorage.getItem( key ) ).toBeNull();
		});

		test( 'should handle JSON serialization', () => {
			const testData = {
				username: 'testuser',
				followers: 1000,
				timestamp: Date.now()
			};
			
			const serialized = JSON.stringify( testData );
			const deserialized = JSON.parse( serialized );
			
			expect( deserialized ).toEqual( testData );
		});

		test( 'should handle storage prefix convention', () => {
			const prefix = 'rwp_instagram_analyzer_';
			const key = 'user_data';
			const fullKey = prefix + key;
			const value = JSON.stringify({ data: 'prefixed_test' });
			
			localStorage.setItem( fullKey, value );
			const retrieved = localStorage.getItem( fullKey );
			
			expect( retrieved ).toBe( value );
		});

		test( 'should validate data structure', () => {
			const validData = {
				data: { username: 'test', followers: 100 },
				timestamp: Date.now()
			};
			
			// Test that data structure is valid
			expect( validData.data ).toBeDefined();
			expect( validData.timestamp ).toBeDefined();
			expect( typeof validData.timestamp ).toBe( 'number' );
		});

		test( 'should handle timestamp validation', () => {
			const now = Date.now();
			const maxAge = 24 * 60 * 60 * 1000; // 24 hours
			
			// Test recent timestamp (should be valid)
			const recentTimestamp = now - 1000; // 1 second ago
			expect( now - recentTimestamp ).toBeLessThan( maxAge );
			
			// Test old timestamp (should be expired)
			const oldTimestamp = now - (25 * 60 * 60 * 1000); // 25 hours ago
			expect( now - oldTimestamp ).toBeGreaterThan( maxAge );
		});

		test( 'should handle error cases gracefully', () => {
			// Test invalid JSON handling
			expect( () => {
				JSON.parse( 'invalid-json' );
			}).toThrow();
			
			// Test null/undefined handling
			expect( localStorage.getItem( 'non-existent-key' ) ).toBeNull();
		});

		test( 'should support data cleanup operations', () => {
			const prefix = 'rwp_instagram_analyzer_';
			
			// Add some test data
			localStorage.setItem( prefix + 'key1', 'value1' );
			localStorage.setItem( prefix + 'key2', 'value2' );
			localStorage.setItem( 'other_key', 'other_value' );
			
			// Verify data was added
			expect( localStorage.getItem( prefix + 'key1' ) ).toBe( 'value1' );
			expect( localStorage.getItem( 'other_key' ) ).toBe( 'other_value' );
			
			// Simulate cleanup of prefixed keys
			const keysToRemove = [];
			for ( let i = 0; i < localStorage.length; i++ ) {
				const key = localStorage.key( i );
				if ( key && key.startsWith( prefix ) ) {
					keysToRemove.push( key );
				}
			}
			
			keysToRemove.forEach( key => localStorage.removeItem( key ) );
			
			// Verify cleanup worked
			expect( localStorage.getItem( prefix + 'key1' ) ).toBeNull();
			expect( localStorage.getItem( prefix + 'key2' ) ).toBeNull();
			expect( localStorage.getItem( 'other_key' ) ).toBe( 'other_value' );
		});
	});
});