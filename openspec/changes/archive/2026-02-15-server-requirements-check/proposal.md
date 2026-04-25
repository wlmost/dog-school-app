## Why

Providers need to install the HomoCanis dog school application on shared hosting servers. Before deployment, they must verify that the hosting environment meets all PHP 8.4 and MySQL requirements. Without automated validation, installations may fail silently or exhibit runtime errors that are difficult to diagnose.

## What Changes

- Create a standalone PHP script that validates server requirements against application dependencies
- Check PHP version (8.4.x required)
- Verify required PHP extensions (Laravel + application-specific extensions)
- Validate MySQL availability and version compatibility
- Check file system permissions for storage and cache directories
- Provide clear, actionable output for missing requirements

## Capabilities

### New Capabilities

- `server-validation`: Automated validation of PHP version, extensions, database connectivity, and file permissions for shared hosting environments

### Modified Capabilities

<!-- No existing capabilities are being modified -->

## Impact

**New Files:**
- Validation script (likely `server-check.php` or similar in root or `/backend`)

**Dependencies:**
- Must check against Laravel 11.x requirements
- Must validate MySQL (provider's constraint)
- Should reference `composer.json` to identify required PHP extensions

**User Experience:**
- Providers can run the script before full installation
- Clear pass/fail reporting with remediation suggestions
- Reduces support burden from failed installations
