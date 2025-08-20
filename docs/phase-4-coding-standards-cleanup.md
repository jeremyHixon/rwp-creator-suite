# Phase 4: WordPress Coding Standards Cleanup

**Priority**: MEDIUM - Important for maintainability and professionalism
**Estimated Time**: 3-4 hours
**Testing Required**: After each file update
**Dependencies**: Complete Phases 1, 2, and 3 first

## Overview

This phase addresses WordPress coding standards violations and inconsistencies found during the analysis. While these issues don't affect functionality, they impact code maintainability, professionalism, and compliance with WordPress development best practices.

## Issues to Address

### Group 1: Class Naming Convention Violations

**Critical Issue:**
- `src/modules/class-shortcodes.php:6` - Class name `Shortcodes` doesn't follow plugin prefix convention

**Required Fix:**
- Rename `Shortcodes` to `RWP_Creator_Suite_Shortcodes`
- Update all references to the class
- Follow WordPress plugin class naming standards

### Group 2: Code Formatting Inconsistencies

**Files with Formatting Issues:**
- Mixed array syntax (short `[]` vs long `array()`)
- Inconsistent spacing around operators and control structures
- Mixed indentation patterns (tabs vs spaces)
- Inconsistent brace placement

**Areas to Standardize:**
- Array syntax: Use long form `array()` for WordPress consistency
- Spacing: Follow WordPress standards exactly
- Indentation: Use tabs for indentation, spaces for alignment
- Braces: Follow WordPress brace placement rules

### Group 3: Documentation and Comment Standards

**Issues Found:**
- Inconsistent PHPDoc formatting
- Missing `@since` tags in some functions
- Inconsistent file headers
- Missing parameter type documentation

**Required Standards:**
- Complete PHPDoc blocks for all functions
- Consistent file headers with proper package information
- Proper `@since`, `@param`, `@return` documentation

## Implementation Plan

### Step 1: Fix Class Naming Violations

**File**: `src/modules/class-shortcodes.php`

**Current Class Declaration:**
```php
class Shortcodes {
```

**Fixed Class Declaration:**
```php
class RWP_Creator_Suite_Shortcodes {
```

**Files to Update for Class Reference:**
1. `rwp-creator-suite.php` - Main plugin file (if referenced)
2. Any other files that instantiate or reference the class

### Step 2: Standardize Array Syntax

**WordPress Standard**: Use long array syntax for consistency with WordPress core

**Pattern to Fix:**
```php
// Incorrect (short syntax)
$array = ['key' => 'value'];

// Correct (WordPress standard)
$array = array( 'key' => 'value' );
```

**Files to Review:**
- All PHP files in `src/` directory
- Focus on newer files that may use short syntax

### Step 3: Fix Spacing and Formatting

**WordPress Spacing Standards:**
```php
// Control structures
if ( $condition ) {
    $result = $value_one + $value_two;
}

// Function calls
$result = function_name( $param1, $param2 );

// Arrays
$array = array(
    'key1' => 'value1',
    'key2' => 'value2',
);
```

### Step 4: Standardize File Headers

**Required File Header Format:**
```php
<?php
/**
 * [Brief description]
 *
 * [Longer description if needed]
 *
 * @package    RWP_Creator_Suite
 * @subpackage RWP_Creator_Suite/[module]
 * @since      [version]
 */

defined( 'ABSPATH' ) || exit;
```

### Step 5: Complete PHPDoc Documentation

**Function Documentation Standard:**
```php
/**
 * [Brief description]
 *
 * [Longer description if needed]
 *
 * @since [version]
 * @param [type] $param [description]
 * @return [type] [description]
 */
public function function_name( $param ) {
    // Function body
}
```

## Testing Checkpoints

### Test Group 1 (After Class Rename)
**Verify Shortcodes Functionality:**
- [ ] Shortcodes still register properly
- [ ] `[rwp-info]` shortcode works
- [ ] No fatal errors from class name change
- [ ] All references updated correctly

### Test Group 2 (After Array Syntax Standardization)
**Verify No Syntax Errors:**
- [ ] All PHP files parse without errors
- [ ] Arrays function properly in all contexts
- [ ] No functionality changes from syntax updates
- [ ] All blocks and admin pages load properly

### Test Group 3 (After Spacing/Formatting Updates)
**Verify Code Quality:**
- [ ] All files follow WordPress coding standards
- [ ] No functionality affected by formatting changes
- [ ] Code is more readable and consistent
- [ ] No syntax errors introduced

### Test Group 4 (After Documentation Updates)
**Verify Documentation Quality:**
- [ ] All functions have proper PHPDoc blocks
- [ ] File headers are consistent and complete
- [ ] Documentation generates properly (if using doc generators)
- [ ] All parameter and return types documented

## Detailed Fix Patterns

### Array Syntax Conversion

**Automated Search/Replace Patterns:**
```bash
# Find short array syntax (for manual review)
grep -r "\[.*=>" src/

# Look for specific patterns that need conversion
grep -r "= \[" src/
grep -r "return \[" src/
```

**Manual Conversion Required** (automated tools may break complex arrays)

### Spacing Standardization

**Common Patterns to Fix:**
```php
// Before (incorrect spacing)
if($condition){
    $result=$value1+$value2;
    function_call($param1,$param2);
}

// After (WordPress standard)
if ( $condition ) {
    $result = $value1 + $value2;
    function_call( $param1, $param2 );
}
```

### PHPDoc Completion

**Files Needing Documentation Review:**
- All API classes (`*-api.php` files)
- All main module classes
- All trait files
- Block render files (PHP functions)

## Validation Tools

### PHP CodeSniffer Setup
```bash
# Install WordPress coding standards
composer require --dev wp-coding-standards/wpcs
./vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs

# Run standards check
./vendor/bin/phpcs --standard=WordPress src/
```

### Manual Review Checklist

**For Each PHP File:**
- [ ] File header present and complete
- [ ] Class names follow plugin prefix convention
- [ ] Functions have complete PHPDoc blocks
- [ ] Array syntax follows WordPress standards
- [ ] Spacing follows WordPress standards
- [ ] Indentation uses tabs, alignment uses spaces
- [ ] No trailing whitespace

## Success Criteria

✅ **Phase 4 Complete When:**
- All class names follow WordPress plugin prefix convention
- All array syntax uses WordPress standard long form
- All spacing follows WordPress coding standards exactly
- All files have proper headers and documentation
- PHP CodeSniffer passes with WordPress standards
- No functionality has been affected by formatting changes

## Post-Phase Validation

### Coding Standards Checklist
- [ ] PHP CodeSniffer passes with `--standard=WordPress`
- [ ] All classes use proper `RWP_Creator_Suite_*` naming
- [ ] All arrays use `array()` syntax consistently
- [ ] All spacing follows WordPress standards
- [ ] All files have proper headers
- [ ] All functions documented with PHPDoc

### Functionality Preservation Checklist
- [ ] All blocks work in editor and frontend
- [ ] All API endpoints function properly
- [ ] All admin pages load and work
- [ ] Shortcodes function correctly
- [ ] User registration and login work
- [ ] No PHP errors in logs

### Code Quality Checklist
- [ ] Code is more readable and consistent
- [ ] Documentation is comprehensive
- [ ] Follows WordPress best practices
- [ ] Ready for WordPress.org submission (if applicable)
- [ ] Maintainable for future developers

## Next Phase Preparation

Once Phase 4 is complete:
- Document final code quality improvements
- Note any additional WordPress compliance items
- Prepare for Phase 5: Testing & Documentation
- Consider any performance optimizations discovered

## Notes for AI Agent

- **Be Conservative**: Only change formatting, don't modify logic
- Test after each group of changes to ensure no functionality breaks
- Use WordPress coding standards exactly as specified
- Pay attention to array syntax - complex nested arrays need careful conversion
- Don't change functionality while fixing formatting
- If unsure about a formatting rule, follow WordPress core code examples
- Document any edge cases or unusual patterns discovered
- Preserve all existing comments while improving documentation