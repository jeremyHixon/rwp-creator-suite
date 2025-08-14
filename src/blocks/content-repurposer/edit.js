import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Icon } from '@wordpress/components';

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon={ <Icon icon="admin-post" /> }
				label={ __(
					'Content Repurposer',
					'rwp-creator-suite'
				) }
				instructions={ __(
					'This block will display an AI-powered content repurposing interface on the frontend.',
					'rwp-creator-suite'
				) }
			/>
		</div>
	);
}