# Shared Styles Architecture

This directory contains the shared styling system for RWP Creator Suite blocks.

## File Structure

```
shared/styles/
├── index.scss          # Main entry point with :export for JS
├── variables.scss      # Design tokens and CSS custom properties  
├── editor-common.scss  # Shared editor preview styles
├── mixins.scss        # Reusable SCSS mixins for common patterns
└── README.md          # This file
```

## Usage in Block Styles

### For Editor Styles (editor.scss)
```scss
@import '../shared/styles/editor-common.scss';

.wp-block-your-block-name {
    @include block-preview('your-block-suffix');
}
```

### For Frontend Styles (style.scss)
```scss
@import '../shared/styles/variables.scss';
@import '../shared/styles/mixins.scss';

.wp-block-your-block-name {
    // Include shared patterns
    @include form-input-styles();
    @include platform-selection-styles();
    @include primary-button-styles('your-button-class');
    
    // Block-specific styles here
}
```

## Available Mixins

### Form & Input Styles
- `@include form-input-styles()` - Standard form inputs with focus states
- `@include character-counter-styles()` - Character counters with warning/error states

### Platform & UI Components
- `@include platform-selection-styles()` - Platform checkboxes with icons
- `@include primary-button-styles($class-name)` - Primary action buttons
- `@include secondary-button-styles($class-name)` - Secondary buttons
- `@include loading-states()` - Loading spinners and containers

### Layout & Results
- `@include results-container-styles()` - Results display containers
- `@include guest-upgrade-styles()` - Guest user messaging and CTAs

### Accessibility
- `@include accessibility-styles()` - Focus states and reduced motion support

## Design Tokens

All design tokens are available as CSS custom properties:

### Colors
- `--rwp-primary-500` through `--rwp-primary-900`
- `--rwp-gray-50` through `--rwp-gray-900`
- `--rwp-success-500`, `--rwp-error-500`, `--rwp-warning-500`

### Typography
- `--rwp-font-family`
- `--rwp-font-size-xs` through `--rwp-font-size-2xl`

### Spacing & Layout
- `--rwp-spacing-1` through `--rwp-spacing-12`
- `--rwp-radius-sm` through `--rwp-radius-xl`

### Transitions
- `--rwp-transition-fast` (150ms)
- `--rwp-transition-base` (200ms)
- `--rwp-transition-slow` (300ms)

## Benefits

1. **Consistency** - All blocks share the same design language
2. **Maintainability** - Changes to shared patterns update all blocks
3. **Reduced Duplication** - Common styles defined once
4. **Performance** - Smaller bundle sizes through shared code
5. **Accessibility** - Consistent focus states and motion preferences

## Migration Guide

When updating existing blocks:

1. Import shared styles at the top of your SCSS files
2. Replace duplicated code with mixin includes
3. Use design tokens instead of hardcoded values
4. Test editor and frontend rendering after changes

## Tailwind Integration

This system works alongside the existing Tailwind setup:
- Shared styles use `blk-` prefixed Tailwind classes
- Custom properties provide fallbacks
- Mixins wrap Tailwind utilities for reusability