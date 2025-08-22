/**
 * Result Card Component
 *
 * Enhanced result display card with improved design, actions,
 * quality indicators, and loading states.
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

const ResultCard = ({
	result,
	onCopy,
	onEdit,
	onSave,
	onDelete,
	showQuality = true,
	showStats = true,
	className = '',
	loading = false,
}) => {
	const [isHovered, setIsHovered] = useState(false);
	const [copyStatus, setCopyStatus] = useState('idle'); // 'idle', 'copying', 'success'
	
	if (loading) {
		return <ResultCardSkeleton />;
	}
	
	const getQualityClass = (score) => {
		if (!score) return '';
		if (score >= 80) return 'quality-high';
		if (score >= 60) return 'quality-medium';
		return 'quality-low';
	};
	
	const formatTimestamp = (timestamp) => {
		if (!timestamp) return '';
		return new Date(timestamp).toLocaleDateString('en-US', {
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		});
	};
	
	const handleCopy = async () => {
		if (!result || copyStatus !== 'idle') return;
		
		setCopyStatus('copying');
		
		try {
			if (onCopy) {
				await onCopy(result);
			}
			setCopyStatus('success');
			setTimeout(() => setCopyStatus('idle'), 2000);
		} catch (error) {
			console.error('Copy failed:', error);
			setCopyStatus('idle');
		}
	};
	
	const getCopyButtonText = () => {
		switch (copyStatus) {
			case 'copying': return __('Copying...', 'rwp-creator-suite');
			case 'success': return __('Copied!', 'rwp-creator-suite');
			default: return __('Copy', 'rwp-creator-suite');
		}
	};
	
	const cardClasses = `result-card ${className}`.trim();
	
	return (
		<div 
			className={cardClasses}
			onMouseEnter={() => setIsHovered(true)}
			onMouseLeave={() => setIsHovered(false)}
		>
			{showQuality && result.qualityScore && (
				<div className={`quality-indicator ${getQualityClass(result.qualityScore)}`}>
					{Math.round(result.qualityScore)}
				</div>
			)}
			
			<div className="result-card-header">
				<div className="result-card-title">
					<span>{result.title || __('Generated Content', 'rwp-creator-suite')}</span>
				</div>
				
				<div className="result-card-meta">
					{result.platform && (
						<div className="result-platform">
							<span className="platform-icon">{result.platform.icon || '📱'}</span>
							{result.platform.name}
						</div>
					)}
					{result.createdAt && (
						<span className="result-timestamp">
							{formatTimestamp(result.createdAt)}
						</span>
					)}
				</div>
			</div>
			
			<div className="result-card-content">
				<p className="result-text">{result.content}</p>
			</div>
			
			<div className="result-card-footer">
				<div className="result-actions">
					<button 
						className={`result-action primary ${copyStatus === 'success' ? 'success' : ''}`}
						onClick={handleCopy}
						disabled={copyStatus === 'copying'}
						type="button"
					>
						{copyStatus === 'copying' && (
							<span className="action-spinner" aria-hidden="true">⏳</span>
						)}
						{copyStatus === 'success' && (
							<span className="action-icon" aria-hidden="true">✓</span>
						)}
						{getCopyButtonText()}
					</button>
					
					{onEdit && (
						<button 
							className="result-action" 
							onClick={() => onEdit(result)}
							type="button"
						>
							{__('Edit', 'rwp-creator-suite')}
						</button>
					)}
					
					{onSave && (
						<button 
							className="result-action" 
							onClick={() => onSave(result)}
							type="button"
						>
							{__('Save', 'rwp-creator-suite')}
						</button>
					)}
					
					{onDelete && (
						<button 
							className="result-action delete" 
							onClick={() => onDelete(result)}
							type="button"
						>
							{__('Delete', 'rwp-creator-suite')}
						</button>
					)}
				</div>
				
				{showStats && (
					<div className="result-stats">
						<div className="result-stat">
							<span>{(result.content || '').length.toLocaleString()}</span>
							<span>{__('chars', 'rwp-creator-suite')}</span>
						</div>
						{result.wordCount && (
							<div className="result-stat">
								<span>{result.wordCount.toLocaleString()}</span>
								<span>{__('words', 'rwp-creator-suite')}</span>
							</div>
						)}
					</div>
				)}
			</div>
			
			<style jsx>{`
				.result-card {
					background: white;
					border-radius: 16px;
					padding: 0;
					box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
					border: 1px solid #f1f5f9;
					transition: all 0.3s ease;
					position: relative;
					overflow: hidden;
					display: flex;
					flex-direction: column;
				}
				
				.result-card::before {
					content: '';
					position: absolute;
					top: 0;
					left: 0;
					right: 0;
					height: 4px;
					background: linear-gradient(90deg, var(--blk-primary, #3b82f6), #8b5cf6);
					opacity: 0;
					transition: opacity 0.3s ease;
				}
				
				.result-card:hover {
					transform: translateY(-4px);
					box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
				}
				
				.result-card:hover::before {
					opacity: 1;
				}
				
				.result-card-header {
					padding: 20px 20px 16px;
					border-bottom: 1px solid #f1f5f9;
					background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
				}
				
				.result-card-title {
					font-size: 16px;
					font-weight: 600;
					color: #1f2937;
					margin: 0 0 8px;
					display: flex;
					align-items: center;
					justify-content: space-between;
				}
				
				.result-card-meta {
					display: flex;
					align-items: center;
					gap: 12px;
					color: #6b7280;
					font-size: 13px;
				}
				
				.result-platform {
					display: flex;
					align-items: center;
					gap: 6px;
					padding: 4px 8px;
					background: #eff6ff;
					color: #1d4ed8;
					border-radius: 6px;
					font-weight: 500;
				}
				
				.platform-icon {
					font-size: 12px;
				}
				
				.result-timestamp {
					color: #9ca3af;
				}
				
				.result-card-content {
					padding: 20px;
					flex: 1;
				}
				
				.result-text {
					font-size: 15px;
					line-height: 1.6;
					color: #374151;
					margin: 0;
					word-wrap: break-word;
					overflow-wrap: break-word;
				}
				
				.result-card-footer {
					padding: 16px 20px;
					border-top: 1px solid #f1f5f9;
					background: #fafbfc;
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 12px;
				}
				
				.result-actions {
					display: flex;
					gap: 8px;
					flex-wrap: wrap;
				}
				
				.result-action {
					padding: 6px 12px;
					border: 1px solid #e5e7eb;
					border-radius: 8px;
					background: white;
					color: #6b7280;
					font-size: 13px;
					font-weight: 500;
					cursor: pointer;
					transition: all 0.2s ease;
					display: flex;
					align-items: center;
					gap: 4px;
					min-height: 32px;
				}
				
				.result-action:hover:not(:disabled) {
					background: #f3f4f6;
					color: #374151;
					border-color: #d1d5db;
				}
				
				.result-action:disabled {
					opacity: 0.6;
					cursor: not-allowed;
				}
				
				.result-action.primary {
					background: var(--blk-primary, #3b82f6);
					color: white;
					border-color: var(--blk-primary, #3b82f6);
				}
				
				.result-action.primary:hover:not(:disabled) {
					background: #2563eb;
					border-color: #2563eb;
				}
				
				.result-action.primary.success {
					background: #10b981;
					border-color: #10b981;
				}
				
				.result-action.delete {
					color: #ef4444;
					border-color: #fecaca;
				}
				
				.result-action.delete:hover:not(:disabled) {
					background: #fef2f2;
					color: #dc2626;
					border-color: #fca5a5;
				}
				
				.action-spinner,
				.action-icon {
					font-size: 11px;
					line-height: 1;
				}
				
				.result-stats {
					display: flex;
					gap: 16px;
					color: #6b7280;
					font-size: 12px;
				}
				
				.result-stat {
					display: flex;
					align-items: center;
					gap: 4px;
					font-weight: 500;
				}
				
				.result-stat span:first-child {
					font-weight: 600;
					color: #374151;
				}
				
				.quality-indicator {
					position: absolute;
					top: 16px;
					right: 16px;
					width: 32px;
					height: 32px;
					border-radius: 50%;
					display: flex;
					align-items: center;
					justify-content: center;
					font-size: 12px;
					font-weight: 600;
					color: white;
					z-index: 1;
				}
				
				.quality-high {
					background: linear-gradient(135deg, #10b981, #059669);
				}
				
				.quality-medium {
					background: linear-gradient(135deg, #f59e0b, #d97706);
				}
				
				.quality-low {
					background: linear-gradient(135deg, #ef4444, #dc2626);
				}
				
				/* Mobile responsive */
				@media (max-width: 640px) {
					.result-card-header {
						padding: 16px 16px 12px;
					}
					
					.result-card-content {
						padding: 16px;
					}
					
					.result-card-footer {
						padding: 12px 16px;
						flex-direction: column;
						align-items: stretch;
						gap: 12px;
					}
					
					.result-actions {
						justify-content: center;
					}
					
					.result-stats {
						justify-content: center;
					}
					
					.quality-indicator {
						top: 12px;
						right: 12px;
						width: 28px;
						height: 28px;
						font-size: 11px;
					}
				}
				
				/* High contrast mode support */
				@media (prefers-contrast: high) {
					.result-card {
						border-width: 2px;
					}
					
					.result-action:focus {
						outline: 2px solid;
						outline-offset: 2px;
					}
				}
				
				/* Reduced motion support */
				@media (prefers-reduced-motion: reduce) {
					.result-card,
					.result-card::before,
					.result-action {
						transition: none;
					}
					
					.result-card:hover {
						transform: none;
					}
				}
			`}</style>
		</div>
	);
};

// Skeleton loading component
const ResultCardSkeleton = ({ className = '' }) => {
	return (
		<div className={`result-skeleton ${className}`.trim()}>
			<div className="skeleton-header">
				<div className="skeleton-line title"></div>
				<div className="skeleton-line meta"></div>
			</div>
			<div className="skeleton-content">
				<div className="skeleton-line text"></div>
				<div className="skeleton-line text"></div>
				<div className="skeleton-line text short"></div>
			</div>
			<div className="skeleton-footer">
				<div className="skeleton-actions">
					<div className="skeleton-button"></div>
					<div className="skeleton-button"></div>
				</div>
			</div>
			
			<style jsx>{`
				.result-skeleton {
					background: white;
					border-radius: 16px;
					padding: 0;
					box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
					border: 1px solid #f1f5f9;
					overflow: hidden;
				}
				
				.skeleton-header {
					padding: 20px 20px 16px;
					border-bottom: 1px solid #f1f5f9;
					background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
				}
				
				.skeleton-content {
					padding: 20px;
				}
				
				.skeleton-footer {
					padding: 16px 20px;
					border-top: 1px solid #f1f5f9;
					background: #fafbfc;
				}
				
				.skeleton-actions {
					display: flex;
					gap: 8px;
				}
				
				.skeleton-line {
					height: 12px;
					background: linear-gradient(90deg, #f1f5f9 25%, #e5e7eb 50%, #f1f5f9 75%);
					background-size: 200% 100%;
					border-radius: 6px;
					animation: skeleton-loading 1.5s infinite;
					margin-bottom: 8px;
				}
				
				.skeleton-line.title {
					height: 16px;
					width: 70%;
					margin-bottom: 12px;
				}
				
				.skeleton-line.meta {
					height: 10px;
					width: 50%;
				}
				
				.skeleton-line.text {
					margin-bottom: 8px;
				}
				
				.skeleton-line.text.short {
					width: 60%;
				}
				
				.skeleton-button {
					height: 24px;
					width: 60px;
					background: linear-gradient(90deg, #f1f5f9 25%, #e5e7eb 50%, #f1f5f9 75%);
					background-size: 200% 100%;
					border-radius: 6px;
					animation: skeleton-loading 1.5s infinite;
				}
				
				@keyframes skeleton-loading {
					0% { background-position: 200% 0; }
					100% { background-position: -200% 0; }
				}
				
				/* Mobile responsive */
				@media (max-width: 640px) {
					.skeleton-header {
						padding: 16px 16px 12px;
					}
					
					.skeleton-content {
						padding: 16px;
					}
					
					.skeleton-footer {
						padding: 12px 16px;
					}
				}
			`}</style>
		</div>
	);
};

// Results grid component
const ResultsGrid = ({ 
	results = [], 
	loading = false, 
	onResultAction,
	emptyStateTitle = null,
	emptyStateMessage = null,
	className = '' 
}) => {
	const handleResultAction = (action, result) => {
		if (onResultAction) {
			onResultAction(action, result);
		}
	};
	
	if (loading) {
		return (
			<div className={`results-loading ${className}`.trim()}>
				{[...Array(3)].map((_, index) => (
					<ResultCardSkeleton key={index} />
				))}
			</div>
		);
	}
	
	if (results.length === 0) {
		return (
			<div className={`results-empty ${className}`.trim()}>
				<div className="results-empty-icon">
					<svg width="64" height="64" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
					</svg>
				</div>
				<h3 className="results-empty-title">
					{emptyStateTitle || __('No content generated yet', 'rwp-creator-suite')}
				</h3>
				<p className="results-empty-text">
					{emptyStateMessage || __('Fill in your content above and click generate to see AI-powered results appear here.', 'rwp-creator-suite')}
				</p>
			</div>
		);
	}
	
	return (
		<div className={`results-grid ${className}`.trim()}>
			{results.map((result, index) => (
				<ResultCard
					key={result.id || index}
					result={result}
					onCopy={(result) => handleResultAction('copy', result)}
					onEdit={(result) => handleResultAction('edit', result)}
					onSave={(result) => handleResultAction('save', result)}
					onDelete={(result) => handleResultAction('delete', result)}
				/>
			))}
			
			<style jsx>{`
				.results-loading,
				.results-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
					gap: 20px;
					margin: 24px 0;
				}
				
				.results-empty {
					text-align: center;
					padding: 60px 20px;
					color: #6b7280;
					max-width: 400px;
					margin: 24px auto;
				}
				
				.results-empty-icon {
					width: 64px;
					height: 64px;
					margin: 0 auto 20px;
					opacity: 0.5;
				}
				
				.results-empty-title {
					font-size: 18px;
					font-weight: 600;
					color: #374151;
					margin: 0 0 8px;
				}
				
				.results-empty-text {
					font-size: 15px;
					margin: 0;
					line-height: 1.5;
				}
				
				/* Mobile responsive */
				@media (max-width: 640px) {
					.results-loading,
					.results-grid {
						grid-template-columns: 1fr;
						gap: 16px;
						margin: 20px 0;
					}
					
					.results-empty {
						padding: 40px 16px;
					}
					
					.results-empty-icon {
						width: 48px;
						height: 48px;
						margin-bottom: 16px;
					}
					
					.results-empty-title {
						font-size: 16px;
					}
					
					.results-empty-text {
						font-size: 14px;
					}
				}
			`}</style>
		</div>
	);
};

export { ResultCard, ResultCardSkeleton, ResultsGrid };
export default ResultCard;