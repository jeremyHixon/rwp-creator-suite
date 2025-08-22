/**
 * Smart Textarea Component
 *
 * Enhanced textarea with auto-resize, word count, floating label,
 * and optional toolbar functionality.
 */

import { __ } from '@wordpress/i18n';
import { useState, useRef, useEffect } from '@wordpress/element';

const SmartTextarea = ({
	label,
	value,
	onChange,
	error = null,
	success = false,
	placeholder = ' ',
	disabled = false,
	required = false,
	id = null,
	className = '',
	showWordCount = true,
	showCharacterCount = false,
	maxLength = null,
	minHeight = 120,
	maxHeight = 400,
	tools = [],
	rows = 4,
	...props
}) => {
	const [isFocused, setIsFocused] = useState(false);
	const [wordCount, setWordCount] = useState(0);
	const [characterCount, setCharacterCount] = useState(0);
	const textareaRef = useRef(null);
	
	// Generate unique ID if not provided
	const textareaId = id || `smart-textarea-${Math.random().toString(36).substr(2, 9)}`;
	
	const hasValue = value && value.trim().length > 0;
	const isLabelFloated = isFocused || hasValue;
	
	// Auto-resize functionality
	useEffect(() => {
		if (textareaRef.current) {
			const textarea = textareaRef.current;
			textarea.style.height = 'auto';
			
			const newHeight = Math.max(
				minHeight,
				Math.min(maxHeight, textarea.scrollHeight)
			);
			
			textarea.style.height = `${newHeight}px`;
		}
	}, [value, minHeight, maxHeight]);
	
	// Word and character count calculation
	useEffect(() => {
		const text = value || '';
		const words = text.trim().split(/\s+/).filter(word => word.length > 0);
		setWordCount(words.length === 1 && words[0] === '' ? 0 : words.length);
		setCharacterCount(text.length);
	}, [value]);
	
	const getTextareaClasses = () => {
		let classes = 'smart-textarea';
		
		if (error) classes += ' smart-textarea--error';
		if (success) classes += ' smart-textarea--success';
		if (disabled) classes += ' smart-textarea--disabled';
		
		return classes;
	};
	
	const getContainerClasses = () => {
		let classes = 'textarea-group';
		
		if (error) classes += ' textarea-group--error';
		if (success) classes += ' textarea-group--success';
		if (disabled) classes += ' textarea-group--disabled';
		if (className) classes += ` ${className}`;
		
		return classes;
	};
	
	const getLabelClasses = () => {
		let classes = 'floating-label';
		
		if (isLabelFloated) classes += ' floating-label--floated';
		if (error) classes += ' floating-label--error';
		if (success) classes += ' floating-label--success';
		
		return classes;
	};
	
	const getCharacterLimitStatus = () => {
		if (!maxLength) return null;
		
		const remaining = maxLength - characterCount;
		const percentage = (characterCount / maxLength) * 100;
		
		if (percentage >= 100) return 'over';
		if (percentage >= 90) return 'warning';
		if (percentage >= 80) return 'caution';
		return 'normal';
	};
	
	const handleChange = (e) => {
		if (maxLength && e.target.value.length > maxLength) {
			return; // Prevent input beyond max length
		}
		if (onChange) {
			onChange(e);
		}
	};
	
	const renderFooter = () => {
		const showFooter = showWordCount || showCharacterCount || tools.length > 0;
		if (!showFooter) return null;
		
		return (
			<div className="textarea-footer">
				<div className="textarea-counts">
					{showWordCount && (
						<span className="word-count">
							{wordCount === 1 ? 
								__('1 word', 'rwp-creator-suite') : 
								sprintf(__('%d words', 'rwp-creator-suite'), wordCount)
							}
						</span>
					)}
					
					{showCharacterCount && (
						<span className={`character-count character-count--${getCharacterLimitStatus()}`}>
							{maxLength ? 
								sprintf(__('%d / %d characters', 'rwp-creator-suite'), characterCount, maxLength) :
								sprintf(__('%d characters', 'rwp-creator-suite'), characterCount)
							}
						</span>
					)}
				</div>
				
				{tools.length > 0 && (
					<div className="textarea-tools">
						{tools.map((tool, index) => (
							<button
								key={index}
								className="textarea-tool"
								onClick={tool.onClick}
								title={tool.title}
								type="button"
								disabled={disabled}
							>
								{tool.icon && <span className="tool-icon">{tool.icon}</span>}
								{tool.label}
							</button>
						))}
					</div>
				)}
			</div>
		);
	};
	
	return (
		<div className={getContainerClasses()}>
			<div className="smart-textarea-container">
				<textarea
					ref={textareaRef}
					id={textareaId}
					className={getTextareaClasses()}
					value={value || ''}
					onChange={handleChange}
					onFocus={() => setIsFocused(true)}
					onBlur={() => setIsFocused(false)}
					placeholder={placeholder}
					disabled={disabled}
					required={required}
					rows={rows}
					aria-invalid={error ? 'true' : 'false'}
					aria-describedby={error ? `${textareaId}-error` : undefined}
					style={{
						minHeight: `${minHeight}px`,
						maxHeight: `${maxHeight}px`,
						resize: 'none',
					}}
					{...props}
				/>
				
				<label htmlFor={textareaId} className={getLabelClasses()}>
					{label}
					{required && <span className="floating-label-required" aria-label={__('Required', 'rwp-creator-suite')}>*</span>}
				</label>
				
				{renderFooter()}
			</div>
			
			{error && (
				<div id={`${textareaId}-error`} className="textarea-error-message" role="alert">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
						<path d="M8 1a7 7 0 100 14A7 7 0 008 1zM7 4a1 1 0 112 0v3a1 1 0 11-2 0V4zm1 7a1 1 0 100-2 1 1 0 000 2z"/>
					</svg>
					{error}
				</div>
			)}
			
			{success && !error && (
				<div className="textarea-success-message">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
						<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
					</svg>
					{__('Valid', 'rwp-creator-suite')}
				</div>
			)}
			
			<style jsx>{`
				.textarea-group {
					position: relative;
					margin-bottom: 24px;
				}
				
				.smart-textarea-container {
					position: relative;
				}
				
				.smart-textarea {
					width: 100%;
					padding: 24px 16px 40px;
					border: 2px solid #e5e7eb;
					border-radius: 12px;
					background: #fafbfc;
					font-size: 16px;
					line-height: 1.5;
					overflow: hidden;
					transition: all 0.2s ease;
					outline: none;
					font-family: inherit;
					color: #1f2937;
				}
				
				.smart-textarea:focus {
					border-color: var(--blk-primary, #3b82f6);
					background: #ffffff;
					box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
				}
				
				.smart-textarea::placeholder {
					color: transparent;
				}
				
				.floating-label {
					position: absolute;
					top: 24px;
					left: 16px;
					color: #6b7280;
					transition: all 0.2s ease;
					pointer-events: none;
					font-size: 16px;
					font-weight: 400;
					background: transparent;
					transform-origin: left top;
				}
				
				.floating-label--floated {
					top: 8px;
					font-size: 12px;
					color: var(--blk-primary, #3b82f6);
					font-weight: 600;
					background: linear-gradient(to right, #fafbfc 20%, #ffffff 80%);
					padding: 0 4px;
					margin-left: -4px;
				}
				
				.floating-label-required {
					color: #ef4444;
					margin-left: 2px;
				}
				
				.textarea-footer {
					position: absolute;
					bottom: 12px;
					left: 16px;
					right: 16px;
					display: flex;
					justify-content: space-between;
					align-items: center;
					pointer-events: none;
					gap: 12px;
				}
				
				.textarea-counts {
					display: flex;
					gap: 16px;
					font-size: 12px;
					color: #6b7280;
					font-weight: 500;
				}
				
				.word-count {
					color: #6b7280;
				}
				
				.character-count {
					color: #6b7280;
				}
				
				.character-count--caution {
					color: #f59e0b;
				}
				
				.character-count--warning {
					color: #ef4444;
				}
				
				.character-count--over {
					color: #dc2626;
					font-weight: 600;
				}
				
				.textarea-tools {
					display: flex;
					gap: 8px;
					pointer-events: auto;
				}
				
				.textarea-tool {
					background: #ffffff;
					border: 1px solid #e5e7eb;
					border-radius: 6px;
					padding: 4px 8px;
					font-size: 12px;
					color: #6b7280;
					cursor: pointer;
					transition: all 0.2s ease;
					display: flex;
					align-items: center;
					gap: 4px;
				}
				
				.textarea-tool:hover:not(:disabled) {
					background: #f3f4f6;
					color: #374151;
					border-color: #d1d5db;
				}
				
				.textarea-tool:disabled {
					opacity: 0.5;
					cursor: not-allowed;
				}
				
				.tool-icon {
					font-size: 10px;
				}
				
				/* Error state */
				.textarea-group--error .smart-textarea {
					border-color: #ef4444;
					background: #fef2f2;
				}
				
				.textarea-group--error .smart-textarea:focus {
					border-color: #ef4444;
					box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
				}
				
				.floating-label--error,
				.textarea-group--error .floating-label--floated {
					color: #ef4444;
				}
				
				/* Success state */
				.textarea-group--success .smart-textarea {
					border-color: #10b981;
					background: #f0fdf4;
				}
				
				.textarea-group--success .smart-textarea:focus {
					border-color: #10b981;
					box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
				}
				
				.floating-label--success,
				.textarea-group--success .floating-label--floated {
					color: #10b981;
				}
				
				/* Disabled state */
				.textarea-group--disabled .smart-textarea {
					background: #f9fafb;
					color: #9ca3af;
					cursor: not-allowed;
					border-color: #e5e7eb;
				}
				
				.textarea-group--disabled .floating-label {
					color: #9ca3af;
				}
				
				.textarea-group--disabled .textarea-counts {
					color: #9ca3af;
				}
				
				/* Error message */
				.textarea-error-message {
					margin-top: 6px;
					color: #ef4444;
					font-size: 14px;
					font-weight: 500;
					display: flex;
					align-items: center;
					gap: 6px;
					line-height: 1.4;
				}
				
				/* Success message */
				.textarea-success-message {
					margin-top: 6px;
					color: #10b981;
					font-size: 14px;
					font-weight: 500;
					display: flex;
					align-items: center;
					gap: 6px;
					line-height: 1.4;
				}
				
				/* Mobile responsive */
				@media (max-width: 640px) {
					.smart-textarea {
						padding: 20px 12px 36px;
						font-size: 16px; /* Prevent zoom on iOS */
					}
					
					.floating-label {
						top: 20px;
						left: 12px;
					}
					
					.floating-label--floated {
						top: 6px;
						left: 8px;
					}
					
					.textarea-footer {
						bottom: 8px;
						left: 12px;
						right: 12px;
					}
					
					.textarea-counts {
						gap: 8px;
						font-size: 11px;
					}
					
					.textarea-tools {
						gap: 4px;
					}
					
					.textarea-tool {
						padding: 3px 6px;
						font-size: 11px;
					}
				}
				
				/* High contrast mode support */
				@media (prefers-contrast: high) {
					.smart-textarea {
						border-width: 2px;
					}
					
					.smart-textarea:focus {
						outline: 2px solid;
						outline-offset: 2px;
					}
				}
				
				/* Reduced motion support */
				@media (prefers-reduced-motion: reduce) {
					.smart-textarea,
					.floating-label {
						transition: none;
					}
				}
			`}</style>
		</div>
	);
};

export default SmartTextarea;