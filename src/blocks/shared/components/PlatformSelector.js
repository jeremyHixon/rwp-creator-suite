/**
 * Platform Selector Component
 * 
 * Reusable React component for selecting social media platforms.
 * Provides consistent UI and behavior across all blocks.
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { CheckboxControl, PanelBody, Notice } from '@wordpress/components';

const PlatformSelector = ({
    selectedPlatforms = [],
    onPlatformsChange,
    allowedPlatforms = null,
    maxSelections = null,
    isGuest = false,
    guestLimit = 3,
    showDescriptions = true,
    layout = 'grid' // 'grid' or 'list'
}) => {
    const [platforms, setPlatforms] = useState([]);
    const [errors, setErrors] = useState([]);

    // Default platform configurations
    const defaultPlatforms = {
        instagram: {
            label: __('Instagram', 'rwp-creator-suite'),
            description: __('Square format, engaging visuals, hashtags', 'rwp-creator-suite'),
            icon: '📸',
            characteristics: ['visual', 'hashtags', 'stories'],
            maxLength: 2200
        },
        twitter: {
            label: __('Twitter/X', 'rwp-creator-suite'),
            description: __('Concise, engaging, trending topics', 'rwp-creator-suite'),
            icon: '🐦',
            characteristics: ['concise', 'trending', 'threads'],
            maxLength: 280
        },
        facebook: {
            label: __('Facebook', 'rwp-creator-suite'),
            description: __('Detailed posts, community engagement', 'rwp-creator-suite'),
            icon: '👥',
            characteristics: ['detailed', 'community', 'links'],
            maxLength: 63206
        },
        linkedin: {
            label: __('LinkedIn', 'rwp-creator-suite'),
            description: __('Professional tone, industry insights', 'rwp-creator-suite'),
            icon: '💼',
            characteristics: ['professional', 'industry', 'networking'],
            maxLength: 3000
        },
        tiktok: {
            label: __('TikTok', 'rwp-creator-suite'),
            description: __('Creative, trend-focused, viral content', 'rwp-creator-suite'),
            icon: '🎵',
            characteristics: ['creative', 'trends', 'viral'],
            maxLength: 2200
        },
        youtube: {
            label: __('YouTube', 'rwp-creator-suite'),
            description: __('Video descriptions, SEO optimized', 'rwp-creator-suite'),
            icon: '🎥',
            characteristics: ['video', 'seo', 'educational'],
            maxLength: 5000
        },
        pinterest: {
            label: __('Pinterest', 'rwp-creator-suite'),
            description: __('Visual discovery, SEO keywords', 'rwp-creator-suite'),
            icon: '📌',
            characteristics: ['visual', 'discovery', 'keywords'],
            maxLength: 500
        }
    };

    useEffect(() => {
        // Initialize platforms based on allowed platforms or use all
        const availablePlatforms = allowedPlatforms || Object.keys(defaultPlatforms);
        
        const platformList = availablePlatforms.map(key => ({
            value: key,
            ...defaultPlatforms[key]
        }));

        setPlatforms(platformList);
    }, [allowedPlatforms]);

    const handlePlatformChange = (platformValue, isChecked) => {
        let newSelectedPlatforms;

        if (isChecked) {
            // Add platform
            newSelectedPlatforms = [...selectedPlatforms, platformValue];

            // Check limits
            const effectiveMaxSelections = isGuest ? guestLimit : maxSelections;
            if (effectiveMaxSelections && newSelectedPlatforms.length > effectiveMaxSelections) {
                const limitType = isGuest ? __('guest users', 'rwp-creator-suite') : __('this feature', 'rwp-creator-suite');
                setErrors([
                    sprintf(
                        __('Maximum %d platforms allowed for %s', 'rwp-creator-suite'),
                        effectiveMaxSelections,
                        limitType
                    )
                ]);
                return;
            }
        } else {
            // Remove platform
            newSelectedPlatforms = selectedPlatforms.filter(p => p !== platformValue);
        }

        setErrors([]); // Clear errors on successful change
        onPlatformsChange(newSelectedPlatforms);
    };

    const renderPlatformItem = (platform) => {
        const isSelected = selectedPlatforms.includes(platform.value);
        const effectiveMaxSelections = isGuest ? guestLimit : maxSelections;
        const isDisabled = !isSelected && effectiveMaxSelections && 
                          selectedPlatforms.length >= effectiveMaxSelections;

        return (
            <div 
                key={platform.value}
                className={`rwp-platform-item ${isSelected ? 'selected' : ''} ${isDisabled ? 'disabled' : ''}`}
            >
                <CheckboxControl
                    label={
                        <div className="rwp-platform-label">
                            <span className="platform-icon">{platform.icon}</span>
                            <span className="platform-name">{platform.label}</span>
                            {showDescriptions && (
                                <span className="platform-description">{platform.description}</span>
                            )}
                        </div>
                    }
                    checked={isSelected}
                    onChange={(checked) => handlePlatformChange(platform.value, checked)}
                    disabled={isDisabled}
                />
                
                {isSelected && (
                    <div className="platform-details">
                        <div className="platform-characteristics">
                            {platform.characteristics.map(char => (
                                <span key={char} className="characteristic-tag">
                                    {char}
                                </span>
                            ))}
                        </div>
                        <div className="platform-limits">
                            {sprintf(
                                __('Max length: %d characters', 'rwp-creator-suite'),
                                platform.maxLength
                            )}
                        </div>
                    </div>
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
            _n('%d platform selected', '%d platforms selected', count, 'rwp-creator-suite'),
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
        <div className={`rwp-platform-selector layout-${layout}`}>
            <div className="platform-selector-header">
                <h4>{__('Select Platforms', 'rwp-creator-suite')}</h4>
                <div className="selection-summary">
                    {getSelectionSummary()}
                </div>
            </div>

            {errors.length > 0 && (
                <Notice status="error" isDismissible={false}>
                    <ul>
                        {errors.map((error, index) => (
                            <li key={index}>{error}</li>
                        ))}
                    </ul>
                </Notice>
            )}

            {isGuest && (
                <Notice status="info" isDismissible={false}>
                    {sprintf(
                        __('Guest users can select up to %d platforms. Sign up for higher limits!', 'rwp-creator-suite'),
                        guestLimit
                    )}
                </Notice>
            )}

            <div className="platforms-grid">
                {platforms.map(renderPlatformItem)}
            </div>

            {selectedPlatforms.length > 0 && (
                <div className="selected-platforms-summary">
                    <h5>{__('Content will be optimized for:', 'rwp-creator-suite')}</h5>
                    <div className="selected-list">
                        {selectedPlatforms.map(platformValue => {
                            const platform = platforms.find(p => p.value === platformValue);
                            return platform ? (
                                <span key={platformValue} className="selected-platform-tag">
                                    {platform.icon} {platform.label}
                                </span>
                            ) : null;
                        })}
                    </div>
                </div>
            )}

            <style jsx>{`
                .rwp-platform-selector {
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 16px;
                    margin: 16px 0;
                }

                .platform-selector-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 16px;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 8px;
                }

                .platform-selector-header h4 {
                    margin: 0;
                }

                .selection-summary {
                    font-size: 14px;
                    color: #666;
                }

                .platforms-grid {
                    display: grid;
                    gap: 12px;
                }

                .layout-grid .platforms-grid {
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                }

                .layout-list .platforms-grid {
                    grid-template-columns: 1fr;
                }

                .rwp-platform-item {
                    border: 1px solid #e0e0e0;
                    border-radius: 6px;
                    padding: 12px;
                    transition: all 0.2s ease;
                }

                .rwp-platform-item:hover {
                    border-color: #007cba;
                    background-color: #f8f9fa;
                }

                .rwp-platform-item.selected {
                    border-color: #007cba;
                    background-color: #e7f3ff;
                }

                .rwp-platform-item.disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }

                .rwp-platform-label {
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                }

                .platform-icon {
                    font-size: 18px;
                    line-height: 1;
                }

                .platform-name {
                    font-weight: 600;
                    color: #1e1e1e;
                }

                .platform-description {
                    font-size: 13px;
                    color: #666;
                    margin-left: auto;
                    text-align: right;
                    max-width: 200px;
                }

                .platform-details {
                    margin-top: 8px;
                    padding-top: 8px;
                    border-top: 1px solid #e0e0e0;
                }

                .platform-characteristics {
                    margin-bottom: 4px;
                }

                .characteristic-tag {
                    display: inline-block;
                    background: #007cba;
                    color: white;
                    font-size: 11px;
                    padding: 2px 6px;
                    border-radius: 3px;
                    margin-right: 4px;
                }

                .platform-limits {
                    font-size: 12px;
                    color: #666;
                }

                .selected-platforms-summary {
                    margin-top: 16px;
                    padding-top: 16px;
                    border-top: 1px solid #eee;
                }

                .selected-platforms-summary h5 {
                    margin: 0 0 8px 0;
                    font-size: 14px;
                }

                .selected-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                }

                .selected-platform-tag {
                    background: #28a745;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 500;
                }
            `}</style>
        </div>
    );
};

export default PlatformSelector;