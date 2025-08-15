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
            
            // Set login state and guest attempts first
            this.isLoggedIn = this.detectUserLoginState();
            this.guestAttempts = this.getGuestAttempts();
            
            // Initialize state with guest persistence support (now that login state is known)
            const initialState = this.getInitialStateWithPersistence(config);
            this.state = new RWPStateManager('content_repurposer', initialState);
            
            this.elements = {};
            
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
            
            // Check for stored guest state (for both guest and newly logged-in users)
            try {
                const stored = localStorage.getItem('rwp_content_repurposer_guest_state');
                
                if (stored) {
                    const parsedState = JSON.parse(stored);
                    
                    // For logged-in users who just authenticated, check for server-stored full content first
                    if (this.isLoggedIn && parsedState.lastGenerated) {
                        // Check if the guest data is recent (within last 30 minutes)
                        const thirtyMinutesAgo = Date.now() - (30 * 60 * 1000);
                        
                        if (parsedState.lastGenerated > thirtyMinutesAgo) {
                            
                            // Try to recover full content from server storage
                            this.recoverGuestContentFromServer()
                                .then(recoveredContent => {
                                    if (recoveredContent) {
                                        // Clear local guest state since we got the full content
                                        localStorage.removeItem('rwp_content_repurposer_guest_state');
                                        localStorage.removeItem('rwp_content_repurposer_state');
                                        
                                        // Add recovery info for display
                                        const enhancedContent = {
                                            ...recoveredContent.repurposed_content,
                                            _recoveryInfo: {
                                                wasRecovered: true,
                                                message: 'Your previous content has been fully restored with all platforms included!'
                                            }
                                        };
                                        
                                        // Update the app state with recovered content
                                        this.state.setState({
                                            content: recoveredContent.content || '',
                                            platforms: recoveredContent.platforms || baseState.platforms,
                                            tone: recoveredContent.tone || baseState.tone,
                                            repurposedContent: enhancedContent
                                        });
                                        
                                        return;
                                    }
                                    
                                    // If server recovery failed, fall back to local conversion
                                    this.fallbackToLocalGuestData(parsedState, baseState, config);
                                })
                                .catch(error => {
                                    this.fallbackToLocalGuestData(parsedState, baseState, config);
                                });
                            
                            // Return base state for now, recovery will update via setState
                            return baseState;
                        } else {
                        }
                    }
                    
                    // For guest users, load stored state normally
                    if (!this.isLoggedIn) {
                        return { ...baseState, ...parsedState, ...config };
                    }
                }
            } catch (error) {
                console.warn('Failed to load guest state:', error);
            }
            
            return baseState;
        }
        
        async recoverGuestContentFromServer() {
            if (!this.isLoggedIn) {
                return null;
            }
            
            try {
                const response = await this.makeAPIRequest('/recover-guest-content', {
                    method: 'POST',
                    body: JSON.stringify({
                        nonce: this.getNonce()
                    })
                });
                
                if (response.success && response.data) {
                    return response.data;
                }
                
                return null;
            } catch (error) {
                return null;
            }
        }
        
        fallbackToLocalGuestData(parsedState, baseState, config) {
            if (parsedState.repurposedContent) {
                // Convert guest preview data to full content for logged-in user
                const convertedContent = this.convertGuestDataForLoggedInUser(parsedState.repurposedContent);
                
                // Only clear guest state after successful transfer if we have content
                if (Object.keys(convertedContent).length > 0) {
                    localStorage.removeItem('rwp_content_repurposer_guest_state');
                    // Also clear any existing logged-in user state to prevent overwriting converted data
                    localStorage.removeItem('rwp_content_repurposer_state');
                    
                    // Update state with local converted content
                    this.state.setState({
                        content: parsedState.content || '',
                        platforms: parsedState.platforms || baseState.platforms,
                        tone: parsedState.tone || baseState.tone,
                        repurposedContent: convertedContent
                    });
                } else {
                }
            }
        }
        
        detectUserLoginState() {
            // Try multiple methods to detect login state
            let isLoggedIn = false;
            
            // Method 1: Check rwpContentRepurposer global
            if (typeof rwpContentRepurposer !== 'undefined' && rwpContentRepurposer.isLoggedIn) {
                isLoggedIn = true;
            }
            
            // Method 2: Check WordPress admin bar (wp-admin-bar is present for logged-in users)
            if (!isLoggedIn && document.getElementById('wpadminbar')) {
                isLoggedIn = true;
            }
            
            // Method 3: Check for WordPress user ID in body class
            if (!isLoggedIn && document.body.classList.contains('logged-in')) {
                isLoggedIn = true;
            }
            
            // Method 4: Check if there's a WordPress user ID in cookies
            if (!isLoggedIn && document.cookie.includes('wordpress_logged_in_')) {
                isLoggedIn = true;
            }
            
            return isLoggedIn;
        }
        
        getGuestAttempts() {
            if (this.isLoggedIn) return { attempts: 0, remaining: Infinity, firstUse: null };
            
            try {
                const stored = localStorage.getItem('content_repurposer_attempts');
                const attempts = stored ? parseInt(stored, 10) : 0;
                
                const firstUseStored = localStorage.getItem('content_repurposer_first_use');
                const firstUse = firstUseStored ? parseInt(firstUseStored, 10) : null;
                
                const remaining = Math.max(0, 3 - attempts);
                
                return { attempts, remaining, firstUse };
            } catch (error) {
                console.warn('Failed to load guest attempts:', error);
                return { attempts: 0, remaining: 3, firstUse: null };
            }
        }
        
        updateGuestAttempts() {
            if (this.isLoggedIn) return;
            
            try {
                const newAttempts = this.guestAttempts.attempts + 1;
                const firstUse = this.guestAttempts.firstUse || Date.now();
                
                localStorage.setItem('content_repurposer_attempts', newAttempts.toString());
                localStorage.setItem('content_repurposer_first_use', firstUse.toString());
                
                this.guestAttempts = {
                    attempts: newAttempts,
                    remaining: Math.max(0, 3 - newAttempts),
                    firstUse
                };
                
                this.updateAttemptsDisplay();
            } catch (error) {
                console.warn('Failed to update guest attempts:', error);
            }
        }
        
        updateAttemptsDisplay() {
            const attemptsCountEl = this.container.querySelector('.rwp-attempts-count');
            if (attemptsCountEl) {
                attemptsCountEl.textContent = this.guestAttempts.remaining;
            }
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
            this.setupUserStateDisplay();
            this.bindEvents();
            this.updateUI();
            this.loadUsageStats();
            
            // Set up state change listener
            this.state.subscribe((newState) => {
                this.updateUI(newState);
                this.persistGuestState(newState);
            });
        }
        
        cacheElements() {
            const container = this.container;
            
            this.elements = {
                loggedInForm: container.querySelector('.rwp-repurposer-logged-in'),
                guestForm: container.querySelector('.rwp-repurposer-guest'),
                guestTeaser: container.querySelector('.rwp-repurposer-guest-teaser'),
                guestExhaustedMessage: container.querySelector('.rwp-guest-exhausted-message'),
                contentInput: container.querySelector('.rwp-content-input'),
                guestContentInput: container.querySelector('.rwp-guest-content-input'),
                characterCount: container.querySelectorAll('.rwp-count-current'),
                platformCheckboxes: container.querySelectorAll('.rwp-platform-checkbox'),
                toneSelect: container.querySelector('.rwp-tone-select'),
                guestToneSelect: container.querySelector('.rwp-guest-tone-select'),
                repurposeButton: container.querySelector('.rwp-repurpose-button:not(.rwp-guest-repurpose-button)'),
                guestRepurposeButton: container.querySelector('.rwp-guest-repurpose-button'),
                usageStats: container.querySelector('.rwp-usage-stats'),
                resultsContainer: container.querySelector('.rwp-results-container'),
                resultsContent: container.querySelector('.rwp-results-content'),
                guestResultsUpgrade: container.querySelector('.rwp-guest-results-upgrade'),
                errorMessage: container.querySelector('.rwp-error-message'),
                errorContent: container.querySelector('.rwp-error-content'),
                attemptsCount: container.querySelector('.rwp-attempts-count')
            };
        }
        
        setupUserStateDisplay() {
            if (this.isLoggedIn) {
                // Show logged-in form, hide all guest elements
                this.showElement(this.elements.loggedInForm);
                this.hideElement(this.elements.guestForm);
                this.hideElement(this.elements.guestTeaser);
                this.hideElement(this.elements.guestExhaustedMessage);
            } else {
                // Guest user logic based on attempts remaining
                this.hideElement(this.elements.loggedInForm);
                
                if (this.guestAttempts.remaining > 0) {
                    // Show guest form with attempts remaining
                    this.showElement(this.elements.guestForm);
                    this.hideElement(this.elements.guestTeaser);
                    this.hideElement(this.elements.guestExhaustedMessage);
                    this.updateAttemptsDisplay();
                } else {
                    // Show exhausted message, hide forms
                    this.hideElement(this.elements.guestForm);
                    this.hideElement(this.elements.guestTeaser);
                    this.showElement(this.elements.guestExhaustedMessage);
                }
            }
        }
        
        showElement(element) {
            if (element) element.style.display = 'block';
        }
        
        hideElement(element) {
            if (element) element.style.display = 'none';
        }
        
        bindEvents() {
            // Content input events for logged-in users
            if (this.elements.contentInput) {
                this.elements.contentInput.addEventListener('input', (e) => {
                    const content = e.target.value;
                    this.state.setState({ content });
                    this.updateCharacterCount(content);
                });
                
                this.elements.contentInput.addEventListener('paste', () => {
                    setTimeout(() => {
                        const content = this.elements.contentInput.value;
                        this.state.setState({ content });
                        this.updateCharacterCount(content);
                    }, 10);
                });
            }
            
            // Content input events for guest users
            if (this.elements.guestContentInput) {
                this.elements.guestContentInput.addEventListener('input', (e) => {
                    const content = e.target.value;
                    this.state.setState({ content });
                    this.updateCharacterCount(content);
                });
                
                this.elements.guestContentInput.addEventListener('paste', () => {
                    setTimeout(() => {
                        const content = this.elements.guestContentInput.value;
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
            
            // Tone selection events
            if (this.elements.toneSelect) {
                this.elements.toneSelect.addEventListener('change', (e) => {
                    this.state.setState({ tone: e.target.value });
                });
            }
            
            if (this.elements.guestToneSelect) {
                this.elements.guestToneSelect.addEventListener('change', (e) => {
                    this.state.setState({ tone: e.target.value });
                });
            }
            
            // Repurpose button events
            if (this.elements.repurposeButton) {
                this.elements.repurposeButton.addEventListener('click', () => {
                    this.repurposeContent();
                });
            }
            
            if (this.elements.guestRepurposeButton) {
                this.elements.guestRepurposeButton.addEventListener('click', () => {
                    this.repurposeContentGuest();
                });
            }
            
            // Copy button events (delegated)
            this.container.addEventListener('click', (e) => {
                if (e.target.classList.contains('rwp-copy-button')) {
                    this.copyToClipboard(e.target);
                }
            });
        }
        
        updateUI(providedState = null) {
            const state = providedState || this.state.getState();
            
            // Update form fields for logged-in users
            if (this.elements.contentInput && this.elements.contentInput.value !== state.content) {
                this.elements.contentInput.value = state.content;
                this.updateCharacterCount(state.content);
            }
            
            // Update form fields for guest users
            if (this.elements.guestContentInput && this.elements.guestContentInput.value !== state.content) {
                this.elements.guestContentInput.value = state.content;
                this.updateCharacterCount(state.content);
            }
            
            // Update platform checkboxes
            this.elements.platformCheckboxes.forEach(checkbox => {
                checkbox.checked = state.platforms.includes(checkbox.value);
            });
            
            // Update tone select for logged-in users
            if (this.elements.toneSelect) {
                this.elements.toneSelect.value = state.tone;
            }
            
            // Update tone select for guest users
            if (this.elements.guestToneSelect) {
                this.elements.guestToneSelect.value = state.tone;
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
            
            // Update all character count elements (both logged-in and guest forms)
            this.elements.characterCount.forEach(element => {
                element.textContent = count.toLocaleString();
                
                // Update character count styling based on limits
                element.className = 'rwp-count-current';
                if (count > 8000) {
                    element.classList.add('rwp-count-warning');
                }
                if (count > 9500) {
                    element.classList.add('rwp-count-error');
                }
            });
        }
        
        updateButtonState(isProcessing) {
            // Update both logged-in and guest buttons
            const buttons = [this.elements.repurposeButton, this.elements.guestRepurposeButton].filter(Boolean);
            
            buttons.forEach(button => {
                button.disabled = isProcessing;
                
                const buttonText = button.querySelector('.rwp-button-text');
                const buttonLoading = button.querySelector('.rwp-button-loading');
                
                if (isProcessing) {
                    button.classList.add('rwp-loading');
                    if (buttonText) buttonText.style.display = 'none';
                    if (buttonLoading) buttonLoading.style.display = 'inline';
                } else {
                    button.classList.remove('rwp-loading');
                    if (buttonText) buttonText.style.display = 'inline';
                    if (buttonLoading) buttonLoading.style.display = 'none';
                }
            });
        }
        
        async repurposeContent() {
            // If user is not logged in, this shouldn't be called, but add a safety check
            if (!this.isLoggedIn) {
                console.warn('Repurpose button clicked for guest user - this should not happen');
                return;
            }
            
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
                        tone: state.tone,
                        nonce: this.isLoggedIn ? this.getNonce() : null
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
        
        async repurposeContentGuest() {
            if (this.guestAttempts.remaining <= 0) {
                this.setupUserStateDisplay();
                return;
            }
            
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
                // For guest users, always request all platforms but will get limited results
                const platforms = ['twitter', 'linkedin', 'facebook', 'instagram'];
                
                const response = await this.makeAPIRequest('/repurpose-content', {
                    method: 'POST',
                    body: JSON.stringify({
                        content: state.content,
                        platforms: platforms,
                        tone: state.tone,
                        is_guest: true,
                        nonce: this.isLoggedIn ? this.getNonce() : null
                    })
                });
                
                if (response.success) {
                    // Update guest attempts after successful request
                    this.updateGuestAttempts();
                    
                    this.state.setState({ 
                        repurposedContent: response.data,
                        isProcessing: false
                    });
                    this.showGuestResults();
                    
                    // Check if exhausted after this attempt
                    if (this.guestAttempts.remaining <= 0) {
                        setTimeout(() => {
                            this.setupUserStateDisplay();
                        }, 1000);
                    }
                } else {
                    throw new Error(response.message || 'Failed to repurpose content');
                }
                
            } catch (error) {
                console.error('Guest content repurposing error:', error);
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
            
            // Check for upgrade message or content recovery message
            if (repurposedContent._upgradeInfo && repurposedContent._upgradeInfo.hasPreviewContent) {
                resultsHTML += `
                    <div class="rwp-upgrade-success-message">
                        <div class="rwp-upgrade-success-content">
                            <span class="rwp-success-icon">✅</span>
                            <strong>Registration successful!</strong> ${repurposedContent._upgradeInfo.message}
                        </div>
                    </div>
                `;
            } else if (repurposedContent._recoveryInfo && repurposedContent._recoveryInfo.wasRecovered) {
                resultsHTML += `
                    <div class="rwp-recovery-success-message">
                        <div class="rwp-recovery-success-content">
                            <span class="rwp-success-icon">🎉</span>
                            <strong>Welcome back!</strong> ${repurposedContent._recoveryInfo.message}
                        </div>
                    </div>
                `;
            }
            
            Object.entries(repurposedContent).forEach(([platform, data]) => {
                // Skip special internal properties
                if (platform.startsWith('_')) return;
                
                const platformName = platform.charAt(0).toUpperCase() + platform.slice(1);
                const characterLimit = this.getCharacterLimit(platform);
                
                if (data.success && data.versions) {
                    resultsHTML += this.renderPlatformSuccess(platform, platformName, data.versions, characterLimit);
                } else {
                    resultsHTML += this.renderPlatformError(platform, platformName, data.error);
                }
            });
            
            this.elements.resultsContent.innerHTML = resultsHTML;
            if (this.isLoggedIn) {
                this.displayResults();
            } else {
                this.displayGuestResults();
            }
        }
        
        renderPlatformSuccess(platform, platformName, versions, characterLimit) {
            const isGuest = !this.isLoggedIn;
            const isTwitter = platform === 'twitter';
            
            const versionsHTML = versions.map((version, index) => {
                
                // For guest users, show preview for non-Twitter platforms
                if (isGuest && !isTwitter && version.is_preview) {
                    return `
                        <div class="rwp-content-version rwp-guest-preview">
                            <div class="rwp-version-text">${this.escapeHtml(version.preview_text)}</div>
                            <div class="rwp-version-meta">
                                <span class="rwp-character-count">Preview • Full version ${version.estimated_length} / ${characterLimit} characters</span>
                            </div>
                        </div>
                    `;
                } else {
                    // Full version for logged-in users or Twitter for guests
                    return `
                        <div class="rwp-content-version">
                            <div class="rwp-version-text">${this.escapeHtml(version.text)}</div>
                            <div class="rwp-version-meta">
                                <span class="rwp-character-count">${version.character_count} / ${characterLimit} characters</span>
                                <button type="button" class="rwp-copy-button" data-content="${this.escapeHtml(version.text)}">
                                    Copy
                                </button>
                            </div>
                        </div>
                    `;
                }
            }).join('');
            
            // Add single CTA per platform for guest previews
            const platformCTA = (isGuest && !isTwitter) ? `
                <div class="rwp-platform-upgrade-cta">
                    <div class="rwp-upgrade-badge">
                        <span class="rwp-lock-icon">🔒</span>
                        <span class="rwp-upgrade-text">
                            <a href="#rwp-upgrade-cta" class="rwp-scroll-to-upgrade">Sign up to see full version</a>
                        </span>
                    </div>
                </div>
            ` : '';
            
            return `
                <div class="rwp-platform-result ${isGuest && !isTwitter ? 'rwp-guest-limited' : ''}">
                    <div class="rwp-platform-header">
                        <span class="rwp-platform-name">${platformName}</span>
                        <span class="rwp-character-limit">Limit: ${characterLimit} chars</span>
                        ${isGuest && !isTwitter ? '<span class="rwp-preview-badge">Preview</span>' : ''}
                    </div>
                    <div class="rwp-platform-content">
                        <div class="rwp-content-versions">
                            ${versionsHTML}
                        </div>
                        ${platformCTA}
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
        
        displayResults() {
            if (this.elements.resultsContainer) {
                this.elements.resultsContainer.style.display = 'block';
            }
        }
        
        showResults() {
            if (this.elements.resultsContainer) {
                this.elements.resultsContainer.style.display = 'block';
                this.elements.resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        displayGuestResults() {
            if (this.elements.resultsContainer) {
                this.elements.resultsContainer.style.display = 'block';
                // Show the upgrade prompt for guest users
                if (this.elements.guestResultsUpgrade) {
                    this.elements.guestResultsUpgrade.style.display = 'block';
                }
            }
        }
        
        showGuestResults() {
            if (this.elements.resultsContainer) {
                this.elements.resultsContainer.style.display = 'block';
                // Show the upgrade prompt for guest users
                if (this.elements.guestResultsUpgrade) {
                    this.elements.guestResultsUpgrade.style.display = 'block';
                }
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
            // Skip usage stats for guest users
            if (!this.isLoggedIn || !this.state.getState().showUsageStats) return;
            
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
            const quotaText = this.elements.usageStats.querySelector('.quota-text');
            
            if (!quotaText) return;
            
            // Use green dot icon like caption writer
            const icon = '🟢';
            
            // Update quota text with simple format like caption writer
            if (remaining === 0) {
                quotaText.innerHTML = `${icon} <strong>Daily limit reached.</strong> Please try again later or upgrade your plan.`;
            } else {
                quotaText.innerHTML = `${icon} Remaining AI generations: <strong>${remaining}</strong>`;
            }
            
            // Show the quota display
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
                
                // Try to get the actual error message from the response
                let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    if (errorData.message) {
                        errorMessage = errorData.message;
                    } else if (errorData.error && errorData.error.message) {
                        errorMessage = errorData.error.message;
                    }
                    console.error('API Error Details:', errorData);
                } catch (e) {
                    console.error('Could not parse error response:', e);
                }
                
                throw new Error(errorMessage);
            }
            
            return await response.json();
        }
        
        persistGuestState(state) {
            if (this.isLoggedIn) {
                return;
            }
            
            try {
                const stateToStore = {
                    content: state.content,
                    platforms: state.platforms,
                    tone: state.tone,
                    repurposedContent: state.repurposedContent || {},
                    lastGenerated: state.repurposedContent && Object.keys(state.repurposedContent).length > 0 ? Date.now() : null
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
            if (text == null || text === undefined) {
                return '';
            }
            
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
        
        convertGuestDataForLoggedInUser(guestRepurposedContent) {
            if (!guestRepurposedContent || Object.keys(guestRepurposedContent).length === 0) {
                return {};
            }
            
            
            const convertedContent = {};
            let hasPreviewContent = false;
            
            Object.entries(guestRepurposedContent).forEach(([platform, data]) => {
                // Skip internal properties
                if (platform.startsWith('_')) return;
                
                if (data.success && data.versions) {
                    convertedContent[platform] = {
                        success: true,
                        versions: data.versions.map(version => {
                            // For Twitter, guest users already got full content
                            if (platform === 'twitter' && version.text) {
                                return version;
                            }
                            
                            // For other platforms with preview content, show upgrade prompt
                            if (version.is_preview && version.preview_text) {
                                hasPreviewContent = true;
                                const convertedText = `${version.preview_text}\n\n[Click "Repurpose Content" again to generate the full version now that you're logged in!]`;
                                return {
                                    text: convertedText,
                                    character_count: version.estimated_length || convertedText.length,
                                    is_converted_from_preview: true,
                                    show_regenerate_prompt: true
                                };
                            }
                            
                            // Fallback for any other content
                            return version;
                        })
                    };
                } else {
                    // Preserve error states
                    convertedContent[platform] = data;
                }
            });
            
            // Add a flag to show upgrade success message
            if (hasPreviewContent) {
                convertedContent._upgradeInfo = {
                    hasPreviewContent: true,
                    message: "Welcome! You can now regenerate this content to see the full versions."
                };
            }
            
            
            // Debug the actual structure of converted content
            Object.entries(convertedContent).forEach(([platform, data]) => {
                if (platform.startsWith('_')) return;
            });
            
            return convertedContent;
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