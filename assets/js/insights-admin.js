/**
 * RWP Creator Suite - Insights Admin JavaScript
 *
 * Handles the analytics insights dashboard functionality including
 * data loading, visualization, and user interactions.
 * @param $
 */

( function ( $ ) {
	'use strict';

	/**
	 * Main Insights Dashboard object
	 */
	const RWPInsights = {
		// Dashboard state
		currentTab: 'overview',
		insights: null,
		charts: {},

		/**
		 * Initialize the dashboard
		 */
		init() {
			this.bindEvents();
			this.initTabs();

			if ( rwpInsights.hasConsent ) {
				this.loadInitialData();
			} else {
				this.handleConsentRequired();
			}
		},

		/**
		 * Bind event listeners
		 */
		bindEvents() {
			// Tab navigation
			$( document ).on(
				'click',
				'.rwp-tab-button',
				this.handleTabClick.bind( this )
			);

			// Action buttons
			$( '#rwp-refresh-insights' ).on(
				'click',
				this.refreshInsights.bind( this )
			);
			$( '#rwp-generate-report' ).on(
				'click',
				this.generateReport.bind( this )
			);

			// Consent handling
			$( '#rwp-enable-analytics' ).on(
				'click',
				this.enableAnalytics.bind( this )
			);

			// Trending period change
			$( '#rwp-trending-period' ).on(
				'change',
				this.loadTrendingData.bind( this )
			);

			// Notification preferences
			$( '#rwp-notification-preferences-form' ).on(
				'submit',
				this.saveNotificationPreferences.bind( this )
			);
			$( '#rwp-test-notification' ).on(
				'click',
				this.sendTestNotification.bind( this )
			);
		},

		/**
		 * Initialize tab functionality
		 */
		initTabs() {
			$( '.rwp-tab-button' ).removeClass( 'active' );
			$( '.rwp-tab-panel' ).removeClass( 'active' );

			$( '.rwp-tab-button[data-tab="' + this.currentTab + '"]' ).addClass(
				'active'
			);
			$( '#rwp-tab-' + this.currentTab ).addClass( 'active' );
		},

		/**
		 * Handle tab clicks
		 * @param e
		 */
		handleTabClick( e ) {
			e.preventDefault();

			const $button = $( e.currentTarget );
			const tab = $button.data( 'tab' );

			if ( tab === this.currentTab ) {
				return;
			}

			// Update active states
			$( '.rwp-tab-button' ).removeClass( 'active' );
			$( '.rwp-tab-panel' ).removeClass( 'active' );

			$button.addClass( 'active' );
			$( '#rwp-tab-' + tab ).addClass( 'active' );

			this.currentTab = tab;

			// Load tab-specific data
			this.loadTabData( tab );
		},

		/**
		 * Load initial dashboard data
		 */
		loadInitialData() {
			this.showLoading( '#rwp-insights-summary' );
			this.showLoading( '#rwp-user-stats' );

			// Load main insights
			this.loadUserInsights()
				.then( ( insights ) => {
					this.insights = insights;
					this.renderInsightsSummary( insights );
					this.renderUserStats( insights.summary_stats );
					this.renderPlatformChart( insights.summary_stats );
					this.loadTabData( this.currentTab );
				} )
				.catch( ( error ) => {
					this.showError(
						'#rwp-insights-summary',
						error.message || rwpInsights.strings.error
					);
				} );
		},

		/**
		 * Load data for specific tab
		 * @param tab
		 */
		loadTabData( tab ) {
			switch ( tab ) {
				case 'trending':
					this.loadTrendingData();
					break;
				case 'benchmarks':
					this.loadBenchmarkData();
					break;
				case 'recommendations':
					this.loadRecommendationsData();
					break;
				case 'achievements':
					this.loadAchievementsData();
					break;
				case 'overview':
					this.loadActivityFeed();
					break;
			}
		},

		/**
		 * Load user insights from API
		 */
		loadUserInsights() {
			return new Promise( ( resolve, reject ) => {
				wp.apiFetch( {
					path: 'rwp-creator-suite/v1/user-insights',
					method: 'GET',
				} )
					.then( ( response ) => {
						if ( response.success ) {
							resolve( response.data );
						} else {
							reject(
								new Error(
									response.message ||
										rwpInsights.strings.error
								)
							);
						}
					} )
					.catch( ( error ) => {
						reject( error );
					} );
			} );
		},

		/**
		 * Load trending data
		 */
		loadTrendingData() {
			const period = $( '#rwp-trending-period' ).val() || 'weekly';
			this.showLoading( '#rwp-trending-content' );

			wp.apiFetch( {
				path: `rwp-creator-suite/v1/trending-report?period=${ period }`,
				method: 'GET',
			} )
				.then( ( response ) => {
					if ( response.success ) {
						this.renderTrendingContent( response.data );
					} else {
						this.showError(
							'#rwp-trending-content',
							response.message || rwpInsights.strings.error
						);
					}
				} )
				.catch( ( error ) => {
					this.showError(
						'#rwp-trending-content',
						error.message || rwpInsights.strings.error
					);
				} );
		},

		/**
		 * Load benchmark data
		 */
		loadBenchmarkData() {
			this.showLoading( '#rwp-benchmark-content' );

			wp.apiFetch( {
				path: 'rwp-creator-suite/v1/performance-benchmark',
				method: 'GET',
			} )
				.then( ( response ) => {
					if ( response.success ) {
						this.renderBenchmarkContent( response.data );
					} else {
						this.showError(
							'#rwp-benchmark-content',
							response.message || rwpInsights.strings.error
						);
					}
				} )
				.catch( ( error ) => {
					this.showError(
						'#rwp-benchmark-content',
						error.message || rwpInsights.strings.error
					);
				} );
		},

		/**
		 * Load recommendations data
		 */
		loadRecommendationsData() {
			this.showLoading( '#rwp-recommendations-content' );

			wp.apiFetch( {
				path: 'rwp-creator-suite/v1/optimization-suggestions',
				method: 'GET',
			} )
				.then( ( response ) => {
					if ( response.success ) {
						this.renderRecommendationsContent( response.data );
					} else {
						this.showError(
							'#rwp-recommendations-content',
							response.message || rwpInsights.strings.error
						);
					}
				} )
				.catch( ( error ) => {
					this.showError(
						'#rwp-recommendations-content',
						error.message || rwpInsights.strings.error
					);
				} );
		},

		/**
		 * Load achievements data
		 */
		loadAchievementsData() {
			this.showLoading(
				'#rwp-achievements-content, #rwp-achievements-grid'
			);

			wp.apiFetch( {
				path: 'rwp-creator-suite/v1/achievements',
				method: 'GET',
			} )
				.then( ( response ) => {
					if ( response.success ) {
						this.renderAchievementsContent( response.data );
					} else {
						this.showError(
							'#rwp-achievements-content, #rwp-achievements-grid',
							response.message || rwpInsights.strings.error
						);
					}
				} )
				.catch( ( error ) => {
					this.showError(
						'#rwp-achievements-content, #rwp-achievements-grid',
						error.message || rwpInsights.strings.error
					);
				} );
		},

		/**
		 * Load activity feed
		 */
		loadActivityFeed() {
			// Mock activity data for demonstration
			const activities = [
				{
					icon: '📝',
					text: 'Created content for Instagram',
					time: '2 hours ago',
				},
				{
					icon: '🏆',
					text: 'Unlocked "Trend Spotter" achievement',
					time: '1 day ago',
				},
				{
					icon: '📈',
					text: 'Used 5 trending hashtags',
					time: '2 days ago',
				},
				{
					icon: '🎯',
					text: 'Optimized content for TikTok',
					time: '3 days ago',
				},
			];

			this.renderActivityFeed( activities );
		},

		/**
		 * Render insights summary
		 * @param insights
		 */
		renderInsightsSummary( insights ) {
			const summaryHtml = `
                <h3>Your Creator Overview</h3>
                <div class="rwp-summary-stats">
                    <div class="rwp-summary-item">
                        <span class="rwp-summary-number">${
							insights.summary_stats?.total_content_pieces || 0
						}</span>
                        <span class="rwp-summary-label">Content Pieces</span>
                    </div>
                    <div class="rwp-summary-item">
                        <span class="rwp-summary-number">${
							insights.summary_stats?.platforms_active || 0
						}</span>
                        <span class="rwp-summary-label">Active Platforms</span>
                    </div>
                    <div class="rwp-summary-item">
                        <span class="rwp-summary-number">${
							insights.summary_stats?.consistency_score || 0
						}%</span>
                        <span class="rwp-summary-label">Consistency</span>
                    </div>
                </div>
            `;

			$( '#rwp-insights-summary' ).html( summaryHtml );
		},

		/**
		 * Render user statistics
		 * @param stats
		 */
		renderUserStats( stats ) {
			if ( ! stats ) {
				$( '#rwp-user-stats' ).html(
					'<p>No statistics available yet.</p>'
				);
				return;
			}

			const statsHtml = `
                <div class="rwp-stat-item">
                    <span class="rwp-stat-number">${
						stats.total_content_pieces || 0
					}</span>
                    <span class="rwp-stat-label">Total Content</span>
                </div>
                <div class="rwp-stat-item">
                    <span class="rwp-stat-number">${
						stats.hashtags_used || 0
					}</span>
                    <span class="rwp-stat-label">Hashtags Used</span>
                </div>
                <div class="rwp-stat-item">
                    <span class="rwp-stat-number">${
						stats.platforms_active || 0
					}</span>
                    <span class="rwp-stat-label">Platforms</span>
                </div>
                <div class="rwp-stat-item">
                    <span class="rwp-stat-number">${
						stats.consistency_score || 0
					}%</span>
                    <span class="rwp-stat-label">Consistency</span>
                </div>
            `;

			$( '#rwp-user-stats' ).html( statsHtml );
		},

		/**
		 * Render platform distribution chart
		 * @param stats
		 */
		renderPlatformChart( stats ) {
			if ( ! stats || ! stats.favorite_platform ) {
				return;
			}

			const ctx = document.getElementById( 'rwp-platform-chart' );
			if ( ! ctx ) {
				return;
			}

			// Destroy existing chart
			if ( this.charts.platform ) {
				this.charts.platform.destroy();
			}

			// Mock platform data based on user stats
			const platforms = [ stats.favorite_platform || 'Instagram' ];
			const data = [ 100 ];

			this.charts.platform = new Chart( ctx, {
				type: 'doughnut',
				data: {
					labels: platforms,
					datasets: [
						{
							data,
							backgroundColor: [
								'#3b82f6',
								'#10b981',
								'#f59e0b',
								'#ef4444',
								'#8b5cf6',
							],
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'bottom',
						},
					},
				},
			} );
		},

		/**
		 * Render trending content
		 * @param data
		 */
		renderTrendingContent( data ) {
			let html = '';

			if ( data.trending_hashtags && data.trending_hashtags.length > 0 ) {
				html += '<div class="rwp-trending-section">';
				html += '<h4>🔥 Trending Hashtags</h4>';

				data.trending_hashtags.forEach( ( hashtag ) => {
					html += `
                        <div class="rwp-trending-item">
                            <div class="rwp-trending-title">${
								hashtag.display_name || 'Trending Hashtag'
							}</div>
                            <div class="rwp-trending-description">
                                Platform: ${ hashtag.platform || 'All' } | 
                                Growth: ${ hashtag.growth_rate || 0 }%
                            </div>
                        </div>
                    `;
				} );

				html += '</div>';
			}

			if (
				data.platform_insights &&
				Object.keys( data.platform_insights ).length > 0
			) {
				html += '<div class="rwp-trending-section">';
				html += '<h4>📊 Platform Insights</h4>';

				Object.entries( data.platform_insights ).forEach(
					( [ platform, insight ] ) => {
						html += `
                        <div class="rwp-trending-item">
                            <div class="rwp-trending-title">${
								platform.charAt( 0 ).toUpperCase() +
								platform.slice( 1 )
							}</div>
                            <div class="rwp-trending-description">${
								insight.recommendation ||
								'Keep up the great work!'
							}</div>
                        </div>
                    `;
					}
				);

				html += '</div>';
			}

			if ( ! html ) {
				html =
					'<p>No trending data available at the moment. Keep creating content to see insights!</p>';
			}

			$( '#rwp-trending-content' ).html( html );
		},

		/**
		 * Render benchmark content
		 * @param data
		 */
		renderBenchmarkContent( data ) {
			let html = '';

			if ( data.summary ) {
				Object.entries( data.summary ).forEach(
					( [ metric, benchmark ] ) => {
						if (
							typeof benchmark === 'object' &&
							benchmark.performance_level
						) {
							const scoreClass =
								'rwp-score-' +
								benchmark.performance_level.replace( '_', '-' );

							html += `
                            <div class="rwp-benchmark-item">
                                <div class="rwp-benchmark-metric">
                                    <span class="rwp-benchmark-label">${ this.formatMetricName(
										metric
									) }</span>
                                    <div class="rwp-benchmark-score">
                                        <span class="${ scoreClass }">
                                            ${
												benchmark.vs_community
													? ( benchmark.vs_community >
													  0
															? '+' +
															  benchmark.vs_community
															: benchmark.vs_community ) +
													  '%'
													: benchmark.score
											}
                                        </span>
                                    </div>
                                </div>
                                <div class="rwp-benchmark-description">${
									benchmark.description
								}</div>
                            </div>
                        `;
						}
					}
				);
			}

			if ( ! html ) {
				html =
					'<p>Benchmark data is being calculated. Check back soon!</p>';
			}

			$( '#rwp-benchmark-content' ).html( html );
		},

		/**
		 * Render recommendations content
		 * @param data
		 */
		renderRecommendationsContent( data ) {
			let html = '';

			if ( data && data.length > 0 ) {
				data.forEach( ( recommendation ) => {
					const impactClass =
						'rwp-impact-' +
						( recommendation.impact || 'medium' ).toLowerCase();

					html += `
                        <div class="rwp-recommendation">
                            <div class="rwp-recommendation-header">
                                <div class="rwp-recommendation-title">${
									recommendation.title
								}</div>
                                <div class="rwp-recommendation-meta">
                                    <span class="${ impactClass }">Impact: ${
										recommendation.impact
									}</span>
                                    <span>Effort: ${
										recommendation.effort || 'Medium'
									}</span>
                                </div>
                            </div>
                            <div class="rwp-recommendation-description">${
								recommendation.description
							}</div>
                            <div class="rwp-recommendation-actions">
                                <button class="button button-primary button-small" onclick="RWPInsights.implementRecommendation('${
									recommendation.type
								}')">
                                    Try This
                                </button>
                                <button class="button button-small" onclick="RWPInsights.dismissRecommendation('${
									recommendation.type
								}')">
                                    Not Now
                                </button>
                            </div>
                        </div>
                    `;
				} );
			} else {
				html =
					'<p>Great job! No specific recommendations at the moment. Keep up the excellent work!</p>';
			}

			$( '#rwp-recommendations-content' ).html( html );
		},

		/**
		 * Render achievements content
		 * @param data
		 */
		renderAchievementsContent( data ) {
			let html = '';

			if ( data && Object.keys( data ).length > 0 ) {
				Object.entries( data ).forEach( ( [ key, achievement ] ) => {
					const completedClass =
						achievement.level > 0 ? 'completed' : '';
					const progressPercent = achievement.next_milestone
						? Math.min(
								100,
								( achievement.progress /
									achievement.next_milestone ) *
									100
						  )
						: 100;

					html += `
                        <div class="rwp-achievement ${ completedClass }">
                            <span class="rwp-achievement-icon">${
								achievement.icon
							}</span>
                            <div class="rwp-achievement-name">${
								achievement.name
							}</div>
                            <div class="rwp-achievement-level">Level ${
								achievement.level
							}</div>
                            <div class="rwp-achievement-description">${
								achievement.description
							}</div>
                            
                            ${
								achievement.next_milestone
									? `
                                <div class="rwp-achievement-progress">
                                    <div class="rwp-progress-bar">
                                        <div class="rwp-progress-fill" style="width: ${ progressPercent }%"></div>
                                    </div>
                                    <div class="rwp-progress-text">
                                        <span>${ achievement.progress }</span>
                                        <span>${ achievement.next_milestone }</span>
                                    </div>
                                </div>
                            `
									: ''
							}
                        </div>
                    `;
				} );
			} else {
				html =
					'<p>Start creating content to unlock your first achievements!</p>';
			}

			$( '#rwp-achievements-content, #rwp-achievements-grid' ).html(
				html
			);
		},

		/**
		 * Render activity feed
		 * @param activities
		 */
		renderActivityFeed( activities ) {
			let html = '';

			activities.forEach( ( activity ) => {
				html += `
                    <div class="rwp-activity-item">
                        <div class="rwp-activity-icon">${ activity.icon }</div>
                        <div class="rwp-activity-content">
                            <div class="rwp-activity-text">${ activity.text }</div>
                            <div class="rwp-activity-time">${ activity.time }</div>
                        </div>
                    </div>
                `;
			} );

			$( '#rwp-activity-feed' ).html( html );
		},

		/**
		 * Refresh insights data
		 * @param e
		 */
		refreshInsights( e ) {
			e.preventDefault();

			const $button = $( e.currentTarget );
			$button
				.prop( 'disabled', true )
				.find( '.dashicons' )
				.addClass( 'rwp-spinning' );

			this.loadInitialData().finally( () => {
				$button
					.prop( 'disabled', false )
					.find( '.dashicons' )
					.removeClass( 'rwp-spinning' );
			} );
		},

		/**
		 * Generate monthly report
		 * @param e
		 */
		generateReport( e ) {
			e.preventDefault();

			const $button = $( e.currentTarget );
			$button.prop( 'disabled', true ).text( 'Generating...' );

			$.post( rwpInsights.ajaxUrl, {
				action: 'rwp_generate_monthly_report',
				nonce: rwpInsights.ajaxNonce,
				month: new Date().getMonth() + 1,
				year: new Date().getFullYear(),
			} )
				.done( ( response ) => {
					if ( response.success ) {
						this.showSuccess(
							'Monthly report generated! Check your email for the detailed report.'
						);
					} else {
						this.showError(
							null,
							response.data || 'Failed to generate report'
						);
					}
				} )
				.fail( () => {
					this.showError( null, 'Failed to generate report' );
				} )
				.always( () => {
					$button.prop( 'disabled', false ).text( 'Generate Report' );
				} );
		},

		/**
		 * Enable analytics consent
		 * @param e
		 */
		enableAnalytics( e ) {
			e.preventDefault();

			const $button = $( e.currentTarget );
			$button.prop( 'disabled', true ).text( 'Enabling...' );

			wp.apiFetch( {
				path: 'rwp-creator-suite/v1/consent',
				method: 'POST',
				data: { consent: true },
			} )
				.then( ( response ) => {
					if ( response.success ) {
						location.reload(); // Reload to show insights
					} else {
						this.showError( null, 'Failed to enable analytics' );
						$button
							.prop( 'disabled', false )
							.text( 'Enable Analytics & Get Insights' );
					}
				} )
				.catch( () => {
					this.showError( null, 'Failed to enable analytics' );
					$button
						.prop( 'disabled', false )
						.text( 'Enable Analytics & Get Insights' );
				} );
		},

		/**
		 * Save notification preferences
		 * @param e
		 */
		saveNotificationPreferences( e ) {
			e.preventDefault();

			const $form = $( e.currentTarget );
			const formData = new FormData( $form[ 0 ] );

			const preferences = {
				weekly_trends: formData.get( 'weekly_trends' ) === '1',
				monthly_reports: formData.get( 'monthly_reports' ) === '1',
				achievement_notifications:
					formData.get( 'achievement_notifications' ) === '1',
				opportunity_alerts:
					formData.get( 'opportunity_alerts' ) === '1',
				breaking_trends: formData.get( 'breaking_trends' ) === '1',
				email_format: formData.get( 'email_format' ) || 'html',
				notification_time:
					formData.get( 'notification_time' ) || '10:00',
			};

			wp.apiFetch( {
				path: 'rwp-creator-suite/v1/notification-preferences',
				method: 'POST',
				data: { preferences },
			} )
				.then( ( response ) => {
					if ( response.success ) {
						this.showSuccess(
							'Notification preferences saved successfully!'
						);
					} else {
						this.showError( null, 'Failed to save preferences' );
					}
				} )
				.catch( () => {
					this.showError( null, 'Failed to save preferences' );
				} );
		},

		/**
		 * Send test notification
		 * @param e
		 */
		sendTestNotification( e ) {
			e.preventDefault();

			const $button = $( e.currentTarget );
			$button.prop( 'disabled', true ).text( 'Sending...' );

			wp.apiFetch( {
				path: 'rwp-creator-suite/v1/test-notification',
				method: 'POST',
				data: { type: 'weekly_trends' },
			} )
				.then( ( response ) => {
					if ( response.success ) {
						this.showSuccess(
							'Test email sent! Check your inbox.'
						);
					} else {
						this.showError( null, 'Failed to send test email' );
					}
				} )
				.catch( () => {
					this.showError( null, 'Failed to send test email' );
				} )
				.finally( () => {
					$button.prop( 'disabled', false ).text( 'Send Test Email' );
				} );
		},

		/**
		 * Handle consent required state
		 */
		handleConsentRequired() {
			// Load notification preferences if on notifications page
			if ( $( '.rwp-notifications-page' ).length > 0 ) {
				this.loadNotificationPreferences();
			}
		},

		/**
		 * Load notification preferences
		 */
		loadNotificationPreferences() {
			wp.apiFetch( {
				path: 'rwp-creator-suite/v1/notification-preferences',
				method: 'GET',
			} )
				.then( ( response ) => {
					if ( response.success && response.preferences ) {
						this.populatePreferences( response.preferences );
					}
				} )
				.catch( ( error ) => {
					console.log( 'Could not load preferences:', error );
				} );
		},

		/**
		 * Populate preferences form
		 * @param preferences
		 */
		populatePreferences( preferences ) {
			Object.entries( preferences ).forEach( ( [ key, value ] ) => {
				const $input = $( `[name="${ key }"]` );
				if ( $input.attr( 'type' ) === 'checkbox' ) {
					$input.prop( 'checked', value );
				} else if (
					$input.is( 'select' ) ||
					$input.attr( 'type' ) === 'radio'
				) {
					$input.val( value );
				}
			} );
		},

		/**
		 * Implement recommendation
		 * @param type
		 */
		implementRecommendation( type ) {
			// Navigate to appropriate tool based on recommendation type
			switch ( type ) {
				case 'hashtag_opportunity':
					window.location.href =
						rwpInsights.strings.captionWriterUrl || '#';
					break;
				case 'platform_opportunity':
					window.location.href =
						rwpInsights.strings.repurposerUrl || '#';
					break;
				default:
					this.showSuccess(
						"Great! We'll help you implement this recommendation."
					);
			}
		},

		/**
		 * Dismiss recommendation
		 * @param type
		 */
		dismissRecommendation( type ) {
			// Could implement actual dismissal logic here
			$( `.rwp-recommendation:contains("${ type }")` ).fadeOut();
		},

		/**
		 * Utility: Format metric name
		 * @param metric
		 */
		formatMetricName( metric ) {
			return metric
				.replace( /_/g, ' ' )
				.replace( /\b\w/g, ( l ) => l.toUpperCase() );
		},

		/**
		 * Show loading state
		 * @param selector
		 */
		showLoading( selector ) {
			$( selector ).html(
				'<div class="rwp-loading"><p>' +
					rwpInsights.strings.loading +
					'</p></div>'
			);
		},

		/**
		 * Show error state
		 * @param selector
		 * @param message
		 */
		showError( selector, message ) {
			const errorHtml =
				'<div class="rwp-error"><p>' + message + '</p></div>';
			if ( selector ) {
				$( selector ).html( errorHtml );
			} else {
				// Show as notification
				$( 'body' ).prepend(
					'<div class="notice notice-error is-dismissible" style="margin: 20px;"><p>' +
						message +
						'</p></div>'
				);
			}
		},

		/**
		 * Show success state
		 * @param message
		 */
		showSuccess( message ) {
			$( 'body' ).prepend(
				'<div class="notice notice-success is-dismissible" style="margin: 20px;"><p>' +
					message +
					'</p></div>'
			);

			// Auto dismiss after 5 seconds
			setTimeout( () => {
				$( '.notice-success' ).fadeOut();
			}, 5000 );
		},
	};

	// Add spinning animation for refresh button
	$( '<style>' )
		.prop( 'type', 'text/css' )
		.html(
			`
            .rwp-spinning {
                animation: rwp-spin 1s linear infinite;
            }
            @keyframes rwp-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `
		)
		.appendTo( 'head' );

	// Initialize when document is ready
	$( document ).ready( function () {
		RWPInsights.init();
	} );

	// Expose to global scope for inline event handlers
	window.RWPInsights = RWPInsights;
} )( jQuery );
