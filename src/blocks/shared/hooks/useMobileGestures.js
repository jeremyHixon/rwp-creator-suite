/**
 * Mobile Gesture Hooks
 * Phase 3 UI/UX Implementation
 * 
 * Provides touch gestures, swipe detection, and mobile-specific interactions
 */

import { useState, useCallback, useRef, useEffect } from '@wordpress/element';

/**
 * Hook for detecting swipe gestures
 */
export const useSwipeGesture = (onSwipe, options = {}) => {
    const [touchStart, setTouchStart] = useState(null);
    const [touchEnd, setTouchEnd] = useState(null);
    const [isDragging, setIsDragging] = useState(false);
    
    const minSwipeDistance = options.minDistance || 50;
    const maxSwipeTime = options.maxTime || 500;
    const startTimeRef = useRef(null);
    
    const onTouchStart = useCallback((e) => {
        setTouchEnd(null);
        setTouchStart({
            x: e.targetTouches[0].clientX,
            y: e.targetTouches[0].clientY
        });
        setIsDragging(false);
        startTimeRef.current = Date.now();
    }, []);
    
    const onTouchMove = useCallback((e) => {
        setTouchEnd({
            x: e.targetTouches[0].clientX,
            y: e.targetTouches[0].clientY
        });
        setIsDragging(true);
    }, []);
    
    const onTouchEnd = useCallback((e) => {
        if (!touchStart || !touchEnd) return;
        
        const deltaTime = Date.now() - startTimeRef.current;
        if (deltaTime > maxSwipeTime) return;
        
        const distanceX = touchStart.x - touchEnd.x;
        const distanceY = touchStart.y - touchEnd.y;
        const absDistanceX = Math.abs(distanceX);
        const absDistanceY = Math.abs(distanceY);
        
        // Check if it's a horizontal swipe
        if (absDistanceX > absDistanceY && absDistanceX > minSwipeDistance) {
            const direction = distanceX > 0 ? 'left' : 'right';
            onSwipe(direction, { distanceX, distanceY, deltaTime });
        }
        
        // Check if it's a vertical swipe
        else if (absDistanceY > absDistanceX && absDistanceY > minSwipeDistance) {
            const direction = distanceY > 0 ? 'up' : 'down';
            onSwipe(direction, { distanceX, distanceY, deltaTime });
        }
        
        setIsDragging(false);
    }, [touchStart, touchEnd, onSwipe, minSwipeDistance, maxSwipeTime]);
    
    return {
        onTouchStart,
        onTouchMove,
        onTouchEnd,
        isDragging
    };
};

/**
 * Hook for pull-to-refresh functionality
 */
export const usePullToRefresh = (onRefresh, options = {}) => {
    const [pullDistance, setPullDistance] = useState(0);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [isPulling, setIsPulling] = useState(false);
    
    const threshold = options.threshold || 80;
    const maxPullDistance = options.maxPullDistance || 120;
    const startY = useRef(0);
    const scrollContainer = useRef(null);
    
    const handleTouchStart = useCallback((e) => {
        if (scrollContainer.current && scrollContainer.current.scrollTop === 0) {
            startY.current = e.touches[0].clientY;
            setIsPulling(false);
        }
    }, []);
    
    const handleTouchMove = useCallback((e) => {
        if (isRefreshing || !scrollContainer.current) return;
        
        const currentY = e.touches[0].clientY;
        const deltaY = currentY - startY.current;
        
        if (deltaY > 0 && scrollContainer.current.scrollTop === 0) {
            e.preventDefault();
            const distance = Math.min(deltaY * 0.5, maxPullDistance);
            setPullDistance(distance);
            setIsPulling(distance > 20);
        }
    }, [isRefreshing, maxPullDistance]);
    
    const handleTouchEnd = useCallback(async () => {
        if (pullDistance > threshold && !isRefreshing) {
            setIsRefreshing(true);
            try {
                await onRefresh();
            } finally {
                setIsRefreshing(false);
            }
        }
        setPullDistance(0);
        setIsPulling(false);
    }, [pullDistance, threshold, isRefreshing, onRefresh]);
    
    return {
        pullDistance,
        isRefreshing,
        isPulling,
        handleTouchStart,
        handleTouchMove,
        handleTouchEnd,
        scrollContainer
    };
};

/**
 * Hook for drag-to-dismiss modal functionality
 */
export const useDragToDismiss = (onDismiss, options = {}) => {
    const [dragY, setDragY] = useState(0);
    const [isDragging, setIsDragging] = useState(false);
    const [velocity, setVelocity] = useState(0);
    
    const dismissThreshold = options.dismissThreshold || 100;
    const velocityThreshold = options.velocityThreshold || 0.5;
    
    const startY = useRef(0);
    const startTime = useRef(0);
    const lastY = useRef(0);
    const lastTime = useRef(0);
    
    const handleTouchStart = useCallback((e) => {
        const touch = e.touches[0];
        startY.current = touch.clientY;
        startTime.current = Date.now();
        lastY.current = touch.clientY;
        lastTime.current = startTime.current;
        setIsDragging(true);
        setDragY(0);
        setVelocity(0);
    }, []);
    
    const handleTouchMove = useCallback((e) => {
        if (!isDragging) return;
        
        const touch = e.touches[0];
        const currentY = touch.clientY;
        const currentTime = Date.now();
        const deltaY = currentY - startY.current;
        
        // Only allow downward drag
        if (deltaY > 0) {
            setDragY(deltaY);
            
            // Calculate velocity
            const timeDelta = currentTime - lastTime.current;
            if (timeDelta > 0) {
                const yDelta = currentY - lastY.current;
                setVelocity(yDelta / timeDelta);
            }
            
            lastY.current = currentY;
            lastTime.current = currentTime;
        }
    }, [isDragging]);
    
    const handleTouchEnd = useCallback(() => {
        if (!isDragging) return;
        
        setIsDragging(false);
        
        // Dismiss if dragged far enough or with high velocity
        if (dragY > dismissThreshold || velocity > velocityThreshold) {
            onDismiss();
        } else {
            // Snap back
            setDragY(0);
        }
        
        setVelocity(0);
    }, [isDragging, dragY, velocity, dismissThreshold, velocityThreshold, onDismiss]);
    
    return {
        dragY,
        isDragging,
        handleTouchStart,
        handleTouchMove,
        handleTouchEnd
    };
};

/**
 * Hook for detecting device capabilities and touch support
 */
export const useDeviceCapabilities = () => {
    const [capabilities, setCapabilities] = useState({
        hasTouch: false,
        hasHover: false,
        hasPointer: false,
        isIOS: false,
        isAndroid: false,
        screenSize: 'desktop'
    });
    
    useEffect(() => {
        const detectCapabilities = () => {
            const hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            const hasHover = window.matchMedia('(hover: hover)').matches;
            const hasPointer = window.matchMedia('(pointer: fine)').matches;
            
            const userAgent = navigator.userAgent || navigator.vendor || window.opera;
            const isIOS = /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;
            const isAndroid = /android/i.test(userAgent);
            
            let screenSize = 'desktop';
            if (window.innerWidth <= 768) {
                screenSize = 'mobile';
            } else if (window.innerWidth <= 1024) {
                screenSize = 'tablet';
            }
            
            setCapabilities({
                hasTouch,
                hasHover,
                hasPointer,
                isIOS,
                isAndroid,
                screenSize
            });
        };
        
        detectCapabilities();
        
        const handleResize = () => {
            detectCapabilities();
        };
        
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);
    
    return capabilities;
};

/**
 * Hook for optimizing touch interactions
 */
export const useTouchOptimization = () => {
    const { hasTouch, isIOS } = useDeviceCapabilities();
    
    const preventZoom = useCallback((e) => {
        if (e.touches && e.touches.length > 1) {
            e.preventDefault();
        }
    }, []);
    
    const optimizeTouchTarget = useCallback((element) => {
        if (!element || !hasTouch) return;
        
        // Ensure minimum touch target size
        const rect = element.getBoundingClientRect();
        const minSize = 44; // 44px minimum for accessibility
        
        if (rect.width < minSize || rect.height < minSize) {
            element.style.minWidth = `${minSize}px`;
            element.style.minHeight = `${minSize}px`;
        }
        
        // Add touch-friendly padding
        if (!element.style.padding) {
            element.style.padding = '12px';
        }
        
        // Optimize for iOS safari
        if (isIOS) {
            element.style.WebkitTouchCallout = 'none';
            element.style.WebkitTapHighlightColor = 'transparent';
        }
    }, [hasTouch, isIOS]);
    
    const addTouchListeners = useCallback((element, handlers) => {
        if (!element || !hasTouch) return () => {};
        
        // Add passive listeners for better performance
        const options = { passive: false };
        
        if (handlers.onTouchStart) {
            element.addEventListener('touchstart', handlers.onTouchStart, options);
        }
        if (handlers.onTouchMove) {
            element.addEventListener('touchmove', handlers.onTouchMove, options);
        }
        if (handlers.onTouchEnd) {
            element.addEventListener('touchend', handlers.onTouchEnd, options);
        }
        
        // Return cleanup function
        return () => {
            if (handlers.onTouchStart) {
                element.removeEventListener('touchstart', handlers.onTouchStart);
            }
            if (handlers.onTouchMove) {
                element.removeEventListener('touchmove', handlers.onTouchMove);
            }
            if (handlers.onTouchEnd) {
                element.removeEventListener('touchend', handlers.onTouchEnd);
            }
        };
    }, [hasTouch]);
    
    return {
        hasTouch,
        isIOS,
        preventZoom,
        optimizeTouchTarget,
        addTouchListeners
    };
};