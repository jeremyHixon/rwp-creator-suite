/**
 * Caption Writer Frontend Application
 * 
 * Handles AI caption generation, template management, and user interactions
 * for the Caption Writer block.
 */

(function() {
    'use strict';

    class CaptionWriterApp {
        constructor(container, config = {}) {
            this.container = container;
            this.config = config;
            this.state = new RWPStateManager('caption_writer', {
                description: '',
                platform: config.platform || 'instagram',
                tone: config.tone || 'casual',
                generatedCaptions: [],
                templates: [],
                favorites: [],
                finalCaption: config.finalCaption || '',
                isGenerating: false,
                activeTab: 'generator',
                characterCount: 0,
                characterLimit: this.getCharacterLimit(config.platform || 'instagram')
            });
            
            this.elements = {};
            this.init();
        }
        
        init() {
            this.cacheElements();
            this.setupEventListeners();
            this.loadBuiltInTemplates();
            this.updateCharacterCount();
            
            if (rwpCaptionWriter.isLoggedIn) {
                this.updateGenerateButtonState();
                this.loadUserData();
            } else {
                // For guests, show teaser instead of functional AI generator
                this.handleGuestExperience();
            }
            
            // Update character limit when platform changes
            this.updateCharacterLimit();
            
            // Add ARIA labels for better accessibility
            this.enhanceAccessibility();
        }
        
        cacheElements() {
            const container = this.container;
            
            this.elements = {
                // Tabs
                tabButtons: container.querySelectorAll('.tab-button'),
                tabContents: container.querySelectorAll('.tab-content'),
                
                // AI Generator
                descriptionInput: container.querySelector('[data-description]'),
                toneSelect: container.querySelector('[data-tone]'),
                generateBtn: container.querySelector('[data-generate]'),
                captionsContainer: container.querySelector('[data-captions]'),
                captionsList: container.querySelector('.captions-list'),
                
                // Templates
                templateCategory: container.querySelector('[data-template-category]'),
                templatesGrid: container.querySelector('[data-templates-grid]'),
                
                // Favorites
                favoritesList: container.querySelector('[data-favorites]'),
                
                // Output
                finalCaptionTextarea: container.querySelector('[data-final-caption]'),
                charCount: container.querySelector('[data-char-count]'),
                charLimit: container.querySelector('[data-char-limit]'),
                
                // Actions
                copyBtn: container.querySelector('[data-copy]'),
                saveFavoriteBtn: container.querySelector('[data-save-favorite]'),
                saveTemplateBtn: container.querySelector('[data-save-template]'),
                
                // Loading/Error
                loadingDiv: container.querySelector('[data-loading]'),
                errorDiv: container.querySelector('[data-error]')
            };
        }
        
        setupEventListeners() {
            // Tab switching with keyboard navigation
            this.elements.tabButtons.forEach((button, index) => {
                button.addEventListener('click', (e) => {
                    this.switchTab(e.target.dataset.tab);
                });
                
                // Keyboard navigation for tabs
                button.addEventListener('keydown', (e) => {
                    let nextIndex = index;
                    
                    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        nextIndex = (index + 1) % this.elements.tabButtons.length;
                    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        nextIndex = (index - 1 + this.elements.tabButtons.length) % this.elements.tabButtons.length;
                    } else if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.switchTab(e.target.dataset.tab);
                        return;
                    }
                    
                    if (nextIndex !== index) {
                        this.elements.tabButtons[nextIndex].focus();
                        this.switchTab(this.elements.tabButtons[nextIndex].dataset.tab);
                    }
                });
            });
            
            // AI Generator (only for logged-in users)
            if (this.elements.generateBtn && rwpCaptionWriter.isLoggedIn) {
                this.elements.generateBtn.addEventListener('click', () => {
                    this.generateCaptions();
                });
            }
            
            // Only set up AI generator input listeners for logged-in users
            if (rwpCaptionWriter.isLoggedIn) {
                if (this.elements.descriptionInput) {
                    this.elements.descriptionInput.addEventListener('input', (e) => {
                        const value = e.target.value;
                        this.state.setState({ description: value });
                        
                        // Real-time validation feedback
                        this.validateDescription(value);
                        
                        // Update generate button state
                        this.updateGenerateButtonState();
                    });
                    
                    // Enhanced accessibility - announce character count for screen readers
                    this.elements.descriptionInput.addEventListener('blur', (e) => {
                        if (e.target.value.length > 0) {
                            this.announceToScreenReader(`Description entered: ${e.target.value.length} characters`);
                        }
                    });
                }
                
                if (this.elements.toneSelect) {
                    this.elements.toneSelect.addEventListener('change', (e) => {
                        this.state.setState({ tone: e.target.value });
                    });
                }
            }
            
            // Template filtering
            if (this.elements.templateCategory) {
                this.elements.templateCategory.addEventListener('change', (e) => {
                    this.filterTemplates(e.target.value);
                });
            }
            
            // Final caption
            if (this.elements.finalCaptionTextarea) {
                this.elements.finalCaptionTextarea.addEventListener('input', (e) => {
                    this.state.setState({ finalCaption: e.target.value });
                    this.updateCharacterCount();
                });
                
                // Set initial value
                if (this.config.finalCaption) {
                    this.elements.finalCaptionTextarea.value = this.config.finalCaption;
                    this.state.setState({ finalCaption: this.config.finalCaption });
                }
            }
            
            // Action buttons
            if (this.elements.copyBtn) {
                this.elements.copyBtn.addEventListener('click', () => {
                    this.copyToClipboard();
                });
            }
            
            if (this.elements.saveFavoriteBtn) {
                this.elements.saveFavoriteBtn.addEventListener('click', () => {
                    this.saveToFavorites();
                });
            }
            
            if (this.elements.saveTemplateBtn) {
                this.elements.saveTemplateBtn.addEventListener('click', () => {
                    this.saveAsTemplate();
                });
            }
        }
        
        switchTab(tabName) {
            this.state.setState({ activeTab: tabName });
            
            // Update tab buttons
            this.elements.tabButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tabName);
            });
            
            // Update tab contents
            this.elements.tabContents.forEach(content => {
                content.classList.toggle('active', content.dataset.content === tabName);
            });
            
            // Load tab-specific data
            if (tabName === 'templates' && this.state.getState().templates.length === 0) {
                this.renderTemplates();
            } else if (tabName === 'favorites' && rwpCaptionWriter.isLoggedIn) {
                this.loadFavorites();
            }
        }
        
        async generateCaptions() {
            const state = this.state.getState();
            const description = this.elements.descriptionInput?.value?.trim() || state.description;
            
            if (!description) {
                this.showError(rwpCaptionWriter.strings.errorDescription);
                return;
            }
            
            this.state.setState({ isGenerating: true });
            this.showLoading(true);
            this.hideError();
            
            // Update generate button
            if (this.elements.generateBtn) {
                this.elements.generateBtn.textContent = rwpCaptionWriter.strings.processing;
                this.elements.generateBtn.disabled = true;
                this.elements.generateBtn.classList.add('loading');
            }
            
            try {
                // Make real API call to generate captions
                const response = await fetch(rwpCaptionWriter.restUrl + 'captions/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': rwpCaptionWriter.nonce
                    },
                    body: JSON.stringify({
                        description: description,
                        tone: state.tone,
                        platform: state.platform
                    })
                });
                
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.message || 'Failed to generate captions');
                }
                
                if (result.success && result.data) {
                    this.state.setState({ 
                        generatedCaptions: result.data,
                        isGenerating: false 
                    });
                    
                    this.renderGeneratedCaptions(result.data);
                    
                    // Show remaining quota if available
                    if (result.meta && typeof result.meta.remaining_quota !== 'undefined') {
                        this.showQuotaInfo(result.meta.remaining_quota);
                    }
                } else {
                    throw new Error(result.message || 'Unexpected response format');
                }
                
                this.showLoading(false);
                
            } catch (error) {
                console.error('Error generating captions:', error);
                
                // Show specific error message if available
                let errorMessage = rwpCaptionWriter.strings.errorGeneral;
                if (error.message) {
                    errorMessage = error.message;
                }
                
                this.showError(errorMessage);
                this.state.setState({ isGenerating: false });
                this.showLoading(false);
            }
            
            // Reset generate button
            if (this.elements.generateBtn) {
                this.elements.generateBtn.textContent = 'Generate Captions';
                this.elements.generateBtn.disabled = false;
                this.elements.generateBtn.classList.remove('loading');
            }
        }
        
        // Mock caption generation for Phase 1 - will be replaced with real API in Phase 2
        generateMockCaptions(description, tone, platform) {
            return new Promise((resolve) => {
                setTimeout(() => {
                    const toneVariations = {
                        casual: [
                            `Check out this amazing ${description}! ✨ Perfect for your feed. What do you think? {hashtags}`,
                            `Loving this ${description} moment! 💫 Sometimes the simple things bring the most joy. {hashtags}`,
                            `${description} vibes hitting different today 🔥 Who else is feeling this energy? {hashtags}`
                        ],
                        witty: [
                            `${description}? More like ${description} goals! 😎 {hashtags}`,
                            `Plot twist: ${description} was actually the main character all along 📸 {hashtags}`,
                            `Instructions unclear, ended up with this epic ${description} instead 🤷‍♀️ {hashtags}`
                        ],
                        inspirational: [
                            `Every ${description} tells a story of possibility ✨ What story will you write today? {hashtags}`,
                            `In a world full of ordinary, be a ${description} 🌟 Chase your dreams fearlessly. {hashtags}`,
                            `The beauty in ${description} reminds us that magic exists in everyday moments 💫 {hashtags}`
                        ],
                        question: [
                            `What's your favorite thing about ${description}? 🤔 Drop your thoughts below! {hashtags}`,
                            `${description}: love it or leave it? 💭 I'm curious to hear your take! {hashtags}`,
                            `Quick question: does this ${description} spark joy for you too? ✨ {hashtags}`
                        ],
                        professional: [
                            `Presenting: ${description}. Excellence in every detail. What are your thoughts on this approach? {hashtags}`,
                            `Today's focus: ${description}. Quality and innovation driving results. {hashtags}`,
                            `Strategic insight: ${description} represents the future of our industry. {hashtags}`
                        ]
                    };
                    
                    const captions = toneVariations[tone] || toneVariations.casual;
                    const result = captions.map(text => ({
                        text: text,
                        character_count: text.length
                    }));
                    
                    resolve(result);
                }, 1500);
            });
        }
        
        renderGeneratedCaptions(captions) {
            if (!this.elements.captionsList) return;
            
            this.elements.captionsList.innerHTML = '';
            
            captions.forEach((caption, index) => {
                const captionElement = document.createElement('div');
                captionElement.className = 'caption-option';
                captionElement.innerHTML = `
                    <div class="caption-text">${this.escapeHtml(caption.text)}</div>
                    <div class="caption-actions">
                        <span class="character-count">${caption.character_count} chars</span>
                        <button class="use-caption-btn" data-caption-index="${index}">
                            Use This
                        </button>
                    </div>
                `;
                
                // Add click handler for use button
                const useBtn = captionElement.querySelector('.use-caption-btn');
                useBtn.addEventListener('click', () => {
                    this.selectCaption(caption.text);
                });
                
                this.elements.captionsList.appendChild(captionElement);
            });
            
            // Show the captions container
            if (this.elements.captionsContainer) {
                this.elements.captionsContainer.style.display = 'block';
            }
        }
        
        selectCaption(captionText) {
            this.state.setState({ finalCaption: captionText });
            
            if (this.elements.finalCaptionTextarea) {
                this.elements.finalCaptionTextarea.value = captionText;
                this.updateCharacterCount();
            }
        }
        
        loadBuiltInTemplates() {
            const templates = [
                {
                    id: 'product-launch',
                    name: 'Product Launch',
                    category: 'business',
                    template: '🚀 Excited to introduce {product}!\n\n{description}\n\n✨ Key features:\n• {feature1}\n• {feature2}\n• {feature3}\n\nWhat do you think? Drop a 💭 below!\n\n{hashtags}',
                    variables: ['product', 'description', 'feature1', 'feature2', 'feature3', 'hashtags'],
                    platforms: ['instagram', 'facebook', 'linkedin']
                },
                {
                    id: 'behind-scenes',
                    name: 'Behind the Scenes',
                    category: 'personal',
                    template: 'Taking you behind the scenes of {activity} 🎬\n\n{insight}\n\nI never expected {surprise}! \n\nWhat\'s something surprising about your work?\n\n{hashtags}',
                    variables: ['activity', 'insight', 'surprise', 'hashtags'],
                    platforms: ['instagram', 'tiktok', 'facebook']
                },
                {
                    id: 'question-engage',
                    name: 'Engagement Question',
                    category: 'engagement',
                    template: '{question} 🤔\n\nA) {optionA}\nB) {optionB}\nC) {optionC}\n\nVote in the comments! I\'ll share the results in my stories.\n\n{hashtags}',
                    variables: ['question', 'optionA', 'optionB', 'optionC', 'hashtags'],
                    platforms: ['instagram', 'facebook', 'twitter']
                },
                {
                    id: 'motivational-monday',
                    name: 'Motivational Monday',
                    category: 'engagement',
                    template: '✨ Monday Motivation ✨\n\n{inspirational_message}\n\nRemember: {reminder}\n\nWhat\'s motivating you this week? Share below! 👇\n\n{hashtags}',
                    variables: ['inspirational_message', 'reminder', 'hashtags'],
                    platforms: ['instagram', 'linkedin', 'facebook']
                }
            ];
            
            this.state.setState({ templates });
            this.renderTemplates();
        }
        
        renderTemplates() {
            if (!this.elements.templatesGrid) return;
            
            const state = this.state.getState();
            const templates = state.templates;
            
            this.elements.templatesGrid.innerHTML = '';
            
            templates.forEach(template => {
                const templateElement = document.createElement('div');
                templateElement.className = 'template-card';
                templateElement.innerHTML = `
                    <div class="template-name">${this.escapeHtml(template.name)}</div>
                    <div class="template-category">${this.escapeHtml(template.category)}</div>
                    <div class="template-preview">${this.escapeHtml(template.template.substring(0, 100))}...</div>
                    <button class="use-template-btn" data-template-id="${template.id}">
                        Use Template
                    </button>
                `;
                
                // Add click handler
                const useBtn = templateElement.querySelector('.use-template-btn');
                useBtn.addEventListener('click', () => {
                    this.selectTemplate(template);
                });
                
                this.elements.templatesGrid.appendChild(templateElement);
            });
        }
        
        selectTemplate(template) {
            this.selectCaption(template.template);
        }
        
        filterTemplates(category) {
            const state = this.state.getState();
            const allTemplates = state.templates;
            
            if (category === 'all') {
                this.renderTemplatesFromArray(allTemplates);
            } else {
                const filteredTemplates = allTemplates.filter(template => 
                    template.category === category
                );
                this.renderTemplatesFromArray(filteredTemplates);
            }
        }
        
        renderTemplatesFromArray(templates) {
            if (!this.elements.templatesGrid) return;
            
            this.elements.templatesGrid.innerHTML = '';
            
            templates.forEach(template => {
                const templateElement = document.createElement('div');
                templateElement.className = 'template-card';
                templateElement.innerHTML = `
                    <div class="template-name">${this.escapeHtml(template.name)}</div>
                    <div class="template-category">${this.escapeHtml(template.category)}</div>
                    <div class="template-preview">${this.escapeHtml(template.template.substring(0, 100))}...</div>
                    <button class="use-template-btn" data-template-id="${template.id}">
                        Use Template
                    </button>
                `;
                
                const useBtn = templateElement.querySelector('.use-template-btn');
                useBtn.addEventListener('click', () => {
                    this.selectTemplate(template);
                });
                
                this.elements.templatesGrid.appendChild(templateElement);
            });
        }
        
        updateCharacterCount() {
            const state = this.state.getState();
            const text = this.elements.finalCaptionTextarea?.value || state.finalCaption;
            const count = text.length;
            const limit = state.characterLimit;
            
            this.state.setState({ characterCount: count });
            
            if (this.elements.charCount) {
                this.elements.charCount.textContent = count;
                
                // Update color based on limit
                if (count > limit) {
                    this.elements.charCount.className = 'character-count over-limit';
                } else if (count > limit * 0.9) {
                    this.elements.charCount.className = 'character-count warning';
                } else {
                    this.elements.charCount.className = 'character-count';
                }
            }
            
            if (this.elements.charLimit) {
                this.elements.charLimit.textContent = limit;
            }
        }
        
        updateCharacterLimit() {
            const platform = this.config.platform || 'instagram';
            const limit = this.getCharacterLimit(platform);
            this.state.setState({ characterLimit: limit });
            this.updateCharacterCount();
        }
        
        getCharacterLimit(platform) {
            return rwpCaptionWriter.characterLimits[platform] || 2200;
        }
        
        async copyToClipboard() {
            const text = this.elements.finalCaptionTextarea?.value || this.state.getState().finalCaption;
            
            if (!text) {
                this.showError('No caption to copy');
                return;
            }
            
            try {
                await navigator.clipboard.writeText(text);
                this.showSuccess(rwpCaptionWriter.strings.copySuccess);
            } catch (error) {
                console.error('Copy failed:', error);
                this.showError('Failed to copy caption');
            }
        }
        
        async saveToFavorites() {
            if (!rwpCaptionWriter.isLoggedIn) {
                this.showError(rwpCaptionWriter.strings.loginRequired);
                return;
            }
            
            const text = this.elements.finalCaptionTextarea?.value || this.state.getState().finalCaption;
            
            if (!text) {
                this.showError('No caption to save');
                return;
            }
            
            try {
                const response = await fetch(rwpCaptionWriter.restUrl + 'favorites', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': rwpCaptionWriter.nonce
                    },
                    body: JSON.stringify({
                        caption: text
                    })
                });
                
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.message || 'Failed to save favorite');
                }
                
                if (result.success) {
                    this.showSuccess(result.message || rwpCaptionWriter.strings.saveSuccess);
                } else {
                    throw new Error(result.message || 'Unexpected response');
                }
                
            } catch (error) {
                console.error('Error saving favorite:', error);
                this.showError(error.message || 'Failed to save favorite');
            }
        }
        
        async saveAsTemplate() {
            if (!rwpCaptionWriter.isLoggedIn) {
                this.showError(rwpCaptionWriter.strings.loginRequired);
                return;
            }
            
            const text = this.elements.finalCaptionTextarea?.value || this.state.getState().finalCaption;
            
            if (!text) {
                this.showError('No caption to save as template');
                return;
            }
            
            // Create a simple prompt for template name
            const templateName = prompt('Enter a name for this template:');
            if (!templateName || !templateName.trim()) {
                return;
            }
            
            const templateCategory = prompt('Enter category (business, personal, engagement, other):', 'other');
            if (!templateCategory || !templateCategory.trim()) {
                return;
            }
            
            try {
                const response = await fetch(rwpCaptionWriter.restUrl + 'templates', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': rwpCaptionWriter.nonce
                    },
                    body: JSON.stringify({
                        name: templateName.trim(),
                        category: templateCategory.trim(),
                        template: text,
                        platforms: [this.state.getState().platform]
                    })
                });
                
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.message || 'Failed to save template');
                }
                
                if (result.success) {
                    this.showSuccess(result.message || rwpCaptionWriter.strings.templateSuccess);
                } else {
                    throw new Error(result.message || 'Unexpected response');
                }
                
            } catch (error) {
                console.error('Error saving template:', error);
                this.showError(error.message || 'Failed to save template');
            }
        }
        
        async loadUserData() {
            // TODO: Load user favorites and templates in Phase 3
        }
        
        async loadFavorites() {
            if (!this.elements.favoritesList) return;
            
            try {
                const response = await fetch(rwpCaptionWriter.restUrl + 'favorites', {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': rwpCaptionWriter.nonce
                    }
                });
                
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.message || 'Failed to load favorites');
                }
                
                if (result.success && result.data) {
                    this.renderFavorites(result.data);
                } else {
                    throw new Error('Unexpected response format');
                }
                
            } catch (error) {
                console.error('Error loading favorites:', error);
                this.elements.favoritesList.innerHTML = `
                    <div class="no-favorites">
                        <p>No favorites saved yet. Generate some captions and save your favorites!</p>
                    </div>
                `;
            }
        }
        
        renderFavorites(favorites) {
            if (!this.elements.favoritesList) return;
            
            if (favorites.length === 0) {
                this.elements.favoritesList.innerHTML = `
                    <div class="no-favorites">
                        <p>No favorites saved yet. Generate some captions and save your favorites!</p>
                    </div>
                `;
                return;
            }
            
            this.elements.favoritesList.innerHTML = '';
            
            favorites.forEach(favorite => {
                const favoriteElement = document.createElement('div');
                favoriteElement.className = 'favorite-item';
                favoriteElement.innerHTML = `
                    <div class="favorite-text">${this.escapeHtml(favorite.caption)}</div>
                    <div class="favorite-meta">
                        <span class="favorite-date">${new Date(favorite.created_at).toLocaleDateString()}</span>
                    </div>
                    <div class="favorite-actions">
                        <button class="use-favorite-btn" data-favorite-id="${favorite.id}">Use This</button>
                        <button class="delete-favorite-btn" data-favorite-id="${favorite.id}">Delete</button>
                    </div>
                `;
                
                // Add event listeners
                const useBtn = favoriteElement.querySelector('.use-favorite-btn');
                const deleteBtn = favoriteElement.querySelector('.delete-favorite-btn');
                
                useBtn.addEventListener('click', () => {
                    this.selectCaption(favorite.caption);
                });
                
                deleteBtn.addEventListener('click', async () => {
                    if (confirm('Are you sure you want to delete this favorite?')) {
                        await this.deleteFavorite(favorite.id);
                    }
                });
                
                this.elements.favoritesList.appendChild(favoriteElement);
            });
        }
        
        async deleteFavorite(favoriteId) {
            try {
                const response = await fetch(rwpCaptionWriter.restUrl + `favorites/${favoriteId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': rwpCaptionWriter.nonce
                    }
                });
                
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.message || 'Failed to delete favorite');
                }
                
                if (result.success) {
                    // Reload favorites
                    this.loadFavorites();
                } else {
                    throw new Error('Unexpected response');
                }
                
            } catch (error) {
                console.error('Error deleting favorite:', error);
                this.showError(error.message || 'Failed to delete favorite');
            }
        }
        
        showLoading(show = true) {
            if (this.elements.loadingDiv) {
                this.elements.loadingDiv.style.display = show ? 'block' : 'none';
            }
        }
        
        showError(message) {
            if (this.elements.errorDiv) {
                this.elements.errorDiv.style.display = 'block';
                this.elements.errorDiv.querySelector('.error-message').textContent = message;
            }
        }
        
        hideError() {
            if (this.elements.errorDiv) {
                this.elements.errorDiv.style.display = 'none';
            }
        }
        
        showSuccess(message) {
            // Create a temporary success notification
            const notification = document.createElement('div');
            notification.className = 'caption-writer-success';
            notification.innerHTML = `
                <div class="success-message">${this.escapeHtml(message)}</div>
            `;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #d1e7dd;
                color: #0f5132;
                padding: 12px 16px;
                border-radius: 4px;
                border: 1px solid #badbcc;
                z-index: 10000;
                font-size: 14px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }
        
        showQuotaInfo(remainingQuota) {
            // Show quota information to user
            let quotaElement = this.container.querySelector('.quota-info');
            if (!quotaElement) {
                quotaElement = document.createElement('div');
                quotaElement.className = 'quota-info';
                
                const generateBtn = this.container.querySelector('[data-generate]');
                if (generateBtn && generateBtn.parentNode) {
                    generateBtn.parentNode.insertBefore(quotaElement, generateBtn.nextSibling);
                }
            }
            
            // Add icon based on quota level
            let icon = '🟢';
            if (remainingQuota <= 5) icon = '🟡';
            if (remainingQuota <= 2) icon = '🟠';
            if (remainingQuota === 0) icon = '🔴';
            
            quotaElement.innerHTML = `
                <span>${icon} Remaining AI generations: <strong>${remainingQuota}</strong></span>
            `;
            
            if (remainingQuota === 0) {
                quotaElement.innerHTML = `
                    <span>${icon} <strong>Daily limit reached.</strong> Please try again later or upgrade your plan.</span>
                `;
                // Disable generate button when quota is exhausted
                if (this.elements.generateBtn) {
                    this.elements.generateBtn.disabled = true;
                    this.elements.generateBtn.textContent = 'Quota Exhausted';
                }
            }
            
            // Add subtle animation
            quotaElement.style.animation = 'slideIn 0.3s ease';
        }
        
        validateDescription(description) {
            const minLength = 10;
            const maxLength = 500;
            
            // Remove any existing validation messages
            const existingMessages = this.container.querySelectorAll('.validation-message');
            existingMessages.forEach(msg => msg.remove());
            
            if (description.length < minLength && description.length > 0) {
                this.showValidationMessage(this.elements.descriptionInput, `Please provide more detail (${minLength - description.length} more characters needed)`, 'warning');
            } else if (description.length > maxLength) {
                this.showValidationMessage(this.elements.descriptionInput, `Description too long (${description.length - maxLength} characters over limit)`, 'error');
            }
        }
        
        updateGenerateButtonState() {
            if (!this.elements.generateBtn) return;
            
            const state = this.state.getState();
            const description = this.elements.descriptionInput?.value?.trim() || state.description;
            const isValid = description.length >= 10 && description.length <= 500;
            const isGenerating = state.isGenerating;
            
            this.elements.generateBtn.disabled = !isValid || isGenerating;
            
            if (!isValid && description.length > 0) {
                this.elements.generateBtn.title = description.length < 10 ? 'Please provide more detail' : 'Description too long';
            } else {
                this.elements.generateBtn.title = '';
            }
        }
        
        showValidationMessage(element, message, type = 'info') {
            const messageEl = document.createElement('div');
            messageEl.className = `validation-message validation-${type}`;
            messageEl.textContent = message;
            messageEl.style.cssText = `
                font-size: 12px;
                margin-top: 4px;
                padding: 6px 10px;
                border-radius: 4px;
                animation: slideIn 0.2s ease;
                ${type === 'error' ? 'background: #fee; color: #c53030; border: 1px solid #feb2b2;' : ''}
                ${type === 'warning' ? 'background: #fffbeb; color: #92400e; border: 1px solid #fed7aa;' : ''}
                ${type === 'info' ? 'background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;' : ''}
            `;
            
            element.parentNode.appendChild(messageEl);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.parentNode.removeChild(messageEl);
                }
            }, 3000);
        }
        
        announceToScreenReader(message) {
            // Create a live region for screen reader announcements
            let liveRegion = document.getElementById('rwp-caption-writer-live-region');
            if (!liveRegion) {
                liveRegion = document.createElement('div');
                liveRegion.id = 'rwp-caption-writer-live-region';
                liveRegion.setAttribute('aria-live', 'polite');
                liveRegion.setAttribute('aria-atomic', 'true');
                liveRegion.style.cssText = `
                    position: absolute;
                    left: -10000px;
                    width: 1px;
                    height: 1px;
                    overflow: hidden;
                `;
                document.body.appendChild(liveRegion);
            }
            
            liveRegion.textContent = message;
        }
        
        enhanceAccessibility() {
            // Add ARIA labels and descriptions
            if (this.elements.descriptionInput) {
                this.elements.descriptionInput.setAttribute('aria-describedby', 'description-help');
                
                // Add help text
                const helpText = document.createElement('div');
                helpText.id = 'description-help';
                helpText.className = 'sr-only';
                helpText.textContent = 'Describe your content in detail. Minimum 10 characters, maximum 500 characters.';
                this.elements.descriptionInput.parentNode.appendChild(helpText);
            }
            
            // Add role and aria-label to tabs
            if (this.elements.tabButtons.length > 0) {
                const tabList = this.elements.tabButtons[0].parentNode;
                tabList.setAttribute('role', 'tablist');
                
                this.elements.tabButtons.forEach((button, index) => {
                    button.setAttribute('role', 'tab');
                    button.setAttribute('aria-selected', button.classList.contains('active'));
                    button.setAttribute('tabindex', button.classList.contains('active') ? '0' : '-1');
                    button.setAttribute('aria-controls', `panel-${button.dataset.tab}`);
                    button.id = `tab-${button.dataset.tab}`;
                });
            }
            
            // Add role and aria-labelledby to tab panels
            this.elements.tabContents.forEach(content => {
                content.setAttribute('role', 'tabpanel');
                content.id = `panel-${content.dataset.content}`;
                content.setAttribute('aria-labelledby', `tab-${content.dataset.content}`);
                content.setAttribute('tabindex', '0');
            });
            
            // Add aria-live to captions container
            if (this.elements.captionsContainer) {
                this.elements.captionsContainer.setAttribute('aria-live', 'polite');
            }
            
            // Add aria-describedby to character counter
            if (this.elements.finalCaptionTextarea && this.elements.charCount) {
                const counterId = 'char-counter-' + Date.now();
                this.elements.charCount.parentNode.id = counterId;
                this.elements.finalCaptionTextarea.setAttribute('aria-describedby', counterId);
            }
        }
        
        handleGuestExperience() {
            // For guests, disable AI generator functionality and show only templates
            // The PHP template already handles showing the teaser overlay for the AI generator
            
            // Disable any AI-related inputs that might still be present
            const aiInputs = this.container.querySelectorAll('[data-description], [data-tone], [data-generate]');
            aiInputs.forEach(input => {
                input.disabled = true;
            });
            
            // Ensure templates tab is available for guests
            const templatesTab = this.container.querySelector('[data-tab="templates"]');
            if (templatesTab) {
                templatesTab.style.display = 'block';
            }
            
            // Hide favorites tab for guests (already handled by PHP, but ensure JS consistency)
            const favoritesTab = this.container.querySelector('[data-tab="favorites"]');
            if (favoritesTab) {
                favoritesTab.style.display = 'none';
            }
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }
    
    // Initialize Caption Writer apps when DOM is loaded
    function initializeCaptionWriters() {
        const containers = document.querySelectorAll('.rwp-caption-writer-container');
        
        containers.forEach(container => {
            try {
                const config = JSON.parse(container.dataset.config || '{}');
                new CaptionWriterApp(container, config);
            } catch (error) {
                console.error('Failed to initialize Caption Writer:', error);
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCaptionWriters);
    } else {
        initializeCaptionWriters();
    }
    
})();