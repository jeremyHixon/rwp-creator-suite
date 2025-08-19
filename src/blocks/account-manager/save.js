import { useBlockProps } from '@wordpress/block-editor';

export default function save({ attributes }) {
    const blockProps = useBlockProps.save({
        'data-view-type': attributes.viewType,
        'data-show-consent': attributes.showConsentSettings ? '1' : '0',
        'data-allow-guest': attributes.allowGuestView ? '1' : '0',
        'data-config': JSON.stringify(attributes)
    });
    
    return <div {...blockProps}></div>;
}