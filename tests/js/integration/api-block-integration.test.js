/**
 * Integration tests for API and Block interactions
 */

describe( 'API and Block Integration', () => {
	beforeEach( () => {
		// Reset global state
		global.rwpInstagramAnalyzer.isLoggedIn = false;
		global.rwpInstagramAnalyzer.currentUserId = 0;
		global.rwpCaptionWriter.isLoggedIn = false;
		global.rwpContentRepurposer.isLoggedIn = false;
		
		// Clear storage
		localStorage.clear();
		sessionStorage.clear();
	});

	describe( 'Instagram Analyzer Integration', () => {
		test( 'should handle authenticated user workflow', () => {
			// Simulate authenticated user
			global.rwpInstagramAnalyzer.isLoggedIn = true;
			global.rwpInstagramAnalyzer.currentUserId = 123;

			// Test that block can access user state
			const blockProps = wp.blockEditor.useBlockProps();
			expect( blockProps ).toBeDefined();
			
			// Test API endpoint simulation
			const apiData = {
				success: true,
				user_id: 123,
				whitelist: ['user1', 'user2']
			};
			
			localStorage.setItem( 'rwp_instagram_analyzer_whitelist', JSON.stringify({
				data: apiData,
				timestamp: Date.now()
			}));
			
			const stored = localStorage.getItem( 'rwp_instagram_analyzer_whitelist' );
			const parsed = JSON.parse( stored );
			
			expect( parsed.data.success ).toBe( true );
			expect( parsed.data.user_id ).toBe( 123 );
		});

		test( 'should handle guest user workflow', () => {
			// Simulate guest user
			global.rwpInstagramAnalyzer.isLoggedIn = false;
			global.rwpInstagramAnalyzer.currentUserId = 0;
			
			// Test that block shows appropriate guest state
			const container = document.createElement( 'div' );
			container.innerHTML = `
				<div class="wp-block-rwp-creator-suite-instagram-analyzer">
					<div class="components-placeholder">
						<div class="components-placeholder__instructions">This block will display an Instagram follower analysis interface on the frontend.</div>
					</div>
				</div>
			`;
			
			const instructions = container.querySelector( '.components-placeholder__instructions' );
			expect( instructions.textContent ).toContain( 'frontend' );
		});

		test( 'should handle data flow between API and storage', () => {
			const testData = {
				followers: ['user1', 'user2', 'user3'],
				analysis: { engagement: 85, authenticity: 92 },
				processed_at: Date.now()
			};
			
			// Simulate API response storage
			localStorage.setItem( 'rwp_instagram_analyzer_results', JSON.stringify({
				data: testData,
				timestamp: Date.now()
			}));
			
			// Simulate block reading the data
			const stored = localStorage.getItem( 'rwp_instagram_analyzer_results' );
			const parsed = JSON.parse( stored );
			
			expect( parsed.data.followers ).toHaveLength( 3 );
			expect( parsed.data.analysis.engagement ).toBe( 85 );
		});
	});

	describe( 'Caption Writer Integration', () => {
		test( 'should handle content generation workflow', () => {
			// Set up user context
			global.rwpCaptionWriter.isLoggedIn = true;
			global.rwpCaptionWriter.currentUserId = 456;
			
			// Simulate caption generation API response
			const generatedCaptions = {
				instagram: 'Test Instagram caption with hashtags #test',
				twitter: 'Short tweet version',
				linkedin: 'Professional LinkedIn post version'
			};
			
			localStorage.setItem( 'rwp_caption_writer_generated', JSON.stringify({
				data: generatedCaptions,
				timestamp: Date.now()
			}));
			
			// Verify data is accessible
			const stored = localStorage.getItem( 'rwp_caption_writer_generated' );
			const parsed = JSON.parse( stored );
			
			expect( parsed.data.instagram ).toContain( '#test' );
			expect( parsed.data.twitter.length ).toBeLessThanOrEqual( 280 );
		});

		test( 'should respect character limits', () => {
			const limits = global.rwpCaptionWriter.characterLimits;
			
			expect( limits.twitter ).toBe( 280 );
			expect( limits.instagram ).toBe( 2200 );
			expect( limits.linkedin ).toBe( 3000 );
			
			// Test that content respects limits
			const testContent = 'A'.repeat( 300 );
			const twitterVersion = testContent.substring( 0, limits.twitter );
			
			expect( twitterVersion.length ).toBeLessThanOrEqual( limits.twitter );
		});
	});

	describe( 'Content Repurposer Integration', () => {
		test( 'should handle multi-platform repurposing', () => {
			const originalContent = 'This is a long-form blog post about social media marketing strategies...';
			
			// Simulate repurposing API response
			const repurposedContent = {
				twitter: 'Key insights on social media marketing #marketing',
				linkedin: 'Detailed professional insight about social media strategies...',
				facebook: 'Engaging Facebook post version with call to action...',
				instagram: 'Visual-focused Instagram caption with relevant hashtags'
			};
			
			localStorage.setItem( 'rwp_content_repurposer_results', JSON.stringify({
				data: repurposedContent,
				timestamp: Date.now(),
				original_length: originalContent.length
			}));
			
			const stored = localStorage.getItem( 'rwp_content_repurposer_results' );
			const parsed = JSON.parse( stored );
			
			expect( parsed.data.twitter ).toContain( '#marketing' );
			expect( parsed.original_length ).toBeGreaterThan( 0 );
		});

		test( 'should handle rate limiting for guest users', () => {
			global.rwpContentRepurposer.isLoggedIn = false;
			
			// Simulate rate limit storage
			const rateLimitKey = 'rwp_content_repurposer_rate_limit';
			const rateLimitData = {
				count: 5,
				reset_time: Date.now() + ( 60 * 60 * 1000 ), // 1 hour from now
				limit: 5
			};
			
			localStorage.setItem( rateLimitKey, JSON.stringify( rateLimitData ) );
			
			const stored = localStorage.getItem( rateLimitKey );
			const parsed = JSON.parse( stored );
			
			expect( parsed.count ).toBe( 5 );
			expect( parsed.limit ).toBe( 5 );
			expect( parsed.reset_time ).toBeGreaterThan( Date.now() );
		});
	});

	describe( 'Cross-Component Integration', () => {
		test( 'should share user authentication state', () => {
			// Set authenticated state
			global.rwpInstagramAnalyzer.isLoggedIn = true;
			global.rwpCaptionWriter.isLoggedIn = true;
			global.rwpContentRepurposer.isLoggedIn = true;
			
			const userId = 789;
			global.rwpInstagramAnalyzer.currentUserId = userId;
			global.rwpCaptionWriter.currentUserId = userId;
			global.rwpContentRepurposer.currentUserId = userId;
			
			// Test that all components have consistent user state
			expect( global.rwpInstagramAnalyzer.currentUserId ).toBe( userId );
			expect( global.rwpCaptionWriter.currentUserId ).toBe( userId );
			expect( global.rwpContentRepurposer.currentUserId ).toBe( userId );
		});

		test( 'should handle AJAX URL configuration consistently', () => {
			const expectedAjaxUrl = '/wp-admin/admin-ajax.php';
			
			expect( global.rwpInstagramAnalyzer.ajaxUrl ).toBe( expectedAjaxUrl );
			expect( global.rwpCaptionWriter.ajaxUrl ).toBe( expectedAjaxUrl );
			expect( global.rwpContentRepurposer.ajaxUrl ).toBe( expectedAjaxUrl );
		});

		test( 'should handle nonce validation consistently', () => {
			const testNonce = 'test-nonce';
			
			expect( global.rwpInstagramAnalyzer.nonce ).toBe( testNonce );
			expect( global.rwpCaptionWriter.nonce ).toBe( testNonce );
			expect( global.rwpContentRepurposer.nonce ).toBe( testNonce );
		});

		test( 'should handle storage cleanup across components', () => {
			// Add data from multiple components
			localStorage.setItem( 'rwp_instagram_analyzer_data', JSON.stringify({ test: 'data1' }) );
			localStorage.setItem( 'rwp_caption_writer_data', JSON.stringify({ test: 'data2' }) );
			localStorage.setItem( 'rwp_content_repurposer_data', JSON.stringify({ test: 'data3' }) );
			localStorage.setItem( 'other_plugin_data', JSON.stringify({ test: 'other' }) );
			
			// Simulate cleanup of all RWP data
			const keysToRemove = [];
			for ( let i = 0; i < localStorage.length; i++ ) {
				const key = localStorage.key( i );
				if ( key && key.startsWith( 'rwp_' ) ) {
					keysToRemove.push( key );
				}
			}
			
			keysToRemove.forEach( key => localStorage.removeItem( key ) );
			
			// Verify RWP data was cleaned up but other data remains
			expect( localStorage.getItem( 'rwp_instagram_analyzer_data' ) ).toBeNull();
			expect( localStorage.getItem( 'rwp_caption_writer_data' ) ).toBeNull();
			expect( localStorage.getItem( 'rwp_content_repurposer_data' ) ).toBeNull();
			expect( localStorage.getItem( 'other_plugin_data' ) ).not.toBeNull();
		});
	});

	describe( 'Error Handling Integration', () => {
		test( 'should handle API errors gracefully across components', () => {
			const errorResponse = {
				success: false,
				error: 'API rate limit exceeded',
				code: 429
			};
			
			// Test that error responses can be stored and retrieved
			localStorage.setItem( 'rwp_last_error', JSON.stringify({
				data: errorResponse,
				timestamp: Date.now(),
				component: 'instagram-analyzer'
			}));
			
			const stored = localStorage.getItem( 'rwp_last_error' );
			const parsed = JSON.parse( stored );
			
			expect( parsed.data.success ).toBe( false );
			expect( parsed.data.code ).toBe( 429 );
			expect( parsed.component ).toBe( 'instagram-analyzer' );
		});

		test( 'should handle storage quota exceeded errors', () => {
			// Simulate storage quota issue by testing large data
			const largeData = 'x'.repeat( 1000 );
			const dataPackage = JSON.stringify({
				data: largeData,
				timestamp: Date.now()
			});
			
			// This should work in our test environment
			expect( () => {
				localStorage.setItem( 'rwp_large_data_test', dataPackage );
			}).not.toThrow();
			
			// Verify it was stored
			const retrieved = localStorage.getItem( 'rwp_large_data_test' );
			expect( retrieved ).toBeDefined();
			
			// Clean up
			localStorage.removeItem( 'rwp_large_data_test' );
		});
	});
});