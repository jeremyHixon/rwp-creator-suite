# Phase 5: Testing Expansion & Coverage Improvement

**Priority**: MEDIUM-HIGH - Critical for long-term maintainability and stability
**Estimated Time**: 4-5 hours
**Testing Required**: Continuous validation of test suite
**Dependencies**: Complete Phases 1-4 first

## Overview

This phase expands the test coverage to include the newer modules and API endpoints that currently lack comprehensive testing. The goal is to achieve robust test coverage that supports future development and prevents regressions.

## Current Testing State Analysis

### Existing Test Coverage (Good)
- **PHP Tests**: Basic test suite with PHPUnit setup
- **JavaScript Tests**: Jest configuration with WordPress mocks
- **Test Files Present**: 15+ test files covering core functionality
- **Infrastructure**: Proper bootstrap and setup files

### Missing Test Coverage (Needs Addition)
- **API Endpoints**: Limited coverage for newer REST API endpoints
- **Block Functionality**: Minimal block-specific testing
- **Integration Tests**: Limited cross-module testing
- **Edge Cases**: Guest/user transition scenarios
- **Error Handling**: API error conditions and fallbacks

## Testing Expansion Plan

### Group 1: API Endpoint Testing

**Missing API Tests:**
- Instagram Analyzer API (especially after Phase 2 REST migration)
- Analytics API endpoints
- User Value API endpoints
- Account Manager API endpoints

**Test Coverage Required:**
- Valid request/response cycles
- Parameter validation
- Permission checking
- Error handling
- Rate limiting
- Guest vs authenticated user scenarios

### Group 2: Block Integration Testing

**Blocks Needing Tests:**
- Caption Writer block (frontend functionality)
- Content Repurposer block (guest access critical)
- Account Manager block (user data display)
- Instagram Analyzer block (file upload, analysis)

**Test Scenarios:**
- Block renders correctly
- Block attributes handled properly
- Frontend JavaScript initializes
- API interactions work
- Guest vs logged-in behavior

### Group 3: State Management Testing

**Missing Coverage:**
- localStorage fallback scenarios
- Guest-to-user transitions
- State persistence across page loads
- Storage quota/availability handling
- Cross-application state sharing

### Group 4: Integration Testing

**Cross-Module Scenarios:**
- User registration → auto-login → state migration
- Guest usage → registration → data preservation
- Analytics collection across different modules
- GDPR compliance workflows

## Implementation Plan

### Step 1: Expand API Testing

**Create New Test Files:**
- `tests/test-instagram-analyzer-api.php`
- `tests/test-analytics-api.php`
- `tests/test-user-value-api.php`
- `tests/test-account-manager-api.php`

**Test Pattern for Each API:**
```php
<?php
class Test_Instagram_Analyzer_API extends WP_UnitTestCase {
    
    private $api;
    
    public function setUp(): void {
        parent::setUp();
        $this->api = new RWP_Creator_Suite_Instagram_Analyzer_API();
    }
    
    public function test_upload_endpoint_exists() {
        // Test endpoint registration
    }
    
    public function test_upload_requires_authentication() {
        // Test permission checking
    }
    
    public function test_upload_validates_file_type() {
        // Test input validation
    }
    
    public function test_upload_handles_large_files() {
        // Test edge cases
    }
    
    // Additional test methods...
}
```

### Step 2: Create Block Testing

**JavaScript Block Tests:**
- `tests/js/blocks/instagram-analyzer/edit.test.js`
- `tests/js/blocks/content-repurposer/edit.test.js`
- `tests/js/blocks/account-manager/edit.test.js`

**Test Pattern for Blocks:**
```javascript
import { render, screen } from '@testing-library/react';
import Edit from '../../../src/blocks/caption-writer/edit';

describe('Caption Writer Block', () => {
    
    test('renders placeholder in editor', () => {
        const props = {
            attributes: {},
            setAttributes: jest.fn(),
        };
        
        render(<Edit {...props} />);
        
        expect(screen.getByText(/Caption Writer/)).toBeInTheDocument();
    });
    
    test('handles attribute updates', () => {
        // Test attribute handling
    });
    
    // Additional tests...
});
```

### Step 3: Enhanced State Management Testing

**Expand Existing State Manager Tests:**
```javascript
describe('RWPEnhancedStateManager', () => {
    
    test('handles localStorage unavailable gracefully', () => {
        // Mock localStorage failure
        jest.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('Storage disabled');
        });
        
        const manager = new RWPEnhancedStateManager('test');
        
        // Should not crash and should show warning
        expect(manager.storageAvailable).toBe(false);
    });
    
    test('migrates guest data on user login', () => {
        // Test guest-to-user transition
    });
    
    test('cleans up expired guest data', () => {
        // Test data cleanup
    });
    
    // Additional tests...
});
```

### Step 4: Integration Testing

**Create Integration Test Scenarios:**
```php
<?php
class Test_User_Journey_Integration extends WP_UnitTestCase {
    
    public function test_guest_to_user_workflow() {
        // 1. Guest uses content repurposer
        // 2. Guest registers account
        // 3. Data migrates to user account
        // 4. User can access saved preferences
    }
    
    public function test_analytics_collection_workflow() {
        // 1. User interacts with blocks
        // 2. Analytics events collected
        // 3. Data aggregated properly
        // 4. Privacy settings respected
    }
    
    // Additional integration tests...
}
```

## Testing Checkpoints

### Test Group 1 (API Testing Expansion)
**Verify API Test Coverage:**
- [ ] All REST endpoints have test coverage
- [ ] Permission checks tested for each endpoint
- [ ] Parameter validation tested
- [ ] Error conditions tested
- [ ] Rate limiting tested
- [ ] Guest vs authenticated scenarios tested

### Test Group 2 (Block Testing)
**Verify Block Test Coverage:**
- [ ] Block edit components render properly
- [ ] Block attributes handled correctly
- [ ] Frontend JavaScript initialization tested
- [ ] Block-specific API interactions tested
- [ ] Guest vs logged-in behavior tested

### Test Group 3 (State Management)
**Verify State Management Testing:**
- [ ] localStorage failure scenarios tested
- [ ] Guest data persistence tested
- [ ] User login transitions tested
- [ ] Cross-application state sharing tested
- [ ] Storage cleanup tested

### Test Group 4 (Integration Testing)
**Verify Integration Scenarios:**
- [ ] Complete user journeys tested
- [ ] Cross-module interactions tested
- [ ] Data flow tested end-to-end
- [ ] Error recovery tested

## Test Infrastructure Improvements

### Enhanced Jest Configuration

**Update `package.json` Jest Config:**
```json
{
  "jest": {
    "preset": "@wordpress/jest-preset-default",
    "setupFilesAfterEnv": [
      "<rootDir>/tests/js/setup.js"
    ],
    "testEnvironment": "jsdom",
    "collectCoverageFrom": [
      "assets/js/**/*.js",
      "src/blocks/**/*.js",
      "!assets/js/**/*.min.js",
      "!**/node_modules/**",
      "!**/build/**"
    ],
    "coverageThreshold": {
      "global": {
        "branches": 70,
        "functions": 80,
        "lines": 80,
        "statements": 80
      }
    },
    "testPathIgnorePatterns": [
      "/node_modules/",
      "/build/"
    ]
  }
}
```

### PHP Test Coverage Improvements

**Update `phpunit.xml`:**
```xml
<phpunit>
    <testsuites>
        <testsuite name="RWP Creator Suite Complete Test Suite">
            <!-- Existing tests -->
            <file>tests/test-main-plugin.php</file>
            <file>tests/test-error-logger.php</file>
            <file>tests/test-registration-api.php</file>
            
            <!-- New API tests -->
            <file>tests/test-instagram-analyzer-api.php</file>
            <file>tests/test-analytics-api.php</file>
            <file>tests/test-account-manager-api.php</file>
            
            <!-- Integration tests -->
            <file>tests/test-user-journey-integration.php</file>
        </testsuite>
    </testsuites>
    
    <!-- Coverage reporting -->
    <logging>
        <log type="coverage-html" target="coverage/html"/>
        <log type="coverage-text" target="php://stdout"/>
    </logging>
</phpunit>
```

## Test Data Management

### Mock Data Creation

**Create Test Data Helpers:**
```php
<?php
class RWP_Test_Data_Helper {
    
    public static function create_test_user() {
        return wp_insert_user(array(
            'user_login' => 'testuser_' . wp_generate_password(8, false),
            'user_email' => 'test_' . wp_generate_password(8, false) . '@example.com',
            'user_pass' => 'password',
            'role' => 'subscriber'
        ));
    }
    
    public static function create_test_instagram_data() {
        // Return mock Instagram data structure
    }
    
    public static function create_test_analytics_data() {
        // Return mock analytics data
    }
}
```

## Success Criteria

✅ **Phase 5 Complete When:**
- All API endpoints have comprehensive test coverage
- All blocks have basic functionality tests
- State management edge cases are tested
- Integration scenarios are covered
- Test coverage reports show improvement
- All new tests pass consistently
- CI/CD pipeline (if applicable) includes all tests

## Post-Phase Validation

### Test Coverage Metrics
- [ ] PHP test coverage above 80% for critical modules
- [ ] JavaScript test coverage above 85% for block functionality
- [ ] All API endpoints tested for success and error cases
- [ ] All permission callbacks tested
- [ ] All validation functions tested

### Test Quality Checklist
- [ ] Tests are reliable and don't have false positives
- [ ] Tests cover both happy path and error conditions
- [ ] Tests are maintainable and well-documented
- [ ] Tests run quickly enough for development workflow
- [ ] Integration tests cover realistic user scenarios

### Functionality Confidence
- [ ] Can refactor code safely with test coverage
- [ ] New features can be added with confidence
- [ ] Regressions will be caught by test suite
- [ ] Edge cases are handled properly
- [ ] User workflows are protected

## Next Phase Preparation

Once Phase 5 is complete:
- Document test patterns for future development
- Set up automated test running (if not already configured)
- Consider performance testing for API endpoints
- Prepare for final documentation and cleanup phase

## Notes for AI Agent

- **Start with critical functionality**: Focus on API endpoints and core block functionality first
- Write tests that actually test meaningful behavior, not just code coverage
- Mock external dependencies properly (WordPress functions, localStorage, etc.)
- Test both success and failure scenarios for each feature
- Pay special attention to guest vs authenticated user scenarios
- Ensure tests are independent and can run in any order
- If existing tests fail after changes, fix the tests or the code as appropriate
- Use meaningful test names that describe what's being tested
- Document any complex test scenarios for future developers