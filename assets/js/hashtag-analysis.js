/**
 * Hashtag Analysis Application
 * 
 * A comprehensive hashtag analysis dashboard with search functionality,
 * analytics visualization, and multi-platform support.
 */

(function() {
    'use strict';

    // Global configuration from WordPress
    const config = window.rwpHashtagAnalysis || {};
    const strings = config.strings || {};
    
    class HashtagAnalysisApp {
        constructor(container) {
            this.container = container;
            this.currentView = container.dataset.defaultView || 'dashboard';
            this.stateManager = new StateManager({
                storagePrefix: 'rwp_hashtag_analysis_',
                maxDataAge: 24 * 60 * 60 * 1000, // 24 hours
            });
            this.apiClient = new HashtagAnalysisAPI();
            
            // State
            this.state = {
                isLoading: false,
                currentHashtag: '',
                searchResults: {},
                analytics: {},
                dashboardData: {},
                recentSearches: [],
                error: null
            };

            this.init();
        }

        init() {
            this.setupState();
            this.render();
            this.loadDashboardData();
        }

        setupState() {
            // Initialize state with localStorage
            const savedState = this.stateManager.getItem('hashtagAnalysis') || {};
            this.state = { ...this.state, ...savedState };

            // Load recent searches
            this.state.recentSearches = this.stateManager.getItem('hashtagAnalysisSearches') || [];
        }

        saveState() {
            this.stateManager.setItem('hashtagAnalysis', {
                searchResults: this.state.searchResults,
                analytics: this.state.analytics,
                dashboardData: this.state.dashboardData
            });

            this.stateManager.setItem('hashtagAnalysisSearches', this.state.recentSearches);
        }

        render() {
            this.container.innerHTML = `
                <div class="hashtag-dashboard">
                    <div class="hashtag-dashboard__header">
                        <h2>${strings.dashboard || 'Hashtag Analysis'}</h2>
                        <div class="hashtag-dashboard__nav">
                            <button class="nav-btn ${this.currentView === 'dashboard' ? 'active' : ''}" 
                                    data-view="dashboard">
                                ${strings.dashboard || 'Dashboard'}
                            </button>
                            <button class="nav-btn ${this.currentView === 'search' ? 'active' : ''}" 
                                    data-view="search">
                                ${strings.search || 'Search'}
                            </button>
                        </div>
                    </div>
                    <div class="hashtag-dashboard__content">
                        ${this.renderCurrentView()}
                    </div>
                </div>
            `;

            this.attachEventListeners();
        }

        renderCurrentView() {
            if (this.state.isLoading) {
                return this.renderLoading();
            }

            if (this.state.error) {
                return this.renderError();
            }

            switch (this.currentView) {
                case 'dashboard':
                    return this.renderDashboard();
                case 'search':
                    return this.renderSearch();
                default:
                    return this.renderDashboard();
            }
        }

        renderLoading() {
            return `
                <div class="hashtag-dashboard__loading">
                    <div class="loading-spinner"></div>
                    <p>${strings.loading || 'Loading...'}</p>
                </div>
            `;
        }

        renderError() {
            return `
                <div class="hashtag-dashboard__error">
                    <p>${this.state.error}</p>
                    <button class="btn-retry" onclick="location.reload()">
                        Try Again
                    </button>
                </div>
            `;
        }

        renderDashboard() {
            const dashboardData = this.state.dashboardData;
            
            return `
                <div class="dashboard-view">
                    <div class="dashboard-overview">
                        <h3>Platform Status</h3>
                        <div class="platform-status">
                            ${this.renderPlatformStatus()}
                        </div>
                    </div>
                    
                    <div class="dashboard-trending">
                        <h3>Trending Hashtags</h3>
                        <div class="trending-grid">
                            ${this.renderTrendingHashtags()}
                        </div>
                    </div>
                    
                    ${this.state.recentSearches.length > 0 ? `
                        <div class="dashboard-recent">
                            <h3>Recent Searches</h3>
                            <div class="recent-searches">
                                ${this.renderRecentSearches()}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        renderSearch() {
            return `
                <div class="search-view">
                    <div class="hashtag-search">
                        <form class="hashtag-search__form">
                            <input 
                                type="text" 
                                id="hashtag-input"
                                placeholder="${strings.searchPlaceholder || 'Enter hashtag to analyze...'}"
                                value="${this.state.currentHashtag}"
                            >
                            <button type="submit">Search</button>
                        </form>
                        
                        <div class="hashtag-search__filters">
                            <select id="platform-filter">
                                <option value="all">${strings.allPlatforms || 'All Platforms'}</option>
                                <option value="tiktok">${strings.tiktok || 'TikTok'}</option>
                                <option value="instagram">${strings.instagram || 'Instagram'}</option>
                                <option value="facebook">${strings.facebook || 'Facebook'}</option>
                            </select>
                            
                            <select id="timeframe-filter">
                                <option value="7d">Last 7 Days</option>
                                <option value="30d">Last 30 Days</option>
                                <option value="1d">Last 24 Hours</option>
                            </select>
                        </div>
                        
                        <div class="hashtag-search__results">
                            ${this.renderSearchResults()}
                        </div>
                    </div>
                </div>
            `;
        }

        renderPlatformStatus() {
            const status = this.state.dashboardData.platform_status || {};
            const platforms = ['tiktok', 'instagram', 'facebook'];
            
            return platforms.map(platform => `
                <div class="platform-status-item">
                    <div class="status-icon ${status[platform] || 'unknown'}"></div>
                    <span class="platform-name">${strings[platform] || platform}</span>
                    <span class="status-text">${status[platform] || 'unknown'}</span>
                </div>
            `).join('');
        }

        renderTrendingHashtags() {
            const trending = this.state.dashboardData.trending_hashtags || {};
            
            if (Object.keys(trending).length === 0) {
                return '<p>Loading trending hashtags...</p>';
            }

            let html = '';
            Object.keys(trending).forEach(platform => {
                if (trending[platform] && trending[platform].length > 0) {
                    html += `
                        <div class="trending-platform">
                            <h4>${strings[platform] || platform}</h4>
                            <div class="hashtag-tags">
                                ${trending[platform].slice(0, 5).map(hashtag => 
                                    `<span class="hashtag-tag" data-hashtag="${hashtag}">#${hashtag}</span>`
                                ).join('')}
                            </div>
                        </div>
                    `;
                }
            });
            
            return html || '<p>No trending data available.</p>';
        }

        renderRecentSearches() {
            return this.state.recentSearches.slice(0, 5).map(search => `
                <div class="recent-search-item" data-hashtag="${search.hashtag}">
                    <span class="hashtag">#${search.hashtag}</span>
                    <span class="timestamp">${this.formatTimestamp(search.timestamp)}</span>
                </div>
            `).join('');
        }

        renderSearchResults() {
            if (!this.state.currentHashtag) {
                return '<p>Enter a hashtag to start searching.</p>';
            }

            if (Object.keys(this.state.searchResults).length === 0) {
                return '<p>No search results yet.</p>';
            }

            let html = `<div class="search-results-header">
                <h3>Results for #${this.state.currentHashtag}</h3>
            </div>`;

            // Render analytics summary
            if (Object.keys(this.state.analytics).length > 0) {
                html += '<div class="analytics-summary">' + this.renderAnalyticsSummary() + '</div>';
            }

            // Render platform results
            Object.keys(this.state.searchResults).forEach(platform => {
                const results = this.state.searchResults[platform];
                if (results && results.length > 0) {
                    html += `
                        <div class="platform-results">
                            <h4>${strings[platform] || platform} (${results.length} posts)</h4>
                            <div class="posts-grid">
                                ${results.slice(0, 6).map(post => this.renderPost(post)).join('')}
                            </div>
                        </div>
                    `;
                }
            });

            return html;
        }

        renderAnalyticsSummary() {
            const analytics = this.state.analytics;
            let html = '<div class="analytics-metrics">';

            Object.keys(analytics).forEach(platform => {
                const data = analytics[platform];
                if (data) {
                    html += `
                        <div class="metric-card">
                            <div class="metric-card__title">${strings[platform] || platform}</div>
                            <div class="metrics-row">
                                <div class="metric">
                                    <span class="metric-value">${this.formatNumber(data.total_posts || 0)}</span>
                                    <span class="metric-label">Posts</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-value">${this.formatNumber(data.total_engagement || 0)}</span>
                                    <span class="metric-label">Engagement</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-value">${data.engagement_rate || 0}%</span>
                                    <span class="metric-label">Engagement Rate</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });

            html += '</div>';
            return html;
        }

        renderPost(post) {
            return `
                <div class="post-card">
                    <div class="post-thumbnail">
                        <img src="${post.thumbnail || 'https://via.placeholder.com/300x300'}" 
                             alt="Post thumbnail" loading="lazy">
                    </div>
                    <div class="post-content">
                        <h5 class="post-title">${this.truncateText(post.title || '', 60)}</h5>
                        <div class="post-metrics">
                            <span class="metric">❤️ ${this.formatNumber(post.metrics.likes || 0)}</span>
                            <span class="metric">💬 ${this.formatNumber(post.metrics.comments || 0)}</span>
                            <span class="metric">📤 ${this.formatNumber(post.metrics.shares || 0)}</span>
                        </div>
                        ${post.url ? `<a href="${post.url}" target="_blank" rel="noopener">View Post</a>` : ''}
                    </div>
                </div>
            `;
        }

        attachEventListeners() {
            // Navigation
            this.container.querySelectorAll('.nav-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.switchView(e.target.dataset.view);
                });
            });

            // Search form
            const searchForm = this.container.querySelector('.hashtag-search__form');
            if (searchForm) {
                searchForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.performSearch();
                });
            }

            // Trending hashtag clicks
            this.container.querySelectorAll('[data-hashtag]').forEach(element => {
                element.addEventListener('click', (e) => {
                    const hashtag = e.target.dataset.hashtag;
                    this.searchHashtag(hashtag);
                });
            });

            // Input changes
            const hashtagInput = this.container.querySelector('#hashtag-input');
            if (hashtagInput) {
                hashtagInput.addEventListener('input', (e) => {
                    this.state.currentHashtag = e.target.value.replace('#', '');
                });
            }
        }

        switchView(view) {
            if (this.currentView !== view) {
                this.currentView = view;
                this.render();
            }
        }

        async loadDashboardData() {
            try {
                this.setState({ isLoading: true, error: null });
                const response = await this.apiClient.getDashboardData();
                
                if (response.success) {
                    this.setState({ 
                        dashboardData: response.data,
                        isLoading: false 
                    });
                    this.saveState();
                } else {
                    throw new Error(response.error || 'Failed to load dashboard data');
                }
            } catch (error) {
                console.error('Dashboard data error:', error);
                this.setState({ 
                    error: 'Failed to load dashboard data',
                    isLoading: false 
                });
            }
        }

        async performSearch() {
            if (!this.state.currentHashtag.trim()) {
                this.showError('Please enter a hashtag to search.');
                return;
            }

            const hashtag = this.state.currentHashtag.trim();
            const platformFilter = this.container.querySelector('#platform-filter')?.value || 'all';
            const platforms = platformFilter === 'all' ? ['tiktok', 'instagram', 'facebook'] : [platformFilter];

            try {
                this.setState({ isLoading: true, error: null });
                
                // Search for posts
                const searchResponse = await this.apiClient.searchHashtag(hashtag, platforms);
                
                // Get analytics
                const analyticsResponse = await this.apiClient.getAnalytics(hashtag, platforms);

                if (searchResponse.success) {
                    this.setState({
                        searchResults: searchResponse.data,
                        analytics: analyticsResponse.success ? analyticsResponse.data : {},
                        isLoading: false
                    });

                    // Add to recent searches
                    this.addToRecentSearches(hashtag);
                    
                    // Switch to search view if not already there
                    if (this.currentView !== 'search') {
                        this.currentView = 'search';
                    }
                    
                    this.render();
                    this.saveState();
                } else {
                    throw new Error(searchResponse.error || 'Search failed');
                }
            } catch (error) {
                console.error('Search error:', error);
                this.setState({ 
                    error: 'Search failed. Please try again.',
                    isLoading: false 
                });
            }
        }

        searchHashtag(hashtag) {
            this.state.currentHashtag = hashtag;
            this.currentView = 'search';
            this.render();
            this.performSearch();
        }

        addToRecentSearches(hashtag) {
            const search = {
                hashtag: hashtag,
                timestamp: Date.now()
            };

            // Remove existing search for the same hashtag
            this.state.recentSearches = this.state.recentSearches.filter(s => s.hashtag !== hashtag);
            
            // Add to beginning
            this.state.recentSearches.unshift(search);
            
            // Keep only last 10
            this.state.recentSearches = this.state.recentSearches.slice(0, 10);
        }

        setState(newState) {
            this.state = { ...this.state, ...newState };
            
            // Re-render if view has changed
            const content = this.container.querySelector('.hashtag-dashboard__content');
            if (content) {
                content.innerHTML = this.renderCurrentView();
                this.attachEventListeners();
            }
        }

        showError(message) {
            this.setState({ error: message, isLoading: false });
        }

        formatNumber(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        }

        formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
            return Math.floor(diff / 86400000) + 'd ago';
        }

        truncateText(text, length) {
            return text.length > length ? text.substring(0, length) + '...' : text;
        }
    }

    // API Client
    class HashtagAnalysisAPI {
        constructor() {
            this.baseUrl = config.restUrl || '/wp-json/hashtag-analysis/v1/';
            this.nonce = config.nonce || '';
        }

        async request(endpoint, options = {}) {
            const url = this.baseUrl + endpoint;
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                }
            };

            const response = await fetch(url, { ...defaultOptions, ...options });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        }

        async searchHashtag(hashtag, platforms = ['tiktok', 'instagram', 'facebook'], limit = 10) {
            return this.request('search', {
                method: 'POST',
                body: JSON.stringify({
                    hashtag: hashtag,
                    platforms: platforms,
                    limit: limit
                })
            });
        }

        async getAnalytics(hashtag, platforms = ['tiktok', 'instagram', 'facebook'], timeframe = '7d') {
            return this.request('analytics', {
                method: 'POST',
                body: JSON.stringify({
                    hashtag: hashtag,
                    platforms: platforms,
                    timeframe: timeframe
                })
            });
        }

        async getDashboardData() {
            return this.request('dashboard');
        }
    }

    // Initialize the application
    function initializeHashtagAnalysis() {
        const containers = document.querySelectorAll('#hashtag-analysis-app');
        
        containers.forEach(container => {
            if (!container.dataset.initialized) {
                new HashtagAnalysisApp(container);
                container.dataset.initialized = 'true';
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeHashtagAnalysis);
    } else {
        initializeHashtagAnalysis();
    }

    // Re-initialize if new blocks are added dynamically
    if (window.addEventListener) {
        window.addEventListener('load', initializeHashtagAnalysis);
    }

})();