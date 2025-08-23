# WordPress Block Development (AI-Optimized)

## Structure
```
src/
├── blocks/
│   └── app-container/
│       ├── block.json
│       ├── index.js
│       ├── edit.js
│       ├── save.js
│       ├── style.scss
│       └── editor.scss
└── index.js
```

## Block as App Container Pattern

### Container Block Registration
```json
// block.json - App container block
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "plugin-name/app-container",
    "title": "Plugin Name App",
    "category": "widgets",
    "icon": "admin-tools",
    "textdomain": "plugin-name",
    "editorScript": "file:./index.js",
    "style": "file:./style-index.css",
    "attributes": {
        "appType": {
            "type": "string",
            "default": "dashboard"
        },
        "appConfig": {
            "type": "object",
            "default": {}
        }
    }
}
```

### Container Edit Component
```javascript
// edit.js - Placeholder only for editor
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, SelectControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({
        className: 'rwp-block-isolation'
    });
    
    return (
        <div {...blockProps}>
            <Placeholder
                icon="admin-tools"
                label="RWP Creator Suite App"
                instructions="This will load your app on the frontend."
                className="rwp-placeholder"
            >
                <SelectControl
                    label="App Type"
                    value={attributes.appType}
                    options={[
                        { label: 'Caption Writer', value: 'caption-writer' },
                        { label: 'Content Repurposer', value: 'content-repurposer' },
                        { label: 'Account Manager', value: 'account-manager' }
                    ]}
                    onChange={(appType) => setAttributes({ appType })}
                    className="rwp-app-selector"
                />
            </Placeholder>
        </div>
    );
}
```

### Container Save Component
```javascript
// save.js - Empty container for JS app mounting
import { useBlockProps } from '@wordpress/block-editor';

export default function save({ attributes }) {
    const blockProps = useBlockProps.save({
        'data-app-type': attributes.appType,
        'data-config': JSON.stringify(attributes.appConfig)
    });
    
    return <div {...blockProps}></div>;
}
```

### Block Variations for Different Apps
```javascript
// Add to index.js after registerBlockType
import { registerBlockType } from '@wordpress/blocks';
import { registerBlockVariation } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import metadata from './block.json';

registerBlockType( metadata.name, {
    edit: Edit,
    save,
} );

registerBlockVariation('rwp-creator-suite/app-container', {
    name: 'caption-writer',
    title: 'Caption Writer',
    attributes: { appType: 'caption-writer' },
    icon: 'editor-alignleft',
    scope: ['inserter'],
    keywords: ['caption', 'social', 'content']
});

registerBlockVariation('rwp-creator-suite/app-container', {
    name: 'content-repurposer',
    title: 'Content Repurposer', 
    attributes: { appType: 'content-repurposer' },
    icon: 'update',
    scope: ['inserter'],
    keywords: ['repurpose', 'convert', 'content']
});

registerBlockVariation('rwp-creator-suite/app-container', {
    name: 'account-manager',
    title: 'Account Manager',
    attributes: { appType: 'account-manager' },
    icon: 'admin-users',
    scope: ['inserter'],
    keywords: ['account', 'profile', 'manage']
});
```

## package.json
```json
{
    "scripts": {
        "build": "wp-scripts build",
        "start": "wp-scripts start"
    },
    "devDependencies": {
        "@wordpress/scripts": "^27.0.0"
    }
}
```

## PHP Registration
```php
function plugin_name_register_blocks() {
    register_block_type( __DIR__ . '/build/blocks/app-container', array(
        'render_callback' => 'plugin_name_render_app_container',
    ) );
}
add_action( 'init', 'plugin_name_register_blocks' );

function plugin_name_render_app_container( $attributes, $content ) {
    $wrapper_attributes = get_block_wrapper_attributes( array(
        'id' => 'plugin-name-app-' . wp_unique_id(),
        'data-app-type' => $attributes['appType'] ?? 'dashboard',
        'data-config' => wp_json_encode( $attributes ),
    ) );
    
    return sprintf( '<div %s></div>', $wrapper_attributes );
}
```

## index.js
```javascript
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import metadata from './block.json';

registerBlockType( metadata.name, {
    edit: Edit,
    save,
} );
```

## edit.js
```javascript
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, BlockControls, AlignmentToolbar, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
    const { content, alignment } = attributes;
    const blockProps = useBlockProps();

    return (
        <>
            <BlockControls>
                <AlignmentToolbar
                    value={ alignment }
                    onChange={ ( alignment ) => setAttributes( { alignment } ) }
                />
            </BlockControls>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', 'plugin-name' ) }>
                    <TextControl
                        label={ __( 'Title', 'plugin-name' ) }
                        value={ title }
                        onChange={ ( title ) => setAttributes( { title } ) }
                    />
                </PanelBody>
            </InspectorControls>
            <div { ...blockProps }>
                <RichText
                    tagName="p"
                    value={ content }
                    onChange={ ( content ) => setAttributes( { content } ) }
                    placeholder={ __( 'Enter text...', 'plugin-name' ) }
                />
            </div>
        </>
    );
}
```

## save.js
```javascript
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
    const { content, alignment } = attributes;
    const blockProps = useBlockProps.save();

    return (
        <div { ...blockProps }>
            <RichText.Content tagName="p" value={ content } />
        </div>
    );
}
```

## Common Patterns

### Media Upload
```javascript
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';

<MediaUploadCheck>
    <MediaUpload
        onSelect={ ( media ) => setAttributes( { mediaId: media.id, mediaUrl: media.url } ) }
        allowedTypes={ ['image'] }
        value={ mediaId }
        render={ ( { open } ) => (
            <Button onClick={ open }>Upload</Button>
        ) }
    />
</MediaUploadCheck>
```

### Inner Blocks
```javascript
// Edit
import { InnerBlocks } from '@wordpress/block-editor';
const ALLOWED_BLOCKS = [ 'core/paragraph', 'core/heading' ];

<InnerBlocks
    allowedBlocks={ ALLOWED_BLOCKS }
    template={ [
        [ 'core/heading', { placeholder: 'Title' } ],
        [ 'core/paragraph', { placeholder: 'Content' } ],
    ] }
/>

// Save
<InnerBlocks.Content />
```

### Dynamic Block
```json
// block.json
{ "render": "file:./render.php" }
```

```php
// render.php
<div <?php echo get_block_wrapper_attributes(); ?>>
    <?php echo esc_html( $attributes['content'] ); ?>
</div>
```

### Block Variations
```javascript
import { registerBlockVariation } from '@wordpress/blocks';

registerBlockVariation( 'core/group', {
    name: 'plugin-name-card',
    title: 'Card',
    attributes: { className: 'is-style-card' },
    innerBlocks: [
        [ 'core/heading' ],
        [ 'core/paragraph' ]
    ],
} );
```

## Frontend App Mounting

### App Loader
```javascript
// frontend.js - Mount apps into block containers
class PluginNameAppLoader {
    constructor() {
        this.apps = new Map();
        this.init();
    }
    
    init() {
        const containers = document.querySelectorAll('[id^="plugin-name-app-"]');
        containers.forEach(container => this.mountApp(container));
    }
    
    async mountApp(container) {
        const appType = container.dataset.appType;
        const config = JSON.parse(container.dataset.config || '{}');
        
        try {
            const AppClass = await this.loadAppModule(appType);
            const app = new AppClass(container, config);
            this.apps.set(container.id, app);
        } catch (error) {
            console.error(`Failed to load app ${appType}:`, error);
            this.renderFallback(container, appType);
        }
    }
    
    async loadAppModule(appType) {
        const appModules = {
            'dashboard': () => import('@modules/dashboard-app'),
            'user-profile': () => import('@modules/user-profile-app'),
            'data-viewer': () => import('@modules/data-viewer-app'),
        };
        
        if (!appModules[appType]) {
            throw new Error(`Unknown app type: ${appType}`);
        }
        
        const module = await appModules[appType]();
        return module.default;
    }
    
    renderFallback(container, appType) {
        container.innerHTML = `
            <div class="plugin-name-error">
                <p>Unable to load ${appType} app. Please refresh the page.</p>
            </div>
        `;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new PluginNameAppLoader();
});
```

## Imports Only
```javascript
// Core
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InnerBlocks, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl, Button } from '@wordpress/components';
import { useState, useEffect, useMemo } from '@wordpress/element';
```

## Critical Rules
1. Use `@wordpress/scripts` only
2. NO custom webpack configs (unless absolutely necessary)
3. NO external UI libraries IN BLOCKS - use for JS apps instead
4. Use block.json for ALL blocks
5. Use WordPress components for BLOCK EDITOR only
6. Store block data in attributes only
7. NO localStorage/sessionStorage IN BLOCKS
8. NO direct DOM manipulation IN BLOCKS
9. **Blocks are containers only** - complex apps mount into them
10. **Use Placeholder components** for editor preview
11. **RWP Class Naming** - Use `rwp-` prefix for components, `blk-` for Tailwind utilities
12. **Nest All Admin Pages** - All admin option pages MUST use `add_submenu_page()` with parent slug `'rwp-creator-tools'` - never create additional top-level menus