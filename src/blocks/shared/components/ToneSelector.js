/**
 * Tone Selector Component
 *
 * Reusable React component for selecting content tone and style.
 * Provides consistent tone options across all content generation blocks.
 */

import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import {
	SelectControl,
	RangeControl,
	ToggleControl,
	PanelBody,
} from '@wordpress/components';

const ToneSelector = ( {
	selectedTone = 'professional',
	selectedStyle = 'informative',
	creativity = 50,
	useEmojis = false,
	useHashtags = true,
	onToneChange,
	onStyleChange,
	onCreativityChange,
	onEmojisChange,
	onHashtagsChange,
	allowedTones = null,
	allowedStyles = null,
	showCreativity = true,
	showEmojis = true,
	showHashtags = true,
} ) => {
	// Available tone options
	const toneOptions = {
		professional: {
			label: __( 'Professional', 'rwp-creator-suite' ),
			description: __(
				'Formal, business-appropriate language',
				'rwp-creator-suite'
			),
			keywords: [ 'formal', 'business', 'corporate', 'polished' ],
			emoji: '👔',
		},
		casual: {
			label: __( 'Casual', 'rwp-creator-suite' ),
			description: __(
				'Relaxed, conversational tone',
				'rwp-creator-suite'
			),
			keywords: [
				'relaxed',
				'friendly',
				'conversational',
				'approachable',
			],
			emoji: '😊',
		},
		enthusiastic: {
			label: __( 'Enthusiastic', 'rwp-creator-suite' ),
			description: __(
				'Energetic and exciting language',
				'rwp-creator-suite'
			),
			keywords: [ 'energetic', 'exciting', 'passionate', 'upbeat' ],
			emoji: '🎉',
		},
		authoritative: {
			label: __( 'Authoritative', 'rwp-creator-suite' ),
			description: __(
				'Expert, confident, and trustworthy',
				'rwp-creator-suite'
			),
			keywords: [ 'expert', 'confident', 'trustworthy', 'knowledgeable' ],
			emoji: '🎯',
		},
		friendly: {
			label: __( 'Friendly', 'rwp-creator-suite' ),
			description: __(
				'Warm, welcoming, and personable',
				'rwp-creator-suite'
			),
			keywords: [ 'warm', 'welcoming', 'personable', 'kind' ],
			emoji: '🤝',
		},
		inspirational: {
			label: __( 'Inspirational', 'rwp-creator-suite' ),
			description: __(
				'Motivating and uplifting content',
				'rwp-creator-suite'
			),
			keywords: [ 'motivating', 'uplifting', 'encouraging', 'positive' ],
			emoji: '✨',
		},
		humorous: {
			label: __( 'Humorous', 'rwp-creator-suite' ),
			description: __(
				'Light-hearted and entertaining',
				'rwp-creator-suite'
			),
			keywords: [ 'funny', 'entertaining', 'witty', 'playful' ],
			emoji: '😄',
		},
		educational: {
			label: __( 'Educational', 'rwp-creator-suite' ),
			description: __(
				'Informative and teaching-focused',
				'rwp-creator-suite'
			),
			keywords: [
				'informative',
				'teaching',
				'explaining',
				'instructional',
			],
			emoji: '📚',
		},
	};

	// Available style options
	const styleOptions = {
		informative: {
			label: __( 'Informative', 'rwp-creator-suite' ),
			description: __(
				'Fact-based, educational content',
				'rwp-creator-suite'
			),
			characteristics: [ 'facts', 'data', 'explanations' ],
		},
		storytelling: {
			label: __( 'Storytelling', 'rwp-creator-suite' ),
			description: __(
				'Narrative-driven, engaging stories',
				'rwp-creator-suite'
			),
			characteristics: [ 'narrative', 'engaging', 'personal' ],
		},
		listicle: {
			label: __( 'List-based', 'rwp-creator-suite' ),
			description: __(
				'Organized in lists or bullet points',
				'rwp-creator-suite'
			),
			characteristics: [ 'organized', 'scannable', 'structured' ],
		},
		howto: {
			label: __( 'How-to Guide', 'rwp-creator-suite' ),
			description: __(
				'Step-by-step instructional content',
				'rwp-creator-suite'
			),
			characteristics: [ 'step-by-step', 'instructional', 'actionable' ],
		},
		opinion: {
			label: __( 'Opinion/Editorial', 'rwp-creator-suite' ),
			description: __(
				'Personal perspective and commentary',
				'rwp-creator-suite'
			),
			characteristics: [ 'personal', 'perspective', 'commentary' ],
		},
		promotional: {
			label: __( 'Promotional', 'rwp-creator-suite' ),
			description: __(
				'Marketing-focused, call-to-action driven',
				'rwp-creator-suite'
			),
			characteristics: [ 'marketing', 'persuasive', 'cta' ],
		},
		news: {
			label: __( 'News/Update', 'rwp-creator-suite' ),
			description: __(
				'Timely information and announcements',
				'rwp-creator-suite'
			),
			characteristics: [ 'timely', 'factual', 'announcements' ],
		},
		behind_scenes: {
			label: __( 'Behind the Scenes', 'rwp-creator-suite' ),
			description: __(
				'Personal insights and process sharing',
				'rwp-creator-suite'
			),
			characteristics: [ 'personal', 'process', 'insights' ],
		},
	};

	const getAvailableOptions = ( allOptions, allowedKeys ) => {
		if ( ! allowedKeys ) {
			return allOptions;
		}

		const filtered = {};
		allowedKeys.forEach( ( key ) => {
			if ( allOptions[ key ] ) {
				filtered[ key ] = allOptions[ key ];
			}
		} );
		return filtered;
	};

	const availableTones = getAvailableOptions( toneOptions, allowedTones );
	const availableStyles = getAvailableOptions( styleOptions, allowedStyles );

	const toneSelectOptions = Object.keys( availableTones ).map( ( key ) => ( {
		value: key,
		label: `${ availableTones[ key ].emoji } ${ availableTones[ key ].label }`,
	} ) );

	const styleSelectOptions = Object.keys( availableStyles ).map(
		( key ) => ( {
			value: key,
			label: availableStyles[ key ].label,
		} )
	);

	const getCreativityDescription = ( value ) => {
		if ( value < 25 ) {
			return __( 'Very Conservative', 'rwp-creator-suite' );
		}
		if ( value < 50 ) {
			return __( 'Conservative', 'rwp-creator-suite' );
		}
		if ( value < 75 ) {
			return __( 'Balanced', 'rwp-creator-suite' );
		}
		if ( value < 90 ) {
			return __( 'Creative', 'rwp-creator-suite' );
		}
		return __( 'Very Creative', 'rwp-creator-suite' );
	};

	const getCurrentToneInfo = () => {
		return (
			availableTones[ selectedTone ] ||
			availableTones[ Object.keys( availableTones )[ 0 ] ]
		);
	};

	const getCurrentStyleInfo = () => {
		return (
			availableStyles[ selectedStyle ] ||
			availableStyles[ Object.keys( availableStyles )[ 0 ] ]
		);
	};

	const renderTonePreview = () => {
		const toneInfo = getCurrentToneInfo();
		if ( ! toneInfo ) {
			return null;
		}

		return (
			<div className="tone-preview">
				<div className="tone-info">
					<span className="tone-emoji">{ toneInfo.emoji }</span>
					<div className="tone-details">
						<strong>{ toneInfo.label }</strong>
						<p>{ toneInfo.description }</p>
						<div className="tone-keywords">
							{ toneInfo.keywords.map( ( keyword ) => (
								<span key={ keyword } className="keyword-tag">
									{ keyword }
								</span>
							) ) }
						</div>
					</div>
				</div>
			</div>
		);
	};

	const renderStylePreview = () => {
		const styleInfo = getCurrentStyleInfo();
		if ( ! styleInfo ) {
			return null;
		}

		return (
			<div className="style-preview">
				<div className="style-info">
					<strong>{ styleInfo.label }</strong>
					<p>{ styleInfo.description }</p>
					<div className="style-characteristics">
						{ styleInfo.characteristics.map( ( char ) => (
							<span key={ char } className="characteristic-tag">
								{ char }
							</span>
						) ) }
					</div>
				</div>
			</div>
		);
	};

	return (
		<div className="rwp-tone-selector">
			<PanelBody
				title={ __( 'Content Tone & Style', 'rwp-creator-suite' ) }
				initialOpen={ true }
			>
				{ /* Tone Selection */ }
				<div className="tone-selection">
					<SelectControl
						label={ __( 'Content Tone', 'rwp-creator-suite' ) }
						value={ selectedTone }
						options={ toneSelectOptions }
						onChange={ onToneChange }
						help={ __(
							'Choose the overall tone for your content',
							'rwp-creator-suite'
						) }
					/>
					{ renderTonePreview() }
				</div>

				{ /* Style Selection */ }
				<div className="style-selection">
					<SelectControl
						label={ __( 'Content Style', 'rwp-creator-suite' ) }
						value={ selectedStyle }
						options={ styleSelectOptions }
						onChange={ onStyleChange }
						help={ __(
							'Select the format and approach for your content',
							'rwp-creator-suite'
						) }
					/>
					{ renderStylePreview() }
				</div>

				{ /* Creativity Level */ }
				{ showCreativity && (
					<div className="creativity-control">
						<RangeControl
							label={ __(
								'Creativity Level',
								'rwp-creator-suite'
							) }
							value={ creativity }
							onChange={ onCreativityChange }
							min={ 0 }
							max={ 100 }
							step={ 5 }
							help={ sprintf(
								__(
									'Current level: %s (%d/100)',
									'rwp-creator-suite'
								),
								getCreativityDescription( creativity ),
								creativity
							) }
						/>
						<div className="creativity-scale">
							<span className="scale-label left">
								{ __( 'Conservative', 'rwp-creator-suite' ) }
							</span>
							<span className="scale-label right">
								{ __( 'Creative', 'rwp-creator-suite' ) }
							</span>
						</div>
					</div>
				) }

				{ /* Additional Options */ }
				<div className="additional-options">
					{ showEmojis && (
						<ToggleControl
							label={ __(
								'Include Emojis',
								'rwp-creator-suite'
							) }
							checked={ useEmojis }
							onChange={ onEmojisChange }
							help={ __(
								'Add relevant emojis to enhance engagement',
								'rwp-creator-suite'
							) }
						/>
					) }

					{ showHashtags && (
						<ToggleControl
							label={ __(
								'Include Hashtags',
								'rwp-creator-suite'
							) }
							checked={ useHashtags }
							onChange={ onHashtagsChange }
							help={ __(
								'Generate relevant hashtags for social media',
								'rwp-creator-suite'
							) }
						/>
					) }
				</div>

				{ /* Configuration Summary */ }
				<div className="tone-summary">
					<h4>
						{ __( 'Current Configuration:', 'rwp-creator-suite' ) }
					</h4>
					<div className="summary-items">
						<div className="summary-item">
							<span className="label">
								{ __( 'Tone:', 'rwp-creator-suite' ) }
							</span>
							<span className="value">
								{ getCurrentToneInfo()?.emoji }{ ' ' }
								{ getCurrentToneInfo()?.label }
							</span>
						</div>
						<div className="summary-item">
							<span className="label">
								{ __( 'Style:', 'rwp-creator-suite' ) }
							</span>
							<span className="value">
								{ getCurrentStyleInfo()?.label }
							</span>
						</div>
						{ showCreativity && (
							<div className="summary-item">
								<span className="label">
									{ __( 'Creativity:', 'rwp-creator-suite' ) }
								</span>
								<span className="value">
									{ getCreativityDescription( creativity ) }
								</span>
							</div>
						) }
						<div className="summary-item">
							<span className="label">
								{ __( 'Features:', 'rwp-creator-suite' ) }
							</span>
							<span className="value">
								{ [
									showEmojis &&
										useEmojis &&
										__( 'Emojis', 'rwp-creator-suite' ),
									showHashtags &&
										useHashtags &&
										__( 'Hashtags', 'rwp-creator-suite' ),
								]
									.filter( Boolean )
									.join( ', ' ) ||
									__( 'None', 'rwp-creator-suite' ) }
							</span>
						</div>
					</div>
				</div>
			</PanelBody>

			<style jsx>{ `
				.rwp-tone-selector {
					margin: 16px 0;
				}

				.tone-selection,
				.style-selection {
					margin-bottom: 24px;
				}

				.tone-preview,
				.style-preview {
					background: #f8f9fa;
					border: 1px solid #e9ecef;
					border-radius: 6px;
					padding: 12px;
					margin-top: 8px;
				}

				.tone-info {
					display: flex;
					align-items: flex-start;
					gap: 12px;
				}

				.tone-emoji {
					font-size: 24px;
					line-height: 1;
				}

				.tone-details {
					flex: 1;
				}

				.tone-details strong {
					color: #1e1e1e;
					font-size: 16px;
				}

				.tone-details p {
					margin: 4px 0 8px 0;
					color: #666;
					font-size: 14px;
				}

				.tone-keywords,
				.style-characteristics {
					display: flex;
					flex-wrap: wrap;
					gap: 4px;
				}

				.keyword-tag,
				.characteristic-tag {
					background: #007cba;
					color: white;
					font-size: 11px;
					padding: 2px 6px;
					border-radius: 3px;
				}

				.creativity-control {
					margin: 20px 0;
				}

				.creativity-scale {
					display: flex;
					justify-content: space-between;
					margin-top: -8px;
					margin-bottom: 16px;
				}

				.scale-label {
					font-size: 12px;
					color: #666;
				}

				.additional-options {
					border-top: 1px solid #e0e0e0;
					padding-top: 16px;
					margin-top: 16px;
				}

				.tone-summary {
					background: #e7f3ff;
					border: 1px solid #b3d9ff;
					border-radius: 6px;
					padding: 16px;
					margin-top: 20px;
				}

				.tone-summary h4 {
					margin: 0 0 12px 0;
					color: #1e1e1e;
				}

				.summary-items {
					display: grid;
					gap: 8px;
				}

				.summary-item {
					display: flex;
					justify-content: space-between;
					align-items: center;
				}

				.summary-item .label {
					font-weight: 600;
					color: #333;
				}

				.summary-item .value {
					color: #007cba;
					font-weight: 500;
				}
			` }</style>
		</div>
	);
};

export default ToneSelector;
