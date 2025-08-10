# Instagram Banner Creator - WordPress Block Development Context

## Project Overview
Build a WordPress block that allows users to upload an image and split it into 3 separate Instagram-optimized images (1080x1440px each) to create a banner effect across their Instagram profile grid.

## Architecture Requirements

### Block Structure (WordPress Guidelines)
- **Block Type**: App container block following documented patterns
- **Edit Component**: Simple placeholder with configuration options
- **Save Component**: Empty div with data attributes for app mounting
- **Frontend App**: Client-side JavaScript application handles all functionality

### Technical Specifications
- **Target Canvas Size**: 3248x1440px
- **Output Images**: 3 images at 1080x1440px each
- **Split Points**: 
  - Image 1: 0-1080px
  - Image 2: 1084-2164px (4px gap)
  - Image 3: 2168-3248px (4px gap)
- **Preview Gap**: 4px between images in preview
- **Guest Limitations**: Blurred low-res preview, login prompt for downloads

## File Structure
```
src/
├── blocks/
│   └── instagram-banner/
│       ├── block.json
│       ├── index.js
│       ├── edit.js
│       ├── save.js
│       ├── style.scss
│       └── editor.scss
└── apps/
    └── instagram-banner/
        ├── index.js
        ├── ImageUploader.js
        ├── ImageCropper.js
        ├── BannerPreview.js
        ├── DownloadControls.js
        └── utils/
            ├── imageProcessing.js
            └── canvasUtils.js
```

## Development Guidelines

### WordPress Block (Container Only)
Follow documented block standards:
- Use WordPress components for editor interface
- NO complex logic in blocks
- NO external UI libraries in blocks
- Simple placeholder with app type selection
- Data attributes for app configuration

### Client-Side App (Heavy Lifting)
- Canvas-based image processing
- File upload with drag/drop
- Crop interface with aspect ratio locking
- Real-time preview generation
- Image download generation
- Guest/user state management

## User Experience Flow
1. **Upload**: Drag/drop or select image file
2. **Crop**: Interactive cropping to 3248x1440 aspect ratio
3. **Preview**: Side-by-side preview with 4px gaps
4. **Download**: Generate and download 3 separate images
5. **Guest Limitations**: Blurred preview, login prompt for downloads

## State Management Requirements
- **localStorage primary** for image data and crop settings
- **No database storage** for guest users by design
- **User preferences only** for logged-in users (crop defaults, etc.)
- **Warning fallbacks** if localStorage unavailable

## Performance Considerations
- **Canvas processing** for image manipulation
- **Worker threads** for heavy image processing if needed
- **Progressive loading** for large images
- **Memory management** for large image files
- **Responsive design** with container queries

## Security & File Handling
- **Client-side only** image processing
- **File type validation** (JPEG, PNG, WebP)
- **File size limits** with user-friendly warnings
- **No server upload** required for processing
- **Privacy focused** - images never leave user's browser

## Guest vs User Features
- **Guests**: 
  - Upload and crop images
  - Blurred low-resolution preview
  - Login prompt for downloads
  - localStorage persistence only
- **Users**:
  - Full-resolution preview
  - Download functionality
  - Crop preference saving
  - Usage analytics (optional)

## Technical Implementation Notes

### Image Processing Pipeline
1. **File Upload**: FileReader API for local processing
2. **Canvas Rendering**: HTML5 Canvas for manipulation
3. **Crop Interface**: Interactive crop area with aspect ratio lock
4. **Split Processing**: Canvas-based splitting into 3 images
5. **Download Generation**: Blob URLs for file downloads

### Responsive Design
- **Mobile-first**: Touch-friendly crop interface
- **Container queries**: Block-based responsive design
- **Progressive enhancement**: Works without JavaScript for basic content

### WordPress Integration
- **Block variations**: Different preset aspect ratios
- **WordPress media**: Optional integration with media library
- **Shortcode support**: For non-block themes
- **Cache compatibility**: Static block container works with all caching

## Code Quality Standards
- **WordPress coding standards** for PHP
- **ESLint configuration** for JavaScript
- **Accessibility compliance** WCAG 2.1 AA
- **Browser compatibility** ES2020+ features
- **Error handling** graceful degradation
- **User feedback** clear status messages

## Testing Requirements
- **Unit tests** for utility functions
- **Integration tests** for canvas processing
- **Browser testing** across major browsers
- **Mobile testing** touch interface
- **Performance testing** with large images
- **Accessibility testing** screen readers

## Deployment Considerations
- **Asset optimization** for production builds
- **Progressive enhancement** ensures basic functionality
- **Error monitoring** for canvas/file processing failures
- **Analytics tracking** for usage patterns (privacy-compliant)

## API Endpoints (Optional)
If server features needed:
- `POST /wp-json/plugin-name/v1/banner-analytics` - Usage tracking (logged-in users)
- `GET /wp-json/plugin-name/v1/user-presets` - Saved crop preferences

## Critical Success Factors
1. **Canvas processing works reliably** across browsers
2. **File handling is performant** with large images
3. **Mobile interface is intuitive** for cropping
4. **Preview accurately represents** Instagram appearance
5. **Downloads work correctly** on all devices
6. **Guest experience encourages** registration
7. **Block editor integration** follows WordPress guidelines
8. **Performance remains good** with multiple blocks on page

## External Dependencies
- **No external image processing libraries** - use Canvas API
- **WordPress block editor components** only in blocks
- **Standard Web APIs**: FileReader, Canvas, Blob, URL
- **CSS-in-JS or scoped CSS** for component styling
- **LocalStorage** for state persistence

## Development Phases
1. **Phase 1**: Basic block structure and app container
2. **Phase 2**: File upload and canvas processing
3. **Phase 3**: Crop interface and aspect ratio handling
4. **Phase 4**: Preview generation and Instagram simulation
5. **Phase 5**: Download functionality and guest/user flow
6. **Phase 6**: Polish, testing, and optimization

## Success Metrics
- **Upload success rate** > 95%
- **Processing time** < 5 seconds for typical images
- **Mobile usability** smooth crop interface
- **Conversion rate** guests to registered users
- **Error rate** < 1% for image processing
- **Performance score** > 90 Lighthouse

---

**Priority**: Follow documented architecture patterns strictly. Block is container only, app does heavy lifting. Ensure guest experience drives registration while providing value.