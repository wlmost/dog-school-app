## Why

Deploying HomoCanis to shared hosting environments currently requires manual file transfer, directory creation, permission configuration, and .env setup. This process is error-prone, time-consuming, and requires technical expertise. Providers need an automated, foolproof installation process that can be completed with minimal technical knowledge while maintaining security and compatibility with the existing Docker-based development structure.

## What Changes

- Create build script to generate deployment-ready tar archive
- Develop interactive installation wizard for one-time setup on shared hosting
- Implement automated directory structure creation matching Docker environment
- Add .env configuration wizard with database connection testing
- Generate and configure .htaccess for security (prevent unauthorized directory access)
- Ensure directory structure compatibility (no code changes required between Docker and shared hosting)
- Provide rollback capability for failed installations

## Capabilities

### New Capabilities

- `deployment-package`: Build process that creates a deployment-ready tar archive containing all necessary application files, excluding development dependencies, with proper structure for shared hosting environments

- `installation-wizard`: Interactive CLI-based wizard that guides users through initial setup, including directory creation, permission verification, database configuration, .env generation, and security hardening

- `environment-setup`: Automated environment configuration that creates required directories, sets appropriate permissions, validates server requirements, and adapts .htaccess rules for shared hosting security

### Modified Capabilities

<!-- No existing capabilities are being modified -->

## Impact

**New Files:**
- Build script (e.g., `build-deployment.sh` or similar)
- Installation wizard script (e.g., `install.php` in deployment root)
- .htaccess template for shared hosting
- Deployment documentation

**Dependencies:**
- Must work with existing server-validation capability (requirements-check.php)
- Must preserve Docker-compatible directory structure
- May need to adjust .gitignore to exclude deployment artifacts
- Build process needs access to composer, npm for production builds

**User Experience:**
- Providers upload tar file to shared hosting
- Extract archive in target directory (e.g., `hundeschule.homocanis.de/`)
- Run installation wizard via browser or SSH
- Wizard validates requirements, configures database, generates .env
- Application becomes immediately functional post-wizard

**Security Considerations:**
- .htaccess must prevent access to sensitive directories (.env, storage/, etc.)
- Installation wizard should self-destruct or lock after completion
- Default credentials must be avoided
- Proper file/directory permissions enforced

**Deployment Workflow:**
1. Developer runs build script (creates tar archive)
2. Provider uploads tar to shared host
3. Provider extracts tar in web root subdirectory
4. Provider accesses installation wizard URL
5. Wizard completes setup, validates, locks itself
6. Application is live
