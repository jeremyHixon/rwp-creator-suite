/**
 * Mobile-Optimized Modal Component
 * Phase 3 UI/UX Implementation
 * 
 * Features:
 * - Bottom sheet design pattern
 * - Drag-to-dismiss functionality
 * - Touch-optimized interactions
 * - Accessibility support
 * - iOS/Android specific optimizations
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import { useFocusTrap, useAnnouncements } from '../hooks/useAccessibility';
import { useDragToDismiss, useDeviceCapabilities } from '../hooks/useMobileGestures';

const MobileModal = ({
    isOpen = false,
    onClose,
    title,
    children,
    size = 'medium', // 'small', 'medium', 'large', 'fullscreen'
    showHandle = true,
    enableDragDismiss = true,
    closeOnBackdropClick = true,
    className = ''
}) => {
    const [isAnimating, setIsAnimating] = useState(false);
    const [isVisible, setIsVisible] = useState(false);
    const modalRef = useRef(null);
    const backdropRef = useRef(null);
    
    const { announce } = useAnnouncements();
    const { screenSize, isIOS } = useDeviceCapabilities();
    
    // Focus trap for accessibility
    const focusTrapRef = useFocusTrap(isOpen);
    
    // Drag-to-dismiss functionality
    const {
        dragY,
        isDragging,
        handleTouchStart,
        handleTouchMove,
        handleTouchEnd
    } = useDragToDismiss(onClose, {
        dismissThreshold: 100,
        velocityThreshold: 0.3
    });
    
    // Handle modal open/close animations
    useEffect(() => {
        if (isOpen) {
            setIsVisible(true);
            setIsAnimating(true);
            
            // Prevent body scroll on iOS
            if (isIOS) {
                document.body.style.overflow = 'hidden';
                document.body.style.position = 'fixed';
                document.body.style.width = '100%';
            }
            
            // Announce modal opening
            setTimeout(() => {
                announce(title ? `${title} ${__('dialog opened', 'rwp-creator-suite')}` : __('Dialog opened', 'rwp-creator-suite'));
            }, 100);
            
            setTimeout(() => setIsAnimating(false), 300);
        } else {
            setIsAnimating(true);
            
            // Restore body scroll
            if (isIOS) {
                document.body.style.overflow = '';
                document.body.style.position = '';
                document.body.style.width = '';
            }
            
            setTimeout(() => {
                setIsVisible(false);
                setIsAnimating(false);
            }, 300);
        }
        
        return () => {
            // Cleanup on unmount
            if (isIOS) {
                document.body.style.overflow = '';
                document.body.style.position = '';
                document.body.style.width = '';
            }
        };
    }, [isOpen, title, announce, isIOS]);
    
    // Handle escape key
    useEffect(() => {
        if (!isOpen) return;
        
        const handleEscapeKey = (e) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };
        
        document.addEventListener('keydown', handleEscapeKey);
        return () => document.removeEventListener('keydown', handleEscapeKey);
    }, [isOpen, onClose]);
    
    // Handle backdrop click
    const handleBackdropClick = (e) => {
        if (closeOnBackdropClick && e.target === backdropRef.current) {
            onClose();
        }
    };
    
    // Get modal size classes
    const getSizeClasses = () => {
        const isMobile = screenSize === 'mobile';
        
        if (size === 'fullscreen' || (isMobile && size === 'large')) {
            return 'blk-inset-0 blk-rounded-none';
        }
        
        const sizeMap = {
            small: isMobile ? 'blk-max-h-[50vh]' : 'blk-max-w-md blk-max-h-[60vh]',
            medium: isMobile ? 'blk-max-h-[70vh]' : 'blk-max-w-lg blk-max-h-[80vh]',
            large: isMobile ? 'blk-max-h-[90vh]' : 'blk-max-w-2xl blk-max-h-[90vh]'
        };
        
        return sizeMap[size] || sizeMap.medium;
    };
    
    // Get position classes
    const getPositionClasses = () => {
        if (screenSize === 'mobile') {
            return size === 'fullscreen' 
                ? 'blk-inset-0'
                : 'blk-bottom-0 blk-left-0 blk-right-0 blk-rounded-t-2xl';
        }
        
        return 'blk-top-1/2 blk-left-1/2 blk--translate-x-1/2 blk--translate-y-1/2 blk-rounded-xl';
    };
    
    if (!isVisible) return null;
    
    return (
        <>
            {/* Backdrop */}
            <div
                ref={backdropRef}
                className={`
                    blk-fixed blk-inset-0 blk-bg-black blk-z-[1000]
                    ${isOpen && !isAnimating 
                        ? 'blk-bg-opacity-50' 
                        : isOpen 
                            ? 'blk-bg-opacity-0 blk-animate-fade-in' 
                            : 'blk-bg-opacity-0'
                    }
                    blk-transition-all blk-duration-300 blk-ease-out
                `}
                onClick={handleBackdropClick}
                aria-hidden="true"
            />
            
            {/* Modal */}
            <div
                ref={(node) => {
                    modalRef.current = node;
                    focusTrapRef.current = node;
                }}
                className={`
                    blk-fixed blk-bg-white blk-shadow-2xl blk-z-[1001] blk-overflow-hidden
                    ${getPositionClasses()}
                    ${getSizeClasses()}
                    ${isOpen && !isAnimating 
                        ? 'blk-translate-y-0 blk-opacity-100' 
                        : isOpen 
                            ? screenSize === 'mobile' 
                                ? 'blk-translate-y-full blk-opacity-0 blk-animate-slide-up' 
                                : 'blk-scale-95 blk-opacity-0 blk-animate-scale-in'
                            : screenSize === 'mobile'
                                ? 'blk-translate-y-full blk-opacity-0'
                                : 'blk-scale-95 blk-opacity-0'
                    }
                    blk-transition-all blk-duration-300 blk-ease-out
                    ${className}
                `}
                role="dialog"
                aria-modal="true"
                aria-labelledby={title ? "modal-title" : undefined}
                aria-describedby="modal-content"
                tabIndex={-1}
                style={{
                    transform: isDragging && enableDragDismiss 
                        ? `translateY(${dragY}px)` 
                        : undefined
                }}
                onTouchStart={enableDragDismiss ? handleTouchStart : undefined}
                onTouchMove={enableDragDismiss ? handleTouchMove : undefined}
                onTouchEnd={enableDragDismiss ? handleTouchEnd : undefined}
            >
                {/* Drag Handle (Mobile) */}
                {showHandle && screenSize === 'mobile' && (
                    <div className="blk-flex blk-justify-center blk-pt-3 blk-pb-1">
                        <div 
                            className="blk-w-10 blk-h-1 blk-bg-gray-300 blk-rounded-full"
                            aria-hidden="true"
                        />
                    </div>
                )}
                
                {/* Header */}
                <div className="blk-flex blk-items-center blk-justify-between blk-p-4 blk-border-b blk-border-gray-200">
                    {title && (
                        <h2 
                            id="modal-title"
                            className="blk-text-lg blk-font-semibold blk-text-gray-900 blk-m-0"
                        >
                            {title}
                        </h2>
                    )}
                    
                    <button
                        type="button"
                        className="blk-btn-enhanced blk-p-2 blk-ml-auto blk-bg-gray-100 hover:blk-bg-gray-200 blk-rounded-full blk-touch-target"
                        onClick={onClose}
                        aria-label={__('Close dialog', 'rwp-creator-suite')}
                    >
                        <svg 
                            width="16" 
                            height="16" 
                            viewBox="0 0 16 16" 
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path d="M12.207 4.207a1 1 0 00-1.414-1.414L8 5.586 5.207 2.793a1 1 0 00-1.414 1.414L6.586 7l-2.793 2.793a1 1 0 101.414 1.414L8 8.414l2.793 2.793a1 1 0 001.414-1.414L9.414 7l2.793-2.793z"/>
                        </svg>
                    </button>
                </div>
                
                {/* Content */}
                <div 
                    id="modal-content"
                    className="blk-p-4 blk-overflow-y-auto blk-max-h-[calc(90vh-120px)]"
                >
                    {children}
                </div>
                
                {/* Drag indicator for screen readers */}
                {enableDragDismiss && screenSize === 'mobile' && (
                    <div className="blk-sr-only">
                        {__('Drag down to close this dialog', 'rwp-creator-suite')}
                    </div>
                )}
            </div>
        </>
    );
};

export default MobileModal;