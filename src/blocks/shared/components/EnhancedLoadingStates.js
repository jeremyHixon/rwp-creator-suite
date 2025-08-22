/**
 * Enhanced Loading States Component
 *
 * Modern loading states with improved animations, progress indicators,
 * and better user feedback. Replaces the basic LoadingStates component.
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';

const EnhancedLoadingStates = ({
	isLoading = false,
	loadingType = 'default',
	loadingMessage = null,
	progress = null,
	estimatedTime = null,
	showProgress = false,
	showCancel = false,
	onCancel = null,
	className = '',
	variant = 'default', // 'default', 'minimal', 'card'
}) => {
	const [elapsed, setElapsed] = useState(0);
	const [progressValue, setProgressValue] = useState(progress || 0);
	
	// Timer for elapsed time
	useEffect(() => {
		if (!isLoading) {
			setElapsed(0);
			return;
		}
		
		const timer = setInterval(() => {
			setElapsed(prev => prev + 1);
		}, 1000);
		
		return () => clearInterval(timer);
	}, [isLoading]);
	
	// Smooth progress animation
	useEffect(() => {
		if (progress !== null) {
			const timer = setTimeout(() => {
				setProgressValue(progress);
			}, 100);
			return () => clearTimeout(timer);
		}
	}, [progress]);
	
	const getLoadingMessages = () => {
		const messages = {
			default: {
				title: __('Loading', 'rwp-creator-suite'),
				subtitle: __('Please wait…', 'rwp-creator-suite'),
				icon: '⏳',
			},
			generating: {
				title: __('Generating Content', 'rwp-creator-suite'),
				subtitle: __('AI is creating your content…', 'rwp-creator-suite'),
				icon: '🤖',
			},
			processing: {
				title: __('Processing', 'rwp-creator-suite'),
				subtitle: __('Analyzing and optimizing…', 'rwp-creator-suite'),
				icon: '⚙️',
			},
			analyzing: {
				title: __('Analyzing', 'rwp-creator-suite'),
				subtitle: __('Examining content and gathering insights…', 'rwp-creator-suite'),
				icon: '📊',
			},
			uploading: {
				title: __('Uploading', 'rwp-creator-suite'),
				subtitle: __('Transferring your content…', 'rwp-creator-suite'),
				icon: '📤',
			},
		};
		
		return messages[loadingType] || messages.default;
	};
	
	const formatTime = (seconds) => {
		if (seconds < 60) {
			return sprintf(__('%d seconds', 'rwp-creator-suite'), seconds);
		}
		const minutes = Math.floor(seconds / 60);
		const remainingSeconds = seconds % 60;
		return sprintf(__('%d:%02d', 'rwp-creator-suite'), minutes, remainingSeconds);
	};
	
	const getEstimatedRemaining = () => {
		if (!estimatedTime || !progress) return null;
		
		const progressDecimal = progress / 100;
		if (progressDecimal === 0) return estimatedTime;
		
		const totalEstimated = elapsed / progressDecimal;
		const remaining = Math.max(0, totalEstimated - elapsed);
		return Math.round(remaining);
	};
	
	if (!isLoading) return null;
	
	const loadingConfig = getLoadingMessages();
	const displayMessage = loadingMessage || loadingConfig.subtitle;
	const remaining = getEstimatedRemaining();
	const containerClasses = `enhanced-loading-states enhanced-loading-states--${variant} ${className}`.trim();
	
	return (
		<div className={containerClasses}>
			<div className="loading-container">
				<div className="loading-spinner">
					<div className="spinner-ring">
						<div className="spinner-inner"></div>
						<div className="spinner-outer"></div>
					</div>
					{loadingConfig.icon && (
						<div className="loading-emoji">
							{loadingConfig.icon}
						</div>
					)}
				</div>
				
				<div className="loading-content">
					<h3 className="loading-title">
						{loadingConfig.title}
					</h3>
					
					<p className="loading-message">{displayMessage}</p>
					
					{showProgress && progress !== null && (
						<div className="progress-container">
							<div className="progress-bar">
								<div 
									className="progress-fill"
									style={{ width: `${Math.min(progressValue, 100)}%` }}
								/>
							</div>
							<div className="progress-text">
								{Math.round(progressValue)}%
							</div>
						</div>
					)}
					
					{!showProgress && (
						<div className="progress-container">
							<div className="progress-bar indeterminate">
								<div className="progress-fill" />
							</div>
						</div>
					)}
					
					<div className="time-info">
						{elapsed > 0 && (
							<div className="elapsed-time">
								{sprintf(__('Elapsed: %s', 'rwp-creator-suite'), formatTime(elapsed))}
							</div>
						)}
						
						{remaining !== null && remaining > 0 && (
							<div className="estimated-remaining">
								{sprintf(__('Estimated remaining: %s', 'rwp-creator-suite'), formatTime(remaining))}
							</div>
						)}
						
						{estimatedTime && !progress && (
							<div className="estimated-total">
								{sprintf(__('Estimated time: %s', 'rwp-creator-suite'), formatTime(estimatedTime))}
							</div>
						)}
					</div>
					
					{showCancel && onCancel && (
						<div className="loading-actions">
							<button
								className="cancel-button"
								onClick={onCancel}
								type="button"
							>
								{__('Cancel', 'rwp-creator-suite')}
							</button>
						</div>
					)}
				</div>
			</div>
			
			<style jsx>{`
				.enhanced-loading-states {
					display: flex;
					align-items: center;
					justify-content: center;
					min-height: 200px;
					padding: 32px 24px;
					background: rgba(255, 255, 255, 0.95);
					border: 1px solid #e5e7eb;
					border-radius: 16px;
					backdrop-filter: blur(8px);
				}
				
				.enhanced-loading-states--minimal {
					min-height: 120px;
					padding: 24px;
					background: transparent;
					border: none;
				}
				
				.enhanced-loading-states--card {
					background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
					box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
					border: 1px solid #f1f5f9;
				}
				
				.loading-container {
					text-align: center;
					max-width: 400px;
					width: 100%;
				}
				
				.loading-spinner {
					position: relative;
					display: inline-block;
					margin-bottom: 24px;
				}
				
				.spinner-ring {
					position: relative;
					width: 48px;
					height: 48px;
				}
				
				.spinner-inner,
				.spinner-outer {
					position: absolute;
					top: 0;
					left: 0;
					width: 100%;
					height: 100%;
					border: 3px solid transparent;
					border-radius: 50%;
				}
				
				.spinner-outer {
					border-top-color: var(--blk-primary, #3b82f6);
					animation: spin 1s linear infinite;
				}
				
				.spinner-inner {
					top: 6px;
					left: 6px;
					width: calc(100% - 12px);
					height: calc(100% - 12px);
					border: 2px solid transparent;
					border-top-color: #8b5cf6;
					animation: spin 1.5s linear infinite reverse;
				}
				
				.loading-emoji {
					position: absolute;
					top: 50%;
					left: 50%;
					transform: translate(-50%, -50%);
					font-size: 20px;
					line-height: 1;
					animation: bounce 2s infinite;
				}
				
				@keyframes spin {
					0% { transform: rotate(0deg); }
					100% { transform: rotate(360deg); }
				}
				
				@keyframes bounce {
					0%, 20%, 50%, 80%, 100% {
						transform: translate(-50%, -50%) translateY(0);
					}
					40% {
						transform: translate(-50%, -50%) translateY(-8px);
					}
					60% {
						transform: translate(-50%, -50%) translateY(-4px);
					}
				}
				
				.loading-content {
					color: #1e1e1e;
				}
				
				.loading-title {
					margin: 0 0 8px 0;
					font-size: 20px;
					font-weight: 600;
					color: #1f2937;
				}
				
				.enhanced-loading-states--minimal .loading-title {
					font-size: 16px;
				}
				
				.loading-message {
					margin: 0 0 24px 0;
					font-size: 15px;
					color: #6b7280;
					line-height: 1.4;
				}
				
				.enhanced-loading-states--minimal .loading-message {
					font-size: 14px;
					margin-bottom: 16px;
				}
				
				.progress-container {
					margin: 20px 0;
				}
				
				.enhanced-loading-states--minimal .progress-container {
					margin: 12px 0;
				}
				
				.progress-bar {
					width: 100%;
					height: 6px;
					background: #e5e7eb;
					border-radius: 3px;
					overflow: hidden;
					margin-bottom: 8px;
					position: relative;
				}
				
				.progress-fill {
					height: 100%;
					background: linear-gradient(90deg, var(--blk-primary, #3b82f6), #8b5cf6);
					border-radius: 3px;
					transition: width 0.3s ease;
				}
				
				.progress-bar.indeterminate .progress-fill {
					width: 30%;
					animation: progress-indeterminate 2s ease-in-out infinite;
				}
				
				@keyframes progress-indeterminate {
					0% { transform: translateX(-100%); }
					50% { transform: translateX(0%); }
					100% { transform: translateX(100%); }
				}
				
				.progress-text {
					font-size: 13px;
					font-weight: 600;
					color: var(--blk-primary, #3b82f6);
				}
				
				.time-info {
					margin: 16px 0;
					font-size: 13px;
					color: #6b7280;
				}
				
				.enhanced-loading-states--minimal .time-info {
					font-size: 12px;
					margin: 12px 0;
				}
				
				.elapsed-time,
				.estimated-remaining,
				.estimated-total {
					margin: 4px 0;
				}
				
				.loading-actions {
					margin-top: 24px;
				}
				
				.enhanced-loading-states--minimal .loading-actions {
					margin-top: 16px;
				}
				
				.cancel-button {
					background: #6b7280;
					color: white;
					border: none;
					padding: 8px 16px;
					border-radius: 8px;
					cursor: pointer;
					font-size: 13px;
					font-weight: 500;
					transition: all 0.2s ease;
				}
				
				.cancel-button:hover {
					background: #5a6268;
					transform: translateY(-1px);
				}
				
				.cancel-button:focus {
					outline: 2px solid var(--blk-primary, #3b82f6);
					outline-offset: 2px;
				}
				
				.cancel-button:active {
					transform: translateY(0);
				}
				
				/* Mobile responsive */
				@media (max-width: 640px) {
					.enhanced-loading-states {
						min-height: 160px;
						padding: 24px 16px;
					}
					
					.enhanced-loading-states--minimal {
						min-height: 100px;
						padding: 16px;
					}
					
					.loading-spinner {
						margin-bottom: 20px;
					}
					
					.spinner-ring {
						width: 40px;
						height: 40px;
					}
					
					.loading-emoji {
						font-size: 16px;
					}
					
					.loading-title {
						font-size: 18px;
					}
					
					.enhanced-loading-states--minimal .loading-title {
						font-size: 15px;
					}
					
					.loading-message {
						font-size: 14px;
					}
					
					.enhanced-loading-states--minimal .loading-message {
						font-size: 13px;
					}
				}
				
				/* High contrast mode support */
				@media (prefers-contrast: high) {
					.enhanced-loading-states {
						border-width: 2px;
					}
					
					.progress-bar {
						border: 1px solid #374151;
					}
					
					.cancel-button:focus {
						outline-width: 3px;
					}
				}
				
				/* Reduced motion support */
				@media (prefers-reduced-motion: reduce) {
					.spinner-outer,
					.spinner-inner,
					.loading-emoji {
						animation: none;
					}
					
					.progress-bar.indeterminate .progress-fill {
						animation: none;
						transform: none;
					}
					
					.cancel-button:hover {
						transform: none;
					}
				}
			`}</style>
		</div>
	);
};

// Loading Button Component
const LoadingButton = ({ 
	loading = false, 
	children, 
	loadingText = null,
	className = '',
	disabled = false,
	variant = 'primary', // 'primary', 'secondary', 'ghost'
	size = 'default', // 'small', 'default', 'large'
	...props 
}) => {
	const displayLoadingText = loadingText || __('Loading...', 'rwp-creator-suite');
	
	const getButtonClasses = () => {
		let classes = 'loading-button';
		
		if (loading) classes += ' loading-button--loading';
		if (variant) classes += ` loading-button--${variant}`;
		if (size) classes += ` loading-button--${size}`;
		if (className) classes += ` ${className}`;
		
		return classes;
	};
	
	return (
		<button 
			{...props}
			className={getButtonClasses()}
			disabled={loading || disabled}
		>
			{loading && (
				<span className="button-spinner" aria-hidden="true">
					<span className="spinner-dot"></span>
				</span>
			)}
			<span className={`button-text ${loading ? 'button-text--hidden' : ''}`}>
				{loading ? displayLoadingText : children}
			</span>
			
			<style jsx>{`
				.loading-button {
					position: relative;
					display: inline-flex;
					align-items: center;
					justify-content: center;
					gap: 8px;
					padding: 10px 20px;
					border: 1px solid transparent;
					border-radius: 8px;
					font-size: 14px;
					font-weight: 500;
					line-height: 1.4;
					cursor: pointer;
					transition: all 0.2s ease;
					min-height: 40px;
				}
				
				.loading-button--primary {
					background: var(--blk-primary, #3b82f6);
					color: white;
					border-color: var(--blk-primary, #3b82f6);
				}
				
				.loading-button--primary:hover:not(:disabled) {
					background: #2563eb;
					border-color: #2563eb;
					transform: translateY(-1px);
					box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
				}
				
				.loading-button--secondary {
					background: #f8fafc;
					color: #374151;
					border-color: #e5e7eb;
				}
				
				.loading-button--secondary:hover:not(:disabled) {
					background: #f1f5f9;
					border-color: #d1d5db;
					transform: translateY(-1px);
				}
				
				.loading-button--ghost {
					background: transparent;
					color: #6b7280;
					border-color: transparent;
				}
				
				.loading-button--ghost:hover:not(:disabled) {
					background: #f9fafb;
					color: #374151;
				}
				
				.loading-button--small {
					padding: 6px 12px;
					font-size: 12px;
					min-height: 32px;
				}
				
				.loading-button--large {
					padding: 14px 28px;
					font-size: 16px;
					min-height: 48px;
				}
				
				.loading-button:disabled {
					opacity: 0.6;
					cursor: not-allowed;
					transform: none !important;
					box-shadow: none !important;
				}
				
				.loading-button--loading {
					pointer-events: none;
				}
				
				.button-spinner {
					position: absolute;
					display: flex;
					align-items: center;
					justify-content: center;
				}
				
				.spinner-dot {
					width: 16px;
					height: 16px;
					border: 2px solid transparent;
					border-top-color: currentColor;
					border-radius: 50%;
					animation: button-spin 0.8s linear infinite;
				}
				
				@keyframes button-spin {
					0% { transform: rotate(0deg); }
					100% { transform: rotate(360deg); }
				}
				
				.button-text {
					transition: opacity 0.2s ease;
				}
				
				.button-text--hidden {
					opacity: 0;
				}
				
				/* Focus styles */
				.loading-button:focus-visible {
					outline: 2px solid var(--blk-primary, #3b82f6);
					outline-offset: 2px;
				}
				
				/* Mobile responsive */
				@media (max-width: 640px) {
					.loading-button {
						min-height: 44px; /* Larger touch target */
					}
					
					.loading-button--small {
						min-height: 36px;
					}
					
					.loading-button--large {
						min-height: 52px;
					}
				}
				
				/* High contrast mode support */
				@media (prefers-contrast: high) {
					.loading-button:focus-visible {
						outline-width: 3px;
					}
				}
				
				/* Reduced motion support */
				@media (prefers-reduced-motion: reduce) {
					.spinner-dot {
						animation: none;
					}
					
					.loading-button:hover {
						transform: none !important;
					}
				}
			`}</style>
		</button>
	);
};

// Preset loading configurations for common use cases
export const LoadingPresets = {
	AIGeneration: (props) => (
		<EnhancedLoadingStates
			loadingType="generating"
			estimatedTime={15}
			showProgress={true}
			showCancel={true}
			variant="card"
			{...props}
		/>
	),
	
	ContentAnalysis: (props) => (
		<EnhancedLoadingStates
			loadingType="analyzing"
			estimatedTime={8}
			showProgress={true}
			{...props}
		/>
	),
	
	FileUpload: (props) => (
		<EnhancedLoadingStates
			loadingType="uploading"
			showProgress={true}
			showCancel={true}
			{...props}
		/>
	),
	
	DataProcessing: (props) => (
		<EnhancedLoadingStates
			loadingType="processing"
			estimatedTime={5}
			variant="minimal"
			{...props}
		/>
	),
};

export { LoadingButton };
export default EnhancedLoadingStates;