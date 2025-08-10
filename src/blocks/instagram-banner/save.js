import { useBlockProps } from '@wordpress/block-editor';

export default function save() {
	const blockProps = useBlockProps.save();

	return (
		<div { ...blockProps }>
			<div
				className="blk-instagram-banner-container"
				id="instagram-banner-app"
			></div>
		</div>
	);
}
