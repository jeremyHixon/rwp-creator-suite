/**
 * Accessibility Hooks and Utilities
 * 
 * Provides comprehensive accessibility features including keyboard navigation,
 * screen reader announcements, and focus management.
 */

import { useState, useCallback, useRef, useEffect } from '@wordpress/element';

/**
 * Hook for managing keyboard navigation through a list of items
 */
export const useKeyboardNavigation = (items = [], options = {}) => {
    const [focusIndex, setFocusIndex] = useState(0);
    const [isNavigating, setIsNavigating] = useState(false);
    
    const handleKeyDown = useCallback((event) => {
        const { key } = event;
        const itemCount = items.length;
        
        if (!itemCount) return;
        
        switch (key) {
            case 'ArrowDown':
            case 'ArrowRight':
                event.preventDefault();
                setIsNavigating(true);
                setFocusIndex((prev) => (prev + 1) % itemCount);
                break;
                
            case 'ArrowUp':
            case 'ArrowLeft':
                event.preventDefault();
                setIsNavigating(true);
                setFocusIndex((prev) => (prev - 1 + itemCount) % itemCount);
                break;
                
            case 'Home':
                event.preventDefault();
                setIsNavigating(true);
                setFocusIndex(0);
                break;
                
            case 'End':
                event.preventDefault();
                setIsNavigating(true);
                setFocusIndex(itemCount - 1);
                break;
                
            case 'Enter':
            case ' ':
                if (options.onSelect) {
                    event.preventDefault();
                    options.onSelect(items[focusIndex], focusIndex);
                }
                break;
                
            case 'Escape':
                if (options.onEscape) {
                    event.preventDefault();
                    options.onEscape();
                }
                break;
        }
    }, [items, focusIndex, options]);
    
    // Reset focus index when items change
    useEffect(() => {
        if (focusIndex >= items.length && items.length > 0) {
            setFocusIndex(0);
        }
    }, [items.length, focusIndex]);
    
    return {
        focusIndex,
        isNavigating,
        handleKeyDown,
        setFocusIndex,
        setIsNavigating
    };
};

/**
 * Hook for managing screen reader announcements
 */
export const useAnnouncements = () => {
    const announceRef = useRef(null);
    
    const announce = useCallback((message, priority = 'polite') => {
        if (!announceRef.current) {
            const liveRegion = document.createElement('div');
            liveRegion.setAttribute('aria-live', priority);
            liveRegion.setAttribute('aria-atomic', 'true');
            liveRegion.className = 'blk-sr-only';
            liveRegion.style.cssText = `
                position: absolute !important;
                width: 1px !important;
                height: 1px !important;
                padding: 0 !important;
                margin: -1px !important;
                overflow: hidden !important;
                clip: rect(0, 0, 0, 0) !important;
                white-space: nowrap !important;
                border: 0 !important;
            `;
            document.body.appendChild(liveRegion);
            announceRef.current = liveRegion;
        }
        
        // Clear and set new message
        announceRef.current.textContent = '';
        setTimeout(() => {
            if (announceRef.current) {
                announceRef.current.textContent = message;
            }
        }, 100);
    }, []);
    
    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (announceRef.current && announceRef.current.parentNode) {
                announceRef.current.parentNode.removeChild(announceRef.current);
            }
        };
    }, []);
    
    return { announce };
};

/**
 * Hook for managing focus trap within a container
 */
export const useFocusTrap = (isActive = false) => {
    const containerRef = useRef(null);
    const previousActiveElement = useRef(null);
    
    useEffect(() => {
        if (!isActive || !containerRef.current) return;
        
        const container = containerRef.current;
        previousActiveElement.current = document.activeElement;
        
        // Get all focusable elements
        const getFocusableElements = () => {
            return container.querySelectorAll(
                'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
        };
        
        const focusableElements = getFocusableElements();
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        // Focus first element
        if (firstElement) {
            firstElement.focus();
        }
        
        const handleTabKey = (e) => {
            const focusableElements = getFocusableElements();
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        lastElement?.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        firstElement?.focus();
                        e.preventDefault();
                    }
                }
            }
            
            if (e.key === 'Escape') {
                // Let parent handle escape
                return;
            }
        };
        
        container.addEventListener('keydown', handleTabKey);
        
        return () => {
            container.removeEventListener('keydown', handleTabKey);
            
            // Restore focus
            if (previousActiveElement.current && typeof previousActiveElement.current.focus === 'function') {
                previousActiveElement.current.focus();
            }
        };
    }, [isActive]);
    
    return containerRef;
};

/**
 * Hook for managing ARIA attributes dynamically
 */
export const useAriaAttributes = (initialAttributes = {}) => {
    const [ariaAttributes, setAriaAttributes] = useState(initialAttributes);
    
    const updateAria = useCallback((updates) => {
        setAriaAttributes(prev => ({ ...prev, ...updates }));
    }, []);
    
    const removeAria = useCallback((keys) => {
        setAriaAttributes(prev => {
            const next = { ...prev };
            keys.forEach(key => delete next[key]);
            return next;
        });
    }, []);
    
    return {
        ariaAttributes,
        updateAria,
        removeAria
    };
};

/**
 * Hook for detecting user preferences for accessibility
 */
export const useAccessibilityPreferences = () => {
    const [preferences, setPreferences] = useState(() => ({
        prefersReducedMotion: false,
        prefersHighContrast: false,
        prefersForcedColors: false
    }));
    
    useEffect(() => {
        const checkPreferences = () => {
            setPreferences({
                prefersReducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                prefersHighContrast: window.matchMedia('(prefers-contrast: high)').matches,
                prefersForcedColors: window.matchMedia('(forced-colors: active)').matches
            });
        };
        
        checkPreferences();
        
        // Listen for changes
        const reducedMotionMQ = window.matchMedia('(prefers-reduced-motion: reduce)');
        const highContrastMQ = window.matchMedia('(prefers-contrast: high)');
        const forcedColorsMQ = window.matchMedia('(forced-colors: active)');
        
        reducedMotionMQ.addListener(checkPreferences);
        highContrastMQ.addListener(checkPreferences);
        forcedColorsMQ.addListener(checkPreferences);
        
        return () => {
            reducedMotionMQ.removeListener(checkPreferences);
            highContrastMQ.removeListener(checkPreferences);
            forcedColorsMQ.removeListener(checkPreferences);
        };
    }, []);
    
    return preferences;
};

/**
 * Hook for managing skip navigation links
 */
export const useSkipNavigation = (targets = []) => {
    const skipLinksRef = useRef(null);
    
    useEffect(() => {
        if (targets.length === 0) return;
        
        // Create skip navigation container
        const skipContainer = document.createElement('div');
        skipContainer.className = 'blk-skip-nav-container';
        skipContainer.style.cssText = `
            position: absolute;
            top: -50px;
            left: 0;
            right: 0;
            z-index: 9999;
            display: flex;
            gap: 8px;
            padding: 8px;
        `;
        
        targets.forEach((target, index) => {
            const skipLink = document.createElement('a');
            skipLink.href = `#${target.id}`;
            skipLink.textContent = target.label;
            skipLink.className = 'blk-skip-link';
            skipLink.style.cssText = `
                background: #000;
                color: #fff;
                padding: 8px 16px;
                text-decoration: none;
                border-radius: 4px;
                font-size: 14px;
                transform: translateY(0);
                transition: transform 0.3s ease;
            `;
            
            skipLink.addEventListener('focus', () => {
                skipContainer.style.top = '0';
            });
            
            skipLink.addEventListener('blur', () => {
                skipContainer.style.top = '-50px';
            });
            
            skipContainer.appendChild(skipLink);
        });
        
        document.body.insertBefore(skipContainer, document.body.firstChild);
        skipLinksRef.current = skipContainer;
        
        return () => {
            if (skipLinksRef.current && skipLinksRef.current.parentNode) {
                skipLinksRef.current.parentNode.removeChild(skipLinksRef.current);
            }
        };
    }, [targets]);
    
    return skipLinksRef;
};