/**
 * Advanced UX Features Hooks
 * Phase 3 UI/UX Implementation
 * 
 * Provides intelligent content suggestions, smart clipboard functionality,
 * progressive enhancement detection, and context-aware help systems
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useAnnouncements } from './useAccessibility';

/**
 * Hook for intelligent content suggestions
 */
export const useContentSuggestions = (content = '', platforms = [], options = {}) => {
    const [suggestions, setSuggestions] = useState([]);
    const [isAnalyzing, setIsAnalyzing] = useState(false);
    const debounceTimer = useRef(null);
    
    const {
        debounceDelay = 300,
        enableHashtagSuggestions = true,
        enableEmojiSuggestions = true,
        enableLengthOptimization = true,
        enableEngagementTips = true
    } = options;
    
    const analyzePlatformRequirements = useCallback(() => {
        const platformRequirements = {
            instagram: { characterLimit: 2200, preferencesHashtags: true, preferencesEmojis: true },
            twitter: { characterLimit: 280, preferencesHashtags: true, preferencesEmojis: false },
            facebook: { characterLimit: 63206, preferencesHashtags: false, preferencesEmojis: false },
            linkedin: { characterLimit: 3000, preferencesHashtags: false, preferencesEmojis: false },
            tiktok: { characterLimit: 2200, preferencesHashtags: true, preferencesEmojis: true },
            youtube: { characterLimit: 5000, preferencesHashtags: true, preferencesEmojis: false },
            pinterest: { characterLimit: 500, preferencesHashtags: true, preferencesEmojis: false }
        };
        
        return platforms.map(platformId => ({
            id: platformId,
            ...platformRequirements[platformId] || { characterLimit: 280, preferencesHashtags: false, preferencesEmojis: false }
        }));
    }, [platforms]);
    
    const generateSuggestions = useCallback(() => {
        if (!content.trim()) {
            setSuggestions([]);
            return;
        }
        
        setIsAnalyzing(true);
        const newSuggestions = [];
        const platformReqs = analyzePlatformRequirements();
        
        // Character count suggestions
        if (enableLengthOptimization) {
            platformReqs.forEach(platform => {
                const charCount = content.length;
                const limit = platform.characterLimit;
                
                if (charCount > limit) {
                    newSuggestions.push({
                        id: `length-${platform.id}`,
                        type: 'warning',
                        platform: platform.id,
                        message: sprintf(
                            __('Content is %d characters over %s limit (%d)', 'rwp-creator-suite'),
                            charCount - limit,
                            platform.id.charAt(0).toUpperCase() + platform.id.slice(1),
                            limit
                        ),
                        action: 'truncate',
                        severity: 'high',
                        data: { charCount, limit, overage: charCount - limit }
                    });
                } else if (charCount > limit * 0.9) {
                    newSuggestions.push({
                        id: `length-warning-${platform.id}`,
                        type: 'info',
                        platform: platform.id,
                        message: sprintf(
                            __('Close to %s character limit (%d/%d)', 'rwp-creator-suite'),
                            platform.id.charAt(0).toUpperCase() + platform.id.slice(1),
                            charCount,
                            limit
                        ),
                        action: 'optimize',
                        severity: 'medium',
                        data: { charCount, limit, percentage: (charCount / limit) * 100 }
                    });
                }
            });
        }
        
        // Content quality suggestions
        const hasHashtags = /#[\w\d_]+/g.test(content);
        const hasMentions = /@[\w\d_]+/g.test(content);
        const hasEmojis = /[\u{1F600}-\u{1F64F}]|[\u{1F300}-\u{1F5FF}]|[\u{1F680}-\u{1F6FF}]|[\u{1F1E0}-\u{1F1FF}]/u.test(content);
        const hasLinks = /https?:\/\/\S+/g.test(content);
        
        // Hashtag suggestions
        if (enableHashtagSuggestions && !hasHashtags) {
            const hashtagPlatforms = platformReqs.filter(p => p.preferencesHashtags);
            if (hashtagPlatforms.length > 0) {
                newSuggestions.push({
                    id: 'hashtags',
                    type: 'suggestion',
                    message: __('Consider adding relevant hashtags for better reach', 'rwp-creator-suite'),
                    action: 'add_hashtags',
                    severity: 'low',
                    platforms: hashtagPlatforms.map(p => p.id),
                    data: { suggestedHashtags: generateHashtagSuggestions(content) }
                });
            }
        }
        
        // Emoji suggestions
        if (enableEmojiSuggestions && !hasEmojis) {
            const emojiPlatforms = platformReqs.filter(p => p.preferencesEmojis);
            if (emojiPlatforms.length > 0) {
                newSuggestions.push({
                    id: 'emojis',
                    type: 'suggestion',
                    message: sprintf(
                        __('Emojis can increase engagement on %s', 'rwp-creator-suite'),
                        emojiPlatforms.map(p => p.id.charAt(0).toUpperCase() + p.id.slice(1)).join(', ')
                    ),
                    action: 'add_emojis',
                    severity: 'low',
                    platforms: emojiPlatforms.map(p => p.id),
                    data: { suggestedEmojis: generateEmojiSuggestions(content) }
                });
            }
        }
        
        // Engagement optimization suggestions
        if (enableEngagementTips) {
            const sentences = content.split(/[.!?]+/).filter(s => s.trim());
            const hasQuestion = /\?/.test(content);
            const hasCallToAction = /\b(click|visit|check out|learn more|swipe|follow|like|share|comment)\b/i.test(content);
            
            if (!hasQuestion && !hasCallToAction) {
                newSuggestions.push({
                    id: 'engagement',
                    type: 'suggestion',
                    message: __('Add a question or call-to-action to boost engagement', 'rwp-creator-suite'),
                    action: 'add_engagement',
                    severity: 'low',
                    data: { 
                        suggestions: [
                            __('What do you think?', 'rwp-creator-suite'),
                            __('Share your thoughts below!', 'rwp-creator-suite'),
                            __('Double-tap if you agree!', 'rwp-creator-suite'),
                            __('Save this for later!', 'rwp-creator-suite')
                        ]
                    }
                });
            }
            
            if (sentences.length === 1 && content.length > 100) {
                newSuggestions.push({
                    id: 'readability',
                    type: 'suggestion',
                    message: __('Break up long sentences for better readability', 'rwp-creator-suite'),
                    action: 'improve_readability',
                    severity: 'low'
                });
            }
        }
        
        setSuggestions(newSuggestions);
        setTimeout(() => setIsAnalyzing(false), 200);
    }, [content, platforms, enableHashtagSuggestions, enableEmojiSuggestions, enableLengthOptimization, enableEngagementTips, analyzePlatformRequirements]);
    
    const generateHashtagSuggestions = (text) => {
        const commonHashtags = {
            business: ['#business', '#entrepreneur', '#startup', '#marketing'],
            lifestyle: ['#lifestyle', '#motivation', '#inspiration', '#wellness'],
            technology: ['#tech', '#innovation', '#ai', '#digital'],
            creative: ['#creative', '#design', '#art', '#photography']
        };
        
        // Simple keyword detection for hashtag suggestions
        const lowerText = text.toLowerCase();
        let suggestions = [];
        
        Object.entries(commonHashtags).forEach(([category, hashtags]) => {
            if (lowerText.includes(category)) {
                suggestions = [...suggestions, ...hashtags.slice(0, 2)];
            }
        });
        
        return suggestions.slice(0, 5);
    };
    
    const generateEmojiSuggestions = (text) => {
        const emojiMap = {
            happy: ['😊', '😄', '🎉'],
            success: ['🎯', '📈', '💪'],
            creative: ['🎨', '💡', '✨'],
            tech: ['💻', '🚀', '⚡'],
            food: ['🍕', '🍔', '🥗'],
            travel: ['✈️', '🌍', '📸']
        };
        
        const lowerText = text.toLowerCase();
        let suggestions = [];
        
        Object.entries(emojiMap).forEach(([category, emojis]) => {
            if (lowerText.includes(category)) {
                suggestions = [...suggestions, ...emojis.slice(0, 1)];
            }
        });
        
        return suggestions.slice(0, 3);
    };
    
    // Debounced content analysis
    useEffect(() => {
        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }
        
        debounceTimer.current = setTimeout(() => {
            generateSuggestions();
        }, debounceDelay);
        
        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [content, platforms, generateSuggestions, debounceDelay]);
    
    const applySuggestion = useCallback((suggestion, customData = {}) => {
        return {
            suggestion,
            data: { ...suggestion.data, ...customData }
        };
    }, []);
    
    const dismissSuggestion = useCallback((suggestionId) => {
        setSuggestions(prev => prev.filter(s => s.id !== suggestionId));
    }, []);
    
    return {
        suggestions,
        isAnalyzing,
        applySuggestion,
        dismissSuggestion,
        refreshSuggestions: generateSuggestions
    };
};

/**
 * Hook for smart clipboard functionality
 */
export const useSmartClipboard = () => {
    const [copied, setCopied] = useState(false);
    const [clipboardHistory, setClipboardHistory] = useState([]);
    const [lastCopiedItem, setLastCopiedItem] = useState(null);
    const { announce } = useAnnouncements();
    
    const copyToClipboard = useCallback(async (text, metadata = {}) => {
        try {
            await navigator.clipboard.writeText(text);
            
            const clipboardItem = {
                id: Date.now(),
                text,
                timestamp: new Date(),
                metadata: {
                    length: text.length,
                    platform: metadata.platform,
                    type: metadata.type || 'text',
                    ...metadata
                }
            };
            
            setCopied(true);
            setLastCopiedItem(clipboardItem);
            
            // Add to history (keep last 10 items)
            setClipboardHistory(prev => [clipboardItem, ...prev.slice(0, 9)]);
            
            // Announce to screen readers
            const announcement = metadata.platform 
                ? sprintf(
                    __('Content copied to clipboard for %s: %s', 'rwp-creator-suite'),
                    metadata.platform,
                    text.substring(0, 50) + (text.length > 50 ? '...' : '')
                )
                : sprintf(
                    __('Content copied to clipboard: %s', 'rwp-creator-suite'),
                    text.substring(0, 50) + (text.length > 50 ? '...' : '')
                );
            
            announce(announcement);
            
            // Provide haptic feedback if available
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
            
            setTimeout(() => setCopied(false), 2000);
            return true;
        } catch (error) {
            console.error('Copy failed:', error);
            announce(__('Failed to copy content to clipboard', 'rwp-creator-suite'), 'assertive');
            
            // Fallback for older browsers
            try {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                const success = document.execCommand('copy');
                document.body.removeChild(textArea);
                
                if (success) {
                    setCopied(true);
                    announce(__('Content copied using fallback method', 'rwp-creator-suite'));
                    setTimeout(() => setCopied(false), 2000);
                    return true;
                }
            } catch (fallbackError) {
                console.error('Fallback copy failed:', fallbackError);
            }
            
            return false;
        }
    }, [announce]);
    
    const copyMultiple = useCallback(async (items) => {
        const results = [];
        
        for (const item of items) {
            const result = await copyToClipboard(item.text, item.metadata);
            results.push({ ...item, success: result });
            
            // Brief delay between copies
            if (items.length > 1) {
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }
        
        announce(
            sprintf(
                __('Copied %d items to clipboard', 'rwp-creator-suite'),
                results.filter(r => r.success).length
            )
        );
        
        return results;
    }, [copyToClipboard, announce]);
    
    const clearHistory = useCallback(() => {
        setClipboardHistory([]);
        announce(__('Clipboard history cleared', 'rwp-creator-suite'));
    }, [announce]);
    
    const getHistoryByPlatform = useCallback((platform) => {
        return clipboardHistory.filter(item => item.metadata.platform === platform);
    }, [clipboardHistory]);
    
    return {
        copied,
        clipboardHistory,
        lastCopiedItem,
        copyToClipboard,
        copyMultiple,
        clearHistory,
        getHistoryByPlatform
    };
};

/**
 * Hook for progressive enhancement detection
 */
export const useProgressiveEnhancement = () => {
    const [capabilities, setCapabilities] = useState({
        clipboard: false,
        notifications: false,
        vibration: false,
        share: false,
        geolocation: false,
        serviceWorker: false,
        webGL: false,
        webAssembly: false,
        intersectionObserver: false,
        resizeObserver: false
    });
    
    const [connectionInfo, setConnectionInfo] = useState({
        effectiveType: '4g',
        downlink: 10,
        rtt: 100,
        saveData: false
    });
    
    useEffect(() => {
        const detectCapabilities = () => {
            const newCapabilities = {
                clipboard: navigator.clipboard !== undefined,
                notifications: 'Notification' in window,
                vibration: 'vibrate' in navigator,
                share: 'share' in navigator,
                geolocation: 'geolocation' in navigator,
                serviceWorker: 'serviceWorker' in navigator,
                webGL: (() => {
                    try {
                        const canvas = document.createElement('canvas');
                        return !!(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'));
                    } catch (e) {
                        return false;
                    }
                })(),
                webAssembly: 'WebAssembly' in window,
                intersectionObserver: 'IntersectionObserver' in window,
                resizeObserver: 'ResizeObserver' in window
            };
            
            setCapabilities(newCapabilities);
        };
        
        const detectConnection = () => {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            
            if (connection) {
                setConnectionInfo({
                    effectiveType: connection.effectiveType || '4g',
                    downlink: connection.downlink || 10,
                    rtt: connection.rtt || 100,
                    saveData: connection.saveData || false
                });
            }
        };
        
        detectCapabilities();
        detectConnection();
        
        // Listen for connection changes
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (connection) {
            connection.addEventListener('change', detectConnection);
            return () => connection.removeEventListener('change', detectConnection);
        }
    }, []);
    
    const isFeatureSupported = useCallback((feature) => {
        return capabilities[feature] || false;
    }, [capabilities]);
    
    const shouldUseLightweightMode = useCallback(() => {
        return connectionInfo.saveData || 
               connectionInfo.effectiveType === 'slow-2g' || 
               connectionInfo.effectiveType === '2g' ||
               connectionInfo.downlink < 1;
    }, [connectionInfo]);
    
    const getOptimalImageFormat = useCallback(() => {
        if (shouldUseLightweightMode()) return 'jpeg';
        
        // Check for modern image format support
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        
        // WebP support
        if (canvas.toDataURL('image/webp').indexOf('image/webp') === 5) {
            return 'webp';
        }
        
        return 'jpeg';
    }, [shouldUseLightweightMode]);
    
    return {
        capabilities,
        connectionInfo,
        isFeatureSupported,
        shouldUseLightweightMode,
        getOptimalImageFormat
    };
};

/**
 * Hook for context-aware help system
 */
export const useContextualHelp = (context = '') => {
    const [helpTopics, setHelpTopics] = useState([]);
    const [activeHelpTopic, setActiveHelpTopic] = useState(null);
    const [userInteractions, setUserInteractions] = useState([]);
    
    const helpContent = {
        'platform-selector': {
            title: __('Platform Selection', 'rwp-creator-suite'),
            content: __('Choose the social media platforms where you want to share your content. Each platform has different characteristics and requirements.', 'rwp-creator-suite'),
            tips: [
                __('Instagram works best with visual content and hashtags', 'rwp-creator-suite'),
                __('Twitter favors concise, engaging posts under 280 characters', 'rwp-creator-suite'),
                __('LinkedIn prefers professional, industry-focused content', 'rwp-creator-suite')
            ]
        },
        'content-input': {
            title: __('Content Creation', 'rwp-creator-suite'),
            content: __('Enter your content here. The AI will optimize it for your selected platforms.', 'rwp-creator-suite'),
            tips: [
                __('Write naturally - the AI will adapt your content for each platform', 'rwp-creator-suite'),
                __('Include key points you want to emphasize', 'rwp-creator-suite'),
                __('Longer content gives the AI more to work with', 'rwp-creator-suite')
            ]
        },
        'tone-selection': {
            title: __('Tone Selection', 'rwp-creator-suite'),
            content: __('Choose the tone that best matches your brand voice and audience.', 'rwp-creator-suite'),
            tips: [
                __('Professional tone works well for B2B content', 'rwp-creator-suite'),
                __('Casual tone is great for lifestyle and personal brands', 'rwp-creator-suite'),
                __('Enthusiastic tone can boost engagement', 'rwp-creator-suite')
            ]
        }
    };
    
    useEffect(() => {
        if (context && helpContent[context]) {
            setHelpTopics([helpContent[context]]);
            setActiveHelpTopic(helpContent[context]);
        }
    }, [context]);
    
    const trackInteraction = useCallback((action, data = {}) => {
        const interaction = {
            timestamp: new Date(),
            action,
            context,
            data
        };
        
        setUserInteractions(prev => [...prev.slice(-19), interaction]);
    }, [context]);
    
    const getSmartSuggestions = useCallback(() => {
        const recentInteractions = userInteractions.slice(-5);
        const suggestions = [];
        
        // Analyze patterns in user interactions
        const hasRepeatedErrors = recentInteractions.filter(i => i.action === 'error').length > 2;
        const hasRepeatedHelp = recentInteractions.filter(i => i.action === 'help-requested').length > 1;
        
        if (hasRepeatedErrors) {
            suggestions.push({
                type: 'tutorial',
                message: __('It looks like you might benefit from a quick tutorial', 'rwp-creator-suite'),
                action: 'show-tutorial'
            });
        }
        
        if (hasRepeatedHelp) {
            suggestions.push({
                type: 'documentation',
                message: __('Check out our detailed documentation for more help', 'rwp-creator-suite'),
                action: 'show-docs'
            });
        }
        
        return suggestions;
    }, [userInteractions]);
    
    return {
        helpTopics,
        activeHelpTopic,
        setActiveHelpTopic,
        trackInteraction,
        getSmartSuggestions,
        userInteractions
    };
};