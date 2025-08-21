/**
 * Caption Writer Admin JavaScript
 * @param $
 */

( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		const CaptionAdmin = {
			init() {
				this.bindEvents();
				this.updateFieldVisibility();
			},

			bindEvents() {
				// Provider selection change
				$( '#rwp_creator_suite_ai_provider' ).on(
					'change',
					this.updateFieldVisibility.bind( this )
				);

				// Password field toggle
				$( '.toggle-password' ).on( 'click', this.togglePasswordField );

				// Test connection button
				$( '#rwp-test-connection' ).on(
					'click',
					this.testConnection.bind( this )
				);

				// Form submission
				$( 'form' ).on( 'submit', this.onFormSubmit.bind( this ) );
			},

			updateFieldVisibility() {
				const provider = $( '#rwp_creator_suite_ai_provider' ).val();
				const $openaiFields = $(
					'[name="rwp_creator_suite_openai_api_key"]'
				).closest( 'tr' );
				const $claudeFields = $(
					'[name="rwp_creator_suite_claude_api_key"]'
				).closest( 'tr' );
				const $modelField = $(
					'[name="rwp_creator_suite_ai_model"]'
				).closest( 'tr' );

				// Hide all provider-specific fields first
				$openaiFields.hide();
				$claudeFields.hide();

				// Show relevant fields based on provider
				if ( provider === 'openai' ) {
					$openaiFields.show();
					$modelField.show();
				} else if ( provider === 'claude' ) {
					$claudeFields.show();
					$modelField.show();
				} else if ( provider === 'mock' ) {
					$modelField.hide();
				} else {
					$modelField.show();
				}

				this.updateConnectionStatus( provider );
			},

			togglePasswordField() {
				const $button = $( this );
				const targetId = $button.data( 'target' );
				const $field = $( '#' + targetId );

				if ( $field.attr( 'type' ) === 'password' ) {
					$field.attr( 'type', 'text' );
					$button.text( 'Hide' );
				} else {
					$field.attr( 'type', 'password' );
					$button.text( 'Show' );
				}
			},

			updateConnectionStatus( provider ) {
				const $status = $( '#rwp-connection-status' );

				if ( provider === 'mock' ) {
					$status
						.removeClass( 'success error testing' )
						.html(
							'<p><span class="rwp-status-indicator connected"></span>Mock provider is ready (no API key required).</p>'
						);
				} else {
					$status
						.removeClass( 'success error testing' )
						.html(
							'<p>Click "Test Connection" to verify your ' +
								provider.toUpperCase() +
								' settings.</p>'
						);
				}
			},

			testConnection() {
				const $button = $( '#rwp-test-connection' );
				const $status = $( '#rwp-connection-status' );
				const provider = $( '#rwp_creator_suite_ai_provider' ).val();

				// Get the appropriate API key
				let apiKey = '';
				if ( provider === 'openai' ) {
					apiKey = $( '#rwp_creator_suite_openai_api_key' ).val();
				} else if ( provider === 'claude' ) {
					apiKey = $( '#rwp_creator_suite_claude_api_key' ).val();
				}

				if ( provider !== 'mock' && ! apiKey ) {
					$status
						.removeClass( 'success testing' )
						.addClass( 'error' )
						.html(
							'<p><span class="rwp-status-indicator disconnected"></span>Please enter an API key first.</p>'
						);
					return;
				}

				// Update UI for testing state
				$button
					.prop( 'disabled', true )
					.text( rwpCaptionAdmin.strings.testing );
				$status
					.removeClass( 'success error' )
					.addClass( 'testing' )
					.html(
						'<p><span class="rwp-status-indicator testing"></span>Testing connection...</p>'
					);

				// Make test request
				const testData = {
					description: 'Test caption generation',
					tone: 'casual',
					platform: 'instagram',
				};

				$.ajax( {
					url: rwpCaptionAdmin.restUrl + 'captions/generate',
					type: 'POST',
					data: JSON.stringify( testData ),
					contentType: 'application/json',
					beforeSend( xhr ) {
						xhr.setRequestHeader(
							'X-WP-Nonce',
							rwpCaptionAdmin.nonce
						);
					},
					success( response ) {
						if ( response.success ) {
							$status
								.removeClass( 'testing error' )
								.addClass( 'success' )
								.html(
									'<p><span class="rwp-status-indicator connected"></span>' +
										rwpCaptionAdmin.strings.testSuccess +
										'</p>'
								);
						} else {
							throw new Error(
								response.message || 'Unknown error'
							);
						}
					},
					error( xhr, status, error ) {
						let errorMessage = rwpCaptionAdmin.strings.testFailed;

						try {
							const response = JSON.parse( xhr.responseText );
							if ( response.message ) {
								errorMessage = response.message;
							}
						} catch ( e ) {
							// Use default error message
						}

						$status
							.removeClass( 'testing success' )
							.addClass( 'error' )
							.html(
								'<p><span class="rwp-status-indicator disconnected"></span>' +
									errorMessage +
									'</p>'
							);
					},
					complete() {
						$button
							.prop( 'disabled', false )
							.text( 'Test Connection' );
					},
				} );
			},

			onFormSubmit( e ) {
				// Validate form before submission
				const provider = $( '#rwp_creator_suite_ai_provider' ).val();

				if ( provider === 'openai' ) {
					const apiKey = $(
						'#rwp_creator_suite_openai_api_key'
					).val();
					if ( ! apiKey ) {
						alert( 'Please enter your OpenAI API key.' );
						e.preventDefault();
						return false;
					}
				} else if ( provider === 'claude' ) {
					const apiKey = $(
						'#rwp_creator_suite_claude_api_key'
					).val();
					if ( ! apiKey ) {
						alert( 'Please enter your Claude API key.' );
						e.preventDefault();
						return false;
					}
				}

				return true;
			},
		};

		// Initialize
		CaptionAdmin.init();
	} );
} )( jQuery );
