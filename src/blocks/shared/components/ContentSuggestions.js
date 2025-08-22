/**
 * Intelligent Content Suggestions Component
 * Phase 3 UI/UX Implementation
 * 
 * Features:
 * - Real-time content analysis
 * - Platform-specific suggestions
 * - Actionable improvement tips
 * - Accessibility compliant
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useContentSuggestions } from '../hooks/useAdvancedUX';
import { useAnnouncements } from '../hooks/useAccessibility';
import { useInViewAnimation } from '../hooks/useAnimations';

const ContentSuggestions = ({
    content = '',
    platforms = [],
    onApplySuggestion = null,
    className = '',
    showHeader = true,
    maxSuggestions = 5
}) => {
    const [dismissedSuggestions, setDismissedSuggestions] = useState(new Set());
    const { suggestions, isAnalyzing, applySuggestion, dismissSuggestion } = useContentSuggestions(content, platforms);
    const { announce } = useAnnouncements();
    const [animationRef, isInView] = useInViewAnimation({ threshold: 0.1 });
    
    // Filter out dismissed suggestions and limit display
    const activeSuggestions = suggestions
        .filter(s => !dismissedSuggestions.has(s.id))
        .slice(0, maxSuggestions);
    
    if (activeSuggestions.length === 0 && !isAnalyzing) return null;
    
    const handleApplySuggestion = (suggestion) => {
        const result = applySuggestion(suggestion);
        
        if (onApplySuggestion) {
            onApplySuggestion(result);
        }
        
        // Announce the action
        announce(
            sprintf(
                __('Applied suggestion: %s', 'rwp-creator-suite'),
                suggestion.message
            )
        );
        
        // Dismiss the suggestion
        handleDismissSuggestion(suggestion.id);
    };
    
    const handleDismissSuggestion = (suggestionId) => {
        setDismissedSuggestions(prev => new Set([...prev, suggestionId]));
        dismissSuggestion(suggestionId);
        
        announce(__('Suggestion dismissed', 'rwp-creator-suite'));
    };
    
    const getSuggestionIcon = (type, severity) => {
        const iconMap = {
            warning: '⚠️',
            error: '❌',
            info: 'ℹ️',
            suggestion: '💡',
            tip: '✨'
        };
        
        return iconMap[type] || iconMap.suggestion;
    };
    
    const getSuggestionActionLabel = (action) => {
        const actionLabels = {
            truncate: __('Shorten', 'rwp-creator-suite'),
            optimize: __('Optimize', 'rwp-creator-suite'),
            add_hashtags: __('Add Hashtags', 'rwp-creator-suite'),
            add_emojis: __('Add Emojis', 'rwp-creator-suite'),
            add_engagement: __('Boost Engagement', 'rwp-creator-suite'),
            improve_readability: __('Improve', 'rwp-creator-suite'),
            fix_formatting: __('Fix Format', 'rwp-creator-suite')
        };
        
        return actionLabels[action] || __('Apply', 'rwp-creator-suite');
    };
    
    const getSeverityClassName = (severity) => {
        const severityMap = {
            high: 'blk-bg-red-50 blk-border-red-200 blk-text-red-900',
            medium: 'blk-bg-yellow-50 blk-border-yellow-200 blk-text-yellow-900',
            low: 'blk-bg-blue-50 blk-border-blue-200 blk-text-blue-900'
        };
        
        return severityMap[severity] || severityMap.low;
    };
    
    const renderSuggestion = (suggestion, index) => {
        const severityClass = getSeverityClassName(suggestion.severity);
        const icon = getSuggestionIcon(suggestion.type, suggestion.severity);
        
        return (
            <div
                key={suggestion.id}
                className={`
                    blk-p-4 blk-rounded-lg blk-border blk-mb-3 blk-transition-all blk-duration-200
                    ${severityClass}
                    ${isInView ? 'blk-animate-slide-up' : 'blk-opacity-0 blk-translate-y-4'}
                `}
                style={{ animationDelay: `${index * 0.1}s` }}
                role="alert"
                aria-live="polite"
            >
                <div className="blk-flex blk-items-start blk-gap-3">
                    {/* Icon */}
                    <div 
                        className="blk-flex-shrink-0 blk-text-lg" 
                        aria-hidden="true"
                    >
                        {icon}
                    </div>
                    
                    {/* Content */}
                    <div className="blk-flex-1 blk-min-w-0">
                        {/* Message */}
                        <p className="blk-text-sm blk-font-medium blk-mb-2">
                            {suggestion.message}
                        </p>
                        
                        {/* Platform badges */}
                        {suggestion.platforms && (
                            <div className="blk-flex blk-flex-wrap blk-gap-1 blk-mb-2">
                                {suggestion.platforms.map(platform => (
                                    <span
                                        key={platform}
                                        className="blk-px-2 blk-py-1 blk-bg-gray-100 blk-text-gray-700 blk-text-xs blk-rounded blk-font-medium"
                                    >
                                        {platform.charAt(0).toUpperCase() + platform.slice(1)}
                                    </span>
                                ))}
                            </div>
                        )}
                        
                        {/* Additional data display */}
                        {suggestion.data && (
                            <div className="blk-mb-3">
                                {/* Character limit info */}
                                {suggestion.data.charCount && suggestion.data.limit && (
                                    <div className="blk-text-xs blk-text-gray-600 blk-mb-1">
                                        {sprintf(
                                            __('Current: %d characters, Limit: %d', 'rwp-creator-suite'),
                                            suggestion.data.charCount,
                                            suggestion.data.limit
                                        )}
                                    </div>
                                )}
                                
                                {/* Suggested hashtags */}
                                {suggestion.data.suggestedHashtags && (
                                    <div className="blk-mb-2">
                                        <div className="blk-text-xs blk-text-gray-600 blk-mb-1">
                                            {__('Suggested hashtags:', 'rwp-creator-suite')}
                                        </div>
                                        <div className="blk-flex blk-flex-wrap blk-gap-1">
                                            {suggestion.data.suggestedHashtags.map(hashtag => (
                                                <span
                                                    key={hashtag}
                                                    className="blk-px-2 blk-py-1 blk-bg-blue-100 blk-text-blue-800 blk-text-xs blk-rounded"
                                                >
                                                    {hashtag}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                
                                {/* Suggested emojis */}
                                {suggestion.data.suggestedEmojis && (
                                    <div className="blk-mb-2">
                                        <div className="blk-text-xs blk-text-gray-600 blk-mb-1">
                                            {__('Suggested emojis:', 'rwp-creator-suite')}
                                        </div>
                                        <div className="blk-flex blk-gap-1">
                                            {suggestion.data.suggestedEmojis.map((emoji, idx) => (
                                                <span
                                                    key={idx}
                                                    className="blk-text-base"
                                                    role="img"
                                                    aria-label={`Emoji ${idx + 1}`}
                                                >
                                                    {emoji}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                
                                {/* Engagement suggestions */}
                                {suggestion.data.suggestions && (
                                    <div className="blk-mb-2">
                                        <div className="blk-text-xs blk-text-gray-600 blk-mb-1">
                                            {__('Try adding:', 'rwp-creator-suite')}
                                        </div>
                                        <div className="blk-space-y-1">
                                            {suggestion.data.suggestions.slice(0, 3).map((text, idx) => (
                                                <div
                                                    key={idx}
                                                    className="blk-text-xs blk-italic blk-text-gray-700"
                                                >
                                                    "{text}"
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                        
                        {/* Actions */}
                        <div className="blk-flex blk-items-center blk-gap-2">
                            {suggestion.action && onApplySuggestion && (
                                <button
                                    type="button"
                                    className="blk-btn-enhanced blk-text-xs blk-px-3 blk-py-1.5 blk-bg-white blk-border blk-border-gray-300 blk-rounded blk-font-medium blk-transition-colors hover:blk-bg-gray-50 focus:blk-outline-none focus:blk-ring-2 focus:blk-ring-blue-500 focus:blk-ring-offset-1"
                                    onClick={() => handleApplySuggestion(suggestion)}
                                    aria-label={sprintf(
                                        __('Apply suggestion: %s', 'rwp-creator-suite'),
                                        suggestion.message
                                    )}
                                >
                                    {getSuggestionActionLabel(suggestion.action)}
                                </button>
                            )}
                            
                            <button
                                type="button"
                                className="blk-text-xs blk-text-gray-500 hover:blk-text-gray-700 blk-transition-colors blk-touch-target"
                                onClick={() => handleDismissSuggestion(suggestion.id)}
                                aria-label={sprintf(
                                    __('Dismiss suggestion: %s', 'rwp-creator-suite'),
                                    suggestion.message
                                )}
                            >
                                {__('Dismiss', 'rwp-creator-suite')}
                            </button>
                        </div>
                    </div>
                    
                    {/* Close button */}
                    <button
                        type="button"
                        className="blk-flex-shrink-0 blk-p-1 blk-text-gray-400 hover:blk-text-gray-600 blk-rounded blk-transition-colors blk-touch-target"
                        onClick={() => handleDismissSuggestion(suggestion.id)}
                        aria-label={__('Close suggestion', 'rwp-creator-suite')}
                    >
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
                            <path d="M8.207 7l3.147-3.146a.854.854 0 00-1.208-1.208L7 5.793 3.854 2.646a.854.854 0 00-1.208 1.208L5.793 7 2.646 10.146a.854.854 0 001.208 1.208L7 8.207l3.146 3.147a.854.854 0 001.208-1.208L8.207 7z"/>
                        </svg>
                    </button>
                </div>
            </div>
        );
    };
    
    return (
        <div 
            ref={animationRef}
            className={`blk-content-suggestions ${className}`}
            role="region"
            aria-label={__('Content suggestions', 'rwp-creator-suite')}
        >
            {showHeader && (
                <div className="blk-flex blk-items-center blk-justify-between blk-mb-4">
                    <h4 className="blk-text-base blk-font-semibold blk-text-gray-900 blk-m-0">
                        {__('Content Suggestions', 'rwp-creator-suite')}
                    </h4>
                    
                    {isAnalyzing && (
                        <div className="blk-flex blk-items-center blk-gap-2 blk-text-sm blk-text-gray-500">
                            <div className="blk-loading-dots">
                                <div className="blk-loading-dot"></div>
                                <div className="blk-loading-dot"></div>
                                <div className="blk-loading-dot"></div>
                            </div>
                            <span>{__('Analyzing...', 'rwp-creator-suite')}</span>
                        </div>
                    )}
                </div>
            )}
            
            {/* Suggestions List */}
            {activeSuggestions.length > 0 && (
                <div className="blk-suggestions-list">
                    {activeSuggestions.map(renderSuggestion)}
                </div>
            )}
            
            {/* Empty state during analysis */}
            {isAnalyzing && activeSuggestions.length === 0 && (
                <div className="blk-p-8 blk-text-center blk-text-gray-500">
                    <div className="blk-loading-dots blk-mb-2 blk-justify-center">
                        <div className="blk-loading-dot"></div>
                        <div className="blk-loading-dot"></div>
                        <div className="blk-loading-dot"></div>
                    </div>
                    <p className="blk-text-sm">{__('Analyzing your content...', 'rwp-creator-suite')}</p>
                </div>
            )}
            
            {/* Status for screen readers */}
            <div className="blk-sr-only" aria-live="polite" aria-atomic="true">
                {isAnalyzing ? (
                    __('Analyzing content for suggestions', 'rwp-creator-suite')
                ) : activeSuggestions.length > 0 ? (
                    sprintf(
                        _n(
                            '%d suggestion available',
                            '%d suggestions available',
                            activeSuggestions.length,
                            'rwp-creator-suite'
                        ),
                        activeSuggestions.length
                    )
                ) : (
                    __('No suggestions at this time', 'rwp-creator-suite')
                )}
            </div>
        </div>
    );
};

export default ContentSuggestions;