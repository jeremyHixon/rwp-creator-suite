/**
 * Phase 3 Integration Utilities
 * 
 * Provides utilities to seamlessly integrate Phase 3 enhancements
 * into existing components without breaking changes
 */

import { useEffect, useState } from '@wordpress/element';
import { 
    useAccessibilityPreferences, 
    useAnnouncements 
} from '../hooks/useAccessibility';
import { useDeviceCapabilities } from '../hooks/useMobileGestures';
import { useProgressiveEnhancement } from '../hooks/useAdvancedUX';

/**
 * Enhanced wrapper for existing components to add Phase 3 capabilities
 */
export const withPhase3Enhancements = (WrappedComponent) => {
    return function EnhancedComponent(props) {
        const [isInitialized, setIsInitialized] = useState(false);
        const { prefersReducedMotion, prefersHighContrast } = useAccessibilityPreferences();
        const { hasTouch, screenSize } = useDeviceCapabilities();
        const { capabilities } = useProgressiveEnhancement();
        const { announce } = useAnnouncements();
        
        // Apply progressive enhancement classes to body
        useEffect(() => {
            const body = document.body;
            const classes = [];
            
            if (hasTouch) classes.push('blk-has-touch');
            if (prefersReducedMotion) classes.push('blk-reduced-motion');
            if (prefersHighContrast) classes.push('blk-high-contrast');
            if (screenSize) classes.push(`blk-screen-${screenSize}`);
            
            // Add device classes
            if (navigator.userAgent.match(/iPad|iPhone|iPod/)) {
                classes.push('blk-ios');
            } else if (navigator.userAgent.match(/Android/)) {
                classes.push('blk-android');
            }
            
            classes.forEach(cls => body.classList.add(cls));
            
            // Cleanup
            return () => {
                classes.forEach(cls => body.classList.remove(cls));
            };
        }, [hasTouch, prefersReducedMotion, prefersHighContrast, screenSize]);
        
        // Initialize component
        useEffect(() => {
            setIsInitialized(true);
            announce(__('Component enhanced with accessibility features', 'rwp-creator-suite'));
        }, [announce]);
        
        // Enhanced props
        const enhancedProps = {
            ...props,
            // Accessibility enhancements
            accessibilityFeatures: {
                prefersReducedMotion,
                prefersHighContrast,
                announce
            },
            // Mobile enhancements  
            mobileFeatures: {
                hasTouch,
                screenSize,
                isIOS: navigator.userAgent.match(/iPad|iPhone|iPod/),
                isAndroid: navigator.userAgent.match(/Android/)
            },
            // Progressive enhancement
            capabilities,
            // Integration status
            isPhase3Enhanced: true,
            isInitialized
        };
        
        return <WrappedComponent {...enhancedProps} />;
    };
};

/**
 * Utility to enhance existing button components with Phase 3 features
 */
export const enhanceButtonProps = (originalProps, options = {}) => {
    const { 
        addRipple = true, 
        addTouchOptimization = true, 
        addAccessibility = true,
        variant = 'primary'
    } = options;
    
    const baseClasses = originalProps.className || '';
    const enhancedClasses = [
        baseClasses,
        'blk-btn-enhanced',
        addRipple ? 'blk-ripple' : '',
        addTouchOptimization ? 'blk-touch-target' : '',
        'blk-transition-all blk-duration-200 blk-ease-out'
    ].filter(Boolean).join(' ');
    
    const enhancedProps = {
        ...originalProps,
        className: enhancedClasses,
        'data-phase3-enhanced': 'button'
    };
    
    if (addAccessibility) {
        enhancedProps['aria-describedby'] = originalProps['aria-describedby'] || 'phase3-button-help';
        enhancedProps['data-touch-feedback'] = 'true';
    }
    
    return enhancedProps;
};

/**
 * Utility to enhance existing input components with Phase 3 features
 */
export const enhanceInputProps = (originalProps, options = {}) => {
    const { 
        addFloatingLabel = false, 
        addTouchOptimization = true, 
        addValidation = true 
    } = options;
    
    const baseClasses = originalProps.className || '';
    const enhancedClasses = [
        baseClasses,
        'blk-form-input',
        addTouchOptimization ? 'blk-touch-target' : '',
        'blk-transition-all blk-duration-200'
    ].filter(Boolean).join(' ');
    
    return {
        ...originalProps,
        className: enhancedClasses,
        'data-phase3-enhanced': 'input',
        autoComplete: originalProps.autoComplete || 'off'
    };
};

/**
 * Utility to enhance existing card components with Phase 3 features
 */
export const enhanceCardProps = (originalProps, options = {}) => {
    const { 
        addHoverEffects = true, 
        addFocusManagement = true, 
        addAnimations = true 
    } = options;
    
    const baseClasses = originalProps.className || '';
    const enhancedClasses = [
        baseClasses,
        addHoverEffects ? 'blk-card-hover' : '',
        addAnimations ? 'blk-card-reveal' : '',
        addFocusManagement ? 'blk-card-focus' : '',
        'blk-transition-all blk-duration-300'
    ].filter(Boolean).join(' ');
    
    const enhancedProps = {
        ...originalProps,
        className: enhancedClasses,
        'data-phase3-enhanced': 'card'
    };
    
    if (addFocusManagement && (originalProps.onClick || originalProps.role === 'button')) {
        enhancedProps.tabIndex = originalProps.tabIndex || 0;
        enhancedProps['aria-label'] = originalProps['aria-label'] || 'Interactive card';
    }
    
    return enhancedProps;
};

/**
 * Migration helper for platform selector components
 */
export const migratePlatformSelector = (props) => {
    return {
        ...props,
        // Map old props to new AccessiblePlatformSelector props
        platforms: props.selectedPlatforms || props.platforms || [],
        onPlatformsChange: props.onPlatformChange || props.onPlatformsChange,
        
        // Add new Phase 3 features
        enableKeyboardNavigation: true,
        enableScreenReaderSupport: true,
        enableTouchOptimization: true,
        enableAnimations: true,
        
        // Preserve existing functionality
        maxSelections: props.maxSelections,
        isGuest: props.isGuest,
        guestLimit: props.guestLimit || 3
    };
};

/**
 * Progressive enhancement detection and application
 */
export const applyProgressiveEnhancements = (element, enhancements = {}) => {
    if (!element) return;
    
    const {
        clipboard = false,
        touch = false,
        animations = false,
        accessibility = false
    } = enhancements;
    
    // Clipboard enhancements
    if (clipboard && navigator.clipboard) {
        element.setAttribute('data-clipboard-supported', 'true');
    }
    
    // Touch enhancements
    if (touch && ('ontouchstart' in window)) {
        element.classList.add('blk-touch-enabled');
        element.style.touchAction = 'manipulation';
    }
    
    // Animation enhancements
    if (animations) {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!prefersReducedMotion) {
            element.classList.add('blk-animations-enabled');
        }
    }
    
    // Accessibility enhancements
    if (accessibility) {
        element.setAttribute('data-a11y-enhanced', 'true');
        
        // Add skip links target if it doesn't exist
        if (!element.id && element.tagName.toLowerCase() !== 'body') {
            element.id = `enhanced-${Date.now()}`;
        }
    }
};

/**
 * Component upgrade checker and migration helper
 */
export const checkComponentUpgrade = (componentName, currentProps) => {
    const upgradeRecommendations = [];
    
    switch (componentName) {
        case 'PlatformSelector':
            if (!currentProps.enableKeyboardNavigation) {
                upgradeRecommendations.push({
                    type: 'accessibility',
                    message: 'Consider upgrading to AccessiblePlatformSelector for better keyboard navigation',
                    component: 'AccessiblePlatformSelector',
                    benefits: ['WCAG 2.1 AA compliance', 'Better screen reader support', 'Touch optimization']
                });
            }
            break;
            
        case 'ModernTabs':
            if (!currentProps.enableSwipe) {
                upgradeRecommendations.push({
                    type: 'mobile',
                    message: 'Consider upgrading to MobileTabNavigation for swipe gestures',
                    component: 'MobileTabNavigation',
                    benefits: ['Swipe gesture support', 'Better mobile UX', 'Touch optimization']
                });
            }
            break;
            
        case 'ResultCard':
            if (!currentProps.enableAnimations) {
                upgradeRecommendations.push({
                    type: 'animations',
                    message: 'Add entrance animations for better UX',
                    enhancement: 'useInViewAnimation',
                    benefits: ['Smoother interactions', 'Better perceived performance', 'Modern feel']
                });
            }
            break;
    }
    
    return upgradeRecommendations;
};

/**
 * Performance optimization helper
 */
export const optimizeForPerformance = (componentConfig) => {
    const { 
        hasAnimations = false,
        hasHeavyImages = false,
        hasComplexInteractions = false,
        targetFrameRate = 60 
    } = componentConfig;
    
    const optimizations = {
        shouldUseRequestIdleCallback: hasComplexInteractions,
        shouldLazyLoadImages: hasHeavyImages,
        shouldReduceAnimations: false,
        shouldUseWebWorkers: false
    };
    
    // Check for low-end devices
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (connection && (connection.effectiveType === '2g' || connection.effectiveType === 'slow-2g')) {
        optimizations.shouldReduceAnimations = true;
        optimizations.shouldLazyLoadImages = true;
    }
    
    // Check for reduced motion preference
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        optimizations.shouldReduceAnimations = true;
    }
    
    // Memory constraints
    if (navigator.deviceMemory && navigator.deviceMemory < 2) {
        optimizations.shouldUseWebWorkers = false;
        optimizations.shouldReduceAnimations = true;
    }
    
    return optimizations;
};

/**
 * Accessibility audit helper
 */
export const auditAccessibility = (element) => {
    const issues = [];
    const improvements = [];
    
    if (!element) return { issues, improvements };
    
    // Check for missing ARIA labels
    const interactiveElements = element.querySelectorAll('button, [role="button"], a, input, select, textarea');
    interactiveElements.forEach(el => {
        if (!el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby') && !el.textContent.trim()) {
            issues.push({
                element: el.tagName.toLowerCase(),
                issue: 'Missing accessible label',
                severity: 'high',
                fix: 'Add aria-label or aria-labelledby attribute'
            });
        }
    });
    
    // Check for keyboard navigation
    const focusableElements = element.querySelectorAll('[tabindex="0"], button, a, input, select, textarea');
    if (focusableElements.length === 0) {
        improvements.push({
            type: 'keyboard-navigation',
            message: 'Consider adding keyboard navigation support',
            impact: 'medium'
        });
    }
    
    // Check for color contrast
    const hasInsufficientContrast = element.querySelector('[style*="color"]');
    if (hasInsufficientContrast) {
        improvements.push({
            type: 'color-contrast',
            message: 'Verify color contrast meets WCAG AA standards',
            impact: 'high'
        });
    }
    
    // Check for motion preferences
    const hasAnimations = element.querySelector('.animate, [style*="transition"], [style*="animation"]');
    if (hasAnimations) {
        improvements.push({
            type: 'motion-preferences',
            message: 'Consider respecting prefers-reduced-motion',
            impact: 'medium'
        });
    }
    
    return {
        issues,
        improvements,
        score: Math.max(0, 100 - (issues.length * 20) - (improvements.length * 5))
    };
};

export default {
    withPhase3Enhancements,
    enhanceButtonProps,
    enhanceInputProps,
    enhanceCardProps,
    migratePlatformSelector,
    applyProgressiveEnhancements,
    checkComponentUpgrade,
    optimizeForPerformance,
    auditAccessibility
};