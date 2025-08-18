# WordPress Block Style Isolation (AI-Optimized)

## Core Principles
- **Complete Style Isolation**: Block styles don't leak to theme, theme styles don't interfere with blocks
- **Tailwind/DaisyUI Scoped**: Utility classes and components only apply within block boundaries
- **Light Theme Only**: DaisyUI "light" theme exclusively for consistency
- **Minimalistic Design**: Clean, simple aesthetics with purposeful styling
- **Editor/Frontend Consistency**: Styles work identically in Gutenberg editor and frontend
- **Performance Optimized**: Minimal CSS footprint, no unused styles

## Build System Setup

### Enhanced package.json
```json
{
  "scripts": {
    "build": "wp-scripts build --webpack-src-dir=src --output-path=build",
    "start": "wp-scripts start --webpack-src-dir=src --output-path=build",
    "build:tailwind": "tailwindcss -i ./src/styles/tailwind.css -o ./build/blocks.css --watch",
    "build:blocks": "npm run build:tailwind && npm run build"
  },
  "devDependencies": {
    "@wordpress/scripts": "^27.0.0",
    "tailwindcss": "^3.4.0",
    "daisyui": "^4.0.0",
    "@tailwindcss/container-queries": "^0.1.1"
  }
}
```

### Tailwind Configuration (Block-Scoped)
```javascript
// tailwind.config.js
module.exports = {
  content: [
    "./src/blocks/**/*.{js,jsx,ts,tsx}",
    "./src/blocks/**/*.php"
  ],
  // Prefix all utilities to prevent conflicts
  prefix: 'blk-',
  // Disable base styles to prevent theme interference
  corePlugins: {
    preflight: false,
    container: false
  },
  theme: {
    extend: {
      // Custom properties for isolation
      spacing: {
        'blk': '1rem',
      }
    }
  },
  plugins: [
    require('daisyui'),
    require('@tailwindcss/container-queries')
  ],
  daisyui: {
    // Prefix DaisyUI classes
    prefix: "dui-",
    // Disable base styles
    base: false,
    // Only light theme for consistency and minimalism
    themes: ["light"],
    // Disable color utilities (use scoped versions)
    utils: false,
    // Reduce component variations for minimalistic approach
    styled: true,
    logs: false
  }
}
```

### Block-Specific CSS Entry
```css
/* src/styles/tailwind.css */
@tailwind base;
@tailwind components; 
@tailwind utilities;

/* Scope all styles to block containers */
.wp-block-plugin-name-block {
  /* Reset any theme inheritance */
  all: initial;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  
  /* Re-enable inheritance for children */
  * {
    all: unset;
    display: revert;
    box-sizing: border-box;
  }
  
  /* Ensure block boundary */
  container-type: inline-size;
  isolation: isolate;
}

/* Scope DaisyUI components */
.wp-block-plugin-name-block .dui-btn,
.wp-block-plugin-name-block .dui-card,
.wp-block-plugin-name-block .dui-input {
  /* DaisyUI styles only apply within blocks */
}
```

## Block Wrapper Strategy

### PHP Block Registration
```php
function plugin_name_register_styled_blocks() {
    register_block_type( __DIR__ . '/build/blocks/styled-block', array(
        'render_callback' => 'plugin_name_render_styled_block',
    ) );
}

function plugin_name_render_styled_block( $attributes, $content ) {
    // Ensure isolated wrapper
    $wrapper_attributes = get_block_wrapper_attributes( array(
        'class' => 'plugin-name-block-isolation',
        'data-block-theme' => $attributes['theme'] ?? 'light',
    ) );
    
    return sprintf(
        '<div %s>%s</div>',
        $wrapper_attributes,
        $content
    );
}
```

### JavaScript Block Wrapper
```javascript
// src/blocks/styled-block/edit.js
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({
        className: 'plugin-name-block-isolation',
        'data-block-theme': attributes.theme || 'light'
    });
    
    return (
        <div {...blockProps}>
            <div className="blk-p-4 blk-bg-white blk-rounded-lg blk-shadow">
                <button className="dui-btn dui-btn-primary blk-w-full">
                    Isolated Button
                </button>
            </div>
        </div>
    );
}
```

## Style Isolation Techniques

### CSS Custom Properties Isolation
```css
/* Block-scoped CSS custom properties - Light theme minimalistic palette */
.wp-block-plugin-name-block {
  --blk-primary: #3b82f6;      /* Clean blue */
  --blk-secondary: #64748b;    /* Muted gray */
  --blk-accent: #10b981;       /* Subtle green */
  --blk-neutral: #f8fafc;      /* Light background */
  --blk-base-100: #ffffff;     /* Pure white */
  --blk-base-content: #1f2937; /* Dark text */
  
  /* Minimalistic spacing scale */
  --blk-spacing-xs: 0.25rem;
  --blk-spacing-sm: 0.5rem;
  --blk-spacing-md: 1rem;
  --blk-spacing-lg: 1.5rem;
  --blk-spacing-xl: 2rem;
  
  /* Override any theme custom properties */
  --wp--preset--color--primary: var(--blk-primary);
}

/* Prevent theme custom properties from leaking in */
.wp-block-plugin-name-block * {
  /* Reset theme color variables */
  --wp--preset--color--background: initial;
  --wp--preset--color--foreground: initial;
}
```

### Container Query Isolation
```css
/* Use container queries for responsive design within blocks */
.wp-block-plugin-name-block {
  container-type: inline-size;
}

/* Responsive design based on block size, not viewport */
@container (min-width: 400px) {
  .wp-block-plugin-name-block .blk-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@container (min-width: 600px) {
  .wp-block-plugin-name-block .blk-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
```

### Shadow DOM Alternative (CSS-in-JS)
```javascript
// src/blocks/styled-block/styles.js
import { css } from '@emotion/css';

export const isolatedStyles = {
  container: css`
    /* Completely isolated styles */
    all: initial;
    display: block;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    
    & * {
      all: unset;
      display: revert;
      box-sizing: border-box;
    }
  `,
  
  button: css`
    @apply blk-bg-blue-500 blk-text-white blk-px-4 blk-py-2 blk-rounded;
    
    &:hover {
      @apply blk-bg-blue-600;
    }
  `
};

// Usage in component
<div className={isolatedStyles.container}>
  <button className={isolatedStyles.button}>
    Isolated Button
  </button>
</div>
```

## Editor/Frontend Consistency

### Shared Style Loading
```php
function plugin_name_enqueue_block_assets() {
    // Load on both editor and frontend
    wp_enqueue_style(
        'plugin-name-blocks',
        plugin_dir_url(__FILE__) . 'build/blocks.css',
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'build/blocks.css')
    );
}
add_action('enqueue_block_assets', 'plugin_name_enqueue_block_assets');

function plugin_name_enqueue_editor_assets() {
    // Editor-specific enhancements
    wp_enqueue_style(
        'plugin-name-blocks-editor',
        plugin_dir_url(__FILE__) . 'build/blocks-editor.css',
        array('plugin-name-blocks'),
        filemtime(plugin_dir_path(__FILE__) . 'build/blocks-editor.css')
    );
}
add_action('enqueue_block_editor_assets', 'plugin_name_enqueue_editor_assets');
```

### Editor Style Adjustments
```css
/* build/blocks-editor.css - Editor-specific adjustments */
.block-editor-block-list__layout .wp-block-plugin-name-block {
  /* Ensure isolation works in editor */
  position: relative;
  z-index: 1;
  
  /* Prevent editor styles from interfering */
  .blk-input {
    /* Override Gutenberg input styles */
    border: 1px solid #d1d5db !important;
    background: white !important;
  }
  
  /* Editor-specific responsive preview */
  &[data-align="full"] {
    container: layout / inline-size;
  }
}
```

## Theme Compatibility Strategies

### Theme Detection and Adaptation
```javascript
// src/utils/theme-detection.js
export class ThemeCompatibility {
  static detectThemeIssues() {
    const issues = [];
    
    // Check for conflicting CSS custom properties
    const computedStyle = getComputedStyle(document.documentElement);
    const themeColors = {
      primary: computedStyle.getPropertyValue('--wp--preset--color--primary'),
      secondary: computedStyle.getPropertyValue('--wp--preset--color--secondary')
    };
    
    if (themeColors.primary) {
      issues.push('Theme defines --wp--preset--color--primary');
    }
    
    return issues;
  }
  
  static applyCompatibilityFixes(blockElement) {
    // Force isolation
    blockElement.style.isolation = 'isolate';
    
    // Reset problematic inheritances
    const resetProps = [
      'font-family',
      'font-size',
      'line-height',
      'color'
    ];
    
    resetProps.forEach(prop => {
      blockElement.style.setProperty(prop, 'revert-layer', 'important');
    });
  }
}
```

### CSS Cascade Layers
```css
/* Use CSS cascade layers for precise control */
@layer theme, blocks, utilities;

@layer blocks {
  .wp-block-plugin-name-block {
    /* Block styles at specific layer */
    @apply blk-p-4 blk-bg-white;
  }
}

@layer utilities {
  /* Utility overrides at highest priority */
  .wp-block-plugin-name-block .blk-important {
    color: red !important;
  }
}
```

## Performance Optimization

### CSS Purging Configuration
```javascript
// tailwind.config.js - Production optimization for minimalistic design
module.exports = {
  content: [
    "./src/blocks/**/*.{js,jsx,ts,tsx}",
    "./src/blocks/**/*.php"
  ],
  safelist: [
    // Essential DaisyUI classes for light theme only
    'dui-btn',
    'dui-btn-primary',
    'dui-btn-outline', 
    'dui-card',
    'dui-card-body',
    'dui-input',
    'dui-textarea',
    // Minimalistic utility classes
    'blk-bg-base-100',
    'blk-bg-base-200',
    'blk-border-base-200',
    'blk-border-base-300',
    'blk-text-base-content',
    // Grid classes for simple layouts
    /^blk-grid-cols-[1-4]$/,
    /^blk-gap-[2-8]$/,
  ],
  // Additional purge options for WordPress
  options: {
    defaultExtractor: content => {
      // Extract classes from PHP render callbacks
      const phpMatches = content.match(/(?:class|className)=['"`]([^'"`]*)/g) || [];
      const jsMatches = content.match(/(?:blk-|dui-)[a-zA-Z0-9-_:\/]+/g) || [];
      return [...phpMatches, ...jsMatches];
    }
  }
};
```

### Critical CSS Inlining
```php
function plugin_name_inline_critical_block_css() {
    $critical_css = '
    .wp-block-plugin-name-block {
        all: initial;
        display: block;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        isolation: isolate;
        /* Light theme base colors */
        --blk-primary: #3b82f6;
        --blk-base-100: #ffffff;
        --blk-base-content: #1f2937;
        --blk-border-base-200: #e5e7eb;
    }
    
    /* Minimal button critical styles */
    .wp-block-plugin-name-block .dui-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 0.375rem;
        border: 1px solid transparent;
        text-decoration: none;
        cursor: pointer;
        box-shadow: none;
        text-transform: none;
    }
    ';
    
    wp_add_inline_style('wp-block-library', $critical_css);
}
add_action('wp_enqueue_scripts', 'plugin_name_inline_critical_block_css');
```

## Block Variations with Isolated Themes

### Block Variations with Light Theme Styling
```javascript
import { registerBlockVariation } from '@wordpress/blocks';

registerBlockVariation('plugin-name/styled-block', {
    name: 'minimal-card',
    title: 'Minimal Card',
    attributes: {
        style: 'minimal',
        className: 'is-style-minimal'
    },
    icon: 'admin-page',
    scope: ['inserter']
});

registerBlockVariation('plugin-name/styled-block', {
    name: 'clean-form',
    title: 'Clean Form',
    attributes: {
        style: 'form',
        className: 'is-style-clean-form'
    },
    icon: 'feedback',
    scope: ['inserter']
});
```

### Light Theme Consistent Styling
```css
/* Light theme variations within isolation - minimalistic approach */
.wp-block-plugin-name-block {
  /* Always light theme base */
  --blk-bg-base: #ffffff;
  --blk-text-base: #1f2937;
  --blk-border-base: #e5e7eb;
  --blk-accent-subtle: #f3f4f6;
}

/* Minimal card styling */
.wp-block-plugin-name-block.is-style-minimal {
  .dui-card {
    @apply blk-bg-base-100 blk-border blk-border-base-200 blk-shadow-none;
    
    .dui-card-body {
      @apply blk-p-6;
    }
  }
}

/* Clean form styling */
.wp-block-plugin-name-block.is-style-clean-form {
  .dui-input,
  .dui-textarea {
    @apply blk-border blk-border-base-300 blk-bg-base-100 blk-focus:border-primary;
    @apply blk-shadow-none blk-rounded-md;
  }
  
  .dui-btn {
    @apply blk-shadow-none blk-font-normal blk-normal-case;
  }
}

/* DaisyUI component minimalistic overrides */
.wp-block-plugin-name-block .dui-btn {
  /* Remove default shadows and bold text for cleaner look */
  box-shadow: none;
  font-weight: 500;
  text-transform: none;
  border: 1px solid transparent;
  
  &.dui-btn-primary {
    background-color: var(--blk-primary);
    border-color: var(--blk-primary);
    
    &:hover {
      background-color: #2563eb;
      border-color: #2563eb;
    }
  }
  
  &.dui-btn-outline {
    background-color: transparent;
    border-color: var(--blk-border-base);
    color: var(--blk-text-base);
    
    &:hover {
      background-color: var(--blk-accent-subtle);
    }
  }
}
```

## Development Workflow

### Block Development Commands
```bash
# Start isolated development
npm run start &
npm run build:tailwind &

# Build for production with purging
NODE_ENV=production npm run build:blocks

# Test theme compatibility
wp-env run tests-wordpress "wp theme activate twentytwentythree"
wp-env run tests-wordpress "wp theme activate astra"
```

### Testing Isolation
```javascript
// tests/js/isolation.test.js
import { render } from '@testing-library/react';

describe('Block Style Isolation', () => {
  test('block styles do not leak to parent', () => {
    const { container } = render(
      <div className="theme-container">
        <div className="wp-block-plugin-name-block">
          <button className="dui-btn">Test</button>
        </div>
      </div>
    );
    
    const themeContainer = container.querySelector('.theme-container');
    const blockButton = container.querySelector('.dui-btn');
    
    // Theme styles should not affect block
    expect(getComputedStyle(blockButton).fontFamily)
      .not.toBe(getComputedStyle(themeContainer).fontFamily);
  });
  
  test('theme styles do not affect block', () => {
    // Test theme interference
    document.body.style.setProperty('--wp--preset--color--primary', '#ff0000');
    
    const { container } = render(
      <div className="wp-block-plugin-name-block">
        <div className="blk-bg-primary">Content</div>
      </div>
    );
    
    const blockElement = container.querySelector('.blk-bg-primary');
    expect(getComputedStyle(blockElement).backgroundColor)
      .toBe('rgb(59, 130, 246)'); // Our isolated primary color
  });
});
```

## Critical Rules

1. **Light Theme Only** - Use DaisyUI "light" theme exclusively for consistency
2. **Minimalistic Approach** - Prefer clean, simple designs over complex styling
3. **Always Use Prefixes** - `blk-` for Tailwind, `dui-` for DaisyUI
4. **Disable Preflight** - Prevent base style conflicts
5. **Isolation First** - Use `all: initial` and `isolation: isolate`
6. **Container Queries** - Responsive design based on block size
7. **Editor Consistency** - Same styles in editor and frontend
8. **Nest All Admin Pages** - All admin option pages MUST use `add_submenu_page()` with parent slug `'rwp-creator-tools'` - never create additional top-level menus
9. **Performance Aware** - Purge unused styles aggressively
10. **Theme Agnostic** - Work with any WordPress theme
11. **CSS Layers** - Use cascade layers for precise control
12. **Subtle Interactions** - Minimize shadows, animations, and effects
13. **Clean Typography** - Use system fonts and normal font weights

## Troubleshooting

### Common Issues
- **Styles Bleeding**: Check CSS specificity and isolation
- **Editor Differences**: Verify shared asset loading
- **Theme Conflicts**: Use CSS custom property resets
- **Performance**: Optimize Tailwind purging configuration
- **DaisyUI Not Working**: Ensure proper prefix and scope configuration
- **Too Much Visual Weight**: Reduce shadows, borders, and font weights
- **Inconsistent Spacing**: Use defined spacing scale variables
- **Complex Designs**: Simplify layouts and reduce visual elements

### Minimalistic Design Checklist
- ✅ Remove unnecessary shadows and gradients
- ✅ Use subtle borders instead of heavy dividers  
- ✅ Prefer normal font weights over bold
- ✅ Use plenty of whitespace
- ✅ Limit color palette to essentials
- ✅ Keep animations subtle or remove entirely
- ✅ Use simple, geometric shapes
- ✅ Prioritize readability over decoration