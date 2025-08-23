# RWP Creator Suite - Class Naming Standards

## Universal Prefix System

All blocks use the `rwp-` prefix for consistency across the plugin. The `blk-` prefix is reserved for Tailwind utility classes only.

## Component Class Names

### Form Components
- `.rwp-form` - Form wrapper
- `.rwp-form-group` - Form field container
- `.rwp-form-label` - Field labels
- `.rwp-content-input` - Text inputs and textareas
- `.rwp-tone-select` - Select dropdowns
- `.rwp-form-actions` - Button containers

### Platform Selection
- `.rwp-platform-selection` - Platform selection wrapper
- `.rwp-platform-label` - Platform selection label
- `.rwp-platform-checkboxes` - Checkbox container
- `.rwp-platform-checkbox` - Individual platform checkbox
- `.rwp-platform-icon-label` - Icon + label wrapper
- `.rwp-platform-icon` - Platform icons
- `.rwp-platform-name` - Platform name text

### Buttons
- `.rwp-button` - Base button class
- `.rwp-button--primary` - Primary action buttons
- `.rwp-button--secondary` - Secondary action buttons  
- `.rwp-button--small` - Small variant
- `.rwp-button--large` - Large variant
- `.rwp-generate-button` - Specific generate buttons
- `.rwp-copy-button` - Copy to clipboard buttons
- `.rwp-guest-cta-button` - Guest call-to-action buttons

### Results & Content
- `.rwp-results-container` - Results wrapper
- `.rwp-results-title` - Results section title
- `.rwp-platform-result` - Platform-specific result
- `.rwp-platform-header` - Result platform header
- `.rwp-platform-name` - Platform name in results
- `.rwp-character-limit` - Character limit display
- `.rwp-content-versions` - Content versions wrapper
- `.rwp-content-version` - Individual content version
- `.rwp-version-text` - Version content text
- `.rwp-version-meta` - Version metadata

### Loading & States
- `.rwp-loading-container` - Loading state wrapper
- `.rwp-loading-spinner` - Spinner element
- `.rwp-error-message` - Error display
- `.rwp-error-content` - Error message content
- `.rwp-success-notification` - Success toast

### Character Counting
- `.rwp-character-counter` - Counter wrapper
- `.rwp-character-count` - Count display
- `.rwp-count-current` - Current count number
- `.rwp-count-warning` - Warning state
- `.rwp-count-error` - Error state
- `.rwp-current-count` - Enhanced counter

### Guest/Upgrade Features
- `.rwp-guest-teaser` - Guest teaser wrapper
- `.rwp-guest-teaser-content` - Teaser content
- `.rwp-guest-teaser-title` - Teaser title
- `.rwp-guest-attempts-counter` - Attempts remaining
- `.rwp-attempts-remaining` - Attempts text
- `.rwp-attempts-count` - Attempts number
- `.rwp-guest-upgrade-hint` - Upgrade suggestions

### Utility States
- `.rwp-copied` - Copied state modifier
- `.rwp-loading` - Loading state modifier
- `.rwp-disabled` - Disabled state modifier
- `.rwp-hidden` - Hidden state
- `.rwp-preview` - Preview state

## Block-Specific Containers

Each block should have its main container follow this pattern:
- `.wp-block-rwp-creator-suite-{block-name}` - WordPress block wrapper
- `.rwp-{block-name}-container` - Main block container

Examples:
- `.wp-block-rwp-creator-suite-caption-writer .rwp-caption-writer-container`
- `.wp-block-rwp-creator-suite-content-repurposer .rwp-content-repurposer-container`
- `.wp-block-rwp-creator-suite-account-manager .rwp-account-manager-container`

## Tailwind Utility Prefix

All Tailwind utilities use the `blk-` prefix to avoid conflicts:
- `.blk-text-center`
- `.blk-bg-blue-500` 
- `.blk-p-4`
- `.blk-rounded-lg`
- etc.

## Legacy Classes to Remove

These old class names should be replaced:
- Any classes without `rwp-` or `blk-` prefixes
- Mixed naming conventions
- Inconsistent modifier patterns