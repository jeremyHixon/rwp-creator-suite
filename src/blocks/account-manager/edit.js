import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl, Placeholder } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({
        className: 'rwp-account-manager-block-isolation',
        'data-view-type': attributes.viewType
    });

    const { viewType, showConsentSettings, allowGuestView } = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Account Manager Settings', 'rwp-creator-suite')} initialOpen={true}>
                    <SelectControl
                        label={__('View Type', 'rwp-creator-suite')}
                        value={viewType}
                        options={[
                            { label: __('Dashboard', 'rwp-creator-suite'), value: 'dashboard' },
                            { label: __('Consent Settings', 'rwp-creator-suite'), value: 'consent' },
                            { label: __('Profile Settings', 'rwp-creator-suite'), value: 'profile' }
                        ]}
                        onChange={(value) => setAttributes({ viewType: value })}
                        help={__('Select which view to display by default', 'rwp-creator-suite')}
                    />
                    
                    <ToggleControl
                        label={__('Show Consent Settings', 'rwp-creator-suite')}
                        checked={showConsentSettings}
                        onChange={(value) => setAttributes({ showConsentSettings: value })}
                        help={__('Allow users to manage their consent preferences', 'rwp-creator-suite')}
                    />
                    
                    <ToggleControl
                        label={__('Allow Guest View', 'rwp-creator-suite')}
                        checked={allowGuestView}
                        onChange={(value) => setAttributes({ allowGuestView: value })}
                        help={__('Show registration/login prompt for non-logged-in users', 'rwp-creator-suite')}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <Placeholder
                    icon="admin-users"
                    label={__('Account Manager', 'rwp-creator-suite')}
                    instructions={__('This block will provide subscribers with an interface to manage their account settings and consent preferences.', 'rwp-creator-suite')}
                >
                    <div className="rwp-account-manager-preview">
                        <div className="rwp-account-manager-tabs">
                            {viewType === 'dashboard' && (
                                <div className="tab active">
                                    {__('Dashboard', 'rwp-creator-suite')}
                                </div>
                            )}
                            {showConsentSettings && (
                                <div className={`tab ${viewType === 'consent' ? 'active' : ''}`}>
                                    {__('Consent Settings', 'rwp-creator-suite')}
                                </div>
                            )}
                            <div className={`tab ${viewType === 'profile' ? 'active' : ''}`}>
                                {__('Profile', 'rwp-creator-suite')}
                            </div>
                        </div>
                        <div className="tab-content">
                            {viewType === 'dashboard' && (
                                <p>{__('Account overview and quick actions', 'rwp-creator-suite')}</p>
                            )}
                            {viewType === 'consent' && showConsentSettings && (
                                <p>{__('Advanced analytics consent management', 'rwp-creator-suite')}</p>
                            )}
                            {viewType === 'profile' && (
                                <p>{__('Profile and account settings', 'rwp-creator-suite')}</p>
                            )}
                        </div>
                    </div>
                </Placeholder>
            </div>
        </>
    );
}