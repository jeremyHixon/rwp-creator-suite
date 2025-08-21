/**
 * Jest setup file for RWP Creator Suite
 *
 * This file is run before each test file is executed.
 */

require('@testing-library/jest-dom');

// Mock WordPress globals
global.wp = {
	i18n: {
		__: ( text ) => text,
		sprintf: ( format, ...args ) => {
			return format.replace( /%s/g, () => args.shift() || '' );
		},
	},
	element: {
		createElement: ( type, props, ...children ) => ({
			type,
			props: { ...props, children },
		}),
		Fragment: ({ children }) => children,
	},
	data: {
		useSelect: jest.fn( () => ({}) ),
		useDispatch: jest.fn( () => ({}) ),
	},
	blocks: {
		registerBlockType: jest.fn(),
	},
	components: {
		Button: 'button',
		TextControl: 'input',
		SelectControl: 'select',
		CheckboxControl: 'input',
		TextareaControl: 'textarea',
		PanelBody: 'div',
		PanelRow: 'div',
	},
	blockEditor: {
		useBlockProps: jest.fn( () => ({}) ),
		BlockControls: 'div',
		InspectorControls: 'div',
	},
};

// Mock WordPress AJAX globals
global.ajaxurl = '/wp-admin/admin-ajax.php';

// Mock WordPress localized scripts
global.rwpInstagramAnalyzer = {
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
};

global.rwpCaptionWriter = {
	ajaxUrl: '/wp-admin/admin-ajax.php',
	restUrl: '/wp-json/rwp-creator-suite/v1/',
	nonce: 'test-nonce',
	isLoggedIn: false,
	currentUserId: 0,
	strings: {
		generatePrompt: 'Describe your content to generate captions',
		processing: 'Generating captions...',
		generatedCaptions: 'Generated Captions',
		loginRequired: 'Login required to save favorites and templates',
		copySuccess: 'Caption copied to clipboard!',
		saveSuccess: 'Saved to favorites!',
		templateSuccess: 'Saved as template!',
		errorGeneral: 'Something went wrong. Please try again.',
		errorDescription: 'Please enter a description for your content',
	},
	characterLimits: {
		instagram: 2200,
		tiktok: 2200,
		twitter: 280,
		linkedin: 3000,
		facebook: 63206,
	},
};

global.rwpContentRepurposer = {
	ajaxUrl: '/wp-admin/admin-ajax.php',
	restUrl: '/wp-json/rwp-creator-suite/v1/',
	nonce: 'test-nonce',
	isLoggedIn: false,
	currentUserId: 0,
	strings: {
		inputPrompt: 'Paste your long-form content to repurpose',
		processing: 'Repurposing content...',
		repurposed: 'Repurposed Content',
		loginRequired: 'Login required for unlimited usage',
		copySuccess: 'Content copied to clipboard!',
		errorGeneral: 'Something went wrong. Please try again.',
		errorContent: 'Please enter content to repurpose',
		errorPlatforms: 'Please select at least one platform',
		rateLimitExceeded: 'Rate limit exceeded. Please try again later.',
	},
	characterLimits: {
		twitter: 280,
		linkedin: 3000,
		facebook: 63206,
		instagram: 2200,
	},
};

// Mock fetch API
global.fetch = jest.fn();

// Mock localStorage
const localStorageMock = {
	getItem: jest.fn(),
	setItem: jest.fn(),
	removeItem: jest.fn(),
	clear: jest.fn(),
	length: 0,
	key: jest.fn(),
};
global.localStorage = localStorageMock;

// Mock sessionStorage
const sessionStorageMock = {
	getItem: jest.fn(),
	setItem: jest.fn(),
	removeItem: jest.fn(),
	clear: jest.fn(),
	length: 0,
	key: jest.fn(),
};
global.sessionStorage = sessionStorageMock;

// Mock File and FileReader for file upload tests
global.File = jest.fn( ( parts, filename, properties ) => ({
	name: filename,
	size: parts.reduce( ( acc, part ) => acc + part.length, 0 ),
	type: properties?.type || 'application/octet-stream',
}));

global.FileReader = jest.fn( () => ({
	readAsArrayBuffer: jest.fn(),
	readAsText: jest.fn(),
	result: null,
	onload: null,
	onerror: null,
}));

// Mock URL.createObjectURL for image handling
global.URL = {
	createObjectURL: jest.fn( () => 'mock-object-url' ),
	revokeObjectURL: jest.fn(),
};

// Mock console methods to avoid noise in tests
global.console = {
	...console,
	warn: jest.fn(),
	error: jest.fn(),
	log: jest.fn(),
};

// Clean up after each test
afterEach( () => {
	// Clear all mocks
	jest.clearAllMocks();
	
	// Reset fetch mock
	global.fetch.mockClear();
	
	// Clear localStorage and sessionStorage
	localStorageMock.getItem.mockClear();
	localStorageMock.setItem.mockClear();
	localStorageMock.removeItem.mockClear();
	localStorageMock.clear.mockClear();
	
	sessionStorageMock.getItem.mockClear();
	sessionStorageMock.setItem.mockClear();
	sessionStorageMock.removeItem.mockClear();
	sessionStorageMock.clear.mockClear();
	
	// Reset WordPress globals
	global.rwpInstagramAnalyzer.isLoggedIn = false;
	global.rwpInstagramAnalyzer.currentUserId = 0;
	global.rwpCaptionWriter.isLoggedIn = false;
	global.rwpCaptionWriter.currentUserId = 0;
	global.rwpContentRepurposer.isLoggedIn = false;
	global.rwpContentRepurposer.currentUserId = 0;
});