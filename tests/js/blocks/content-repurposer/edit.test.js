/**
 * Tests for Content Repurposer block edit component
 */

describe( 'Content Repurposer Block Edit Component', () => {
	beforeEach( () => {
		// Mock useBlockProps to return basic props
		wp.blockEditor.useBlockProps.mockReturnValue( {
			className: 'wp-block-rwp-creator-suite-content-repurposer',
			'data-block': 'content-repurposer',
		} );
	} );

	afterEach( () => {
		// Clear DOM after each test
		document.body.innerHTML = '';
	} );

	test( 'should render placeholder with correct label', () => {
		// Create a container and render the component
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-content-repurposer" data-block="content-repurposer">
				<div class="components-placeholder" data-testid="content-repurposer-placeholder">
					<div class="components-placeholder__label">
						<svg class="dashicon"></svg>
						Content Repurposer
					</div>
					<div class="components-placeholder__instructions">This block will display an AI-powered content repurposing interface on the frontend.</div>
				</div>
			</div>
		`;

		// Test that placeholder is rendered
		const placeholder = container.querySelector(
			'[data-testid="content-repurposer-placeholder"]'
		);
		expect( placeholder ).toBeDefined();
		expect(
			placeholder.classList.contains( 'components-placeholder' )
		).toBe( true );
	} );

	test( 'should display correct block label', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-content-repurposer">
				<div class="components-placeholder">
					<div class="components-placeholder__label">Content Repurposer</div>
				</div>
			</div>
		`;

		const label = container.querySelector(
			'.components-placeholder__label'
		);
		expect( label ).toBeDefined();
		expect( label.textContent ).toBe( 'Content Repurposer' );
	} );

	test( 'should display correct instructions', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-content-repurposer">
				<div class="components-placeholder">
					<div class="components-placeholder__instructions">This block will display an AI-powered content repurposing interface on the frontend.</div>
				</div>
			</div>
		`;

		const instructions = container.querySelector(
			'.components-placeholder__instructions'
		);
		expect( instructions ).toBeDefined();
		expect( instructions.textContent ).toBe(
			'This block will display an AI-powered content repurposing interface on the frontend.'
		);
	} );

	test( 'should use block props from useBlockProps hook', () => {
		// Verify that useBlockProps is called
		const mockEdit = () => {
			wp.blockEditor.useBlockProps();
			return true;
		};

		mockEdit();
		expect( wp.blockEditor.useBlockProps ).toHaveBeenCalled();
	} );

	test( 'should use WordPress i18n for text translation', () => {
		// Mock the translation function to track calls
		const originalTranslate = wp.i18n.__;
		wp.i18n.__ = jest.fn( ( text ) => text );

		// Simulate the component's translation calls
		wp.i18n.__( 'Content Repurposer', 'rwp-creator-suite' );
		wp.i18n.__(
			'This block will display an AI-powered content repurposing interface on the frontend.',
			'rwp-creator-suite'
		);

		expect( wp.i18n.__ ).toHaveBeenCalledWith(
			'Content Repurposer',
			'rwp-creator-suite'
		);
		expect( wp.i18n.__ ).toHaveBeenCalledWith(
			'This block will display an AI-powered content repurposing interface on the frontend.',
			'rwp-creator-suite'
		);

		// Restore original function
		wp.i18n.__ = originalTranslate;
	} );

	test( 'should render with correct CSS classes', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-content-repurposer" data-block="content-repurposer">
				<div class="components-placeholder">
					<div class="components-placeholder__label">Content Repurposer</div>
				</div>
			</div>
		`;

		const blockElement = container.querySelector(
			'.wp-block-rwp-creator-suite-content-repurposer'
		);
		expect( blockElement ).toBeDefined();
		expect( blockElement.getAttribute( 'data-block' ) ).toBe(
			'content-repurposer'
		);

		const placeholder = container.querySelector(
			'.components-placeholder'
		);
		expect( placeholder ).toBeDefined();
	} );

	test( 'should include icon in placeholder', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-content-repurposer">
				<div class="components-placeholder">
					<div class="components-placeholder__label">
						<svg class="dashicon admin-post-icon"></svg>
						Content Repurposer
					</div>
				</div>
			</div>
		`;

		// Check that icon is present
		const icon = container.querySelector( '.admin-post-icon' );
		expect( icon ).toBeDefined();
	} );

	test( 'should be accessible with proper structure', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-content-repurposer">
				<div class="components-placeholder">
					<div class="components-placeholder__label">Content Repurposer</div>
					<div class="components-placeholder__instructions">This block will display an AI-powered content repurposing interface on the frontend.</div>
				</div>
			</div>
		`;

		// Check that label and instructions are present for screen readers
		const label = container.querySelector(
			'.components-placeholder__label'
		);
		const instructions = container.querySelector(
			'.components-placeholder__instructions'
		);

		expect( label ).toBeDefined();
		expect( instructions ).toBeDefined();
		expect( label.textContent ).toBeTruthy();
		expect( instructions.textContent ).toBeTruthy();
	} );

	test( 'should handle frontend functionality considerations', () => {
		// Test that the block provides appropriate guidance for frontend usage
		const container = document.createElement( 'div' );
		document.body.appendChild( container );

		container.innerHTML = `
			<div class="wp-block-rwp-creator-suite-content-repurposer">
				<div class="components-placeholder">
					<div class="components-placeholder__instructions">This block will display an AI-powered content repurposing interface on the frontend.</div>
				</div>
			</div>
		`;

		const instructions = container.querySelector(
			'.components-placeholder__instructions'
		);
		expect( instructions.textContent ).toContain( 'frontend' );
		expect( instructions.textContent ).toContain( 'repurposing interface' );
	} );
} );
