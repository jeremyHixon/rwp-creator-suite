/**
 * Floating Input Component
 *
 * Enhanced input field with floating label pattern for improved UX.
 * Supports validation states, error messages, and accessibility features.
 */

import { __ } from '@wordpress/i18n';
import { useState, useRef, useEffect } from '@wordpress/element';

const FloatingInput = ({
	label,
	value,
	onChange,
	error = null,
	success = false,
	type = 'text',
	placeholder = ' ',
	disabled = false,
	required = false,
	id = null,
	className = '',
	...props
}) => {
	const [isFocused, setIsFocused] = useState(false);
	const inputRef = useRef(null);
	
	// Generate unique ID if not provided
	const inputId = id || `floating-input-${Math.random().toString(36).substr(2, 9)}`;
	
	const hasValue = value && value.trim().length > 0;
	const isLabelFloated = isFocused || hasValue;
	
	const getInputClasses = () => {
		let classes = 'floating-input';
		
		if (error) classes += ' floating-input--error';
		if (success) classes += ' floating-input--success';
		if (disabled) classes += ' floating-input--disabled';
		
		return classes;
	};
	
	const getContainerClasses = () => {
		let classes = 'input-group';
		
		if (error) classes += ' input-group--error';
		if (success) classes += ' input-group--success';
		if (disabled) classes += ' input-group--disabled';
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
	
	return (
		<div className={getContainerClasses()}>
			<input
				ref={inputRef}
				id={inputId}
				type={type}
				className={getInputClasses()}
				value={value || ''}
				onChange={onChange}
				onFocus={() => setIsFocused(true)}
				onBlur={() => setIsFocused(false)}
				placeholder={placeholder}
				disabled={disabled}
				required={required}
				aria-invalid={error ? 'true' : 'false'}
				aria-describedby={error ? `${inputId}-error` : undefined}
				{...props}
			/>
			<label htmlFor={inputId} className={getLabelClasses()}>
				{label}
				{required && <span className="floating-label-required" aria-label={__('Required', 'rwp-creator-suite')}>*</span>}
			</label>
			
			{error && (
				<div id={`${inputId}-error`} className="input-error-message" role="alert">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
						<path d="M8 1a7 7 0 100 14A7 7 0 008 1zM7 4a1 1 0 112 0v3a1 1 0 11-2 0V4zm1 7a1 1 0 100-2 1 1 0 000 2z"/>
					</svg>
					{error}
				</div>
			)}
			
			{success && !error && (
				<div className="input-success-message">
					<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
						<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
					</svg>
					{__('Valid', 'rwp-creator-suite')}
				</div>
			)}
			
			<style jsx>{`
				.input-group {
					position: relative;
					margin-bottom: 24px;
				}
				
				.floating-input {
					width: 100%;
					padding: 20px 16px 8px;
					border: 2px solid #e5e7eb;
					border-radius: 12px;
					background: #fafbfc;
					font-size: 16px;
					transition: all 0.2s ease;
					outline: none;
					font-family: inherit;
					color: #1f2937;
					line-height: 1.5;
				}
				
				.floating-input:focus {
					border-color: var(--blk-primary, #3b82f6);
					background: #ffffff;
					box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
				}
				
				.floating-input::placeholder {
					color: transparent;
				}
				
				.floating-label {
					position: absolute;
					top: 20px;
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
				
				/* Error state */
				.input-group--error .floating-input {
					border-color: #ef4444;
					background: #fef2f2;
				}
				
				.input-group--error .floating-input:focus {
					border-color: #ef4444;
					box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
				}
				
				.floating-label--error,
				.input-group--error .floating-label--floated {
					color: #ef4444;
				}
				
				/* Success state */
				.input-group--success .floating-input {
					border-color: #10b981;
					background: #f0fdf4;
				}
				
				.input-group--success .floating-input:focus {
					border-color: #10b981;
					box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
				}
				
				.floating-label--success,
				.input-group--success .floating-label--floated {
					color: #10b981;
				}
				
				/* Disabled state */
				.input-group--disabled .floating-input {
					background: #f9fafb;
					color: #9ca3af;
					cursor: not-allowed;
					border-color: #e5e7eb;
				}
				
				.input-group--disabled .floating-label {
					color: #9ca3af;
				}
				
				/* Error message */
				.input-error-message {
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
				.input-success-message {
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
					.floating-input {
						font-size: 16px; /* Prevent zoom on iOS */
					}
					
					.input-group {
						margin-bottom: 20px;
					}
				}
				
				/* High contrast mode support */
				@media (prefers-contrast: high) {
					.floating-input {
						border-width: 2px;
					}
					
					.floating-input:focus {
						outline: 2px solid;
						outline-offset: 2px;
					}
				}
				
				/* Reduced motion support */
				@media (prefers-reduced-motion: reduce) {
					.floating-input,
					.floating-label {
						transition: none;
					}
				}
			`}</style>
		</div>
	);
};

export default FloatingInput;