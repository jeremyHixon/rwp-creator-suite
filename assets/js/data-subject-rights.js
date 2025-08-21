/**
 * Data Subject Rights JavaScript
 *
 * Handles user interface for GDPR data subject rights requests.
 * @param $
 */

( function ( $ ) {
	'use strict';

	// Initialize when document is ready
	$( document ).ready( function () {
		initDataSubjectRights();
	} );

	/**
	 * Initialize data subject rights functionality
	 */
	function initDataSubjectRights() {
		// Handle data export request
		$( '#request-data-export' ).on( 'click', handleDataExportRequest );

		// Handle data rectification request
		$( '#submit-data-corrections' ).on( 'click', handleDataRectification );

		// Handle data erasure request
		$( '#request-data-deletion' ).on( 'click', handleDataErasureRequest );

		// Handle data portability request
		$( '#request-data-portability' ).on(
			'click',
			handleDataPortabilityRequest
		);
	}

	/**
	 * Handle data export request
	 */
	function handleDataExportRequest() {
		if ( ! window.rwpDataRights ) {
			console.error( 'RWP Data Rights: Configuration not found' );
			return;
		}

		showLoadingState( this, 'Preparing your data export...' );

		wp.apiFetch( {
			path: 'rwp-creator-suite/v1/data-export',
			method: 'GET',
		} )
			.then( function ( response ) {
				if ( response.success ) {
					showNotification( response.message, 'success' );
					updateExportStatus( 'processing', response.export_id );
				} else {
					showNotification(
						response.message || 'Failed to process export request.',
						'error'
					);
				}
			} )
			.catch( function ( error ) {
				console.error( 'Data export error:', error );
				showNotification(
					'An error occurred while processing your request.',
					'error'
				);
			} )
			.finally( function () {
				hideLoadingState(
					'#request-data-export',
					'Request Data Export'
				);
			} );
	}

	/**
	 * Handle data rectification request
	 */
	function handleDataRectification() {
		const corrections = getDataCorrections();

		if ( Object.keys( corrections ).length === 0 ) {
			showNotification(
				'Please specify what data needs to be corrected.',
				'warning'
			);
			return;
		}

		showLoadingState( this, 'Processing corrections...' );

		wp.apiFetch( {
			path: 'rwp-creator-suite/v1/data-rectification',
			method: 'POST',
			data: {
				corrections,
			},
		} )
			.then( function ( response ) {
				if ( response.success ) {
					showNotification( response.message, 'success' );
					clearCorrectionForm();
				} else {
					showNotification(
						response.message || 'Failed to process corrections.',
						'error'
					);
				}
			} )
			.catch( function ( error ) {
				console.error( 'Data rectification error:', error );
				showNotification(
					'An error occurred while processing your corrections.',
					'error'
				);
			} )
			.finally( function () {
				hideLoadingState(
					'#submit-data-corrections',
					'Submit Corrections'
				);
			} );
	}

	/**
	 * Handle data erasure request
	 */
	function handleDataErasureRequest() {
		if ( ! window.rwpDataRights ) {
			return;
		}

		const erasureScope = $( '#erasure-scope' ).val() || 'all';

		// Confirm destructive action
		const confirmMessage =
			erasureScope === 'all'
				? 'Are you sure you want to permanently delete ALL your data? This action cannot be undone.'
				: `Are you sure you want to delete your ${ erasureScope.replace(
						'_',
						' '
				  ) } data? This action cannot be undone.`;

		if ( ! confirm( confirmMessage ) ) {
			return;
		}

		showLoadingState( this, 'Processing deletion request...' );

		wp.apiFetch( {
			path: 'rwp-creator-suite/v1/data-erasure',
			method: 'POST',
			data: {
				erasure_scope: erasureScope,
			},
		} )
			.then( function ( response ) {
				if ( response.success ) {
					showNotification( response.message, 'success' );

					// If all data deleted, redirect after delay
					if ( erasureScope === 'all' ) {
						setTimeout( function () {
							window.location.href = window.location.origin;
						}, 3000 );
					}
				} else {
					showNotification(
						response.message ||
							'Failed to process deletion request.',
						'error'
					);
				}
			} )
			.catch( function ( error ) {
				console.error( 'Data erasure error:', error );
				showNotification(
					'An error occurred while processing your deletion request.',
					'error'
				);
			} )
			.finally( function () {
				hideLoadingState( '#request-data-deletion', 'Delete My Data' );
			} );
	}

	/**
	 * Handle data portability request
	 */
	function handleDataPortabilityRequest() {
		const format = $( '#portability-format' ).val() || 'json';

		showLoadingState( this, 'Generating portable export...' );

		wp.apiFetch( {
			path: `rwp-creator-suite/v1/data-portability?format=${ format }`,
			method: 'GET',
		} )
			.then( function ( response ) {
				if ( response.success ) {
					showNotification( response.message, 'success' );

					// Provide download link
					if ( response.download_url ) {
						showDownloadLink(
							response.download_url,
							format,
							response.size
						);
					}
				} else {
					showNotification(
						response.message ||
							'Failed to generate portable export.',
						'error'
					);
				}
			} )
			.catch( function ( error ) {
				console.error( 'Data portability error:', error );
				showNotification(
					'An error occurred while generating your export.',
					'error'
				);
			} )
			.finally( function () {
				hideLoadingState(
					'#request-data-portability',
					'Download My Data'
				);
			} );
	}

	/**
	 * Get data corrections from form
	 */
	function getDataCorrections() {
		const corrections = {};

		// Get preferences corrections
		const preferencesCorrections = {};
		$( '.data-correction-field[data-type="preferences"]' ).each(
			function () {
				const field = $( this ).data( 'field' );
				const value = $( this ).val();
				if ( value ) {
					preferencesCorrections[ field ] = value;
				}
			}
		);

		if ( Object.keys( preferencesCorrections ).length > 0 ) {
			corrections.preferences = preferencesCorrections;
		}

		// Get consent corrections
		const consentCorrections = {};
		$( '.consent-correction-checkbox' ).each( function () {
			const category = $( this ).data( 'category' );
			const checked = $( this ).is( ':checked' );
			consentCorrections[ category ] = checked;
		} );

		if ( Object.keys( consentCorrections ).length > 0 ) {
			corrections.consent_preferences = consentCorrections;
		}

		return corrections;
	}

	/**
	 * Clear correction form
	 */
	function clearCorrectionForm() {
		$( '.data-correction-field' ).val( '' );
		$( '.consent-correction-checkbox' ).prop( 'checked', false );
	}

	/**
	 * Show loading state on button
	 * @param button
	 * @param message
	 */
	function showLoadingState( button, message ) {
		const $button = $( button );
		$button
			.data( 'original-text', $button.text() )
			.text( message )
			.prop( 'disabled', true )
			.addClass( 'updating-message' );
	}

	/**
	 * Hide loading state on button
	 * @param buttonSelector
	 * @param defaultText
	 */
	function hideLoadingState( buttonSelector, defaultText ) {
		const $button = $( buttonSelector );
		const originalText = $button.data( 'original-text' ) || defaultText;
		$button
			.text( originalText )
			.prop( 'disabled', false )
			.removeClass( 'updating-message' );
	}

	/**
	 * Update export status display
	 * @param status
	 * @param exportId
	 */
	function updateExportStatus( status, exportId ) {
		const $statusDiv = $( '#export-status' );

		if ( $statusDiv.length === 0 ) {
			const statusHtml = `
                <div id="export-status" class="data-rights-status">
                    <h4>Export Status</h4>
                    <div class="status-content">
                        <span class="status-indicator status-${ status }"></span>
                        <span class="status-text">${ getStatusText(
							status
						) }</span>
                    </div>
                    ${
						exportId
							? `<p class="export-id">Export ID: ${ exportId }</p>`
							: ''
					}
                </div>
            `;

			$( '#request-data-export' )
				.closest( '.tool-item' )
				.after( statusHtml );
		} else {
			$statusDiv
				.find( '.status-indicator' )
				.removeClass(
					'status-pending status-processing status-completed status-failed'
				)
				.addClass( `status-${ status }` );
			$statusDiv.find( '.status-text' ).text( getStatusText( status ) );
		}
	}

	/**
	 * Get status text for export
	 * @param status
	 */
	function getStatusText( status ) {
		const statusTexts = {
			pending: 'Export request received',
			processing: 'Preparing your data...',
			completed: 'Export completed - check your email',
			failed: 'Export failed - please try again',
		};

		return statusTexts[ status ] || 'Unknown status';
	}

	/**
	 * Show download link
	 * @param downloadUrl
	 * @param format
	 * @param size
	 */
	function showDownloadLink( downloadUrl, format, size ) {
		const sizeText = size ? ` (${ formatBytes( size ) })` : '';
		const linkHtml = `
            <div class="download-link-container">
                <h4>Your Download is Ready</h4>
                <a href="${ downloadUrl }" class="button button-primary download-link" download>
                    Download ${ format.toUpperCase() } Export${ sizeText }
                </a>
                <p class="download-note">This link will expire in 24 hours for security.</p>
            </div>
        `;

		$( '#request-data-portability' )
			.closest( '.tool-item' )
			.after( linkHtml );
	}

	/**
	 * Format bytes to human readable
	 * @param bytes
	 */
	function formatBytes( bytes ) {
		if ( bytes === 0 ) {
			return '0 Bytes';
		}
		const k = 1024;
		const sizes = [ 'Bytes', 'KB', 'MB', 'GB' ];
		const i = Math.floor( Math.log( bytes ) / Math.log( k ) );
		return (
			parseFloat( ( bytes / Math.pow( k, i ) ).toFixed( 2 ) ) +
			' ' +
			sizes[ i ]
		);
	}

	/**
	 * Show notification message
	 * @param message
	 * @param type
	 */
	function showNotification( message, type ) {
		// Create notification element
		const $notification = $( `
            <div class="data-rights-notification ${ type }">
                <p>${ message }</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss</span>
                </button>
            </div>
        ` );

		// Add to page
		$( '.rwp-data-rights' ).prepend( $notification );

		// Handle dismiss
		$notification.find( '.notice-dismiss' ).on( 'click', function () {
			$notification.fadeOut();
		} );

		// Auto-dismiss after 8 seconds
		setTimeout( function () {
			$notification.fadeOut();
		}, 8000 );
	}
} )( jQuery );
