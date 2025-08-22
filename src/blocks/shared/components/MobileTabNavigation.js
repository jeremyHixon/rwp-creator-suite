/**
 * Mobile-Optimized Tab Navigation Component
 * Phase 3 UI/UX Implementation
 * 
 * Features:
 * - Swipe gesture support
 * - Horizontal scrolling optimization
 * - Touch-friendly tab sizing
 * - Keyboard navigation support
 * - Accessibility compliance
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import { useSwipeGesture, useDeviceCapabilities } from '../hooks/useMobileGestures';
import { useAnnouncements } from '../hooks/useAccessibility';

const MobileTabNavigation = ({
    tabs = [],
    activeTab,
    onTabChange,
    enableSwipe = true,
    showIcons = true,
    size = 'medium', // 'small', 'medium', 'large'
    orientation = 'horizontal',
    className = ''
}) => {
    const [focusedTabIndex, setFocusedTabIndex] = useState(0);
    const [isKeyboardNavigating, setIsKeyboardNavigating] = useState(false);
    const tabListRef = useRef(null);
    const tabRefs = useRef([]);
    
    const { announce } = useAnnouncements();
    const { screenSize, hasTouch } = useDeviceCapabilities();
    
    // Swipe gesture handling
    const { onTouchStart, onTouchMove, onTouchEnd } = useSwipeGesture(
        (direction) => {
            if (!enableSwipe) return;
            
            const currentIndex = tabs.findIndex(tab => tab.id === activeTab);
            let newIndex;
            
            if (direction === 'left' && currentIndex < tabs.length - 1) {
                newIndex = currentIndex + 1;
            } else if (direction === 'right' && currentIndex > 0) {
                newIndex = currentIndex - 1;
            }
            
            if (newIndex !== undefined && tabs[newIndex]) {
                onTabChange(tabs[newIndex].id);
                announce(
                    sprintf(
                        __('Switched to %s tab', 'rwp-creator-suite'),
                        tabs[newIndex].label
                    )
                );
            }
        },
        { minDistance: 30 }
    );
    
    // Initialize focused tab index
    useEffect(() => {
        const activeIndex = tabs.findIndex(tab => tab.id === activeTab);
        if (activeIndex !== -1) {
            setFocusedTabIndex(activeIndex);
        }
    }, [activeTab, tabs]);
    
    // Scroll active tab into view
    useEffect(() => {
        if (tabRefs.current[focusedTabIndex] && tabListRef.current) {
            const activeTabElement = tabRefs.current[focusedTabIndex];
            const containerRect = tabListRef.current.getBoundingClientRect();
            const tabRect = activeTabElement.getBoundingClientRect();
            
            if (tabRect.left < containerRect.left || tabRect.right > containerRect.right) {
                activeTabElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'center'
                });
            }
        }
    }, [focusedTabIndex]);
    
    // Keyboard navigation
    const handleKeyDown = (e) => {
        const { key } = e;
        let newIndex = focusedTabIndex;
        
        switch (key) {
            case 'ArrowRight':
                if (orientation === 'horizontal') {
                    e.preventDefault();
                    newIndex = (focusedTabIndex + 1) % tabs.length;
                    setIsKeyboardNavigating(true);
                }
                break;
                
            case 'ArrowLeft':
                if (orientation === 'horizontal') {
                    e.preventDefault();
                    newIndex = (focusedTabIndex - 1 + tabs.length) % tabs.length;
                    setIsKeyboardNavigating(true);
                }
                break;
                
            case 'ArrowDown':
                if (orientation === 'vertical') {
                    e.preventDefault();
                    newIndex = (focusedTabIndex + 1) % tabs.length;
                    setIsKeyboardNavigating(true);
                }
                break;
                
            case 'ArrowUp':
                if (orientation === 'vertical') {
                    e.preventDefault();
                    newIndex = (focusedTabIndex - 1 + tabs.length) % tabs.length;
                    setIsKeyboardNavigating(true);
                }
                break;
                
            case 'Home':
                e.preventDefault();
                newIndex = 0;
                setIsKeyboardNavigating(true);
                break;
                
            case 'End':
                e.preventDefault();
                newIndex = tabs.length - 1;
                setIsKeyboardNavigating(true);
                break;
                
            case 'Enter':
            case ' ':
                e.preventDefault();
                if (tabs[focusedTabIndex]) {
                    onTabChange(tabs[focusedTabIndex].id);
                }
                break;
        }
        
        if (newIndex !== focusedTabIndex) {
            setFocusedTabIndex(newIndex);
        }
    };
    
    // Get size-based classes
    const getSizeClasses = () => {
        const sizeMap = {
            small: 'blk-px-3 blk-py-2 blk-text-sm',
            medium: 'blk-px-4 blk-py-3 blk-text-base',
            large: 'blk-px-6 blk-py-4 blk-text-lg'
        };
        
        return sizeMap[size] || sizeMap.medium;
    };
    
    // Get orientation classes
    const getOrientationClasses = () => {
        if (orientation === 'vertical') {
            return 'blk-flex-col blk-w-full';
        }
        
        return screenSize === 'mobile' 
            ? 'blk-flex-row blk-overflow-x-auto blk-scrollbar-hide blk-snap-x blk-snap-mandatory'
            : 'blk-flex-row blk-flex-wrap';
    };
    
    const renderTab = (tab, index) => {
        const isActive = tab.id === activeTab;
        const isFocused = index === focusedTabIndex && isKeyboardNavigating;
        
        const tabClasses = [
            'blk-tab',
            'blk-btn-enhanced',
            'blk-border-none blk-bg-transparent',
            'blk-font-medium blk-cursor-pointer',
            'blk-transition-all blk-duration-200 blk-ease-in-out',
            'blk-touch-target',
            getSizeClasses(),
            
            // Mobile optimizations
            screenSize === 'mobile' ? [
                'blk-flex-none blk-whitespace-nowrap',
                'blk-min-w-[120px]',
                'blk-snap-center'
            ].join(' ') : '',
            
            // State classes
            isActive ? [
                'blk-text-blue-600 blk-border-b-2 blk-border-blue-600',
                'blk-bg-blue-50'
            ].join(' ') : [
                'blk-text-gray-600 blk-border-b-2 blk-border-transparent',
                'hover:blk-text-gray-900 hover:blk-bg-gray-50'
            ].join(' '),
            
            // Focus styles
            isFocused ? 'blk-focus-current' : '',
            
            // Disabled state
            tab.disabled ? 'blk-opacity-50 blk-cursor-not-allowed' : ''
        ].filter(Boolean).join(' ');
        
        return (
            <button
                key={tab.id}
                ref={el => tabRefs.current[index] = el}
                type="button"
                role="tab"
                aria-selected={isActive}
                aria-controls={`tabpanel-${tab.id}`}
                aria-describedby={tab.description ? `tab-desc-${tab.id}` : undefined}
                id={`tab-${tab.id}`}
                tabIndex={isActive ? 0 : -1}
                disabled={tab.disabled}
                className={tabClasses}
                onClick={() => {
                    if (!tab.disabled) {
                        onTabChange(tab.id);
                        setFocusedTabIndex(index);
                        setIsKeyboardNavigating(false);
                        
                        announce(
                            sprintf(
                                __('%s tab selected', 'rwp-creator-suite'),
                                tab.label
                            )
                        );
                    }
                }}
                onFocus={() => {
                    setFocusedTabIndex(index);
                    setIsKeyboardNavigating(true);
                }}
            >
                {/* Icon */}
                {showIcons && tab.icon && (
                    <span 
                        className="blk-mr-2 blk-text-lg" 
                        aria-hidden="true"
                    >
                        {tab.icon}
                    </span>
                )}
                
                {/* Label */}
                <span>{tab.label}</span>
                
                {/* Badge/Counter */}
                {tab.badge && (
                    <span 
                        className="blk-ml-2 blk-px-2 blk-py-1 blk-text-xs blk-bg-red-500 blk-text-white blk-rounded-full blk-min-w-[20px] blk-h-5 blk-flex blk-items-center blk-justify-center"
                        aria-label={sprintf(
                            __('%d notifications', 'rwp-creator-suite'),
                            tab.badge
                        )}
                    >
                        {tab.badge}
                    </span>
                )}
                
                {/* Screen reader description */}
                {tab.description && (
                    <span 
                        id={`tab-desc-${tab.id}`}
                        className="blk-sr-only"
                    >
                        {tab.description}
                    </span>
                )}
            </button>
        );
    };
    
    return (
        <div className={`blk-tab-navigation ${className}`}>
            {/* Tab List */}
            <div
                ref={tabListRef}
                role="tablist"
                aria-label={__('Tab navigation', 'rwp-creator-suite')}
                aria-orientation={orientation}
                className={`
                    blk-flex blk-border-b blk-border-gray-200 blk-mb-6
                    ${getOrientationClasses()}
                    ${hasTouch && enableSwipe ? 'blk-touch-pan-x' : ''}
                `}
                onKeyDown={handleKeyDown}
                onTouchStart={hasTouch && enableSwipe ? onTouchStart : undefined}
                onTouchMove={hasTouch && enableSwipe ? onTouchMove : undefined}
                onTouchEnd={hasTouch && enableSwipe ? onTouchEnd : undefined}
            >
                {tabs.map(renderTab)}
            </div>
            
            {/* Swipe Indicator (Mobile) */}
            {hasTouch && enableSwipe && screenSize === 'mobile' && tabs.length > 2 && (
                <div className="blk-sr-only">
                    {__('Swipe left or right to navigate between tabs', 'rwp-creator-suite')}
                </div>
            )}
            
            {/* Tab Panels Container */}
            <div className="blk-tab-panels">
                {tabs.map(tab => (
                    <div
                        key={tab.id}
                        id={`tabpanel-${tab.id}`}
                        role="tabpanel"
                        aria-labelledby={`tab-${tab.id}`}
                        hidden={tab.id !== activeTab}
                        tabIndex={tab.id === activeTab ? 0 : -1}
                        className={tab.id === activeTab ? 'blk-block' : 'blk-hidden'}
                    >
                        {tab.content}
                    </div>
                ))}
            </div>
            
            {/* Status for screen readers */}
            <div className="blk-sr-only" aria-live="polite">
                {activeTab && tabs.find(tab => tab.id === activeTab) && 
                    sprintf(
                        __('Currently viewing %s tab, %d of %d', 'rwp-creator-suite'),
                        tabs.find(tab => tab.id === activeTab).label,
                        tabs.findIndex(tab => tab.id === activeTab) + 1,
                        tabs.length
                    )
                }
            </div>
        </div>
    );
};

export default MobileTabNavigation;