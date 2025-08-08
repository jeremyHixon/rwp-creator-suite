import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Icon } from '@wordpress/components';

export default function Edit() {
    const blockProps = useBlockProps();

    return (
        <div {...blockProps}>
            <Placeholder
                icon={<Icon icon="chart-bar" />}
                label={__('Instagram Follower Analyzer', 'rwp-creator-suite')}
                instructions={__(
                    'This block will display an Instagram follower analysis interface on the frontend.',
                    'rwp-creator-suite'
                )}
            />
        </div>
    );
}