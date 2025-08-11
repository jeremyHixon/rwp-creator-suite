import { useBlockProps } from '@wordpress/block-editor';

export default function save({ attributes }) {
	const blockProps = useBlockProps.save();
	const { defaultView } = attributes;

	return (
		<div {...blockProps}>
			<div
				className="blk-hashtag-analysis-container"
				id="hashtag-analysis-app"
				data-default-view={defaultView}
			></div>
		</div>
	);
}