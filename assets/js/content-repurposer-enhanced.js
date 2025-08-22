/**
 * Enhanced Content Repurposer Frontend Integration
 * 
 * Demonstrates integration of Phase 2 enhanced components with the existing
 * WordPress block functionality. This file shows how to initialize and use
 * the new floating inputs, smart textarea, result cards, and loading states.
 */

(function() {
    'use strict';
    
    class EnhancedContentRepurposerApp {
        constructor(container, config = {}) {
            this.container = container;
            this.config = config;
            
            // Initialize enhanced components
            this.components = {
                smartTextarea: null,
                floatingInputs: [],
                loadingStates: null,
                resultCards: [],
                guestTeaser: null
            };
            
            // Check for required dependencies
            if (typeof RWPStateManager === 'undefined') {
                console.warn('EnhancedContentRepurposer: RWPStateManager dependency not loaded, using fallback');
                this.initializeFallbackMode();
                return;
            }
            
            // Set login state and guest attempts first
            this.isLoggedIn = this.detectUserLoginState();
            this.guestAttempts = this.getGuestAttempts();
            
            // Initialize state with guest persistence support
            const initialState = this.getInitialStateWithPersistence(config);
            this.state = new RWPStateManager('content_repurposer_enhanced', initialState);
            
            this.elements = {};
            this.init();
        }
        
        init() {
            this.cacheElements();
            this.initializeEnhancedComponents();
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
                
                // Enhanced component containers
                smartTextareas: container.querySelectorAll('[data-component="SmartTextarea"]'),
                floatingInputs: container.querySelectorAll('[data-component="FloatingInput"]'),
                loadingButtons: container.querySelectorAll('[data-component="LoadingButton"]'),
                enhancedGuestTeasers: container.querySelectorAll('[data-component="EnhancedGuestTeaser"]'),
                resultsGrid: container.querySelector('[data-component="ResultsGrid"]'),
                enhancedLoadingStates: container.querySelector('[data-component="EnhancedLoadingStates"]'),
                
                // Traditional elements for backward compatibility
                contentInput: container.querySelector('.rwp-content-input'),
                guestContentInput: container.querySelector('.rwp-guest-content-input'),
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
                loadingContainer: container.querySelector('.rwp-loading-container')
            };
        }
        
        initializeEnhancedComponents() {
            // Initialize Smart Textareas
            this.elements.smartTextareas.forEach((textareaContainer) => {
                const textarea = textareaContainer.querySelector('.smart-textarea');
                if (textarea) {
                    this.initializeSmartTextarea(textarea, textareaContainer);
                }
            });
            
            // Initialize Floating Inputs
            this.elements.floatingInputs.forEach((inputContainer) => {
                const input = inputContainer.querySelector('.floating-input');
                if (input) {
                    this.initializeFloatingInput(input, inputContainer);
                }
            });
            
            // Initialize Loading Buttons
            this.elements.loadingButtons.forEach((button) => {
                this.initializeLoadingButton(button);
            });
            
            // Initialize Enhanced Guest Teasers
            this.elements.enhancedGuestTeasers.forEach((teaser) => {
                this.initializeEnhancedGuestTeaser(teaser);
            });
        }
        
        initializeSmartTextarea(textarea, container) {
            const wordCountEl = container.querySelector('.word-count');
            const charCountEl = container.querySelector('.character-count');
            const label = container.querySelector('.floating-label');
            
            // Auto-resize functionality
            const autoResize = () => {
                textarea.style.height = 'auto';
                const newHeight = Math.max(120, Math.min(400, textarea.scrollHeight));
                textarea.style.height = `${newHeight}px`;
            };
            
            // Count updates
            const updateCounts = () => {
                const text = textarea.value || '';
                const words = text.trim().split(/\s+/).filter(word => word.length > 0);
                const wordCount = words.length === 1 && words[0] === '' ? 0 : words.length;
                const charCount = text.length;
                
                if (wordCountEl) {
                    wordCountEl.textContent = wordCount === 1 ? '1 word' : `${wordCount} words`;
                }
                
                if (charCountEl) {
                    const maxLength = parseInt(textarea.getAttribute('maxlength')) || 10000;
                    charCountEl.textContent = `${charCount} / ${maxLength.toLocaleString()} characters`;
                    
                    // Update character count styling
                    charCountEl.className = 'character-count';
                    if (charCount > maxLength * 0.9) charCountEl.classList.add('character-count--warning');
                    if (charCount >= maxLength) charCountEl.classList.add('character-count--over');
                }
            };
            
            // Floating label behavior
            const updateLabel = () => {
                if (label) {
                    const hasValue = textarea.value && textarea.value.trim().length > 0;
                    const isFocused = document.activeElement === textarea;
                    
                    if (isFocused || hasValue) {
                        label.classList.add('floating-label--floated');
                    } else {
                        label.classList.remove('floating-label--floated');
                    }
                }
            };
            
            // Event listeners
            textarea.addEventListener('input', () => {
                autoResize();
                updateCounts();
                updateLabel();
                
                // Update application state
                const content = textarea.value;
                this.state.setState({ content });
            });
            
            textarea.addEventListener('focus', updateLabel);
            textarea.addEventListener('blur', updateLabel);
            textarea.addEventListener('paste', () => {
                setTimeout(() => {
                    autoResize();
                    updateCounts();
                    updateLabel();
                    
                    const content = textarea.value;
                    this.state.setState({ content });
                }, 10);
            });
            
            // Initialize
            autoResize();
            updateCounts();
            updateLabel();
        }
        
        initializeFloatingInput(input, container) {
            const label = container.querySelector('.floating-label');
            
            const updateLabel = () => {
                if (label) {
                    const hasValue = input.value && input.value.trim().length > 0;
                    const isFocused = document.activeElement === input;
                    
                    if (isFocused || hasValue) {
                        label.classList.add('floating-label--floated');
                    } else {
                        label.classList.remove('floating-label--floated');
                    }
                }
            };
            
            input.addEventListener('input', () => {
                updateLabel();
                
                // Update application state for tone selects
                if (input.classList.contains('rwp-tone-select')) {
                    this.state.setState({ tone: input.value });
                }
            });
            
            input.addEventListener('change', () => {
                updateLabel();
                
                if (input.classList.contains('rwp-tone-select')) {
                    this.state.setState({ tone: input.value });
                }
            });
            
            input.addEventListener('focus', updateLabel);
            input.addEventListener('blur', updateLabel);
            
            // Initialize
            updateLabel();
        }
        
        initializeLoadingButton(button) {
            const originalText = button.querySelector('.button-text')?.textContent || 'Loading...';
            
            button._setLoading = (loading, loadingText = null) => {
                const textSpan = button.querySelector('.button-text');
                let spinner = button.querySelector('.button-spinner');
                
                if (loading) {
                    button.classList.add('loading-button--loading');
                    button.disabled = true;
                    
                    if (textSpan) {
                        textSpan.textContent = loadingText || 'Loading...';
                        textSpan.classList.add('button-text--hidden');
                    }
                    
                    if (!spinner) {
                        spinner = document.createElement('span');
                        spinner.className = 'button-spinner';
                        spinner.innerHTML = '<span class="spinner-dot"></span>';
                        button.appendChild(spinner);
                    }
                } else {
                    button.classList.remove('loading-button--loading');
                    button.disabled = false;
                    
                    if (textSpan) {
                        textSpan.textContent = originalText;
                        textSpan.classList.remove('button-text--hidden');
                    }
                    
                    if (spinner) {
                        spinner.remove();
                    }
                }
            };
        }
        
        initializeEnhancedGuestTeaser(teaser) {
            // Enhanced guest teaser is mostly CSS-driven
            // Add any interactive behavior here if needed
            
            const ctaButton = teaser.querySelector('.cta-button');
            const loginLink = teaser.querySelector('.login-link');
            
            if (ctaButton) {
                ctaButton.addEventListener('click', () => {
                    // Add animation feedback
                    ctaButton.classList.add('animating');
                    setTimeout(() => {
                        ctaButton.classList.remove('animating');
                    }, 300);
                });
            }
        }
        
        showEnhancedLoading(type = 'generating', message = null, showProgress = false) {
            if (this.elements.loadingContainer) {
                const loadingState = this.elements.enhancedLoadingStates;
                
                if (loadingState) {
                    // Configure loading state based on type
                    const config = this.getLoadingConfig(type);
                    
                    loadingState.innerHTML = `
                        <div class="loading-container">
                            <div class="loading-spinner">
                                <div class="spinner-ring">
                                    <div class="spinner-inner"></div>
                                    <div class="spinner-outer"></div>
                                </div>
                                <div class="loading-emoji">${config.icon}</div>
                            </div>
                            
                            <div class="loading-content">
                                <h3 class="loading-title">${config.title}</h3>
                                <p class="loading-message">${message || config.subtitle}</p>
                                
                                <div class="progress-container">
                                    <div class="progress-bar ${showProgress ? '' : 'indeterminate'}">
                                        <div class="progress-fill"></div>
                                    </div>
                                    ${showProgress ? '<div class="progress-text">0%</div>' : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                this.elements.loadingContainer.style.display = 'block';
            }
            
            // Update loading buttons
            this.elements.loadingButtons.forEach(button => {
                if (button._setLoading) {
                    button._setLoading(true, this.getLoadingConfig(type).title);
                }
            });
        }
        
        hideEnhancedLoading() {
            if (this.elements.loadingContainer) {
                this.elements.loadingContainer.style.display = 'none';
            }
            
            // Update loading buttons
            this.elements.loadingButtons.forEach(button => {
                if (button._setLoading) {
                    button._setLoading(false);
                }
            });
        }
        
        displayEnhancedResults(repurposedContent) {
            if (!this.elements.resultsGrid || !repurposedContent || Object.keys(repurposedContent).length === 0) {
                return;
            }
            
            const results = this.transformToResultCards(repurposedContent);
            let resultsHTML = '';
            
            results.forEach((result, index) => {
                resultsHTML += this.renderEnhancedResultCard(result, index);
            });
            
            this.elements.resultsGrid.innerHTML = resultsHTML;
            
            // Show results container
            if (this.elements.resultsContainer) {
                this.elements.resultsContainer.style.display = 'block';
                this.elements.resultsContainer.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
            
            // Initialize copy buttons in result cards
            this.initializeResultCardActions();
        }
        
        renderEnhancedResultCard(result, index) {
            const qualityClass = this.getQualityClass(result.qualityScore);
            
            return `
                <div class="result-card" data-result-index="${index}">
                    ${result.qualityScore ? `
                        <div class="quality-indicator ${qualityClass}">
                            ${Math.round(result.qualityScore)}
                        </div>
                    ` : ''}
                    
                    <div class="result-card-header">
                        <div class="result-card-title">
                            <span>${this.escapeHtml(result.title || 'Generated Content')}</span>
                        </div>
                        <div class="result-card-meta">
                            ${result.platform ? `
                                <div class="result-platform">
                                    <span class="platform-icon">${result.platform.icon || '📱'}</span>
                                    ${result.platform.name}
                                </div>
                            ` : ''}
                            ${result.createdAt ? `
                                <span class="result-timestamp">
                                    ${this.formatTimestamp(result.createdAt)}
                                </span>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="result-card-content">
                        <p class="result-text">${this.escapeHtml(result.content)}</p>
                    </div>
                    
                    <div class="result-card-footer">
                        <div class="result-actions">
                            <button class="result-action primary copy-button" data-content="${this.escapeHtml(result.content)}">
                                Copy
                            </button>
                            ${result.isPreview ? `
                                <button class="result-action upgrade-button">
                                    See Full Version
                                </button>
                            ` : ''}
                        </div>
                        
                        <div class="result-stats">
                            <div class="result-stat">
                                <span>${(result.content || '').length.toLocaleString()}</span>
                                <span>chars</span>
                            </div>
                            ${result.wordCount ? `
                                <div class="result-stat">
                                    <span>${result.wordCount.toLocaleString()}</span>
                                    <span>words</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }
        
        initializeResultCardActions() {
            // Copy button functionality
            this.elements.resultsGrid.addEventListener('click', async (e) => {
                if (e.target.classList.contains('copy-button')) {
                    const content = e.target.getAttribute('data-content');
                    
                    try {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            await navigator.clipboard.writeText(content);
                            
                            const originalText = e.target.textContent;
                            e.target.textContent = 'Copied!';
                            e.target.classList.add('result-action--success');
                            
                            setTimeout(() => {
                                e.target.textContent = originalText;
                                e.target.classList.remove('result-action--success');
                            }, 2000);
                        } else {
                            this.fallbackCopyToClipboard(content, e.target);
                        }
                    } catch (error) {
                        console.warn('Copy failed:', error);
                        this.fallbackCopyToClipboard(content, e.target);
                    }
                }
                
                if (e.target.classList.contains('upgrade-button')) {
                    // Scroll to upgrade CTA
                    const upgradeSection = this.container.querySelector('#rwp-upgrade-cta');
                    if (upgradeSection) {
                        upgradeSection.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                }
            });
        }
        
        // Utility methods (inherited from original implementation)
        getLoadingConfig(type) {
            const configs = {
                default: {
                    title: 'Loading',
                    subtitle: 'Please wait…',
                    icon: '⏳'
                },
                generating: {
                    title: 'Generating Content',
                    subtitle: 'AI is creating your content…',
                    icon: '🤖'
                },
                processing: {
                    title: 'Processing',
                    subtitle: 'Analyzing and optimizing…',
                    icon: '⚙️'
                },
                analyzing: {
                    title: 'Analyzing',
                    subtitle: 'Examining content and gathering insights…',
                    icon: '📊'
                }
            };
            
            return configs[type] || configs.default;
        }
        
        transformToResultCards(repurposedContent) {
            const results = [];
            
            Object.entries(repurposedContent).forEach(([platform, data]) => {
                if (platform.startsWith('_')) return; // Skip internal properties
                
                if (data.success && data.versions) {
                    data.versions.forEach((version, index) => {
                        results.push({
                            id: `${platform}-${index}`,
                            title: `${platform.charAt(0).toUpperCase() + platform.slice(1)} Version ${index + 1}`,
                            content: version.text || version.preview_text || '',
                            platform: {
                                name: platform.charAt(0).toUpperCase() + platform.slice(1),
                                icon: this.getPlatformIcon(platform)
                            },
                            qualityScore: version.quality_score,
                            wordCount: this.countWords(version.text || version.preview_text || ''),
                            createdAt: Date.now(),
                            isPreview: version.is_preview || false
                        });
                    });
                }
            });
            
            return results;
        }
        
        getQualityClass(score) {
            if (!score) return '';
            if (score >= 80) return 'quality-high';
            if (score >= 60) return 'quality-medium';
            return 'quality-low';
        }
        
        getPlatformIcon(platform) {
            const icons = {
                twitter: '𝕏',
                linkedin: '💼',
                facebook: '📘',
                instagram: '📷',
                tiktok: '🎵',
                youtube: '📺',
                pinterest: '📌'
            };
            return icons[platform] || '📱';
        }
        
        countWords(text) {
            const words = (text || '').trim().split(/\s+/).filter(word => word.length > 0);
            return words.length === 1 && words[0] === '' ? 0 : words.length;
        }
        
        formatTimestamp(timestamp) {
            return new Date(timestamp).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        escapeHtml(text) {
            if (text == null || text === undefined) return '';
            
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
        
        fallbackCopyToClipboard(text, button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.top = '-9999px';
            textArea.style.left = '-9999px';
            textArea.style.opacity = '0';
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const success = document.execCommand('copy');
                if (success && button) {
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.classList.add('result-action--success');
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.classList.remove('result-action--success');
                    }, 2000);
                }
            } catch (error) {
                console.warn('Fallback copy failed:', error);
            }
            
            document.body.removeChild(textArea);
        }
        
        // Inherit other methods from original ContentRepurposerApp
        // (detectUserLoginState, getGuestAttempts, setupUserStateDisplay, etc.)
        detectUserLoginState() {
            return typeof rwpContentRepurposer !== 'undefined' && rwpContentRepurposer.isLoggedIn;
        }
        
        getGuestAttempts() {
            if (this.isLoggedIn) {
                return { attempts: 0, remaining: Infinity, firstUse: null };
            }
            
            try {
                const stored = localStorage.getItem('content_repurposer_attempts');
                const attempts = stored ? parseInt(stored, 10) : 0;
                const remaining = Math.max(0, 3 - attempts);
                
                return { attempts, remaining, firstUse: null };
            } catch (error) {
                console.warn('Failed to load guest attempts:', error);
                return { attempts: 0, remaining: 3, firstUse: null };
            }
        }
        
        getInitialStateWithPersistence(config) {
            return {
                content: '',
                platforms: config.platforms || ['twitter', 'linkedin'],
                tone: config.tone || 'professional',
                repurposedContent: {},
                isProcessing: false,
                showUsageStats: config.showUsage !== '0',
                usageStats: null,
                error: null
            };
        }
        
        setupUserStateDisplay() {
            // Implementation similar to original
            if (this.isLoggedIn) {
                this.showElement(this.elements.loggedInForm);
                this.hideElement(this.elements.guestForm);
                this.hideElement(this.elements.guestTeaser);
                this.hideElement(this.elements.guestExhaustedMessage);
            } else {
                this.hideElement(this.elements.loggedInForm);
                
                if (this.guestAttempts.remaining > 0) {
                    this.showElement(this.elements.guestForm);
                    this.hideElement(this.elements.guestTeaser);
                    this.hideElement(this.elements.guestExhaustedMessage);
                } else {
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
            // Platform selection events
            this.elements.platformCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    const platforms = Array.from(this.elements.platformCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);
                    this.state.setState({ platforms });
                });
            });
            
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
        }
        
        updateUI(providedState = null) {
            const state = providedState || this.state.getState();
            
            // Update platform checkboxes
            this.elements.platformCheckboxes.forEach(checkbox => {
                checkbox.checked = state.platforms.includes(checkbox.value);
                
                // Update enhanced platform cards
                const platformCard = checkbox.closest('.rwp-platform-card');
                if (platformCard) {
                    if (checkbox.checked) {
                        platformCard.classList.add('rwp-platform-card--selected');
                    } else {
                        platformCard.classList.remove('rwp-platform-card--selected');
                    }
                }
            });
            
            // Update results
            if (state.repurposedContent && Object.keys(state.repurposedContent).length > 0) {
                this.displayEnhancedResults(state.repurposedContent);
            }
            
            // Update loading state
            if (state.isProcessing) {
                this.showEnhancedLoading('generating', 'AI is creating your content…', true);
            } else {
                this.hideEnhancedLoading();
            }
        }
        
        async repurposeContent() {
            const state = this.state.getState();
            
            this.state.setState({
                isProcessing: true,
                error: null,
                repurposedContent: {}
            });
            
            try {
                // Simulate API call for demo purposes
                await new Promise(resolve => setTimeout(resolve, 3000));
                
                // Mock successful response
                const mockResponse = {
                    success: true,
                    data: {
                        twitter: {
                            success: true,
                            versions: [
                                {
                                    text: `🚀 Just discovered the power of enhanced UI components! The new floating labels and smart textareas make content creation so much smoother. 

#WebDev #UX #React`,
                                    character_count: 156,
                                    quality_score: 85
                                }
                            ]
                        },
                        linkedin: {
                            success: true,
                            versions: [
                                {
                                    text: `I'm excited to share insights about the latest UI enhancements we've been working on! 

The new floating label patterns and smart textarea components significantly improve user experience by:

✨ Reducing cognitive load with clear visual feedback
🔄 Auto-resizing based on content
📊 Real-time word and character counting
🎨 Beautiful animations that feel natural

These seemingly small improvements make a huge difference in user satisfaction and engagement. What UI patterns have you found most effective in your projects?

#UserExperience #WebDevelopment #React #UI`,
                                    character_count: 624,
                                    quality_score: 92
                                }
                            ]
                        }
                    }
                };
                
                this.state.setState({
                    repurposedContent: mockResponse.data,
                    isProcessing: false
                });
                
            } catch (error) {
                console.error('Content repurposing error:', error);
                this.state.setState({
                    isProcessing: false,
                    error: error.message
                });
            }
        }
        
        async repurposeContentGuest() {
            // Similar to repurposeContent but with guest limitations
            await this.repurposeContent();
        }
        
        persistGuestState(state) {
            // Persist guest state if not logged in
        }
        
        loadUsageStats() {
            // Load usage statistics
        }
        
        initializeFallbackMode() {
            this.container.innerHTML = `
                <div class="rwp-error-message" style="display: block;">
                    <div class="rwp-error-content">
                        Enhanced Content Repurposer is not properly configured. Please refresh the page.
                    </div>
                </div>
            `;
        }
    }
    
    // Initialize Enhanced Content Repurposer apps when DOM is ready
    function initializeEnhancedContentRepurposerApps() {
        const containers = document.querySelectorAll('.wp-block-rwp-creator-suite-content-repurposer.rwp-enhanced');
        
        containers.forEach(container => {
            // Prevent duplicate initialization
            if (container.dataset.rwpEnhancedInitialized) return;
            
            const config = {
                platforms: JSON.parse(container.dataset.platforms || '["twitter","linkedin"]'),
                tone: container.dataset.tone || 'professional',
                showUsage: container.dataset.showUsage || '1'
            };
            
            new EnhancedContentRepurposerApp(container, config);
            container.dataset.rwpEnhancedInitialized = 'true';
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEnhancedContentRepurposerApps);
    } else {
        initializeEnhancedContentRepurposerApps();
    }
    
    // Make the class available globally for debugging
    window.EnhancedContentRepurposerApp = EnhancedContentRepurposerApp;
    
})();