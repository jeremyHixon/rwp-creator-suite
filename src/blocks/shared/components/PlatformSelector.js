/**
 * Platform Selector Component
 *
 * Reusable React component for selecting social media platforms.
 * Provides consistent UI and behavior across all blocks.
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { CheckboxControl, PanelBody, Notice } from '@wordpress/components';

const PlatformSelector = ( {
	selectedPlatforms = [],
	onPlatformsChange,
	allowedPlatforms = null,
	maxSelections = null,
	isGuest = false,
	guestLimit = 3,
	showDescriptions = true,
	layout = 'grid', // 'grid' or 'list'
} ) => {
	const [ platforms, setPlatforms ] = useState( [] );
	const [ errors, setErrors ] = useState( [] );

	// Default platform configurations
	const defaultPlatforms = {
		instagram: {
			label: __( 'Instagram', 'rwp-creator-suite' ),
			description: __(
				'Square format, engaging visuals, hashtags',
				'rwp-creator-suite'
			),
			icon: '📸',
			characteristics: [ 'visual', 'hashtags', 'stories' ],
			maxLength: 2200,
		},
		twitter: {
			label: __( 'Twitter/X', 'rwp-creator-suite' ),
			description: __(
				'Concise, engaging, trending topics',
				'rwp-creator-suite'
			),
			icon: '🐦',
			characteristics: [ 'concise', 'trending', 'threads' ],
			maxLength: 280,
		},
		facebook: {
			label: __( 'Facebook', 'rwp-creator-suite' ),
			description: __(
				'Detailed posts, community engagement',
				'rwp-creator-suite'
			),
			icon: '👥',
			characteristics: [ 'detailed', 'community', 'links' ],
			maxLength: 63206,
		},
		linkedin: {
			label: __( 'LinkedIn', 'rwp-creator-suite' ),
			description: __(
				'Professional tone, industry insights',
				'rwp-creator-suite'
			),
			icon: '💼',
			characteristics: [ 'professional', 'industry', 'networking' ],
			maxLength: 3000,
		},
		tiktok: {
			label: __( 'TikTok', 'rwp-creator-suite' ),
			description: __(
				'Creative, trend-focused, viral content',
				'rwp-creator-suite'
			),
			icon: '🎵',
			characteristics: [ 'creative', 'trends', 'viral' ],
			maxLength: 2200,
		},
		youtube: {
			label: __( 'YouTube', 'rwp-creator-suite' ),
			description: __(
				'Video descriptions, SEO optimized',
				'rwp-creator-suite'
			),
			icon: '🎥',
			characteristics: [ 'video', 'seo', 'educational' ],
			maxLength: 5000,
		},
		pinterest: {
			label: __( 'Pinterest', 'rwp-creator-suite' ),
			description: __(
				'Visual discovery, SEO keywords',
				'rwp-creator-suite'
			),
			icon: '📌',
			characteristics: [ 'visual', 'discovery', 'keywords' ],
			maxLength: 500,
		},
	};

	useEffect( () => {
		// Initialize platforms based on allowed platforms or use all
		const availablePlatforms =
			allowedPlatforms || Object.keys( defaultPlatforms );

		const platformList = availablePlatforms.map( ( key ) => ( {
			value: key,
			...defaultPlatforms[ key ],
		} ) );

		setPlatforms( platformList );
	}, [ allowedPlatforms ] );

	const handlePlatformChange = ( platformValue, isChecked ) => {
		let newSelectedPlatforms;

		if ( isChecked ) {
			// Add platform
			newSelectedPlatforms = [ ...selectedPlatforms, platformValue ];

			// Check limits
			const effectiveMaxSelections = isGuest ? guestLimit : maxSelections;
			if (
				effectiveMaxSelections &&
				newSelectedPlatforms.length > effectiveMaxSelections
			) {
				const limitType = isGuest
					? __( 'guest users', 'rwp-creator-suite' )
					: __( 'this feature', 'rwp-creator-suite' );
				setErrors( [
					sprintf(
						__(
							'Maximum %d platforms allowed for %s',
							'rwp-creator-suite'
						),
						effectiveMaxSelections,
						limitType
					),
				] );
				return;
			}
		} else {
			// Remove platform
			newSelectedPlatforms = selectedPlatforms.filter(
				( p ) => p !== platformValue
			);
		}

		setErrors( [] ); // Clear errors on successful change
		onPlatformsChange( newSelectedPlatforms );
	};

	const renderPlatformItem = ( platform ) => {
		const isSelected = selectedPlatforms.includes( platform.value );
		const effectiveMaxSelections = isGuest ? guestLimit : maxSelections;
		const isDisabled =
			! isSelected &&
			effectiveMaxSelections &&
			selectedPlatforms.length >= effectiveMaxSelections;

		return (
			<div
				key={ platform.value }
				className={ `
					p-3 sm:p-4 rounded-xl text-center relative min-h-[70px] sm:min-h-[80px] 
					flex flex-col items-center justify-center cursor-pointer
					transition-all duration-200 ease-in-out
					bg-gradient-to-br from-gray-50 to-white
					border-2 border-gray-200
					${isSelected 
						? 'border-blue-500 bg-gradient-to-br from-blue-50 to-white shadow-lg shadow-blue-500/15' 
						: 'hover:shadow-lg hover:-translate-y-0.5'
					}
					${isDisabled 
						? 'opacity-60 cursor-not-allowed hover:shadow-none hover:translate-y-0' 
						: ''
					}
				` }
				onClick={ () => !isDisabled && handlePlatformChange( platform.value, !isSelected ) }
				role="button"
				tabIndex={ isDisabled ? -1 : 0 }
				onKeyDown={ ( e ) => {
					if ( ( e.key === 'Enter' || e.key === ' ' ) && !isDisabled ) {
						e.preventDefault();
						handlePlatformChange( platform.value, !isSelected );
					}
				} }
				aria-pressed={ isSelected }
				aria-disabled={ isDisabled }
			>
				<div className={ `text-xl sm:text-2xl mb-1 sm:mb-2 transition-opacity duration-200 ${isSelected ? 'opacity-100' : 'opacity-70'}` }>
					{ platform.icon }
				</div>
				<div className={ `text-xs sm:text-sm font-medium ${isSelected ? 'text-gray-900 font-semibold' : 'text-gray-600'}` }>
					{ platform.label }
				</div>
				
				{ isSelected && (
					<div className="mt-2 pt-2 border-t border-gray-200 w-full">
						<div className="mb-1">
							{ platform.characteristics.map( ( char ) => (
								<span
									key={ char }
									className="inline-block bg-blue-500 text-white text-xs px-1.5 py-0.5 rounded mr-1 mb-1"
								>
									{ char }
								</span>
							) ) }
						</div>
						<div className="text-xs text-gray-500">
							{ sprintf(
								__(
									'Max: %d chars',
									'rwp-creator-suite'
								),
								platform.maxLength
							) }
						</div>
					</div>
				) }

				{ /* Hidden checkbox for form compatibility */ }
				<input
					type="checkbox"
					checked={ isSelected }
					onChange={ () => {} }
					className="hidden"
					data-platform-checkbox={ platform.value }
				/>
			</div>
		);
	};

	const getSelectionSummary = () => {
		const count = selectedPlatforms.length;
		const effectiveMaxSelections = isGuest ? guestLimit : maxSelections;

		if ( count === 0 ) {
			return __( 'No platforms selected', 'rwp-creator-suite' );
		}

		let summary = sprintf(
			_n(
				'%d platform selected',
				'%d platforms selected',
				count,
				'rwp-creator-suite'
			),
			count
		);

		if ( effectiveMaxSelections ) {
			summary += sprintf(
				__( ' (max %d)', 'rwp-creator-suite' ),
				effectiveMaxSelections
			);
		}

		return summary;
	};

	return (
		<div className="border border-gray-300 rounded-lg p-4 my-4">
			<div className="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
				<h4 className="m-0 text-base font-semibold">{ __( 'Select Platforms', 'rwp-creator-suite' ) }</h4>
				<div className="text-sm text-gray-600">
					{ getSelectionSummary() }
				</div>
			</div>

			{ errors.length > 0 && (
				<Notice status="error" isDismissible={ false }>
					<ul>
						{ errors.map( ( error, index ) => (
							<li key={ index }>{ error }</li>
						) ) }
					</ul>
				</Notice>
			) }

			{ isGuest && (
				<Notice status="info" isDismissible={ false }>
					{ sprintf(
						__(
							'Guest users can select up to %d platforms. Sign up for higher limits!',
							'rwp-creator-suite'
						),
						guestLimit
					) }
				</Notice>
			) }

			<div className="grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-3 my-4 sm:grid-cols-[repeat(auto-fit,minmax(140px,1fr))] lg:gap-4">
				{ platforms.map( renderPlatformItem ) }
			</div>

			{ selectedPlatforms.length > 0 && (
				<div className="mt-4 pt-4 border-t border-gray-200">
					<h5 className="m-0 mb-2 text-sm font-medium">
						{ __(
							'Content will be optimized for:',
							'rwp-creator-suite'
						) }
					</h5>
					<div className="flex flex-wrap gap-2">
						{ selectedPlatforms.map( ( platformValue ) => {
							const platform = platforms.find(
								( p ) => p.value === platformValue
							);
							return platform ? (
								<span
									key={ platformValue }
									className="bg-green-500 text-white px-2 py-1 rounded text-xs font-medium"
								>
									{ platform.icon } { platform.label }
								</span>
							) : null;
						} ) }
					</div>
				</div>
			) }

		</div>
	);
};

export default PlatformSelector;
