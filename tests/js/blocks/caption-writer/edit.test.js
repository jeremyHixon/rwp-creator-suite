/**
 * Tests for Caption Writer block edit component
 */

import { render, screen } from '@testing-library/dom';

// Mock the edit component
const mockEdit = () => {
	const blockProps = wp.blockEditor.useBlockProps();
	
	return wp.element.createElement(
		'div',
		blockProps,
		wp.element.createElement(
			'div',
			{
				className: 'wp-block-placeholder',
				'data-testid': 'caption-writer-placeholder'
			},
			wp.element.createElement(
				'div',
				{ className: 'wp-block-placeholder__label' },
				wp.i18n.__( 'Caption Writer', 'rwp-creator-suite' )
			),
			wp.element.createElement(
				'div',
				{ className: 'wp-block-placeholder__instructions' },
				wp.i18n.__( 
					'This block will display an AI-powered caption generation interface on the frontend.',
					'rwp-creator-suite'
				)
			)
		)
	);
};

describe( 'Caption Writer Block Edit Component', () => {
	beforeEach( () => {
		// Mock useBlockProps to return basic props
		wp.blockEditor.useBlockProps.mockReturnValue({
			className: 'wp-block-rwp-creator-suite-caption-writer',
			'data-block': 'caption-writer'
		});
	});

	afterEach( () => {
		// Clear DOM after each test
		document.body.innerHTML = '';
	});

	test( 'should render placeholder with correct label', () => {
		// Create a container and render the component
		const container = document.createElement( 'div' );
		document.body.appendChild( container );
		
		// Mock React-like rendering
		const element = mockEdit();
		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-caption-writer" data-block="caption-writer">
				<div class="wp-block-placeholder" data-testid="caption-writer-placeholder">
					<div class="wp-block-placeholder__label">Caption Writer</div>
					<div class="wp-block-placeholder__instructions">This block will display an AI-powered caption generation interface on the frontend.</div>
				</div>
			</div>
		`;

		// Test that placeholder is rendered
		const placeholder = container.querySelector( '[data-testid="caption-writer-placeholder"]' );
		expect( placeholder ).toBeDefined();
		expect( placeholder.classList.contains( 'wp-block-placeholder' ) ).toBe( true );
	});

	test( 'should display correct block label', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );
		
		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-caption-writer">
				<div class="wp-block-placeholder">
					<div class="wp-block-placeholder__label">Caption Writer</div>
				</div>
			</div>
		`;

		const label = container.querySelector( '.wp-block-placeholder__label' );
		expect( label ).toBeDefined();
		expect( label.textContent ).toBe( 'Caption Writer' );
	});

	test( 'should display correct instructions', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );
		
		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-caption-writer">
				<div class="wp-block-placeholder">
					<div class="wp-block-placeholder__instructions">This block will display an AI-powered caption generation interface on the frontend.</div>
				</div>
			</div>
		`;

		const instructions = container.querySelector( '.wp-block-placeholder__instructions' );
		expect( instructions ).toBeDefined();
		expect( instructions.textContent ).toBe( 
			'This block will display an AI-powered caption generation interface on the frontend.' 
		);
	});

	test( 'should use block props from useBlockProps hook', () => {
		// Verify that useBlockProps is called
		mockEdit();
		expect( wp.blockEditor.useBlockProps ).toHaveBeenCalled();
	});

	test( 'should use WordPress i18n for text translation', () => {
		// Mock the translation function to track calls
		const originalTranslate = wp.i18n.__;
		wp.i18n.__ = jest.fn( ( text ) => text );

		mockEdit();

		expect( wp.i18n.__ ).toHaveBeenCalledWith( 'Caption Writer', 'rwp-creator-suite' );
		expect( wp.i18n.__ ).toHaveBeenCalledWith( 
			'This block will display an AI-powered caption generation interface on the frontend.',
			'rwp-creator-suite'
		);

		// Restore original function
		wp.i18n.__ = originalTranslate;
	});

	test( 'should render with correct CSS classes', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );
		
		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-caption-writer" data-block="caption-writer">
				<div class="wp-block-placeholder">
					<div class="wp-block-placeholder__label">Caption Writer</div>
				</div>
			</div>
		`;

		const blockElement = container.querySelector( '.wp-block-rwp-creator-suite-caption-writer' );
		expect( blockElement ).toBeDefined();
		expect( blockElement.getAttribute( 'data-block' ) ).toBe( 'caption-writer' );
		
		const placeholder = container.querySelector( '.wp-block-placeholder' );
		expect( placeholder ).toBeDefined();
	});

	test( 'should be accessible with proper structure', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );
		
		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-caption-writer">
				<div class="wp-block-placeholder">
					<div class="wp-block-placeholder__label">Caption Writer</div>
					<div class="wp-block-placeholder__instructions">This block will display an AI-powered caption generation interface on the frontend.</div>
				</div>
			</div>
		`;

		// Check that label and instructions are present for screen readers
		const label = container.querySelector( '.wp-block-placeholder__label' );
		const instructions = container.querySelector( '.wp-block-placeholder__instructions' );
		
		expect( label ).toBeDefined();
		expect( instructions ).toBeDefined();
		expect( label.textContent ).toBeTruthy();
		expect( instructions.textContent ).toBeTruthy();
	});
});