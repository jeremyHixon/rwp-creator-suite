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
    TabPanel,
    CheckboxControl
} from '@wordpress/components';
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {
    const { platforms = ['instagram'], tone, selectedTemplate, finalCaption, showPreview } = attributes;
    
    const [description, setDescription] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);
    const [generatedCaptions, setGeneratedCaptions] = useState([]);
    const [templates, setTemplates] = useState([]);
    const [characterCount, setCharacterCount] = useState(0);
    const [characterLimits, setCharacterLimits] = useState({ instagram: 2200 });
    const [activeTab, setActiveTab] = useState('generator');
    const [error, setError] = useState('');
    const [hasError, setHasError] = useState(false);
    const [isRetrying, setIsRetrying] = useState(false);
    const [retryCount, setRetryCount] = useState(0);
    
    const blockProps = useBlockProps({
        className: 'caption-writer-editor'
    });
    
    // Character limits for different platforms - cached for performance
    const platformLimits = useMemo(() => ({
        instagram: 2200,
        tiktok: 2200,
        twitter: 280,
        linkedin: 3000,
        facebook: 63206
    }), []);
    
    // Update character limits when platforms change
    useEffect(() => {
        const limits = {};
        platforms.forEach(platform => {
            limits[platform] = platformLimits[platform] || 2200;
        });
        setCharacterLimits(limits);
    }, [platforms]);
    
    // Update character count when final caption changes
    useEffect(() => {
        setCharacterCount(finalCaption.length);
    }, [finalCaption]);
    
    // Sync local state with block attributes on mount
    useEffect(() => {
        if (finalCaption && finalCaption !== '') {
            // Initialize any local state that depends on attributes
        }
    }, []);
    
    // Update attributes when local state changes
    useEffect(() => {
        // Debounce attribute updates to avoid excessive re-renders
        const timeoutId = setTimeout(() => {
            if (description !== '') {
                // Could save description to attributes if needed for persistence
            }
        }, 500);
        
        return () => clearTimeout(timeoutId);
    }, [description]);
    
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
    
    // Comprehensive input validation function
    const validateCaptionInput = useCallback((desc, selectedTone, selectedPlatforms) => {
        const errors = [];
        
        // Description validation
        if (!desc || desc.trim().length === 0) {
            errors.push(__('Please enter a description for your content', 'rwp-creator-suite'));
        } else if (desc.trim().length < 10) {
            errors.push(__('Description must be at least 10 characters long', 'rwp-creator-suite'));
        } else if (desc.trim().length > 500) {
            errors.push(__('Description must be less than 500 characters', 'rwp-creator-suite'));
        }
        
        // Platform validation
        if (!selectedPlatforms || selectedPlatforms.length === 0) {
            errors.push(__('Please select at least one platform', 'rwp-creator-suite'));
        }
        
        // Tone validation
        const validTones = ['casual', 'witty', 'inspirational', 'question', 'professional'];
        if (!selectedTone || !validTones.includes(selectedTone)) {
            errors.push(__('Please select a valid tone', 'rwp-creator-suite'));
        }
        
        return {
            isValid: errors.length === 0,
            message: errors.join('. ')
        };
    }, []);
    
    const generateCaptions = async () => {
        
        // Validate input comprehensively
        const validation = validateCaptionInput(description, tone, platforms);
        if (!validation.isValid) {
            setError(validation.message);
            setHasError(true);
            return;
        }
        
        // Check for required dependencies with fallback
        if (typeof rwpCaptionWriter === 'undefined') {
            console.warn('Caption writer dependencies not loaded, attempting fallback');
            setError(__('Caption writer service temporarily unavailable. Please try again.', 'rwp-creator-suite'));
            setHasError(true);
            return;
        }
        
        setIsGenerating(true);
        setError('');
        setHasError(false);
        
        try {
            // Sanitize inputs before API call
            const sanitizedData = {
                description: description.trim(),
                tone: tone,
                platforms: platforms.filter(p => ['instagram', 'tiktok', 'twitter', 'linkedin', 'facebook'].includes(p))
            };
            
            // Make API call to generate captions
            const response = await fetch(rwpCaptionWriter.restUrl + 'captions/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': rwpCaptionWriter.nonce
                },
                body: JSON.stringify(sanitizedData)
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP ${response.status}: Failed to generate captions`);
            }
            
            const result = await response.json();
            
            if (result.success && result.data && Array.isArray(result.data)) {
                setGeneratedCaptions(result.data);
                setHasError(false);
            } else {
                throw new Error(result.message || 'Invalid response from server');
            }
        } catch (error) {
            console.error('Error generating captions:', error);
            
            // Implement automatic retry for network errors
            if (shouldRetry(error) && !isRetrying) {
                await retryApiCall(error);
                return;
            }
            
            // Provide user-friendly error messages
            let userMessage = __('Failed to generate captions. Please try again.', 'rwp-creator-suite');
            
            if (error.message.includes('NetworkError') || error.message.includes('fetch')) {
                userMessage = __('Network error. Please check your connection and try again.', 'rwp-creator-suite');
            } else if (error.message.includes('401')) {
                userMessage = __('Authentication error. Please refresh the page and try again.', 'rwp-creator-suite');
            } else if (error.message.includes('429')) {
                userMessage = __('Rate limit exceeded. Please wait a moment and try again.', 'rwp-creator-suite');
            } else if (error.message.includes('500')) {
                userMessage = __('Server error. Please try again later.', 'rwp-creator-suite');
            }
            
            setError(userMessage);
            setHasError(true);
        } finally {
            setIsGenerating(false);
        }
    };
    
    const selectCaption = (captionText) => {
        // Update both local state and block attributes synchronously
        setAttributes({ 
            finalCaption: captionText,
            showPreview: true 
        });
        
        // Ensure character count is updated immediately
        setCharacterCount(captionText.length);
    };
    
    const selectTemplate = (template) => {
        setAttributes({ 
            selectedTemplate: template.id,
            finalCaption: template.template 
        });
    };
    
    const getCharacterCountColor = useCallback((limit) => {
        if (characterCount > limit) return '#d63638';
        if (characterCount > limit * 0.9) return '#dba617';
        return '#1e1e1e';
    }, [characterCount]);
    
    // Memoize platform limit calculations for performance
    const currentPlatformLimits = useMemo(() => {
        const limits = {};
        platforms.forEach(platform => {
            limits[platform] = platformLimits[platform] || 2200;
        });
        return limits;
    }, [platforms, platformLimits]);
    
    // Memoize minimum limit calculation
    const minPlatformLimit = useMemo(() => {
        return Math.min(...platforms.map(p => currentPlatformLimits[p]));
    }, [platforms, currentPlatformLimits]);
    
    const togglePlatform = (platform) => {
        const updatedPlatforms = platforms.includes(platform)
            ? platforms.filter(p => p !== platform)
            : [...platforms, platform];
        
        // Ensure at least one platform is always selected
        if (updatedPlatforms.length === 0) return;
        
        setAttributes({ platforms: updatedPlatforms });
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
                            <div>
                                <label style={{fontWeight: '600', marginBottom: '8px', display: 'block'}}>
                                    {__('Target Platforms', 'rwp-creator-suite')}
                                </label>
                                {[
                                    { label: 'Instagram', value: 'instagram' },
                                    { label: 'TikTok/Reels', value: 'tiktok' },
                                    { label: 'Twitter/X', value: 'twitter' },
                                    { label: 'LinkedIn', value: 'linkedin' },
                                    { label: 'Facebook', value: 'facebook' }
                                ].map(platform => (
                                    <CheckboxControl
                                        key={platform.value}
                                        label={platform.label}
                                        checked={platforms.includes(platform.value)}
                                        onChange={() => togglePlatform(platform.value)}
                                        style={{marginBottom: '4px'}}
                                    />
                                ))}
                            </div>
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
            
            {hasError && (
                <Notice status="error" isDismissible={true} onRemove={() => setHasError(false)}>
                    <strong>{__('Caption Writer Error', 'rwp-creator-suite')}</strong>
                    <p>{error}</p>
                    <Button 
                        isSecondary 
                        isSmall
                        onClick={() => {
                            setHasError(false);
                            setError('');
                        }}
                    >
                        {__('Try Again', 'rwp-creator-suite')}
                    </Button>
                </Notice>
            )}
            
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
                                                disabled={!description.trim() || isGenerating || isRetrying}
                                                isBusy={isGenerating || isRetrying}
                                                className="generate-button"
                                            >
                                                {isGenerating ? 
                                                    __('Generating...', 'rwp-creator-suite') : 
                                                    isRetrying ? 
                                                    __('Retrying...', 'rwp-creator-suite') + ` (${retryCount}/3)` :
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
                                    <div className="current-count" style={{ 
                                        color: getCharacterCountColor(minPlatformLimit),
                                        backgroundColor: characterCount > minPlatformLimit ? '#fef2f2' : 
                                                        characterCount > minPlatformLimit * 0.9 ? '#fffbeb' : '',
                                        borderColor: characterCount > minPlatformLimit ? '#fecaca' : 
                                                    characterCount > minPlatformLimit * 0.9 ? '#fed7aa' : ''
                                    }}>
                                        {characterCount}
                                    </div>
                                    <div className="platform-limits">
                                        {platforms.map(platform => {
                                            const limit = currentPlatformLimits[platform];
                                            return (
                                                <div 
                                                    key={platform} 
                                                    className="platform-limit-item"
                                                    data-over-limit={characterCount > limit ? 'true' : undefined}
                                                >
                                                    <span className="character-limit">
                                                        {limit}
                                                    </span>
                                                    <span className="platform-name">
                                                        {platform.charAt(0).toUpperCase() + platform.slice(1)}
                                                    </span>
                                                    {characterCount > limit && (
                                                        <span className="over-limit-badge">Over limit!</span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </Placeholder>
            </div>
        </>
    );
}