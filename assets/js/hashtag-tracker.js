/**
 * Hashtag Tracker
 * 
 * Client-side hashtag tracking for RWP Creator Suite analytics.
 * Tracks user-added hashtags (NOT AI-generated) and user interactions.
 */

class RWPHashtagTracker {
    constructor() {
        this.apiUrl = rwpHashtagTracker.apiUrl;
        this.nonce = rwpHashtagTracker.nonce;
        this.debug = rwpHashtagTracker.debug;
        this.trackedHashtags = new Set();
        this.lastPlatform = '';
        this.lastTone = '';
        
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bindEvents());
        } else {
            this.bindEvents();
        }
    }

    bindEvents() {
        // Track hashtags in text inputs and textareas
        this.bindHashtagTracking();
        
        // Track platform and tone selections
        this.bindSelectionTracking();
        
        // Track feature usage
        this.bindFeatureTracking();
        
        // Track form submissions
        this.bindFormTracking();
    }

    bindHashtagTracking() {
        // Find all text inputs and textareas that might contain hashtags
        const inputSelectors = [
            'textarea[name*="description"]',
            'textarea[name*="content"]',
            'input[type="text"][name*="hashtag"]',
            'textarea[name*="hashtag"]',
            '.wp-block textarea',
            '.rwp-content-input textarea',
            '.rwp-caption-input textarea'
        ];

        inputSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                this.bindInputHashtagTracking(element);
            });
        });
    }

    bindInputHashtagTracking(element) {
        let lastValue = element.value;
        let debounceTimer = null;

        const trackHashtags = () => {
            const currentValue = element.value;
            const newHashtags = this.extractNewHashtags(lastValue, currentValue);
            
            if (newHashtags.length > 0) {
                const context = this.getInputContext(element);
                newHashtags.forEach(hashtag => {
                    this.trackHashtag(hashtag, context);
                });
            }
            
            lastValue = currentValue;
        };

        // Track on input with debouncing
        element.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(trackHashtags, 1000);
        });

        // Track on blur (immediate)
        element.addEventListener('blur', () => {
            clearTimeout(debounceTimer);
            trackHashtags();
        });
    }

    bindSelectionTracking() {
        // Track platform selection
        const platformSelectors = [
            'select[name*="platform"]',
            '.platform-selector select',
            '.rwp-platform-select',
            'input[name*="platform"]'
        ];

        platformSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                element.addEventListener('change', (e) => {
                    const platform = e.target.value;
                    if (platform && platform !== this.lastPlatform) {
                        this.trackAction('platform_selected', platform, {
                            previous_platform: this.lastPlatform,
                            feature: this.getCurrentFeature()
                        });
                        this.lastPlatform = platform;
                    }
                });
            });
        });

        // Track tone selection
        const toneSelectors = [
            'select[name*="tone"]',
            '.tone-selector select',
            '.rwp-tone-select',
            'input[name*="tone"]'
        ];

        toneSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                element.addEventListener('change', (e) => {
                    const tone = e.target.value;
                    if (tone && tone !== this.lastTone) {
                        this.trackAction('tone_selected', tone, {
                            platform: this.getCurrentPlatform(),
                            feature: this.getCurrentFeature()
                        });
                        this.lastTone = tone;
                    }
                });
            });
        });
    }

    bindFeatureTracking() {
        // Track button clicks for content generation
        const buttonSelectors = [
            '.rwp-generate-button',
            '.rwp-repurpose-button',
            '.rwp-caption-generate',
            'button[data-feature]'
        ];

        buttonSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                element.addEventListener('click', () => {
                    const feature = element.dataset.feature || this.getFeatureFromElement(element);
                    if (feature) {
                        this.trackAction('feature_used', feature, {
                            platform: this.getCurrentPlatform(),
                            tone: this.getCurrentTone()
                        });
                    }
                });
            });
        });
    }

    bindFormTracking() {
        // Track form submissions that generate content
        const forms = document.querySelectorAll('form[data-rwp-feature], .rwp-form');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                // Extract hashtags from all inputs before submission
                const formData = new FormData(form);
                const textInputs = form.querySelectorAll('textarea, input[type="text"]');
                
                textInputs.forEach(input => {
                    const hashtags = this.extractHashtagsFromText(input.value);
                    const context = this.getInputContext(input);
                    
                    hashtags.forEach(hashtag => {
                        this.trackHashtag(hashtag, {
                            ...context,
                            source: 'form_submission'
                        });
                    });
                });
            });
        });
    }

    extractNewHashtags(oldText, newText) {
        const oldHashtags = this.extractHashtagsFromText(oldText);
        const newHashtags = this.extractHashtagsFromText(newText);
        
        // Return only new hashtags that weren't in the old text
        return newHashtags.filter(hashtag => 
            !oldHashtags.includes(hashtag) && !this.trackedHashtags.has(hashtag)
        );
    }

    extractHashtagsFromText(text) {
        if (!text || typeof text !== 'string') return [];
        
        const hashtagRegex = /#([a-zA-Z0-9_]+)/g;
        const matches = [];
        let match;
        
        while ((match = hashtagRegex.exec(text)) !== null) {
            const hashtag = match[1].toLowerCase();
            if (!matches.includes(hashtag)) {
                matches.push(hashtag);
            }
        }
        
        return matches;
    }

    getInputContext(element) {
        return {
            platform: this.getCurrentPlatform(),
            tone: this.getCurrentTone(),
            feature: this.getCurrentFeature(),
            source: 'user_input'
        };
    }

    getCurrentPlatform() {
        // Try to find currently selected platform
        const platformElements = document.querySelectorAll('select[name*="platform"], input[name*="platform"]:checked');
        for (let element of platformElements) {
            if (element.value) return element.value;
        }
        return this.lastPlatform || '';
    }

    getCurrentTone() {
        // Try to find currently selected tone
        const toneElements = document.querySelectorAll('select[name*="tone"], input[name*="tone"]:checked');
        for (let element of toneElements) {
            if (element.value) return element.value;
        }
        return this.lastTone || '';
    }

    getCurrentFeature() {
        // Determine feature based on current page/block
        if (document.querySelector('.wp-block-rwp-creator-suite-caption-writer')) {
            return 'caption_writer';
        }
        if (document.querySelector('.wp-block-rwp-creator-suite-content-repurposer')) {
            return 'content_repurposer';
        }
        
        // Check URL or page title
        const url = window.location.href;
        if (url.includes('caption')) return 'caption_writer';
        if (url.includes('repurpose')) return 'content_repurposer';
        
        return 'unknown';
    }

    getFeatureFromElement(element) {
        // Try to determine feature from element context
        if (element.closest('.wp-block-rwp-creator-suite-caption-writer')) {
            return 'caption_writer';
        }
        if (element.closest('.wp-block-rwp-creator-suite-content-repurposer')) {
            return 'content_repurposer';
        }
        if (element.dataset.feature) {
            return element.dataset.feature;
        }
        return this.getCurrentFeature();
    }

    async trackHashtag(hashtag, context = {}) {
        if (!hashtag || this.trackedHashtags.has(hashtag)) {
            return;
        }

        this.trackedHashtags.add(hashtag);

        try {
            const response = await fetch(`${this.apiUrl}analytics/track-hashtag`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    hashtag: hashtag,
                    platform: context.platform || '',
                    tone: context.tone || '',
                    feature: context.feature || this.getCurrentFeature(),
                    source: context.source || 'user_input'
                })
            });

            const data = await response.json();
            
            if (this.debug && !data.success) {
                console.warn('Hashtag tracking failed:', data.message);
            }
        } catch (error) {
            if (this.debug) {
                console.error('Hashtag tracking error:', error);
            }
        }
    }

    async trackAction(action, value, context = {}) {
        try {
            const response = await fetch(`${this.apiUrl}analytics/track-action`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    action: action,
                    value: value,
                    context: context
                })
            });

            const data = await response.json();
            
            if (this.debug && !data.success) {
                console.warn('Action tracking failed:', data.message);
            }
        } catch (error) {
            if (this.debug) {
                console.error('Action tracking error:', error);
            }
        }
    }

    // Public API for manual tracking
    manualTrackHashtag(hashtag, context = {}) {
        this.trackHashtag(hashtag, context);
    }

    manualTrackAction(action, value, context = {}) {
        this.trackAction(action, value, context);
    }

    // Reset tracked hashtags (useful for SPA-like behavior)
    resetTrackedHashtags() {
        this.trackedHashtags.clear();
    }
}

// Initialize when script loads
if (typeof rwpHashtagTracker !== 'undefined') {
    window.rwpHashtagTrackerInstance = new RWPHashtagTracker();
    
    // Make public methods available globally
    window.rwpTrack = {
        hashtag: (hashtag, context) => window.rwpHashtagTrackerInstance.manualTrackHashtag(hashtag, context),
        action: (action, value, context) => window.rwpHashtagTrackerInstance.manualTrackAction(action, value, context),
        reset: () => window.rwpHashtagTrackerInstance.resetTrackedHashtags()
    };
}