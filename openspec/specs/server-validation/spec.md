# Server Validation

## Purpose

Automated validation of server requirements for HomoCanis application deployment on shared hosting environments. Validates PHP version, required extensions, file permissions, and database connectivity before installation to prevent deployment failures and reduce support burden.

## Requirements

### Requirement: PHP Version Validation

The validation script SHALL check that the server is running PHP 8.4.x and report the result.

#### Scenario: PHP 8.4.x is installed
- **WHEN** the script runs on a server with PHP 8.4.x
- **THEN** the PHP version check SHALL display as passed with the detected version number

#### Scenario: PHP version is below 8.4
- **WHEN** the script runs on a server with PHP version below 8.4.0
- **THEN** the PHP version check SHALL display as failed with an error message indicating the minimum required version

#### Scenario: PHP version is above 8.4.x
- **WHEN** the script runs on a server with PHP version 8.5 or higher
- **THEN** the PHP version check SHALL display a warning that the version is untested

### Requirement: PHP Extension Validation

The validation script SHALL check for all required PHP extensions and report which are present and which are missing.

#### Scenario: All required extensions are present
- **WHEN** all required extensions are installed and enabled
- **THEN** the extension check SHALL display as passed with a list of verified extensions

#### Scenario: Required extension is missing
- **WHEN** one or more required extensions are not installed or disabled
- **THEN** the extension check SHALL display as failed with a list of missing extensions and remediation guidance

#### Scenario: Optional extension is missing
- **WHEN** an optional but recommended extension is not installed
- **THEN** the extension check SHALL display a warning indicating the extension is recommended

### Requirement: Required Extensions List

The validation script SHALL check for the following PHP extensions:

**Required:**
- `json` - JSON parsing
- `mbstring` - Multi-byte string handling
- `openssl` - Encryption and secure connections
- `pdo` - Database abstraction layer
- `pdo_mysql` - MySQL database driver
- `tokenizer` - PHP tokenization
- `xml` - XML processing
- `ctype` - Character type checking
- `fileinfo` - File type detection
- `filter` - Input filtering
- `hash` - Hash functions
- `curl` - HTTP requests (PayPal SDK)
- `zip` - Archive handling (dompdf)

**Recommended:**
- `bcmath` - Arbitrary precision mathematics
- `gd` OR `imagick` - Image processing

#### Scenario: Extension check list is complete
- **WHEN** the script performs extension validation
- **THEN** it SHALL check for all extensions listed in this requirement

### Requirement: MySQL Database Connectivity

The validation script SHALL optionally test MySQL database connectivity and version when credentials are provided.

#### Scenario: Database credentials available from environment
- **WHEN** a `.env` file exists with database credentials
- **THEN** the script SHALL attempt to connect to the MySQL database using those credentials

#### Scenario: Manual database credential entry
- **WHEN** no `.env` file exists or database test is requested
- **THEN** the script SHALL provide a form to manually enter database credentials for testing

#### Scenario: Database connection succeeds
- **WHEN** connection to MySQL succeeds
- **THEN** the script SHALL display the MySQL version and connection status as passed

#### Scenario: Database connection fails
- **WHEN** connection to MySQL fails
- **THEN** the script SHALL display the connection error with remediation guidance

#### Scenario: No database credentials provided
- **WHEN** no database credentials are available
- **THEN** the script SHALL skip database testing and indicate it was not performed

### Requirement: MySQL Version Validation

When database connectivity testing is performed, the script SHALL validate the MySQL version.

#### Scenario: MySQL 8.0 or higher
- **WHEN** the connected database is MySQL 8.0 or higher
- **THEN** the version check SHALL display as passed

#### Scenario: MySQL 5.7
- **WHEN** the connected database is MySQL 5.7
- **THEN** the version check SHALL display a warning that MySQL 8.0+ is recommended

#### Scenario: MySQL below 5.7
- **WHEN** the connected database is MySQL version below 5.7
- **THEN** the version check SHALL display as failed indicating incompatibility

### Requirement: File Permission Validation

The validation script SHALL check write permissions for Laravel's required directories.

#### Scenario: All directories are writable
- **WHEN** `storage/`, `bootstrap/cache/`, and `public/storage` (if exists) are writable
- **THEN** the permission check SHALL display as passed

#### Scenario: Directory is not writable
- **WHEN** one or more required directories are not writable
- **THEN** the permission check SHALL display as failed with the directory path and suggested permissions

#### Scenario: Directory does not exist
- **WHEN** a required directory does not exist
- **THEN** the permission check SHALL indicate the directory is missing and needs to be created

### Requirement: Permission Testing Method

The validation script SHALL test write permissions by attempting to create and delete a temporary test file.

#### Scenario: Write permission test
- **WHEN** testing directory write permissions
- **THEN** the script SHALL create a temporary file, verify it was created, delete it, and verify deletion succeeded

#### Scenario: Permission octal display
- **WHEN** displaying permission check results
- **THEN** the script SHALL show current permission octals (e.g., 755, 775) and recommended permissions

### Requirement: HTML Output Format

The validation script SHALL provide output in HTML format with visual indicators for check results.

#### Scenario: Overall summary section
- **WHEN** the script completes all checks
- **THEN** it SHALL display a summary section showing overall pass/fail status

#### Scenario: Detailed check sections
- **WHEN** displaying validation results
- **THEN** the script SHALL organize results into sections: PHP Version, Extensions, Permissions, and Database

#### Scenario: Visual status indicators
- **WHEN** displaying individual check results
- **THEN** the script SHALL use visual indicators: ✓ for pass, ✗ for fail, ⚠ for warning

#### Scenario: Color-coded results
- **WHEN** displaying check results
- **THEN** the script SHALL use color coding (green for pass, red for fail, yellow/orange for warning)

#### Scenario: Mobile-responsive layout
- **WHEN** the script is accessed from any device
- **THEN** the HTML output SHALL be readable and usable on mobile, tablet, and desktop screens

### Requirement: Actionable Remediation Guidance

The validation script SHALL provide specific remediation steps for each failed or warning check.

#### Scenario: Failed check remediation
- **WHEN** a validation check fails
- **THEN** the script SHALL display actionable steps to resolve the issue

#### Scenario: Missing extension remediation
- **WHEN** a PHP extension is missing
- **THEN** the remediation SHALL include the extension name and typical installation methods

#### Scenario: Permission issue remediation
- **WHEN** a permission check fails
- **THEN** the remediation SHALL include the specific directory path and the recommended permission command

### Requirement: Standalone Execution

The validation script SHALL be executable as a standalone PHP file without Laravel framework dependencies.

#### Scenario: Pre-bootstrap execution
- **WHEN** the script is accessed before Laravel is installed or configured
- **THEN** it SHALL run successfully using only native PHP functions

#### Scenario: No Composer dependencies
- **WHEN** the script executes
- **THEN** it SHALL not require Composer autoloader or any external packages

#### Scenario: Direct web access
- **WHEN** accessed via web browser at `/requirements-check.php`
- **THEN** the script SHALL execute and display results

### Requirement: Security Considerations

The validation script SHALL include security considerations for production environments.

#### Scenario: Production deletion warning
- **WHEN** the script displays results
- **THEN** it SHALL include a prominent warning to delete or restrict access to the script after installation

#### Scenario: No credential logging
- **WHEN** database credentials are tested
- **THEN** the script SHALL NOT log or display credentials in plain text

#### Scenario: System information disclosure
- **WHEN** displaying system information
- **THEN** the script SHALL limit information disclosure to what is necessary for requirements validation
