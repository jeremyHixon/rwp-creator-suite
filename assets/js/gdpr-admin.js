/**
 * GDPR Admin Interface JavaScript
 *
 * Handles the admin interface for GDPR compliance management.
 * @param $
 */

( function ( $ ) {
	'use strict';

	// Initialize when document is ready
	$( document ).ready( function () {
		initGdprAdmin();
	} );

	/**
	 * Initialize GDPR admin functionality
	 */
	function initGdprAdmin() {
		// Quick action buttons
		$( '#run-compliance-check, #initial-compliance-check' ).on(
			'click',
			handleRunComplianceCheck
		);
		$( '#export-compliance-report' ).on( 'click', handleExportReport );
		$( '#cleanup-expired-data' ).on( 'click', handleDataCleanup );
		$( '#refresh-compliance-data' ).on( 'click', handleRefreshData );

		// Auto-refresh data every 5 minutes if on compliance tab
		if ( window.location.href.includes( 'tab=compliance' ) ) {
			setInterval(
				function () {
					loadComplianceStatus();
				},
				5 * 60 * 1000
			); // 5 minutes
		}

		// Load initial data
		loadComplianceStatus();
	}

	/**
	 * Handle run compliance check
	 * @param e
	 */
	function handleRunComplianceCheck( e ) {
		e.preventDefault();

		const $button = $( this );
		const originalText = $button.text();

		console.log( 'Compliance check button clicked', $button.attr( 'id' ) );

		if ( ! window.rwpGdprAdmin ) {
			console.error( 'RWP GDPR Admin: Configuration not found' );
			alert(
				'Error: GDPR Admin configuration not found. Please check console for details.'
			);
			return;
		}

		console.log( 'rwpGdprAdmin config:', window.rwpGdprAdmin );

		// Show immediate loading state
		$button
			.text(
				rwpGdprAdmin.strings.runningComplianceCheck ||
					'Running compliance check...'
			)
			.prop( 'disabled', true )
			.addClass( 'updating-message' );

		// Make AJAX request
		console.log( 'Making AJAX request to:', ajaxurl );
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'rwp_run_compliance_check',
				nonce: rwpGdprAdmin.nonce,
			},
			success( response ) {
				console.log( 'Compliance check response:', response );
				if ( response.success ) {
					showNotification(
						rwpGdprAdmin.strings.complianceCheckComplete ||
							'Compliance check completed!',
						'success'
					);

					// Refresh the page data
					setTimeout( function () {
						location.reload();
					}, 2000 );
				} else {
					console.error( 'Compliance check failed:', response );
					showNotification(
						response.data ||
							rwpGdprAdmin.strings.error ||
							'Compliance check failed',
						'error'
					);
				}
			},
			error( xhr, status, error ) {
				console.error( 'AJAX error:', {
					xhr,
					status,
					error,
				} );
				console.error( 'Response text:', xhr.responseText );
				showNotification(
					rwpGdprAdmin.strings.error ||
						'An error occurred during compliance check',
					'error'
				);
			},
			complete() {
				// Restore button state
				$button
					.text( originalText )
					.prop( 'disabled', false )
					.removeClass( 'updating-message' );
			},
		} );
	}

	/**
	 * Handle export compliance report
	 */
	function handleExportReport() {
		const $button = $( this );
		const originalText = $button.text();

		if ( ! window.rwpGdprAdmin ) {
			console.error( 'RWP GDPR Admin: Configuration not found' );
			return;
		}

		// Show loading state
		$button
			.text( rwpGdprAdmin.strings.exportingReport )
			.prop( 'disabled', true );

		// Create a temporary form to download the report
		const $form = $( '<form>', {
			method: 'POST',
			action: ajaxurl,
		} );

		$form.append(
			$( '<input>', {
				type: 'hidden',
				name: 'action',
				value: 'rwp_export_compliance_report',
			} )
		);

		$form.append(
			$( '<input>', {
				type: 'hidden',
				name: 'nonce',
				value: rwpGdprAdmin.nonce,
			} )
		);

		// Submit form to trigger download
		$form.appendTo( 'body' ).submit().remove();

		// Restore button state
		setTimeout( function () {
			$button.text( originalText ).prop( 'disabled', false );
			showNotification( 'Report export initiated', 'success' );
		}, 1000 );
	}

	/**
	 * Handle expired data cleanup
	 */
	function handleDataCleanup() {
		const $button = $( this );
		const originalText = $button.text();

		if ( ! window.rwpGdprAdmin ) {
			console.error( 'RWP GDPR Admin: Configuration not found' );
			return;
		}

		// Confirm action
		if ( ! confirm( rwpGdprAdmin.strings.confirmDataCleanup ) ) {
			return;
		}

		// Show loading state
		$button
			.text( rwpGdprAdmin.strings.cleaningUpData )
			.prop( 'disabled', true )
			.addClass( 'updating-message' );

		// Make AJAX request
		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'rwp_cleanup_expired_data',
				nonce: rwpGdprAdmin.nonce,
			},
			success( response ) {
				if ( response.success ) {
					showNotification(
						rwpGdprAdmin.strings.dataCleanupComplete,
						'success'
					);
				} else {
					showNotification(
						response.data || rwpGdprAdmin.strings.error,
						'error'
					);
				}
			},
			error( xhr, status, error ) {
				console.error( 'Data cleanup error:', error );
				showNotification( rwpGdprAdmin.strings.error, 'error' );
			},
			complete() {
				// Restore button state
				$button
					.text( originalText )
					.prop( 'disabled', false )
					.removeClass( 'updating-message' );
			},
		} );
	}

	/**
	 * Handle refresh data
	 */
	function handleRefreshData() {
		loadComplianceStatus();
	}

	/**
	 * Load compliance status via API
	 */
	function loadComplianceStatus() {
		if ( ! window.rwpGdprAdmin ) {
			return;
		}

		wp.apiFetch( {
			path: 'rwp-creator-suite/v1/compliance-status',
			method: 'GET',
		} )
			.then( function ( response ) {
				if ( response.success ) {
					updateComplianceDisplay( response.latest_report );
				}
			} )
			.catch( function ( error ) {
				console.error( 'Failed to load compliance status:', error );
			} );
	}

	/**
	 * Update compliance display with new data
	 * @param report
	 */
	function updateComplianceDisplay( report ) {
		if ( ! report ) {
			return;
		}

		// Update compliance score
		$( '.score-number' ).text( report.compliance_score + '%' );
		$( '.score-status' ).text(
			report.status.replace( '_', ' ' ).toUpperCase()
		);

		// Update score class
		$( '.score-number' )
			.removeClass(
				'score-compliant score-warning score-critical score-minor_issues'
			)
			.addClass( 'score-' + report.status );

		// Update last check time
		if ( report.timestamp ) {
			$( '.last-check-time' ).text(
				new Date( report.timestamp ).toLocaleString()
			);
		}
	}

	/**
	 * Show admin notification
	 * @param message
	 * @param type
	 */
	function showNotification( message, type ) {
		// Create notification element
		const $notification = $( `
            <div class="notice notice-${ type } is-dismissible">
                <p>${ message }</p>
            </div>
        ` );

		// Add after page title
		$( '.wrap h1' ).after( $notification );

		// Handle WordPress dismiss functionality
		$notification.find( '.notice-dismiss' ).on( 'click', function () {
			$notification.fadeOut();
		} );

		// Auto-dismiss success messages after 5 seconds
		if ( type === 'success' ) {
			setTimeout( function () {
				$notification.fadeOut();
			}, 5000 );
		}
	}

	/**
	 * Initialize charts if Chart.js is available
	 */
	function initializeCharts() {
		if ( typeof Chart === 'undefined' ) {
			return;
		}

		// Compliance trend chart
		const trendCanvas = document.getElementById( 'compliance-trend-chart' );
		if ( trendCanvas ) {
			initComplianceTrendChart( trendCanvas );
		}

		// Consent breakdown chart
		const consentCanvas = document.getElementById(
			'consent-breakdown-chart'
		);
		if ( consentCanvas ) {
			initConsentBreakdownChart( consentCanvas );
		}
	}

	/**
	 * Initialize compliance trend chart
	 * @param canvas
	 */
	function initComplianceTrendChart( canvas ) {
		// This would be populated with actual trend data
		new Chart( canvas, {
			type: 'line',
			data: {
				labels: [
					'Day 1',
					'Day 2',
					'Day 3',
					'Day 4',
					'Day 5',
					'Day 6',
					'Day 7',
				],
				datasets: [
					{
						label: 'Compliance Score',
						data: [ 95, 96, 94, 97, 95, 98, 97 ],
						borderColor: '#3498db',
						backgroundColor: 'rgba(52, 152, 219, 0.1)',
						tension: 0.4,
					},
				],
			},
			options: {
				responsive: true,
				scales: {
					y: {
						beginAtZero: false,
						min: 80,
						max: 100,
					},
				},
			},
		} );
	}

	/**
	 * Initialize consent breakdown chart
	 * @param canvas
	 */
	function initConsentBreakdownChart( canvas ) {
		// This would be populated with actual consent data
		new Chart( canvas, {
			type: 'doughnut',
			data: {
				labels: [
					'Basic Analytics',
					'Hashtag Trends',
					'Performance Benchmarking',
					'Product Improvement',
				],
				datasets: [
					{
						data: [ 85, 65, 78, 72 ],
						backgroundColor: [
							'#3498db',
							'#2ecc71',
							'#f39c12',
							'#9b59b6',
						],
					},
				],
			},
			options: {
				responsive: true,
				plugins: {
					legend: {
						position: 'bottom',
					},
				},
			},
		} );
	}

	// Initialize charts after page load
	$( window ).on( 'load', function () {
		setTimeout( initializeCharts, 100 );
	} );
} )( jQuery );
