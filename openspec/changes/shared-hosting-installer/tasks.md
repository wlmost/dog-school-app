## 1. Build Script Foundation

- [x] 1.1 Create build-deployment.sh script in project root
- [x] 1.2 Add dependency checks (composer, npm, tar, gzip availability)
- [x] 1.3 Implement timestamp generation for archive filename
- [x] 1.4 Add cleanup function for temporary build directory
- [x] 1.5 Implement error handling with clear error messages

## 2. Build Script - Dependency Installation

- [x] 2.1 Add backend dependency installation (composer install --no-dev --optimize-autoloader)
- [x] 2.2 Add frontend dependency installation (npm ci)
- [x] 2.3 Add frontend build step (npm run build)
- [x] 2.4 Verify production dependencies installed correctly

## 3. Build Script - File Selection

- [x] 3.1 Create temporary build directory
- [x] 3.2 Copy backend/ directory with exclusions (node_modules, .git, .env, tests/)
- [x] 3.3 Copy frontend/dist/ built assets
- [x] 3.4 Copy .env.example as .env.template
- [x] 3.5 Add exclusion logic for docker/, openspec/, .git/
- [x] 3.6 Verify correct directory structure in temp build directory

## 4. .htaccess Templates Creation

- [x] 4.1 Create root .htaccess template (redirect to backend/public/)
- [x] 4.2 Create backend/public/.htaccess template (Laravel routing + security headers)
- [x] 4.3 Create backend/.htaccess template (deny all)
- [x] 4.4 Create storage/.htaccess template (deny all)
- [x] 4.5 Create frontend/.htaccess template (deny all)
- [x] 4.6 Add security headers (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection)
- [x] 4.7 Add commented-out HTTPS redirect rules to backend/public/.htaccess

## 5. Build Script - .htaccess Integration

- [x] 5.1 Copy .htaccess templates to temp build directory
- [x] 5.2 Place root .htaccess in temp build root
- [x] 5.3 Place backend/public/.htaccess in correct location
- [x] 5.4 Place protection .htaccess files in backend/, storage/, frontend/
- [x] 5.5 Verify all .htaccess files are in correct locations

## 6. Installation Wizard - Foundation

- [x] 6.1 Create install.php in project root
- [x] 6.2 Implement session-based multi-step form structure
- [x] 6.3 Add responsive HTML/CSS layout (mobile-friendly)
- [x] 6.4 Implement step progression logic
- [x] 6.5 Add installation lock check (install.lock file)
- [x] 6.6 Implement lock file creation on completion

## 7. Installation Wizard - Server Validation

- [x] 7.1 Integrate requirements-check.php validation logic
- [x] 7.2 Display server requirements check results
- [x] 7.3 Implement blocking on critical requirement failures
- [x] 7.4 Implement warning display for optional requirements
- [x] 7.5 Add "Proceed" button enabled only when requirements pass

## 8. Installation Wizard - Database Configuration

- [x] 8.1 Create database configuration form (host, port, database, username, password)
- [x] 8.2 Add default values (localhost, 3306)
- [x] 8.3 Implement database connection test using PDO
- [x] 8.4 Display connection success/failure with error messages
- [x] 8.5 Add database existence check
- [x] 8.6 Implement retry logic for failed connections

## 9. Installation Wizard - Application Configuration

- [x] 9.1 Create application settings form (APP_NAME, APP_URL, APP_ENV, timezone)
- [x] 9.2 Add default values (HomoCanis, auto-detected URL, production)
- [x] 9.3 Implement PHP timezone dropdown population
- [x] 9.4 Add URL validation
- [x] 9.5 Add environment selection (production/local)

## 10. Installation Wizard - .env Generation

- [x] 10.1 Read .env.template file
- [x] 10.2 Implement placeholder substitution with user input
- [x] 10.3 Generate random APP_KEY using PHP
- [x] 10.4 Write .env file to backend/ directory
- [x] 10.5 Set .env file permissions to 600
- [x] 10.6 Verify .env file created successfully

## 11. Installation Wizard - Environment Setup

- [x] 11.1 Create storage/ directory structure (app/public, framework/cache, framework/sessions, framework/views, logs)
- [x] 11.2 Create bootstrap/cache directory
- [x] 11.3 Set directory permissions to 775
- [x] 11.4 Verify directories are writable by web server
- [x] 11.5 Display permission warnings if chmod fails
- [x] 11.6 Provide manual chmod instructions on failure

## 12. Installation Wizard - Database Migration

- [x] 12.1 Detect Laravel artisan availability
- [x] 12.2 Execute php artisan migrate --force
- [x] 12.3 Capture and display migration output
- [x] 12.4 Implement migration success detection
- [x] 12.5 Handle migration failures with error display
- [x] 12.6 Add optional seeder execution checkbox
- [x] 12.7 Execute seeders if requested

## 13. Installation Wizard - Storage Symlink

- [x] 13.1 Execute php artisan storage:link
- [x] 13.2 Detect if symlink already exists
- [x] 13.3 Handle symlink creation failure
- [x] 13.4 Display manual symlink instructions on failure

## 14. Installation Wizard - Rollback Mechanism

- [x] 14.1 Implement rollback function (delete .env, drop tables, clean directories)
- [x] 14.2 Add rollback offer on critical failures
- [x] 14.3 Execute rollback on user confirmation
- [x] 14.4 Display rollback success confirmation
- [x] 14.5 Allow restarting installation after rollback

## 15. Installation Wizard - Completion

- [x] 15.1 Create install.lock file on successful completion
- [x] 15.2 Rename install.php to install.php.completed
- [x] 15.3 Display success screen with application URL
- [x] 15.4 Add security notice recommending installer deletion
- [x] 15.5 Implement automatic installer deletion button
- [x] 15.6 Display next steps (login, admin access)

## 16. Installation Wizard - Error Handling

- [x] 16.1 Implement detailed error message display
- [x] 16.2 Add recovery suggestions for common errors
- [x] 16.3 Create install.log file for logging
- [x] 16.4 Log all installation steps and errors
- [x] 16.5 Set install.log permissions to 600
- [x] 16.6 Display log file location on errors

## 17. Installation Wizard - Progress Persistence

- [x] 17.1 Save step progress to session after each step
- [x] 17.2 Save collected data to session
- [x] 17.3 Restore step and data from session on page load
- [x] 17.4 Handle session expiry gracefully
- [x] 17.5 Allow resuming from safe checkpoint after session loss

## 18. Build Script - Archive Creation

- [x] 18.1 Add install.php to temp build directory
- [x] 18.2 Set install.php permissions to 755
- [x] 18.3 Create tar.gz archive with timestamped filename
- [x] 18.4 Verify archive integrity (tar -tzf)
- [x] 18.5 Verify critical files present in archive (index.php, install.php, .htaccess)
- [x] 18.6 Display archive filename and file size on success

## 19. Build Script - Finalization

- [x] 19.1 Clean up temporary build directory after success
- [x] 19.2 Clean up partial artifacts on failure
- [x] 19.3 Add progress messages for each build step
- [x] 19.4 Display final success message with archive location
- [x] 19.5 Make build script executable (chmod +x build-deployment.sh)

## 20. Testing - Build Process

- [x] 20.1 Test build script execution from project root
- [x] 20.2 Verify tar archive created successfully
- [x] 20.3 Extract archive and verify directory structure
- [x] 20.4 Verify .htaccess files present in correct locations
- [x] 20.5 Verify install.php included in archive
- [x] 20.6 Verify .env.template included
- [x] 20.7 Verify development files excluded (node_modules, .git, docker/)

## 21. Testing - Installation Wizard

- [ ] 21.1 Test wizard loads in browser
- [ ] 21.2 Test server requirements validation step
- [ ] 21.3 Test database configuration with valid credentials
- [ ] 21.4 Test database configuration with invalid credentials
- [ ] 21.5 Test application settings form
- [ ] 21.6 Test .env file generation
- [ ] 21.7 Test database migration execution
- [ ] 21.8 Test storage symlink creation
- [ ] 21.9 Test installation completion and lock file creation
- [ ] 21.10 Test locked wizard (verify cannot reinstall)

## 22. Testing - Error Handling

- [ ] 22.1 Test build failure with missing composer
- [ ] 22.2 Test build failure with missing npm
- [ ] 22.3 Test wizard with failing server requirements
- [ ] 22.4 Test wizard with database connection failure
- [ ] 22.5 Test wizard with migration failure
- [ ] 22.6 Test rollback functionality
- [ ] 22.7 Verify error messages are clear and actionable

## 23. Testing - Security

- [ ] 23.1 Verify .env permissions are 600
- [ ] 23.2 Verify storage/.htaccess blocks direct access
- [ ] 23.3 Verify backend/.htaccess blocks direct access
- [ ] 23.4 Verify frontend/.htaccess blocks direct access
- [ ] 23.5 Verify install.lock prevents reinstallation
- [ ] 23.6 Verify install.php renamed/deleted after completion
- [ ] 23.7 Test security headers in backend/public/.htaccess

## 24. Documentation

- [x] 24.1 Create deployment guide in DEPLOYMENT.md
- [x] 24.2 Document build script usage
- [x] 24.3 Document installation wizard steps with screenshots
- [x] 24.4 Document manual deployment process as fallback
- [x] 24.5 Document troubleshooting common issues
- [x] 24.6 Document rollback procedure
- [x] 24.7 Add shared hosting requirements to README
- [x] 24.8 Document .htaccess customization options
