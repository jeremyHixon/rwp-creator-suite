# Claude Code Context: Hashtag Analysis Web App - Phase 1 Development

## Project Overview

You are developing a **WordPress plugin** for hashtag analysis that provides a platform-agnostic dashboard for social media analytics. This is Phase 1 of implementation following a hybrid technical model that balances direct API integration with third-party aggregators.

## Strategic Context from Feasibility Study

### Core Value Proposition
- Transform unstructured social media data into actionable intelligence
- Provide unified dashboard for analyzing activity across fragmented social media ecosystem
- Focus on engagement, reach, and sentiment analysis for data-driven decisions

### Phase 1 Implementation Strategy
**Primary Goals:**
1. **Foundation & High-Value Integration** - Get MVP to market quickly
2. **TikTok Direct Integration** - Stable, developer-friendly API with rich data
3. **Third-Party Aggregator Integration** - Access Instagram/Facebook via services like Apify/Data365
4. **Validate Core Concept** - Generate user feedback with minimal business risk

**Recommended Platform Priority:**
1. **TikTok** (Direct API) - LOW lift, stable, rich data
2. **Instagram/Facebook** (Third-party) - LOW-MEDIUM lift via aggregators
3. **X/Twitter** (Future Phase) - HIGH lift, expensive, volatile

## Technical Architecture Requirements

### WordPress Plugin Architecture
- **Modular service-based organization** with feature modules
- **Block-app integration** - Blocks as containers for client-side apps
- **Client-side heavy** - Push logic to JavaScript apps, minimal PHP rendering
- **Cache-friendly** - Aggressive caching for guests, user-specific data for logged-in users
- **localStorage primary** - Database only for logged-in user preferences

### Core Technology Stack
```
Backend (PHP):
- WordPress REST API endpoints
- Service container pattern
- Module-based organization
- User preference management (logged-in users only)

Frontend (JavaScript):
- WordPress blocks as app containers
- State management with localStorage fallbacks
- API client with error handling
- Modular app architecture

Build System:
- @wordpress/scripts for blocks
- Tailwind CSS with DaisyUI (light theme only)
- Style isolation for blocks
- Environment-specific configs
```

## Phase 1 Feature Requirements

### MVP Features (Core Functionality)
Based on feasibility study recommendations:

1. **Real-time Post Tracking**
   - Track hashtag mentions across integrated platforms
   - Display post count and growth metrics
   - Basic timeline visualization

2. **Basic Engagement Metrics**
   - Likes, shares, comments aggregation
   - Engagement rate calculations
   - Top performing posts identification

3. **Simple Hashtag Search**
   - Search functionality for hashtags
   - Platform filtering (TikTok, Instagram, Facebook)
   - Basic results sorting and filtering

4. **Foundational Dashboard**
   - Clean, minimalistic interface (light theme only)
   - Platform-agnostic data display
   - Export capabilities for reports

### Technical Implementation Priorities

#### 1. TikTok Direct Integration
```php
// Core service for TikTok API
class HashtagAnalysis_TikTok_Service {
    // Direct API integration
    // Rate limit management (600 requests/minute)
    // Hashtag discovery and analytics
    // Video metadata extraction
}
```

#### 2. Third-Party Aggregator Integration
```php
// Aggregator service for Instagram/Facebook
class HashtagAnalysis_Aggregator_Service {
    // Apify/Data365 integration
    // Unified data format
    // Error handling and fallbacks
}
```

#### 3. WordPress Block Containers
```javascript
// App container blocks for different views
registerBlockType('hashtag-analysis/dashboard', {
    // Dashboard app container
});

registerBlockType('hashtag-analysis/search', {
    // Search interface container
});
```

## Development Guidelines

### WordPress Coding Standards Compliance
- **Prefix everything**: `hashtag_analysis_`
- **Escape output**: `esc_*` functions
- **Sanitize input**: `sanitize_*` functions
- **Use nonces** for forms/AJAX
- **Check capabilities** for user actions
- **Use `$wpdb->prepare()`** for database queries
- **Text domain**: `'hashtag-analysis'`

### Block Development Standards
- **Blocks as containers only** - No complex logic in blocks
- **Use WordPress components** for block editor only
- **NO localStorage/sessionStorage** in blocks
- **Placeholder components** for editor preview
- **Progressive enhancement** - Core functionality without JS

### Client-Side Architecture
- **State management** with localStorage primary, memory fallback
- **API client** with proper error handling and nonce management
- **Module loader** for lazy loading features
- **Storage isolation** - No guest data in database by design

### Legal & Compliance Requirements
- **GDPR/CCPA compliance** from inception
- **Data minimization** principles
- **Transparent privacy policy**
- **Only public data collection**
- **Respect platform terms of service**
- **No personal data storage** for guests

## File Structure

```
hashtag-analysis/
├── hashtag-analysis.php              # Main plugin file
├── includes/
│   ├── class-plugin-loader.php       # Plugin initialization
│   ├── modules/
│   │   ├── tiktok/
│   │   │   ├── class-tiktok-service.php
│   │   │   ├── class-tiktok-api.php
│   │   │   └── class-tiktok-analytics.php
│   │   ├── aggregator/
│   │   │   ├── class-aggregator-service.php
│   │   │   └── class-data-normalizer.php
│   │   ├── analytics/
│   │   │   ├── class-hashtag-tracker.php
│   │   │   └── class-engagement-calculator.php
│   │   └── dashboard/
│   │       ├── class-dashboard-api.php
│   │       └── class-report-generator.php
│   ├── core/
│   │   ├── class-service-container.php
│   │   ├── class-api-client.php
│   │   └── class-cache-manager.php
│   └── blocks/
│       └── app-container/
├── assets/
│   ├── js/
│   │   ├── modules/
│   │   │   ├── state-manager/
│   │   │   ├── api-client/
│   │   │   ├── dashboard-app/
│   │   │   └── search-app/
│   │   ├── admin.js
│   │   └── frontend.js
│   └── css/
│       ├── admin.scss
│       └── frontend.scss
├── build/                            # Generated assets
├── tests/                           # PHPUnit & Jest tests
└── migrations/                      # Database migrations
```

## API Integration Specifications

### TikTok API Integration
```php
// Rate limits: 600 requests/minute per API
// Endpoints: /video/query/, /v2/user/info/
// Authentication: TikTok Developer account + app registration
// Best practices: Stagger requests, cache data, exponential backoff
```

### Third-Party Aggregator Integration
```php
// Primary providers: Apify, Data365
// Pricing model: Pay-per-result preferred
// Data format: Clean JSON output
// Fallback strategy: Multiple provider support
```

### WordPress REST API Design
```php
// Namespace: hashtag-analysis/v1
// Endpoints:
// - /hashtags/search
// - /hashtags/analytics
// - /user-preferences (auth required)
// - /dashboard-data

// Security: Nonce verification, capability checks
// Caching: Aggressive for guests, none for logged-in users
```

## Performance & Caching Strategy

### Guest User Optimization
- **Static block containers** - Fully cacheable
- **Client-side state** - localStorage for app data
- **API caching** - Cache public data aggressively
- **No database storage** - All guest data stays client-side

### Rate Limit Management
- **TikTok**: 600 requests/minute sliding window
- **Aggregators**: Respect provider limits
- **Implementation**: Exponential backoff, request queuing
- **Monitoring**: Track usage and implement warnings

## Error Handling & Resilience

### API Error Handling
```javascript
// Graceful degradation for API failures
// User-friendly error messages
// Automatic retry with exponential backoff
// Fallback to cached data when available
```

### Storage Fallbacks
```javascript
// localStorage → sessionStorage → memory storage
// User warnings for storage limitations
// No server fallback by design (privacy compliance)
```

## Development Workflow

### Phase 1 Milestones
1. **Week 1-2**: Core plugin structure, TikTok API integration
2. **Week 3-4**: Third-party aggregator integration, data normalization
3. **Week 5-6**: WordPress blocks, basic dashboard app
4. **Week 7-8**: Search functionality, engagement metrics
5. **Week 9-10**: Testing, optimization, MVP launch

### Testing Strategy
- **PHPUnit** for PHP backend logic
- **Jest** for JavaScript client-side code
- **Integration tests** for API endpoints
- **WordPress compatibility** testing

## Security & Privacy

### Data Handling Principles
- **Collect only public data** that's already accessible
- **No personal data storage** for unauthenticated users
- **User preferences only** for logged-in users
- **Transparent data usage** in privacy policy
- **Regular compliance audits**

### API Security
- **WordPress nonces** for state-changing operations
- **User capability checks** before data access
- **Rate limiting** to prevent abuse
- **Input sanitization** for all user data

## Constraints & Limitations

### Technical Constraints
- **WordPress compatibility** - Must work with any theme
- **No localStorage breaking changes** - Graceful degradation required
- **Style isolation** - Blocks must not interfere with themes
- **Performance budget** - Fast loading times essential

### Business Constraints
- **Legal compliance** - GDPR/CCPA from day one
- **Platform terms** - Respect all social media platform ToS
- **Cost management** - Monitor API usage costs closely
- **Scalability** - Design for growth but start simple

## Success Metrics for Phase 1

### Technical Metrics
- **API response times** < 2 seconds average
- **Block loading performance** < 1 second
- **Error rates** < 5% for API calls
- **Cache hit rates** > 80% for guest users

### Product Metrics
- **User engagement** with dashboard features
- **Search query success rates**
- **Data accuracy** compared to platform native analytics
- **User retention** and feature adoption

## Next Steps for Development

1. **Initialize plugin structure** with service container
2. **Set up build system** with @wordpress/scripts
3. **Implement TikTok service** with rate limit handling
4. **Create aggregator service** with Apify integration
5. **Build app container blocks** with minimal placeholder UI
6. **Develop state management** with localStorage primary strategy
7. **Implement basic dashboard** with real-time metrics
8. **Add search functionality** with platform filtering
9. **Create user preference system** for logged-in users
10. **Comprehensive testing** and MVP optimization

Remember: Focus on getting a working MVP to market quickly while maintaining high code quality and legal compliance. The hybrid approach reduces risk while providing immediate value to users.