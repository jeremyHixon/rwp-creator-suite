/**
 * Simplified Analytics Dashboard JavaScript
 *
 * Lightweight JavaScript for the refactored Phase 2 analytics dashboard
 * with focus on essential functionality and error resilience.
 * @param $
 */

( function ( $ ) {
	'use strict';

	/**
	 * Simplified Analytics Dashboard Class
	 */
	class SimpleAnalyticsDashboard {
		constructor() {
			this.currentTab = 'overview';
			this.isRefreshing = false;

			this.init();
		}

		/**
		 * Initialize the dashboard
		 */
		init() {
			this.bindEvents();
			this.initializeTabs();
			this.showWelcomeMessage();
		}

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			// Tab navigation
			$( '.rwp-nav-tabs .nav-tab' ).on( 'click', ( e ) => {
				e.preventDefault();
				const tabId = $( e.target ).attr( 'href' );
				if ( tabId && tabId.startsWith( '#' ) ) {
					this.switchTab( tabId.substring( 1 ) );
				}
			} );

			// Refresh button with debouncing
			$( '#refresh-dashboard' ).on( 'click', () => {
				if ( ! this.isRefreshing ) {
					this.refreshDashboard();
				}
			} );

			// Export button
			$( '#export-analytics' ).on( 'click', () => {
				this.exportAnalytics();
			} );

			// Handle keyboard navigation
			$( document ).on( 'keydown', ( e ) => {
				if ( e.altKey && e.key >= '1' && e.key <= '2' ) {
					const tabIndex = parseInt( e.key ) - 1;
					const tabs = [ 'overview', 'privacy' ];
					if ( tabs[ tabIndex ] ) {
						this.switchTab( tabs[ tabIndex ] );
					}
				}
			} );
		}

		/**
		 * Initialize tab functionality
		 */
		initializeTabs() {
			// Show first tab by default
			this.switchTab( 'overview' );
		}

		/**
		 * Switch between dashboard tabs
		 * @param tabId
		 */
		switchTab( tabId ) {
			if ( this.currentTab === tabId ) {
				return; // Already on this tab
			}

			try {
				// Update navigation
				$( '.rwp-nav-tabs .nav-tab' ).removeClass( 'nav-tab-active' );
				$( `.rwp-nav-tabs .nav-tab[href="#${ tabId }"]` ).addClass(
					'nav-tab-active'
				);

				// Show/hide sections with smooth transition
				$( '.rwp-dashboard-section' ).hide();
				$( `#${ tabId }` ).fadeIn( 200 );

				this.currentTab = tabId;

				// Announce tab change for screen readers
				this.announceTabChange( tabId );
			} catch ( error ) {
				console.error( 'Error switching tabs:', error );
				this.showError(
					'Failed to switch tabs. Please try refreshing the page.'
				);
			}
		}

		/**
		 * Announce tab change for accessibility
		 * @param tabId
		 */
		announceTabChange( tabId ) {
			const tabNames = {
				overview: 'Community Overview',
				privacy: 'Privacy and Transparency',
			};

			const announcement = `Switched to ${
				tabNames[ tabId ] || tabId
			} tab`;

			// Create temporary element for screen reader announcement
			const $announcement = $( '<div>', {
				'aria-live': 'polite',
				'aria-atomic': 'true',
				class: 'screen-reader-text',
				text: announcement,
			} );

			$( 'body' ).append( $announcement );
			setTimeout( () => $announcement.remove(), 1000 );
		}

		/**
		 * Refresh dashboard data
		 */
		async refreshDashboard() {
			if ( this.isRefreshing ) {
				return; // Prevent multiple simultaneous refreshes
			}

			const $button = $( '#refresh-dashboard' );
			const originalText = $button.html();

			this.isRefreshing = true;
			$button
				.prop( 'disabled', true )
				.html(
					'<span class="dashicons dashicons-update rwp-spinning"></span> Refreshing...'
				);

			try {
				// Simple AJAX request with timeout
				const response = await this.makeAjaxRequest(
					'rwp_get_dashboard_metrics',
					{},
					10000
				);

				if ( response.success ) {
					this.updateMetrics( response.data );
					this.showSuccess(
						rwpAnalyticsDashboard.strings.refresh_success ||
							'Dashboard refreshed successfully.'
					);
				} else {
					throw new Error( response.data || 'Refresh failed' );
				}
			} catch ( error ) {
				console.error( 'Failed to refresh dashboard:', error );
				this.showError(
					'Failed to refresh dashboard data. Using cached data.'
				);
			} finally {
				this.isRefreshing = false;
				$button.prop( 'disabled', false ).html( originalText );
			}
		}

		/**
		 * Update dashboard metrics
		 * @param data
		 */
		updateMetrics( data ) {
			if ( ! data || ! data.community_stats ) {
				return;
			}

			const stats = data.community_stats;

			// Safely update metrics with fallback
			this.updateElement( '#active-creators', stats.active_creators );
			this.updateElement(
				'#content-generated',
				stats.content_generated_24h
			);
			this.updateElement( '#top-platform', stats.top_platform );
			this.updateElement( '#most-used-tone', stats.most_used_tone );
		}

		/**
		 * Safely update element content
		 * @param selector
		 * @param value
		 */
		updateElement( selector, value ) {
			try {
				const $element = $( selector );
				if ( $element.length && value !== undefined ) {
					$element.text( this.formatValue( value ) );
				}
			} catch ( error ) {
				console.warn(
					`Failed to update element ${ selector }:`,
					error
				);
			}
		}

		/**
		 * Format value for display
		 * @param value
		 */
		formatValue( value ) {
			if ( value === null || value === undefined ) {
				return '--';
			}

			if ( typeof value === 'number' ) {
				return value.toLocaleString();
			}

			return String( value );
		}

		/**
		 * Export analytics data
		 */
		async exportAnalytics() {
			const $button = $( '#export-analytics' );
			const originalText = $button.html();

			$button
				.prop( 'disabled', true )
				.html(
					'<span class="dashicons dashicons-download"></span> Exporting...'
				);

			try {
				// Create download link
				const url = `${ rwpAnalyticsDashboard.ajaxUrl }?action=rwp_export_analytics&nonce=${ rwpAnalyticsDashboard.nonce }`;

				// Use a more reliable download method
				const link = document.createElement( 'a' );
				link.href = url;
				link.download = `analytics-report-${
					new Date().toISOString().split( 'T' )[ 0 ]
				}.csv`;
				link.style.display = 'none';

				document.body.appendChild( link );
				link.click();

				// Clean up after a short delay
				setTimeout( () => {
					document.body.removeChild( link );
				}, 100 );

				this.showSuccess(
					rwpAnalyticsDashboard.strings.export_success ||
						'Analytics report exported successfully.'
				);
			} catch ( error ) {
				console.error( 'Failed to export analytics:', error );
				this.showError(
					'Failed to export analytics data. Please try again.'
				);
			} finally {
				$button.prop( 'disabled', false ).html( originalText );
			}
		}

		/**
		 * Make AJAX request with proper error handling
		 * @param action
		 * @param data
		 * @param timeout
		 */
		async makeAjaxRequest( action, data = {}, timeout = 5000 ) {
			return new Promise( ( resolve, reject ) => {
				const requestData = {
					action,
					nonce: rwpAnalyticsDashboard.nonce,
					...data,
				};

				const xhr = $.ajax( {
					url: rwpAnalyticsDashboard.ajaxUrl,
					type: 'POST',
					data: requestData,
					timeout,
					dataType: 'json',
				} );

				xhr.done( resolve );
				xhr.fail( ( jqXHR, textStatus, errorThrown ) => {
					const error = new Error(
						`AJAX failed: ${ textStatus } - ${ errorThrown }`
					);
					error.status = jqXHR.status;
					error.responseText = jqXHR.responseText;
					reject( error );
				} );
			} );
		}

		/**
		 * Show success message
		 * @param message
		 */
		showSuccess( message ) {
			this.showNotification( message, 'success' );
		}

		/**
		 * Show error message
		 * @param message
		 */
		showError( message ) {
			this.showNotification( message, 'error' );
		}

		/**
		 * Show notification to user
		 * @param message
		 * @param type
		 */
		showNotification( message, type = 'info' ) {
			// Remove any existing notifications
			$( '.rwp-notification' ).remove();

			const $notification = $( `
                <div class="rwp-notification rwp-notification-${ type }">
                    <p>${ this.escapeHtml( message ) }</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            ` );

			// Add appropriate styling
			$notification.css( {
				position: 'fixed',
				top: '32px',
				right: '20px',
				zIndex: 9999,
				maxWidth: '300px',
				padding: '10px 15px',
				borderRadius: '4px',
				boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
				backgroundColor:
					type === 'error'
						? '#f8d7da'
						: type === 'success'
						? '#d4edda'
						: '#d1ecf1',
				color:
					type === 'error'
						? '#721c24'
						: type === 'success'
						? '#155724'
						: '#0c5460',
				border: `1px solid ${
					type === 'error'
						? '#f5c6cb'
						: type === 'success'
						? '#c3e6cb'
						: '#bee5eb'
				}`,
			} );

			$( 'body' ).append( $notification );

			// Auto-dismiss after 5 seconds
			setTimeout( () => {
				$notification.fadeOut( 300, () => $notification.remove() );
			}, 5000 );

			// Manual dismiss
			$notification.find( '.notice-dismiss' ).on( 'click', () => {
				$notification.fadeOut( 300, () => $notification.remove() );
			} );
		}

		/**
		 * Escape HTML to prevent XSS
		 * @param text
		 */
		escapeHtml( text ) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;',
			};
			return text.replace( /[&<>"']/g, ( m ) => map[ m ] );
		}

		/**
		 * Show welcome message
		 */
		showWelcomeMessage() {
			// Only show if this is the first time visiting the dashboard
			if ( ! localStorage.getItem( 'rwp_analytics_dashboard_visited' ) ) {
				setTimeout( () => {
					this.showNotification(
						'Welcome to the Analytics Dashboard! Use Alt+1 and Alt+2 to navigate between tabs.',
						'info'
					);
					localStorage.setItem(
						'rwp_analytics_dashboard_visited',
						'true'
					);
				}, 1000 );
			}
		}

		/**
		 * Cleanup when dashboard is unloaded
		 */
		destroy() {
			// Remove any timers or event handlers
			$( '.rwp-notification' ).remove();
			$( document ).off( 'keydown' );
		}
	}

	// Initialize dashboard when document is ready
	$( document ).ready( function () {
		// Only initialize on analytics dashboard page
		if (
			$( '.rwp-analytics-dashboard' ).length &&
			typeof rwpAnalyticsDashboard !== 'undefined'
		) {
			console.log( 'Initializing simplified analytics dashboard' );
			window.rwpSimpleAnalyticsDashboard = new SimpleAnalyticsDashboard();
		}
	} );

	// Cleanup on page unload
	$( window ).on( 'beforeunload', function () {
		if ( window.rwpSimpleAnalyticsDashboard ) {
			window.rwpSimpleAnalyticsDashboard.destroy();
		}
	} );

	// Add CSS for spinning animation and notifications
	const style = document.createElement( 'style' );
	style.textContent = `
        .rwp-spinning {
            animation: rwp-spin 1s linear infinite;
        }
        @keyframes rwp-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .screen-reader-text {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        .rwp-notification {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .rwp-notification p {
            margin: 0 !important;
            flex-grow: 1 !important;
        }
        .rwp-notification .notice-dismiss {
            background: none !important;
            border: none !important;
            cursor: pointer !important;
            padding: 5px !important;
            margin-left: 10px !important;
        }
    `;
	document.head.appendChild( style );
} )( jQuery );
