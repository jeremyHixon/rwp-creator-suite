import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Placeholder, Icon, PanelBody, SelectControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
	const blockProps = useBlockProps();
	const { defaultView } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Settings', 'rwp-creator-suite')}>
					<SelectControl
						label={__('Default View', 'rwp-creator-suite')}
						value={defaultView}
						options={[
							{ label: __('Dashboard', 'rwp-creator-suite'), value: 'dashboard' },
							{ label: __('Search', 'rwp-creator-suite'), value: 'search' }
						]}
						onChange={(value) => setAttributes({ defaultView: value })}
						help={__('Choose the default view when the block loads.', 'rwp-creator-suite')}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<Placeholder
					icon={<Icon icon="chart-bar" />}
					label={__('Hashtag Analysis Dashboard', 'rwp-creator-suite')}
					instructions={__(
						'This block will display a hashtag analysis dashboard on the frontend with real-time social media metrics from TikTok, Instagram, and Facebook.',
						'rwp-creator-suite'
					)}
				>
					<div style={{ marginTop: '16px', fontSize: '14px', color: '#666' }}>
						<strong>{__('Default View:', 'rwp-creator-suite')} </strong>
						{defaultView === 'dashboard' ? __('Dashboard', 'rwp-creator-suite') : __('Search', 'rwp-creator-suite')}
					</div>
				</Placeholder>
			</div>
		</>
	);
}