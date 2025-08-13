/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { 
	useBlockProps,
	InspectorControls,
	BlockControls,
	AlignmentToolbar 
} from '@wordpress/block-editor';
import { 
	PanelBody, 
	ToggleControl,
	SelectControl,
	CheckboxControl,
	Card,
	CardBody,
	CardHeader
} from '@wordpress/components';

/**
 * Edit component for Content Repurposer block.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { platforms, tone, showUsageStats, align } = attributes;

	const blockProps = useBlockProps( {
		className: `align${ align || 'none' }`,
	} );

	const platformOptions = [
		{ label: __( 'Twitter', 'rwp-creator-suite' ), value: 'twitter' },
		{ label: __( 'LinkedIn', 'rwp-creator-suite' ), value: 'linkedin' },
		{ label: __( 'Facebook', 'rwp-creator-suite' ), value: 'facebook' },
		{ label: __( 'Instagram', 'rwp-creator-suite' ), value: 'instagram' },
	];

	const toneOptions = [
		{ label: __( 'Professional', 'rwp-creator-suite' ), value: 'professional' },
		{ label: __( 'Casual', 'rwp-creator-suite' ), value: 'casual' },
		{ label: __( 'Engaging', 'rwp-creator-suite' ), value: 'engaging' },
		{ label: __( 'Informative', 'rwp-creator-suite' ), value: 'informative' },
	];

	const onPlatformChange = ( platform ) => {
		const updatedPlatforms = platforms.includes( platform )
			? platforms.filter( p => p !== platform )
			: [ ...platforms, platform ];
		setAttributes( { platforms: updatedPlatforms } );
	};

	return (
		<>
			<BlockControls>
				<AlignmentToolbar
					value={ align }
					onChange={ ( newAlign ) => setAttributes( { align: newAlign } ) }
				/>
			</BlockControls>

			<InspectorControls>
				<PanelBody 
					title={ __( 'Content Repurposer Settings', 'rwp-creator-suite' ) }
					initialOpen={ true }
				>
					<fieldset>
						<legend className="blocks-base-control__label">
							{ __( 'Target Platforms', 'rwp-creator-suite' ) }
						</legend>
						{ platformOptions.map( ( option ) => (
							<CheckboxControl
								key={ option.value }
								label={ option.label }
								checked={ platforms.includes( option.value ) }
								onChange={ () => onPlatformChange( option.value ) }
							/>
						) ) }
					</fieldset>

					<SelectControl
						label={ __( 'Tone', 'rwp-creator-suite' ) }
						value={ tone }
						options={ toneOptions }
						onChange={ ( newTone ) => setAttributes( { tone: newTone } ) }
						help={ __( 'Choose the tone for repurposed content', 'rwp-creator-suite' ) }
					/>

					<ToggleControl
						label={ __( 'Show Usage Statistics', 'rwp-creator-suite' ) }
						checked={ showUsageStats }
						onChange={ ( newShowUsageStats ) => 
							setAttributes( { showUsageStats: newShowUsageStats } ) 
						}
						help={ __( 'Display usage stats and rate limits', 'rwp-creator-suite' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<Card>
					<CardHeader>
						<h3>{ __( 'Content Repurposer', 'rwp-creator-suite' ) }</h3>
					</CardHeader>
					<CardBody>
						<p>
							{ __( 'Transform your long-form content into engaging social media posts.', 'rwp-creator-suite' ) }
						</p>
						
						<div className="rwp-content-repurposer-preview">
							<div className="rwp-preview-settings">
								<strong>{ __( 'Selected Platforms:', 'rwp-creator-suite' ) }</strong>
								{ platforms.length > 0 ? (
									<ul>
										{ platforms.map( platform => (
											<li key={ platform }>
												{ platformOptions.find( opt => opt.value === platform )?.label }
											</li>
										) ) }
									</ul>
								) : (
									<p><em>{ __( 'No platforms selected', 'rwp-creator-suite' ) }</em></p>
								) }
								
								<strong>{ __( 'Tone:', 'rwp-creator-suite' ) }</strong>
								<span> { toneOptions.find( opt => opt.value === tone )?.label }</span>
							</div>
						</div>

						<p className="description">
							{ __( 'The actual repurposer interface will appear on the frontend.', 'rwp-creator-suite' ) }
						</p>
					</CardBody>
				</Card>
			</div>
		</>
	);
}