# Instagram Banner Overlay Enhancement - Claude Code Context

## Task Overview
Replace the `.blk-teaser-overlay` implementation in the Instagram Banner Creator block with the enhanced version from the Instagram Analyzer block to improve user experience and visual appeal.

## Project Structure
```
blocks/
├── instagram-analyzer/
│   ├── block.json
│   ├── edit.js
│   ├── save.js
│   ├── index.js
│   ├── style.scss (source of enhanced overlay)
│   └── editor.scss
└── instagram-banner/
    ├── block.json
    ├── edit.js
    ├── save.js
    ├── index.js
    ├── style.scss (target file to update)
    └── editor.scss
```

## Target File
- **File to modify**: `blocks/instagram-banner/style.scss`
- **Specific section**: `.blk-teaser-overlay` and related styles
- **Action**: Replace with enhanced version from Instagram Analyzer

## WordPress Development Standards
- **Prefix**: All CSS classes use `blk-` for Tailwind utilities and isolation
- **Architecture**: Client-heavy with block containers, minimalistic light theme
- **Framework**: WordPress blocks using `@wordpress/scripts`
- **Responsive**: Container queries with mobile-first approach
- **Isolation**: Complete style isolation from theme using CSS containment

## Source Implementation (Instagram Analyzer)
The enhanced `.blk-teaser-overlay` from Instagram Analyzer includes:

### Key Features:
1. **Gradient Background**: `linear-gradient(135deg, rgba(59, 130, 246, 0.95), rgba(139, 92, 246, 0.95))`
2. **Structured Content**: Icon, heading, description, benefits list, CTA
3. **Benefits Section**: `.blk-teaser-benefits` with checkmark-style items
4. **Enhanced CTA**: White button with hover states and additional note text
5. **Container Queries**: Responsive behavior using `@container` rules

### Source SCSS Structure:
```scss
.blk-teaser-results {
    position: relative;

    .blk-teaser-overlay {
        // Enhanced gradient overlay
        .blk-teaser-content {
            // Centered content with structured layout
            .blk-teaser-icon { }
            h3 { }
            p { }
            .blk-teaser-benefits {
                .blk-benefit-item { }
            }
            .blk-teaser-cta {
                .blk-button--large { }
                .blk-teaser-note { }
            }
        }
    }

    .blk-teaser-preview {
        // Blurred background preview
    }
}
```

## Current Implementation (Instagram Banner)
The current Instagram Banner has a basic overlay that needs enhancement:

### Current Issues:
- Simple overlay with minimal styling
- Basic "Create Account to Download" message
- No benefits list or structured content
- Less engaging visual design
- Missing proper CTA structure

## Required Changes

### 1. Replace Teaser Overlay Section
**Location**: Lines containing `.blk-teaser-overlay` in `style.scss`

**Replace with**: Enhanced overlay implementation including:
- Gradient background styling
- `.blk-teaser-content` with structured layout
- `.blk-teaser-icon` for visual appeal
- `.blk-teaser-benefits` with benefit items
- `.blk-teaser-cta` with enhanced button and note

### 2. Add Container Query Support
**Add**: Container query setup to main container:
```scss
.blk-instagram-banner-container {
    container-type: inline-size;
}
```

### 3. Update Responsive Behavior
**Add**: Container queries for mobile optimization:
```scss
@container (max-width: 480px) {
    .blk-teaser-content {
        padding: 1rem !important;
        .blk-teaser-benefits {
            text-align: center !important;
        }
    }
}
```

## Expected Outcome
After implementation, the Instagram Banner Creator will have:

1. **Professional gradient overlay** matching Instagram Analyzer design
2. **Structured content layout** with icon, heading, benefits, and CTA
3. **Enhanced user engagement** through better visual hierarchy
4. **Improved mobile experience** with responsive container queries
5. **Consistent branding** across both blocks

## Development Safety
- **Additive Enhancement**: Only improving existing functionality
- **No Breaking Changes**: Maintaining all current block functionality  
- **WordPress Compliance**: Following all block development standards
- **Style Isolation**: Maintaining `blk-` prefixed classes and containment

## Testing Checklist
After implementation:
- [ ] Overlay displays correctly on desktop
- [ ] Responsive behavior works on mobile
- [ ] Benefits list displays properly
- [ ] CTA button has correct styling and hover states
- [ ] Container queries function as expected
- [ ] No theme style conflicts
- [ ] Block editor preview remains consistent

## Context Notes
- This is part of a WordPress Creator Suite plugin
- Both blocks follow the same architectural patterns
- Light theme minimalistic design approach
- Enhanced UX to improve conversion rates
- Maintains accessibility and performance standards