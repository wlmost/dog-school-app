## ADDED Requirements

### Requirement: Build Script Execution

The build script SHALL be executable from the project root and generate a deployment-ready tar archive.

#### Scenario: Successful build
- **WHEN** developer runs the build script from project root
- **THEN** the script SHALL complete successfully and create a timestamped tar.gz file

#### Scenario: Build fails due to missing dependencies
- **WHEN** composer or npm is not available
- **THEN** the script SHALL fail with a clear error message indicating the missing dependency

#### Scenario: Build directory already exists
- **WHEN** a previous build's temporary directory exists
- **THEN** the script SHALL clean up the old directory before proceeding

### Requirement: Dependency Installation

The build script SHALL install production dependencies for both backend and frontend.

#### Scenario: Backend dependency installation
- **WHEN** the build script runs
- **THEN** it SHALL execute `composer install --no-dev --optimize-autoloader` in the backend directory

#### Scenario: Frontend dependency installation and build
- **WHEN** the build script runs
- **THEN** it SHALL execute `npm ci` followed by `npm run build` in the frontend directory

#### Scenario: Development dependencies excluded
- **WHEN** dependencies are installed
- **THEN** development-only packages SHALL NOT be included in the deployment package

### Requirement: File Selection and Exclusion

The build script SHALL copy application files while excluding development and system files.

#### Scenario: Application files included
- **WHEN** creating the deployment package
- **THEN** the script SHALL include backend/, frontend/dist/, and all necessary application files

#### Scenario: Development files excluded
- **WHEN** creating the deployment package
- **THEN** the script SHALL exclude node_modules/, .git/, docker/, openspec/, tests/, and .env files

#### Scenario: Configuration templates included
- **WHEN** creating the deployment package
- **THEN** the script SHALL include .env.example renamed as .env.template

### Requirement: Directory Structure Preservation

The deployment package SHALL maintain the exact directory structure used in Docker development.

#### Scenario: Directory structure matches Docker
- **WHEN** the deployment package is extracted
- **THEN** the directory structure SHALL be identical to the Docker environment (backend/, frontend/, etc.)

#### Scenario: Relative paths unchanged
- **WHEN** the application runs from the deployment package
- **THEN** all relative paths in code SHALL work without modification

### Requirement: Apache Configuration Generation

The build script SHALL generate .htaccess files for Apache-based shared hosting.

#### Scenario: Root .htaccess created
- **WHEN** generating Apache configuration
- **THEN** the script SHALL create a root .htaccess that redirects requests to backend/public/

#### Scenario: Security .htaccess files created
- **WHEN** generating Apache configuration
- **THEN** the script SHALL create deny-all .htaccess files for storage/, backend/ root, and frontend/ directories

#### Scenario: Laravel public .htaccess enhanced
- **WHEN** generating Apache configuration
- **THEN** the script SHALL include security headers (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection) in backend/public/.htaccess

### Requirement: Installation Wizard Inclusion

The build script SHALL include the installation wizard script in the deployment package.

#### Scenario: Install script included
- **WHEN** creating the deployment package
- **THEN** the script SHALL include install.php at the root level

#### Scenario: Install script is executable
- **WHEN** the deployment package is extracted
- **THEN** install.php SHALL have appropriate permissions (755)

### Requirement: Archive Creation

The build script SHALL create a compressed tar archive with a timestamped filename.

#### Scenario: Archive filename includes timestamp
- **WHEN** the archive is created
- **THEN** the filename SHALL include the current date and time (e.g., homocanis-deployment-20260215-143000.tar.gz)

#### Scenario: Archive is compressed
- **WHEN** creating the archive
- **THEN** the script SHALL use gzip compression to minimize file size

#### Scenario: Archive contains complete application
- **WHEN** the archive is extracted
- **THEN** it SHALL contain all files necessary to run the application without additional downloads

### Requirement: Build Artifact Cleanup

The build script SHALL clean up temporary files after successful archive creation.

#### Scenario: Temporary directory removed
- **WHEN** the archive is successfully created
- **THEN** the script SHALL delete the temporary build directory

#### Scenario: Failed build cleanup
- **WHEN** the build fails
- **THEN** the script SHALL clean up partial artifacts and report the error

### Requirement: Build Verification

The build script SHALL verify the created archive contains required files.

#### Scenario: Critical files verified
- **WHEN** the archive is created
- **THEN** the script SHALL verify presence of backend/public/index.php, install.php, and .htaccess

#### Scenario: Archive integrity check
- **WHEN** the archive is created
- **THEN** the script SHALL verify the archive can be listed without errors

### Requirement: Build Output and Logging

The build script SHALL provide clear output and logging of the build process.

#### Scenario: Progress indication
- **WHEN** the build script runs
- **THEN** it SHALL display progress messages for each major step (dependencies, file copying, archive creation)

#### Scenario: Success confirmation
- **WHEN** the build completes successfully
- **THEN** the script SHALL display the archive filename and file size

#### Scenario: Error reporting
- **WHEN** any build step fails
- **THEN** the script SHALL display a clear error message indicating which step failed and why
