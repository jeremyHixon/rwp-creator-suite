/**
 * Analytics Dashboard JavaScript
 * 
 * Handles real-time data updates, chart rendering, and interactive features
 * for the Phase 2 analytics dashboard.
 */

(function($) {
    'use strict';

    /**
     * Analytics Dashboard Class
     */
    class AnalyticsDashboard {
        constructor() {
            this.charts = {};
            this.updateInterval = null;
            this.currentTab = 'overview';
            this.requestInProgress = false;
            this.failureCount = 0;
            this.maxFailures = 3;
            this.circuitBreakerOpen = false;
            
            this.init();
        }

        /**
         * Initialize the dashboard
         */
        init() {
            this.bindEvents();
            this.initializeTabs();
            this.loadDashboardData();
            // this.startPeriodicUpdates(); // DISABLED - causing infinite AJAX requests consuming browser resources
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Tab navigation
            $('.rwp-nav-tabs .nav-tab').on('click', (e) => {
                e.preventDefault();
                this.switchTab($(e.target).attr('href').substring(1));
            });

            // Refresh button
            $('#refresh-dashboard').on('click', () => {
                this.refreshDashboard();
            });

            // Export button
            $('#export-analytics').on('click', () => {
                this.exportAnalytics();
            });

            // Window resize handler for responsive charts
            $(window).on('resize', () => {
                this.resizeCharts();
            });
        }

        /**
         * Initialize tab functionality
         */
        initializeTabs() {
            // Show first tab by default
            this.switchTab('overview');
        }

        /**
         * Switch between dashboard tabs
         */
        switchTab(tabId) {
            // Update navigation
            $('.rwp-nav-tabs .nav-tab').removeClass('nav-tab-active');
            $(`.rwp-nav-tabs .nav-tab[href="#${tabId}"]`).addClass('nav-tab-active');

            // Show/hide sections
            $('.rwp-dashboard-section').hide();
            $(`#${tabId}`).show();

            this.currentTab = tabId;

            // Load tab-specific data and charts
            this.loadTabData(tabId);
        }

        /**
         * Load dashboard data
         */
        async loadDashboardData() {
            try {
                const response = await this.apiRequest('analytics/summary');
                if (response.success && response.data) {
                    this.updateDashboardMetrics(response.data);
                    this.renderCharts(response.data);
                } else {
                    console.warn('Dashboard data request failed or returned no data:', response.message);
                    this.showFallbackData();
                }
            } catch (error) {
                console.error('Failed to load dashboard data:', error);
                this.showFallbackData();
            }
        }

        /**
         * Load tab-specific data
         */
        async loadTabData(tabId) {
            try {
                switch (tabId) {
                    case 'overview':
                        await this.loadOverviewData();
                        break;
                    case 'hashtags':
                        await this.loadHashtagData();
                        break;
                    case 'content':
                        await this.loadContentData();
                        break;
                    case 'ai-metrics':
                        await this.loadAIMetricsData();
                        break;
                    case 'privacy':
                        await this.loadPrivacyData();
                        break;
                }
            } catch (error) {
                console.error(`Failed to load ${tabId} tab data:`, error);
                this.showTabError(tabId);
            }
        }

        /**
         * Load overview tab data
         */
        async loadOverviewData() {
            try {
                // Load platform stats
                const platformResponse = await this.apiRequest('analytics/platforms');
                if (platformResponse.success && platformResponse.data) {
                    this.renderPlatformChart(platformResponse.data);
                } else {
                    console.warn('Platform data unavailable, showing placeholder');
                    this.showChartPlaceholder('platform-chart');
                }

                // Load usage trends
                const trendsResponse = await this.apiRequest('analytics/trends', { period: 'daily', days: 7 });
                if (trendsResponse.success && trendsResponse.data) {
                    this.renderUsageTimelineChart(trendsResponse.data);
                } else {
                    console.warn('Trends data unavailable, showing placeholder');
                    this.showChartPlaceholder('usage-timeline-chart');
                }

                // Load activity feed (always succeeds with fallback)
                this.loadActivityFeed();
            } catch (error) {
                console.error('Failed to load overview data:', error);
                throw error; // Re-throw to be caught by loadTabData
            }
        }

        /**
         * Load hashtag intelligence data
         */
        async loadHashtagData() {
            try {
                const hashtagResponse = await this.apiRequest('analytics/hashtags', { limit: 10 });
                if (hashtagResponse.success) {
                    this.renderTrendingHashtags(hashtagResponse.data);
                    this.renderHashtagTrendsChart(hashtagResponse.data);
                    this.renderPlatformHashtagsChart(hashtagResponse.data);
                }
            } catch (error) {
                console.error('Failed to load hashtag data:', error);
            }
        }

        /**
         * Load content analytics data
         */
        async loadContentData() {
            try {
                // Load template stats
                const templateResponse = await this.apiRequest('analytics/templates', { limit: 10 });
                if (templateResponse.success) {
                    this.renderTemplatePerformance(templateResponse.data);
                }

                // Load feature stats
                const featureResponse = await this.apiRequest('analytics/features');
                if (featureResponse.success) {
                    this.renderContentPatternsChart(featureResponse.data);
                    this.renderToneEffectivenessChart(featureResponse.data);
                }
            } catch (error) {
                console.error('Failed to load content data:', error);
            }
        }

        /**
         * Load AI metrics data
         */
        async loadAIMetricsData() {
            try {
                const trendsResponse = await this.apiRequest('analytics/trends', { period: 'daily', days: 7 });
                if (trendsResponse.success) {
                    this.renderAIPerformanceChart(trendsResponse.data);
                    this.renderErrorPatternsChart(trendsResponse.data);
                }
            } catch (error) {
                console.error('Failed to load AI metrics data:', error);
            }
        }

        /**
         * Load privacy center data
         */
        async loadPrivacyData() {
            try {
                const consentResponse = await this.apiRequest('analytics/consent');
                if (consentResponse.success) {
                    this.updateConsentMetrics(consentResponse.data);
                }
            } catch (error) {
                console.error('Failed to load privacy data:', error);
            }
        }

        /**
         * Update dashboard metrics
         */
        updateDashboardMetrics(data) {
            if (data.totals) {
                $('#active-creators').text(this.formatNumber(data.totals.unique_sessions));
                $('#content-generated').text(this.formatNumber(data.totals.content_generated));
            }
        }

        /**
         * Render all charts
         */
        renderCharts(data) {
            // Only render charts for the current tab to avoid performance issues
            if (this.currentTab === 'overview') {
                this.loadOverviewData();
            }
        }

        /**
         * Render platform usage chart
         */
        renderPlatformChart(data) {
            const ctx = document.getElementById('platform-chart');
            if (!ctx) return;

            // Properly destroy existing chart and clear references
            if (this.charts.platformChart) {
                this.charts.platformChart.destroy();
                this.charts.platformChart = null;
            }

            const platforms = data.map(item => this.capitalizeFirst(item.platform));
            const usage = data.map(item => parseInt(item.total_usage));
            const colors = [
                '#E1306C', // Instagram
                '#1DA1F2', // Twitter
                '#0077B5', // LinkedIn
                '#1877F2', // Facebook
                '#000000'  // TikTok
            ];

            this.charts.platformChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: platforms,
                    datasets: [{
                        data: usage,
                        backgroundColor: colors.slice(0, platforms.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed * 100) / total).toFixed(1);
                                    return `${context.label}: ${context.formattedValue} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        /**
         * Render usage timeline chart
         */
        renderUsageTimelineChart(data) {
            const ctx = document.getElementById('usage-timeline-chart');
            if (!ctx) return;

            // Properly destroy existing chart and clear references
            if (this.charts.usageTimelineChart) {
                this.charts.usageTimelineChart.destroy();
                this.charts.usageTimelineChart = null;
            }

            const dates = data.map(item => this.formatDate(item.period));
            const events = data.map(item => item.total_events);
            const sessions = data.map(item => item.unique_sessions);

            this.charts.usageTimelineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Total Events',
                        data: events,
                        borderColor: '#2271B1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Unique Sessions',
                        data: sessions,
                        borderColor: '#00A32A',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatNumber(value)
                            }
                        }
                    }
                }
            });
        }

        /**
         * Render trending hashtags list
         */
        renderTrendingHashtags(data) {
            const container = $('#trending-hashtags');
            if (!container.length) return;

            container.empty();

            if (!data || data.length === 0) {
                container.html('<p class="rwp-no-data">No trending hashtags data available.</p>');
                return;
            }

            data.slice(0, 5).forEach((hashtag, index) => {
                const growth = Math.floor(Math.random() * 200) + 50; // Simulated growth
                const item = $(`
                    <div class="rwp-trending-item">
                        <span class="rwp-trending-hashtag">#hashtag${hashtag.hashtag_id}</span>
                        <span class="rwp-trending-growth">+${growth}% usage</span>
                    </div>
                `);
                container.append(item);
            });
        }

        /**
         * Render hashtag trends chart
         */
        renderHashtagTrendsChart(data) {
            const ctx = document.getElementById('hashtag-trends-chart');
            if (!ctx) return;

            // Properly destroy existing chart and clear references
            if (this.charts.hashtagTrendsChart) {
                this.charts.hashtagTrendsChart.destroy();
                this.charts.hashtagTrendsChart = null;
            }

            // Simulated trend data
            const days = Array.from({length: 7}, (_, i) => {
                const date = new Date();
                date.setDate(date.getDate() - (6 - i));
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });

            const trendData = data.slice(0, 3).map((hashtag, index) => ({
                label: `#hashtag${hashtag.hashtag_id}`,
                data: Array.from({length: 7}, () => Math.floor(Math.random() * 100) + 20),
                borderColor: ['#2271B1', '#00A32A', '#DBA617'][index],
                backgroundColor: ['rgba(34, 113, 177, 0.1)', 'rgba(0, 163, 42, 0.1)', 'rgba(219, 166, 23, 0.1)'][index],
                tension: 0.4
            }));

            this.charts.hashtagTrendsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: days,
                    datasets: trendData
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Usage Count'
                            }
                        }
                    }
                }
            });
        }

        /**
         * Render platform hashtags chart
         */
        renderPlatformHashtagsChart(data) {
            const ctx = document.getElementById('platform-hashtags-chart');
            if (!ctx) return;

            // Properly destroy existing chart and clear references
            if (this.charts.platformHashtagsChart) {
                this.charts.platformHashtagsChart.destroy();
                this.charts.platformHashtagsChart = null;
            }

            // Group data by platform
            const platformData = this.groupDataByPlatform(data);
            const platforms = Object.keys(platformData);
            const datasets = platforms.map((platform, index) => ({
                label: this.capitalizeFirst(platform),
                data: platformData[platform],
                backgroundColor: ['#E1306C', '#1DA1F2', '#0077B5', '#1877F2', '#000000'][index % 5]
            }));

            this.charts.platformHashtagsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Top 5 Hashtags'],
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Usage Count'
                            }
                        }
                    }
                }
            });
        }

        /**
         * Render template performance
         */
        renderTemplatePerformance(data) {
            const container = $('#template-performance');
            if (!container.length) return;

            container.empty();

            if (!data || data.length === 0) {
                container.html('<p class="rwp-no-data">No template data available.</p>');
                return;
            }

            // Calculate stats
            const totalUsage = data.reduce((sum, template) => sum + template.usage_count, 0);
            const mostUsed = data[0];
            const highestCompletion = data.find(t => t.completion_status === 'completed') || data[0];
            const avgCustomizations = data.reduce((sum, t) => sum + (t.avg_customizations || 0), 0) / data.length;

            const statsHtml = `
                <div class="rwp-template-stats">
                    <div class="rwp-template-stat-card">
                        <div class="rwp-template-stat-title">Most Used Template</div>
                        <div class="rwp-template-stat-value">${mostUsed.usage_count}</div>
                        <div class="rwp-template-stat-label">Template ${mostUsed.template_id}</div>
                    </div>
                    <div class="rwp-template-stat-card">
                        <div class="rwp-template-stat-title">Total Template Usage</div>
                        <div class="rwp-template-stat-value">${this.formatNumber(totalUsage)}</div>
                        <div class="rwp-template-stat-label">All Templates</div>
                    </div>
                    <div class="rwp-template-stat-card">
                        <div class="rwp-template-stat-title">Avg Customizations</div>
                        <div class="rwp-template-stat-value">${avgCustomizations.toFixed(1)}</div>
                        <div class="rwp-template-stat-label">Per Template</div>
                    </div>
                </div>
            `;

            container.html(statsHtml);
        }

        /**
         * Render content patterns chart
         */
        renderContentPatternsChart(data) {
            const ctx = document.getElementById('content-patterns-chart');
            if (!ctx) return;

            // Properly destroy existing chart and clear references
            if (this.charts.contentPatternsChart) {
                this.charts.contentPatternsChart.destroy();
                this.charts.contentPatternsChart = null;
            }

            // Prepare data
            const features = data.map(item => this.capitalizeFirst(item.feature.replace(/['"]/g, '')));
            const usage = data.map(item => parseInt(item.usage_count));

            this.charts.contentPatternsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: features,
                    datasets: [{
                        label: 'Usage Count',
                        data: usage,
                        backgroundColor: '#2271B1',
                        borderColor: '#1d5f96',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Usage Count'
                            }
                        }
                    }
                }
            });
        }

        /**
         * Render tone effectiveness chart
         */
        renderToneEffectivenessChart(data) {
            const ctx = document.getElementById('tone-effectiveness-chart');
            if (!ctx) return;

            // Properly destroy existing chart and clear references
            if (this.charts.toneEffectivenessChart) {
                this.charts.toneEffectivenessChart.destroy();
                this.charts.toneEffectivenessChart = null;
            }

            // Simulated tone data by platform
            const tones = ['Professional', 'Casual', 'Friendly', 'Enthusiastic'];
            const platforms = ['Instagram', 'Twitter', 'LinkedIn'];
            
            const datasets = platforms.map((platform, index) => ({
                label: platform,
                data: tones.map(() => Math.floor(Math.random() * 100) + 20),
                backgroundColor: ['#E1306C', '#1DA1F2', '#0077B5'][index]
            }));

            this.charts.toneEffectivenessChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: tones,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Effectiveness Score'
                            }
                        }
                    }
                }
            });
        }

        /**
         * Render AI performance chart
         */
        renderAIPerformanceChart(data) {
            const ctx = document.getElementById('ai-performance-chart');
            if (!ctx) return;

            // Properly destroy existing chart and clear references
            if (this.charts.aiPerformanceChart) {
                this.charts.aiPerformanceChart.destroy();
                this.charts.aiPerformanceChart = null;
            }

            // Simulated performance data
            const dates = data.map(item => this.formatDate(item.period));
            const responseTimes = dates.map(() => Math.random() * 2 + 1);
            const successRates = dates.map(() => Math.random() * 5 + 95);

            this.charts.aiPerformanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Response Time (s)',
                        data: responseTimes,
                        borderColor: '#2271B1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        yAxisID: 'y'
                    }, {
                        label: 'Success Rate (%)',
                        data: successRates,
                        borderColor: '#00A32A',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Response Time (s)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Success Rate (%)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }

        /**
         * Render error patterns chart
         */
        renderErrorPatternsChart(data) {
            const ctx = document.getElementById('error-patterns-chart');
            if (!ctx) return;

            // Properly destroy existing chart and clear references
            if (this.charts.errorPatternsChart) {
                this.charts.errorPatternsChart.destroy();
                this.charts.errorPatternsChart = null;
            }

            // Simulated error data
            const errorTypes = ['Rate Limit', 'Network Timeout', 'API Error', 'Parse Error'];
            const errorCounts = errorTypes.map(() => Math.floor(Math.random() * 20) + 1);

            this.charts.errorPatternsChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: errorTypes,
                    datasets: [{
                        data: errorCounts,
                        backgroundColor: ['#DB4848', '#DBA617', '#8A2BE2', '#FF6347'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        /**
         * Load activity feed
         */
        loadActivityFeed() {
            const container = $('#activity-feed');
            if (!container.length) return;

            // Simulated activity data
            const activities = [
                { icon: 'edit', text: 'New caption generated for Instagram', time: '2 minutes ago' },
                { icon: 'tag', text: 'Hashtag #contentcreator trending up', time: '5 minutes ago' },
                { icon: 'share', text: 'Content repurposed for LinkedIn', time: '8 minutes ago' },
                { icon: 'analytics', text: 'Template "Product Launch" used', time: '12 minutes ago' },
                { icon: 'groups', text: 'New creator joined the community', time: '15 minutes ago' }
            ];

            container.empty();

            activities.forEach(activity => {
                const item = $(`
                    <div class="rwp-activity-item">
                        <div class="rwp-activity-icon">
                            <span class="dashicons dashicons-${activity.icon}"></span>
                        </div>
                        <div class="rwp-activity-content">
                            <p class="rwp-activity-text">${activity.text}</p>
                            <span class="rwp-activity-time">${activity.time}</span>
                        </div>
                    </div>
                `);
                container.append(item);
            });
        }

        /**
         * Update consent metrics
         */
        updateConsentMetrics(data) {
            // Metrics are already rendered in PHP, but we could update them here if needed
            console.log('Consent data loaded:', data);
        }

        /**
         * Start periodic updates
         */
        startPeriodicUpdates() {
            // Update every 30 seconds
            this.updateInterval = setInterval(() => {
                this.loadDashboardData();
            }, 30000);
        }

        /**
         * Stop periodic updates
         */
        stopPeriodicUpdates() {
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
                this.updateInterval = null;
            }
        }

        /**
         * Refresh dashboard data
         */
        async refreshDashboard() {
            const button = $('#refresh-dashboard');
            const originalText = button.html();
            
            button.prop('disabled', true)
                  .html('<span class="dashicons dashicons-update rwp-spinning"></span> Refreshing...');

            try {
                // Force refresh via AJAX
                await $.post(rwpAnalyticsDashboard.ajaxUrl, {
                    action: 'rwp_get_dashboard_metrics',
                    nonce: rwpAnalyticsDashboard.nonce
                });

                await this.loadDashboardData();
                this.showSuccess(rwpAnalyticsDashboard.strings.refresh_success);
            } catch (error) {
                console.error('Failed to refresh dashboard:', error);
                this.showError('Failed to refresh dashboard data.');
            } finally {
                button.prop('disabled', false).html(originalText);
            }
        }

        /**
         * Export analytics data
         */
        async exportAnalytics() {
            const button = $('#export-analytics');
            const originalText = button.html();
            
            button.prop('disabled', true)
                  .html('<span class="dashicons dashicons-download"></span> Exporting...');

            try {
                // Create download link
                const url = `${rwpAnalyticsDashboard.ajaxUrl}?action=rwp_export_analytics&nonce=${rwpAnalyticsDashboard.nonce}`;
                const link = document.createElement('a');
                link.href = url;
                link.download = `analytics-report-${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                this.showSuccess(rwpAnalyticsDashboard.strings.export_success);
            } catch (error) {
                console.error('Failed to export analytics:', error);
                this.showError('Failed to export analytics data.');
            } finally {
                button.prop('disabled', false).html(originalText);
            }
        }

        /**
         * Resize charts for responsive design
         */
        resizeCharts() {
            Object.values(this.charts).forEach(chart => {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        }

        /**
         * Make API request with circuit breaker pattern
         */
        async apiRequest(endpoint, params = {}) {
            // Check circuit breaker
            if (this.circuitBreakerOpen) {
                console.warn('Circuit breaker open - blocking API requests to prevent resource consumption');
                return { success: false, data: [], message: 'Circuit breaker open' };
            }

            // Prevent concurrent requests
            if (this.requestInProgress) {
                console.warn('Request already in progress - blocking duplicate request');
                return { success: false, data: [], message: 'Request in progress' };
            }

            this.requestInProgress = true;

            try {
                const result = await this.restApiRequest(endpoint, params);
                
                if (result.success) {
                    // Reset failure count on success
                    this.failureCount = 0;
                } else {
                    this.handleRequestFailure();
                }
                
                return result;
            } catch (error) {
                this.handleRequestFailure();
                console.error('API request failed:', error);
                return { success: false, data: [], message: error.message };
            } finally {
                this.requestInProgress = false;
            }
        }

        /**
         * Handle request failure and manage circuit breaker
         */
        handleRequestFailure() {
            this.failureCount++;
            console.warn(`API request failure ${this.failureCount}/${this.maxFailures}`);
            
            if (this.failureCount >= this.maxFailures) {
                this.circuitBreakerOpen = true;
                console.error('Circuit breaker opened - too many failures. API requests disabled to protect browser resources.');
                
                // Auto-reset circuit breaker after 5 minutes
                setTimeout(() => {
                    this.circuitBreakerOpen = false;
                    this.failureCount = 0;
                    console.info('Circuit breaker reset - API requests re-enabled');
                }, 300000); // 5 minutes
            }
        }

        /**
         * Make REST API request
         */
        async restApiRequest(endpoint, params = {}) {
            // Build the REST API URL
            const baseUrl = rwpAnalyticsDashboard.apiUrl;
            // Ensure baseUrl ends with slash and endpoint doesn't start with slash
            const cleanBaseUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
            const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
            const fullUrl = cleanBaseUrl + cleanEndpoint;
            const url = new URL(fullUrl);
            
            // Add query parameters for GET requests
            Object.keys(params).forEach(key => {
                if (params[key] !== undefined && params[key] !== null) {
                    url.searchParams.append(key, params[key]);
                }
            });

            console.log('Making REST API request:', url.toString()); // Debug log

            try {
                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': rwpAnalyticsDashboard.restNonce,
                        'Content-Type': 'application/json'
                    }
                });

                console.log('REST API response status:', response.status); // Debug log

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                console.log('REST API response data:', data); // Debug log

                return data;
            } catch (error) {
                console.error('REST API request failed:', error);
                throw error;
            }
        }

        /**
         * Group data by platform
         */
        groupDataByPlatform(data) {
            const grouped = {};
            data.forEach(item => {
                const platform = item.platform || 'unknown';
                if (!grouped[platform]) {
                    grouped[platform] = [];
                }
                grouped[platform].push(item.usage_count);
            });
            return grouped;
        }

        /**
         * Format number with commas
         */
        formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        /**
         * Format date for display
         */
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        /**
         * Capitalize first letter
         */
        capitalizeFirst(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        /**
         * Show success message
         */
        showSuccess(message) {
            // You could implement a notification system here
            console.log('Success:', message);
        }

        /**
         * Show error message
         */
        showError(message) {
            // You could implement a notification system here
            console.error('Error:', message);
        }

        /**
         * Show fallback data when API fails
         */
        showFallbackData() {
            console.info('Showing fallback data due to API failure');
            
            // Update metrics with placeholder data
            $('#active-creators').text('--');
            $('#content-generated').text('--');
            $('#top-platform').text('N/A');
            $('#most-used-tone').text('N/A');
            
            // Show message in activity feed
            const activityFeed = $('#activity-feed');
            if (activityFeed.length) {
                activityFeed.html(`
                    <div class="rwp-activity-error">
                        <p><strong>Analytics data unavailable</strong></p>
                        <p>Dashboard is in offline mode. Data will reload when connection is restored.</p>
                    </div>
                `);
            }
        }

        /**
         * Show tab-specific error
         */
        showTabError(tabId) {
            const tabElement = $(`#${tabId}`);
            if (tabElement.length) {
                // Find chart containers and show error messages
                tabElement.find('canvas').each(function() {
                    const canvas = $(this);
                    const container = canvas.closest('.rwp-chart-container');
                    if (container.length) {
                        container.append(`
                            <div class="rwp-chart-error">
                                <p>Unable to load chart data</p>
                            </div>
                        `);
                        canvas.hide();
                    }
                });
            }
        }

        /**
         * Show placeholder for individual charts
         */
        showChartPlaceholder(chartId) {
            const canvas = document.getElementById(chartId);
            if (canvas) {
                const container = canvas.closest('.rwp-chart-container');
                if (container) {
                    // Remove any existing error messages
                    const existingError = container.querySelector('.rwp-chart-error');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    // Add placeholder message
                    const placeholder = document.createElement('div');
                    placeholder.className = 'rwp-chart-placeholder';
                    placeholder.innerHTML = '<p>Chart data temporarily unavailable</p>';
                    container.appendChild(placeholder);
                    
                    // Hide canvas
                    canvas.style.display = 'none';
                }
            }
        }

        /**
         * Cleanup when page unloads
         */
        destroy() {
            this.stopPeriodicUpdates();
            
            // Destroy all charts
            Object.values(this.charts).forEach(chart => {
                if (chart && typeof chart.destroy === 'function') {
                    chart.destroy();
                }
            });
            
            this.charts = {};
        }
    }

    // Initialize dashboard when document is ready
    $(document).ready(function() {
        // Only initialize on analytics dashboard page
        if ($('.rwp-analytics-dashboard').length) {
            console.log('Initializing analytics dashboard with config:', rwpAnalyticsDashboard);
            window.rwpAnalyticsDashboard = new AnalyticsDashboard();
        }
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (window.rwpAnalyticsDashboard) {
            window.rwpAnalyticsDashboard.destroy();
        }
    });

    // Add spinning animation for refresh button
    const style = document.createElement('style');
    style.textContent = `
        .rwp-spinning {
            animation: rwp-spin 1s linear infinite;
        }
        @keyframes rwp-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);

})(jQuery);