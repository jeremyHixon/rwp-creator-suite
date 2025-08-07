# RWP Creator Suite

A comprehensive WordPress plugin designed for content creators, providing streamlined user authentication and subscriber experience optimization.

## Features

### User Authentication
- **Email-only registration**: Users can register with just an email address - usernames are automatically generated
- **Automatic login**: Users are automatically logged in after successful registration
- **Smart redirects**: Users are redirected back to the page they originally tried to access
- **Automatic logout**: Direct logout without confirmation page when accessing `/wp-login.php?action=logout`
- **Subscriber-focused**: Optimized experience for subscriber-level users

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
