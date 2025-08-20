# Consent Management Phase 1: Foundation & Google Analytics Integration

See docs/consent-management-architecture-overview.md for the architecture overfiew.

**Goal:** Extend existing binary consent to granular categories and integrate with Google Analytics via Site Kit

## Core Consent Categories

### Category Definition
Extend the current binary analytics consent system to support granular consent categories:

- **`necessary`** - Always granted, essential functionality (login, security, basic site operation)
- **`analytics`** - Google Analytics via Site Kit + custom tracking data
- **`marketing`** - Future advertising pixels, social media tracking, remarketing
- **`functional`** - User preferences, enhanced features, personalization

### Implementation Requirements

#### Database Schema Updates
- **Migrate from binary to categorical consent** in WordPress user meta
- **Add consent timestamp tracking** for audit compliance
- **Implement consent version tracking** for policy updates
- **Create consent audit trail** table for GDPR compliance requirements

#### Consent Storage Structure
```php
// New user meta structure
'rwp_consent_analytics' => 'granted|denied|not_set'
'rwp_consent_marketing' => 'granted|denied|not_set' 
'rwp_consent_functional' => 'granted|denied|not_set'
'rwp_consent_timestamp' => timestamp
'rwp_consent_version' => policy_version
```

## Google Analytics Integration

### Google Consent Mode v2 Implementation

#### Step 1: Default Consent State Script
Add to plugin head output before any Google tags:
```javascript
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
  'ad_storage': 'denied',
  'analytics_storage': 'denied', 
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'wait_for_update': 500
});
```

#### Step 2: Site Kit Configuration
- **Disable Site Kit's automatic tag insertion** in Settings > Analytics
- **Keep Site Kit consent mode enabled** in Admin Settings
- **Ensure Site Kit reads from WP Consent API** (implemented in Phase 2)

#### Step 3: Consent Update Integration
Modify existing `setConsent()` method to call Google's consent update:
```javascript
function updateGoogleConsent(categories) {
  gtag('consent', 'update', {
    'analytics_storage': categories.analytics ? 'granted' : 'denied',
    'ad_storage': categories.marketing ? 'granted' : 'denied',
    'ad_user_data': categories.marketing ? 'granted' : 'denied',
    'ad_personalization': categories.marketing ? 'granted' : 'denied'
  });
}
```

## Technical Implementation Tasks

### Backend Updates (PHP)
1. **Extend `RWP_Creator_Suite_Consent_Manager` class**
   - Add granular consent category methods
   - Implement consent versioning system
   - Create audit trail logging

2. **Update REST API endpoints**
   - Modify `/consent` endpoint to handle categories
   - Add consent history endpoint for user dashboard
   - Implement consent export for GDPR requests

### Frontend Updates (JavaScript)
1. **Enhance `RWPConsentManager` class**
   - Add category-specific consent handling
   - Implement Google consent mode integration
   - Create consent status caching system

2. **Update consent banner UI**
   - Add category selection interface
   - Implement progressive disclosure for detailed options
   - Ensure mobile responsiveness

## Testing Requirements

### Functional Testing
- **Consent category independence** - each category can be granted/denied separately
- **Google Analytics data flow** - verify GA4 respects consent decisions
- **Consent persistence** - preferences survive browser sessions and logins
- **Migration testing** - existing binary consent converts properly

### Compliance Testing  
- **GDPR compliance** - consent is freely given, specific, informed, unambiguous
- **Data flow validation** - no tracking occurs without proper consent
- **Audit trail integrity** - all consent changes are properly logged

## Success Metrics

### Technical Metrics
- **Zero tracking data leakage** without consent
- **<500ms consent initialization** on page load
- **100% Site Kit compatibility** with consent decisions
- **Backward compatibility** with existing consent preferences

### Compliance Metrics
- **Complete audit trail** for all consent interactions
- **GDPR-compliant data export** functionality
- **Proper consent granularity** for regulatory requirements
- **User control validation** - users can modify preferences easily

## Risk Mitigation

### Technical Risks
- **Site Kit conflicts** - thorough testing of tag insertion disable
- **Performance impact** - optimize consent check efficiency
- **Migration data loss** - comprehensive backup and rollback plan

### Compliance Risks
- **Consent leakage** - implement fail-safe defaults (deny all)
- **Audit requirements** - ensure complete consent change logging
- **User rights** - validate data export and deletion capabilities

## Deliverables

1. **Extended consent manager classes** with category support
2. **Google Consent Mode integration** with Site Kit compatibility
3. **Migrated user consent data** to new categorical structure
4. **Updated consent banner UI** with category selection
5. **Comprehensive test suite** for consent functionality
6. **Documentation** for consent system usage and extension

This phase establishes the foundation for a robust, compliant consent management system that integrates seamlessly with Google Analytics while maintaining extensibility for future requirements.