/**
 * Content Repurposer Frontend Application
 * 
 * Handles AI content repurposing, platform optimization, and user interactions
 * for the Content Repurposer block.
 */

(function() {
    'use strict';

    class ContentRepurposerApp {
        constructor(container, config = {}) {
            this.container = container;
            this.config = config;
            
            // Check for required dependencies with graceful fallback
            if (typeof RWPStateManager === 'undefined') {
                console.warn('ContentRepurposer: RWPStateManager dependency not loaded, using fallback');
                this.initializeFallbackMode();
                return;
            }
            
            // Initialize state with guest persistence support
            const initialState = this.getInitialStateWithPersistence(config);
            this.state = new RWPStateManager('content_repurposer', initialState);
            
            this.elements = {};
            this.isLoggedIn = this.detectUserLoginState();
            
            this.init();
        }
        
        getInitialStateWithPersistence(config) {
            const baseState = {
                content: '',
                platforms: config.platforms || ['twitter', 'linkedin'],
                tone: config.tone || 'professional',
                repurposedContent: {},
                isProcessing: false,
                showUsageStats: config.showUsage !== '0',
                usageStats: null,
                error: null
            };
            
            // For guest users, try to load from localStorage with fallback
            if (!this.isLoggedIn) {
                try {
                    const stored = localStorage.getItem('rwp_content_repurposer_guest_state');
                    if (stored) {
                        const parsedState = JSON.parse(stored);
                        // Merge with base state, preserving config overrides
                        return { ...baseState, ...parsedState, ...config };
                    }
                } catch (error) {
                    console.warn('Failed to load guest state:', error);
                }
            }
            
            return baseState;
        }
        
        detectUserLoginState() {
            return (
                typeof rwpContentRepurposer !== 'undefined' &&
                rwpContentRepurposer.isLoggedIn
            );
        }
        
        initializeFallbackMode() {
            // Simple fallback for when dependencies aren't available
            this.container.innerHTML = `
                <div class="rwp-error-message" style="display: block;">
                    <div class="rwp-error-content">
                        Content Repurposer is not properly configured. Please refresh the page.
                    </div>
                </div>
            `;
        }
        
        init() {
            this.cacheElements();
            this.bindEvents();
            this.updateUI();
            this.loadUsageStats();
            
            // Set up state change listener
            this.state.subscribe((newState) => {
                this.updateUI();
                this.persistGuestState(newState);
            });
        }
        
        cacheElements() {
            const container = this.container;
            
            this.elements = {
                contentInput: container.querySelector('.rwp-content-input'),
                characterCount: container.querySelector('.rwp-count-current'),
                platformCheckboxes: container.querySelectorAll('.rwp-platform-checkbox'),
                toneSelect: container.querySelector('.rwp-tone-select'),
                repurposeButton: container.querySelector('.rwp-repurpose-button'),
                buttonText: container.querySelector('.rwp-button-text'),
                buttonLoading: container.querySelector('.rwp-button-loading'),
                usageStats: container.querySelector('.rwp-usage-stats'),
                resultsContainer: container.querySelector('.rwp-results-container'),
                resultsContent: container.querySelector('.rwp-results-content'),
                errorMessage: container.querySelector('.rwp-error-message'),
                errorContent: container.querySelector('.rwp-error-content')
            };
        }
        
        bindEvents() {
            // Content input events
            if (this.elements.contentInput) {
                this.elements.contentInput.addEventListener('input', (e) => {
                    const content = e.target.value;
                    this.state.setState({ content });
                    this.updateCharacterCount(content);
                });
                
                this.elements.contentInput.addEventListener('paste', () => {
                    // Update character count after paste
                    setTimeout(() => {
                        const content = this.elements.contentInput.value;
                        this.state.setState({ content });
                        this.updateCharacterCount(content);
                    }, 10);
                });
            }
            
            // Platform selection events
            this.elements.platformCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    const platforms = Array.from(this.elements.platformCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);
                    this.state.setState({ platforms });
                });
            });
            
            // Tone selection event
            if (this.elements.toneSelect) {
                this.elements.toneSelect.addEventListener('change', (e) => {
                    this.state.setState({ tone: e.target.value });
                });
            }
            
            // Repurpose button event
            if (this.elements.repurposeButton) {
                this.elements.repurposeButton.addEventListener('click', () => {
                    this.repurposeContent();
                });
            }
            
            // Copy button events (delegated)
            this.container.addEventListener('click', (e) => {
                if (e.target.classList.contains('rwp-copy-button')) {
                    this.copyToClipboard(e.target);
                }
            });
        }
        
        updateUI() {
            const state = this.state.getState();
            
            // Update form fields
            if (this.elements.contentInput && this.elements.contentInput.value !== state.content) {
                this.elements.contentInput.value = state.content;
                this.updateCharacterCount(state.content);
            }
            
            // Update platform checkboxes
            this.elements.platformCheckboxes.forEach(checkbox => {
                checkbox.checked = state.platforms.includes(checkbox.value);
            });
            
            // Update tone select
            if (this.elements.toneSelect) {
                this.elements.toneSelect.value = state.tone;
            }
            
            // Update button state
            this.updateButtonState(state.isProcessing);
            
            // Update results
            this.updateResults(state.repurposedContent);
            
            // Update error display
            this.updateError(state.error);
            
            // Update usage stats
            if (state.showUsageStats && state.usageStats) {
                this.updateUsageStats(state.usageStats);
            }
        }
        
        updateCharacterCount(content) {
            if (!this.elements.characterCount) return;
            
            const count = content.length;
            this.elements.characterCount.textContent = count.toLocaleString();
            
            // Update character count styling based on limits
            this.elements.characterCount.className = 'rwp-count-current';
            if (count > 8000) {
                this.elements.characterCount.classList.add('rwp-count-warning');
            }
            if (count > 9500) {
                this.elements.characterCount.classList.add('rwp-count-error');
            }
        }
        
        updateButtonState(isProcessing) {
            if (!this.elements.repurposeButton) return;
            
            this.elements.repurposeButton.disabled = isProcessing;
            
            if (isProcessing) {
                this.elements.repurposeButton.classList.add('rwp-loading');
            } else {
                this.elements.repurposeButton.classList.remove('rwp-loading');
            }
        }
        
        async repurposeContent() {
            const state = this.state.getState();
            
            // Validate input
            const validation = this.validateInput(state);
            if (!validation.valid) {
                this.showError(validation.message);
                return;
            }
            
            this.state.setState({ 
                isProcessing: true, 
                error: null,
                repurposedContent: {}
            });
            
            try {
                const response = await this.makeAPIRequest('/repurpose-content', {
                    method: 'POST',
                    body: JSON.stringify({
                        content: state.content,
                        platforms: state.platforms,
                        tone: state.tone
                    })
                });
                
                if (response.success) {
                    this.state.setState({ 
                        repurposedContent: response.data,
                        usageStats: response.usage,
                        isProcessing: false
                    });
                    this.showResults();
                } else {
                    throw new Error(response.message || 'Failed to repurpose content');
                }
                
            } catch (error) {
                console.error('Content repurposing error:', error);
                this.state.setState({ 
                    isProcessing: false,
                    error: error.message
                });
            }
        }
        
        validateInput(state) {
            if (!state.content || state.content.trim().length === 0) {
                return {
                    valid: false,
                    message: this.getString('errorContent')
                };
            }
            
            if (state.content.length > 10000) {
                return {
                    valid: false,
                    message: 'Content is too long. Maximum 10,000 characters allowed.'
                };
            }
            
            if (!state.platforms || state.platforms.length === 0) {
                return {
                    valid: false,
                    message: this.getString('errorPlatforms')
                };
            }
            
            return { valid: true };
        }
        
        updateResults(repurposedContent) {
            if (!this.elements.resultsContent || !repurposedContent || Object.keys(repurposedContent).length === 0) {
                if (this.elements.resultsContainer) {
                    this.elements.resultsContainer.style.display = 'none';
                }
                return;
            }
            
            let resultsHTML = '';
            
            Object.entries(repurposedContent).forEach(([platform, data]) => {
                const platformName = platform.charAt(0).toUpperCase() + platform.slice(1);
                const characterLimit = this.getCharacterLimit(platform);
                
                if (data.success && data.versions) {
                    resultsHTML += this.renderPlatformSuccess(platform, platformName, data.versions, characterLimit);
                } else {
                    resultsHTML += this.renderPlatformError(platform, platformName, data.error);
                }
            });
            
            this.elements.resultsContent.innerHTML = resultsHTML;
            this.showResults();
        }
        
        renderPlatformSuccess(platform, platformName, versions, characterLimit) {
            const versionsHTML = versions.map((version, index) => `
                <div class="rwp-content-version">
                    <div class="rwp-version-text">${this.escapeHtml(version.text)}</div>
                    <div class="rwp-version-meta">
                        <span class="rwp-character-count">${version.character_count} / ${characterLimit} characters</span>
                        <button type="button" class="rwp-copy-button" data-content="${this.escapeHtml(version.text)}">
                            Copy
                        </button>
                    </div>
                </div>
            `).join('');
            
            return `
                <div class="rwp-platform-result">
                    <div class="rwp-platform-header">
                        <span class="rwp-platform-name">${platformName}</span>
                        <span class="rwp-character-limit">Limit: ${characterLimit} chars</span>
                    </div>
                    <div class="rwp-platform-content">
                        <div class="rwp-content-versions">
                            ${versionsHTML}
                        </div>
                    </div>
                </div>
            `;
        }
        
        renderPlatformError(platform, platformName, error) {
            return `
                <div class="rwp-platform-result rwp-result-error">
                    <div class="rwp-platform-header">
                        <span class="rwp-platform-name">${platformName}</span>
                        <span class="rwp-error-indicator">Error</span>
                    </div>
                    <div class="rwp-platform-content">
                        <div class="rwp-error-text">
                            ${this.escapeHtml(error || 'Failed to repurpose content for this platform')}
                        </div>
                    </div>
                </div>
            `;
        }
        
        showResults() {
            if (this.elements.resultsContainer) {
                this.elements.resultsContainer.style.display = 'block';
                this.elements.resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        updateError(error) {
            if (!this.elements.errorMessage) return;
            
            if (error) {
                this.elements.errorContent.textContent = error;
                this.elements.errorMessage.style.display = 'block';
            } else {
                this.elements.errorMessage.style.display = 'none';
            }
        }
        
        showError(message) {
            this.state.setState({ error: message });
        }
        
        async loadUsageStats() {
            if (!this.state.getState().showUsageStats) return;
            
            try {
                const response = await this.makeAPIRequest('/repurpose-usage');
                if (response.success) {
                    this.state.setState({ usageStats: response.data });
                }
            } catch (error) {
                console.warn('Failed to load usage stats:', error);
            }
        }
        
        updateUsageStats(stats) {
            if (!this.elements.usageStats || !stats) return;
            
            const remaining = stats.remaining || 0;
            const isLimited = remaining <= 3;
            
            const statsHTML = `
                <div class="rwp-stats-grid">
                    <div class="rwp-stat-item">
                        <span class="rwp-stat-value">${stats.current_hour_usage || 0}</span>
                        <span class="rwp-stat-label">Used This Hour</span>
                    </div>
                    <div class="rwp-stat-item">
                        <span class="rwp-stat-value ${isLimited ? 'rwp-stat-warning' : ''}">${remaining}</span>
                        <span class="rwp-stat-label">Remaining</span>
                    </div>
                    ${!stats.is_guest ? `
                        <div class="rwp-stat-item">
                            <span class="rwp-stat-value">${stats.total_usage || 0}</span>
                            <span class="rwp-stat-label">Total Used</span>
                        </div>
                        <div class="rwp-stat-item">
                            <span class="rwp-stat-value">${stats.monthly_usage || 0}</span>
                            <span class="rwp-stat-label">This Month</span>
                        </div>
                    ` : ''}
                </div>
                ${stats.is_guest ? `<p class="rwp-guest-notice">Sign in for unlimited usage!</p>` : ''}
            `;
            
            this.elements.usageStats.innerHTML = `
                <h4>Usage Statistics</h4>
                <div class="rwp-stats-content">${statsHTML}</div>
            `;
            this.elements.usageStats.style.display = 'block';
        }
        
        async copyToClipboard(button) {
            const content = button.getAttribute('data-content');
            if (!content) return;
            
            try {
                await navigator.clipboard.writeText(content);
                
                // Update button to show success
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.classList.add('rwp-copied');
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('rwp-copied');
                }, 2000);
                
            } catch (error) {
                console.warn('Failed to copy to clipboard:', error);
                // Fallback for older browsers
                this.fallbackCopyToClipboard(content);
            }
        }
        
        fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
            } catch (error) {
                console.warn('Fallback copy failed:', error);
            }
            
            document.body.removeChild(textArea);
        }
        
        async makeAPIRequest(endpoint, options = {}) {
            const url = this.getRestURL() + endpoint.replace('/', '');
            
            const defaultOptions = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.getNonce()
                }
            };
            
            const requestOptions = { ...defaultOptions, ...options };
            
            const response = await fetch(url, requestOptions);
            
            if (!response.ok) {
                if (response.status === 429) {
                    throw new Error(this.getString('rateLimitExceeded'));
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        }
        
        persistGuestState(state) {
            if (this.isLoggedIn) return;
            
            try {
                const stateToStore = {
                    content: state.content,
                    platforms: state.platforms,
                    tone: state.tone
                };
                localStorage.setItem('rwp_content_repurposer_guest_state', JSON.stringify(stateToStore));
            } catch (error) {
                console.warn('Failed to persist guest state:', error);
            }
        }
        
        getCharacterLimit(platform) {
            const limits = this.getCharacterLimits();
            return limits[platform] || 280;
        }
        
        getCharacterLimits() {
            return (typeof rwpContentRepurposer !== 'undefined' && rwpContentRepurposer.characterLimits)
                ? rwpContentRepurposer.characterLimits
                : {
                    twitter: 280,
                    linkedin: 3000,
                    facebook: 63206,
                    instagram: 2200
                };
        }
        
        getString(key) {
            return (typeof rwpContentRepurposer !== 'undefined' && rwpContentRepurposer.strings && rwpContentRepurposer.strings[key])
                ? rwpContentRepurposer.strings[key]
                : key;
        }
        
        getRestURL() {
            return (typeof rwpContentRepurposer !== 'undefined' && rwpContentRepurposer.restUrl)
                ? rwpContentRepurposer.restUrl
                : '/wp-json/rwp-creator-suite/v1/';
        }
        
        getNonce() {
            return (typeof rwpContentRepurposer !== 'undefined' && rwpContentRepurposer.nonce)
                ? rwpContentRepurposer.nonce
                : '';
        }
        
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    }

    // Initialize Content Repurposer apps when DOM is ready
    function initializeContentRepurposerApps() {
        const containers = document.querySelectorAll('.wp-block-rwp-creator-suite-content-repurposer');
        
        containers.forEach(container => {
            // Prevent duplicate initialization
            if (container.dataset.rwpInitialized) return;
            
            const config = {
                platforms: JSON.parse(container.dataset.platforms || '["twitter","linkedin"]'),
                tone: container.dataset.tone || 'professional',
                showUsage: container.dataset.showUsage || '1'
            };
            
            new ContentRepurposerApp(container, config);
            container.dataset.rwpInitialized = 'true';
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeContentRepurposerApps);
    } else {
        initializeContentRepurposerApps();
    }

    // Make the class available globally for debugging
    window.ContentRepurposerApp = ContentRepurposerApp;

})();