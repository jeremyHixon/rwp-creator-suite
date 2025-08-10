# RWP Creator Suite

A comprehensive WordPress plugin designed for content creators, providing streamlined user authentication and subscriber experience optimization.

## Features

### User Authentication
- **Email-only registration**: Users can register with just an email address - usernames are automatically generated
- **Automatic login**: Users are automatically logged in after successful registration
- **Smart redirects**: Users are redirected back to the page they originally tried to access
- **Automatic logout**: Direct logout without confirmation page when accessing `/wp-login.php?action=logout`
- **Subscriber-focused**: Optimized experience for subscriber-level users

### Instagram Follower Analyzer
- **WordPress Block**: Easy-to-use Gutenberg block for analyzing Instagram follower relationships
- **Data Upload**: Secure client-side processing of Instagram data export ZIP files
- **Follower Analysis**: Identifies accounts you follow that don't follow you back
- **Whitelist Management**: Save accounts to a whitelist to exclude them from analysis results
- **User Authentication Integration**: Full functionality for registered users, preview mode for guests
- **Secure Data Handling**: All analysis is performed client-side for privacy protection

### Instagram Banner Creator
- **WordPress Block**: Intuitive Gutenberg block for creating Instagram banner effects
- **Image Upload**: Drag-and-drop interface for uploading images (JPEG, PNG, WebP up to 10MB)
- **Image Cropping**: Interactive crop interface with locked 3248×1440 aspect ratio for optimal banner layout
- **Three-Panel Split**: Automatically splits images into three 1080×1440 Instagram posts with 4px gaps
- **Real-time Preview**: Live preview of the banner effect showing all three images side by side
- **Batch Download**: Download all three images at once for easy posting to Instagram
- **User Authentication Integration**: Full functionality for registered users, teaser preview for guests
- **Responsive Design**: Mobile-optimized interface with touch-friendly controls

### Security & Performance
- **Rate limiting**: Prevents spam registrations with configurable rate limits
- **Secure redirects**: All redirects are validated for security
- **Asset URL filtering**: Prevents storing of image/asset URLs in redirect handling

## Requirements

- WordPress 5.0+
- PHP 7.4+

## Installation

1. Upload the plugin files to `/wp-content/plugins/rwp-creator-suite/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. The plugin will automatically enable user registration and set the default role to subscriber

## Usage

### Registration Process
1. Users visit any page that requires authentication
2. They are redirected to the WordPress registration form
3. Only email address is required - username is auto-generated
4. After successful registration, users are automatically logged in
5. Users are redirected back to their original destination

### Logout Process
- When users navigate to `/wp-login.php?action=logout`, they are automatically logged out and redirected to the homepage
- No confirmation page is shown

## Technical Details

### Hooks and Filters
- `rwp_creator_suite_before_user_registration` - Fired before user registration
- `rwp_creator_suite_after_user_registration` - Fired after user registration
- `rwp_creator_suite_user_auto_login` - Fired after automatic login
- `rwp_creator_suite_registration_redirect_url` - Filter for customizing redirect URLs
- `rwp_creator_suite_subscriber_redirect_url` - Filter for subscriber default redirect

## Changelog

### Version 1.3.0 (2025-08-10)
#### New Features
- **Instagram Banner Creator Block**: Complete implementation of a Gutenberg block for creating Instagram banner effects
  - Client-side image processing with drag-and-drop upload interface
  - Interactive cropping tool with locked 3248×1440 aspect ratio for optimal banner layout
  - Automatic image splitting into three 1080×1440 Instagram posts with 4px gaps
  - Real-time preview showing the banner effect with all three images
  - Batch download functionality for easy Instagram posting
  - Support for JPEG, PNG, and WebP formats up to 10MB
  - Responsive design optimized for mobile devices
  - Integration with user authentication system (full access for registered users, teaser for guests)

#### Technical Improvements  
- Added `InstagramBannerCreator` JavaScript class with complete image processing workflow
- Implemented HTML5 Canvas-based image manipulation for precise splitting
- Added responsive SCSS styling with container queries and mobile optimization
- Enhanced block registration system to support the new banner creator block
- Added state management for preserving user progress during the creation process

### Version 1.2.1 (2025-08-10)
#### New Features
- **Account Viewed Tracking**: Instagram Follower Analyzer now tracks which account profiles have been viewed
  - Visual indicators: Eye icon (👁) and "Viewed" badge appear on accounts that have been clicked
  - Automatic marking when users click username links or "View Profile" buttons
  - Viewed state persists across sessions using browser storage
  - Helps users systematically work through large lists of non-mutual followers

#### Technical Improvements
- Enhanced StateManager with `markAccountAsViewed()`, `getViewedAccounts()`, and `saveViewedAccounts()` methods
- Added `blk-account-item--viewed` CSS class for styling viewed accounts
- Improved UI updates with real-time visual feedback
- Updated Instagram Analyzer block version to 1.1.0

### Version 1.2.0 (2025-08-08)
#### New Features
- **Instagram Follower Analyzer Block**: Complete implementation of a Gutenberg block for analyzing Instagram follower relationships
  - Client-side ZIP file processing for Instagram data exports
  - Real-time analysis of followers vs following to identify accounts that don't follow back
  - Secure local data processing to protect user privacy
  - Whitelist management system for saving preferred accounts
  - Integration with user authentication system (full access for registered users, preview for guests)
  - AJAX API endpoints for whitelist synchronization across devices
  - Responsive design with professional styling
  - Support for drag-and-drop file uploads with progress indicators
  - Search and filtering capabilities for analysis results

#### Technical Improvements  
- Added `RWP_Creator_Suite_Instagram_Analyzer_API` class for handling AJAX endpoints
- Implemented secure nonce-based authentication for API calls
- Added Instagram username sanitization and validation
- Created comprehensive JavaScript application with state management
- Enhanced block manager to register the Instagram Analyzer block
- Added JSZip integration for client-side ZIP file processing

### Version 1.1.0 (2025-08-07)
#### New Features
- Added automatic logout redirect functionality
  - Users navigating to `/wp-login.php?action=logout` are automatically logged out and redirected to homepage
  - Bypasses WordPress default logout confirmation page
  - Implemented secure nonce handling for logout process

#### Bug Fixes
- Fixed redirect loop issues in logout functionality
- Improved redirect handler to prevent storing asset URLs (images, CSS, JS, etc.)
- Enhanced security validation for logout process

#### Technical Improvements
- Added `handle_automatic_logout()` method to WP Login Integration class
- Improved nonce creation and verification for logout process
- Enhanced URL validation in redirect handler

### Version 1.0.0 (Initial Release)
#### Features
- Email-only user registration with auto-generated usernames
- Automatic login after registration
- Smart redirect handling for authentication flow
- Subscriber role optimization
- Rate limiting for registration attempts
- WordPress login form integration
- Secure redirect validation

## Support

For support and feature requests, please contact the plugin author.

## License

GPL v2 or later
