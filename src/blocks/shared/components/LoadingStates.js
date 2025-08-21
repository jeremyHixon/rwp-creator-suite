/**
 * Loading States Component
 *
 * Reusable React component for displaying various loading states.
 * Provides consistent loading UI across all blocks.
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';

const LoadingStates = ( {
	isLoading = false,
	loadingType = 'default', // 'default', 'generating', 'processing', 'analyzing', 'uploading'
	loadingMessage = null,
	progress = null, // 0-100 for progress bar
	estimatedTime = null, // in seconds
	showProgress = false,
	showCancel = false,
	onCancel = null,
	className = '',
} ) => {
	const [ elapsed, setElapsed ] = useState( 0 );
	const [ dots, setDots ] = useState( '' );

	// Timer for elapsed time
	useEffect( () => {
		if ( ! isLoading ) {
			setElapsed( 0 );
			return;
		}

		const timer = setInterval( () => {
			setElapsed( ( prev ) => prev + 1 );
		}, 1000 );

		return () => clearInterval( timer );
	}, [ isLoading ] );

	// Animated dots
	useEffect( () => {
		if ( ! isLoading ) {
			setDots( '' );
			return;
		}

		const dotTimer = setInterval( () => {
			setDots( ( prev ) => {
				if ( prev === '...' ) {
					return '';
				}
				return prev + '.';
			} );
		}, 500 );

		return () => clearInterval( dotTimer );
	}, [ isLoading ] );

	const getLoadingMessages = () => {
		const messages = {
			default: {
				title: __( 'Loading', 'rwp-creator-suite' ),
				subtitle: __( 'Please wait…', 'rwp-creator-suite' ),
				icon: '⏳',
			},
			generating: {
				title: __( 'Generating Content', 'rwp-creator-suite' ),
				subtitle: __(
					'AI is creating your content…',
					'rwp-creator-suite'
				),
				icon: '🤖',
			},
			processing: {
				title: __( 'Processing', 'rwp-creator-suite' ),
				subtitle: __(
					'Analyzing and optimizing…',
					'rwp-creator-suite'
				),
				icon: '⚙️',
			},
			analyzing: {
				title: __( 'Analyzing', 'rwp-creator-suite' ),
				subtitle: __(
					'Examining content and gathering insights…',
					'rwp-creator-suite'
				),
				icon: '📊',
			},
			uploading: {
				title: __( 'Uploading', 'rwp-creator-suite' ),
				subtitle: __(
					'Transferring your content…',
					'rwp-creator-suite'
				),
				icon: '📤',
			},
		};

		return messages[ loadingType ] || messages.default;
	};

	const formatTime = ( seconds ) => {
		if ( seconds < 60 ) {
			return sprintf( __( '%d seconds', 'rwp-creator-suite' ), seconds );
		}
		const minutes = Math.floor( seconds / 60 );
		const remainingSeconds = seconds % 60;
		return sprintf(
			__( '%d:%02d', 'rwp-creator-suite' ),
			minutes,
			remainingSeconds
		);
	};

	const getEstimatedRemaining = () => {
		if ( ! estimatedTime || ! progress ) {
			return null;
		}

		const progressDecimal = progress / 100;
		if ( progressDecimal === 0 ) {
			return estimatedTime;
		}

		const totalEstimated = elapsed / progressDecimal;
		const remaining = Math.max( 0, totalEstimated - elapsed );
		return Math.round( remaining );
	};

	if ( ! isLoading ) {
		return null;
	}

	const loadingConfig = getLoadingMessages();
	const displayMessage = loadingMessage || loadingConfig.subtitle;
	const remaining = getEstimatedRemaining();

	return (
		<div className={ `rwp-loading-states ${ className }` }>
			<div className="loading-container">
				{ /* Loading Icon and Spinner */ }
				<div className="loading-icon">
					<div className="icon-wrapper">
						<span className="loading-emoji">
							{ loadingConfig.icon }
						</span>
						<Spinner />
					</div>
				</div>

				{ /* Loading Content */ }
				<div className="loading-content">
					<h3 className="loading-title">
						{ loadingConfig.title }
						{ dots }
					</h3>

					<p className="loading-message">{ displayMessage }</p>

					{ /* Progress Bar */ }
					{ showProgress && progress !== null && (
						<div className="progress-container">
							<div className="progress-bar">
								<div
									className="progress-fill"
									style={ {
										width: `${ Math.min(
											progress,
											100
										) }%`,
									} }
								/>
							</div>
							<div className="progress-text">
								{ Math.round( progress ) }%
							</div>
						</div>
					) }

					{ /* Time Information */ }
					<div className="time-info">
						{ elapsed > 0 && (
							<div className="elapsed-time">
								{ sprintf(
									__( 'Elapsed: %s', 'rwp-creator-suite' ),
									formatTime( elapsed )
								) }
							</div>
						) }

						{ remaining !== null && remaining > 0 && (
							<div className="estimated-remaining">
								{ sprintf(
									__(
										'Estimated remaining: %s',
										'rwp-creator-suite'
									),
									formatTime( remaining )
								) }
							</div>
						) }

						{ estimatedTime && ! progress && (
							<div className="estimated-total">
								{ sprintf(
									__(
										'Estimated time: %s',
										'rwp-creator-suite'
									),
									formatTime( estimatedTime )
								) }
							</div>
						) }
					</div>

					{ /* Cancel Button */ }
					{ showCancel && onCancel && (
						<div className="loading-actions">
							<button
								className="cancel-button"
								onClick={ onCancel }
								type="button"
							>
								{ __( 'Cancel', 'rwp-creator-suite' ) }
							</button>
						</div>
					) }
				</div>
			</div>

			<style jsx>{ `
				.rwp-loading-states {
					display: flex;
					align-items: center;
					justify-content: center;
					min-height: 200px;
					padding: 24px;
					background: rgba( 255, 255, 255, 0.95 );
					border: 1px solid #e0e0e0;
					border-radius: 8px;
					backdrop-filter: blur( 2px );
				}

				.loading-container {
					text-align: center;
					max-width: 400px;
					width: 100%;
				}

				.loading-icon {
					margin-bottom: 20px;
				}

				.icon-wrapper {
					position: relative;
					display: inline-block;
				}

				.loading-emoji {
					font-size: 48px;
					line-height: 1;
					display: block;
					margin-bottom: 12px;
					animation: bounce 2s infinite;
				}

				.loading-content {
					color: #1e1e1e;
				}

				.loading-title {
					margin: 0 0 8px 0;
					font-size: 24px;
					font-weight: 600;
					color: #1e1e1e;
				}

				.loading-message {
					margin: 0 0 20px 0;
					font-size: 16px;
					color: #666;
					line-height: 1.4;
				}

				.progress-container {
					margin: 20px 0;
				}

				.progress-bar {
					width: 100%;
					height: 8px;
					background: #e9ecef;
					border-radius: 4px;
					overflow: hidden;
					margin-bottom: 8px;
				}

				.progress-fill {
					height: 100%;
					background: linear-gradient( 90deg, #007cba, #005a87 );
					border-radius: 4px;
					transition: width 0.3s ease;
					animation: shimmer 2s infinite;
				}

				.progress-text {
					font-size: 14px;
					font-weight: 600;
					color: #007cba;
				}

				.time-info {
					margin: 16px 0;
					font-size: 14px;
					color: #666;
				}

				.elapsed-time,
				.estimated-remaining,
				.estimated-total {
					margin: 4px 0;
				}

				.loading-actions {
					margin-top: 20px;
				}

				.cancel-button {
					background: #6c757d;
					color: white;
					border: none;
					padding: 8px 16px;
					border-radius: 4px;
					cursor: pointer;
					font-size: 14px;
					transition: background-color 0.2s ease;
				}

				.cancel-button:hover {
					background: #5a6268;
				}

				.cancel-button:focus {
					outline: 2px solid #007cba;
					outline-offset: 2px;
				}

				@keyframes bounce {
					0%,
					20%,
					50%,
					80%,
					100% {
						transform: translateY( 0 );
					}
					40% {
						transform: translateY( -10px );
					}
					60% {
						transform: translateY( -5px );
					}
				}

				@keyframes shimmer {
					0% {
						background-position: -200px 0;
					}
					100% {
						background-position: calc( 200px + 100% ) 0;
					}
				}

				.progress-fill {
					background: linear-gradient(
						90deg,
						#007cba 0%,
						#4fc3f7 50%,
						#007cba 100%
					);
					background-size: 200px 100%;
					animation: shimmer 2s infinite;
				}

				/* Responsive adjustments */
				@media ( max-width: 480px ) {
					.rwp-loading-states {
						min-height: 150px;
						padding: 16px;
					}

					.loading-emoji {
						font-size: 36px;
						margin-bottom: 8px;
					}

					.loading-title {
						font-size: 20px;
					}

					.loading-message {
						font-size: 14px;
					}
				}
			` }</style>
		</div>
	);
};

// Preset loading configurations for common use cases
export const LoadingPresets = {
	AIGeneration: ( props ) => (
		<LoadingStates
			loadingType="generating"
			estimatedTime={ 15 }
			showProgress={ true }
			showCancel={ true }
			{ ...props }
		/>
	),

	ContentAnalysis: ( props ) => (
		<LoadingStates
			loadingType="analyzing"
			estimatedTime={ 8 }
			showProgress={ true }
			{ ...props }
		/>
	),

	FileUpload: ( props ) => (
		<LoadingStates
			loadingType="uploading"
			showProgress={ true }
			showCancel={ true }
			{ ...props }
		/>
	),

	DataProcessing: ( props ) => (
		<LoadingStates
			loadingType="processing"
			estimatedTime={ 5 }
			{ ...props }
		/>
	),
};

export default LoadingStates;
