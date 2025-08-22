/**
 * Smart Clipboard Component
 * Phase 3 UI/UX Implementation
 * 
 * Features:
 * - Enhanced copy feedback with animations
 * - Clipboard history management
 * - Platform-specific copy actions
 * - Progressive enhancement support
 * - Accessibility compliant
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useSmartClipboard, useProgressiveEnhancement } from '../hooks/useAdvancedUX';
import { useLoadingAnimation } from '../hooks/useAnimations';

const SmartCopyButton = ({
    content,
    platform = null,
    variant = 'primary', // 'primary', 'secondary', 'minimal'
    size = 'medium', // 'small', 'medium', 'large'
    showFeedback = true,
    className = '',
    children = null,
    metadata = {},
    onCopyComplete = null,
    disabled = false,
    ...props
}) => {
    const { copied, copyToClipboard } = useSmartClipboard();
    const { isFeatureSupported } = useProgressiveEnhancement();
    const { showSuccess, animationPhase } = useLoadingAnimation(false);
    const [isProcessing, setIsProcessing] = useState(false);
    
    const hasClipboardSupport = isFeatureSupported('clipboard');
    
    const handleCopy = async () => {
        if (disabled || !content) return;
        
        setIsProcessing(true);
        
        const copyMetadata = {
            platform,
            timestamp: new Date().toISOString(),
            ...metadata
        };
        
        const success = await copyToClipboard(content, copyMetadata);
        
        if (success && onCopyComplete) {
            onCopyComplete({ content, platform, metadata: copyMetadata });
        }
        
        setIsProcessing(false);
    };
    
    // Get button classes based on variant and size
    const getButtonClasses = () => {
        const baseClasses = 'blk-btn-enhanced blk-touch-target blk-transition-all blk-duration-200 blk-ease-in-out blk-relative blk-overflow-hidden';
        
        const variantClasses = {
            primary: 'blk-bg-blue-600 blk-text-white blk-border blk-border-blue-600 hover:blk-bg-blue-700 hover:blk-border-blue-700 focus:blk-ring-2 focus:blk-ring-blue-500 focus:blk-ring-offset-2',
            secondary: 'blk-bg-white blk-text-blue-600 blk-border blk-border-blue-600 hover:blk-bg-blue-50 focus:blk-ring-2 focus:blk-ring-blue-500 focus:blk-ring-offset-2',
            minimal: 'blk-bg-transparent blk-text-gray-600 blk-border blk-border-gray-300 hover:blk-bg-gray-50 hover:blk-text-gray-900 focus:blk-ring-2 focus:blk-ring-gray-500 focus:blk-ring-offset-2'
        };
        
        const sizeClasses = {
            small: 'blk-px-2 blk-py-1 blk-text-xs blk-rounded',
            medium: 'blk-px-4 blk-py-2 blk-text-sm blk-rounded-md',
            large: 'blk-px-6 blk-py-3 blk-text-base blk-rounded-lg'
        };
        
        const stateClasses = [
            disabled ? 'blk-opacity-50 blk-cursor-not-allowed' : '',
            copied ? 'blk-animate-scale-in' : '',
            isProcessing ? 'blk-cursor-wait' : ''
        ].filter(Boolean).join(' ');
        
        return [
            baseClasses,
            variantClasses[variant] || variantClasses.primary,
            sizeClasses[size] || sizeClasses.medium,
            stateClasses,
            className
        ].filter(Boolean).join(' ');
    };
    
    const renderButtonContent = () => {
        // Show success state
        if (copied && showFeedback) {
            return (
                <div className="blk-flex blk-items-center blk-gap-2">
                    <div className="blk-success-checkmark blk-animate-scale-in" />
                    <span>{__('Copied!', 'rwp-creator-suite')}</span>
                </div>
            );
        }
        
        // Show processing state
        if (isProcessing) {
            return (
                <div className="blk-flex blk-items-center blk-gap-2">
                    <div className="blk-loading-dots">
                        <div className="blk-loading-dot"></div>
                        <div className="blk-loading-dot"></div>
                        <div className="blk-loading-dot"></div>
                    </div>
                    <span>{__('Copying...', 'rwp-creator-suite')}</span>
                </div>
            );
        }
        
        // Custom children or default content
        if (children) {
            return children;
        }
        
        // Default copy icon and text
        return (
            <div className="blk-flex blk-items-center blk-gap-2">
                <svg 
                    width="16" 
                    height="16" 
                    viewBox="0 0 16 16" 
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path d="M4 4h8v8H4V4zm0-2a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2V4a2 2 0 00-2-2H4z"/>
                    <path d="M2 6a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" opacity="0.3"/>
                </svg>
                <span>
                    {platform 
                        ? sprintf(__('Copy for %s', 'rwp-creator-suite'), platform.charAt(0).toUpperCase() + platform.slice(1))
                        : __('Copy', 'rwp-creator-suite')
                    }
                </span>
            </div>
        );
    };
    
    // Fallback for browsers without clipboard support
    if (!hasClipboardSupport) {
        return (
            <div className="blk-p-2 blk-bg-yellow-50 blk-border blk-border-yellow-200 blk-rounded blk-text-sm">
                <p className="blk-text-yellow-800 blk-mb-2">
                    {__('Copy not supported. Select and copy manually:', 'rwp-creator-suite')}
                </p>
                <textarea
                    readOnly
                    value={content}
                    className="blk-w-full blk-p-2 blk-border blk-border-gray-300 blk-rounded blk-text-sm blk-bg-white"
                    rows={Math.min(content.split('\n').length, 4)}
                    onFocus={(e) => e.target.select()}
                />
            </div>
        );
    }
    
    return (
        <button
            type="button"
            className={getButtonClasses()}
            onClick={handleCopy}
            disabled={disabled || !content || isProcessing}
            aria-label={
                copied 
                    ? __('Content copied to clipboard', 'rwp-creator-suite')
                    : platform
                        ? sprintf(__('Copy content for %s', 'rwp-creator-suite'), platform)
                        : __('Copy content to clipboard', 'rwp-creator-suite')
            }
            {...props}
        >
            {renderButtonContent()}
            
            {/* Ripple effect */}
            <div className="blk-absolute blk-inset-0 blk-pointer-events-none blk-overflow-hidden">
                {copied && (
                    <div className="blk-ripple-effect blk-bg-white blk-opacity-20 blk-animate-ping" />
                )}
            </div>
        </button>
    );
};

const ClipboardHistory = ({
    maxItems = 10,
    platform = null,
    onItemSelect = null,
    className = ''
}) => {
    const { clipboardHistory, clearHistory, getHistoryByPlatform } = useSmartClipboard();
    const [isExpanded, setIsExpanded] = useState(false);
    
    const historyItems = platform 
        ? getHistoryByPlatform(platform).slice(0, maxItems)
        : clipboardHistory.slice(0, maxItems);
    
    if (historyItems.length === 0) {
        return null;
    }
    
    const handleItemSelect = (item) => {
        if (onItemSelect) {
            onItemSelect(item);
        }
    };
    
    const formatTimestamp = (timestamp) => {
        const now = new Date();
        const diff = now - new Date(timestamp);
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return __('Just now', 'rwp-creator-suite');
        if (minutes < 60) return sprintf(__('%d minutes ago', 'rwp-creator-suite'), minutes);
        if (hours < 24) return sprintf(__('%d hours ago', 'rwp-creator-suite'), hours);
        return sprintf(__('%d days ago', 'rwp-creator-suite'), days);
    };
    
    return (
        <div className={`blk-clipboard-history ${className}`}>
            <div className="blk-flex blk-items-center blk-justify-between blk-mb-3">
                <h5 className="blk-text-sm blk-font-semibold blk-text-gray-900 blk-m-0">
                    {platform 
                        ? sprintf(__('%s Clipboard History', 'rwp-creator-suite'), platform.charAt(0).toUpperCase() + platform.slice(1))
                        : __('Clipboard History', 'rwp-creator-suite')
                    }
                </h5>
                
                <div className="blk-flex blk-items-center blk-gap-2">
                    <button
                        type="button"
                        className="blk-text-xs blk-text-blue-600 hover:blk-text-blue-800 blk-transition-colors"
                        onClick={() => setIsExpanded(!isExpanded)}
                        aria-expanded={isExpanded}
                    >
                        {isExpanded ? __('Show Less', 'rwp-creator-suite') : __('Show All', 'rwp-creator-suite')}
                    </button>
                    
                    <button
                        type="button"
                        className="blk-text-xs blk-text-red-600 hover:blk-text-red-800 blk-transition-colors"
                        onClick={clearHistory}
                    >
                        {__('Clear', 'rwp-creator-suite')}
                    </button>
                </div>
            </div>
            
            <div className="blk-space-y-2">
                {historyItems.slice(0, isExpanded ? maxItems : 3).map((item, index) => (
                    <div
                        key={item.id}
                        className={`
                            blk-p-3 blk-bg-gray-50 blk-rounded blk-border blk-cursor-pointer blk-transition-all
                            hover:blk-bg-gray-100 hover:blk-border-gray-300
                            ${index < 3 ? 'blk-animate-slide-up' : ''}
                        `}
                        style={{ animationDelay: `${index * 0.05}s` }}
                        onClick={() => handleItemSelect(item)}
                        role="button"
                        tabIndex={0}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                handleItemSelect(item);
                            }
                        }}
                        aria-label={sprintf(
                            __('Clipboard item from %s', 'rwp-creator-suite'),
                            formatTimestamp(item.timestamp)
                        )}
                    >
                        <div className="blk-flex blk-items-start blk-justify-between blk-gap-3">
                            <div className="blk-flex-1 blk-min-w-0">
                                <p className="blk-text-sm blk-text-gray-900 blk-truncate">
                                    {item.text.length > 60 
                                        ? item.text.substring(0, 60) + '...' 
                                        : item.text
                                    }
                                </p>
                                
                                <div className="blk-flex blk-items-center blk-gap-2 blk-mt-1">
                                    <span className="blk-text-xs blk-text-gray-500">
                                        {formatTimestamp(item.timestamp)}
                                    </span>
                                    
                                    {item.metadata.platform && (
                                        <span className="blk-px-2 blk-py-0.5 blk-bg-gray-200 blk-text-gray-700 blk-text-xs blk-rounded">
                                            {item.metadata.platform.charAt(0).toUpperCase() + item.metadata.platform.slice(1)}
                                        </span>
                                    )}
                                    
                                    <span className="blk-text-xs blk-text-gray-400">
                                        {sprintf(__('%d chars', 'rwp-creator-suite'), item.metadata.length)}
                                    </span>
                                </div>
                            </div>
                            
                            <div className="blk-flex-shrink-0">
                                <SmartCopyButton
                                    content={item.text}
                                    platform={item.metadata.platform}
                                    size="small"
                                    variant="minimal"
                                    showFeedback={false}
                                >
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
                                        <path d="M3 3h6v6H3V3zm0-1.5A1.5 1.5 0 001.5 3v6A1.5 1.5 0 003 10.5h6A1.5 1.5 0 0010.5 9V3A1.5 1.5 0 009 1.5H3z"/>
                                    </svg>
                                </SmartCopyButton>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
            
            {/* Status for screen readers */}
            <div className="blk-sr-only" aria-live="polite">
                {sprintf(
                    _n(
                        '%d item in clipboard history',
                        '%d items in clipboard history',
                        historyItems.length,
                        'rwp-creator-suite'
                    ),
                    historyItems.length
                )}
            </div>
        </div>
    );
};

export { SmartCopyButton, ClipboardHistory };
export default SmartCopyButton;