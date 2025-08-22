/**
 * Enhanced Guest Teaser Component
 *
 * Modern gradient-styled guest teaser with animated elements,
 * feature highlights, and compelling call-to-action.
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

const EnhancedGuestTeaser = ({
	features = null,
	onLogin,
	onSignup,
	title = null,
	subtitle = null,
	ctaText = null,
	showFeatures = true,
	className = ''
}) => {
	const [isAnimating, setIsAnimating] = useState(false);
	
	const defaultFeatures = [
		{
			icon: '🤖',
			title: __('AI-Powered Content', 'rwp-creator-suite'),
			description: __('Generate captions and content using advanced AI technology', 'rwp-creator-suite')
		},
		{
			icon: '📱',
			title: __('Multi-Platform Support', 'rwp-creator-suite'),
			description: __('Optimize content for Instagram, Twitter, Facebook, and more', 'rwp-creator-suite')
		},
		{
			icon: '✨',
			title: __('Smart Templates', 'rwp-creator-suite'),
			description: __('Access professional templates for every content type', 'rwp-creator-suite')
		},
		{
			icon: '📊',
			title: __('Analytics & Insights', 'rwp-creator-suite'),
			description: __('Track performance and optimize your content strategy', 'rwp-creator-suite')
		}
	];
	
	const displayFeatures = features || defaultFeatures;
	const displayTitle = title || __('Unlock Professional Creator Tools', 'rwp-creator-suite');
	const displaySubtitle = subtitle || __('Join thousands of creators who use our AI-powered tools to grow their audience and create engaging content.', 'rwp-creator-suite');
	const displayCtaText = ctaText || __('Get Started Free', 'rwp-creator-suite');
	
	const handleCtaClick = () => {
		setIsAnimating(true);
		setTimeout(() => setIsAnimating(false), 300);
		
		if (onSignup) {
			onSignup();
		}
	};
	
	const containerClasses = `guest-teaser-enhanced ${className}`.trim();
	
	return (
		<div className={containerClasses}>
			<div className="teaser-content">
				<div className="teaser-icon">
					<svg width="32" height="32" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
					</svg>
				</div>
				
				<h2 className="teaser-title">
					{displayTitle}
				</h2>
				
				<p className="teaser-subtitle">
					{displaySubtitle}
				</p>
				
				{showFeatures && (
					<div className="feature-grid">
						{displayFeatures.map((feature, index) => (
							<div key={index} className="feature-item">
								<div className="feature-icon">
									{typeof feature.icon === 'string' ? (
										<span>{feature.icon}</span>
									) : (
										feature.icon
									)}
								</div>
								<h3 className="feature-title">{feature.title}</h3>
								<p className="feature-description">{feature.description}</p>
							</div>
						))}
					</div>
				)}
				
				<div className="teaser-cta">
					<button 
						className={`cta-button ${isAnimating ? 'animating' : ''}`}
						onClick={handleCtaClick}
						type="button"
					>
						<span className="cta-button-text">{displayCtaText}</span>
					</button>
					
					{onLogin && (
						<p className="login-prompt">
							{__('Already have an account?', 'rwp-creator-suite')}{' '}
							<button 
								className="login-link"
								onClick={onLogin}
								type="button"
							>
								{__('Sign in', 'rwp-creator-suite')}
							</button>
						</p>
					)}
				</div>
			</div>
			
			<style jsx>{`
				.guest-teaser-enhanced {
					background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
					border-radius: 20px;
					padding: 48px 32px;
					color: white;
					text-align: center;
					position: relative;
					overflow: hidden;
					margin: 32px 0;
				}
				
				.guest-teaser-enhanced::before {
					content: '';
					position: absolute;
					top: -50%;
					right: -50%;
					width: 100%;
					height: 100%;
					background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
					animation: pulse 4s ease-in-out infinite;
				}
				
				@keyframes pulse {
					0%, 100% { opacity: 1; transform: scale(1); }
					50% { opacity: 0.8; transform: scale(1.05); }
				}
				
				.teaser-content {
					position: relative;
					z-index: 2;
					max-width: 800px;
					margin: 0 auto;
				}
				
				.teaser-icon {
					width: 64px;
					height: 64px;
					margin: 0 auto 24px;
					background: rgba(255, 255, 255, 0.2);
					border-radius: 50%;
					display: flex;
					align-items: center;
					justify-content: center;
					backdrop-filter: blur(10px);
				}
				
				.teaser-title {
					font-size: 28px;
					font-weight: 700;
					margin: 0 0 16px;
					background: linear-gradient(45deg, #ffffff, #e0e7ff);
					-webkit-background-clip: text;
					-webkit-text-fill-color: transparent;
					background-clip: text;
					line-height: 1.2;
				}
				
				.teaser-subtitle {
					font-size: 18px;
					margin: 0 0 32px;
					opacity: 0.9;
					line-height: 1.5;
					max-width: 600px;
					margin-left: auto;
					margin-right: auto;
				}
				
				.feature-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
					gap: 20px;
					margin: 32px 0;
				}
				
				.feature-item {
					background: rgba(255, 255, 255, 0.1);
					backdrop-filter: blur(10px);
					padding: 24px 20px;
					border-radius: 16px;
					border: 1px solid rgba(255, 255, 255, 0.2);
					transition: all 0.3s ease;
				}
				
				.feature-item:hover {
					transform: translateY(-4px);
					background: rgba(255, 255, 255, 0.15);
					box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
				}
				
				.feature-icon {
					width: 40px;
					height: 40px;
					margin: 0 auto 16px;
					background: rgba(255, 255, 255, 0.2);
					border-radius: 12px;
					display: flex;
					align-items: center;
					justify-content: center;
					font-size: 18px;
				}
				
				.feature-title {
					font-size: 16px;
					font-weight: 600;
					margin: 0 0 8px;
					color: white;
				}
				
				.feature-description {
					font-size: 14px;
					opacity: 0.8;
					line-height: 1.4;
					margin: 0;
				}
				
				.teaser-cta {
					margin-top: 40px;
				}
				
				.cta-button {
					background: white;
					color: #667eea;
					padding: 16px 32px;
					border: none;
					border-radius: 12px;
					font-size: 16px;
					font-weight: 600;
					cursor: pointer;
					transition: all 0.3s ease;
					box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
					position: relative;
					overflow: hidden;
					min-width: 160px;
				}
				
				.cta-button::before {
					content: '';
					position: absolute;
					top: 0;
					left: 0;
					right: 0;
					bottom: 0;
					background: linear-gradient(45deg, #f8fafc, #e2e8f0);
					opacity: 0;
					transition: opacity 0.3s ease;
				}
				
				.cta-button:hover {
					transform: translateY(-2px);
					box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
				}
				
				.cta-button:hover::before {
					opacity: 1;
				}
				
				.cta-button:active,
				.cta-button.animating {
					transform: translateY(0);
					box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
				}
				
				.cta-button-text {
					position: relative;
					z-index: 2;
				}
				
				.login-prompt {
					margin-top: 16px;
					opacity: 0.8;
					font-size: 14px;
					line-height: 1.4;
				}
				
				.login-link {
					background: none;
					border: none;
					color: white;
					text-decoration: underline;
					cursor: pointer;
					font-size: 14px;
					font-weight: 500;
					transition: opacity 0.2s ease;
				}
				
				.login-link:hover {
					opacity: 0.8;
				}
				
				.login-link:focus {
					outline: 2px solid rgba(255, 255, 255, 0.5);
					outline-offset: 2px;
					border-radius: 4px;
				}
				
				/* Mobile responsive */
				@media (max-width: 768px) {
					.guest-teaser-enhanced {
						padding: 32px 24px;
						border-radius: 16px;
					}
					
					.teaser-icon {
						width: 48px;
						height: 48px;
						margin-bottom: 20px;
					}
					
					.teaser-icon svg {
						width: 24px;
						height: 24px;
					}
					
					.teaser-title {
						font-size: 24px;
						margin-bottom: 12px;
					}
					
					.teaser-subtitle {
						font-size: 16px;
						margin-bottom: 24px;
					}
					
					.feature-grid {
						grid-template-columns: 1fr;
						gap: 16px;
						margin: 24px 0;
					}
					
					.feature-item {
						padding: 20px 16px;
					}
					
					.feature-icon {
						width: 32px;
						height: 32px;
						font-size: 16px;
						margin-bottom: 12px;
					}
					
					.feature-title {
						font-size: 15px;
					}
					
					.feature-description {
						font-size: 13px;
					}
					
					.teaser-cta {
						margin-top: 24px;
					}
					
					.cta-button {
						padding: 14px 24px;
						font-size: 15px;
						width: 100%;
						max-width: 280px;
					}
				}
				
				@media (max-width: 480px) {
					.guest-teaser-enhanced {
						padding: 24px 16px;
						margin: 24px 0;
					}
					
					.teaser-title {
						font-size: 22px;
					}
					
					.teaser-subtitle {
						font-size: 15px;
					}
					
					.feature-item {
						padding: 16px 12px;
					}
				}
				
				/* High contrast mode support */
				@media (prefers-contrast: high) {
					.feature-item {
						border-width: 2px;
					}
					
					.cta-button:focus,
					.login-link:focus {
						outline-width: 3px;
					}
				}
				
				/* Reduced motion support */
				@media (prefers-reduced-motion: reduce) {
					.guest-teaser-enhanced::before {
						animation: none;
					}
					
					.feature-item,
					.cta-button {
						transition: none;
					}
					
					.feature-item:hover,
					.cta-button:hover {
						transform: none;
					}
				}
				
				/* Dark mode support */
				@media (prefers-color-scheme: dark) {
					.guest-teaser-enhanced {
						background: linear-gradient(135deg, #4c1d95 0%, #581c87 100%);
					}
					
					.teaser-title {
						background: linear-gradient(45deg, #ffffff, #f3f4f6);
						-webkit-background-clip: text;
						-webkit-text-fill-color: transparent;
						background-clip: text;
					}
				}
			`}</style>
		</div>
	);
};

export default EnhancedGuestTeaser;