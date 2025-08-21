/**
 * JavaScript test data helpers for mock data generation
 */

/**
 * Helper class for generating JavaScript test data
 */
class MockDataGenerator {
	/**
	 * Generate mock Instagram analyzer data
	 * @param {Object} overrides - Optional overrides for default data
	 * @return {Object} Mock Instagram analyzer data
	 */
	static getInstagramData( overrides = {} ) {
		const defaults = {
			userId: 123,
			whitelist: [ 'user1', 'user2', 'user3' ],
			analysisResults: {
				totalFollowers: 1000,
				engagementRate: 85.5,
				authenticityScore: 92.3,
				suspiciousAccounts: 15,
				botDetection: {
					likelyBots: 8,
					suspiciousPatterns: 7,
				},
			},
			timestamp: Date.now(),
		};

		return { ...defaults, ...overrides };
	}

	/**
	 * Generate mock analytics data
	 * @param {Object} overrides - Optional overrides for default data
	 * @return {Object} Mock analytics data
	 */
	static getAnalyticsData( overrides = {} ) {
		const defaults = {
			userId: 123,
			eventType: 'page_view',
			eventData: {
				page: 'instagram-analyzer',
				timestamp: Date.now(),
				userAgent: 'Test Browser',
				ipAddress: '127.0.0.1',
			},
			sessionData: {
				sessionId: 'test_session_123',
				sessionStart: Date.now() - 3600000,
				pageViews: 5,
			},
		};

		return { ...defaults, ...overrides };
	}

	/**
	 * Generate mock user value data
	 * @param {Object} overrides - Optional overrides for default data
	 * @return {Object} Mock user value data
	 */
	static getUserValueData( overrides = {} ) {
		const defaults = {
			userId: 123,
			userTier: 'premium',
			subscriptionStatus: 'active',
			usageStats: {
				apiCallsToday: 25,
				apiCallsMonth: 750,
				featuresUsed: [ 'instagram_analyzer', 'caption_writer' ],
			},
			limits: {
				dailyApiCalls: 100,
				monthlyApiCalls: 3000,
				concurrentAnalyses: 5,
			},
			preferences: {
				emailNotifications: true,
				dataRetentionDays: 30,
				autoCleanup: true,
			},
		};

		return { ...defaults, ...overrides };
	}

	/**
	 * Generate mock account manager data
	 * @param {Object} overrides - Optional overrides for default data
	 * @return {Object} Mock account manager data
	 */
	static getAccountData( overrides = {} ) {
		const defaults = {
			userId: 123,
			accountSettings: {
				displayName: 'Test User',
				email: 'test@example.com',
				timezone: 'America/New_York',
				language: 'en_US',
			},
			privacySettings: {
				dataCollection: true,
				analyticsTracking: true,
				emailMarketing: false,
			},
			consentHistory: [
				{
					timestamp: Date.now() - 86400000,
					action: 'granted',
					type: 'analytics',
				},
				{
					timestamp: Date.now() - 172800000,
					action: 'denied',
					type: 'marketing',
				},
			],
		};

		return { ...defaults, ...overrides };
	}

	/**
	 * Generate mock API response
	 * @param {boolean} success - Whether the response indicates success
	 * @param {Object}  data    - Optional data to include in response
	 * @param {string}  message - Optional message
	 * @return {Object} Mock API response
	 */
	static getApiResponse( success = true, data = {}, message = '' ) {
		const response = {
			success,
			data,
		};

		if ( message ) {
			response.message = message;
		} else if ( ! success ) {
			response.message = 'An error occurred';
		}

		return response;
	}

	/**
	 * Generate mock error response
	 * @param {string} errorCode    - The error code
	 * @param {string} errorMessage - The error message
	 * @param {number} httpCode     - The HTTP status code
	 * @return {Object} Mock error response
	 */
	static getErrorResponse(
		errorCode = 'generic_error',
		errorMessage = 'An error occurred',
		httpCode = 400
	) {
		return {
			success: false,
			error: {
				code: errorCode,
				message: errorMessage,
			},
			httpCode,
		};
	}

	/**
	 * Generate mock validation errors
	 * @param {Object} fields - Fields with validation errors
	 * @return {Object} Mock validation error response
	 */
	static getValidationErrors( fields = {} ) {
		const defaultFields = {
			email: 'Invalid email address',
			nonce: 'Invalid security token',
		};

		const errorFields =
			Object.keys( fields ).length > 0 ? fields : defaultFields;

		return {
			success: false,
			errors: errorFields,
			message: 'Validation failed',
		};
	}

	/**
	 * Generate mock rate limit data
	 * @param {Object} overrides - Optional overrides for default data
	 * @return {Object} Mock rate limit data
	 */
	static getRateLimitData( overrides = {} ) {
		const defaults = {
			userId: 0, // Guest user
			ipAddress: '127.0.0.1',
			requestsCount: 5,
			limit: 10,
			windowStart: Date.now() - 3600000, // 1 hour ago
			windowDuration: 3600000, // 1 hour
			resetTime: Date.now() + 3600000, // 1 hour from now
		};

		return { ...defaults, ...overrides };
	}

	/**
	 * Generate mock caption writer data
	 * @param {Object} overrides - Optional overrides for default data
	 * @return {Object} Mock caption writer data
	 */
	static getCaptionData( overrides = {} ) {
		const defaults = {
			userId: 123,
			inputDescription: 'A beautiful sunset over the ocean',
			generatedCaptions: {
				instagram:
					'Chasing sunsets and dreams 🌅 #sunset #ocean #beautiful #nature',
				twitter: 'Nothing beats a perfect sunset 🌅 #sunset',
				linkedin:
					'Taking a moment to appreciate the natural beauty around us.',
				facebook:
					"What a beautiful way to end the day! There's something magical about watching the sun set over the ocean.",
			},
			characterCounts: {
				instagram: 67,
				twitter: 34,
				linkedin: 58,
				facebook: 95,
			},
			generationTime: 2.5, // seconds
			timestamp: Date.now(),
		};

		return { ...defaults, ...overrides };
	}

	/**
	 * Generate mock content repurposer data
	 * @param {Object} overrides - Optional overrides for default data
	 * @return {Object} Mock content repurposer data
	 */
	static getRepurposerData( overrides = {} ) {
		const defaults = {
			userId: 123,
			originalContent:
				'This is a long-form blog post about social media marketing strategies and best practices for content creators...',
			repurposedContent: {
				twitter:
					'Essential social media marketing tips for content creators 📱 #SocialMedia #Marketing',
				linkedin:
					'Sharing key insights on social media marketing strategies that every content creator should know...',
				facebook:
					'Want to level up your social media game? Here are proven strategies that work!',
				instagram:
					'Social media marketing made simple ✨ Swipe for tips! #ContentCreator #MarketingTips',
			},
			platformsSelected: [
				'twitter',
				'linkedin',
				'facebook',
				'instagram',
			],
			contentAnalysis: {
				originalWordCount: 500,
				readingTime: '2 min',
				keyTopics: [ 'social media', 'marketing', 'content creation' ],
			},
			processingTime: 3.2, // seconds
			timestamp: Date.now(),
		};

		return { ...defaults, ...overrides };
	}

	/**
	 * Generate mock local storage data
	 * @param {string} key       - Storage key
	 * @param {Object} data      - Data to store
	 * @param {number} timestamp - Optional timestamp
	 * @return {string} Stringified storage data
	 */
	static getStorageData( key, data, timestamp = Date.now() ) {
		return JSON.stringify( {
			data,
			timestamp,
			key,
		} );
	}

	/**
	 * Generate mock WordPress globals for testing
	 * @param {Object} overrides - Optional overrides for default globals
	 * @return {Object} Mock WordPress globals
	 */
	static getWordPressGlobals( overrides = {} ) {
		const defaults = {
			rwpInstagramAnalyzer: {
				ajaxUrl: '/wp-admin/admin-ajax.php',
				nonce: 'test-nonce',
				isLoggedIn: false,
				currentUserId: 0,
				strings: {
					uploadPrompt: 'Upload your Instagram data export ZIP file',
					processing: 'Processing...',
					analysisComplete: 'Analysis Complete',
					loginRequired: 'Login required to see full results',
				},
			},
			rwpCaptionWriter: {
				ajaxUrl: '/wp-admin/admin-ajax.php',
				restUrl: '/wp-json/rwp-creator-suite/v1/',
				nonce: 'test-nonce',
				isLoggedIn: false,
				currentUserId: 0,
				characterLimits: {
					instagram: 2200,
					tiktok: 2200,
					twitter: 280,
					linkedin: 3000,
					facebook: 63206,
				},
			},
			rwpContentRepurposer: {
				ajaxUrl: '/wp-admin/admin-ajax.php',
				restUrl: '/wp-json/rwp-creator-suite/v1/',
				nonce: 'test-nonce',
				isLoggedIn: false,
				currentUserId: 0,
				characterLimits: {
					twitter: 280,
					linkedin: 3000,
					facebook: 63206,
					instagram: 2200,
				},
			},
		};

		return { ...defaults, ...overrides };
	}

	/**
	 * Generate mock DOM elements for testing
	 * @param {string} type       - Element type
	 * @param {Object} attributes - Element attributes
	 * @param {string} content    - Element content
	 * @return {HTMLElement} Mock DOM element
	 */
	static getDOMElement( type = 'div', attributes = {}, content = '' ) {
		const element = document.createElement( type );

		Object.keys( attributes ).forEach( ( key ) => {
			element.setAttribute( key, attributes[ key ] );
		} );

		if ( content ) {
			element.textContent = content;
		}

		return element;
	}

	/**
	 * Generate mock fetch response
	 * @param {Object}  data   - Response data
	 * @param {number}  status - HTTP status code
	 * @param {boolean} ok     - Whether response is ok
	 * @return {Object} Mock fetch response
	 */
	static getFetchResponse( data = {}, status = 200, ok = true ) {
		return {
			ok,
			status,
			json: () => Promise.resolve( data ),
			text: () => Promise.resolve( JSON.stringify( data ) ),
			headers: new Map(),
		};
	}
}

// Export for use in tests
if ( typeof module !== 'undefined' && module.exports ) {
	module.exports = MockDataGenerator;
} else if ( typeof window !== 'undefined' ) {
	window.MockDataGenerator = MockDataGenerator;
}
