/**
 * Enhanced Accessible Platform Selector Component
 * Phase 3 UI/UX Implementation
 * 
 * Features:
 * - Full keyboard navigation with arrow keys
 * - Screen reader announcements
 * - Focus management
 * - High contrast mode support
 * - Touch-friendly design
 * - WCAG 2.1 AA compliance
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { useKeyboardNavigation, useAnnouncements, useAccessibilityPreferences } from '../hooks/useAccessibility';

const AccessiblePlatformSelector = ({
    selectedPlatforms = [],
    onPlatformsChange,
    allowedPlatforms = null,
    maxSelections = null,
    isGuest = false,
    guestLimit = 3,
    showDescriptions = true,
    layout = 'grid',
    onFocusChange = null
}) => {
    const [platforms, setPlatforms] = useState([]);
    const [errors, setErrors] = useState([]);
    const [selectionCount, setSelectionCount] = useState(0);
    const gridRef = useRef(null);
    
    const { announce } = useAnnouncements();
    const { prefersReducedMotion, prefersHighContrast } = useAccessibilityPreferences();
    
    // Default platform configurations
    const defaultPlatforms = {
        instagram: {
            label: __('Instagram', 'rwp-creator-suite'),
            description: __('Square format, engaging visuals, hashtags', 'rwp-creator-suite'),
            icon: '📸',
            characteristics: ['visual', 'hashtags', 'stories'],
            maxLength: 2200,
            color: '#E4405F'
        },
        twitter: {
            label: __('Twitter/X', 'rwp-creator-suite'),
            description: __('Concise, engaging, trending topics', 'rwp-creator-suite'),
            icon: '🐦',
            characteristics: ['concise', 'trending', 'threads'],
            maxLength: 280,
            color: '#1DA1F2'
        },
        facebook: {
            label: __('Facebook', 'rwp-creator-suite'),
            description: __('Detailed posts, community engagement', 'rwp-creator-suite'),
            icon: '👥',
            characteristics: ['detailed', 'community', 'links'],
            maxLength: 63206,
            color: '#4267B2'
        },
        linkedin: {
            label: __('LinkedIn', 'rwp-creator-suite'),
            description: __('Professional tone, industry insights', 'rwp-creator-suite'),
            icon: '💼',
            characteristics: ['professional', 'industry', 'networking'],
            maxLength: 3000,
            color: '#2867B2'
        },
        tiktok: {
            label: __('TikTok', 'rwp-creator-suite'),
            description: __('Creative, trend-focused, viral content', 'rwp-creator-suite'),
            icon: '🎵',
            characteristics: ['creative', 'trends', 'viral'],
            maxLength: 2200,
            color: '#FF0050'
        },
        youtube: {
            label: __('YouTube', 'rwp-creator-suite'),
            description: __('Video descriptions, SEO optimized', 'rwp-creator-suite'),
            icon: '🎥',
            characteristics: ['video', 'seo', 'educational'],
            maxLength: 5000,
            color: '#FF0000'
        },
        pinterest: {
            label: __('Pinterest', 'rwp-creator-suite'),
            description: __('Visual discovery, SEO keywords', 'rwp-creator-suite'),
            icon: '📌',
            characteristics: ['visual', 'discovery', 'keywords'],
            maxLength: 500,
            color: '#BD081C'
        }
    };
    
    // Initialize platforms
    useEffect(() => {
        const availablePlatforms = allowedPlatforms || Object.keys(defaultPlatforms);
        const platformList = availablePlatforms.map((key) => ({
            value: key,
            ...defaultPlatforms[key]
        }));
        setPlatforms(platformList);
    }, [allowedPlatforms]);
    
    // Update selection count
    useEffect(() => {
        setSelectionCount(selectedPlatforms.length);
    }, [selectedPlatforms.length]);
    
    // Keyboard navigation setup
    const handlePlatformSelect = (platform, index) => {
        const isSelected = selectedPlatforms.includes(platform.value);
        handlePlatformChange(platform.value, !isSelected);
    };
    
    const { focusIndex, isNavigating, handleKeyDown } = useKeyboardNavigation(
        platforms,
        {
            onSelect: handlePlatformSelect,
            onEscape: () => {
                if (gridRef.current) {
                    gridRef.current.blur();
                }
            }
        }
    );
    
    // Handle focus change callback
    useEffect(() => {
        if (onFocusChange && isNavigating) {
            onFocusChange(platforms[focusIndex]);
        }
    }, [focusIndex, isNavigating, onFocusChange, platforms]);
    
    const handlePlatformChange = (platformValue, isChecked) => {
        const platform = platforms.find(p => p.value === platformValue);
        let newSelectedPlatforms;
        
        if (isChecked) {
            // Add platform
            newSelectedPlatforms = [...selectedPlatforms, platformValue];
            
            // Check limits
            const effectiveMaxSelections = isGuest ? guestLimit : maxSelections;
            if (effectiveMaxSelections && newSelectedPlatforms.length > effectiveMaxSelections) {
                const limitType = isGuest 
                    ? __('guest users', 'rwp-creator-suite')
                    : __('this feature', 'rwp-creator-suite');
                    
                const errorMessage = sprintf(
                    __('Maximum %d platforms allowed for %s', 'rwp-creator-suite'),
                    effectiveMaxSelections,
                    limitType
                );
                
                setErrors([errorMessage]);
                announce(errorMessage, 'assertive');
                return;
            }
            
            // Announce selection
            announce(
                sprintf(
                    __('%s selected. %d of %d platforms selected.', 'rwp-creator-suite'),
                    platform.label,
                    newSelectedPlatforms.length,
                    effectiveMaxSelections || platforms.length
                )
            );
        } else {
            // Remove platform
            newSelectedPlatforms = selectedPlatforms.filter(p => p !== platformValue);
            
            announce(
                sprintf(
                    __('%s removed. %d platforms selected.', 'rwp-creator-suite'),
                    platform.label,
                    newSelectedPlatforms.length
                )
            );
        }
        
        setErrors([]); // Clear errors on successful change
        onPlatformsChange(newSelectedPlatforms);
    };
    
    const renderPlatformItem = (platform, index) => {
        const isSelected = selectedPlatforms.includes(platform.value);
        const effectiveMaxSelections = isGuest ? guestLimit : maxSelections;
        const isDisabled = !isSelected && effectiveMaxSelections && selectedPlatforms.length >= effectiveMaxSelections;
        const isFocused = isNavigating && index === focusIndex;
        
        // Dynamic classes based on state and preferences
        const cardClasses = [
            'blk-platform-card',
            'blk-p-3 sm:blk-p-4',
            'blk-rounded-xl',
            'blk-text-center',
            'blk-relative',
            'blk-min-h-[70px] sm:blk-min-h-[80px]',
            'blk-flex blk-flex-col blk-items-center blk-justify-center',
            'blk-cursor-pointer',
            'blk-touch-target',
            prefersReducedMotion ? '' : 'blk-transition-all blk-duration-200 blk-ease-in-out',
            'blk-bg-gradient-to-br blk-from-gray-50 blk-to-white',
            'blk-border-2 blk-border-gray-200',
            isSelected ? 'blk-border-blue-500 blk-bg-gradient-to-br blk-from-blue-50 blk-to-white blk-shadow-lg blk-shadow-blue-500/15' : '',
            !isSelected && !isDisabled && !prefersReducedMotion ? 'hover:blk-shadow-lg hover:blk--translate-y-0.5' : '',
            isDisabled ? 'blk-opacity-60 blk-cursor-not-allowed hover:blk-shadow-none hover:blk-translate-y-0' : '',
            isFocused ? 'blk-focus-current' : ''
        ].filter(Boolean).join(' ');
        
        // Create unique ID for accessibility
        const cardId = `platform-card-${platform.value}`;
        const descriptionId = `platform-description-${platform.value}`;
        
        return (
            <div
                key={platform.value}
                id={cardId}
                className={cardClasses}
                onClick={() => !isDisabled && handlePlatformChange(platform.value, !isSelected)}
                role="checkbox"
                aria-checked={isSelected}
                aria-describedby={showDescriptions ? descriptionId : undefined}
                aria-disabled={isDisabled}
                aria-pressed={isSelected}
                tabIndex={-1} // Grid container manages focus
                onKeyDown={(e) => {
                    if ((e.key === 'Enter' || e.key === ' ') && !isDisabled) {
                        e.preventDefault();
                        handlePlatformChange(platform.value, !isSelected);
                    }
                }}
                style={prefersHighContrast ? {
                    borderWidth: '3px',
                    borderColor: isSelected ? '#000' : 'currentColor'
                } : {}}
            >
                <div 
                    className={`blk-text-xl sm:blk-text-2xl blk-mb-1 sm:blk-mb-2 ${prefersReducedMotion ? '' : 'blk-transition-opacity blk-duration-200'} ${isSelected ? 'blk-opacity-100' : 'blk-opacity-70'}`}
                    aria-hidden="true"
                >
                    {platform.icon}
                </div>
                
                <div className={`blk-text-xs sm:blk-text-sm blk-font-medium ${isSelected ? 'blk-text-gray-900 blk-font-semibold' : 'blk-text-gray-600'}`}>
                    {platform.label}
                </div>
                
                {showDescriptions && (
                    <div
                        id={descriptionId}
                        className="blk-sr-only"
                    >
                        {platform.description}. 
                        {sprintf(
                            __('Character limit: %d. Current status: %s', 'rwp-creator-suite'),
                            platform.maxLength,
                            isSelected ? __('selected', 'rwp-creator-suite') : __('not selected', 'rwp-creator-suite')
                        )}
                        {isDisabled ? '. ' + __('Selection disabled due to limit', 'rwp-creator-suite') : ''}
                    </div>
                )}
                
                {isSelected && (
                    <div className="blk-mt-2 blk-pt-2 blk-border-t blk-border-gray-200 blk-w-full">
                        <div className="blk-mb-1">
                            {platform.characteristics.map((char) => (
                                <span
                                    key={char}
                                    className="blk-inline-block blk-bg-blue-500 blk-text-white blk-text-xs blk-px-1.5 blk-py-0.5 blk-rounded blk-mr-1 blk-mb-1"
                                >
                                    {char}
                                </span>
                            ))}
                        </div>
                        <div className="blk-text-xs blk-text-gray-500">
                            {sprintf(
                                __('Max: %d chars', 'rwp-creator-suite'),
                                platform.maxLength
                            )}
                        </div>
                    </div>
                )}
                
                {/* Selection indicator for screen readers */}
                {isSelected && (
                    <span className="blk-sr-only">
                        {__('Selected', 'rwp-creator-suite')}
                    </span>
                )}
                
                {/* Disabled indicator for screen readers */}
                {isDisabled && (
                    <span className="blk-sr-only">
                        {__('Disabled - maximum selections reached', 'rwp-creator-suite')}
                    </span>
                )}
            </div>
        );
    };
    
    const getSelectionSummary = () => {
        const count = selectedPlatforms.length;
        const effectiveMaxSelections = isGuest ? guestLimit : maxSelections;
        
        if (count === 0) {
            return __('No platforms selected', 'rwp-creator-suite');
        }
        
        let summary = sprintf(
            _n(
                '%d platform selected',
                '%d platforms selected',
                count,
                'rwp-creator-suite'
            ),
            count
        );
        
        if (effectiveMaxSelections) {
            summary += sprintf(
                __(' (max %d)', 'rwp-creator-suite'),
                effectiveMaxSelections
            );
        }
        
        return summary;
    };
    
    return (
        <div className="blk-border blk-border-gray-300 blk-rounded-lg blk-p-4 blk-my-4">
            {/* Header with instructions */}
            <div className="blk-flex blk-justify-between blk-items-center blk-mb-4 blk-pb-2 blk-border-b blk-border-gray-200">
                <h4 className="blk-m-0 blk-text-base blk-font-semibold blk-text-contrast-high">
                    {__('Select Platforms', 'rwp-creator-suite')}
                </h4>
                <div className="blk-text-sm blk-text-contrast-medium" aria-live="polite">
                    {getSelectionSummary()}
                </div>
            </div>
            
            {/* Instructions for keyboard users */}
            <div className="blk-sr-only" id="platform-selector-instructions">
                {__('Use arrow keys to navigate between platforms. Press Enter or Space to select or deselect a platform. Press Escape to exit navigation.', 'rwp-creator-suite')}
            </div>
            
            {/* Error messages */}
            {errors.length > 0 && (
                <div role="alert" aria-live="assertive">
                    <Notice status="error" isDismissible={false}>
                        <ul className="blk-m-0 blk-p-0 blk-list-none">
                            {errors.map((error, index) => (
                                <li key={index}>{error}</li>
                            ))}
                        </ul>
                    </Notice>
                </div>
            )}
            
            {/* Guest limit notice */}
            {isGuest && (
                <Notice status="info" isDismissible={false}>
                    {sprintf(
                        __('Guest users can select up to %d platforms. Sign up for higher limits!', 'rwp-creator-suite'),
                        guestLimit
                    )}
                </Notice>
            )}
            
            {/* Platform grid with keyboard navigation */}
            <div
                ref={gridRef}
                className={`
                    blk-grid blk-grid-cols-[repeat(auto-fit,minmax(120px,1fr))] blk-gap-3 blk-my-4 
                    sm:blk-grid-cols-[repeat(auto-fit,minmax(140px,1fr))] lg:blk-gap-4
                    ${isNavigating ? 'blk-keyboard-nav-active' : ''}
                `}
                role="group"
                aria-label={__('Select social media platforms', 'rwp-creator-suite')}
                aria-describedby="platform-selector-instructions"
                onKeyDown={handleKeyDown}
                tabIndex={0}
                onFocus={() => {
                    if (!isNavigating) {
                        announce(__('Platform selector focused. Use arrow keys to navigate.', 'rwp-creator-suite'));
                    }
                }}
                onBlur={() => {
                    // Blur will be handled by the hook if needed
                }}
            >
                {platforms.map(renderPlatformItem)}
            </div>
            
            {/* Selected platforms summary */}
            {selectedPlatforms.length > 0 && (
                <div className="blk-mt-4 blk-pt-4 blk-border-t blk-border-gray-200">
                    <h5 className="blk-m-0 blk-mb-2 blk-text-sm blk-font-medium blk-text-contrast-high">
                        {__('Content will be optimized for:', 'rwp-creator-suite')}
                    </h5>
                    <div className="blk-flex blk-flex-wrap blk-gap-2" role="list">
                        {selectedPlatforms.map((platformValue) => {
                            const platform = platforms.find(p => p.value === platformValue);
                            return platform ? (
                                <span
                                    key={platformValue}
                                    className="blk-bg-green-500 blk-text-white blk-px-2 blk-py-1 blk-rounded blk-text-xs blk-font-medium"
                                    role="listitem"
                                >
                                    <span aria-hidden="true">{platform.icon}</span> {platform.label}
                                </span>
                            ) : null;
                        })}
                    </div>
                </div>
            )}
            
            {/* Status for screen readers */}
            <div className="blk-sr-only" aria-live="polite" aria-atomic="true">
                {selectedPlatforms.length > 0 
                    ? sprintf(
                        __('%d platforms selected for content optimization', 'rwp-creator-suite'),
                        selectedPlatforms.length
                    )
                    : __('No platforms selected', 'rwp-creator-suite')
                }
            </div>
        </div>
    );
};

export default AccessiblePlatformSelector;