## Context

The HomoCanis application is a Laravel 11 application that needs to be deployable on shared hosting environments. Shared hosting typically has:
- Limited shell/SSH access
- Web-based file management (FTP/SFTP)
- Pre-installed PHP (version varies by host)
- MySQL databases with web-based management (phpMyAdmin)

Providers need to validate their environment before attempting deployment. Currently, there is no automated way to check requirements, leading to installation failures and support burden.

**Current State:**
- Application requires PHP 8.2+ (composer.json specifies `^8.2`)
- User requirement: PHP 8.4 specifically for shared hosting
- Dependencies include Laravel 11, Sanctum, dompdf, PayPal SDK
- No existing requirement validation tool

**Constraints:**
- Must run on shared hosting without shell access
- Must be runnable via web browser
- Cannot assume Composer is installed on shared host
- Must work before Laravel is fully bootstrapped

## Goals / Non-Goals

**Goals:**
- Create a standalone PHP script that validates server requirements
- Check PHP version compatibility (8.4.x)
- Verify all required PHP extensions
- Test MySQL connectivity and version
- Validate file system permissions for Laravel directories
- Provide clear, actionable output with remediation guidance
- Work without Composer or Laravel bootstrap

**Non-Goals:**
- Automated remediation of missing requirements
- Installation/deployment automation
- Performance benchmarking
- Post-installation health checks
- Configuration file generation

## Decisions

### 1. Script Location and Access Method

**Decision:** Create `backend/requirements-check.php` as a standalone web-accessible script.

**Rationale:**
- Shared hosting typically only allows web access
- Placing in `backend/` keeps it with application code
- Can be accessed via browser at `/requirements-check.php` before full deployment
- Standalone file works without Laravel bootstrap

**Alternatives Considered:**
- CLI-only script: Rejected - shared hosting may block shell access
- Artisan command: Rejected - requires Laravel bootstrap which may fail if requirements aren't met
- Root-level script: Rejected - keeps validation logic separate from application code

### 2. Extension Detection Strategy

**Decision:** Hardcode required extensions based on Laravel 11 + application dependencies, with clear version/date stamp.

**Rationale:**
- No Composer available on shared host to parse `composer.json`
- Extensions rarely change between minor releases
- Explicit list is easier to maintain and understand
- Can include comments explaining why each extension is needed

**Extensions to Check:**
- Core: `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `filter`, `hash`
- Laravel-specific: `bcmath` (optional but recommended)
- Application: `gd` or `imagick` (for image processing), `curl` (PayPal SDK), `zip` (dompdf)

**Alternatives Considered:**
- Parse composer.json: Rejected - file may not be uploaded yet, complex parsing without Composer
- Dynamic detection from installed packages: Rejected - packages not installed yet

### 3. Database Connection Testing

**Decision:** Optional test using environment variables or form input, not required for script to run.

**Rationale:**
- `.env` file may not exist yet during initial validation
- Database credentials are sensitive, shouldn't be in URL parameters
- Should be opt-in test

**Approach:**
- If `.env` exists and is readable, attempt connection
- Display form to manually enter credentials for testing
- Use PDO directly (not Laravel DB facade)
- Test connection + version check (MySQL 5.7+ recommended, 8.0+ ideal)

**Alternatives Considered:**
- Require database credentials: Rejected - script should work at earliest deployment stage
- Skip database check entirely: Rejected - MySQL connectivity is a common issue

### 4. Output Format

**Decision:** HTML output with color-coded pass/fail indicators and detailed explanations.

**Rationale:**
- Web-based access is primary use case
- HTML allows better formatting, colors, expandable sections
- Can include links to documentation
- Mobile-friendly for providers checking on tablets

**Format:**
- Summary section: overall pass/fail
- Detailed sections: PHP version, extensions, permissions, database
- Each check shows: ✓ Pass / ✗ Fail / ⚠ Warning
- Actionable remediation text for failures

**Alternatives Considered:**
- Plain text: Rejected - less readable
- JSON: Rejected - not user-friendly for non-technical providers
- Both HTML + JSON: Possible future enhancement

### 5. Permission Checks

**Decision:** Test write permissions for `storage/`, `bootstrap/cache/`, and `public/storage` (if exists).

**Rationale:**
- Laravel requires writable storage and cache directories
- Shared hosting often has permission issues
- Can test without modifying existing files

**Approach:**
- Attempt to create temporary test file in each directory
- Test both creation and deletion
- Report current permission octals (e.g., 755, 775)
- Suggest correct permissions (775 or 777 for directories)

### 6. PHP Version Validation

**Decision:** Require PHP 8.4.x specifically, warn on other versions.

**Rationale:**
- User specifically requested PHP 8.4 validation
- `composer.json` allows `^8.2` but shared hosting constraint is 8.4

**Approach:**
- Check `PHP_VERSION` constant
- Parse major.minor.patch
- Fail if < 8.4.0
- Warn if > 8.4.x (e.g., 8.5 or 9.0) with message about untested version

**Alternatives Considered:**
- Allow 8.2+: Rejected - contradicts user requirement
- Strict 8.4 only: Too restrictive for patch versions

## Risks / Trade-offs

**[Risk]** Hardcoded extension list becomes outdated when dependencies change  
→ **Mitigation:** Include version/date stamp in script, update as part of dependency updates, document update process

**[Risk]** Script accessible in production could leak system information  
→ **Mitigation:** Include warning to delete after installation, or move to protected directory, consider IP whitelist option

**[Risk]** False negatives if extension is present but misconfigured  
→ **Mitigation:** Use `extension_loaded()` for basic check, consider additional function tests for critical extensions

**[Risk]** Database credentials in `.env` before production security hardening  
→ **Mitigation:** Database test is optional, warn about credential security, don't log credentials

**[Trade-off]** Web-only access means no automation/CI use  
→ **Accepted:** Primary use case is manual validation by provider on shared host

**[Trade-off]** No auto-remediation means manual fixes required  
→ **Accepted:** Shared hosting requires provider/host to make changes, script guides but cannot fix
