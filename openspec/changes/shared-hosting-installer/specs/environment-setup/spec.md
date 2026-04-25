## ADDED Requirements

### Requirement: Directory Structure Creation

The environment setup SHALL create all required Laravel directories with proper permissions.

#### Scenario: Storage directory structure
- **WHEN** environment setup runs
- **THEN** it SHALL create storage/app/public, storage/framework/cache, storage/framework/sessions, storage/framework/views, storage/logs directories

#### Scenario: Bootstrap cache directory
- **WHEN** environment setup runs
- **THEN** it SHALL create bootstrap/cache directory if it doesn't exist

#### Scenario: Public storage directory
- **WHEN** environment setup runs
- **THEN** it SHALL ensure public/storage exists (will be symlinked later by wizard)

#### Scenario: Nested directory creation
- **WHEN** creating directory paths
- **THEN** the setup SHALL create all parent directories recursively (mkdir -p behavior)

### Requirement: Permission Management

The environment setup SHALL set appropriate permissions for directories and files.

#### Scenario: Writable directory permissions
- **WHEN** setting permissions on storage and cache directories
- **THEN** the setup SHALL use 775 (rwxrwxr-x) to allow web server write access

#### Scenario: Configuration file permissions
- **WHEN** .env file exists
- **THEN** the setup SHALL set permissions to 600 (rw-------) for security

#### Scenario: Log file permissions
- **WHEN** log files exist in storage/logs
- **THEN** the setup SHALL set permissions to 664 (rw-rw-r--) for web server write access

#### Scenario: Public directory permissions
- **WHEN** setting permissions on public directory
- **THEN** the setup SHALL use 755 (rwxr-xr-x) for web server read access

#### Scenario: Permission setting verification
- **WHEN** permissions are set
- **THEN** the setup SHALL verify the operation succeeded and report failures

### Requirement: Apache .htaccess Generation

The environment setup SHALL generate and place .htaccess files for Apache-based security.

#### Scenario: Root directory .htaccess
- **WHEN** generating .htaccess files
- **THEN** the setup SHALL create a root .htaccess that redirects all requests to backend/public/

#### Scenario: Backend public .htaccess
- **WHEN** generating .htaccess files
- **THEN** the setup SHALL create backend/public/.htaccess with Laravel rewrite rules and security headers

#### Scenario: Storage protection .htaccess
- **WHEN** generating .htaccess files
- **THEN** the setup SHALL create storage/.htaccess with "Deny from all" to block direct access

#### Scenario: Backend root protection .htaccess
- **WHEN** generating .htaccess files
- **THEN** the setup SHALL create backend/.htaccess with "Deny from all" to block direct access to application code

#### Scenario: Frontend protection .htaccess
- **WHEN** generating .htaccess files
- **THEN** the setup SHALL create frontend/.htaccess with "Deny from all" since frontend assets are built into backend/public

### Requirement: Security Headers Configuration

The .htaccess files SHALL include security headers to protect against common vulnerabilities.

#### Scenario: Content type protection
- **WHEN** .htaccess is generated for backend/public
- **THEN** it SHALL include "Header set X-Content-Type-Options 'nosniff'"

#### Scenario: Clickjacking protection
- **WHEN** .htaccess is generated for backend/public
- **THEN** it SHALL include "Header set X-Frame-Options 'SAMEORIGIN'"

#### Scenario: XSS protection
- **WHEN** .htaccess is generated for backend/public
- **THEN** it SHALL include "Header set X-XSS-Protection '1; mode=block'"

#### Scenario: HTTPS enforcement option
- **WHEN** .htaccess is generated for backend/public
- **THEN** it SHALL include commented-out HTTPS redirect rules that can be enabled

### Requirement: Laravel Rewrite Rules

The backend/public/.htaccess SHALL include proper Laravel rewrite rules for routing.

#### Scenario: Front controller routing
- **WHEN** .htaccess is generated
- **THEN** it SHALL include RewriteRule to route all non-file requests to index.php

#### Scenario: Existing file handling
- **WHEN** a request is for an existing file
- **THEN** the .htaccess SHALL serve the file directly without routing through Laravel

#### Scenario: Existing directory handling
- **WHEN** a request is for an existing directory
- **THEN** the .htaccess SHALL serve it directly without routing through Laravel

### Requirement: Root Directory Request Routing

The root .htaccess SHALL properly route requests to the Laravel public directory.

#### Scenario: Request forwarding to backend/public
- **WHEN** a request comes to the root domain
- **THEN** the root .htaccess SHALL rewrite it to backend/public/

#### Scenario: Static asset handling
- **WHEN** a request is for a static asset (CSS, JS, images)
- **THEN** the root .htaccess SHALL route it correctly to backend/public/

#### Scenario: API request routing
- **WHEN** a request is for /api/
- **THEN** the root .htaccess SHALL route it to backend/public/index.php

### Requirement: Server Requirements Integration

The environment setup SHALL validate server compatibility using the server-validation capability.

#### Scenario: Server validation execution
- **WHEN** environment setup runs
- **THEN** it SHALL call the server-validation capability to verify requirements

#### Scenario: Validation failure handling
- **WHEN** server validation fails
- **THEN** the setup SHALL report failing checks and abort setup

#### Scenario: Validation success confirmation
- **WHEN** server validation passes
- **THEN** the setup SHALL log success and proceed with directory/permission setup

### Requirement: .htaccess Template System

The environment setup SHALL use templates for generating .htaccess files.

#### Scenario: Template file existence
- **WHEN** generating .htaccess files
- **THEN** the setup SHALL use predefined templates for each .htaccess type

#### Scenario: Dynamic value substitution
- **WHEN** generating .htaccess from template
- **THEN** the setup SHALL substitute placeholders (e.g., {APP_URL}) with actual values

#### Scenario: Template not found handling
- **WHEN** a required .htaccess template is missing
- **THEN** the setup SHALL report error and provide manual .htaccess content

### Requirement: Existing Configuration Preservation

The environment setup SHALL preserve existing .htaccess files if they exist.

#### Scenario: Existing .htaccess backup
- **WHEN** an .htaccess file already exists
- **THEN** the setup SHALL create a backup (.htaccess.backup) before overwriting

#### Scenario: User confirmation for overwrite
- **WHEN** an .htaccess file already exists
- **THEN** the setup SHALL ask for confirmation before overwriting

#### Scenario: Skip existing files option
- **WHEN** running setup on existing installation
- **THEN** the setup SHALL offer option to skip .htaccess generation

### Requirement: File Ownership Verification

The environment setup SHALL verify that files are owned by the correct user.

#### Scenario: Web server user detection
- **WHEN** verifying ownership
- **THEN** the setup SHALL detect the web server user (e.g., www-data, apache)

#### Scenario: Ownership mismatch warning
- **WHEN** files are not owned by the web server user
- **THEN** the setup SHALL display a warning and suggest chown commands

#### Scenario: Ownership cannot be changed
- **WHEN** running in shared hosting without shell access
- **THEN** the setup SHALL skip ownership checks and display informational message

### Requirement: Directory Writability Testing

The environment setup SHALL test that required directories are writable by the web server.

#### Scenario: Write test execution
- **WHEN** verifying writability
- **THEN** the setup SHALL attempt to create a test file in each critical directory

#### Scenario: Write test success
- **WHEN** test file creation succeeds
- **THEN** the setup SHALL delete the test file and mark directory as writable

#### Scenario: Write test failure
- **WHEN** test file creation fails
- **THEN** the setup SHALL report the directory and suggest permission fixes

### Requirement: Setup Status Reporting

The environment setup SHALL provide clear feedback on setup progress and results.

#### Scenario: Progress indicators
- **WHEN** setup is running
- **THEN** it SHALL display status for each directory/file operation

#### Scenario: Success summary
- **WHEN** setup completes successfully
- **THEN** it SHALL display a summary of created directories, set permissions, and generated .htaccess files

#### Scenario: Error reporting
- **WHEN** any setup step fails
- **THEN** it SHALL report the specific operation that failed and the reason

#### Scenario: Partial success handling
- **WHEN** some operations succeed but others fail
- **THEN** the setup SHALL report both successes and failures separately

### Requirement: Idempotent Operation

The environment setup SHALL be safe to run multiple times without causing errors.

#### Scenario: Re-run on existing structure
- **WHEN** setup is run on an already-configured environment
- **THEN** it SHALL skip existing directories and files without errors

#### Scenario: Missing directory recreation
- **WHEN** some required directories are deleted after initial setup
- **THEN** re-running setup SHALL recreate only the missing directories

#### Scenario: Permission correction
- **WHEN** permissions were manually changed incorrectly
- **THEN** re-running setup SHALL correct them to required values
