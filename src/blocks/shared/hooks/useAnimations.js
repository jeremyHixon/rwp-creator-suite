/**
 * Advanced Animation Hooks
 * Phase 3 UI/UX Implementation
 * 
 * Provides sophisticated animation controls, intersection observers,
 * and performance-optimized animation management
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { useAccessibilityPreferences } from './useAccessibility';

/**
 * Hook for managing intersection-based animations
 */
export const useInViewAnimation = (options = {}) => {
    const [isInView, setIsInView] = useState(false);
    const [hasAnimated, setHasAnimated] = useState(false);
    const elementRef = useRef(null);
    const observerRef = useRef(null);
    
    const {
        threshold = 0.1,
        rootMargin = '0px',
        triggerOnce = true,
        delay = 0
    } = options;
    
    const { prefersReducedMotion } = useAccessibilityPreferences();
    
    useEffect(() => {
        const element = elementRef.current;
        if (!element || typeof IntersectionObserver === 'undefined') return;
        
        // Skip animations if user prefers reduced motion
        if (prefersReducedMotion) {
            setIsInView(true);
            setHasAnimated(true);
            return;
        }
        
        observerRef.current = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    if (delay > 0) {
                        setTimeout(() => {
                            setIsInView(true);
                            if (triggerOnce) {
                                setHasAnimated(true);
                            }
                        }, delay);
                    } else {
                        setIsInView(true);
                        if (triggerOnce) {
                            setHasAnimated(true);
                        }
                    }
                } else if (!triggerOnce && !hasAnimated) {
                    setIsInView(false);
                }
            },
            {
                threshold,
                rootMargin
            }
        );
        
        observerRef.current.observe(element);
        
        return () => {
            if (observerRef.current) {
                observerRef.current.disconnect();
            }
        };
    }, [threshold, rootMargin, triggerOnce, delay, prefersReducedMotion, hasAnimated]);
    
    return [elementRef, isInView, hasAnimated];
};

/**
 * Hook for managing staggered animations
 */
export const useStaggeredAnimation = (items = [], options = {}) => {
    const [visibleItems, setVisibleItems] = useState(new Set());
    const [isAnimating, setIsAnimating] = useState(false);
    const timeoutsRef = useRef([]);
    
    const {
        delay = 100,
        startDelay = 0,
        triggerOnce = true
    } = options;
    
    const { prefersReducedMotion } = useAccessibilityPreferences();
    
    const startAnimation = useCallback(() => {
        if (prefersReducedMotion) {
            setVisibleItems(new Set(items.map((_, index) => index)));
            return;
        }
        
        setIsAnimating(true);
        setVisibleItems(new Set());
        
        // Clear any existing timeouts
        timeoutsRef.current.forEach(clearTimeout);
        timeoutsRef.current = [];
        
        items.forEach((_, index) => {
            const timeout = setTimeout(() => {
                setVisibleItems(prev => new Set([...prev, index]));
                
                // Check if animation is complete
                if (index === items.length - 1) {
                    setTimeout(() => setIsAnimating(false), delay);
                }
            }, startDelay + (index * delay));
            
            timeoutsRef.current.push(timeout);
        });
    }, [items, delay, startDelay, prefersReducedMotion]);
    
    const resetAnimation = useCallback(() => {
        timeoutsRef.current.forEach(clearTimeout);
        timeoutsRef.current = [];
        setVisibleItems(new Set());
        setIsAnimating(false);
    }, []);
    
    // Auto-trigger on items change if triggerOnce is false
    useEffect(() => {
        if (!triggerOnce || visibleItems.size === 0) {
            startAnimation();
        }
    }, [items.length, startAnimation, triggerOnce, visibleItems.size]);
    
    // Cleanup on unmount
    useEffect(() => {
        return () => {
            timeoutsRef.current.forEach(clearTimeout);
        };
    }, []);
    
    return {
        visibleItems,
        isAnimating,
        startAnimation,
        resetAnimation
    };
};

/**
 * Hook for managing loading state animations
 */
export const useLoadingAnimation = (isLoading, options = {}) => {
    const [showLoading, setShowLoading] = useState(false);
    const [showSuccess, setShowSuccess] = useState(false);
    const [animationPhase, setAnimationPhase] = useState('idle'); // 'idle', 'loading', 'success', 'error'
    
    const {
        minLoadingTime = 500,
        successDuration = 2000,
        errorDuration = 3000
    } = options;
    
    const { prefersReducedMotion } = useAccessibilityPreferences();
    
    useEffect(() => {
        if (isLoading) {
            setAnimationPhase('loading');
            setShowLoading(true);
            setShowSuccess(false);
        } else if (showLoading) {
            // Ensure minimum loading time for smooth UX
            const loadingDuration = prefersReducedMotion ? 0 : minLoadingTime;
            
            setTimeout(() => {
                setAnimationPhase('success');
                setShowLoading(false);
                setShowSuccess(true);
                
                // Hide success after duration
                setTimeout(() => {
                    setShowSuccess(false);
                    setAnimationPhase('idle');
                }, successDuration);
            }, loadingDuration);
        }
    }, [isLoading, showLoading, minLoadingTime, successDuration, prefersReducedMotion]);
    
    const triggerError = useCallback(() => {
        setAnimationPhase('error');
        setShowLoading(false);
        setShowSuccess(false);
        
        setTimeout(() => {
            setAnimationPhase('idle');
        }, errorDuration);
    }, [errorDuration]);
    
    return {
        showLoading,
        showSuccess,
        animationPhase,
        triggerError
    };
};

/**
 * Hook for managing ripple effects
 */
export const useRippleEffect = () => {
    const [ripples, setRipples] = useState([]);
    const nextRippleId = useRef(0);
    
    const { prefersReducedMotion } = useAccessibilityPreferences();
    
    const createRipple = useCallback((event) => {
        if (prefersReducedMotion) return;
        
        const button = event.currentTarget;
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        const newRipple = {
            id: nextRippleId.current++,
            x,
            y,
            size
        };
        
        setRipples(prev => [...prev, newRipple]);
        
        // Remove ripple after animation
        setTimeout(() => {
            setRipples(prev => prev.filter(ripple => ripple.id !== newRipple.id));
        }, 600);
    }, [prefersReducedMotion]);
    
    return {
        ripples,
        createRipple
    };
};

/**
 * Hook for managing text reveal animations
 */
export const useTextReveal = (text, options = {}) => {
    const [revealedWords, setRevealedWords] = useState(new Set());
    const [isRevealing, setIsRevealing] = useState(false);
    
    const {
        wordDelay = 100,
        startDelay = 0,
        splitBy = ' '
    } = options;
    
    const words = text.split(splitBy);
    const { prefersReducedMotion } = useAccessibilityPreferences();
    
    const startReveal = useCallback(() => {
        if (prefersReducedMotion) {
            setRevealedWords(new Set(words.map((_, index) => index)));
            return;
        }
        
        setIsRevealing(true);
        setRevealedWords(new Set());
        
        words.forEach((_, index) => {
            setTimeout(() => {
                setRevealedWords(prev => new Set([...prev, index]));
                
                if (index === words.length - 1) {
                    setTimeout(() => setIsRevealing(false), wordDelay);
                }
            }, startDelay + (index * wordDelay));
        });
    }, [words, wordDelay, startDelay, prefersReducedMotion]);
    
    return {
        words,
        revealedWords,
        isRevealing,
        startReveal
    };
};

/**
 * Hook for managing scroll-based animations
 */
export const useScrollAnimation = (options = {}) => {
    const [scrollProgress, setScrollProgress] = useState(0);
    const [scrollDirection, setScrollDirection] = useState('down');
    const elementRef = useRef(null);
    const lastScrollY = useRef(0);
    
    const {
        offset = 0,
        throttleDelay = 16 // ~60fps
    } = options;
    
    useEffect(() => {
        const element = elementRef.current;
        if (!element) return;
        
        let ticking = false;
        
        const updateScrollProgress = () => {
            const rect = element.getBoundingClientRect();
            const elementTop = rect.top + window.pageYOffset;
            const elementHeight = rect.height;
            const windowHeight = window.innerHeight;
            
            const scrollY = window.pageYOffset;
            const start = elementTop - windowHeight + offset;
            const end = elementTop + elementHeight - offset;
            
            // Update scroll direction
            if (scrollY > lastScrollY.current) {
                setScrollDirection('down');
            } else if (scrollY < lastScrollY.current) {
                setScrollDirection('up');
            }
            lastScrollY.current = scrollY;
            
            // Calculate progress (0-1)
            const progress = Math.max(0, Math.min(1, (scrollY - start) / (end - start)));
            setScrollProgress(progress);
            
            ticking = false;
        };
        
        const handleScroll = () => {
            if (!ticking) {
                setTimeout(() => {
                    updateScrollProgress();
                }, throttleDelay);
                ticking = true;
            }
        };
        
        window.addEventListener('scroll', handleScroll, { passive: true });
        updateScrollProgress(); // Initial calculation
        
        return () => {
            window.removeEventListener('scroll', handleScroll);
        };
    }, [offset, throttleDelay]);
    
    return {
        elementRef,
        scrollProgress,
        scrollDirection
    };
};

/**
 * Hook for managing animation performance
 */
export const useAnimationPerformance = () => {
    const [isReducedPerformance, setIsReducedPerformance] = useState(false);
    const frameRate = useRef(60);
    const lastTime = useRef(performance.now());
    const frameCount = useRef(0);
    
    useEffect(() => {
        let animationId;
        
        const measurePerformance = (currentTime) => {
            frameCount.current++;
            
            if (currentTime - lastTime.current >= 1000) {
                frameRate.current = frameCount.current;
                frameCount.current = 0;
                lastTime.current = currentTime;
                
                // Consider performance reduced if frame rate drops below 45fps
                setIsReducedPerformance(frameRate.current < 45);
            }
            
            animationId = requestAnimationFrame(measurePerformance);
        };
        
        // Only measure performance during active animations
        const startMeasuring = () => {
            if (!animationId) {
                animationId = requestAnimationFrame(measurePerformance);
            }
        };
        
        const stopMeasuring = () => {
            if (animationId) {
                cancelAnimationFrame(animationId);
                animationId = null;
            }
        };
        
        // Start measuring after a short delay
        const timeout = setTimeout(startMeasuring, 1000);
        
        return () => {
            clearTimeout(timeout);
            stopMeasuring();
        };
    }, []);
    
    return {
        frameRate: frameRate.current,
        isReducedPerformance
    };
};