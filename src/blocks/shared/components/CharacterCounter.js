/**
 * Character Counter Component
 *
 * Reusable React component for displaying character count with platform-specific limits.
 * Provides visual feedback and warnings for content length.
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';

const CharacterCounter = ( {
	text = '',
	selectedPlatforms = [],
	showIndividualLimits = true,
	showOverallStatus = true,
	customLimits = null,
	warningThreshold = 0.8, // Show warning at 80% of limit
	className = '',
} ) => {
	// Platform character limits
	const defaultLimits = {
		twitter: 280,
		instagram: 2200,
		facebook: 63206,
		linkedin: 3000,
		tiktok: 2200,
		youtube: 5000,
		pinterest: 500,
	};

	const platformNames = {
		twitter: __( 'Twitter/X', 'rwp-creator-suite' ),
		instagram: __( 'Instagram', 'rwp-creator-suite' ),
		facebook: __( 'Facebook', 'rwp-creator-suite' ),
		linkedin: __( 'LinkedIn', 'rwp-creator-suite' ),
		tiktok: __( 'TikTok', 'rwp-creator-suite' ),
		youtube: __( 'YouTube', 'rwp-creator-suite' ),
		pinterest: __( 'Pinterest', 'rwp-creator-suite' ),
	};

	const limits = customLimits || defaultLimits;
	const characterCount = text.length;

	// Calculate status for each platform
	const getPlatformStatus = ( platform ) => {
		const limit = limits[ platform ];
		if ( ! limit ) {
			return null;
		}

		const percentage = ( characterCount / limit ) * 100;
		const remaining = limit - characterCount;

		let status = 'good';
		if ( percentage >= 100 ) {
			status = 'over';
		} else if ( percentage >= warningThreshold * 100 ) {
			status = 'warning';
		}

		return {
			platform,
			limit,
			current: characterCount,
			percentage,
			remaining,
			status,
			name: platformNames[ platform ] || platform,
		};
	};

	// Get overall status
	const getOverallStatus = () => {
		if ( selectedPlatforms.length === 0 ) {
			return {
				status: 'none',
				message: __( 'No platforms selected', 'rwp-creator-suite' ),
			};
		}

		const platformStatuses = selectedPlatforms
			.map( getPlatformStatus )
			.filter( Boolean );

		if ( platformStatuses.some( ( p ) => p.status === 'over' ) ) {
			const overPlatforms = platformStatuses.filter(
				( p ) => p.status === 'over'
			);
			return {
				status: 'over',
				message: sprintf(
					_n(
						'Exceeds limit for %s',
						'Exceeds limits for %s',
						overPlatforms.length,
						'rwp-creator-suite'
					),
					overPlatforms.map( ( p ) => p.name ).join( ', ' )
				),
			};
		}

		if ( platformStatuses.some( ( p ) => p.status === 'warning' ) ) {
			const warningPlatforms = platformStatuses.filter(
				( p ) => p.status === 'warning'
			);
			return {
				status: 'warning',
				message: sprintf(
					_n(
						'Approaching limit for %s',
						'Approaching limits for %s',
						warningPlatforms.length,
						'rwp-creator-suite'
					),
					warningPlatforms.map( ( p ) => p.name ).join( ', ' )
				),
			};
		}

		return {
			status: 'good',
			message: __(
				'Within limits for all platforms',
				'rwp-creator-suite'
			),
		};
	};

	const getStatusIcon = ( status ) => {
		switch ( status ) {
			case 'good':
				return '✅';
			case 'warning':
				return '⚠️';
			case 'over':
				return '❌';
			default:
				return 'ℹ️';
		}
	};

	const getStatusColor = ( status ) => {
		switch ( status ) {
			case 'good':
				return '#28a745';
			case 'warning':
				return '#ffc107';
			case 'over':
				return '#dc3545';
			default:
				return '#6c757d';
		}
	};

	const renderProgressBar = ( percentage, status ) => {
		const color = getStatusColor( status );
		const width = Math.min( percentage, 100 );

		return (
			<div className="progress-bar-container">
				<div
					className="progress-bar"
					style={ {
						width: `${ width }%`,
						backgroundColor: color,
					} }
				/>
				<div className="progress-text">
					{ Math.round( percentage ) }%
				</div>
			</div>
		);
	};

	const renderPlatformLimit = ( platformStatus ) => {
		if ( ! platformStatus ) {
			return null;
		}

		const {
			platform,
			name,
			current,
			limit,
			remaining,
			percentage,
			status,
		} = platformStatus;

		return (
			<div key={ platform } className={ `platform-limit ${ status }` }>
				<div className="platform-header">
					<span className="platform-name">
						{ getStatusIcon( status ) } { name }
					</span>
					<span className="character-info">
						{ current }/{ limit }
					</span>
				</div>

				{ renderProgressBar( percentage, status ) }

				<div className="platform-details">
					<span
						className={ `remaining ${
							remaining < 0 ? 'negative' : ''
						}` }
					>
						{ remaining >= 0
							? sprintf(
									__(
										'%d characters remaining',
										'rwp-creator-suite'
									),
									remaining
							  )
							: sprintf(
									__(
										'%d characters over limit',
										'rwp-creator-suite'
									),
									Math.abs( remaining )
							  ) }
					</span>
				</div>
			</div>
		);
	};

	const overallStatus = getOverallStatus();

	return (
		<div className={ `rwp-character-counter ${ className }` }>
			{ /* Overall Status */ }
			{ showOverallStatus && (
				<div className={ `overall-status ${ overallStatus.status }` }>
					<div className="status-header">
						<span className="status-icon">
							{ getStatusIcon( overallStatus.status ) }
						</span>
						<span className="status-message">
							{ overallStatus.message }
						</span>
						<span className="total-count">
							{ sprintf(
								__(
									'%d characters total',
									'rwp-creator-suite'
								),
								characterCount
							) }
						</span>
					</div>
				</div>
			) }

			{ /* Individual Platform Limits */ }
			{ showIndividualLimits && selectedPlatforms.length > 0 && (
				<div className="platform-limits">
					<h4>{ __( 'Platform Limits', 'rwp-creator-suite' ) }</h4>
					<div className="limits-grid">
						{ selectedPlatforms.map( ( platform ) =>
							renderPlatformLimit( getPlatformStatus( platform ) )
						) }
					</div>
				</div>
			) }

			{ /* Tips for optimization */ }
			{ characterCount > 0 && overallStatus.status === 'over' && (
				<div className="optimization-tips">
					<h5>{ __( 'Optimization Tips:', 'rwp-creator-suite' ) }</h5>
					<ul>
						<li>
							{ __(
								'Try removing unnecessary words or phrases',
								'rwp-creator-suite'
							) }
						</li>
						<li>
							{ __(
								'Use shorter synonyms where possible',
								'rwp-creator-suite'
							) }
						</li>
						<li>
							{ __(
								'Consider breaking into multiple posts for platforms with strict limits',
								'rwp-creator-suite'
							) }
						</li>
						<li>
							{ __(
								'Remove or reduce hashtags if they push you over the limit',
								'rwp-creator-suite'
							) }
						</li>
					</ul>
				</div>
			) }

			<style jsx>{ `
				.rwp-character-counter {
					border: 1px solid #e0e0e0;
					border-radius: 6px;
					padding: 16px;
					margin: 16px 0;
					background: #fff;
				}

				.overall-status {
					padding: 12px;
					border-radius: 4px;
					margin-bottom: 16px;
				}

				.overall-status.good {
					background: #d4edda;
					border: 1px solid #c3e6cb;
				}

				.overall-status.warning {
					background: #fff3cd;
					border: 1px solid #ffeaa7;
				}

				.overall-status.over {
					background: #f8d7da;
					border: 1px solid #f5c6cb;
				}

				.overall-status.none {
					background: #e2e3e5;
					border: 1px solid #d6d8db;
				}

				.status-header {
					display: flex;
					align-items: center;
					gap: 8px;
				}

				.status-icon {
					font-size: 16px;
				}

				.status-message {
					flex: 1;
					font-weight: 500;
				}

				.total-count {
					font-size: 14px;
					color: #666;
				}

				.platform-limits h4 {
					margin: 0 0 12px 0;
					font-size: 16px;
					color: #1e1e1e;
				}

				.limits-grid {
					display: grid;
					gap: 12px;
				}

				.platform-limit {
					border: 1px solid #e0e0e0;
					border-radius: 4px;
					padding: 12px;
					background: #f8f9fa;
				}

				.platform-limit.warning {
					border-color: #ffc107;
					background: #fff8e1;
				}

				.platform-limit.over {
					border-color: #dc3545;
					background: #ffebee;
				}

				.platform-header {
					display: flex;
					justify-content: space-between;
					align-items: center;
					margin-bottom: 8px;
				}

				.platform-name {
					font-weight: 600;
					color: #1e1e1e;
				}

				.character-info {
					font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
					font-size: 14px;
					color: #666;
				}

				.progress-bar-container {
					position: relative;
					height: 20px;
					background: #e9ecef;
					border-radius: 10px;
					overflow: hidden;
					margin-bottom: 8px;
				}

				.progress-bar {
					height: 100%;
					transition:
						width 0.3s ease,
						background-color 0.3s ease;
				}

				.progress-text {
					position: absolute;
					top: 50%;
					left: 50%;
					transform: translate( -50%, -50% );
					font-size: 12px;
					font-weight: 600;
					color: #1e1e1e;
					text-shadow: 0 0 3px rgba( 255, 255, 255, 0.8 );
				}

				.platform-details {
					text-align: center;
				}

				.remaining {
					font-size: 13px;
					color: #666;
				}

				.remaining.negative {
					color: #dc3545;
					font-weight: 600;
				}

				.optimization-tips {
					margin-top: 16px;
					padding: 12px;
					background: #fff3cd;
					border: 1px solid #ffeaa7;
					border-radius: 4px;
				}

				.optimization-tips h5 {
					margin: 0 0 8px 0;
					color: #856404;
				}

				.optimization-tips ul {
					margin: 0;
					padding-left: 20px;
				}

				.optimization-tips li {
					margin-bottom: 4px;
					font-size: 14px;
					color: #856404;
				}

				@media ( min-width: 768px ) {
					.limits-grid {
						grid-template-columns: repeat(
							auto-fit,
							minmax( 300px, 1fr )
						);
					}
				}
			` }</style>
		</div>
	);
};

export default CharacterCounter;
