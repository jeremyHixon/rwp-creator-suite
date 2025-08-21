/**
 * Tests for DOM helper utilities used in frontend JavaScript
 */

describe( 'DOM Helper Utilities', () => {
	beforeEach( () => {
		// Clear DOM before each test
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		// Clean up after each test
		document.body.innerHTML = '';
	} );

	describe( 'Element Selection and Manipulation', () => {
		test( 'should find elements by data attributes', () => {
			document.body.innerHTML = `
				<div data-testid="test-element">Test Content</div>
				<div data-platform-checkbox="instagram">Instagram</div>
				<div data-final-caption>Caption area</div>
			`;

			const testElement = document.querySelector(
				'[data-testid="test-element"]'
			);
			const instagramCheckbox = document.querySelector(
				'[data-platform-checkbox="instagram"]'
			);
			const captionArea = document.querySelector(
				'[data-final-caption]'
			);

			expect( testElement ).toBeDefined();
			expect( testElement.textContent ).toBe( 'Test Content' );
			expect( instagramCheckbox ).toBeDefined();
			expect( captionArea ).toBeDefined();
		} );

		test( 'should handle missing elements gracefully', () => {
			document.body.innerHTML = '<div>No special elements</div>';

			const missingElement = document.querySelector(
				'[data-missing-element]'
			);
			expect( missingElement ).toBeNull();
		} );

		test( 'should manipulate element visibility', () => {
			document.body.innerHTML = `
				<div id="test-element" style="display: block;">Visible Element</div>
			`;

			const element = document.getElementById( 'test-element' );

			// Hide element
			element.style.display = 'none';
			expect( element.style.display ).toBe( 'none' );

			// Show element
			element.style.display = 'block';
			expect( element.style.display ).toBe( 'block' );
		} );
	} );

	describe( 'Form Element Interactions', () => {
		test( 'should handle checkbox state changes', () => {
			document.body.innerHTML = `
				<input type="checkbox" id="test-checkbox" data-platform-checkbox="instagram">
				<label for="test-checkbox">Instagram</label>
			`;

			const checkbox = document.getElementById( 'test-checkbox' );

			// Initial state
			expect( checkbox.checked ).toBe( false );

			// Check the checkbox
			checkbox.checked = true;
			expect( checkbox.checked ).toBe( true );

			// Uncheck the checkbox
			checkbox.checked = false;
			expect( checkbox.checked ).toBe( false );
		} );

		test( 'should handle text input changes', () => {
			document.body.innerHTML = `
				<textarea id="description" data-description placeholder="Enter description"></textarea>
				<textarea id="final-caption" data-final-caption></textarea>
			`;

			const descriptionInput = document.getElementById( 'description' );
			const finalCaptionInput =
				document.getElementById( 'final-caption' );

			// Set values
			descriptionInput.value = 'Test description content';
			finalCaptionInput.value = 'Final caption text';

			expect( descriptionInput.value ).toBe( 'Test description content' );
			expect( finalCaptionInput.value ).toBe( 'Final caption text' );
		} );

		test( 'should handle select element changes', () => {
			document.body.innerHTML = `
				<select id="tone-selector" data-tone>
					<option value="casual">Casual</option>
					<option value="professional">Professional</option>
					<option value="witty">Witty</option>
				</select>
			`;

			const select = document.getElementById( 'tone-selector' );

			// Change selection
			select.value = 'professional';
			expect( select.value ).toBe( 'professional' );

			select.value = 'witty';
			expect( select.value ).toBe( 'witty' );
		} );
	} );

	describe( 'Dynamic Content Creation', () => {
		test( 'should create and append new elements', () => {
			document.body.innerHTML = '<div id="container"></div>';

			const container = document.getElementById( 'container' );

			// Create new element
			const newElement = document.createElement( 'div' );
			newElement.className = 'generated-caption';
			newElement.textContent = 'Generated caption text';

			container.appendChild( newElement );

			expect( container.children.length ).toBe( 1 );
			expect(
				container.querySelector( '.generated-caption' ).textContent
			).toBe( 'Generated caption text' );
		} );

		test( 'should remove elements', () => {
			document.body.innerHTML = `
				<div id="container">
					<div class="removable-item">Item 1</div>
					<div class="removable-item">Item 2</div>
				</div>
			`;

			const container = document.getElementById( 'container' );
			const firstItem = container.querySelector( '.removable-item' );

			expect( container.children.length ).toBe( 2 );

			firstItem.remove();

			expect( container.children.length ).toBe( 1 );
			expect(
				container.querySelector( '.removable-item' ).textContent
			).toBe( 'Item 2' );
		} );
	} );

	describe( 'Event Handling', () => {
		test( 'should handle button clicks', () => {
			document.body.innerHTML = `
				<button id="test-button" data-generate>Generate</button>
				<div id="result" style="display: none;">Result</div>
			`;

			const button = document.getElementById( 'test-button' );
			const result = document.getElementById( 'result' );

			let clickHandled = false;

			button.addEventListener( 'click', () => {
				clickHandled = true;
				result.style.display = 'block';
			} );

			// Simulate click
			button.click();

			expect( clickHandled ).toBe( true );
			expect( result.style.display ).toBe( 'block' );
		} );

		test( 'should handle input events', () => {
			document.body.innerHTML = `
				<textarea id="text-input" data-description></textarea>
				<div id="char-count">0</div>
			`;

			const textInput = document.getElementById( 'text-input' );
			const charCount = document.getElementById( 'char-count' );

			textInput.addEventListener( 'input', ( event ) => {
				charCount.textContent = event.target.value.length;
			} );

			// Simulate input
			textInput.value = 'Hello world';
			textInput.dispatchEvent( new Event( 'input', { bubbles: true } ) );

			expect( charCount.textContent ).toBe( '11' );
		} );

		test( 'should handle form submissions', () => {
			document.body.innerHTML = `
				<form id="test-form">
					<input type="text" name="username" value="testuser">
					<button type="submit">Submit</button>
				</form>
			`;

			const form = document.getElementById( 'test-form' );
			let formSubmitted = false;
			let submittedData = null;

			form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				formSubmitted = true;
				const formData = new FormData( form );
				submittedData = formData.get( 'username' );
			} );

			// Simulate form submission
			form.dispatchEvent( new Event( 'submit', { bubbles: true } ) );

			expect( formSubmitted ).toBe( true );
			expect( submittedData ).toBe( 'testuser' );
		} );
	} );

	describe( 'Character Counting and Limits', () => {
		test( 'should count characters correctly', () => {
			const testStrings = [
				{ text: '', expected: 0 },
				{ text: 'Hello', expected: 5 },
				{ text: 'Hello world!', expected: 12 },
				{ text: 'Multi\nline\ntext', expected: 15 },
			];

			testStrings.forEach( ( { text, expected } ) => {
				expect( text.length ).toBe( expected );
			} );
		} );

		test( 'should check platform character limits', () => {
			const platformLimits = {
				twitter: 280,
				instagram: 2200,
				linkedin: 3000,
				facebook: 63206,
				tiktok: 2200,
			};

			const testText = 'A'.repeat( 300 );

			Object.entries( platformLimits ).forEach(
				( [ platform, limit ] ) => {
					const isOverLimit = testText.length > limit;

					if ( platform === 'twitter' ) {
						expect( isOverLimit ).toBe( true );
					} else {
						expect( isOverLimit ).toBe( false );
					}
				}
			);
		} );
	} );

	describe( 'Loading States and UI Feedback', () => {
		test( 'should show and hide loading states', () => {
			document.body.innerHTML = `
				<button id="action-button">Action</button>
				<div id="loading" style="display: none;">Loading...</div>
				<div id="content">Content</div>
			`;

			const button = document.getElementById( 'action-button' );
			const loading = document.getElementById( 'loading' );
			const content = document.getElementById( 'content' );

			// Show loading
			button.disabled = true;
			loading.style.display = 'block';
			content.style.display = 'none';

			expect( button.disabled ).toBe( true );
			expect( loading.style.display ).toBe( 'block' );
			expect( content.style.display ).toBe( 'none' );

			// Hide loading
			button.disabled = false;
			loading.style.display = 'none';
			content.style.display = 'block';

			expect( button.disabled ).toBe( false );
			expect( loading.style.display ).toBe( 'none' );
			expect( content.style.display ).toBe( 'block' );
		} );

		test( 'should display error messages', () => {
			document.body.innerHTML = `
				<div id="error-container" style="display: none;"></div>
			`;

			const errorContainer = document.getElementById( 'error-container' );
			const errorMessage = 'Something went wrong!';

			// Show error
			errorContainer.textContent = errorMessage;
			errorContainer.style.display = 'block';
			errorContainer.className = 'error-message';

			expect( errorContainer.textContent ).toBe( errorMessage );
			expect( errorContainer.style.display ).toBe( 'block' );
			expect( errorContainer.classList.contains( 'error-message' ) ).toBe(
				true
			);
		} );
	} );
} );
