import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { 
    Placeholder,
    SelectControl,
    TextareaControl,
    ButtonGroup,
    Button,
    Notice,
    Panel,
    PanelBody,
    PanelRow,
    TabPanel
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {
    const { platform, tone, selectedTemplate, finalCaption, showPreview } = attributes;
    
    const [description, setDescription] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);
    const [generatedCaptions, setGeneratedCaptions] = useState([]);
    const [templates, setTemplates] = useState([]);
    const [characterCount, setCharacterCount] = useState(0);
    const [characterLimit, setCharacterLimit] = useState(2200);
    const [activeTab, setActiveTab] = useState('generator');
    const [error, setError] = useState('');
    
    const blockProps = useBlockProps({
        className: 'caption-writer-editor'
    });
    
    // Character limits for different platforms
    const characterLimits = {
        instagram: 2200,
        tiktok: 2200,
        twitter: 280,
        linkedin: 3000,
        facebook: 63206
    };
    
    // Update character limit when platform changes
    useEffect(() => {
        setCharacterLimit(characterLimits[platform] || 2200);
    }, [platform]);
    
    // Update character count when final caption changes
    useEffect(() => {
        setCharacterCount(finalCaption.length);
    }, [finalCaption]);
    
    // Load built-in templates
    useEffect(() => {
        const builtInTemplates = [
            {
                id: 'product-launch',
                name: 'Product Launch',
                category: 'business',
                template: '🚀 Excited to introduce {product}!\n\n{description}\n\n✨ Key features:\n• {feature1}\n• {feature2}\n• {feature3}\n\nWhat do you think? Drop a 💭 below!\n\n{hashtags}',
                variables: ['product', 'description', 'feature1', 'feature2', 'feature3', 'hashtags'],
                platforms: ['instagram', 'facebook', 'linkedin']
            },
            {
                id: 'behind-scenes',
                name: 'Behind the Scenes',
                category: 'personal',
                template: 'Taking you behind the scenes of {activity} 🎬\n\n{insight}\n\nI never expected {surprise}! \n\nWhat\'s something surprising about your work?\n\n{hashtags}',
                variables: ['activity', 'insight', 'surprise', 'hashtags'],
                platforms: ['instagram', 'tiktok', 'facebook']
            },
            {
                id: 'question-engage',
                name: 'Engagement Question',
                category: 'engagement',
                template: '{question} 🤔\n\nA) {optionA}\nB) {optionB}\nC) {optionC}\n\nVote in the comments! I\'ll share the results in my stories.\n\n{hashtags}',
                variables: ['question', 'optionA', 'optionB', 'optionC', 'hashtags'],
                platforms: ['instagram', 'facebook', 'twitter']
            }
        ];
        setTemplates(builtInTemplates);
    }, []);
    
    const generateCaptions = () => {
        if (!description.trim()) {
            setError(__('Please enter a description for your content', 'rwp-creator-suite'));
            return;
        }
        
        setIsGenerating(true);
        setError('');
        
        // TODO: Implement actual API call in Phase 2
        // For now, show placeholder generated captions
        setTimeout(() => {
            const mockCaptions = [
                {
                    text: `Check out this amazing ${description}! ✨ Perfect for your feed. What do you think? {hashtags}`,
                    character_count: 85
                },
                {
                    text: `Loving this ${description} moment! 💫 Sometimes the simple things bring the most joy. Share yours below! {hashtags}`,
                    character_count: 125
                },
                {
                    text: `${description} vibes hitting different today 🔥 Who else is feeling this energy? {hashtags}`,
                    character_count: 95
                }
            ];
            
            setGeneratedCaptions(mockCaptions);
            setIsGenerating(false);
        }, 1500);
    };
    
    const selectCaption = (captionText) => {
        setAttributes({ finalCaption: captionText });
    };
    
    const selectTemplate = (template) => {
        setAttributes({ 
            selectedTemplate: template.id,
            finalCaption: template.template 
        });
    };
    
    const getCharacterCountColor = () => {
        if (characterCount > characterLimit) return '#d63638';
        if (characterCount > characterLimit * 0.9) return '#dba617';
        return '#1e1e1e';
    };
    
    const tabs = [
        {
            name: 'generator',
            title: __('AI Generator', 'rwp-creator-suite'),
            className: 'tab-generator'
        },
        {
            name: 'templates',
            title: __('Templates', 'rwp-creator-suite'),
            className: 'tab-templates'
        }
    ];
    
    return (
        <>
            <InspectorControls>
                <Panel>
                    <PanelBody title={__('Settings', 'rwp-creator-suite')}>
                        <PanelRow>
                            <SelectControl
                                label={__('Platform', 'rwp-creator-suite')}
                                value={platform}
                                options={[
                                    { label: 'Instagram', value: 'instagram' },
                                    { label: 'TikTok/Reels', value: 'tiktok' },
                                    { label: 'Twitter/X', value: 'twitter' },
                                    { label: 'LinkedIn', value: 'linkedin' },
                                    { label: 'Facebook', value: 'facebook' }
                                ]}
                                onChange={(platform) => setAttributes({ platform })}
                            />
                        </PanelRow>
                        <PanelRow>
                            <SelectControl
                                label={__('Tone', 'rwp-creator-suite')}
                                value={tone}
                                options={[
                                    { label: 'Casual', value: 'casual' },
                                    { label: 'Witty', value: 'witty' },
                                    { label: 'Inspirational', value: 'inspirational' },
                                    { label: 'Question-based', value: 'question' },
                                    { label: 'Professional', value: 'professional' }
                                ]}
                                onChange={(tone) => setAttributes({ tone })}
                            />
                        </PanelRow>
                    </PanelBody>
                </Panel>
            </InspectorControls>
            
            <div {...blockProps}>
                <Placeholder
                    icon="edit-page"
                    label={__('Caption Writer & Template Generator', 'rwp-creator-suite')}
                    instructions={__('Create engaging captions with AI or choose from templates.', 'rwp-creator-suite')}
                >
                    <div className="caption-writer-container">
                        {error && (
                            <Notice status="error" isDismissible={false}>
                                {error}
                            </Notice>
                        )}
                        
                        <TabPanel
                            className="caption-writer-tabs"
                            activeClass="is-active"
                            tabs={tabs}
                        >
                            {(tab) => (
                                <div className={`tab-content tab-${tab.name}`}>
                                    {tab.name === 'generator' && (
                                        <div className="ai-generator">
                                            <TextareaControl
                                                label={__('Describe your content', 'rwp-creator-suite')}
                                                help={__('e.g., "Photo of a golden retriever in a field of flowers"', 'rwp-creator-suite')}
                                                value={description}
                                                onChange={setDescription}
                                                placeholder={__('Describe what your content is about...', 'rwp-creator-suite')}
                                                rows={3}
                                            />
                                            
                                            <Button
                                                isPrimary
                                                onClick={generateCaptions}
                                                disabled={!description.trim() || isGenerating}
                                                isBusy={isGenerating}
                                                className="generate-button"
                                            >
                                                {isGenerating ? 
                                                    __('Generating...', 'rwp-creator-suite') : 
                                                    __('Generate Captions', 'rwp-creator-suite')
                                                }
                                            </Button>
                                            
                                            {generatedCaptions.length > 0 && (
                                                <div className="generated-captions">
                                                    <h4>{__('Generated Captions', 'rwp-creator-suite')}</h4>
                                                    {generatedCaptions.map((caption, index) => (
                                                        <div key={index} className="caption-option">
                                                            <p>{caption.text}</p>
                                                            <div className="caption-meta">
                                                                <span className="character-count">
                                                                    {caption.character_count} chars
                                                                </span>
                                                                <Button
                                                                    isSecondary
                                                                    isSmall
                                                                    onClick={() => selectCaption(caption.text)}
                                                                >
                                                                    {__('Use This', 'rwp-creator-suite')}
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                    
                                    {tab.name === 'templates' && (
                                        <div className="template-library">
                                            <h4>{__('Choose a Template', 'rwp-creator-suite')}</h4>
                                            <div className="templates-grid">
                                                {templates.map((template) => (
                                                    <div key={template.id} className="template-card">
                                                        <h5>{template.name}</h5>
                                                        <p className="template-category">
                                                            {template.category}
                                                        </p>
                                                        <div className="template-preview">
                                                            {template.template.substring(0, 100)}...
                                                        </div>
                                                        <Button
                                                            isSecondary
                                                            onClick={() => selectTemplate(template)}
                                                            className="select-template-btn"
                                                        >
                                                            {__('Use Template', 'rwp-creator-suite')}
                                                        </Button>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </TabPanel>
                        
                        {finalCaption && (
                            <div className="caption-output">
                                <label>{__('Final Caption', 'rwp-creator-suite')}</label>
                                <TextareaControl
                                    value={finalCaption}
                                    onChange={(value) => setAttributes({ finalCaption: value })}
                                    rows={8}
                                    className="final-caption-textarea"
                                />
                                <div className="character-counter">
                                    <span 
                                        className="character-count"
                                        style={{ color: getCharacterCountColor() }}
                                    >
                                        {characterCount}
                                    </span>
                                    <span className="character-limit">
                                        / {characterLimit} ({platform})
                                    </span>
                                </div>
                            </div>
                        )}
                    </div>
                </Placeholder>
            </div>
        </>
    );
}