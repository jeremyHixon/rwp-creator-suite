import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Icon } from '@wordpress/components';

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon={ <Icon icon="format-gallery" /> }
				label={ __( 'Instagram Banner Creator', 'rwp-creator-suite' ) }
				instructions={ __(
					'This block will display an Instagram banner creation interface on the frontend where users can upload and split images.',
					'rwp-creator-suite'
				) }
			/>
		</div>
	);
}
