/**
 * Character Meter Component
 *
 * Visual character counter with platform-specific progress bars,
 * dynamic color coding, and animated shimmer effects.
 */

import { __ } from '@wordpress/i18n';
import { sprintf } from '@wordpress/i18n';

const CharacterMeter = ( { 
	content, 
	selectedPlatforms = [],
	className = '',
	showOverallStatus = true 
} ) => {
	// Platform limits and icons
	const platformConfig = {
		twitter: { limit: 280, icon: '🐦', name: __( 'Twitter/X', 'rwp-creator-suite' ) },
		instagram: { limit: 2200, icon: '📸', name: __( 'Instagram', 'rwp-creator-suite' ) },
		facebook: { limit: 63206, icon: '👥', name: __( 'Facebook', 'rwp-creator-suite' ) },
		linkedin: { limit: 3000, icon: '💼', name: __( 'LinkedIn', 'rwp-creator-suite' ) },
		tiktok: { limit: 2200, icon: '🎵', name: __( 'TikTok', 'rwp-creator-suite' ) },
		youtube: { limit: 5000, icon: '🎥', name: __( 'YouTube', 'rwp-creator-suite' ) },
		pinterest: { limit: 500, icon: '📌', name: __( 'Pinterest', 'rwp-creator-suite' ) }
	};

	const currentLength = content.length;

	const getCharacterStatus = ( count, limit ) => {
		const percentage = ( count / limit ) * 100;
		if ( count > limit ) return 'over';
		if ( percentage > 90 ) return 'danger';
		if ( percentage > 75 ) return 'warning';
		return 'safe';
	};

	const getStatusColors = ( status ) => {
		switch ( status ) {
			case 'safe':
				return {
					bg: 'from-green-500 to-emerald-600',
					text: 'text-green-700',
					bgLight: 'bg-green-50',
					border: 'border-green-300'
				};
			case 'warning':
				return {
					bg: 'from-amber-500 to-yellow-600',
					text: 'text-amber-700',
					bgLight: 'bg-amber-50',
					border: 'border-amber-300'
				};
			case 'danger':
				return {
					bg: 'from-orange-500 to-red-600',
					text: 'text-red-700',
					bgLight: 'bg-red-50',
					border: 'border-red-300'
				};
			case 'over':
				return {
					bg: 'from-red-600 to-red-800',
					text: 'text-red-800',
					bgLight: 'bg-red-50',
					border: 'border-red-400'
				};
			default:
				return getStatusColors( 'safe' );
		}
	};

	const getOverallStatus = () => {
		if ( selectedPlatforms.length === 0 ) {
			return {
				status: 'none',
				message: __( 'No platforms selected', 'rwp-creator-suite' ),
				icon: 'ℹ️'
			};
		}

		const platformStatuses = selectedPlatforms.map( platformId => {
			const config = platformConfig[ platformId ];
			if ( !config ) return null;
			
			const status = getCharacterStatus( currentLength, config.limit );
			return { platformId, status, config };
		} ).filter( Boolean );

		const overLimit = platformStatuses.filter( p => p.status === 'over' );
		const danger = platformStatuses.filter( p => p.status === 'danger' );
		const warning = platformStatuses.filter( p => p.status === 'warning' );

		if ( overLimit.length > 0 ) {
			return {
				status: 'over',
				message: sprintf(
					_n(
						'Exceeds limit for %s',
						'Exceeds limits for %s',
						overLimit.length,
						'rwp-creator-suite'
					),
					overLimit.map( p => p.config.name ).join( ', ' )
				),
				icon: '❌'
			};
		}

		if ( danger.length > 0 ) {
			return {
				status: 'danger',
				message: sprintf(
					_n(
						'Near limit for %s',
						'Near limits for %s',
						danger.length,
						'rwp-creator-suite'
					),
					danger.map( p => p.config.name ).join( ', ' )
				),
				icon: '⚠️'
			};
		}

		if ( warning.length > 0 ) {
			return {
				status: 'warning',
				message: sprintf(
					_n(
						'Approaching limit for %s',
						'Approaching limits for %s',
						warning.length,
						'rwp-creator-suite'
					),
					warning.map( p => p.config.name ).join( ', ' )
				),
				icon: '⚠️'
			};
		}

		return {
			status: 'safe',
			message: __( 'Within limits for all platforms', 'rwp-creator-suite' ),
			icon: '✅'
		};
	};

	const overallStatus = getOverallStatus();
	const overallColors = getStatusColors( overallStatus.status );

	if ( selectedPlatforms.length === 0 ) {
		return null;
	}

	return (
		<div 
			className={ `bg-gray-50 rounded-xl p-3 sm:p-4 border border-gray-200 ${className}` }
			role="region"
			aria-label={ __( 'Character count and platform limits', 'rwp-creator-suite' ) }
		>
			{/* Overall Status */}
			{ showOverallStatus && overallStatus.status !== 'none' && (
				<div className={ `
					p-3 rounded-lg border mb-4 flex items-center gap-3
					${overallColors.bgLight} ${overallColors.border}
				` }>
					<span className="text-lg">{ overallStatus.icon }</span>
					<div className="flex-1">
						<span className={ `font-medium ${overallColors.text}` }>
							{ overallStatus.message }
						</span>
						<div className="text-sm text-gray-600 mt-1">
							{ sprintf(
								__( '%d characters total', 'rwp-creator-suite' ),
								currentLength
							) }
						</div>
					</div>
				</div>
			) }

			{/* Platform Meters */}
			<div className="space-y-3">
				{ selectedPlatforms.map( platformId => {
					const config = platformConfig[ platformId ];
					if ( !config ) return null;

					const status = getCharacterStatus( currentLength, config.limit );
					const colors = getStatusColors( status );
					const percentage = Math.min( ( currentLength / config.limit ) * 100, 100 );
					const remaining = config.limit - currentLength;

					return (
						<div key={ platformId } className="space-y-2">
							{/* Platform Header */}
							<div className="flex items-center justify-between">
								<div className="flex items-center gap-1 sm:gap-2">
									<span className="text-xs sm:text-sm">{ config.icon }</span>
									<span className="text-xs sm:text-sm font-semibold text-gray-700 uppercase tracking-wide">
										{ config.name }
									</span>
								</div>
								<span className="text-xs sm:text-sm font-medium text-gray-600">
									{ currentLength }/{ config.limit }
								</span>
							</div>

							{/* Progress Bar */}
							<div 
								className="h-2 bg-gray-200 rounded-full overflow-hidden relative"
								role="progressbar"
								aria-valuenow={ currentLength }
								aria-valuemin={ 0 }
								aria-valuemax={ config.limit }
								aria-label={ sprintf(
									__( '%s character usage: %d of %d characters', 'rwp-creator-suite' ),
									config.name,
									currentLength,
									config.limit
								) }
							>
								<div 
									className={ `
										h-full bg-gradient-to-r transition-all duration-300 ease-out relative
										${colors.bg}
									` }
									style={ { width: `${percentage}%` } }
								>
									{/* Animated shimmer effect */}
									<div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-shimmer" />
								</div>
							</div>

							{/* Status Text */}
							<div className={ `text-xs font-medium ${colors.text}` }>
								{ remaining >= 0 
									? sprintf(
										__( '%d characters remaining', 'rwp-creator-suite' ),
										remaining
									)
									: sprintf(
										__( '%d characters over limit', 'rwp-creator-suite' ),
										Math.abs( remaining )
									)
								}
							</div>
						</div>
					);
				} ) }
			</div>

			{/* Tips for over limit */}
			{ overallStatus.status === 'over' && (
				<div className="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
					<h5 className="text-sm font-semibold text-amber-800 mb-2">
						{ __( 'Optimization Tips:', 'rwp-creator-suite' ) }
					</h5>
					<ul className="text-xs text-amber-700 space-y-1">
						<li>• { __( 'Remove unnecessary words or phrases', 'rwp-creator-suite' ) }</li>
						<li>• { __( 'Use shorter synonyms where possible', 'rwp-creator-suite' ) }</li>
						<li>• { __( 'Consider breaking into multiple posts', 'rwp-creator-suite' ) }</li>
						<li>• { __( 'Reduce hashtags if they push you over', 'rwp-creator-suite' ) }</li>
					</ul>
				</div>
			) }

			{/* Custom shimmer animation */}
			<style jsx>{ `
				@keyframes shimmer {
					0% { transform: translateX(-100%); }
					100% { transform: translateX(100%); }
				}
				.animate-shimmer {
					animation: shimmer 2s infinite;
				}
			` }</style>
		</div>
	);
};

export default CharacterMeter;